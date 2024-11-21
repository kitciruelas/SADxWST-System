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

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="Css_user/users-dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Bootstrap CSS -->
<link href="https://stackpath.bootstrapcdn.com/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">

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
            <a href="../config/user-logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
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

<!-- Table -->
<table class="table table-bordered" id="paymentTable">
    <thead class="table-light">
        <tr>
            <th>No</th>
            <th>Resident Name</th>
            <th>Amount</th>
            <th>Payment Date</th>
            <th>Status</th>
            <th>Payment Method</th>
            <th>Reference Number (if online)</th>
        </tr>
    </thead>
    <tbody>
        <?php
        // Assuming the session is already started and the user ID is stored in the session
        $user_id = $_SESSION['id'];

        // SQL query to fetch rent payment data for the logged-in user
        $query = "
        SELECT 
            rentpayment.payment_id,
            CONCAT(users.fname, ' ', users.lname) AS resident_name,
            rooms.room_number,
            rentpayment.amount,
            rentpayment.payment_date,
            rentpayment.status,
            rentpayment.payment_method,
            rentpayment.reference_number
        FROM rentpayment
        INNER JOIN users ON rentpayment.user_id = users.id
        INNER JOIN roomassignments ON rentpayment.user_id = roomassignments.user_id
        INNER JOIN rooms ON roomassignments.room_id = rooms.room_id
        WHERE rentpayment.user_id = $user_id
        ORDER BY rentpayment.payment_date DESC
    ";
        // Execute the query
        $result = mysqli_query($conn, $query);

        // Initialize row number counter
        $no = 1;

        // Loop through the results and populate the table
        if (mysqli_num_rows($result) > 0) {
            while ($row = mysqli_fetch_assoc($result)) {
                echo "<tr class='table-row'>
                        <td>" . $no++ . "</td>
                        <td>" . $row['resident_name'] . "</td>
                        <td class='amount'>" . number_format($row['amount'], 2) . "</td>
                        <td class='payment_date'>" . $row['payment_date'] . "</td>
                        <td class='status'>" . $row['status'] . "</td>
                        <td class='payment_method'>" . $row['payment_method'] . "</td>";
                if ($row['payment_method'] == 'online banking') {
                    echo "<td>" . htmlspecialchars($row['reference_number']) . "</td>";
                } else {
                    echo "<td>-</td>";
                }
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='8'>You have no payments recorded yet.</td></tr>";
        }
        ?>
    </tbody>
</table>







<!-- Pagination Controls -->
<div id="pagination">
    <button id="prevPage" onclick="prevPage()" disabled>Previous</button>
    <span id="pageIndicator">Page 1</span>
    <button id="nextPage" onclick="nextPage()">Next</button>
</div>
<style>
        
    /* Style for the entire table */
    .table {
        background-color: #f8f9fa; /* Light background for the table */
        border-collapse: collapse; /* Ensures borders don't double up */
    }

    /* Style for table headers */
    .table th {
        background-color: #2B228A; /* Dark background */
        color: #ffffff; /* White text */
        font-weight: bold;
        text-align: center;
        padding: 12px;
        border-bottom: 2px solid #dee2e6; /* Bottom border only */
    }

    /* Style for table rows */
    .table td {
        padding: 10px;
        vertical-align: middle; /* Center content vertically */
        border-bottom: 1px solid #dee2e6; /* Border only at the bottom of each row */
    }

    /* Optional hover effect for rows */
    .table tbody tr:hover {
        background-color: #e9ecef; /* Slightly darker background on hover */
    }

    /* Styling the action buttons */
    .table .btn {
        margin-right: 5px; /* Space between buttons */
    }

</style>
        </div>
    </div>



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
        let rows = document.querySelectorAll('#paymentTable tbody tr');

        rows.forEach(row => {
            let columns = row.querySelectorAll('td');
            let matchesSearch = false;

            // Search logic
            for (let i = 0; i < columns.length; i++) {
                let columnText = columns[i].textContent.toLowerCase();
                if (columnText.indexOf(searchInput) > -1) {
                    matchesSearch = true;
                }
            }

            // Filter logic
            let matchesFilter = true;
            if (filterSelect !== 'all') {
                let filterColumn = row.querySelector('.' + filterSelect);
                if (filterColumn) {
                    let filterText = filterColumn.textContent.toLowerCase();
                    if (!filterText.includes(searchInput)) {
                        matchesFilter = false;
                    }
                }
            }

            // Show or hide row based on search and filter
            if (matchesSearch && matchesFilter) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    function applySort() {
        let sortValue = document.getElementById('sort').value;
        let rows = Array.from(document.querySelectorAll('#paymentTable tbody tr'));
        let sortedRows = [];

        // Sort based on selected value
        if (sortValue.includes('room_number')) {
            sortedRows = rows.sort((a, b) => {
                let roomNumberA = a.querySelector('.room_number').textContent;
                let roomNumberB = b.querySelector('.room_number').textContent;
                return sortValue === 'room_number_asc' ? roomNumberA - roomNumberB : roomNumberB - roomNumberA;
            });
        } else if (sortValue.includes('amount')) {
            sortedRows = rows.sort((a, b) => {
                let amountA = parseFloat(a.querySelector('.amount').textContent.replace(/[^0-9.-]+/g, ''));
                let amountB = parseFloat(b.querySelector('.amount').textContent.replace(/[^0-9.-]+/g, ''));
                return sortValue === 'amount_asc' ? amountA - amountB : amountB - amountA;
            });
        } else if (sortValue.includes('payment_date')) {
            sortedRows = rows.sort((a, b) => {
                let dateA = new Date(a.querySelector('.payment_date').textContent);
                let dateB = new Date(b.querySelector('.payment_date').textContent);
                return sortValue === 'payment_date_asc' ? dateA - dateB : dateB - dateA;
            });
        }

        // Append sorted rows back to the table body
        let tbody = document.querySelector('#paymentTable tbody');
        tbody.innerHTML = '';
        sortedRows.forEach(row => tbody.appendChild(row));
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
</body>
</html>
