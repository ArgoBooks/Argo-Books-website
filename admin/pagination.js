/**
 * Shared client-side table pagination for admin pages.
 *
 * Usage (auto-init):
 *   <table data-paginate="25"> ... </table>
 *
 * Usage (manual):
 *   const p = new TablePaginator(tableEl, { perPage: 25 });
 *   p.reset();   // after filtering / re-rendering
 */
class TablePaginator {
    static ROW_OPTIONS = [10, 25, 50, 100];
    static STORAGE_KEY = 'admin_rows_per_page';

    constructor(tableEl, options = {}) {
        this.table = tableEl;
        this.tbody = tableEl.querySelector('tbody');
        if (!this.tbody) return;

        // Use saved preference if available, otherwise fall back to option/default
        const saved = localStorage.getItem(TablePaginator.STORAGE_KEY);
        this.perPage = saved ? parseInt(saved, 10) : (options.perPage || 25);
        this.currentPage = 1;
        this.controlsEl = document.createElement('div');
        this.controlsEl.className = 'pagination-controls';

        // Insert controls after the table's scroll wrapper or parent
        const wrapper = tableEl.closest('.table-responsive') || tableEl.closest('.leads-table-wrapper') || tableEl.parentElement;
        wrapper.after(this.controlsEl);

        this.update();

        // Auto-repaginate when rows are added or removed, so tables whose rows
        // are injected or replaced by JavaScript/AJAX after load paginate the
        // same way as server-rendered ones, with no per-page wiring. We only
        // watch childList: the paginator toggles row display/classes, never the
        // row set itself, so this can't loop. Debounced to one run per frame.
        this._observerScheduled = false;
        this._observer = new MutationObserver(() => {
            if (this._observerScheduled) return;
            this._observerScheduled = true;
            requestAnimationFrame(() => {
                this._observerScheduled = false;
                this.reset();
            });
        });
        this._observer.observe(this.tbody, { childList: true });
    }

    /** Returns rows that are NOT hidden by external filters. */
    _rows() {
        return Array.from(this.tbody.querySelectorAll('tr')).filter(r => {
            // Skip rows hidden by an external filter (display:none without our class)
            if (r.style.display === 'none' && !r.classList.contains('pg-hidden')) return false;
            // Skip empty-state placeholder rows
            if (r.querySelector('.empty-state')) return false;
            return true;
        });
    }

    update() {
        const rows = this._rows();
        const totalPages = Math.max(1, Math.ceil(rows.length / this.perPage));
        if (this.currentPage > totalPages) this.currentPage = totalPages;

        const start = (this.currentPage - 1) * this.perPage;
        const end = start + this.perPage;

        rows.forEach((row, i) => {
            if (i >= start && i < end) {
                row.style.display = '';
                row.classList.remove('pg-hidden');
            } else {
                row.style.display = 'none';
                row.classList.add('pg-hidden');
            }
        });

        this._renderControls(rows.length, totalPages);
    }

    _renderControls(total, totalPages) {
        if (total <= Math.min(...TablePaginator.ROW_OPTIONS)) {
            this.controlsEl.style.display = 'none';
            return;
        }
        this.controlsEl.style.display = '';

        // Build rows-per-page selector
        const rowOpts = TablePaginator.ROW_OPTIONS.map(n =>
            `<option value="${n}"${n === this.perPage ? ' selected' : ''}>${n}</option>`
        ).join('');

        // Build page number buttons with ellipsis for large page counts
        let pages = '';
        const cur = this.currentPage;
        const range = [];

        for (let i = 1; i <= totalPages; i++) {
            if (totalPages <= 7 || i === 1 || i === totalPages || (i >= cur - 1 && i <= cur + 1)) {
                range.push(i);
            } else if (range[range.length - 1] !== '...') {
                range.push('...');
            }
        }

        for (const p of range) {
            if (p === '...') {
                pages += '<span class="pg-ellipsis">\u2026</span>';
            } else {
                pages += `<button class="pg-num${p === cur ? ' pg-active' : ''}" data-pg="${p}">${p}</button>`;
            }
        }

        // Range summary, e.g. "1-10 of 35". Optional noun via data-paginate-noun
        // (e.g. data-paginate-noun="expenses" -> "1-10 of 35 expenses").
        const start = total === 0 ? 0 : (this.currentPage - 1) * this.perPage + 1;
        const end = Math.min(this.currentPage * this.perPage, total);
        const noun = this.table.dataset.paginateNoun ? ' ' + this.table.dataset.paginateNoun : '';

        this.controlsEl.innerHTML = `
            <div class="pg-left">
                <span class="pg-summary">${start}\u2013${end} of ${total}${noun}</span>
                <span class="pg-divider"></span>
                <div class="pg-rows-selector">
                    <label>Rows per page:</label>
                    <select>${rowOpts}</select>
                </div>
            </div>
            <div class="pg-nav">
                <button class="pg-arrow" ${cur <= 1 ? 'disabled' : ''} data-pg="prev">\u2039</button>
                ${pages}
                <button class="pg-arrow" ${cur >= totalPages ? 'disabled' : ''} data-pg="next">\u203A</button>
            </div>
        `;

        this.controlsEl.querySelector('.pg-rows-selector select').addEventListener('change', (e) => {
            this.setPerPage(parseInt(e.target.value, 10));
        });
        this.controlsEl.querySelector('[data-pg="prev"]').addEventListener('click', () => this.goTo(cur - 1));
        this.controlsEl.querySelector('[data-pg="next"]').addEventListener('click', () => this.goTo(cur + 1));
        this.controlsEl.querySelectorAll('.pg-num').forEach(btn => {
            btn.addEventListener('click', () => this.goTo(parseInt(btn.dataset.pg, 10)));
        });
    }

    setPerPage(n) {
        this.perPage = n;
        this.currentPage = 1;
        localStorage.setItem(TablePaginator.STORAGE_KEY, n);
        this.update();
    }

    goTo(page) {
        this.currentPage = page;
        this.update();
        window.scrollTo(0, document.documentElement.scrollHeight);
    }

    reset() {
        this.currentPage = 1;
        this.update();
    }
}

// Auto-init tables with data-paginate attribute
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('table[data-paginate]').forEach(table => {
        const perPage = parseInt(table.dataset.paginate, 10) || 25;
        table._paginator = new TablePaginator(table, { perPage });
    });
});
