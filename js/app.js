/**
 * 2Rad Wächter Köln – frontend logic
 * - Language selection: German, English, Kölsch (all three selectable manually)
 * - Automatic default:
 *     1. Stored user choice (localStorage) always takes priority
 *     2. Kölsch only if the visitor's IP resolves to Cologne (geo check)
 *     3. Otherwise: German if browser/OS language is German, else English
 * - Loads provider data (currently dummy data via api/providers.php) and renders
 *   overall totals as well as individual provider cards
 */

(function () {
  'use strict';

  var STORAGE_KEY = '2rad-lang';
  var DATA_URL = 'api/providers.php';
  var GEO_URL = 'api/geo.php';
  var SUPPORTED_LANGS = ['de', 'en', 'ko'];

  var translations = {
    de: {
      siteTitle: '2Rad Wächter Köln',
      heroTitle: 'Alle Bike- & eScooter-Sharing-Anbieter in Köln',
      heroSubtitle: 'Auf einen Blick: wie viele Fahrräder und eScooter aktuell in Köln zum Ausleihen verfügbar sind.',
      lastUpdated: 'Stand:',
      totalVehicles: 'Fahrzeuge insgesamt',
      totalBikes: 'Fahrräder',
      totalEscooters: 'eScooter',
      providersHeading: 'Anbieter in Köln',
      disclaimer: 'Alle Angaben sind Dummy-Daten zu Demonstrationszwecken und ohne Gewähr.',
      typeBike: 'Fahrrad',
      typeEscooter: 'eScooter',
      bikesLabel: 'Räder',
      escootersLabel: 'Scooter',
      loading: 'Daten werden geladen …',
      loadError: 'Daten konnten nicht geladen werden.',
      themeToggleLabel: 'Farbschema wechseln'
    },
    en: {
      siteTitle: '2Wheel Guardian Cologne',
      heroTitle: 'All bike- & eScooter-sharing providers in Cologne',
      heroSubtitle: 'One overview of how many bikes and eScooters are currently available for rent in Cologne.',
      lastUpdated: 'Last updated:',
      totalVehicles: 'Vehicles in total',
      totalBikes: 'Bikes',
      totalEscooters: 'eScooters',
      providersHeading: 'Providers in Cologne',
      disclaimer: 'All figures are dummy data for demonstration purposes and provided without guarantee.',
      typeBike: 'Bike',
      typeEscooter: 'eScooter',
      bikesLabel: 'Bikes',
      escootersLabel: 'Scooters',
      loading: 'Loading data …',
      loadError: 'Could not load data.',
      themeToggleLabel: 'Toggle color scheme'
    },
    ko: {
      siteTitle: '2Rad Wächter Kölle',
      heroTitle: 'All die Sharing-Anbieter för Fahrrad un eScooter en Kölle',
      heroSubtitle: 'Jeck op Räder? Hä kriss du op ene Blick, wie vill Fahrrööder un eScooter jrad en Kölle ze han sin.',
      lastUpdated: 'Stand:',
      totalVehicles: 'Fahrzeuge insgesamt',
      totalBikes: 'Fahrrööder',
      totalEscooters: 'eScooter',
      providersHeading: 'Anbieter en Kölle',
      disclaimer: 'Alle Zahle sin Dummy-Date för Demonstrationszwecke un ohne Jewähr.',
      typeBike: 'Fahrrad',
      typeEscooter: 'eScooter',
      bikesLabel: 'Rääder',
      escootersLabel: 'Scooter',
      loading: 'Date wääde jelade …',
      loadError: 'Date konnte nit jelade wääde.',
      themeToggleLabel: 'Farrschema wähle'
    }
  };

  function normalizeLang(lang) {
    return SUPPORTED_LANGS.indexOf(lang) !== -1 ? lang : null;
  }

  function detectBrowserLang() {
    var navLang = (navigator.language || navigator.userLanguage || '').toLowerCase();
    var navLangs = (navigator.languages && navigator.languages.length) ? navigator.languages : [navLang];
    var isGerman = navLangs.some(function (l) { return (l || '').toLowerCase().indexOf('de') === 0; });
    return isGerman ? 'de' : 'en';
  }

  var currentLang = normalizeLang(localStorage.getItem(STORAGE_KEY)) || detectBrowserLang();
  var userHasChosen = !!normalizeLang(localStorage.getItem(STORAGE_KEY));

  function t(key) {
    return (translations[currentLang] && translations[currentLang][key]) || key;
  }

  function updateLangButtons() {
    var buttons = document.querySelectorAll('.lang-btn');
    buttons.forEach(function (btn) {
      btn.classList.toggle('active', btn.getAttribute('data-lang') === currentLang);
    });
  }

  function applyTranslations() {
    document.documentElement.lang = currentLang === 'ko' ? 'de' : currentLang;

    var titles = {
      de: '2Rad Wächter Köln – Bike- & Scooter-Sharing im Überblick',
      en: '2Wheel Guardian Cologne – Bike & Scooter Sharing at a Glance',
      ko: '2Rad Wächter Kölle – Bike- & Scooter-Sharing op ene Blick'
    };
    document.title = titles[currentLang] || titles.de;

    var nodes = document.querySelectorAll('[data-i18n]');
    nodes.forEach(function (node) {
      var key = node.getAttribute('data-i18n');
      node.textContent = t(key);
    });

    var ariaNodes = document.querySelectorAll('[data-i18n-aria]');
    ariaNodes.forEach(function (node) {
      var key = node.getAttribute('data-i18n-aria');
      node.setAttribute('aria-label', t(key));
    });

    updateLangButtons();
  }

  function setLanguage(lang, fromUser) {
    var normalized = normalizeLang(lang) || 'de';
    currentLang = normalized;
    if (fromUser) {
      userHasChosen = true;
      localStorage.setItem(STORAGE_KEY, currentLang);
    }
    applyTranslations();
    if (window.__providerData) {
      renderProviders(window.__providerData);
    }
  }

  function formatNumber(n) {
    var locale = currentLang === 'en' ? 'en-US' : 'de-DE';
    return new Intl.NumberFormat(locale).format(n);
  }

  function typeBadges(types) {
    return types.map(function (type) {
      var label = type === 'bike' ? t('typeBike') : t('typeEscooter');
      return '<span class="provider-type-badge">' + label + '</span>';
    }).join('');
  }

  function renderProviders(data) {
    var providers = data.providers || [];

    var totalBikes = 0;
    var totalEscooters = 0;
    providers.forEach(function (p) {
      totalBikes += p.bikes || 0;
      totalEscooters += p.escooters || 0;
    });

    document.getElementById('total-bikes').textContent = formatNumber(totalBikes);
    document.getElementById('total-escooters').textContent = formatNumber(totalEscooters);
    document.getElementById('total-vehicles').textContent = formatNumber(totalBikes + totalEscooters);

    var updatedEl = document.getElementById('updated-time');
    if (data.updated) {
      var d = new Date(data.updated);
      var locale = currentLang === 'en' ? 'en-US' : 'de-DE';
      updatedEl.dateTime = data.updated;
      updatedEl.textContent = new Intl.DateTimeFormat(locale, {
        dateStyle: 'medium',
        timeStyle: 'short'
      }).format(d);
    }

    var grid = document.getElementById('provider-grid');
    grid.innerHTML = providers
      .slice()
      .sort(function (a, b) { return (b.bikes + b.escooters) - (a.bikes + a.escooters); })
      .map(function (p) {
        return (
          '<article class="provider-card" style="--provider-color: ' + (p.color || '#ef0000') + '">' +
            '<h3>' + p.name + '</h3>' +
            '<div class="provider-types">' + typeBadges(p.types || []) + '</div>' +
            '<div class="provider-counts">' +
              '<div><strong>' + formatNumber(p.bikes || 0) + '</strong>' + t('bikesLabel') + '</div>' +
              '<div><strong>' + formatNumber(p.escooters || 0) + '</strong>' + t('escootersLabel') + '</div>' +
            '</div>' +
          '</article>'
        );
      })
      .join('');
  }

  function loadData() {
    var grid = document.getElementById('provider-grid');
    grid.innerHTML = '<p>' + t('loading') + '</p>';

    fetch(DATA_URL, { cache: 'no-store' })
      .then(function (res) {
        if (!res.ok) { throw new Error('HTTP ' + res.status); }
        return res.json();
      })
      .then(function (data) {
        window.__providerData = data;
        renderProviders(data);
      })
      .catch(function (err) {
        console.error('Failed to load provider data:', err);
        grid.innerHTML = '<p>' + t('loadError') + '</p>';
      });
  }

  function detectColognByGeo() {
    // Only auto-switch to Kölsch if the user hasn't made their own choice yet.
    if (userHasChosen) {
      return;
    }
    fetch(GEO_URL, { cache: 'no-store' })
      .then(function (res) {
        if (!res.ok) { throw new Error('HTTP ' + res.status); }
        return res.json();
      })
      .then(function (geo) {
        if (!userHasChosen && geo && geo.isCologne) {
          setLanguage('ko', false);
        }
      })
      .catch(function (err) {
        console.warn('Geo detection unavailable:', err);
      });
  }

  // --- Dark mode -------------------------------------------------------

  var THEME_STORAGE_KEY = '2rad-theme';
  var darkModeQuery = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

  function getSystemTheme() {
    return darkModeQuery && darkModeQuery.matches ? 'dark' : 'light';
  }

  var themeUserHasChosen = !!localStorage.getItem(THEME_STORAGE_KEY);
  var currentTheme = localStorage.getItem(THEME_STORAGE_KEY) || getSystemTheme();

  function applyTheme(theme) {
    currentTheme = theme === 'dark' ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', currentTheme);
    var toggle = document.getElementById('theme-toggle');
    if (toggle) {
      toggle.setAttribute('aria-pressed', currentTheme === 'dark' ? 'true' : 'false');
    }
  }

  function setTheme(theme, fromUser) {
    if (fromUser) {
      themeUserHasChosen = true;
      localStorage.setItem(THEME_STORAGE_KEY, theme);
    }
    applyTheme(theme);
  }

  function initTheme() {
    applyTheme(currentTheme);

    var toggle = document.getElementById('theme-toggle');
    if (toggle) {
      toggle.addEventListener('click', function () {
        setTheme(currentTheme === 'dark' ? 'light' : 'dark', true);
      });
    }

    // Only auto-adopt system changes (e.g. OS switching to night mode on a
    // schedule) as long as the user hasn't chosen a theme manually.
    if (darkModeQuery) {
      var handleSystemChange = function (e) {
        if (!themeUserHasChosen) {
          applyTheme(e.matches ? 'dark' : 'light');
        }
      };
      if (darkModeQuery.addEventListener) {
        darkModeQuery.addEventListener('change', handleSystemChange);
      } else if (darkModeQuery.addListener) {
        darkModeQuery.addListener(handleSystemChange);
      }
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    applyTranslations();
    initTheme();

    document.querySelectorAll('.lang-btn').forEach(function (btn) {
      btn.addEventListener('click', function () {
        setLanguage(btn.getAttribute('data-lang'), true);
      });
    });

    loadData();
    detectColognByGeo();
  });
})();
