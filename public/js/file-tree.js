// file-tree.js - Project file structure display and management
// Shows default folder structure: ProjectName.py + images/ + includes/

class FileTreeManager {
  constructor(containerId, startExpanded = false) {
    this.container = document.getElementById(containerId);
    this.projectId = null;
    this.projectName = null;
    this.isExpanded = startExpanded;
  }

  /**
   * Initialize file tree with default structure
   */
  initializeDefaultStructure(projectName) {
    this.projectName = projectName.replace(/\s+/g, '_');
    
    const structure = {
      name: projectName,
      type: 'root',
      children: [
        {
          name: `${this.projectName}.py`,
          type: 'file',
          icon: '🐍'
        },
        {
          name: 'images',
          type: 'folder',
          icon: '📁',
          children: [
            { name: '(placeholder)', type: 'folder', icon: '─' }
          ]
        },
        {
          name: 'includes',
          type: 'folder',
          icon: '📁',
          children: [
            { name: '(placeholder)', type: 'folder', icon: '─' }
          ]
        }
      ]
    };

    return structure;
  }

  /**
   * Render file tree
   */
  render(structure) {
    if (!this.container) return;

    // Render just the tree structure (toggle UI is in parent HTML)
    const html = `
      <div class="file-tree">
        ${this.renderNode(structure, 0)}
      </div>
    `;

    this.container.innerHTML = html;

    // Setup toggle
  }

  /**
   * Recursively render tree nodes
   */
  renderNode(node, depth) {
    if (node.type === 'root') {
      return node.children.map((child, idx) => this.renderNode(child, depth)).join('');
    }

    const indent = depth * 20;
    let html = `<div class="file-tree-item" style="margin-left: ${indent}px;">`;
    html += `<span class="file-tree-icon">${node.icon}</span>`;
    html += `<span class="file-tree-name">${escapeHtml(node.name)}</span>`;
    
    if (node.type === 'folder' && node.children) {
      html += `<button class="file-tree-expand" data-expanded="false">⊕</button>`;
    }
    
    html += `</div>`;

    if (node.type === 'folder' && node.children) {
      html += `<div class="file-tree-children" style="display: none;">`;
      node.children.forEach(child => {
        html += this.renderNode(child, depth + 1);
      });
      html += `</div>`;
    }

    return html;
  }

  /**
   * Format and escape HTML
   */
  escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }
}

// Export
window.FileTreeManager = FileTreeManager;
