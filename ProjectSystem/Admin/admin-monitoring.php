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

// Capture search and filter values
$search = isset($_GET['search']) ? $_GET['search'] : ''; 
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all'; 

// Base SQL query
$sql = "SELECT * FROM rooms WHERE 1";

// Apply search conditions
if (!empty($search)) {
    $search = $conn->real_escape_string($search); // Prevent SQL injection

    switch ($filter) {
        case 'room_number':
            $sql .= " AND room_number LIKE '%$search%'";
            break;
        case 'capacity':
            $sql .= " AND capacity LIKE '%$search%'";
            break;
        case 'status':
            $sql .= " AND status LIKE '%$search%'";
            break;
        default:
            $sql .= " AND (room_number LIKE '%$search%' OR capacity LIKE '%$search%' OR status LIKE '%$search%')";
            break;
    }
}

if (isset($_GET['delete_attendance_id'])) {
    // Get the ID of the attendance record to be deleted
    $delete_id = $_GET['delete_attendance_id'];

    // Validate if the ID is numeric (optional but recommended)
    if (is_numeric($delete_id)) {
        // Prepare the DELETE SQL query
        $sql = "DELETE FROM presencemonitoring WHERE attendance_id = ?";

        // Prepare the statement
        if ($stmt = $conn->prepare($sql)) {
            // Bind the parameter to the query
            $stmt->bind_param("i", $delete_id);

            // Execute the query
            if ($stmt->execute()) {
                // Success message
                echo "<script>alert('Record deleted successfully'); window.location.href='admin-monitoring.php';</script>";
            } else {
                // Error message on failure
                echo "<script>alert('Error deleting record');</script>";
            }

            // Close the statement
            $stmt->close();
        } else {
            // Error in preparing the query
            echo "<script>alert('Error preparing the query');</script>";
        }
    } else {
        echo "<script>alert('Invalid ID');</script>";
    }
}
$query = "
SELECT 
    pm.attendance_id AS ID,
    CONCAT(u.fname, ' ', u.lname) AS Resident_Name,
    r.room_number AS Room_Number,
    DATE_FORMAT(pm.check_in, '%Y-%m-%d %h:%i %p') AS Check_In_Time,  -- Format check_in with AM/PM
    DATE_FORMAT(pm.check_out, '%Y-%m-%d %h:%i %p') AS Check_Out_Time, -- Format check_out with AM/PM
    pm.date AS Date
FROM 
    presencemonitoring pm
JOIN 
    users u ON pm.user_id = u.id
JOIN 
    roomassignments ra ON ra.user_id = u.id
JOIN 
    rooms r ON ra.room_id = r.room_id
ORDER BY 
    pm.check_in DESC;  -- Sorting by check_in in descending order
";


$result = $conn->query($query);

$conn->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Presence Monitoring</title>
    <link rel="icon" href="img-icon/eye.png" type="image/png">

    <link rel="stylesheet" href="Css_Admin/admin_manageuser.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.1/css/jquery.dataTables.min.css">

<!-- Bootstrap CSS -->
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>

<!-- Bootstrap JS -->
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<!-- DataTables Buttons CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.3/css/buttons.dataTables.min.css">

<!-- DataTables Buttons JS -->
<script src="https://cdn.datatables.net/buttons/2.2.3/js/dataTables.buttons.min.js"></script>

<!-- JSZip for Excel Export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>

<!-- pdfMake for PDF Export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>

<!-- DataTables Buttons for exporting -->
<script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.html5.min.js"></script>

