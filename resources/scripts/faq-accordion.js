// resources/scripts/faq-accordion.js
//
// The one FAQ accordion for the whole site, in place of a copy inline on every compare page, industry page, pricing page, article and tool page.
//
// Loaded automatically by partials/faq.php, so a page that renders its FAQ
// through the partial needs no script wiring of its own.
//
// Event delegation rather than per-item listeners: one listener handles every
// accordion on the page, works no matter when the markup appears, and cannot
// double-bind if the partial is somehow included twice.

(function () {
  if (window.__argoFaqAccordion) return;
  window.__argoFaqAccordion = true;

  document.addEventListener('click', function (event) {
    var question = event.target.closest ? event.target.closest('.faq-question') : null;
    if (!question) return;

    var item = question.closest('.faq-item');
    if (!item) return;

    var wasActive = item.classList.contains('active');

    // Close siblings within the same grid only, so two accordions on one page
    // (for example a pricing FAQ beside a feature FAQ) do not fight.
    var scope = item.closest('.faq-grid') || document;
    scope.querySelectorAll('.faq-item').forEach(function (other) {
      other.classList.remove('active');
      var btn = other.querySelector('.faq-question');
      if (btn && btn.hasAttribute('aria-expanded')) btn.setAttribute('aria-expanded', 'false');
    });

    if (!wasActive) {
      item.classList.add('active');
      if (question.hasAttribute('aria-expanded')) question.setAttribute('aria-expanded', 'true');
    }
  });
})();
