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

// Define tables and their display names
$tables = [
    'announce' => 'Announcements',
    'rentpayment' => 'Rent Payments',
    'roomfeedback' => 'Room Feedback',
    'rooms' => 'Rooms',
    'visitors' => 'Visitors'
];
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archives</title>
    <link rel="icon" href="../img-icon/logo.png" type="image/png">

    <link rel="stylesheet" href="../Admin/Css_Admin/style.css"> 
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="menu" id="hamburgerMenu">
            <i class="fas fa-bars"></i> <!-- Hamburger menu icon -->
        </div>

        <div class="sidebar-nav">
        <a href="dashboard.php" class="nav-link"><i class="fas fa-home"></i> <span>Home</span></a>
            <a href="manageuser.php" class="nav-link"><i class="fas fa-users"></i> <span>Manage User</span></a>
            <a href="admin-room.php" class="nav-link" > <i class="fas fa-building"></i> <span>Room Management</span></a>
            <a href="admin-visitor_log.php" class="nav-link"><i class="fas fa-address-book"></i> <span>Log Visitor</span></a>

            <a href="admin-monitoring.php" class="nav-link"><i class="fas fa-eye"></i> <span>Presence Monitoring</span></a>
            <a href="admin-chat.php" class="nav-link"><i class="fas fa-comments"></i> <span>Group Chat</span></a>
            <a href="rent_payment.php" class="nav-link"><i class="fas fa-money-bill-alt"></i> <span>Rent Payment</span></a>
            <a href="activity-logs.php" class="nav-link active"><i class="fas fa-clipboard-list"></i> <span>Activity Logs</span></a>


        </div>
        
        <div class="logout">
        <a href="../config/logout.php" id="logoutLink">
            <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
        </a>
        </div>
        <script>
    document.getElementById('logoutLink').addEventListener('click', function(event) {
        event.preventDefault(); // Prevent the default link behavior
        const logoutUrl = this.href; // Store the logout URL

        Swal.fire({
            title: 'Are you sure?',
            text: "You want to log out?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, log me out!'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Logging out...',
                    text: 'Please wait while we log you out.',
                    allowOutsideClick: false,
                    onBeforeOpen: () => {
                        Swal.showLoading(); // Show loading indicator
                    },
                    timer: 2000, // Auto-close after 2 seconds
                    timerProgressBar: true, // Show progress bar
                    willClose: () => {
                        window.location.href = logoutUrl; // Redirect to logout URL
                    }
                });
            }
        });
    });
    </script>
    </div>

    <!-- Top bar -->
    <div class="topbar">
        <h2>Archives</h2>

    </div>
    <div class="main-content">      
    <div class="container mt-1">
    <!-- Search, Filter, and Sort Controls -->
<div class="row mb-4">
<div class="d-flex justify-content-start">
    <a href="activity-logs.php" class="btn " onclick="window.location.reload();">
    <i class="fas fa-arrow-left fa-2x me-1"></i></a>

</div>
    <div class="col-12 col-md-6">
        <form method="GET" action="" class="search-form">
            <div class="input-group">
                <input type="text" id="searchInput" name="search" class="form-control custom-input-small" 
                    placeholder="Search archived announcement, rooms, etc...."
                    value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                <span class="input-group-text">
                    <i class="fas fa-search"></i>
                </span>
            </div>
        </form>
    </div>
    
 <div style="display: none;">
    <div class="col-6 col-md-2 mt-2">
        <select id="filterSelect" class="form-select" onchange="filterTable()">
            <option value="all" selected>Filter by</option>
            <option value="amount">Amount</option>
            <option value="status">Status</option>
        </select>
    </div>

    <div class="col-6 col-md-2 mt-2">
        <select name="sort" class="form-select" id="sort" onchange="applySort()">
            <option value="" selected>Select Sort</option>
            <option value="amount_asc">Amount (Low to High)</option>
            <option value="amount_desc">Amount (High to Low)</option>
            <option value="payment_date_asc">Payment Date (Oldest to Newest)</option>
            <option value="payment_date_desc">Payment Date (Newest to Oldest)</option>
        </select>
    </div>
