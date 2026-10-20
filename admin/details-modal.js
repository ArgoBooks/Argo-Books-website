// "See all details" modals: a .bd-details-btn with data-details-target opens the .bd-details-modal of that id, a searchable full list.
(function () {
    const close = modal => { modal.style.display = 'none'; };

    // Listening on the document covers buttons and modals that a filter swaps in after load.
    document.addEventListener('click', e => {
        const btn = e.target.closest('.bd-details-btn[data-details-target]');
        if (btn) {
            const modal = document.getElementById(btn.getAttribute('data-details-target'));
            if (!modal) return;
            modal.style.display = 'block';
            const search = modal.querySelector('.bd-details-search');
            if (search) { search.value = ''; search.dispatchEvent(new Event('input', { bubbles: true })); search.focus(); }
            return;
        }
        const closeBtn = e.target.closest('.bd-details-close');
        if (closeBtn) {
            const modal = closeBtn.closest('.bd-details-modal');
            if (modal) close(modal);
        }
    });

    // Rows are read at filter time, so a list built after load filters the same as one rendered by the server.
    document.addEventListener('input', e => {
        const search = e.target.closest ? e.target.closest('.bd-details-search') : null;
        if (!search) return;
        const modal = search.closest('.bd-details-modal');
        if (!modal) return;
        const q = (search.value || '').trim().toLowerCase();
        modal.querySelectorAll('.bd-details-row[data-name]').forEach(r => {
            r.style.display = (!q || r.getAttribute('data-name').includes(q)) ? '' : 'none';
        });
    });

    document.addEventListener('mousedown', e => {
        if (e.target.classList && e.target.classList.contains('bd-details-modal')) close(e.target);
    });

    document.addEventListener('keydown', e => {
        if (e.key !== 'Escape') return;
        document.querySelectorAll('.bd-details-modal').forEach(m => {
            if (m.style.display === 'block') close(m);
        });
    });
})();
