/**
 * Shared section-tabs controller for admin pages.
 *
 * Auto-wires any `.section-tab[data-tab]` buttons inside a `.section-tabs`
 * container, toggles matching `.tab-content` siblings, and syncs the
 * current tab with the URL via `?tab=<id>` for deep linking.
 *
 * Each tab's `data-tab` value must equal the id of the .tab-content div
 * it activates. Markup lives in each admin page; CSS lives in common-style.css.
 */
(function () {
    function activate(tabBtn) {
        var tabsContainer = tabBtn.closest('.section-tabs');
        if (!tabsContainer) return;
        var targetId = tabBtn.dataset.tab;
        if (!targetId) return;

        tabsContainer.querySelectorAll('.section-tab').forEach(function (b) {
            b.classList.remove('active');
        });

        var scope = tabsContainer.parentElement;
        if (scope) {
            scope.querySelectorAll(':scope > .tab-content').forEach(function (c) {
                c.classList.remove('active');
            });
        }

        tabBtn.classList.add('active');
        var target = document.getElementById(targetId);
        if (target) target.classList.add('active');
    }

    // Query params that, when present in the current URL, indicate a server-rendered "detail view".
    var DETAIL_VIEW_PARAMS = ['test_id'];

    function updateUrlOrReload(tabId) {
        try {
            var url = new URL(window.location.href);
            var currentHadDetail = DETAIL_VIEW_PARAMS.some(function (p) {
                return url.searchParams.has(p);
            });

            url.searchParams.set('tab', tabId);
            DETAIL_VIEW_PARAMS.forEach(function (p) { url.searchParams.delete(p); });
            if (url.hash) url.hash = '';

            if (currentHadDetail) {
                window.location.assign(url.toString());
            } else {
                history.replaceState({}, '', url.toString());
            }
        } catch (e) {
            // URL APIs missing, ignore; click still works visually.
        }
    }

    function wireClicks() {
        document.querySelectorAll('.section-tabs .section-tab[data-tab]').forEach(function (btn) {
            if (btn.dataset.sectionTabWired === '1') return;
            btn.dataset.sectionTabWired = '1';
            btn.addEventListener('click', function () {
                activate(btn);
                updateUrlOrReload(btn.dataset.tab);
            });
        });
    }

    function activateInitial() {
        var urlParams = new URLSearchParams(window.location.search);
        var tabFromQuery = urlParams.get('tab');
        var hash = window.location.hash ? window.location.hash.substring(1) : '';
        var initial = tabFromQuery || hash;
        if (!initial) return;

        var sel = '.section-tab[data-tab="' + (window.CSS && CSS.escape ? CSS.escape(initial) : initial) + '"]';
        var btn = document.querySelector(sel);
        if (btn) activate(btn);
    }

    function bootstrap() {
        wireClicks();
        activateInitial();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrap);
    } else {
        bootstrap();
    }
})();
