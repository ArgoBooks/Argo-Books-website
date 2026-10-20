// Admin filters fetch the filtered page and swap only the areas marked data-live-region, so nothing reloads and the scroll position stays where it is.
(function () {
    'use strict';

    var REGION = '[data-live-region]';
    var SCROLL_KEY = 'scrollPosition';
    var pending = null;

    function saveScroll() {
        try { sessionStorage.setItem(SCROLL_KEY, window.scrollY); } catch (e) { /* private mode */ }
    }

    function restoreScroll() {
        try {
            var y = sessionStorage.getItem(SCROLL_KEY);
            if (y === null) return;
            sessionStorage.removeItem(SCROLL_KEY);
            window.scrollTo(0, parseInt(y, 10) || 0);
        } catch (e) { /* private mode */ }
    }

    // A full page load keeps the scroll position too, for the rare case the in-place swap cannot be used.
    function navigate(url, replace) {
        saveScroll();
        if (replace) window.location.replace(url);
        else window.location.assign(url);
    }

    // A folder and its index.php are the same page, and filter links use either form.
    function samePage(pathname) {
        var strip = function (p) { return p.replace(/\/index\.php$/, '/'); };
        return strip(pathname) === strip(window.location.pathname);
    }

    function isLiveLink(a) {
        if (!a.closest('[data-live-filter]')) return false;
        if (a.hasAttribute('download') || (a.target && a.target !== '_self')) return false;
        var url = new URL(a.href, window.location.href);
        // A link that keeps the same query, such as one to a #section, changes no filter.
        if (url.search === window.location.search) return false;
        return url.origin === window.location.origin && samePage(url.pathname);
    }

    function destroyCharts(region) {
        if (!window.Chart || typeof window.Chart.getChart !== 'function') return;
        region.querySelectorAll('canvas').forEach(function (canvas) {
            var chart = window.Chart.getChart(canvas);
            if (chart) chart.destroy();
        });
    }

    // Scripts parsed from fetched HTML never run on their own, so each one is replaced with a fresh copy that does.
    function runScripts(region) {
        region.querySelectorAll('script').forEach(function (old) {
            var script = document.createElement('script');
            Array.prototype.forEach.call(old.attributes, function (attr) {
                script.setAttribute(attr.name, attr.value);
            });
            if (old.src) {
                script.async = false;
            } else {
                script.textContent = old.textContent;
            }
            old.replaceWith(script);
        });
    }

    function swapRegions(doc) {
        var current = document.querySelectorAll(REGION);
        var fresh = doc.querySelectorAll(REGION);
        if (current.length === 0 || current.length !== fresh.length) return false;

        document.dispatchEvent(new CustomEvent('admin:live-teardown'));
        for (var i = 0; i < current.length; i++) {
            destroyCharts(current[i]);
            var next = document.importNode(fresh[i], true);
            current[i].replaceWith(next);
            runScripts(next);
        }
        return true;
    }

    function setBusy(busy) {
        document.querySelectorAll(REGION).forEach(function (region) {
            if (busy) region.setAttribute('aria-busy', 'true');
            else region.removeAttribute('aria-busy');
        });
    }

    // A filter keeps the tab being viewed, since links and forms are rendered with the tab that was open when the page loaded.
    function withActiveTab(url) {
        var active = document.querySelector('.section-tab.active[data-tab]');
        if (!active) return url;
        var dest = new URL(url, window.location.href);
        dest.searchParams.set('tab', active.dataset.tab);
        return dest.href;
    }

    function load(url, push) {
        if (push) url = withActiveTab(url);
        if (pending) pending.abort();
        var controller = new AbortController();
        pending = controller;

        if (!document.querySelector(REGION)) {
            navigate(url, !push);
            return;
        }
        setBusy(true);

        fetch(url, { signal: controller.signal, credentials: 'same-origin' })
            .then(function (response) {
                // A login page or any other address means the session ended, which only a normal page load can handle.
                if (!response.ok || !samePage(new URL(response.url).pathname)) {
                    throw new Error('not the same page');
                }
                return response.text();
            })
            .then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                if (doc.querySelectorAll(REGION).length !== document.querySelectorAll(REGION).length) {
                    throw new Error('page layout differs');
                }
                // The address changes before the swap, because the page's scripts read their filters and tab from it.
                if (push) history.pushState({ liveFilter: true }, '', url);
                if (!swapRegions(doc)) throw new Error('swap failed');
                if (doc.title) document.title = doc.title;
                document.dispatchEvent(new CustomEvent('admin:live-update', { detail: { url: url } }));
            })
            .catch(function (error) {
                if (error.name === 'AbortError') return;
                navigate(url, !push);
            })
            .finally(function () {
                if (pending === controller) {
                    pending = null;
                    setBusy(false);
                }
            });
    }

    document.addEventListener('click', function (e) {
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        var link = e.target.closest('a[href]');
        if (!link || !isLiveLink(link)) return;
        e.preventDefault();
        load(link.href, true);
    });

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (e.defaultPrevented || !form.closest('[data-live-filter]')) return;
        if ((form.getAttribute('method') || 'get').toLowerCase() !== 'get') return;
        e.preventDefault();
        var url = new URL(form.getAttribute('action') || window.location.href, window.location.href);
        url.search = new URLSearchParams(new FormData(form, e.submitter || null)).toString();
        load(url.href, true);
    });

    window.addEventListener('popstate', function () {
        load(window.location.href, false);
    });

    // For filters that build their address in code, such as a searchable dropdown.
    window.adminLiveFilters = {
        go: function (url) {
            load(new URL(url, window.location.href).href, true);
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', restoreScroll);
    } else {
        restoreScroll();
    }
})();
