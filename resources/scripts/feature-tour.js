// Feature tour (shared). Tab switching + mockup entrance animations,
// extracted from the landing page's inline script.
document.addEventListener('DOMContentLoaded', function () {
        // Feature tabs
        const tabBtns = document.querySelectorAll('.tab-btn');
        const tabContents = document.querySelectorAll('.tab-content');
        const tabsContent = document.querySelector('.features-tabs-content');
        const TAB_FADE_MS = 400;
        let activeTabAnimation = null;
        let tabFadeTimer = null;

        function clearTabAnimation() {
            if (activeTabAnimation) {
                activeTabAnimation.forEach(id => clearTimeout(id));
                activeTabAnimation = null;
            }
        }

        function swapTabContent(tabId) {
            tabContents.forEach(c => c.classList.remove('active'));
            document.getElementById('tab-' + tabId).classList.add('active');

            // Reset animation classes on all mockups
            document.querySelectorAll('.animating').forEach(el => el.classList.remove('animating'));

            startTabAnimation(tabId);
        }

        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const tabId = btn.dataset.tab;
                if (btn.classList.contains('active')) return;

                clearTabAnimation();
                if (tabFadeTimer) clearTimeout(tabFadeTimer);

                // The button highlight moves right away; only the graphic waits
                // for the fade so the click still feels instant.
                tabBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');

                if (!tabsContent) {
                    swapTabContent(tabId);
                    return;
                }

                tabsContent.classList.add('tab-fading');
                tabFadeTimer = setTimeout(() => {
                    tabFadeTimer = null;
                    swapTabContent(tabId);
                    tabsContent.classList.remove('tab-fading');
                }, TAB_FADE_MS);
            });
        });


        // Receipt scan & extract animation (loops)
        (function initReceiptScan() {
            const stage = document.getElementById('receiptScan');
            if (!stage) return;
            const status = document.getElementById('efStatus');
            const totalVal = stage.querySelector('.ef-total-val');
            let timers = [];
            function t(fn, d) { timers.push(setTimeout(fn, d)); }
            function clearTimers() { timers.forEach(clearTimeout); timers = []; }

            function countUp(el, target, duration) {
                const start = performance.now();
                function step(now) {
                    // Clamp low as well as high: the first rAF timestamp can
                    // precede the performance.now() captured just above, and a
                    // negative p sends the cubic ease to a large negative number.
                    const p = Math.max(0, Math.min((now - start) / duration, 1));
                    const eased = 1 - Math.pow(1 - p, 3);
                    el.textContent = '$' + (target * eased).toLocaleString('en-US', {
                        minimumFractionDigits: 2, maximumFractionDigits: 2
                    });
                    if (p < 1) requestAnimationFrame(step);
                }
                requestAnimationFrame(step);
            }

            const seq = [
                { at: 380,  f: 'merchant' },
                { at: 720,  f: 'date' },
                { at: 1020, f: 'category', formOnly: true },
                { at: 1320, f: 'item-0' },
                { at: 1560, f: 'item-1' },
                { at: 1800, f: 'item-2' },
                { at: 2040, f: 'item-3' },
                { at: 2380, f: 'tax' },
                { at: 2680, f: 'total' }
            ];

            function run() {
                clearTimers();
                stage.classList.remove('done');
                stage.querySelectorAll('.scan-row').forEach(el => el.classList.remove('detected'));
                stage.querySelectorAll('.ef-field, .ef-line, .ef-trow').forEach(el => el.classList.remove('in'));
                if (status) status.classList.remove('in');
                if (totalVal) totalVal.textContent = '$0.00';

                // restart the beam sweep
                stage.classList.remove('scanning');
                void stage.offsetWidth;
                stage.classList.add('scanning');

                seq.forEach(s => {
                    t(() => {
                        if (!s.formOnly) {
                            const row = stage.querySelector('.scan-row[data-field="' + s.f + '"]');
                            if (row) row.classList.add('detected');
                        }
                        stage.querySelectorAll(
                            '.ef-field[data-field="' + s.f + '"], .ef-line[data-field="' + s.f + '"], .ef-trow[data-field="' + s.f + '"]'
                        ).forEach(el => el.classList.add('in'));
                        if (s.f === 'total' && totalVal) countUp(totalVal, 120.35, 800);
                    }, s.at);
                });

                // completion + loop
                t(() => {
                    stage.classList.remove('scanning');
                    stage.classList.add('done');
                    if (status) status.classList.add('in');
                }, 3150);
                t(run, 6400);
            }
            run();
        })();

        // Feature tab animation controller
        function startTabAnimation(tabId) {
            const timeouts = [];
            function t(fn, delay) {
                timeouts.push(setTimeout(fn, delay));
            }

            switch (tabId) {
                case 'expenses': animateExpenses(t); break;
                case 'predictive': animatePredictive(t); break;
                case 'inventory': animateInventory(t); break;
                case 'rental': animateRental(t); break;
                case 'customers': animateCustomers(t); break;
                case 'invoices': animateInvoices(t); break;
                case 'quotes': animateQuotes(t); break;
                case 'bank-import': animateBankImport(t); break;
                case 'sheet-import': animateSheetImport(t); break;
                case 'report': animateReport(t); break;
                case 'stripe': animateStripe(t); break;
                case 'payroll': animatePayroll(t); break;
            }

            activeTabAnimation = timeouts;
        }

        // Expense & Revenue Tracking animation
        // Expense & Revenue overview: table streams in, charts pop up and animate
        function animateExpenses(t) {
            const stage = document.getElementById('expenseStage');
            if (!stage) return;
            const rows = stage.querySelectorAll('.txn-row');
            const chartLine = document.getElementById('expChartLine');
            const chartBars = document.getElementById('expChartBars');
            const netVal = stage.querySelector('.exp-net-val');

            function countUp(el, target, duration) {
                const start = performance.now();
                function step(now) {
                    // Clamp low as well as high: the first rAF timestamp can
                    // precede the performance.now() captured just above, and a
                    // negative p sends the cubic ease to a large negative number.
                    const p = Math.max(0, Math.min((now - start) / duration, 1));
                    const eased = 1 - Math.pow(1 - p, 3);
                    el.textContent = Math.round(target * eased).toLocaleString('en-US');
                    if (p < 1) requestAnimationFrame(step);
                }
                requestAnimationFrame(step);
            }

            function run() {
                rows.forEach(r => r.classList.remove('in'));
                if (chartLine) chartLine.classList.remove('in');
                if (chartBars) chartBars.classList.remove('in');
                if (netVal) netVal.textContent = '0';

                rows.forEach((r, i) => t(() => r.classList.add('in'), 250 + i * 170));
                const afterRows = 250 + rows.length * 170;

                t(() => { if (chartLine) chartLine.classList.add('in'); }, afterRows + 200);
                t(() => {
                    if (chartBars) chartBars.classList.add('in');
                    if (netVal) countUp(netVal, 12089, 1000);
                }, afterRows + 1000);

                t(run, afterRows + 4600);
            }
            run();
        }

        // Predictive Analytics animation
        // Predictive Analytics: KPIs count up, history draws, forecast extends into the cone
        function animatePredictive(t) {
            const stage = document.getElementById('forecastStage');
            if (!stage) return;
            const chart = stage.querySelector('.fc-chart');
            const kpis = stage.querySelectorAll('.fc-kpi');
            const next = stage.querySelector('.fc-next');
            const conf = stage.querySelector('.fc-conf');

            function countUp(el, target, duration) {
                const start = performance.now();
                function step(now) {
                    // Clamp low as well as high: the first rAF timestamp can
                    // precede the performance.now() captured just above, and a
                    // negative p sends the cubic ease to a large negative number.
                    const p = Math.max(0, Math.min((now - start) / duration, 1));
                    const eased = 1 - Math.pow(1 - p, 3);
                    el.textContent = Math.round(target * eased).toLocaleString('en-US');
                    if (p < 1) requestAnimationFrame(step);
                }
                requestAnimationFrame(step);
            }

            function run() {
                kpis.forEach(k => k.classList.remove('in'));
                if (chart) chart.classList.remove('in');
                if (next) next.textContent = '0';
                if (conf) conf.textContent = '0';

                kpis.forEach((k, i) => t(() => k.classList.add('in'), 250 + i * 160));
                t(() => {
                    if (next) countUp(next, 48200, 1200);
                    if (conf) countUp(conf, 94, 1200);
                }, 650);
                t(() => { if (chart) chart.classList.add('in'); }, 750);

                t(run, 6400);
            }
            run();
        }

        // Inventory Management animation
        // Inventory: stock cards stream in, counts count up, stock bars fill
        function animateInventory(t) {
            const stage = document.getElementById('inventoryStage');
            if (!stage) return;
            const cards = stage.querySelectorAll('.inv-card');

            function countUp(el, target, duration) {
                const start = performance.now();
                function step(now) {
                    // Clamp low as well as high: the first rAF timestamp can
                    // precede the performance.now() captured just above, and a
                    // negative p sends the cubic ease to a large negative number.
                    const p = Math.max(0, Math.min((now - start) / duration, 1));
                    const eased = 1 - Math.pow(1 - p, 3);
                    el.textContent = Math.round(target * eased).toLocaleString('en-US');
                    if (p < 1) requestAnimationFrame(step);
                }
                requestAnimationFrame(step);
            }

            function run() {
                cards.forEach(c => {
                    c.classList.remove('in');
                    const num = c.querySelector('.inv-num');
                    if (num) num.textContent = '0';
                });

                cards.forEach((c, i) => {
                    t(() => {
                        c.classList.add('in');
                        const num = c.querySelector('.inv-num');
                        if (num) countUp(num, parseInt(num.dataset.target, 10), 800);
                    }, 200 + i * 150);
                });

                t(run, 200 + cards.length * 150 + 4200);
            }
            run();
        }

        // Rental Management: calendar fades in, booked range fills, booking card pops up
        function animateRental(t) {
            const stage = document.getElementById('rentalStage');
            if (!stage) return;
            const seqDays = stage.querySelectorAll('.rent-seq');
            const booking = document.getElementById('rentBooking');

            function run() {
                stage.classList.remove('shown');
                seqDays.forEach(d => d.classList.remove('booked'));
                if (booking) booking.classList.remove('in');

                t(() => stage.classList.add('shown'), 100);
                seqDays.forEach((d, i) => t(() => d.classList.add('booked'), 800 + i * 200));
                t(() => { if (booking) booking.classList.add('in'); }, 800 + seqDays.length * 200 + 350);

                t(run, 6200);
            }
            run();
        }

        // Customer Management animation
        // Customer Management: directory streams in, profile card pops up
        function animateCustomers(t) {
            const stage = document.getElementById('customerStage');
            if (!stage) return;
            const rows = stage.querySelectorAll('.cust-row');
            const profile = document.getElementById('custProfile');
            const ltv = stage.querySelector('.cps-ltv');

            function countUp(el, target, duration) {
                const start = performance.now();
                function step(now) {
                    // Clamp low as well as high: the first rAF timestamp can
                    // precede the performance.now() captured just above, and a
                    // negative p sends the cubic ease to a large negative number.
                    const p = Math.max(0, Math.min((now - start) / duration, 1));
                    const eased = 1 - Math.pow(1 - p, 3);
                    el.textContent = Math.round(target * eased).toLocaleString('en-US');
                    if (p < 1) requestAnimationFrame(step);
                }
                requestAnimationFrame(step);
            }

            function run() {
                rows.forEach(r => r.classList.remove('in'));
                if (profile) profile.classList.remove('in');
                if (ltv) ltv.textContent = '0';

                rows.forEach((r, i) => t(() => r.classList.add('in'), 250 + i * 150));
                const afterRows = 250 + rows.length * 150;

                t(() => {
                    if (profile) profile.classList.add('in');
                    if (ltv) countUp(ltv, 4230, 1100);
                }, afterRows + 300);

                t(run, afterRows + 4600);
            }
            run();
        }

        // Quotes: the same staggered build as the invoice, ending on the customer's
        // answer rather than on payment. No colour or template panels, so nothing
        // here touches the invoice studio's controls.
        function animateQuotes(t) {
            const doc = document.getElementById('quoteDoc');
            if (!doc) return;

            const status = document.getElementById('quoteStatus');
            const totalVal = doc.querySelector('.inv-total-value');

            function countTo(el, target, duration) {
                const startTime = performance.now();
                function update(now) {
                    const progress = Math.max(0, Math.min((now - startTime) / duration, 1));
                    const eased = 1 - Math.pow(1 - progress, 3);
                    el.textContent = '$' + (target * eased).toLocaleString('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                    if (progress < 1) requestAnimationFrame(update);
                }
                requestAnimationFrame(update);
            }

            // Reset to the intro state
            doc.classList.add('intro');
            doc.querySelectorAll('.inv-anim, .inv-item').forEach(el => el.classList.remove('in'));
            if (status) status.classList.remove('in');

            const steps = doc.querySelectorAll('.inv-top, .inv-billto, .inv-row-head, .inv-item, .inv-totals');
            steps.forEach((el, i) => t(() => el.classList.add('in'), 300 + i * 280));

            const lastAt = 300 + (steps.length - 1) * 280;

            if (totalVal) {
                totalVal.textContent = '$0.00';
                t(() => countTo(totalVal, 1234, 900), lastAt);
            }

            // The customer answers last, which is the point the whole page makes.
            t(() => { if (status) status.classList.add('in'); }, lastAt + 600);
            t(() => doc.classList.remove('intro'), lastAt + 1700);
        }

        // Invoicing animation
        // Invoice Studio: one-shot intro build, then reveal interactive controls
        function animateInvoices(t) {
            const studio = document.getElementById('invoiceStudio');
            const doc = document.getElementById('invoiceDoc');
            if (!studio || !doc) return;

            const colorPanel = document.getElementById('colorPanel');
            const templatePanel = document.getElementById('templatePanel');
            const status = document.getElementById('invStatus');
            const totalVal = doc.querySelector('.inv-total-value');

            function animateCounter(el, target, duration) {
                const startTime = performance.now();
                function update(now) {
                    // Same low clamp as countUp: the first rAF timestamp can
                    // precede startTime, and a negative progress sends the cubic
                    // ease to a large negative total.
                    const progress = Math.max(0, Math.min((now - startTime) / duration, 1));
                    const eased = 1 - Math.pow(1 - progress, 3);
                    el.textContent = '$' + (target * eased).toLocaleString('en-US', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    });
                    if (progress < 1) requestAnimationFrame(update);
                }
                requestAnimationFrame(update);
            }

            // Reset to the intro state
            doc.classList.add('intro');
            doc.querySelectorAll('.inv-anim, .inv-item').forEach(el => el.classList.remove('in'));
            if (status) status.classList.remove('in');
            if (colorPanel) colorPanel.classList.add('panel-hidden');
            if (templatePanel) templatePanel.classList.add('panel-hidden');

            // Staggered build of each section
            const steps = doc.querySelectorAll('.inv-top, .inv-billto, .inv-row-head, .inv-item, .inv-totals');
            steps.forEach((el, i) => t(() => el.classList.add('in'), 300 + i * 280));

            const lastAt = 300 + (steps.length - 1) * 280; // when the totals row lands

            // Count the total up as it appears
            if (totalVal) {
                totalVal.textContent = '$0.00';
                t(() => animateCounter(totalVal, 1234, 900), lastAt);
            }

            // Stamp "Paid"
            t(() => { if (status) status.classList.add('in'); }, lastAt + 600);

            // Reveal the interactive controls, then drop the intro state
            t(() => {
                if (colorPanel) colorPanel.classList.remove('panel-hidden');
                if (templatePanel) templatePanel.classList.remove('panel-hidden');
            }, lastAt + 1000);
            t(() => doc.classList.remove('intro'), lastAt + 1700);
        }

        // Invoice Studio: live color wheel + template switching (set up once)
        function initInvoiceStudio() {
            const studio = document.getElementById('invoiceStudio');
            const doc = document.getElementById('invoiceDoc');
            if (!studio || !doc) return;

            const wheel = document.getElementById('colorWheel');
            const thumb = document.getElementById('colorThumb');
            const lightSlider = document.getElementById('lightSlider');
            const lightThumb = document.getElementById('lightThumb');

            let hue = 227, sat = 79, light = 58;

            function apply() {
                studio.style.setProperty('--inv-accent', hue + ' ' + sat + '% ' + light + '%');
                if (lightSlider) {
                    lightSlider.style.background =
                        'linear-gradient(to right, hsl(' + hue + ' ' + sat + '% 72%), hsl(' + hue + ' ' + sat + '% 20%))';
                }
            }

            function pickColor(clientX, clientY) {
                const rect = wheel.getBoundingClientRect();
                const r = rect.width / 2;
                let x = clientX - rect.left - r;
                let y = clientY - rect.top - r;
                let dist = Math.sqrt(x * x + y * y);
                if (dist > r) { x = x / dist * r; y = y / dist * r; dist = r; }
                let ang = Math.atan2(y, x) * 180 / Math.PI;
                if (ang < 0) ang += 360;
                hue = Math.round(ang);
                sat = Math.round(48 + (dist / r) * 37); // 48–85, kept tasteful
                thumb.style.left = (r + x) + 'px';
                thumb.style.top = (r + y) + 'px';
                apply();
            }

            function pickLight(clientX) {
                const rect = lightSlider.getBoundingClientRect();
                let p = (clientX - rect.left) / rect.width;
                p = Math.max(0, Math.min(1, p));
                light = Math.round(64 - p * 30); // 64 (light) → 34 (dark)
                lightThumb.style.left = (p * 100) + '%';
                apply();
            }

            function makeDrag(pickFn) {
                return function (e) {
                    e.preventDefault();
                    const move = ev => {
                        const pt = ev.touches ? ev.touches[0] : ev;
                        pickFn(pt.clientX, pt.clientY);
                    };
                    move(e);
                    const up = () => {
                        document.removeEventListener('mousemove', move);
                        document.removeEventListener('mouseup', up);
                        document.removeEventListener('touchmove', move);
                        document.removeEventListener('touchend', up);
                    };
                    document.addEventListener('mousemove', move);
                    document.addEventListener('mouseup', up);
                    document.addEventListener('touchmove', move, { passive: false });
                    document.addEventListener('touchend', up);
                };
            }

            if (wheel) {
                const drag = makeDrag((x, y) => pickColor(x, y));
                wheel.addEventListener('mousedown', drag);
                wheel.addEventListener('touchstart', drag, { passive: false });
            }
            if (lightSlider) {
                const drag = makeDrag(x => pickLight(x));
                lightSlider.addEventListener('mousedown', drag);
                lightSlider.addEventListener('touchstart', drag, { passive: false });
            }

            const tmplBtns = studio.querySelectorAll('.tmpl-btn');
            tmplBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    doc.classList.remove('theme-modern', 'theme-contemporary', 'theme-classic');
                    doc.classList.add(btn.dataset.theme);
                    tmplBtns.forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                });
            });

            // Place the wheel thumb at the starting accent
            if (thumb && wheel) {
                const r = 80; // half of the 160px wheel
                const rad = hue * Math.PI / 180;
                const d = ((sat - 48) / 37) * r;
                thumb.style.left = (r + Math.cos(rad) * d) + 'px';
                thumb.style.top = (r + Math.sin(rad) * d) + 'px';
            }
            apply();
        }

        // --- Bank statement import -------------------------------------
        function animateBankImport(t) {
            const stage = document.getElementById('bankStage');
            if (!stage) return;
            const rows = stage.querySelectorAll('.bk-row');
            const newEl = document.getElementById('bkNew');
            const matEl = document.getElementById('bkMatched');

            function run() {
                stage.classList.remove('shown', 'done');
                rows.forEach(r => r.classList.remove('in', 'tagged'));
                if (newEl) newEl.textContent = '0';
                if (matEl) matEl.textContent = '0';

                t(() => stage.classList.add('shown'), 200);
                rows.forEach((r, i) => {
                    t(() => r.classList.add('in'), 600 + i * 190);
                    // The badge lands after the row, so you see it being decided
                    // rather than arriving pre-labelled.
                    t(() => {
                        r.classList.add('tagged');
                        const isNew = r.querySelector('.bk-new');
                        const el = isNew ? newEl : matEl;
                        if (el) el.textContent = String(parseInt(el.textContent, 10) + 1);
                    }, 900 + i * 190);
                });
                const end = 900 + rows.length * 190;
                t(() => stage.classList.add('done'), end + 200);
                t(run, end + 4600);
            }
            run();
        }

        // --- Spreadsheet import ---------------------------------------
        function animateSheetImport(t) {
            const stage = document.getElementById('sheetStage');
            if (!stage) return;
            const cols = stage.querySelectorAll('.sh-col');
            const status = document.getElementById('shStatus');

            function run() {
                stage.classList.remove('shown', 'done');
                cols.forEach(c => c.classList.remove('mapped'));
                stage.querySelectorAll('.sh-cell').forEach(c => c.classList.remove('lit'));
                if (status) status.textContent = 'Reading columns\u2026';

                t(() => stage.classList.add('shown'), 200);
                cols.forEach((col, i) => {
                    t(() => {
                        col.classList.add('mapped');
                        // light the column body so the mapping reads as a column,
                        // not just a header label
                        stage.querySelectorAll('.sh-cell[data-i="' + i + '"]')
                            .forEach(cell => cell.classList.add('lit'));
                    }, 700 + i * 320);
                });
                const end = 700 + cols.length * 320;
                t(() => {
                    stage.classList.add('done');
                    if (status) status.textContent = '4 columns matched \u00b7 128 rows ready to import';
                }, end + 200);
                t(run, end + 4400);
            }
            run();
        }

        // --- Report builder -------------------------------------------
        function animateReport(t) {
            const stage = document.getElementById('reportStage');
            if (!stage) return;
            const lines = stage.querySelectorAll('.rp-line');
            const net = stage.querySelector('.rp-net');

            function countUp(el, target, duration) {
                const start = performance.now();
                function step(now) {
                    const p = Math.max(0, Math.min((now - start) / duration, 1));
                    const eased = 1 - Math.pow(1 - p, 3);
                    el.textContent = (target * eased).toLocaleString('en-US', {
                        minimumFractionDigits: 2, maximumFractionDigits: 2
                    });
                    if (p < 1) requestAnimationFrame(step);
                }
                requestAnimationFrame(step);
            }

            function run() {
                stage.classList.remove('done');
                lines.forEach(l => l.classList.remove('in'));
                if (net) net.textContent = '0.00';

                lines.forEach((l, i) => t(() => l.classList.add('in'), 350 + i * 260));
                const end = 350 + lines.length * 260;
                t(() => { if (net) countUp(net, 19186, 900); }, end);
                t(() => stage.classList.add('done'), end + 700);
                t(run, end + 4800);
            }
            run();
        }

        // --- Stripe payments ------------------------------------------
        function animateStripe(t) {
            const stage = document.getElementById('stripeStage');
            if (!stage) return;
            const rows = stage.querySelectorAll('.st-row');
            const gross = stage.querySelector('.st-gross');
            const fee = stage.querySelector('.st-fee');
            const net = stage.querySelector('.st-net');

            function money(el, target, duration, prefix) {
                const start = performance.now();
                function step(now) {
                    const p = Math.max(0, Math.min((now - start) / duration, 1));
                    const eased = 1 - Math.pow(1 - p, 3);
                    el.textContent = prefix + (target * eased).toLocaleString('en-US', {
                        minimumFractionDigits: 2, maximumFractionDigits: 2
                    });
                    if (p < 1) requestAnimationFrame(step);
                }
                requestAnimationFrame(step);
            }

            function run() {
                stage.classList.remove('done');
                rows.forEach(r => r.classList.remove('in', 'paid'));
                if (gross) gross.textContent = '$0.00';
                if (fee) fee.textContent = '\u2212$0.00';
                if (net) net.textContent = '$0.00';

                rows.forEach((r, i) => {
                    t(() => r.classList.add('in'), 300 + i * 340);
                    t(() => r.classList.add('paid'), 700 + i * 340);
                });
                const end = 700 + rows.length * 340;
                t(() => {
                    stage.classList.add('done');
                    if (gross) money(gross, 2326.50, 800, '$');
                    if (fee) money(fee, 70.77, 800, '\u2212$');
                    if (net) money(net, 2255.73, 900, '$');
                }, end + 250);
                t(run, end + 5000);
            }
            run();
        }

        // --- Payroll pay run ------------------------------------------
        // The deduction cells land a beat after their row so the figures read as
        // being worked out. The net total counts up across the same beats, which
        // is the number the employer actually cares about.
        function animatePayroll(t) {
            const stage = document.getElementById('payrollStage');
            if (!stage) return;
            const rows = stage.querySelectorAll('.pr-row');
            const totalEl = document.getElementById('prNet');
            const nets = [1804.74, 1524.79, 1273.18];

            function run() {
                stage.classList.remove('shown', 'done');
                rows.forEach(r => r.classList.remove('in', 'tagged'));
                if (totalEl) totalEl.textContent = '$0.00';
                let running = 0;

                t(() => stage.classList.add('shown'), 200);
                rows.forEach((r, i) => {
                    t(() => r.classList.add('in'), 600 + i * 420);
                    t(() => {
                        r.classList.add('tagged');
                        running += nets[i] || 0;
                        if (totalEl) {
                            totalEl.textContent = '$' + running.toLocaleString('en-US', {
                                minimumFractionDigits: 2, maximumFractionDigits: 2
                            });
                        }
                    }, 1000 + i * 420);
                });
                const end = 1000 + rows.length * 420;
                t(() => stage.classList.add('done'), end + 200);
                t(run, end + 4800);
            }
            run();
        }

        initInvoiceStudio();

        // Standalone hero demo (feature pages): one panel, no tab bar, so kick
        // its animation off directly. Every animate*() reschedules itself and
        // resets its own state at the top of each pass, so a single call loops
        // forever. The receipt scan self-starts above and needs nothing here.
        const heroDemo = document.querySelector('[data-feature-demo]');
        if (heroDemo && tabBtns.length === 0) {
            startTabAnimation(heroDemo.dataset.featureDemo);
        }

});
