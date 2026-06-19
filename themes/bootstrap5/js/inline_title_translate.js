/*global VuFind */
VuFind.register('inlineTitleTranslate', function inlineTitleTranslate() {
  var buttonSelector = '[data-inline-title-translate]';
  var googleTranslateUrl = 'https://translate.googleapis.com/translate_a/single';

  /**
   * Get the translation output element for a button.
   * @param {HTMLElement} button Translate button
   * @returns {?HTMLElement} Translation output element
   */
  function getOutput(button) {
    return document.getElementById(button.dataset.translationTarget);
  }

  /**
   * Toggle the button loading state.
   * @param {HTMLElement} button Translate button
   * @param {boolean} loading Loading state
   * @returns {void}
   */
  function setLoading(button, loading) {
    var icon = button.querySelector('.fa');
    button.disabled = loading;
    if (!icon) {
      return;
    }
    icon.classList.toggle('fa-language', !loading);
    icon.classList.toggle('fa-circle-notch', loading);
    icon.classList.toggle('fa-spin', loading);
  }

  /**
   * Extract translated text from Google's response.
   * @param {Array} data Google Translate response
   * @returns {string} Translated title
   */
  function getTranslatedText(data) {
    if (!Array.isArray(data) || !Array.isArray(data[0])) {
      return '';
    }
    return data[0].map(function getTranslationPart(part) {
      return Array.isArray(part) ? part[0] : '';
    }).join('');
  }

  /**
   * Show translated text below the title.
   * @param {HTMLElement} button Translate button
   * @param {string} text Translated title
   * @returns {void}
   */
  function showTranslation(button, text) {
    var output = getOutput(button);
    if (!output) {
      return;
    }
    output.textContent = text;
    output.classList.remove('hidden');
    button.dataset.translated = 'true';
  }

  /**
   * Translate a title to English.
   * @param {HTMLElement} button Translate button
   * @returns {Promise<void>} Translation request
   */
  function translate(button) {
    var output = getOutput(button);
    if (button.dataset.translated === 'true') {
      if (output) {
        output.classList.toggle('hidden');
      }
      return Promise.resolve();
    }

    var query = new URLSearchParams({
      client: 'gtx',
      sl: 'auto',
      tl: 'en',
      dt: 't',
      q: button.dataset.title || '',
    });

    setLoading(button, true);
    return fetch(googleTranslateUrl + '?' + query.toString())
      .then(function handleResponse(response) {
        if (!response.ok) {
          throw new Error('Translation failed');
        }
        return response.json();
      })
      .then(function handleJson(data) {
        var translated = getTranslatedText(data);
        if (!translated) {
          throw new Error('Empty translation');
        }
        showTranslation(button, translated);
      })
      .catch(function handleError() {
        showTranslation(button, 'Tradução indisponível');
      })
      .finally(function finishRequest() {
        setLoading(button, false);
      });
  }

  /**
   * Initialize translation buttons.
   * @param {?HTMLElement} container Container to initialize
   * @returns {void}
   */
  function init(container) {
    var target = container || document;
    target.querySelectorAll(buttonSelector).forEach(function initButton(button) {
      if (button.dataset.inlineTitleTranslateReady) {
        return;
      }
      button.dataset.inlineTitleTranslateReady = 'true';
      button.addEventListener('click', function onClick() {
        translate(button);
      });
    });
  }

  VuFind.listen('results-init', function onResultsInit(event) {
    init(event.container);
  });

  return { init: init };
});
