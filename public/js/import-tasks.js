/**
 * Task Import Handler
 * Handles importing tasks from ZIP files (with images) or JSON files
 */

class TaskImporter {
  constructor() {
    this.zipReady = typeof JSZip !== 'undefined';
  }

  getProblemType(taskType) {
    const map = {
      code: 'code_completion',
      code_ui: 'code_completion',
      code_reading: 'code_completion',
      code_random_complex: 'code_completion',
      single_choice: 'multiple_choice',
      multiple_choice: 'multiple_choice',
      free_text: 'essay'
    };
    return map[taskType] || 'code_completion';
  }

  validateTaskSchema(task) {
    if (!task || typeof task !== 'object') {
      throw new Error('Invalid task format');
    }

    if (!task.version) {
      throw new Error('Missing version field (requires v3.0 export)');
    }

    const version = String(task.version).trim();
    if (!version.startsWith('3.')) {
      throw new Error(`Unsupported export version: ${version}`);
    }

    const allowedTaskTypes = ['code', 'code_ui', 'single_choice', 'multiple_choice', 'free_text', 'code_reading', 'code_random_complex'];
    if (!allowedTaskTypes.includes(task.task_type)) {
      throw new Error(`Invalid task_type: ${task.task_type}`);
    }

    const taskDifficulty = (task.task_difficulty ?? 'medium').toString().toLowerCase();
    const allowedTaskDifficulties = ['basic', 'medium', 'hard'];
    if (!allowedTaskDifficulties.includes(taskDifficulty)) {
      throw new Error(`Invalid task_difficulty: ${task.task_difficulty}`);
    }

    if (!task.title || String(task.title).trim() === '') {
      throw new Error('Missing title');
    }

    if (['single_choice', 'multiple_choice', 'free_text', 'code_random_complex', 'code_reading'].includes(task.task_type)) {
      if (!task.task_text || String(task.task_text).trim() === '') {
        throw new Error(`Missing task_text for ${task.task_type}`);
      }
    }

    if (task.task_type === 'code_random_complex') {
      if (!task.code_template || String(task.code_template).trim() === '') {
        throw new Error('Missing code_template for code_random_complex');
      }
      if (!task.solution_code || String(task.solution_code).trim() === '') {
        throw new Error('Missing solution_code for code_random_complex');
      }
      if (!task.randomizer_code || String(task.randomizer_code).trim() === '') {
        throw new Error('Missing randomizer_code for code_random_complex');
      }
    }

    if (task.task_type === 'code_reading') {
      const overrides = task.variable_overrides;
      const hasOverrides = overrides !== null && overrides !== '' && overrides !== '[]' && overrides !== '{}';
      if (!hasOverrides) {
        throw new Error('Missing variable_overrides for code_reading');
      }
    }

    if (['single_choice', 'multiple_choice'].includes(task.task_type)) {
      if (!Array.isArray(task.options) || task.options.length === 0) {
        throw new Error(`Missing options for ${task.task_type}`);
      }
    }

    if (task.test_cases && typeof task.test_cases !== 'string' && !Array.isArray(task.test_cases)) {
      throw new Error('Invalid test_cases format');
    }
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
        this.validateTaskSchema(task);
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
        // Handle legacy format: if test_cases is empty but variable_overrides exists,
        // it might be old intelligent test format that needs to stay as variable_overrides
        let normalizedTestCases = taskWithImages.test_cases && typeof taskWithImages.test_cases !== 'string'
          ? JSON.stringify(taskWithImages.test_cases)
          : taskWithImages.test_cases;

        const variableOverridesValue = taskWithImages.variable_overrides;
        const variableOverridesNormalized = (variableOverridesValue === null || variableOverridesValue === '' || variableOverridesValue === undefined)
          ? null
          : (typeof variableOverridesValue === 'string' ? variableOverridesValue : JSON.stringify(variableOverridesValue));

        const taskType = taskWithImages.task_type;
        const taskDifficultyRaw = (taskWithImages.task_difficulty ?? 'medium').toString().toLowerCase();
        const taskDifficulty = ['basic', 'medium', 'hard'].includes(taskDifficultyRaw) ? taskDifficultyRaw : 'medium';

        // Ensure max_iterations is a proper number for code_random_complex and code_reading
        // to prevent losing iteration counts during import and ensure consistency with originals
        let maxIterationsForPayload = null;
        if (typeof taskWithImages.max_iterations === 'number' && taskWithImages.max_iterations > 0) {
          maxIterationsForPayload = taskWithImages.max_iterations;
        } else if (taskType === 'code_random_complex') {
          // Default to 3 for code_random_complex if not provided
          maxIterationsForPayload = 3;
        } else if (taskType === 'code_reading') {
          // Default to 1 for code_reading if not provided
          maxIterationsForPayload = 1;
        }

        const taskPayload = {
          assignment_id: taskWithImages.assignment_id ? parseInt(taskWithImages.assignment_id, 10) : null,
          title: taskWithImages.title,
          description: taskWithImages.description || '',
          max_attempts: taskWithImages.max_attempts ? parseInt(taskWithImages.max_attempts, 10) : 1,
          max_iterations: maxIterationsForPayload,
          show_solution: taskWithImages.show_solution !== undefined ? parseInt(taskWithImages.show_solution, 10) : 1,
          show_solution_code: taskWithImages.show_solution_code !== undefined ? parseInt(taskWithImages.show_solution_code, 10) : 0,
          min_keywords_required: taskWithImages.min_keywords_required ? parseInt(taskWithImages.min_keywords_required, 10) : null,
          task_type: taskType,
          task_difficulty: taskDifficulty,
          problem_type: this.getProblemType(taskType),
          task_text: taskWithImages.task_text || '',
          code_template: taskWithImages.code_template || '',
          solution_code: taskWithImages.solution_code || '',
          randomizer_code: taskWithImages.randomizer_code || '',
          test_cases: normalizedTestCases || null,
          hint1: taskWithImages.hint1 || '',
          hint2: taskWithImages.hint2 || '',
          hint3: taskWithImages.hint3 || '',
          stoff: taskWithImages.stoff || '',
          question_text: taskWithImages.question_text || '',
          image_url: taskWithImages.image_url || '',
          correct_answer: taskWithImages.correct_answer || '',
          // For code_random_complex: preserve variable_overrides if it contains <random> markers
          // Only set to null if it's code_random_complex AND variable_overrides is actually empty
          variable_overrides: (taskType === 'code_random_complex' && !variableOverridesNormalized)
            ? null
            : variableOverridesNormalized,
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
