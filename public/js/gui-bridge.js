/**
 * GUI-Bridge für Python idegui integration
 * Verwaltet:
 * - Tab-Navigation (Output ↔ Plot)
 * - GUI Container Lifecycle
 * - Python → JS Kommunikation
 */

export class GUIBridge {
  constructor() {
    this.tabButtons = document.querySelectorAll('.output-plot-tab');
    this.guiContainer = document.getElementById('gui-container');
    this.outputContainer = document.getElementById('output-container');
    this.plotContainer = document.getElementById('plot-container');
    this.outputPlotSection = document.getElementById('output-plot-section');
    
    this.currentTab = 'output';
    this.hasGUI = false;
    
    if (this.tabButtons.length > 0) {
      this.initTabs();
    }
  }
  
  /**
   * Initialize tab switching for Output/Plot
   */
  initTabs() {
    this.tabButtons.forEach(btn => {
      btn.addEventListener('click', (e) => {
        const tabName = e.target.getAttribute('data-tab');
        this.switchTab(tabName);
      });
    });
  }
  
  /**
   * Switch between Output and Plot tabs
   */
  switchTab(tabName) {
    if (tabName === this.currentTab) return;
    
    // Update active button
    this.tabButtons.forEach(btn => {
      btn.classList.toggle('active', btn.getAttribute('data-tab') === tabName);
    });
    
    // Update active panel
    const panels = document.querySelectorAll('.output-plot-panel');
    panels.forEach(panel => {
      const isActive = (tabName === 'output' && panel === this.outputContainer) ||
                       (tabName === 'plot' && panel === this.plotContainer);
      panel.classList.toggle('active', isActive);
    });
    
    this.currentTab = tabName;
    console.log('[GUIBridge] Switched to tab:', tabName);
  }
  
  /**
   * Show GUI when Python code imports idegui
   */
  showGUI() {
    if (!this.guiContainer) return;
    this.guiContainer.classList.add('active');
    this.hasGUI = true;
    console.log('[GUIBridge] GUI activated');
  }
  
  /**
   * Hide GUI
   */
  hideGUI() {
    if (!this.guiContainer) return;
    this.guiContainer.classList.remove('active');
    this.hasGUI = false;
    console.log('[GUIBridge] GUI deactivated');
  }
  
  /**
   * Clear GUI
   */
  clearGUI() {
    if (this.guiContainer) {
      this.guiContainer.innerHTML = '';
    }
  }
  
  /**
   * Append element to GUI container
   */
  appendToGUI(element) {
    if (!this.guiContainer) return;
    this.guiContainer.appendChild(element);
    this.showGUI();
  }
  
  /**
   * Get GUI container element
   */
  getGUIContainer() {
    return this.guiContainer;
  }
}

// Export singleton instance
export const guiBridge = new GUIBridge();