<script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.print.min.js"></script>
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
            <a href="admin-room.php" class="nav-link" id="roomManagerDropdown"><i class="fas fa-building"></i> <span>Room Manager</span>
            <a href="admin-visitor_log.php" class="nav-link"><i class="fas fa-address-book"></i> <span>Log Visitor</span></a>
            <a href="admin-monitoring.php" class="nav-link active"><i class="fas fa-eye"></i> <span>Presence Monitoring</span></a>
            <a href="admin-chat.php" class="nav-link"><i class="fas fa-comments"></i> <span>Group Chat</span></a>
            <a href="rent_payment.php" class="nav-link"><i class="fas fa-money-bill-alt"></i> <span>Rent Payment</span></a>
            <a href="activity-logs.php" class="nav-link"><i class="fas fa-clipboard-list"></i> <span>Activity Logs</span></a>

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
        <h2>Presence Monitoring</h2>
    </div>

    <!-- Main content -->
    <div class="main-content">
    <div class="container">

    <div class="container mt-1">
    <!-- Search and Filter Section -->
    <!-- Room Table Filter Section -->
<div class="row mb-4">
    <div class="col-12 col-md-8">
        <input type="text" id="searchInput" class="form-control custom-input-small" placeholder="Search...">
    </div>
    <div class="col-6 col-md-2">
        <select id="filterSelect" class="form-select">
            <option value="all">Filter by</option>
            <option value="1">Resident Name</option>
            <option value="2">Room</option>
            <option value="3">Check-In Time</option>
            <option value="4">Check-Out Time</option>
        </select>
    </div>
    <div class="col-6 col-md-2">
        <select id="sortSelect" class="form-select">
            <option value="">Sort by</option>
            <option value="resident_asc">Resident Name (A-Z)</option>
            <option value="resident_desc">Resident Name (Z-A)</option>
            <option value="room_asc">Room (Low to High)</option>
            <option value="room_desc">Room (High to Low)</option>
            <option value="checkin_asc">Check-In (Earliest)</option>
            <option value="checkin_desc">Check-In (Latest)</option>
            <option value="checkout_asc">Check-Out (Earliest)</option>
            <option value="checkout_desc">Check-Out (Latest)</option>
        </select>
    </div>
</div>

<!-- Room Table -->
<div class="table-responsive">
    <table class="table table-bordered" id="monitoring">
        <thead class="table-light">
            <tr>
                <th>ID</th>
                <th>Resident Name</th>
                <th>Room</th>
                <th>Check-In Time</th>
                <th>Check-Out Time</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row["ID"]) . "</td>";
                    echo "<td>" . htmlspecialchars($row["Resident_Name"]) . "</td>";
                    echo "<td>" . htmlspecialchars($row["Room_Number"]) . "</td>";
                    echo "<td>" . htmlspecialchars($row["Check_In_Time"]) . "</td>";
                    echo "<td>" . htmlspecialchars($row["Check_Out_Time"]) . "</td>";
                    echo "<td>";
                    echo "<form method='GET' action='admin-monitoring.php' style='display:inline;' onsubmit='return confirmDelete()'>
                        <input type='hidden' name='delete_attendance_id' value='" . htmlspecialchars($row["ID"]) . "' />
                        <button type='submit' class='custom-btn delete-btn'>Delete</button>
                    </form>";
                    echo "</td>";
                    echo "</tr>";
                }
            }
            ?>
        </tbody>
    </table>
</div>



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


           <!-- JavaScript Libraries -->
           <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>


