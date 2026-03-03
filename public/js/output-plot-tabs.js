/**
 * Output/Plot Tab Navigation
 * Dynamisches Umschalten zwischen Output und Plot
 */

export function initOutputPlotTabs() {
  const tabs = document.querySelectorAll('.output-plot-tab');
  const outputPanel = document.getElementById('output-container');
  const plotPanel = document.getElementById('plot-container');
  
  if (!tabs.length || !outputPanel || !plotPanel) {
    console.log('[OutputPlotTabs] Components not found, skipping init');
    return;
  }
  
  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      const targetTab = tab.getAttribute('data-tab');
      
      // Update button states
      tabs.forEach(t => {
        t.classList.toggle('active', t.getAttribute('data-tab') === targetTab);
      });
      
      // Update panel visibility
      if (targetTab === 'output') {
        outputPanel.classList.add('active');
        plotPanel.classList.remove('active');
      } else if (targetTab === 'plot') {
        outputPanel.classList.remove('active');
        plotPanel.classList.add('active');
      }
    });
  });
  
  console.log('[OutputPlotTabs] Initialized');
}

/**
 * Helper: Switch to Output tab
 */
export function switchToOutput() {
  const tab = document.querySelector('.output-plot-tab[data-tab="output"]');
  if (tab) tab.click();
}

/**
 * Helper: Switch to Plot tab
 */
export function switchToPlot() {
  const tab = document.querySelector('.output-plot-tab[data-tab="plot"]');
  if (tab) tab.click();
}
