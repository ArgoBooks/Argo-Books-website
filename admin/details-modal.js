/**
 * "See all details" modals: a searchable full list behind a button, used by the
 * marketing funnel's breakdown cards and the app-stats country chart.
 *
 * A trigger is any .bd-details-btn carrying data-details-target with the id of a
 * .bd-details-modal. Rows are read at filter time, so a page that builds its list
 * after load works the same as one that renders it server-side.
 */
(function () {
    const close = modal => { modal.style.display = 'none'; };

    document.querySelectorAll('.bd-details-modal').forEach(modal => {
        const search = modal.querySelector('.bd-details-search');
        const filter = () => {
            const q = (search.value || '').trim().toLowerCase();
            modal.querySelectorAll('.bd-details-row[data-name]').forEach(r => {
                r.style.display = (!q || r.getAttribute('data-name').includes(q)) ? '' : 'none';
            });
        };
        if (search) search.addEventListener('input', filter);
        modal.querySelectorAll('.bd-details-close').forEach(x => x.addEventListener('click', () => close(modal)));
        modal.addEventListener('mousedown', e => { if (e.target === modal) close(modal); });
    });

    document.querySelectorAll('.bd-details-btn[data-details-target]').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = document.getElementById(btn.getAttribute('data-details-target'));
            if (!modal) return;
            modal.style.display = 'block';
            const search = modal.querySelector('.bd-details-search');
            if (search) { search.value = ''; search.dispatchEvent(new Event('input')); search.focus(); }
        });
    });

    document.addEventListener('keydown', e => {
        if (e.key !== 'Escape') return;
        document.querySelectorAll('.bd-details-modal').forEach(m => {
            if (m.style.display === 'block') close(m);
        });
    });
})();
