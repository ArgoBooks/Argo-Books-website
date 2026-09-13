<?php
// compare/mockups/argo-books-vs-quickbooks.php
//
// Decorative price chart for the QuickBooks comparison, included by
// compare/compare-page.php inside .diff-mockup. Reads $argo_monthly and the
// price variables that compare/data/argo-books-vs-quickbooks.php declares; both that
// file and this one are included at global scope by the template.
?>
<!-- Decorative dashboard mockup. aria-hidden so it adds no
                             indexable text (no duplicate-content/SEO impact). -->
                        <svg viewBox="0 0 640 460" role="img" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg" font-family="'IBM Plex Sans', sans-serif">
                            <defs>
                                <clipPath id="dmClip2"><rect x="1" y="1" width="638" height="458" rx="18"/></clipPath>
                            </defs>
                            <g clip-path="url(#dmClip2)">
                                <rect x="0" y="0" width="640" height="460" fill="#ffffff"/>

                                <!-- Title -->
                                <text x="40" y="54" font-family="Fraunces, Georgia, serif" font-size="21" font-weight="700" fill="#0f172a">What you'll pay per month</text>
                                <text x="40" y="80" font-size="14" fill="#0f172a">QuickBooks keeps climbing. Argo Books stays flat.</text>

                                <!-- Legend (under the subtitle, clear of the callout) -->
                                <line x1="40" y1="104" x2="58" y2="104" stroke="#ef4444" stroke-width="2.6" stroke-linecap="round"/>
                                <text x="64" y="109" font-size="13" fill="#0f172a">QuickBooks</text>
                                <line x1="166" y1="104" x2="184" y2="104" stroke="#3f63e8" stroke-width="2.6" stroke-linecap="round"/>
                                <text x="190" y="109" font-size="13" fill="#0f172a">Argo Books</text>

                                <!-- Gridlines + y labels -->
                                <g stroke="#f1f5f9" stroke-width="1">
                                    <line x1="88" y1="194" x2="560" y2="194"/>
                                    <line x1="88" y1="289" x2="560" y2="289"/>
                                    <line x1="88" y1="384" x2="560" y2="384"/>
                                </g>
                                <g font-size="14" font-weight="600" fill="#0f172a" text-anchor="end">
                                    <text x="76" y="199">$<?= $qb_easystart ?></text>
                                    <text x="76" y="294">$<?= $argo_monthly ?></text>
                                    <text x="76" y="389">$0</text>
                                </g>

                                <!-- Savings gap -->
                                <path d="M88 270 L206 251 L324 232 L442 213 L560 194 L560 289 L88 289 Z" fill="#10b981" opacity="0.10"/>
                                <text x="300" y="263" text-anchor="middle" font-size="14" font-weight="600" fill="#15803d">You keep the difference</text>

                                <!-- QuickBooks line -->
                                <polyline points="88,270 206,251 324,232 442,213 560,194" fill="none" stroke="#ef4444" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>
                                <g fill="#ef4444"><circle cx="88" cy="270" r="3"/><circle cx="206" cy="251" r="3"/><circle cx="324" cy="232" r="3"/><circle cx="442" cy="213" r="3"/></g>
                                <text x="92" y="260" font-size="12" font-weight="600" fill="#0f172a">was $<?= (int) round($qb_easystart / 1.7) ?></text>

                                <!-- Argo line -->
                                <line x1="88" y1="289" x2="560" y2="289" stroke="#3f63e8" stroke-width="2.6" stroke-linecap="round"/>
                                <g fill="#3f63e8"><circle cx="88" cy="289" r="3"/><circle cx="206" cy="289" r="3"/><circle cx="324" cy="289" r="3"/><circle cx="442" cy="289" r="3"/></g>

                                <!-- End markers -->
                                <circle cx="560" cy="194" r="3.2" fill="#ef4444" stroke="#ffffff" stroke-width="1.3"/>
                                <circle cx="560" cy="289" r="3.6" fill="#3f63e8" stroke="#ffffff" stroke-width="1.3"/>

                                <!-- End pills -->
                                <text x="595" y="174" text-anchor="middle" font-size="11" font-weight="600" fill="#ef4444">&amp; climbing</text>
                                <rect x="564" y="182" width="62" height="22" rx="6" fill="#fee2e2"/>
                                <text x="595" y="197" text-anchor="middle" font-size="13" font-weight="700" fill="#b91c1c">$<?= $qb_easystart ?>/mo</text>
                                <rect x="564" y="278" width="62" height="22" rx="6" fill="#eef2fe"/>
                                <text x="595" y="293" text-anchor="middle" font-size="13" font-weight="700" fill="#3f63e8">$<?= $argo_monthly ?>/mo</text>
                                <text x="595" y="313" text-anchor="middle" font-size="11" font-weight="600" fill="#0f172a">forever</text>

                                <!-- X axis -->
                                <g font-size="14" font-weight="600" fill="#0f172a" text-anchor="middle">
                                    <text x="88" y="408">2022</text>
                                    <text x="206" y="408">2023</text>
                                    <text x="324" y="408">2024</text>
                                    <text x="442" y="408">2025</text>
                                    <text x="560" y="408">2026</text>
                                </g>
                            </g>
                            <rect x="1" y="1" width="638" height="458" rx="18" fill="none" stroke="#e2e8f0" stroke-width="1"/>
                        </svg>