<!-- Include jQuery and Bootstrap JS (required for dropdown) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Bootstrap JS and Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Hamburger Menu Script -->
    <script>
   $(document).ready(function() {
    // Initialize DataTable with pagination disabled
    var table = $('#monitoring').DataTable({
        dom: 'Bfrtip',
        buttons: [
            'copy', 'csv', 'excel', 'print'
        ],
        paging: false,      // Disable DataTables pagination
        info: false,        // Remove "Showing X of Y entries" info
        searching:false,
        lengthChange: false,
        order: []
    });

    // Custom pagination logic
    const rowsPerPage = 10;
    let currentPage = 1;
    const rows = $('#monitoring tbody tr');
    const totalPages = Math.ceil(rows.length / rowsPerPage);

    function showPage(page) {
        const start = (page - 1) * rowsPerPage;
        const end = start + rowsPerPage;
        
        rows.hide();
        rows.slice(start, end).show();
        
        $('#pageIndicator').text(`Page ${page}`);
        $('#prevPage').prop('disabled', page === 1);
        $('#nextPage').prop('disabled', page === totalPages);
    }

    function nextPage() {
        if (currentPage < totalPages) {
            currentPage++;
            showPage(currentPage);
        }
    }

    function prevPage() {
        if (currentPage > 1) {
            currentPage--;
            showPage(currentPage);
        }
    }

    // Initialize pagination
    showPage(1);

    // Bind pagination buttons
    $('#prevPage').on('click', prevPage);
    $('#nextPage').on('click', nextPage);

    // Update pagination when search/filter changes
    table.on('search.dt', function() {
        currentPage = 1;
        rows = $('#monitoring tbody tr:visible');
        totalPages = Math.ceil(rows.length / rowsPerPage);
        showPage(1);
    });

    // Handle custom search
    $('#searchInput').on('keyup', function() {
        table.search(this.value).draw();
    });

    // Handle custom filter
    $('#filterSelect').on('change', function() {
        var columnIndex = $(this).val();
        if (columnIndex === 'all') {
            table.search($('#searchInput').val()).draw();
        } else {
            table.column(columnIndex).search($('#searchInput').val()).draw();
        }
    });

    // Handle custom sort
    $('#sortSelect').on('change', function() {
        var value = $(this).val();
        
        switch(value) {
            case 'resident_asc':
                table.order([1, 'asc']).draw();
                break;
            case 'resident_desc':
                table.order([1, 'desc']).draw();
                break;
            case 'room_asc':
                table.order([2, 'asc']).draw();
                break;
            case 'room_desc':
                table.order([2, 'desc']).draw();
                break;
            case 'checkin_asc':
                table.order([3, 'asc']).draw();
                break;
            case 'checkin_desc':
                table.order([3, 'desc']).draw();
                break;
            case 'checkout_asc':
                table.order([4, 'asc']).draw();
                break;
            case 'checkout_desc':
                table.order([4, 'desc']).draw();
                break;
            default:
                table.order([]).draw();
        }
    });

    // Hamburger menu
    const sidebar = $('#sidebar');
    const hamburgerMenu = $('#hamburgerMenu');
    const mainContent = $('.main-content');
    const topbar = $('.topbar');

    hamburgerMenu.on('click', function() {
        sidebar.toggleClass('collapsed');
        mainContent.toggleClass('expanded');
        topbar.toggleClass('expanded');
        
        const icon = hamburgerMenu.find('i');
        icon.toggleClass('fa-bars fa-times');
    });
});

function confirmDelete() {
    return confirm("Are you sure you want to delete this record?");
}

function getFormattedDate() {
    const now = new Date();
    const date = now.getFullYear() + '-' + 
                 String(now.getMonth() + 1).padStart(2, '0') + '-' + 
                 String(now.getDate()).padStart(2, '0');
    const time = String(now.getHours()).padStart(2, '0') + ':' + 
                 String(now.getMinutes()).padStart(2, '0') + ':' + 
                 String(now.getSeconds()).padStart(2, '0');
    return `${date} ${time}`;
}

     // JavaScript for client-side pagination
     const rowsPerPage = 10; // Limit to 10 rows per page
    let currentPage = 1;
    const rows = document.querySelectorAll('#room-table-body tr');
    const totalPages = Math.ceil(rows.length / rowsPerPage);

    // Show the initial set of rows
    showPage(currentPage);

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

    function nextPage() {
        if (currentPage < totalPages) {
            currentPage++;
            showPage(currentPage);
        }
    }

    function prevPage() {
        if (currentPage > 1) {
            currentPage--;
            showPage(currentPage);
        }
    }
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
