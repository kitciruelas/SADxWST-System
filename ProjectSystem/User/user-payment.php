<?php
session_start();
include '../config/config.php'; // Correct path to your config file

// Check if the user is logged in
if (!isset($_SESSION['id'])) {
    header("Location: login.php"); // Redirect to login if not logged in
    exit();
}

// Store the logged-in user's ID
$userId = $_SESSION['id'];

// Define the function to get the room number
function getRoomNumber($user_id) {
    global $conn; // Use the global database connection

    // Query to get the room number based on the user_id
    $query = "
        SELECT rooms.room_number
        FROM roomassignments
        JOIN rooms ON roomassignments.room_id = rooms.room_id
        WHERE roomassignments.user_id = $user_id
        LIMIT 1
    ";

    // Execute the query
    $result = mysqli_query($conn, $query);

    // Check if the user has an assigned room
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        return $row['room_number']; // Return the room number
    } else {
        return 'No room assigned'; // Return a default message if no room is assigned
    }
}

// Add this new function to format the receipt
function generateReceiptHTML($payment) {
    return "
        <div class='receipt-content'>
            <h4>Payment Receipt</h4>
            <div class='receipt-details'>
                <p><strong>Resident:</strong> {$payment['resident_name']}</p>
                <p><strong>Amount:</strong> ₱" . number_format($payment['amount'], 2) . "</p>
                <p><strong>Date:</strong> {$payment['payment_date']}</p>
                <p><strong>Status:</strong> {$payment['status']}</p>
                <p><strong>Payment Method:</strong> {$payment['payment_method']}</p>
                " . ($payment['reference_number'] ? "<p><strong>Reference Number:</strong> {$payment['reference_number']}</p>" : "") . "
            </div>
        </div>
    ";
}

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="../Admin/Css_Admin/admin_manageuser.css"> <!-- I-load ang custom CSS sa huli -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">

    <!-- jQuery (needed for Bootstrap's JavaScript plugins) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Bootstrap JS -->
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<style>
.main-content {
   padding-top: 80px;
}
</style>
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="menu" id="hamburgerMenu">
            <i class="fas fa-bars"></i> <!-- Hamburger menu icon -->
        </div>

        <div class="sidebar-nav">
        <a href="user-dashboard.php" class="nav-link"><i class="fas fa-home"></i><span>Home</span></a>
        <a href="user_room.php" class="nav-link"><i class="fas fa-key"></i> <span>Room Assign</span></a>
        <a href="visitor_log.php" class="nav-link"><i class="fas fa-user-check"></i> <span>Log Visitor</span></a>
        <a href="chat.php" class="nav-link"><i class="fas fa-comments"></i> <span>Chat</span></a>
        <a href="user-payment.php" class="nav-link active"><i class="fas fa-money-bill-alt"></i> <span>Payment History</span></a>


        </div>
        
        <div class="logout">
        <a href="../config/user-logout.php" onclick="return confirmLogout();">
    <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
</a>

<script>
function confirmLogout() {
    return confirm("Are you sure you want to log out?");
}
</script>
        </div>
    </div>

    <!-- Top bar -->
    <div class="topbar">
        <h2>Payment History</h2>

    </div>
    <div class="main-content">      
    <div class="container mt-1">
    <!-- Search, Filter, and Sort Controls -->
<div class="row mb-4">
    <div class="col-12 col-md-6">
        <input type="text" id="searchInput" class="form-control custom-input-small" placeholder="Search for payment details..." onkeyup="filterTable()">
    </div>
    
    <div class="col-6 col-md-2">
        <select id="filterSelect" class="form-select" onchange="filterTable()">
            <option value="all" selected>Filter by</option>
            <option value="amount">Amount</option>
            <option value="status">Status</option>
        </select>
    </div>

    <div class="col-6 col-md-2">
        <select name="sort" class="form-select" id="sort" onchange="applySort()">
            <option value="" selected>Select Sort</option>

            <option value="amount_asc">Amount (Low to High)</option>
            <option value="amount_desc">Amount (High to Low)</option>
            <option value="payment_date_asc">Payment Date (Oldest to Newest)</option>
            <option value="payment_date_desc">Payment Date (Newest to Oldest)</option>
        </select>
    </div>
</div>

<!-- Replace the existing table with this new structure -->
<div class="accordion" id="paymentAccordion">
    <?php
    // Modified query to get payments grouped by month
    $query = "
        SELECT 
            rentpayment.payment_id,
            CONCAT(users.fname, ' ', users.lname) AS resident_name,
            rooms.room_number,
            rentpayment.amount,
            rentpayment.payment_date,
            rentpayment.status,
            rentpayment.payment_method,
            rentpayment.reference_number,
            DATE_FORMAT(rentpayment.payment_date, '%Y-%m') as payment_month
        FROM rentpayment
        INNER JOIN users ON rentpayment.user_id = users.id
        INNER JOIN roomassignments ON rentpayment.user_id = roomassignments.user_id
        INNER JOIN rooms ON roomassignments.room_id = rooms.room_id
        WHERE rentpayment.user_id = " . $userId . "
        ORDER BY rentpayment.payment_date DESC
    ";
    
    $result = mysqli_query($conn, $query);
    if (!$result) {
        die("Query failed: " . mysqli_error($conn));
    }

    $payments_by_month = [];
    
    // Group payments by month
    while ($row = mysqli_fetch_assoc($result)) {
        $month = date('F Y', strtotime($row['payment_date']));
        if (!isset($payments_by_month[$month])) {
            $payments_by_month[$month] = [];
        }
        $payments_by_month[$month][] = $row;
    }

    if (mysqli_num_rows($result) == 0) {
        // Show message when no payments exist
        echo '<div class="alert alert-info text-center" role="alert">
                <i class="fas fa-info-circle me-2"></i>
                No payment history available
              </div>';
    } else {
        foreach ($payments_by_month as $month => $payments) {
            $month_id = str_replace(' ', '', $month);
            ?>
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $month_id; ?>">
                        <i class="fas fa-calendar-alt me-2"></i>
                        <?php echo $month; ?>
                        <span class="badge bg-primary ms-2"><?php echo count($payments); ?> payments</span>
                    </button>
                </h2>
                <div id="<?php echo $month_id; ?>" class="accordion-collapse collapse">
                    <div class="accordion-body">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Resident Name</th>
                                    <th>Amount</th>
                                    <th>Payment Date</th>
                                    <th>Status</th>
                                    <th>Payment Method</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $no = 1;
                                foreach ($payments as $payment) {
                                    echo "<tr class='table-row'>
                                        <td>" . $no++ . "</td>
                                        <td>" . htmlspecialchars($payment['resident_name']) . "</td>
                                        <td class='amount'>₱" . number_format($payment['amount'], 2) . "</td>
                                        <td class='payment_date'>" . date('M d, Y', strtotime($payment['payment_date'])) . "</td>
                                        <td class='status'>" . htmlspecialchars($payment['status']) . "</td>
                                        <td class='payment_method'>" . htmlspecialchars($payment['payment_method']) . "</td>
                                        <td>
                                            <button class='btn btn-primary btn-sm' 
                                                onclick='viewReceipt(" . json_encode([
                                                    "resident_name" => $payment['resident_name'],
                                                    "room_number" => $payment['room_number'],
                                                    "amount" => $payment['amount'],
                                                    "payment_date" => date('M d, Y', strtotime($payment['payment_date'])),
                                                    "status" => $payment['status'],
                                                    "payment_method" => $payment['payment_method'],
                                                    "reference_number" => $payment['reference_number']
                                                ]) . ")'>
                                                <i class='fas fa-eye me-1'></i> View Receipt
                                            </button>
                                        </td>
                                    </tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php
        }
    }
    ?>
