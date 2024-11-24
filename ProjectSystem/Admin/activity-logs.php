<?php
session_start();

// Redirect to login if not logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: admin-login.php");
    exit;
}

include '../config/config.php'; // Ensure this is correct

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}



// SQL query to fetch users from the users table
$sql = "SELECT id, fname, lname FROM users";
$result = mysqli_query($conn, $sql);

// Store the options in an array
$options = [];

if (mysqli_num_rows($result) > 0) {
    // Loop through the results and generate options for the dropdown
    while ($row = mysqli_fetch_assoc($result)) {
        // Safely output the user's first and last name using htmlspecialchars
        $fname = htmlspecialchars($row['fname']);
        $lname = htmlspecialchars($row['lname']);
        $options[] = "<option value='" . $row['id'] . "'>" . $fname . " " . $lname . "</option>";
    }
} else {
    // If no users found, add a "No users" option
    $options[] = "<option value=''>No users found</option>";
}

// Close the database connection

// Combine all options into a single string
$options_string = implode("\n", $options);

if (isset($_POST['user_id'])) {
    $user_id = $_POST['user_id'];

    // Query to get room number for the selected user
    $sql = "SELECT room_id FROM roomassignments WHERE user_id = '$user_id'";
    $result = mysqli_query($conn, $sql);

    if ($row = mysqli_fetch_assoc($result)) {
        $room_number = $row['room_id'];
    } else {
        $room_number = ''; // If no room is assigned
    }
}


