<?php
require_once __DIR__ . '/../../../resources/icons.php';
$pageTitle = 'Keyboard Shortcuts';
$pageDescription = 'Reference guide for keyboard shortcuts in Argo Books on Windows, Linux and Mac, including the Report Generator layout designer.';
$currentPage = 'keyboard_shortcuts';
$pageCategory = 'reference';

include __DIR__ . '/../../docs-header.php';
?>

        <div class="docs-content">
            <h2>Application Shortcuts</h2>
            <p>These shortcuts are available throughout the application. On a Mac, use <strong>Command</strong> (<strong>&#8984;</strong>) wherever Windows and Linux use <strong>Ctrl</strong>.</p>
            <p>Undo, redo and new stay out of the way while you are typing: in a text box they do what they do in any text box, so Ctrl + Z takes back the last thing you typed rather than the last thing you saved.</p>
            <div class="comparison-table-wrapper">
                <table class="comparison-table">
                    <thead>
                        <tr>
                            <th>Windows &amp; Linux</th>
                            <th>Mac</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><strong>Ctrl + K</strong></td><td><strong>&#8984; + K</strong></td><td>Open search / quick actions</td></tr>
                        <tr><td><strong>Ctrl + S</strong></td><td><strong>&#8984; + S</strong></td><td>Save your company</td></tr>
                        <tr><td><strong>Ctrl + Z</strong></td><td><strong>&#8984; + Z</strong></td><td>Undo your last change</td></tr>
                        <tr><td><strong>Ctrl + Y</strong></td><td><strong>&#8984; + Y</strong></td><td>Redo the change you undid</td></tr>
                        <tr><td><strong>Ctrl + Shift + Z</strong></td><td><strong>&#8984; + Shift + Z</strong></td><td>Redo the change you undid (alternative)</td></tr>
                        <tr><td><strong>Ctrl + F</strong></td><td><strong>&#8984; + F</strong></td><td>Jump to the search box on the page you are on</td></tr>
                        <tr><td><strong>Ctrl + N</strong></td><td><strong>&#8984; + N</strong></td><td>Create a new one of whatever the page lists, such as an invoice on the Invoices page</td></tr>
                        <tr><td><strong>Ctrl + Shift + S</strong></td><td><strong>&#8984; + Shift + S</strong></td><td>Save your company under a new name or in a new place (Save As)</td></tr>
                        <tr><td><strong>Ctrl + Scroll</strong></td><td><strong>&#8984; + Scroll</strong>, or pinch on the trackpad</td><td>Zoom a chart or an invoice preview</td></tr>
                        <tr><td><strong>F11</strong></td><td>The green button at the top left of the window</td><td>Enter or leave full screen</td></tr>
                        <tr><td><strong>Esc</strong></td><td><strong>Esc</strong></td><td>Close the open dialog or panel, or leave full screen</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="info-box">
                <strong>In the quick actions panel:</strong> type to search, use <strong>&uarr;</strong> and <strong>&darr;</strong> to move through the results, <strong>Enter</strong> to run the highlighted one, and <strong>Esc</strong> to close.
            </div>

            <div class="info-box">
                <strong>Full screen:</strong> <strong>F11</strong> hides the title bar and fills the screen, and pressing it again returns you. <strong>Esc</strong> also leaves full screen, but only when nothing else is using it: if a dialog is open, Esc closes the dialog and you stay full screen. If you would rather use the mouse, the restore button in the top right corner also brings the window back. On a Mac, the F11 key belongs to macOS, which uses it to show the desktop, so use the green button at the top left of the window instead.
            </div>

            <h2>Report Generator Layout Designer</h2>
            <p>The following shortcuts are available when working in the layout designer (Step 2) of the Report Generator. Here <strong>Ctrl + S</strong> (<strong>&#8984; + S</strong> on a Mac) saves the layout as a template rather than saving your company.</p>

            <h3>General Actions</h3>
            <div class="comparison-table-wrapper">
                <table class="comparison-table">
                    <thead>
                        <tr>
                            <th>Windows &amp; Linux</th>
                            <th>Mac</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><strong>Ctrl + Z</strong></td><td><strong>&#8984; + Z</strong></td><td>Undo last action</td></tr>
                        <tr><td><strong>Ctrl + Y</strong></td><td><strong>&#8984; + Y</strong></td><td>Redo last undone action</td></tr>
                        <tr><td><strong>Ctrl + Shift + Z</strong></td><td><strong>&#8984; + Shift + Z</strong></td><td>Redo last undone action (alternative)</td></tr>
                        <tr><td><strong>Ctrl + S</strong></td><td><strong>&#8984; + S</strong></td><td>Save the current layout as a template</td></tr>
                        <tr><td><strong>Ctrl + G</strong></td><td><strong>&#8984; + G</strong></td><td>Show or hide the alignment grid</td></tr>
                    </tbody>
                </table>
            </div>

            <h3>Selection &amp; Editing</h3>
            <div class="comparison-table-wrapper">
                <table class="comparison-table">
                    <thead>
                        <tr>
                            <th>Windows &amp; Linux</th>
                            <th>Mac</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><strong>Ctrl + A</strong></td><td><strong>&#8984; + A</strong></td><td>Select all elements on the current page</td></tr>
                        <tr><td><strong>Ctrl + D</strong></td><td><strong>&#8984; + D</strong></td><td>Duplicate selected element(s)</td></tr>
                        <tr><td><strong>Delete</strong> or <strong>Backspace</strong></td><td><strong>Delete</strong></td><td>Delete selected element(s)</td></tr>
                        <tr><td><strong>Esc</strong></td><td><strong>Esc</strong></td><td>Clear the selection</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="info-box">
                <strong>Note:</strong> The canvas shortcuts above act on the current selection, so click an element first. Ctrl + A (&#8984; + A on a Mac) extends a selection to everything on the page rather than starting one from nothing.
            </div>

            <h3>Element Movement (Fine Control)</h3>
            <div class="comparison-table-wrapper">
                <table class="comparison-table">
                    <thead>
                        <tr>
                            <th>Shortcut</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><strong>&larr;</strong></td><td>Move element 1 pixel left</td></tr>
                        <tr><td><strong>&rarr;</strong></td><td>Move element 1 pixel right</td></tr>
                        <tr><td><strong>&uarr;</strong></td><td>Move element 1 pixel up</td></tr>
                        <tr><td><strong>&darr;</strong></td><td>Move element 1 pixel down</td></tr>
                    </tbody>
                </table>
            </div>

            <h3>Element Movement (Large Steps)</h3>
            <div class="comparison-table-wrapper">
                <table class="comparison-table">
                    <thead>
                        <tr>
                            <th>Shortcut</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td><strong>Shift + &larr;</strong></td><td>Move element 10 pixels left</td></tr>
                        <tr><td><strong>Shift + &rarr;</strong></td><td>Move element 10 pixels right</td></tr>
                        <tr><td><strong>Shift + &uarr;</strong></td><td>Move element 10 pixels up</td></tr>
                        <tr><td><strong>Shift + &darr;</strong></td><td>Move element 10 pixels down</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="page-navigation">
                <a href="supported-languages.php" class="nav-button prev">
                    <span class="nav-label">Previous</span>
                    <span class="nav-title">&larr; Supported Languages</span>
                </a>
                <a href="../security/encryption.php" class="nav-button next">
                    <span class="nav-label">Next</span>
                    <span class="nav-title">Encryption &rarr;</span>
                </a>
            </div>
        </div>

<?php include __DIR__ . '/../../docs-footer.php'; ?>
