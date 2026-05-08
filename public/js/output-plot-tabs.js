/**
 * Output/Plot Tab Navigation
 * Dynamisches Umschalten zwischen Output und Plot
 */

export function initOutputPlotTabs() {
  const tabs = document.querySelectorAll('.output-plot-tab');
  const plotTab = document.querySelector('.output-plot-tab[data-tab="plot"]');
  const tabsContainer = document.getElementById('output-plot-tabs');
  const outputPanel = document.getElementById('output-container');
  const plotPanel = document.getElementById('plot-container');
  const isProjectMode = document.body?.dataset?.pyideMode === 'projects';

  if (!tabs.length || !outputPanel || !plotPanel) {
    console.log('[OutputPlotTabs] Components not found, skipping init');
    return;
  }

  function setActiveTab(targetTab) {
    tabs.forEach((tab) => {
      tab.classList.toggle('active', tab.getAttribute('data-tab') === targetTab);
    });

    if (targetTab === 'plot') {
      outputPanel.classList.remove('active');
      plotPanel.classList.add('active');
    } else {
      outputPanel.classList.add('active');
      plotPanel.classList.remove('active');
    }
  }

  function hasPlotContent() {
    return !!plotPanel.querySelector('.plot-card, img, canvas, svg');
  }

  function syncPlotTabVisibility() {
    if (!plotTab) return;
    const hadPlot = plotTab.style.display !== 'none';
    const showPlotTab = hasPlotContent();
    plotTab.style.display = showPlotTab ? '' : 'none';

    // In projects, keep full output height until a plot actually exists.
    if (tabsContainer && isProjectMode) {
      tabsContainer.classList.toggle('hidden-when-no-plot', !showPlotTab);
    }

    // Auto-switch to Plot tab when new plot content appears
    if (showPlotTab && !hadPlot) {
      setActiveTab('plot');
    }

    if (!showPlotTab && plotPanel.classList.contains('active')) {
      setActiveTab('output');
    }
  }

  tabs.forEach(tab => {
    tab.addEventListener('click', () => {
      const targetTab = tab.getAttribute('data-tab');

      if (targetTab === 'plot' && plotTab && plotTab.style.display === 'none') {
        return;
      }

      setActiveTab(targetTab === 'plot' ? 'plot' : 'output');
    });
  });

  const observer = new MutationObserver(() => {
    syncPlotTabVisibility();
  });
  observer.observe(plotPanel, { childList: true, subtree: true });

  setActiveTab('output');
  syncPlotTabVisibility();

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
  if (tab && tab.style.display !== 'none') tab.click();
}
