(function () {
  var KEY = 'enma_theme';
  var THEMES = ['light', 'dark', 'midnight', 'navy', 'nord'];
  var LABELS = { light: 'Light', dark: 'Dark', midnight: 'Midnight', navy: 'Navy', nord: 'Nord' };

  function normalizeTheme(value) {
    var v = (value || '').toLowerCase();
    return THEMES.indexOf(v) !== -1 ? v : 'dark';
  }

  function applyTheme(theme) {
    var normalized = normalizeTheme(theme);
    var root = document.documentElement;
    root.setAttribute('data-enma-theme', normalized);
    root.setAttribute('data-theme', normalized);
    for (var i = 0; i < THEMES.length; i++) {
      root.classList.remove('theme-' + THEMES[i]);
      if (document.body) {
        document.body.classList.remove('theme-' + THEMES[i]);
      }
    }
    root.classList.add('theme-' + normalized);
    if (document.body) {
      document.body.classList.add('theme-' + normalized);
      document.body.setAttribute('data-theme', normalized);
    }
    try { localStorage.setItem(KEY, normalized); } catch (e) {}
    var label = document.getElementById('enma-theme-label');
    if (label) {
      label.textContent = LABELS[normalized] || normalized;
    }
  }

  function getCurrentTheme() {
    var fromAttr = normalizeTheme(document.documentElement.getAttribute('data-enma-theme'));
    if (fromAttr) return fromAttr;
    var fromStorage = '';
    try { fromStorage = localStorage.getItem(KEY) || ''; } catch (e) { fromStorage = ''; }
    return normalizeTheme(fromStorage);
  }

  function cycleTheme() {
    var current = getCurrentTheme();
    var idx = THEMES.indexOf(current);
    var next = THEMES[(idx + 1) % THEMES.length];
    applyTheme(next);
  }

  function init() {
    applyTheme(getCurrentTheme());
    var toggle = document.getElementById('enma-theme-toggle');
    if (toggle) {
      toggle.addEventListener('click', cycleTheme);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  window.enmaTheme = { applyTheme: applyTheme, cycleTheme: cycleTheme, getCurrentTheme: getCurrentTheme };
})();
