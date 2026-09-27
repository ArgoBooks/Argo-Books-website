<?php
require_once __DIR__ . '/../../../resources/icons.php';
$pageTitle = 'Payroll';
$pageDescription = 'Run Canadian payroll in Argo Books: add employees, calculate CPP, EI and income tax, approve pay runs, and keep deduction tables current.';
$currentPage = 'payroll';
$pageCategory = 'features';

include __DIR__ . '/../../docs-header.php';
?>

        <div class="docs-content">
            <p>Argo Books includes a payroll engine for Canadian employers. Add your employees, run a pay
            period, and Argo Books calculates CPP, EI and income tax withholdings, then posts the net pay
            straight into your books as an expense.</p>

            <div class="info-box">
                <p><strong>Note:</strong> Payroll is a Premium feature for Canadian employers. It follows CRA and Revenu Québec rules and produces Canadian forms (T4, RL-1, ROE), so it's not designed for employers outside Canada. <a href="../getting-started/version-comparison.php" class="link">See the version comparison</a> for what each plan includes.</p>
            </div>

            <h2>Adding Employees</h2>
            <p>Everything payroll needs about a person lives on the Employees page:</p>
            <ol class="steps-list">
                <li>Go to "Employees" in the navigation menu</li>
                <li>Click "Add employee"</li>
                <li>Enter their name, SIN, and start date</li>
                <li>Select their province of employment, which determines the tax tables used</li>
                <li>Choose salary or hourly, and set the pay frequency</li>
                <li>Enter their TD1 federal and provincial claim amounts from the forms they filled in</li>
                <li>Set any CPP or EI exemptions that apply</li>
                <li>Select the dental coverage code, which becomes box 45 on their T4</li>
            </ol>

            <div class="info-box">
                <p><strong>Note:</strong> Employees are archived rather than deleted. The Archive button on an employee's row archives them, and the same button restores them. Archived employees stay in the list; use the Filter to show only active or only archived employees.</p>
            </div>

            <h2>Running Payroll</h2>
            <p>Pay runs live on their own page. Go to "Pay Runs" and click "Run payroll" to start one. It
            is three steps:</p>
            <ol class="steps-list">
                <li><strong>Period:</strong> Set the period dates and pay date, and choose who is being paid</li>
                <li><strong>Amounts:</strong> Enter hours or pay, plus vacation pay and any other earnings</li>
                <li><strong>Review:</strong> Check the calculated deductions and net pay, then approve</li>
            </ol>
            <p>The review step also shows your total cost, meaning gross pay plus your employer share of CPP
            and EI, and the total to remit.</p>
            <p>Approving the run writes one expense per employee at their <strong>net pay</strong>. The
            withheld amounts are not written as separate expenses, because the money has not left your
            business yet. It leaves when you make your remittance, which keeps the expense from being
            counted twice.</p>

            <h2>Warnings and Blocks</h2>
            <p>Argo Books checks a pay run before it is approved. Some problems stop the run, others just
            flag it:</p>
            <ul>
                <li><strong>Blocked:</strong> A pay period that runs backwards, where the end date is before the start date</li>
                <li><strong>Warning:</strong> A period that overlaps one an employee has already been paid for</li>
                <li><strong>Warning:</strong> A pay date more than 90 days after the period ends</li>
                <li><strong>Warning:</strong> An employee reaching a CPP, CPP2, EI or QPIP annual maximum partway through the period</li>
                <li><strong>Warning:</strong> A missing TD1 claim amount</li>
            </ul>
            <p>Warnings appear on the review step and can be accepted. They are usually mistakes, but each
            one is occasionally exactly right, so the decision stays with you.</p>

            <h2>Voiding a Pay Run</h2>
            <p>An approved run is not deleted. Voiding it writes a reversing run and removes the associated
            wage expenses, so the correction is visible in your history rather than the original run simply
            disappearing.</p>

            <h2>Deduction Tables</h2>
            <p>No tax rates are built into Argo Books. Every rate, bracket and annual ceiling lives in a
            dated edition file. CRA and Revenu Québec reissue their figures twice a year, in January and
            July, and Argo Books downloads the matching edition when a pay run needs one it does not
            already have.</p>
            <p>Downloaded editions are checked before they are trusted. An edition is only kept if it
            parses, its identifier matches the one requested, and every derived maximum reproduces from its
            own rate with brackets meeting cleanly at each boundary. Anything that fails those checks is
            discarded and changes nothing.</p>

            <h2>Quebec</h2>
            <p>Quebec is calculated separately, because none of its figures appear in CRA's tables. Argo
            Books implements Revenu Québec's own method, including QPP, QPIP and the federal abatement.
            Quebec employers also get RL-1 slips at year end alongside their T4s.</p>

            <h2>What Payroll Does Not Cover</h2>
            <p>Argo Books handles the common case for small employers. It does not currently support:</p>
            <ul>
                <li>Registered pension plan contributions or union dues</li>
                <li>The Quebec health services fund or labour standards contributions</li>
                <li>Direct deposit or paying employees from within the app</li>
                <li>Filing directly with CRA or Revenu Québec</li>
            </ul>
            <p>Year-end forms are generated for you to file yourself. See <a href="payroll-year-end.php" class="link">Payroll Year-End</a>.</p>

            <div class="page-navigation">
                <a href="invoicing.php" class="nav-button prev">
                    <span class="nav-label">Previous</span>
                    <span class="nav-title">&larr; Invoicing & Payments</span>
                </a>
                <a href="payroll-year-end.php" class="nav-button next">
                    <span class="nav-label">Next</span>
                    <span class="nav-title">Payroll Year-End &rarr;</span>
                </a>
            </div>
        </div>

<?php include __DIR__ . '/../../docs-footer.php'; ?>