</div>
</div>

<!-- Replace the existing table with this new structure -->
<div class="accordion" id="archiveAccordion">
    <?php
    foreach ($tables as $table => $displayName) {
        // Query to fetch archived entries
        $sql = "SELECT * FROM $table WHERE archive_status = 'archived'";
        $result = mysqli_query($conn, $sql);

        if (mysqli_num_rows($result) > 0) {
            echo "<div class='accordion-item'>";
            echo "<h2 class='accordion-header' id='heading$table'>";
            echo "<button class='accordion-button collapsed' type='button' data-bs-toggle='collapse' data-bs-target='#collapse$table' aria-expanded='false' aria-controls='collapse$table'>";
            echo "$displayName";
            echo "</button>";
            echo "</h2>";
            echo "<div id='collapse$table' class='accordion-collapse collapse' aria-labelledby='heading$table' data-bs-parent='#archiveAccordion'>";
            echo "<div class='accordion-body'>";
            echo "<div class='table-responsive'>";
            echo "<table class='table table-bordered'>";
            echo "<thead><tr>";

            // Fetch and display column names
            $fields = mysqli_fetch_fields($result);
            foreach ($fields as $field) {
                echo "<th>" . htmlspecialchars($field->name) . "</th>";
            }
            echo "<th>Actions</th>"; // Add a column for actions
            echo "</tr></thead><tbody>";

            // Fetch and display rows
            while ($row = mysqli_fetch_assoc($result)) {
                echo "<tr>";
                foreach ($row as $key => $value) {
                    echo "<td>" . htmlspecialchars($value) . "</td>";
                }
                // Debugging: Print available keys
                echo "<!-- Available keys: " . implode(', ', array_keys($row)) . " -->";

                // Determine the primary key column based on the table
                $primaryKeyColumn = '';
                switch ($table) {
                    case 'announce':
                        $primaryKeyColumn = 'announcementId';
                        break;
                    case 'rentpayment':
                        $primaryKeyColumn = 'payment_id';
                        break;
                    case 'roomfeedback':
                        $primaryKeyColumn = 'id';
                        break;
                    case 'rooms':
                        $primaryKeyColumn = 'room_id';
                        break;
                    case 'visitors':
                        $primaryKeyColumn = 'id';
                        break;
                    default:
                        echo "<script>console.error('Unknown table: $table');</script>";
                        continue 2; // Skip this iteration if the table is unknown
                }

                // Add action buttons
                echo "<td>
                        <form method='POST' style='display:inline;'>
                            <input type='hidden' name='entry_id' value='{$row[$primaryKeyColumn]}'>
                            <input type='hidden' name='table' value='$table'>
                            <button type='submit' name='rearchive' class='btn btn-warning btn-sm'>Restore</button>
                            <button type='submit' name='delete' class='btn btn-danger btn-sm'>Delete</button>
                        </form>
                      </td>";
                echo "</tr>";
            }
            echo "</tbody></table>";
            echo "</div>";
            echo "</div></div></div>";
        }
    }
    ?>
</div>