</div>

<!-- Updated Modal for Receipt -->
<div class="modal fade" id="receiptModal" tabindex="-1" aria-labelledby="receiptModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="receiptModalLabel">
                    <i class="fas fa-receipt me-2"></i>Payment Receipt
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="receiptContent">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" onclick="printReceipt()">
                    <i class="fas fa-print me-2"></i>Print
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Updated CSS -->
<style>
/* Updated Container Styling */
.container {
    max-width: 1200px;
    padding: 20px;
    background-color: #f8f9fa;
    border-radius: 12px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* Search and Filter Controls */
.custom-input-small {
    height: 38px;
    border-radius: 6px;
    border: 1px solid #ced4da;
    padding: 8px 12px;
    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
}

.custom-input-small:focus {
    border-color: #80bdff;
    box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
}

.form-select {
    height: 38px;
    border-radius: 6px;
    cursor: pointer;
}

/* Accordion Styling */
.accordion-item {
    margin-bottom: 10px;
    border: none;
    border-radius: 8px;
    overflow: hidden;
}

.accordion-button {
    background-color: #ffffff;
    border: 1px solid #e9ecef;
    padding: 15px 20px;
    font-weight: 500;
    color: #495057;
}

.accordion-button:not(.collapsed) {
    background-color: #e7f1ff;
    color: #0d6efd;
    border-bottom: none;
}

.accordion-body {
    background-color: #ffffff;
    border: 1px solid #e9ecef;
    border-top: none;
    border-bottom-left-radius: 8px;
    border-bottom-right-radius: 8px;
    padding: 20px;
}

/* Table Styling */
.table {
    margin-bottom: 0;
}

.table thead th {
    background-color: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    color: #495057;
    font-weight: 600;
    padding: 12px;
}

.table tbody td {
    padding: 12px;
    vertical-align: middle;
}

.table-row:hover {
    background-color: #f8f9fa;
    transition: background-color 0.2s ease;
}

/* Badge Styling */
.badge {
    padding: 6px 10px;
    font-weight: 500;
    font-size: 0.85rem;
}

/* Button Styling */
.btn-primary {
    background-color: #0d6efd;
    border: none;
    padding: 8px 16px;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    background-color: #0b5ed7;
    transform: translateY(-1px);
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

/* Receipt Modal Styling */
.modal-content {
    border: none;
    border-radius: 12px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.2);
}

.modal-header {
    border-top-left-radius: 12px;
    border-top-right-radius: 12px;
    padding: 20px;
}

.modal-body {
    padding: 25px;
}

.receipt-content {
    background-color: #ffffff;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.receipt-details p {
    padding: 12px 0;
    margin: 0;
    border-bottom: 1px dashed #dee2e6;
    display: flex;
    justify-content: space-between;
}

.receipt-details p:last-child {
    border-bottom: none;
}

/* Responsive Design */
@media (max-width: 768px) {
    .container {
        padding: 10px;
    }
    
    .table-responsive {
        border: none;
    }
    
    .btn-primary.btn-sm {
        padding: 4px 8px;
        font-size: 0.8rem;
    }
    
    .accordion-button {
        padding: 12px 15px;
    }
}

/* Print Styles */
@media print {
    .receipt-content {
        box-shadow: none;
        border: 1px solid #dee2e6;
    }
    
    .receipt-details p {
        border-bottom-style: solid;
    }
}
</style>

<!-- Updated JavaScript for printing -->
<script>
function printReceipt() {
    const printContent = document.getElementById('receiptContent').innerHTML;
    const originalContent = document.body.innerHTML;
    
    document.body.innerHTML = `
        <div style="max-width: 800px; margin: 20px auto; padding: 20px;">
            ${printContent}
        </div>
    `;
    
    window.print();
    document.body.innerHTML = originalContent;
    
    // Reinitialize components after printing
    initializeComponents();
    
    // Show the modal again
    const modalElement = document.getElementById('receiptModal');
    const modal = new bootstrap.Modal(modalElement);
    modal.show();
}
</script>

<!-- JavaScript Libraries -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>


<!-- Include jQuery and Bootstrap JS (required for dropdown) -->
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>

    
    <!-- JavaScript -->
    <script>
           function filterTable() {
        let searchInput = document.getElementById('searchInput').value.toLowerCase();
        let filterSelect = document.getElementById('filterSelect').value;
        let accordionItems = document.querySelectorAll('.accordion-item');

        accordionItems.forEach(item => {
            let rows = item.querySelectorAll('tbody tr');
            let visibleRows = 0;

            rows.forEach(row => {
                let columns = row.querySelectorAll('td');
                let matchesSearch = false;
                let matchesFilter = true;

                columns.forEach(column => {
                    let text = column.textContent.toLowerCase();
                    if (text.includes(searchInput)) {
                        matchesSearch = true;
                    }
                });

                // Additional filter logic
                if (filterSelect !== 'all') {
                    let filterColumn = row.querySelector('.' + filterSelect);
                    if (filterColumn) {
                        let filterText = filterColumn.textContent.toLowerCase();
                        matchesFilter = filterText.includes(searchInput);
                    }
                }

                // Show or hide row
                if (matchesSearch && matchesFilter) {
                    row.style.display = '';
                    visibleRows++;
                } else {
                    row.style.display = 'none';
                }
            });

            // Show/hide accordion section based on whether it has visible rows
            if (visibleRows > 0) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
            }
        });
    }

    function applySort() {
        let sortValue = document.getElementById('sort').value;
        let accordionItems = document.querySelectorAll('.accordion-item');

        accordionItems.forEach(item => {
            let tbody = item.querySelector('tbody');
            let rows = Array.from(tbody.querySelectorAll('tr'));

            rows.sort((a, b) => {
                if (sortValue.includes('amount')) {
                    let amountA = parseFloat(a.querySelector('.amount').textContent.replace(/[^0-9.-]+/g, ''));
                    let amountB = parseFloat(b.querySelector('.amount').textContent.replace(/[^0-9.-]+/g, ''));
                    return sortValue === 'amount_asc' ? amountA - amountB : amountB - amountA;
                } else if (sortValue.includes('payment_date')) {
                    let dateA = new Date(a.querySelector('.payment_date').textContent);
                    let dateB = new Date(b.querySelector('.payment_date').textContent);
                    return sortValue === 'payment_date_asc' ? dateA - dateB : dateB - dateA;
                }
                return 0;
            });

            // Clear and re-append sorted rows
            tbody.innerHTML = '';
            rows.forEach(row => tbody.appendChild(row));
        });
    }
