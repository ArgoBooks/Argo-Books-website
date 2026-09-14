<?php
require_once __DIR__ . '/../../../resources/icons.php';
$pageTitle = 'Rental Management';
$pageDescription = 'Learn how to manage equipment rentals, track availability, handle bookings, and process returns with Argo Books rental features.';
$currentPage = 'rental';
$pageCategory = 'features';

include __DIR__ . '/../../docs-header.php';
?>

        <div class="docs-content">
            <p>Manage equipment rentals, take bookings for later dates, and process returns with deposits and extra charges. Perfect for rental businesses of any size.</p>

            <h2>Before You Start</h2>
            <p>A rental item rents out stock you already track, so each one needs a stock record first:</p>
            <ol class="steps-list">
                <li>Create the product under "Expense products" or "Revenue products" and turn on Track Inventory. See <a class="link" href="product-management.php">Product Management</a>.</li>
                <li>Add its stock: on "Stock Levels", click "Add Item", pick the product and a <a class="link" href="locations.php">location</a>, and enter how many you own. Recording a purchase of the product adds it too. See <a class="link" href="inventory.php">Inventory Management</a>.</li>
            </ol>

            <h2>Setting Up Rental Items</h2>
            <ol class="steps-list">
                <li>Go to Rental Inventory in the sidebar</li>
                <li>Click "Add Item" to create a new rental item</li>
                <li>Pick the stock record it rents out, shown as the product and its location. Its stock is how many units you have.</li>
                <li>Set a daily, weekly, or monthly rate (at least one)</li>
                <li>Set the security deposit for each unit (optional)</li>
            </ol>

            <h2>Tracking Availability</h2>
            <p>Monitor your rental inventory status:</p>
            <ul>
                <li><strong>Total Items:</strong> Every unit you own, on the shelf or out with customers</li>
                <li><strong>Available:</strong> Units on the shelf and ready to rent</li>
                <li><strong>Rented Out:</strong> Units currently out with customers</li>
                <li><strong>In Maintenance:</strong> Units temporarily unavailable for service</li>
            </ul>
            <p>Click the calendar icon on an item to see how many units are free on each day, including units reserved for later dates.</p>

            <h2>Creating a Rental Record</h2>
            <ol class="steps-list">
                <li>Go to Rental Records in the sidebar</li>
                <li>Click "New Rental" to create a new record</li>
                <li>Select the customer and the start and due dates. Customers come from the <a class="link" href="customers.php">Customers</a> page, or use "Create new customer"</li>
                <li>Add each rental item and its quantity</li>
                <li>Check the deposit and estimated amount</li>
                <li>Save the rental record</li>
            </ol>
            <p>You can also rent out a single item from Rental Inventory with the Rent Out button.</p>

            <div class="info-box">
                <p><strong>Booking ahead:</strong> A rental that starts after today is saved as a reservation. Its stock stays on the shelf until you check it out, and the same units can't be booked for dates that overlap.</p>
            </div>

            <h2>Rental Status</h2>
            <p>Track each rental through its lifecycle:</p>
            <ul>
                <li><strong>Reserved:</strong> Booked for later dates and not picked up yet</li>
                <li><strong>Active:</strong> Item is currently rented out</li>
                <li><strong>Returned:</strong> Item has been returned and rental is complete</li>
                <li><strong>Overdue:</strong> Return date has passed</li>
                <li><strong>Cancelled:</strong> A reservation that was cancelled</li>
            </ul>

            <h2>Checking Out a Reservation</h2>
            <ol class="steps-list">
                <li>Find the reserved rental in Rental Records</li>
                <li>Click "Check Out" when the customer picks it up</li>
                <li>The units leave your stock and the rental becomes Active</li>
            </ol>
            <p>If the customer picks it up early, the rental starts that day. To cancel a reservation instead, click "Cancel Reservation".</p>

            <h2>Processing Returns</h2>
            <ol class="steps-list">
                <li>Find the rental in your active rentals list</li>
                <li>Click "Return" and check the return date. The cost is worked out from the days it was out.</li>
                <li>Add any late fee or damage as an extra charge, with a note saying what it is for</li>
                <li>Enter how much of the deposit to refund. The whole deposit is filled in; enter less to keep part of it.</li>
                <li>Tick "Mark as Paid" if the customer has paid</li>
                <li>Click "Confirm Return". The units go back into stock automatically.</li>
            </ol>

            <div class="info-box">
                <p><strong>Tip:</strong> A rental marked paid without an invoice is recorded in your revenue. Marking it unpaid removes that revenue again. If you create an invoice from the rental instead, the invoice records it, with one line for each item and one for any extra charges.</p>
            </div>

            <h2>Maintenance Mode</h2>
            <p>Mark items as unavailable when they need service:</p>
            <ul>
                <li>Edit the item and set its status to "In Maintenance" to remove it from available inventory</li>
                <li>Return it to "Active" status when service is complete</li>
            </ul>

            <h2>Rental Records Dashboard</h2>
            <p>The Rental Records page displays four summary cards:</p>
            <ul>
                <li><strong>Total Rentals:</strong> All rental records</li>
                <li><strong>Active:</strong> Currently rented out</li>
                <li><strong>Overdue:</strong> Rentals past their return date</li>
                <li><strong>Total Revenue:</strong> Revenue from completed rentals</li>
            </ul>

            <div class="page-navigation">
                <a href="bank-matching.php" class="nav-button prev">
                    <span class="nav-label">Previous</span>
                    <span class="nav-title">&larr; Bank Matching</span>
                </a>
                <a href="customers.php" class="nav-button next">
                    <span class="nav-label">Next</span>
                    <span class="nav-title">Customer Management &rarr;</span>
                </a>
            </div>
        </div>

<?php include __DIR__ . '/../../docs-footer.php'; ?>
