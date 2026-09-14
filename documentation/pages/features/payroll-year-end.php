<?php
require_once __DIR__ . '/../../../resources/icons.php';
$pageTitle = 'Payroll Year-End';
$pageDescription = 'Produce T4 slips and CRA XML, Quebec RL-1 slips, and Records of Employment for ROE Web from Argo Books, and see what you owe CRA and when.';
$currentPage = 'payroll-year-end';
$pageCategory = 'features';

include __DIR__ . '/../../docs-header.php';
?>

        <div class="docs-content">
            <p>Once your pay runs are recorded, Argo Books produces the forms you have to give employees and
            file with the government: T4 slips, the T4 summary, Quebec RL-1 slips, and the Record of
            Employment file. You need employees and approved pay runs first; see <a class="link" href="payroll.php">Payroll</a>.</p>

            <div class="info-box">
                <p><strong>Note:</strong> Argo Books does not file on your behalf. It generates the slips and the upload file; you submit them through CRA, Revenu Québec or ROE Web yourself.</p>
            </div>

            <h2>Remittance Due</h2>
            <p>The payroll dashboard shows what has to reach CRA next and by when. This is deliberately
            <strong>last month's withholdings against this month's due date</strong>, not the current
            month's running total. A regular remitter pays by the 15th of the month following the month the
            deductions were withheld, so the figure you need is the one that has already closed.</p>

            <h2>T4 Slips and Summary</h2>
            <p>At year end, Argo Books produces both the slips your employees need and the file CRA needs:</p>
            <ol class="steps-list">
                <li>Go to "Pay Runs" and click "Year end"</li>
                <li>Select the tax year</li>
                <li>Enter your filing details, including your CRA payroll account number</li>
                <li>Clear anything listed under "Fix these before filing"</li>
                <li>Click "Download slips" for the PDFs to distribute, and "Export for filing" for the XML to upload</li>
            </ol>
            <p>The XML is wrapped in the T619 transmittal that CRA's electronic filing expects, so it can be
            uploaded as-is.</p>

            <div class="info-box">
                <p><strong>Deadline:</strong> T4s are due to CRA and to your employees by the last day of February.</p>
            </div>

            <div class="info-box">
                <p><strong>Note:</strong> The payroll account number is the one ending in <strong>RP</strong>, not RC. It is on your CRA statement of account. Quebec employers also need their Revenu Québec identification number, which is a different number found on your Revenu Québec statement.</p>
            </div>

            <h2>Amendments and Cancellations</h2>
            <p>If something was wrong on a slip you have already filed, regenerate it as an amendment rather
            than a new original. Argo Books supports all three types:</p>
            <ul>
                <li><strong>Original:</strong> The first filing for that employee and year</li>
                <li><strong>Amended:</strong> A correction to a slip already filed</li>
                <li><strong>Cancelled:</strong> A slip that should not have been filed at all</li>
            </ul>
            <p>Slips are selected per employee, so you can amend one person without regenerating the whole
            year. Tick only the employees whose slips changed.</p>

            <div class="info-box">
                <p><strong>Important:</strong> Send an amended return on its own. CRA rejects a return that mixes amended and original slips.</p>
            </div>

            <h2>Quebec RL-1</h2>
            <p>Employers in Quebec file with Revenu Québec as well as CRA. Argo Books produces RL-1 slips
            and the RL-1 summary as PDFs alongside the federal T4 package, covering QPP, QPIP and Quebec
            tax. They do not include the health services fund.</p>

            <h2>Record of Employment</h2>
            <p>An ROE is due within five days of an interruption of earnings, not at year end, so it is
            offered from the employee's row rather than from the year-end tool. Argo Books fills in what
            your pay runs already know, including the last day paid and the insurable hours and earnings for
            each pay period, and saves the file ROE Web accepts as an upload.</p>
            <ol class="steps-list">
                <li>On the Employees page, click the Record of Employment button on the employee's row</li>
                <li>Check the figures under "From your pay runs"</li>
                <li>Choose the reason for issuing, and whether you expect them back</li>
                <li>Enter who Service Canada should call about this ROE: someone at your company, not the employee</li>
                <li>Click "Save ROE XML" and upload the file to ROE Web</li>
            </ol>

            <div class="info-box">
                <p><strong>Note:</strong> Argo Books never fills in block 16, the reason for issuing. It has no way to know whether someone quit, was let go or went on leave, and that answer affects their benefit claim, so you choose it every time.</p>
            </div>

            <div class="info-box">
                <p><strong>Tip:</strong> Not ready to submit? "Save as draft" makes a file ROE Web loads without submitting it, so you can check it there first. If you would rather key the figures in by hand, "View worksheet" shows them on one sheet.</p>
            </div>

            <h2>Before You File</h2>
            <p>The year-end tool lists anything that would stop a filing under "Fix these before filing",
            so work through that first. It also shows your total employment income and tax for the year,
            which is worth comparing against what you actually remitted to CRA over the twelve months.</p>
            <p>Two details that are easy to miss:</p>
            <ul>
                <li><strong>Dental coverage codes</strong> are required on every T4, so an employee missing one will hold up the filing</li>
                <li><strong>Social insurance numbers</strong> are not strictly required for a T4, and one will file without them, but the contributions are then not credited to that employee. An RL-1 or a Record of Employment does require one</li>
            </ul>

            <div class="page-navigation">
                <a href="payroll.php" class="nav-button prev">
                    <span class="nav-label">Previous</span>
                    <span class="nav-title">&larr; Payroll</span>
                </a>
                <a href="bank-statement-import.php" class="nav-button next">
                    <span class="nav-label">Next</span>
                    <span class="nav-title">Bank Statement Import &rarr;</span>
                </a>
            </div>
        </div>

<?php include __DIR__ . '/../../docs-footer.php'; ?>
