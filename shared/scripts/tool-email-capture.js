// shared/scripts/tool-email-capture.js
//
// Drives every "email me these results" box rendered by
// partials/tool-email-capture.php. Vanilla, no module, no build step, matching
// the rest of the tool scripts.
//
// In results mode the rows come from window.toolEmailSummary(), read at submit
// time so the email always matches what is on screen rather than whatever was
// there when the page loaded.

(function () {
  'use strict';

  var ENDPOINT = (window.INVGEN_BASE || '') + '/api/tool-email.php';

  function setMessage(box, text, isError) {
    var msg = box.querySelector('[data-tec-msg]');
    if (!msg) return;
    msg.textContent = text;
    msg.classList.toggle('is-error', !!isError);
  }

  // The default: every element the page tagged with data-tec-row, in document
  // order, using the attribute as the label and the rendered text as the value.
  // Reading what is on screen means the email cannot disagree with the page, and
  // it keeps the formatting work (currency, locale) in the one place that
  // already does it.
  function rowsFromMarkup() {
    return Array.prototype.map.call(
      document.querySelectorAll('[data-tec-row]'),
      function (el) {
        return { label: el.dataset.tecRow, value: (el.textContent || '').trim() };
      }
    );
  }

  function collectSummary() {
    if (typeof window.toolEmailSummary !== 'function') return rowsFromMarkup();
    try {
      var rows = window.toolEmailSummary();
      return Array.isArray(rows) ? rows : rowsFromMarkup();
    } catch (err) {
      // A broken callback must not cost the address: fall back to the markup
      // rather than failing the submit.
      return rowsFromMarkup();
    }
  }

  function wire(box) {
    var form = box.querySelector('.tec-form');
    var emailInput = box.querySelector('[data-tec-email]');
    var subscribeInput = box.querySelector('[data-tec-subscribe]');
    var sendButton = box.querySelector('[data-tec-send]');
    if (!form || !emailInput || !sendButton) return;

    var optInOnly = box.dataset.mode === 'optin';

    form.addEventListener('submit', function (event) {
      event.preventDefault();

      var email = emailInput.value.trim();
      if (!email || email.indexOf('@') < 1) {
        setMessage(box, 'Please enter a valid email address.', true);
        emailInput.focus();
        return;
      }

      var summary = optInOnly ? [] : collectSummary();
      var subscribe = optInOnly || !!(subscribeInput && subscribeInput.checked);

      if (!summary.length && !subscribe) {
        setMessage(box, 'Tick the box to get updates, or there is nothing to send.', true);
        return;
      }

      sendButton.disabled = true;
      var original = sendButton.textContent;
      sendButton.textContent = 'Sending...';
      setMessage(box, '');

      fetch(ENDPOINT, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          email: email,
          source: box.dataset.source,
          summary: summary,
          subscribe: subscribe
        })
      })
        .then(function (response) {
          return response.json().catch(function () {
            return { ok: false, message: 'Something went wrong. Please try again.' };
          });
        })
        .then(function (data) {
          if (data && data.ok) {
            setMessage(box, data.message || 'Sent. Check your inbox.', false);
            form.hidden = true;
            var consent = box.querySelector('[data-tec-consent]');
            if (consent) consent.hidden = true;
          } else {
            setMessage(box, (data && data.message) || 'Something went wrong. Please try again.', true);
          }
        })
        .catch(function () {
          setMessage(box, 'Could not reach the server. Please check your connection and try again.', true);
        })
        .finally(function () {
          sendButton.disabled = false;
          sendButton.textContent = original;
        });
    });
  }

  function init() {
    document.querySelectorAll('[data-tool-email]').forEach(wire);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