// Toggle Reference Number Field based on Payment Method
function toggleReferenceNumber() {
    const paymentMethod = document.getElementById('payment_method').value;
    const referenceDiv = document.getElementById('reference_number_div');
    const referenceField = document.getElementById('reference_number');

    if (paymentMethod === 'online banking') {
        referenceDiv.style.display = 'block';  // Show the reference number field
        referenceField.required = true;       // Make it required
    } else {
        referenceDiv.style.display = 'none';  // Hide the reference number field
        referenceField.value = '';            // Clear the field
        referenceField.required = false;      // Remove the required attribute
    }
}

        // Sidebar toggle
        const hamburgerMenu = document.getElementById('hamburgerMenu');
        const sidebar = document.getElementById('sidebar');

        sidebar.classList.add('collapsed');
        hamburgerMenu.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            const icon = hamburgerMenu.querySelector('i');
            if (sidebar.classList.contains('collapsed')) {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            } else {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times');
            }
        });
    </script>

<!-- Add this JavaScript function before the closing </body> tag -->
<script>
function viewReceipt(payment) {
    const receiptHTML = `
        <div class='receipt-content'>
            <h4>Payment Receipt</h4>
            <div class='receipt-details'>
                <p><strong>Resident:</strong> ${payment.resident_name}</p>
                <p><strong>Room Number:</strong> ${payment.room_number}</p>
                <p><strong>Amount:</strong> ₱${parseFloat(payment.amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</p>
                <p><strong>Date:</strong> ${payment.payment_date}</p>
                <p><strong>Status:</strong> ${payment.status}</p>
                <p><strong>Payment Method:</strong> ${payment.payment_method}</p>
                ${payment.reference_number ? `<p><strong>Reference Number:</strong> ${payment.reference_number}</p>` : ''}
            </div>
        </div>
    `;
    
    document.getElementById('receiptContent').innerHTML = receiptHTML;
    
    // Initialize and show modal using Bootstrap 5
    const modalElement = document.getElementById('receiptModal');
    if (!modalElement.classList.contains('show')) {
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
    }
}

// Make sure Bootstrap is properly initialized
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<!-- Place these just before closing </body> tag -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
