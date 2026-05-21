/**
 * Task Export with Image Bundling
 * Handles exporting tasks as ZIP files with all referenced images
 */

class TaskExporter {
  constructor() {
    this.zipReady = typeof JSZip !== 'undefined';
    if (!this.zipReady) {
      console.warn('JSZip not loaded. Export will work but without image bundling.');
    }
  }

  /**
   * Build task export object
   */
  buildTaskExport(task) {
    const taskType = task.task_type;
    const rawDifficulty = (task.task_difficulty || 'medium').toString().toLowerCase();
    const taskDifficulty = ['basic', 'medium', 'hard'].includes(rawDifficulty) ? rawDifficulty : 'medium';
    const options = Array.isArray(task.options)
      ? task.options.map((opt) => ({
          text: opt.text || '',
          image_url: opt.image_url || '',
          is_correct: !!opt.is_correct
        }))
      : [];

    // For code_random_complex and code_reading, ensure max_iterations is a number (not null)
    // to prevent losing iteration counts during export/import
    let maxIterationsValue = null;
    if (typeof task.max_iterations === 'number' && task.max_iterations > 0) {
      maxIterationsValue = task.max_iterations;
    } else if (taskType === 'code_random_complex' || taskType === 'code_reading') {
      // Default to 3 for code_random_complex, 1 for code_reading
      maxIterationsValue = taskType === 'code_random_complex' ? 3 : 1;
    }

    return {
      version: '3.0',
      task_type: taskType,
      task_difficulty: taskDifficulty,
      manual_review_required: typeof task.manual_review_required === 'number'
        ? task.manual_review_required
        : (task.manual_review_required ? 1 : 0),
      title: task.title,
      task_text: task.task_text || '',
      description: task.description || '',
      max_attempts: typeof task.max_attempts === 'number' ? task.max_attempts : 1,
      max_iterations: maxIterationsValue,
      show_solution: typeof task.show_solution === 'number' ? task.show_solution : (task.show_solution ? 1 : 0),
      show_solution_code: typeof task.show_solution_code === 'number' ? task.show_solution_code : (task.show_solution_code ? 1 : 0),
      min_keywords_required: typeof task.min_keywords_required === 'number' ? task.min_keywords_required : null,
      question_text: task.question_text || '',
      image_url: task.image_url || '',
      code_template: task.code_template || '',
      solution_code: task.solution_code || '',
      randomizer_code: task.randomizer_code || '',
      test_cases: task.test_cases || null,
      hint1: task.hint1 || '',
      hint2: task.hint2 || '',
      hint3: task.hint3 || '',
      stoff: task.stoff || '',
      correct_answer: task.correct_answer || '',
      variable_overrides: task.variable_overrides || null,
      folderstructure: task.folderstructure ? 1 : 0,
      options
    };
  }