<?php
// Handle form submissions for re-archiving and deleting
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $entryId = $_POST['entry_id'] ?? null;
    $table = $_POST['table'] ?? null;

    if (!$entryId || !$table) {
        echo "<script>console.error('Missing entry_id or table.');</script>";
    } else {
        // Determine the primary key column based on the table
        $primaryKeyColumn = '';
        switch ($table) {
            case 'announce':
                $primaryKeyColumn = 'announcementId';
                break;
            case 'rentpayment':
                $primaryKeyColumn = 'payment_id';
                break;
            case 'roomfeedback':
                $primaryKeyColumn = 'id';
                break;
            case 'rooms':
                $primaryKeyColumn = 'room_id';
                break;
            case 'visitors':
                $primaryKeyColumn = 'id';
                break;
            default:
                echo "<script>console.error('Unknown table: $table');</script>";
                exit;
        }

        if (isset($_POST['rearchive'])) {
            // Re-archive logic
            $updateSql = "UPDATE $table SET archive_status = 'active' WHERE $primaryKeyColumn = $entryId";
            if (mysqli_query($conn, $updateSql)) {
                echo "<script>alert('Entry re-archived successfully.'); window.location.href = 'archives.php';</script>";
            } else {
                echo "<script>console.error('Error re-archiving entry: " . mysqli_error($conn) . "');</script>";
            }
        }

        if (isset($_POST['delete'])) {
            // Delete logic
            $deleteSql = "DELETE FROM $table WHERE $primaryKeyColumn = $entryId";
            if (mysqli_query($conn, $deleteSql)) {
                echo "<script>alert('Entry deleted successfully.'); window.location.href = 'archives.php';</script>";
            } else {
                echo "<script>console.error('Error deleting entry: " . mysqli_error($conn) . "');</script>";
            }
        }
    }
}
?>

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
            
        
            /* Controls section styling */
          
            /* Search input styling */
            .input-group .form-control {
                border-radius: 8px;
                padding: 0.75rem 2.25rem 0.75rem 1rem;
                border: 2px solid #e0e0e0;
                transition: all 0.3s ease;
            }

            .input-group .form-control:focus {
                border-color: #2B228A;
                box-shadow: 0 0 0 0.2rem rgba(43, 34, 138, 0.1);
            }

            /* Filter and Sort controls */
            .form-select {
                border-radius: 8px;
                border: 2px solid #e0e0e0;
                background-color: white;
                cursor: pointer;
                min-width: 160px;
            }

            .form-select:focus {
                border-color: #2B228A;
                box-shadow: 0 0 0 0.2rem rgba(43, 34, 138, 0.1);
            }

            /* Labels */
            .form-label {
                color: #495057;
                font-weight: 500;
                margin-bottom: 0;
            }

            /* Log Visitor button */
            .btn-primary {
                background-color: #2B228A;
                border: none;
                padding: 0.75rem 1.5rem;
                border-radius: 8px;
                font-weight: 500;
                transition: all 0.3s ease;
            }

            .btn-primary:hover {
                background-color: #201b68;
                transform: translateY(-1px);
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            }

            /* Table styling */
            .table-responsive {
                border-radius: 10px;
                overflow: hidden;
                box-shadow: 0 0 15px rgba(0,0,0,0.05);
            }

            .table {
                margin-bottom: 0;
            }

            .table thead th {
                background-color: #2B228A;
                color: white;
                font-weight: 500;
                padding: 1rem;
                border: none;
                white-space: nowrap;
            }

            .table tbody tr {
                transition: all 0.3s ease;
            }

            .table tbody tr:hover {
                background-color: #f8f9fa;
            }

            .table td {
                padding: 1rem;
                vertical-align: middle;
                border-color: #e9ecef;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            /* Action buttons */
            .btn-sm {
                padding: 0.4rem 0.8rem;
                font-size: 0.875rem;
                border-radius: 6px;
                margin: 0 0.2rem;
                white-space: nowrap;
            }

            .btn-secondary {
                background-color: #6c757d;
                border: none;
            }

            .btn-danger {
                background-color: #dc3545;
                border: none;
            }

            /* Responsive adjustments */
            @media (max-width: 768px) {
                .container {
                    padding: 1rem;
                }
                
                .controls-wrapper {
                    flex-direction: column;
                    gap: 1rem;
                }
                
                .input-group {
                    width: 100% !important;
                }
                
                .d-flex {
                    flex-direction: column;
                    gap: 1rem;
                }
                
                .form-select {
                    width: 100%;
                }
            }
            .btn-close {
    background: none;
    border: none;
    padding: 0.5rem;
    cursor: pointer;
    opacity: 0.75;
    transition: opacity 0.15s;
}

.btn-close:hover {
    opacity: 1;
}

.btn-close span {
    font-size: 1.5rem;
    color: #fff;
    line-height: 1;
}

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
    overflow-x: auto;
    overflow-y: auto;
    max-height: 400px;
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

/* Ensure table cells do not overflow */
.table-responsive {
    overflow-x: auto; /* Enable horizontal scrolling */
}

/* Action buttons */
.btn-sm {
    padding: 0.4rem 0.8rem;
    font-size: 0.875rem;
    border-radius: 6px;
    margin: 0 0.2rem; /* Add margin between buttons */
    white-space: nowrap; /* Prevent text from wrapping */
}

.table td {
    padding: 1rem;
    vertical-align: middle;
    border-color: #e9ecef;
    overflow: hidden; /* Hide overflow */
    text-overflow: ellipsis; /* Add ellipsis for overflowed text */
    white-space: nowrap; /* Prevent text from wrapping */
}

/* Custom scrollbar styling */
.table-responsive::-webkit-scrollbar {
    width: 8px; /* Width of the scrollbar */
    height: 8px; /* Height of the scrollbar */
}

.table-responsive::-webkit-scrollbar-track {
    background: #f1f1f1; /* Background of the scrollbar track */
    border-radius: 10px;
}

.table-responsive::-webkit-scrollbar-thumb {
    background: #888; /* Color of the scrollbar thumb */
    border-radius: 10px;
}

.table-responsive::-webkit-scrollbar-thumb:hover {
    background: #555; /* Color of the scrollbar thumb on hover */
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
            let headerText = item.querySelector('.accordion-button').textContent.toLowerCase();
            let rows = item.querySelectorAll('tbody tr');
            let visibleRows = 0;
            let matchesSearch = headerText.includes(searchInput);

            rows.forEach(row => {
                let columns = row.querySelectorAll('td');
                let rowMatchesSearch = matchesSearch;
                let matchesFilter = true;

                columns.forEach(column => {
                    let text = column.textContent.toLowerCase();
                    if (text.includes(searchInput)) {
                        rowMatchesSearch = true;
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
                if (rowMatchesSearch && matchesFilter) {
                    row.style.display = '';
                    visibleRows++;
                } else {
                    row.style.display = 'none';
                }
            });

            // Show/hide accordion section based on whether it has visible rows or matches search
            if (visibleRows > 0 || matchesSearch) {
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

<script>
function filterTable() {
    let searchInput = document.getElementById('searchInput').value.toLowerCase();
    let filterSelect = document.getElementById('filterSelect').value;
    let accordionItems = document.querySelectorAll('.accordion-item');

    accordionItems.forEach(item => {
        let headerText = item.querySelector('.accordion-button').textContent.toLowerCase();
        let rows = item.querySelectorAll('tbody tr');
        let visibleRows = 0;
        let matchesSearch = headerText.includes(searchInput);

        rows.forEach(row => {
            let columns = row.querySelectorAll('td');
            let rowMatchesSearch = matchesSearch;
            let matchesFilter = true;

            columns.forEach(column => {
                let text = column.textContent.toLowerCase();
                if (text.includes(searchInput)) {
                    rowMatchesSearch = true;
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
            if (rowMatchesSearch && matchesFilter) {
                row.style.display = '';
                visibleRows++;
            } else {
                row.style.display = 'none';
            }
        });

        // Show/hide accordion section based on whether it has visible rows or matches search
        if (visibleRows > 0 || matchesSearch) {
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

// Ensure the filterTable function is called when the search input changes
document.getElementById('searchInput').addEventListener('input', filterTable);

// Add event listeners to accordion buttons
document.querySelectorAll('.accordion-button').forEach(button => {
    button.addEventListener('click', function() {
        // Toggle the accordion section manually
        let collapseElement = this.nextElementSibling;
        if (collapseElement.classList.contains('show')) {
            new bootstrap.Collapse(collapseElement, { toggle: false });
        } else {
            new bootstrap.Collapse(collapseElement, { toggle: true });
        }
    });
});
</script>
</body>
</html>
