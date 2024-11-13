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
    pm.check_in AS Check_In_Time,
    pm.check_out AS Check_Out_Time,
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
    pm.check_in DESC;  -- Sorting by check_in in ascending order

";

$result = $conn->query($query);

$conn->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="Css_Admin/adminmanageuser.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="menu" id="hamburgerMenu">
            <i class="fas fa-bars"></i>
        </div>
        <div class="sidebar-nav">
            <a href="dashboard.php" class="nav-link"><i class="fas fa-user-cog"></i> <span>Profile</span></a>
            <a href="manageuser.php" class="nav-link"><i class="fas fa-users"></i> <span>Manage User</span></a>
            <a href="admin-room.php" class="nav-link" id="roomManagerDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="fas fa-building"></i> <span>Room Manager</span>
            <a href="admin-visitor_log.php" class="nav-link"><i class="fas fa-address-book"></i> <span>Log Visitor</span></a>
            <a href="admin-monitoring.php" class="nav-link"><i class="fas fa-eye"></i> <span>Presence Monitoring</span></a>


        </div>
        <div class="logout">
            <a href="../config/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
        </div>
    </div>

    <!-- Top bar -->
    <div class="topbar">
        <h2>Presence Monitoring</h2>
    </div>

    <!-- Main content -->
    <div class="main-content">
    <div class="container">

<div class="d-flex justify-content-start">
<button type="button" class="btn " onclick="window.history.back();">
    <i class="fas fa-arrow-left fa-2x me-2"></i></button>
</div>        
    <div class="container mt-1">
    <!-- Search and Filter Section -->
    <!-- Room Table Filter Section -->
<div class="row mb-4">
    <div class="col-12 col-md-8">
        <input type="text" id="searchInput" class="form-control custom-input-small" placeholder="Search for room details...">
    </div>
    <div class="col-6 col-md-2">
        <select id="filterSelect" class="form-select">
            <option value="all" selected>Filter by</option>
            <option value="room_number">Room Number</option>
            <option value="check_in_time">Check-In Time</option>
            <option value="check_out_time">Check-Out Time</option>
        </select>
    </div>
</div>

<!-- Room Table -->
<div class="table-responsive">
    <table class="table table-bordered">
        <thead class="table-light">
            <tr>
                <th>ID</th>
                <th>Resident Name</th>
                <th>Room Number</th>
                <th>Check-In Time</th>
                <th>Check-Out Time</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="room-table-body">
            <?php
            if ($result->num_rows > 0) {
                $counter = 1;
                while ($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . $counter++ . "</td>";
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
            } else {
                echo "<tr><td colspan='6'>No records found</td></tr>";
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

    <!-- Bootstrap JS and Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Hamburger Menu Script -->
    <script>

          // Get the elements
    const searchInput = document.getElementById('searchInput');
    const filterSelect = document.getElementById('filterSelect');
    const tableBody = document.getElementById('room-table-body');
    
    // Function to filter the table based on the search and filter criteria
    function filterTable() {
        const searchQuery = searchInput.value.toLowerCase();
        const filterValue = filterSelect.value;

        // Loop through all rows
        Array.from(tableBody.rows).forEach(row => {
            let shouldDisplay = false;

            // Get the column values for the selected filter
            const residentName = row.cells[1].textContent.toLowerCase();
            const roomNumber = row.cells[2].textContent.toLowerCase();
            const checkInTime = row.cells[3].textContent.toLowerCase();
            const checkOutTime = row.cells[4].textContent.toLowerCase();
            const dateTime = row.cells[5].textContent.toLowerCase();

            // Check if the search query matches based on the filter selected
            if (filterValue === 'all') {
                shouldDisplay = residentName.includes(searchQuery) ||
                                roomNumber.includes(searchQuery) ||
                                checkInTime.includes(searchQuery) ||
                                checkOutTime.includes(searchQuery) ||
                                dateTime.includes(searchQuery);
            } else if (filterValue === 'room_number') {
                shouldDisplay = roomNumber.includes(searchQuery);
            } else if (filterValue === 'check_in_time') {
                shouldDisplay = checkInTime.includes(searchQuery);
            } else if (filterValue === 'check_out_time') {
                shouldDisplay = checkOutTime.includes(searchQuery);
            } else if (filterValue === 'date_time') {
                shouldDisplay = dateTime.includes(searchQuery);
            }

            // Show or hide the row based on the result
            row.style.display = shouldDisplay ? '' : 'none';
        });
    }

    // Add event listeners for search input and filter select
    searchInput.addEventListener('input', filterTable);
    filterSelect.addEventListener('change', filterTable);

    // Initial filter application
    filterTable();

    
   function confirmDelete() {
    return confirm("Are you sure you want to delete this record?");
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