// Check if the form is submitted (for both create and update)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Check if it's a payment creation (no payment_id in the form)
    if (!isset($_POST['payment_id'])) {
        // Capture form data for creating a payment
        $user_id = mysqli_real_escape_string($conn, $_POST['user_id']);
        $amount = mysqli_real_escape_string($conn, $_POST['amount']);
        $payment_date = mysqli_real_escape_string($conn, $_POST['payment_date']);
        $status = mysqli_real_escape_string($conn, $_POST['status']);
        $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
        $reference_number = isset($_POST['reference_number']) ? mysqli_real_escape_string($conn, $_POST['reference_number']) : NULL;

        // Prepare SQL query to insert payment data
        $query = "INSERT INTO rentpayment (user_id, amount, payment_date, status, payment_method, reference_number) 
                  VALUES ('$user_id', '$amount', '$payment_date', '$status', '$payment_method', '$reference_number')";

        // Execute the query
        if (mysqli_query($conn, $query)) {
            header('Location: rent_payment.php?message=Payment created successfully');
        } else {
            header('Location: rent_payment.php?error=Failed to create payment');
        }
    }

    // Check if it's a payment update (payment_id is present)
    elseif (isset($_POST['payment_id'])) {
        // Update Payment
        $payment_id = mysqli_real_escape_string($conn, $_POST['payment_id']);
        $amount = mysqli_real_escape_string($conn, $_POST['amount']);
        $status = mysqli_real_escape_string($conn, $_POST['status']);
        $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
        $reference_number = !empty($_POST['reference_number']) ? mysqli_real_escape_string($conn, $_POST['reference_number']) : NULL;

        // Prepare SQL query to update the payment
        $sql = "UPDATE rentpayment 
                SET amount = '$amount', 
                    status = '$status', 
                    payment_method = '$payment_method', 
                    reference_number = " . ($reference_number ? "'$reference_number'" : "NULL") . " 
                WHERE payment_id = '$payment_id'";

        // Execute the query
        if (mysqli_query($conn, $sql)) {
            header('Location: rent_payment.php?message=Payment updated successfully');
        } else {
            header('Location: rent_payment.php?error=Failed to update payment');
        }
    }
}
// Check if the delete request is set
if (isset($_GET['delete_payment_id'])) {
    $payment_id = mysqli_real_escape_string($conn, $_GET['delete_payment_id']);

    // Prepare the SQL query to delete the payment
    $query = "DELETE FROM rentpayment WHERE payment_id = '$payment_id'";

    // Execute the query
    if (mysqli_query($conn, $query)) {
        // Redirect to the same page with a success message
        header('Location: rent_payment.php?message=Payment deleted successfully');
    } else {
        // Redirect with an error message if deletion fails
        header('Location: rent_payment.php?error=Failed to delete payment');
    }

    exit();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs</title>
    <link rel="icon" href="img-icon/clip.png" type="image/png">

    <link rel="stylesheet" href="Css_Admin/admin_manageuser.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet"><!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.1/css/jquery.dataTables.min.css">

<!-- Bootstrap CSS -->
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<!-- jQuery (full version) -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>

<!-- DataTables CSS (recommended for styling) -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.10.21/css/jquery.dataTables.min.css">

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.10.21/js/jquery.dataTables.min.js"></script>

<!-- DataTables Buttons CSS (for buttons like export) -->
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.3/css/buttons.dataTables.min.css">

<!-- DataTables Buttons JS (for exporting and other button features) -->
<script src="https://cdn.datatables.net/buttons/2.2.3/js/dataTables.buttons.min.js"></script>

<!-- JSZip for Excel Export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>

<!-- pdfMake for PDF Export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>

<!-- DataTables Buttons for exporting -->
<script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.html5.min.js"></script>

<!-- DataTables Print Buttons JS -->
<script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.print.min.js"></script>

<!-- Bootstrap JS (optional, if you're using Bootstrap components) -->
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>



</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="menu" id="hamburgerMenu">
            <i class="fas fa-bars"></i>
        </div>
        <div class="sidebar-nav">
            <a href="dashboard.php" class="nav-link"><i class="fas fa-home"></i> <span>Home</span></a>
            <a href="manageuser.php" class="nav-link"><i class="fas fa-users"></i> <span>Manage User</span></a>
            <a href="admin-room.php" class="nav-link" id="roomManagerDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="fas fa-building"></i> <span>Room Manager</span>
            <a href="admin-visitor_log.php" class="nav-link"><i class="fas fa-address-book"></i> <span>Log Visitor</span></a>
            <a href="admin-monitoring.php" class="nav-link"><i class="fas fa-eye"></i> <span>Monitoring</span></a>

            <a href="admin-chat.php" class="nav-link"><i class="fas fa-comments"></i> <span>Group Chat</span></a>
            <a href="rent_payment.php" class="nav-link"><i class="fas fa-money-bill-alt"></i> <span>Rent Payment</span></a>
            <a href="activity-logs.php" class="nav-link active"><i class="fas fa-clipboard-list"></i> <span>Activity Logs</span></a>

        </div>
        <div class="logout">
        <a href="../config/logout.php" onclick="return confirmLogout();">
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
        <h2>Activity Logs</h2>
    </div>

    <!-- Main content -->
    <div class="main-content">
    <div class="container">

<div class="d-flex justify-content-start">
       
    <div class="container mt-1">
    <div class="row mb-4">
    <div class="col-12 col-md-6">
        <input type="text" id="searchInput" class="form-control custom-input-small" placeholder="Search for activity logs..." onkeyup="filterTable()">
    </div>
    
    <div class="col-6 col-md-2">
        <select id="filterSelect" class="form-select" onchange="filterTable()">
            <option value="all" selected>Filter by</option>
            <option value="fname">Name</option>
            <option value="activity_type">Activity Type</option>
            <option value="activity_details">Activity Details</option>
        </select>
    </div>

    <div class="col-6 col-md-2">
        <select name="sort" class="form-select" id="sort" onchange="applySort()">
            <option value="" selected>Sort by</option>
            <option value="timestamp_asc">Date (Oldest to Newest)</option>
            <option value="timestamp_desc">Date (Newest to Oldest)</option>
            <option value="name_asc">Name (A to Z)</option>
            <option value="name_desc">Name (Z to A)</option>
            <option value="activity_type_asc">Activity Type (A to Z)</option>
            <option value="activity_type_desc">Activity Type (Z to A)</option>
        </select>
    </div>
</div>

<!-- Activity Logs Table -->
<table class="table table-bordered" id="activityLogTable">
    <thead class="table-light">
        <tr>
            <th>No</th>
            <th>Name</th>
            <th>Activity Type</th>
            <th>Activity Details</th>
            <th>Activity Timestamp</th>
        </tr>
    </thead>
    <tbody id="assignpage">
        <?php
        $sql = "SELECT al.log_id, 
                    IFNULL(s.fname, u.fname) AS fname,
                    IFNULL(s.lname, u.lname) AS lname,
                    al.activity_type, 
                    al.activity_details, 
                    al.activity_timestamp,
                    IF(s.id IS NOT NULL, 'Staff', 'User') AS role
            FROM activity_logs al
            LEFT JOIN staff s ON al.user_id = s.id
            LEFT JOIN users u ON al.user_id = u.id
            ORDER BY al.activity_timestamp DESC";

        $result = mysqli_query($conn, $sql);

        if (mysqli_num_rows($result) > 0) {
            $no = 1; // Row number counter
            while ($row = mysqli_fetch_assoc($result)) {
                echo "<tr>";
                echo "<td>" . $no++ . "</td>";
                echo "<td>" . htmlspecialchars($row['fname']) . " " . htmlspecialchars($row['lname']) . "</td>";
                echo "<td>" . htmlspecialchars($row['activity_type']) . "</td>";
                echo "<td>" . htmlspecialchars($row['activity_details']) . "</td>";
                echo "<td>" . $row['activity_timestamp'] . "</td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='5'>No activity logs found.</td></tr>";
        }
        ?>
    </tbody>
</table>


<script>
    // Confirmation before deletion
    function confirmDelete() {
        return confirm("Are you sure you want to delete this activity log?");
    }
</script>


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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Bootstrap JS and Popper.js -->



    <!-- Bootstrap JS and Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <script>
// DataTable Initialization with Export Buttons
$(document).ready(function() {
    var table = $('#activityLogTable').DataTable({
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'copy',
                exportOptions: { columns: ':not(:last-child)' },
                title: 'Activity Logs List Report - ' + getFormattedDate(),
            },
            {
                extend: 'csv',
                exportOptions: { columns: ':not(:last-child)' },
                title: 'Activity Logs List Report - ' + getFormattedDate(),
            },
            {
                extend: 'excel',
                exportOptions: { columns: ':not(:last-child)' },
                title: 'Activity Logs List Report - ' + getFormattedDate(),
            },
            {
                extend: 'print',
                exportOptions: { columns: ':not(:last-child)' },
                title: '',
                customize: function(win) {
                    var doc = win.document;
                    $(doc.body).css({
                        fontFamily: 'Arial, sans-serif',
                        fontSize: '12pt',
                        color: '#333333',
                        lineHeight: '1.6',
                        backgroundColor: '#ffffff',
                    });
                    $(doc.body).prepend('<h1 style="text-align:center; font-size: 20pt; font-weight: bold;"> Activity Logs List Report</h1>');
                    $(doc.body).prepend('<p style="text-align:center; font-size: 12pt;">' + getFormattedDate() + '</p><hr>');
                },
            }
        ],
        paging: false,
        searching: false,
        info: false,
    });
});

// Function to get the current date and time in a formatted string
function getFormattedDate() {
    var now = new Date();
    var date = now.getFullYear() + '-' + ('0' + (now.getMonth() + 1)).slice(-2) + '-' + ('0' + now.getDate()).slice(-2);
    var time = ('0' + now.getHours()).slice(-2) + ':' + ('0' + now.getMinutes()).slice(-2) + ':' + ('0' + now.getSeconds()).slice(-2);
    return date + ' ' + time;
}

// Function to filter the table based on search and filter inputs
function filterTable() {
    var searchInput = document.getElementById('searchInput').value.toLowerCase();
    var filterSelect = document.getElementById('filterSelect').value;
    var table = document.getElementById('activityLogTable');
    var rows = table.getElementsByTagName('tr');
    
    // Loop through rows
    for (var i = 1; i < rows.length; i++) {
        var cells = rows[i].getElementsByTagName('td');
        var showRow = false;
        
        // Apply filter and search
        if (searchInput !== "") {
            var searchValue = "";
            // Depending on the filter selected, get the corresponding cell content
            switch (filterSelect) {
                case "fname":
                    searchValue = cells[1].textContent.toLowerCase(); // Name column
                    break;
                case "activity_type":
                    searchValue = cells[2].textContent.toLowerCase(); // Activity Type column
                    break;
                case "activity_details":
                    searchValue = cells[3].textContent.toLowerCase(); // Activity Details column
                    break;
                case "role":
                    searchValue = cells[4].textContent.toLowerCase(); // Role column
                    break;
                default:
                    // If no filter is selected, search across all columns
                    searchValue = cells[1].textContent.toLowerCase() + " " + cells[2].textContent.toLowerCase() + " " + cells[3].textContent.toLowerCase() + " " + cells[4].textContent.toLowerCase();
            }

            if (searchValue.indexOf(searchInput) > -1) {
                showRow = true;
            }
        } else {
            showRow = true; // Show all rows if search input is empty
        }

        // Apply row visibility
        rows[i].style.display = showRow ? "" : "none";
    }
}

// Get the correct column index based on the filter selected
function getFilterColumnIndex(filter) {
    switch (filter) {
        case 'fname': return 1;  // Name column
        case 'activity_type': return 2;  // Activity Type column
        case 'activity_details': return 3;  // Activity Details column
        case 'role': return 4;  // Role column
        default: return -1;
    }
}

// Function to apply sorting
function applySort() {
    var sortSelect = document.getElementById('sort').value;
    var table = document.getElementById('activityLogTable');
    var rows = Array.from(table.getElementsByTagName('tr')).slice(1);  // Exclude header row

    switch (sortSelect) {
        case 'timestamp_asc':
            rows.sort((a, b) => new Date(a.cells[4].textContent) - new Date(b.cells[4].textContent));
            break;
        case 'timestamp_desc':
            rows.sort((a, b) => new Date(b.cells[4].textContent) - new Date(a.cells[4].textContent));
            break;
        case 'name_asc':
            rows.sort((a, b) => a.cells[1].textContent.localeCompare(b.cells[1].textContent));
            break;
        case 'name_desc':
            rows.sort((a, b) => b.cells[1].textContent.localeCompare(a.cells[1].textContent));
            break;
        case 'activity_type_asc':
            rows.sort((a, b) => a.cells[2].textContent.localeCompare(b.cells[2].textContent));
            break;
        case 'activity_type_desc':
            rows.sort((a, b) => b.cells[2].textContent.localeCompare(a.cells[2].textContent));
            break;
        default:
            return;
    }

    // Append sorted rows to table
    rows.forEach(row => table.appendChild(row));
}


// Function to apply sorting
function applySort() {
    var sortSelect = document.getElementById('sort').value;
    var table = document.getElementById('activityLogTable');
    var rows = Array.from(table.getElementsByTagName('tr')).slice(1);  // Exclude header row

    switch (sortSelect) {
        case 'timestamp_asc':
            rows.sort((a, b) => new Date(a.cells[4].textContent) - new Date(b.cells[4].textContent));
            break;
        case 'timestamp_desc':
            rows.sort((a, b) => new Date(b.cells[4].textContent) - new Date(a.cells[4].textContent));
            break;
        case 'name_asc':
            rows.sort((a, b) => a.cells[1].textContent.localeCompare(b.cells[1].textContent));
            break;
        case 'name_desc':
            rows.sort((a, b) => b.cells[1].textContent.localeCompare(a.cells[1].textContent));
            break;
        case 'activity_type_asc':
            rows.sort((a, b) => a.cells[2].textContent.localeCompare(b.cells[2].textContent));
            break;
        case 'activity_type_desc':
            rows.sort((a, b) => b.cells[2].textContent.localeCompare(a.cells[2].textContent));
            break;
        default:
            return;
    }

    // Append sorted rows to table
    rows.forEach(row => table.appendChild(row));
}


    const rowsPerPage = 15;
    let currentPage = 1;

    // Use a timeout to ensure the table is fully rendered before pagination logic is applied
    window.onload = function() {
        const rows = document.querySelectorAll('#assignpage tr');
        const totalPages = Math.ceil(rows.length / rowsPerPage);

        // Function to display the current page
        function showPage(page) {
            const start = (page - 1) * rowsPerPage;
            const end = start + rowsPerPage;
            rows.forEach((row, index) => {
                row.style.display = index >= start && index < end ? '' : 'none';
            });
            document.getElementById('pageIndicator').innerText = `Page ${page}`;
            document.getElementById('prevPage').disabled = page === 1;
            document.getElementById('nextPage').disabled = page === totalPages;
        }

        // Show initial page
        showPage(currentPage);

        // Next Page function
        function nextPage() {
            if (currentPage < totalPages) {
                currentPage++;
                showPage(currentPage);
            }
        }

        // Previous Page function
        function prevPage() {
            if (currentPage > 1) {
                currentPage--;
                showPage(currentPage);
            }
        }

        // Attach next/prev page functions to buttons
        document.getElementById('prevPage').onclick = prevPage;
        document.getElementById('nextPage').onclick = nextPage;
    };


// Toggle Sidebar Functionality
const hamburgerMenu = document.getElementById('hamburgerMenu');
const sidebar = document.getElementById('sidebar');
sidebar.classList.add('collapsed');

hamburgerMenu.addEventListener('click', function() {
    sidebar.classList.toggle('collapsed');
    const icon = hamburgerMenu.querySelector('i');
    icon.classList.toggle('fa-bars');
    icon.classList.toggle('fa-times');
});

</script>

</body>
</html>
