/**
 * 2Rad Wächter Köln – frontend logic
 * - Language selection: German, English, Kölsch (all three selectable manually)
 * - Automatic default:
 *     1. Stored user choice (localStorage) always takes priority
 *     2. Kölsch only if the visitor's IP resolves to Cologne (geo check)
 *     3. Otherwise: German if browser/OS language is German, else English
 * - Loads provider data (live via api/providers.php, falling back to a
 *   clearly marked "no live data" state per provider when none is available)
 *   and renders overall totals as well as individual provider cards
 */

/**
 * @typedef {Object} Provider
 * @property {string} id
 * @property {string} name
 * @property {string[]} types
 * @property {?number} bikes         null when `available` is false
 * @property {?number} escooters     null when `available` is false
 * @property {string} color
 * @property {boolean} live          whether the reading is a real (or last known-good) GBFS value
 * @property {boolean} available     whether this provider has any live data at all
 * @property {?string} source        diagnostic label of the concrete feed used, if any
 */

/**
 * @typedef {Object} ProviderResponse
 * @property {string} updated
 * @property {Provider[]} providers
 */

(function () {
  'use strict';

  const STORAGE_KEY = '2rad-lang';
  const DATA_URL = 'api/providers.php';
  const GEO_URL = 'api/geo.php';
  const SUPPORTED_LANGS = ['de', 'en', 'ko'];

  const translations = {
    de: {
      siteTitle: '2Rad Wächter Köln',
      heroTitle: 'Alle Bike- & eScooter-Sharing-Anbieter in Köln',
      heroSubtitle: 'Auf einen Blick: wie viele Fahrräder und eScooter aktuell in Köln zum Ausleihen verfügbar sind.',
      lastUpdated: 'Stand:',
      totalVehicles: 'Fahrzeuge insgesamt',
      totalBikes: 'Fahrräder',
      totalEscooters: 'eScooter',
      providersHeading: 'Anbieter in Köln',
      footerNote: 'Live-Daten aus den öffentlichen GBFS-Feeds der Anbieter. Wo (noch) keine Live-Daten verfügbar sind, ist das beim jeweiligen Anbieter gekennzeichnet.',
      noDataLabel: 'Keine Live-Daten verfügbar',
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
      footerNote: 'Live data from each provider\'s public GBFS feed. Providers without live data (yet) are clearly marked as such.',
      noDataLabel: 'No live data available',
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
      footerNote: 'Live-Date us de öffentliche GBFS-Feeds vun de Anbieter. Wo (noch) kein Live-Date do sin, steiht dat bei däm Anbieter drusse.',
      noDataLabel: 'Kein Live-Date do',
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
    // navigator.userLanguage is deprecated/IE-only; kept purely as a last-resort
    // fallback for very old browsers where navigator.language is unavailable.
    // noinspection JSDeprecatedSymbols
    const navLang = (navigator.language || navigator.userLanguage || '').toLowerCase();
    const navLangs = (navigator.languages && navigator.languages.length) ? navigator.languages : [navLang];
    const isGerman = navLangs.some(function (l) { return (l || '').toLowerCase().indexOf('de') === 0; });
    return isGerman ? 'de' : 'en';
  }

  let currentLang = normalizeLang(localStorage.getItem(STORAGE_KEY)) || detectBrowserLang();
  let userHasChosen = !!normalizeLang(localStorage.getItem(STORAGE_KEY));

  function t(key) {
    return (translations[currentLang] && translations[currentLang][key]) || key;
  }

  function updateLangButtons() {
    const buttons = document.querySelectorAll('.lang-btn');
    buttons.forEach(function (btn) {
      btn.classList.toggle('active', btn.getAttribute('data-lang') === currentLang);
    });
  }

  function applyTranslations() {
    document.documentElement.lang = currentLang === 'ko' ? 'de' : currentLang;

    const titles = {
      de: '2Rad Wächter Köln – Bike- & Scooter-Sharing im Überblick',
      en: '2Wheel Guardian Cologne – Bike & Scooter Sharing at a Glance',
      ko: '2Rad Wächter Kölle – Bike- & Scooter-Sharing op ene Blick'
    };
    document.title = titles[currentLang] || titles.de;

    const nodes = document.querySelectorAll('[data-i18n]');
    nodes.forEach(function (node) {
      const key = node.getAttribute('data-i18n');
      node.textContent = t(key);
    });

    const ariaNodes = document.querySelectorAll('[data-i18n-aria]');
    ariaNodes.forEach(function (node) {
      const key = node.getAttribute('data-i18n-aria');
      node.setAttribute('aria-label', t(key));
    });

    updateLangButtons();
  }

  function setLanguage(lang, fromUser) {
    currentLang = normalizeLang(lang) || 'de';
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
    const locale = currentLang === 'en' ? 'en-US' : 'de-DE';
    return new Intl.NumberFormat(locale).format(n);
  }

  function typeBadges(types) {
    return types.map(function (type) {
      const label = type === 'bike' ? t('typeBike') : t('typeEscooter');
      return '<span class="provider-type-badge">' + label + '</span>';
    }).join('');
  }

  /**
   * @param {ProviderResponse} data
   */
  function renderProviders(data) {
    /** @type {Provider[]} */
    const providers = data.providers || [];

    let totalBikes = 0;
    let totalEscooters = 0;
    providers.forEach(function (/** @type {Provider} */ p) {
      if (!p.available) { return; }
      totalBikes += p.bikes || 0;
      totalEscooters += p.escooters || 0;
    });

    document.getElementById('total-bikes').textContent = formatNumber(totalBikes);
    document.getElementById('total-escooters').textContent = formatNumber(totalEscooters);
    document.getElementById('total-vehicles').textContent = formatNumber(totalBikes + totalEscooters);

    const updatedEl = document.getElementById('updated-time');
    if (data.updated) {
      const d = new Date(data.updated);
      const locale = currentLang === 'en' ? 'en-US' : 'de-DE';
      updatedEl.dateTime = data.updated;
      /** @type {Intl.DateTimeFormatOptions} */
      const dateTimeOptions = { dateStyle: 'medium', timeStyle: 'short' };
      updatedEl.textContent = new Intl.DateTimeFormat(locale, dateTimeOptions).format(d);
    }

    const grid = document.getElementById('provider-grid');
    grid.innerHTML = providers
      .slice()
      .sort(function (/** @type {Provider} */ a, /** @type {Provider} */ b) {
        if (!a.available && !b.available) { return 0; }
        if (!a.available) { return 1; }
        if (!b.available) { return -1; }
        return (b.bikes + b.escooters) - (a.bikes + a.escooters);
      })
      .map(function (/** @type {Provider} */ p) {
        const counts = p.available
          ? (
            '<div class="provider-counts">' +
              '<div><strong>' + formatNumber(p.bikes || 0) + '</strong>' + t('bikesLabel') + '</div>' +
              '<div><strong>' + formatNumber(p.escooters || 0) + '</strong>' + t('escootersLabel') + '</div>' +
            '</div>'
          )
          : '<div class="provider-counts provider-counts--unavailable">' + t('noDataLabel') + '</div>';

        return (
          '<article class="provider-card' + (p.available ? '' : ' provider-card--unavailable') + '" style="--provider-color: ' + (p.color || '#ef0000') + '">' +
            '<h3>' + p.name + '</h3>' +
            '<div class="provider-types">' + typeBadges(p.types || []) + '</div>' +
            counts +
          '</article>'
        );
      })
      .join('');
  }

  function loadData() {
    const grid = document.getElementById('provider-grid');
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

  const THEME_STORAGE_KEY = '2rad-theme';
  const darkModeQuery = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

  function getSystemTheme() {
    return darkModeQuery && darkModeQuery.matches ? 'dark' : 'light';
  }

  let themeUserHasChosen = !!localStorage.getItem(THEME_STORAGE_KEY);
  let currentTheme = localStorage.getItem(THEME_STORAGE_KEY) || getSystemTheme();

  function applyTheme(theme) {
    currentTheme = theme === 'dark' ? 'dark' : 'light';
    document.documentElement.setAttribute('data-theme', currentTheme);
    const toggle = document.getElementById('theme-toggle');
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

    const toggle = document.getElementById('theme-toggle');
    if (toggle) {
      toggle.addEventListener('click', function () {
        setTheme(currentTheme === 'dark' ? 'light' : 'dark', true);
      });
    }

    // A live OS theme change always wins, even after a manual toggle: the
    // manual choice only holds until the system preference actually changes,
    // at which point we resync and forget the stored override so future
    // system changes keep being picked up automatically.
    if (darkModeQuery) {
      const handleSystemChange = function (e) {
        themeUserHasChosen = false;
        localStorage.removeItem(THEME_STORAGE_KEY);
        applyTheme(e.matches ? 'dark' : 'light');
      };
      if (darkModeQuery.addEventListener) {
        darkModeQuery.addEventListener('change', handleSystemChange);
      } else {
        // MediaQueryList.addListener is deprecated (replaced by addEventListener),
        // kept only as a fallback for Safari < 14 / very old browsers.
        // noinspection JSDeprecatedSymbols
        const legacyAddListener = darkModeQuery.addListener;
        if (legacyAddListener) {
          legacyAddListener.call(darkModeQuery, handleSystemChange);
        }
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
