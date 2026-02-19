/**
 * Task Import Handler
 * Handles importing tasks from ZIP files (with images) or JSON files
 */

class TaskImporter {
  constructor() {
    this.zipReady = typeof JSZip !== 'undefined';
  }

  /**
   * Process imported tasks
   */
  async processImport(file) {
    try {
      if (file.type === 'application/zip' || file.name.endsWith('.zip')) {
        return await this.processZipImport(file);
      } else if (file.type === 'application/json' || file.name.endsWith('.json')) {
        return await this.processJsonImport(file);
      } else {
        throw new Error('Unsupported file type. Please upload a .zip or .json file.');
      }
    } catch (err) {
      console.error('Import error:', err);
      throw err;
    }
  }

  /**
   * Process JSON import
   */
  async processJsonImport(file) {
    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = (e) => {
        try {
          const data = JSON.parse(e.target.result);
          const tasks = Array.isArray(data) ? data : [data];
          resolve({
            tasks,
            images: {} // No images in JSON
          });
        } catch (err) {
          reject(new Error('Invalid JSON format: ' + err.message));
        }
      };
      reader.onerror = () => reject(new Error('Failed to read file'));
      reader.readAsText(file);
    });
  }

  /**
   * Process ZIP import
   */
  async processZipImport(file) {
    if (!this.zipReady) {
      throw new Error('JSZip library not loaded. Please reload the page.');
    }

    try {
      const zip = await JSZip.loadAsync(file);
      const tasks = [];
      const images = {}; // Maps relative path to blob

      // Extract images
      zip.folder('images')?.forEach((relativePath, file) => {
        // Just track that we have images; we'll process them later
      });

      // Process manifest if it exists
      let manifest = null;
      if (zip.file('manifest.json')) {
        try {
          const manifestData = await zip.file('manifest.json').async('text');
          manifest = JSON.parse(manifestData);
        } catch (err) {
          console.warn('Failed to parse manifest:', err);
        }
      }

      // Extract single task.json
      if (zip.file('task.json')) {
        const taskData = await zip.file('task.json').async('text');
        const task = JSON.parse(taskData);
        tasks.push(task);
      }

      // Extract tasks from tasks/ folder
      if (zip.folder('tasks')) {
        const taskFiles = [];
        zip.folder('tasks').forEach((relativePath, file) => {
          if (relativePath.endsWith('.json')) {
            taskFiles.push({ path: relativePath, file });
          }
        });

        // Sort by path to maintain order
        taskFiles.sort((a, b) => a.path.localeCompare(b.path));

        for (const { file } of taskFiles) {
          const taskData = await file.async('text');
          const task = JSON.parse(taskData);
          tasks.push(task);
        }
      }

      // Extract images
      if (zip.folder('images')) {
        await Promise.all(
          Object.entries(zip.folder('images').files)
            .filter(([path, file]) => !file.dir)
            .map(async ([path, file]) => {
              const blob = await file.async('blob');
              images[path] = blob;
            })
        );
      }

      if (tasks.length === 0) {
        throw new Error('No tasks found in ZIP file');
      }

      return { tasks, images, manifest };
    } catch (err) {
      if (err.message.includes('No tasks found')) {
        throw err;
      }
      throw new Error('Failed to process ZIP file: ' + err.message);
    }
  }

  /**
   * Map image path in task to actual blob
   */
  resolveImagePath(imagePath, images) {
    if (!imagePath) return null;

    // Try exact match
    if (images[imagePath]) {
      return images[imagePath];
    }

    // Try with different path separators
    const normalized = imagePath.replace(/\\/g, '/');
    if (images[normalized]) {
      return images[normalized];
    }

    // Try stripping leading directories
    const filename = imagePath.split('/').pop();
    for (const [path, blob] of Object.entries(images)) {
      if (path.endsWith(filename)) {
        return blob;
      }
    }

    return null;
  }

  /**
   * Upload image blob and return URL
   */
  async uploadImage(blob, filename) {
    const formData = new FormData();
    formData.append('image', blob, filename);

    try {
      const response = await fetch('../api/admin/tasks/upload_image.php', {
        method: 'POST',
        body: formData
      });

      if (!response.ok) {
        throw new Error(`Upload failed with status ${response.status}`);
      }

      const data = await response.json();
      if (!data.ok) {
        throw new Error(data.error || 'Upload failed');
      }

      return data.image_url;
    } catch (err) {
      console.error(`Failed to upload ${filename}:`, err);
      return null;
    }
  }

  /**
   * Get filename from image path
   */
  getFilename(imagePath) {
    if (!imagePath) return 'image.jpg';
    return imagePath.split('/').pop();
  }

  /**
   * Import tasks
   */
  async importTasks(tasks, images) {
    const results = {
      created: [],
      failed: []
    };

    for (let i = 0; i < tasks.length; i++) {
      const task = tasks[i];

      try {
        // Process and upload images
        const taskWithImages = { ...task };

        // Main task image
        if (task.image_url && images[task.image_url]) {
          const blob = this.resolveImagePath(task.image_url, images);
          if (blob) {
            const filename = this.getFilename(task.image_url);
            const uploadedUrl = await this.uploadImage(blob, filename);
            if (uploadedUrl) {
              taskWithImages.image_url = uploadedUrl;
            }
          }
        }

        // Option images
        if (Array.isArray(taskWithImages.options)) {
          for (const option of taskWithImages.options) {
            if (option.image_url && images[option.image_url]) {
              const blob = this.resolveImagePath(option.image_url, images);
              if (blob) {
                const filename = this.getFilename(option.image_url);
                const uploadedUrl = await this.uploadImage(blob, filename);
                if (uploadedUrl) {
                  option.image_url = uploadedUrl;
                }
              }
            }
          }
        }

        // Create/update task via API
        const normalizedTestCases = taskWithImages.test_cases && typeof taskWithImages.test_cases !== 'string'
          ? JSON.stringify(taskWithImages.test_cases)
          : taskWithImages.test_cases;

        const taskPayload = {
          assignment_id: taskWithImages.assignment_id ? parseInt(taskWithImages.assignment_id, 10) : null,
          title: taskWithImages.title,
          description: taskWithImages.description || '',
          max_attempts: taskWithImages.max_attempts ? parseInt(taskWithImages.max_attempts, 10) : 1,
          show_solution: taskWithImages.show_solution ? parseInt(taskWithImages.show_solution, 10) : 1,
          min_keywords_required: taskWithImages.min_keywords_required ? parseInt(taskWithImages.min_keywords_required, 10) : null,
          task_type: taskWithImages.task_type || taskWithImages.problem_type || 'code',
          problem_type: taskWithImages.problem_type || taskWithImages.task_type || 'code',
          code_template: taskWithImages.code_template || '',
          solution_code: taskWithImages.solution_code || '',
          validation_mode: taskWithImages.validation_mode || '',
          test_cases: normalizedTestCases || null,
          hint1: taskWithImages.hint1 || '',
          hint2: taskWithImages.hint2 || '',
          hint3: taskWithImages.hint3 || '',
          stoff: taskWithImages.stoff || '',
          question_text: taskWithImages.question_text || '',
          image_url: taskWithImages.image_url || '',
          keywords: taskWithImages.keywords || '',
          correct_answer: taskWithImages.correct_answer || '',
          variable_overrides: (taskWithImages.task_type === 'code_random_complex' || taskWithImages.problem_type === 'code_random_complex')
            ? null
            : (taskWithImages.variable_overrides || null),
          options: taskWithImages.options || []
        };

        // If task has ID, update it; otherwise create new
        if (taskWithImages.id) {
          const response = await fetch('../api/tasks/update.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: taskWithImages.id, ...taskPayload })
          });

          if (!response.ok) throw new Error(`Update failed: ${response.status}`);
          const result = await response.json();
          if (!result.ok) throw new Error(result.error || 'Update failed');

          results.created.push({
            title: task.title,
            type: taskWithImages.task_type,
            action: 'updated'
          });
        } else {
          const response = await fetch('../api/tasks/create.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(taskPayload)
          });

          if (!response.ok) throw new Error(`Create failed: ${response.status}`);
          const result = await response.json();
          if (!result.ok) throw new Error(result.error || 'Create failed');

          results.created.push({
            title: task.title,
            type: taskWithImages.task_type,
            action: 'created',
            id: result.id
          });
        }
      } catch (err) {
        console.error(`Failed to import task ${i + 1}:`, err);
        results.failed.push({
          title: task.title || `Task ${i + 1}`,
          error: err.message
        });
      }
    }

    return results;
  }
}

// Create global instance
window.taskImporter = new TaskImporter();
