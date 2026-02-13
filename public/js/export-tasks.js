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
   * Normalize task type for export /import compatibility
   */
  normalizeTaskType(task) {
    const rawType = task.task_type || task.problem_type || 'code';
    if (rawType === 'code_completion' || rawType === 'code_fix') return 'code';
    if (rawType === 'essay') return 'free_text';
    return rawType;
  }

  /**
   * Build task export object
   */
  buildTaskExport(task) {
    const taskType = this.normalizeTaskType(task);
    const options = Array.isArray(task.options)
      ? task.options.map((opt) => ({
          text: opt.text || '',
          image_url: opt.image_url || '',
          is_correct: !!opt.is_correct
        }))
      : [];

    return {
      version: '2.0',
      task_type: taskType,
      problem_type: task.problem_type || taskType,
      title: task.title,
      description: task.description || '',
      position: task.position || null,
      max_attempts: typeof task.max_attempts === 'number' ? task.max_attempts : 1,
      show_solution: typeof task.show_solution === 'number' ? task.show_solution : 1,
      min_keywords_required: typeof task.min_keywords_required === 'number' ? task.min_keywords_required : null,
      question_text: task.question_text || '',
      image_url: task.image_url || '',
      code_template: task.code_template || task.starter_code || '',
      solution_code: task.solution_code || '',
      validation_mode: task.validation_mode || '',
      test_cases: task.test_cases || null,
      hint1: task.hint1 || '',
      hint2: task.hint2 || '',
      hint3: task.hint3 || '',
      stoff: task.stoff || '',
      keywords: task.keywords || '',
      correct_answer: task.correct_answer || '',
      variable_overrides: task.variable_overrides || '',
      options
    };
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

        // Add task JSON to tasks folder
        const taskFilename = `task_${i + 1}_${task.title.replace(/[^a-z0-9]/gi, '_').toLowerCase()}.json`;
        tasksFolder.file(taskFilename, JSON.stringify(taskExport, null, 2));
      }

      // Add index/manifest
      zip.file('manifest.json', JSON.stringify({
        version: '2.0',
        task_count: tasks.length,
        tasks: tasks.map((t, i) => ({
          index: i + 1,
          id: t.id,
          title: t.title,
          type: this.normalizeTaskType(t)
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