  /**
   * Fetch all real files from a folder-based task via API
   * Returns array of { path, content } objects
   */
  async fetchFolderFiles(taskId, solutionMode = false) {
    try {
      const modeParam = solutionMode ? '&solution_mode=1' : '';
      const res = await fetch(`../api/tasks/get-folder-files.php?task_id=${taskId}&include_content=1${modeParam}`);
      if (!res.ok) return [];
      const data = await res.json();
      if (!data.ok || !Array.isArray(data.files)) return [];

      // Collect only real (non-virtual) files recursively
      const realFiles = [];
      const collectFiles = (items, prefix = '') => {
        for (const item of items) {
          const itemPath = prefix ? `${prefix}/${item.name}` : item.name;
          if (item.type === 'file' && !item.virtual) {
            realFiles.push({ path: item.path || itemPath, content: typeof item.content === 'string' ? item.content : null });
          } else if (item.type === 'folder' && Array.isArray(item.children)) {
            collectFiles(item.children, itemPath);
          }
        }
      };
      collectFiles(data.files);

      // Fetch content of each real file
      const results = [];
      for (const fileEntry of realFiles) {
        const filePath = fileEntry.path;
        if (fileEntry.content !== null) {
          results.push({ path: filePath, content: fileEntry.content });
          continue;
        }

        try {
          const readRes = await fetch(`../api/tasks/folder-manage.php?action=read&task_id=${taskId}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ path: filePath })
          });
          if (!readRes.ok) continue;
          const readData = await readRes.json();
          if (readData.ok) {
            results.push({ path: filePath, content: readData.content });
          }
        } catch (e) {
          console.warn(`Could not read folder file ${filePath}:`, e);
        }
      }
      return results;
    } catch (e) {
      console.warn('fetchFolderFiles failed:', e);
      return [];
    }
  }

  /**
   * Extract all image URLs from a task
   */
  extractImageUrls(task) {
    const urls = new Set();
    
    if (task.image_url) {
      urls.add(task.image_url);
    }
    
    if (Array.isArray(task.options)) {
      task.options.forEach((opt) => {
        if (opt.image_url) {
          urls.add(opt.image_url);
        }
      });
    }
    
    return Array.from(urls);
  }

  /**
   * Download image and return as blob
   */
  async downloadImage(url) {
    try {
      const response = await fetch(url);
      if (!response.ok) {
        console.warn(`Failed to download image: ${url}`);
        return null;
      }
      return await response.blob();
    } catch (err) {
      console.error(`Error downloading image ${url}:`, err);
      return null;
    }
  }

  /**
   * Extract filename from image URL
   */
  getImageFilename(url) {
    try {
      const urlObj = new URL(url, window.location.origin);
      const pathname = urlObj.pathname;
      const filename = pathname.split('/').pop();
      return filename || 'image.jpg';
    } catch {
      return 'image.jpg';
    }
  }

  /**
   * Export single task as ZIP
   */
  async exportSingleTask(task) {
    try {
      if (!this.zipReady) {
        // Fallback: export as JSON without images
        const exportData = this.buildTaskExport(task);
        const blob = new Blob([JSON.stringify(exportData, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `task_${task.title.replace(/[^a-z0-9]/gi, '_').toLowerCase()}.json`;
        a.click();
        URL.revokeObjectURL(url);
        return;
      }

      const zip = new JSZip();
      const taskExport = this.buildTaskExport(task);
      const imageUrls = this.extractImageUrls(task);
      const imageMap = {}; // Maps original URL to new filename in ZIP

      // Download and add images to ZIP
      for (const url of imageUrls) {
        if (url.startsWith('http')) {
          const blob = await this.downloadImage(url);
          if (blob) {
            const filename = this.getImageFilename(url);
            zip.file(`images/${filename}`, blob);
            imageMap[url] = `images/${filename}`;
          }
        } else if (url.startsWith('data:')) {
          // Skip data URLs for now
          console.warn('Data URL images not supported in ZIP export');
        }
      }

      // Update image paths in task export
      if (taskExport.image_url && imageMap[taskExport.image_url]) {
        taskExport.image_url = imageMap[taskExport.image_url];
      }
      if (taskExport.options) {
        taskExport.options.forEach((opt) => {
          if (opt.image_url && imageMap[opt.image_url]) {
            opt.image_url = imageMap[opt.image_url];
          }
        });
      }

      // Bundle folder files if folderstructure is enabled
      if (task.folderstructure && task.id) {
        const folderFiles = await this.fetchFolderFiles(task.id);
        for (const { path, content } of folderFiles) {
          zip.file(`folder_files/${path}`, content);
        }

        const solutionFiles = await this.fetchFolderFiles(task.id, true);
        for (const { path, content } of solutionFiles) {
          zip.file(`solution_files/${path}`, content);
        }
      }

      // Add task JSON
      zip.file('task.json', JSON.stringify(taskExport, null, 2));

      // Generate and download ZIP
      const zipBlob = await zip.generateAsync({ type: 'blob' });
      const url = URL.createObjectURL(zipBlob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `task_${task.title.replace(/[^a-z0-9]/gi, '_').toLowerCase()}.zip`;
      a.click();
      URL.revokeObjectURL(url);
    } catch (err) {
      console.error('Export error:', err);
      alert('Export failed: ' + err.message);
    }
  }

  /**
   * Export multiple tasks as ZIP
   */
  async exportMultipleTasks(tasks) {
    try {
      if (!this.zipReady) {
        // Fallback: export as JSON without images
        const exportData = tasks.map((t) => this.buildTaskExport(t));
        const blob = new Blob([JSON.stringify(exportData, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `tasks_export_${tasks.length}_tasks.json`;
        a.click();
        URL.revokeObjectURL(url);
        return;
      }

      const zip = new JSZip();
      const imageMap = {}; // Global image map

      // Process each task
      const tasksFolder = zip.folder('tasks');
      for (let i = 0; i < tasks.length; i++) {
        const task = tasks[i];
        const taskExport = this.buildTaskExport(task);
        const imageUrls = this.extractImageUrls(task);

        // Download and add images to ZIP
        for (const url of imageUrls) {
          if (!imageMap[url] && url.startsWith('http')) {
            const blob = await this.downloadImage(url);
            if (blob) {
              const filename = this.getImageFilename(url);
              zip.file(`images/${filename}`, blob);
              imageMap[url] = `../images/${filename}`;
            }
          }
        }

        // Update image paths in task export
        if (taskExport.image_url && imageMap[taskExport.image_url]) {
          taskExport.image_url = imageMap[taskExport.image_url];
        }
        if (taskExport.options) {
          taskExport.options.forEach((opt) => {
            if (opt.image_url && imageMap[opt.image_url]) {
              opt.image_url = imageMap[opt.image_url];
            }
          });
        }

        // Bundle folder files for this task
        if (task.folderstructure && task.id) {
          const folderFiles = await this.fetchFolderFiles(task.id);
          const taskFolderFilesDir = `folder_files/task_${i + 1}/`;
          for (const { path, content } of folderFiles) {
            zip.file(taskFolderFilesDir + path, content);
          }
          if (folderFiles.length > 0) {
            taskExport._folder_files_dir = taskFolderFilesDir;
          }

          const solutionFiles = await this.fetchFolderFiles(task.id, true);
          const taskSolutionFilesDir = `solution_files/task_${i + 1}/`;
          for (const { path, content } of solutionFiles) {
            zip.file(taskSolutionFilesDir + path, content);
          }
          if (solutionFiles.length > 0) {
            taskExport._solution_files_dir = taskSolutionFilesDir;
          }
        }

        // Add task JSON to tasks folder
        const taskFilename = `task_${i + 1}_${task.title.replace(/[^a-z0-9]/gi, '_').toLowerCase()}.json`;
        tasksFolder.file(taskFilename, JSON.stringify(taskExport, null, 2));
      }

      // Add index/manifest
      zip.file('manifest.json', JSON.stringify({
        version: '3.0',
        task_count: tasks.length,
        tasks: tasks.map((t, i) => ({
          index: i + 1,
          id: t.id,
          title: t.title,
          type: t.task_type
        })),
        exported_at: new Date().toISOString()
      }, null, 2));

      // Generate and download ZIP
      const zipBlob = await zip.generateAsync({ type: 'blob' });
      const url = URL.createObjectURL(zipBlob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `tasks_export_${tasks.length}_tasks.zip`;
      a.click();
      URL.revokeObjectURL(url);
    } catch (err) {
      console.error('Export error:', err);
      alert('Export failed: ' + err.message);
    }
  }
}

// Create global instance
window.taskExporter = new TaskExporter();
