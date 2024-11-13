<?php
session_start();

// Redirect to login if not logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: admin-login.php");
    exit;
}

include '../config/config.php'; // Ensure this is correct
// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
// Fetch current room assignments, resident details, and room details
$query = "
    SELECT 
        roomassignments.assignment_id,
        users.id AS user_id,
        CONCAT(users.fname, ' ', users.lname) AS resident_name,
        rooms.room_number,
        rooms.room_id,
        rooms.room_monthlyrent
    FROM 
        roomassignments
    INNER JOIN 
        users ON roomassignments.user_id = users.id
    INNER JOIN 
        rooms ON roomassignments.room_id = rooms.room_id
    ORDER BY 
        roomassignments.assignment_id ASC
";
$result = $conn->query($query);

// Assuming you have a mysqli connection ($conn)
global $conn; // Use your database connection variable here


// Fetch the selected filter and room ID from the GET request
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$room_id = isset($_GET['room_id']) ? $_GET['room_id'] : '';

// Define the getRooms function
function getRooms($filter = 'all') {
    global $conn; // Use your database connection

    // Start with the base query to fetch rooms
    $sql = "SELECT room_id, room_number FROM rooms WHERE status != 'maintenance'"; // Exclude rooms in maintenance

    // Add filter conditions based on the selected filter
    if ($filter == 'resident') {
        // For example: Show only occupied rooms
        $sql .= " AND room_id IN (SELECT room_id FROM roomassignments WHERE resident_name IS NOT NULL)";
    } elseif ($filter == 'monthly_rent') {
        // Example: Show rooms with a rent greater than or equal to 600
        $sql .= " AND room_monthlyrent >= 600";
    }

    // Execute the query
    $result = $conn->query($sql);

    // Return the result set
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Fetch the available rooms based on the filter
$rooms = getRooms($filter);

// Modify the query to fetch room assignments based on the selected room_id
$sql = "SELECT r.room_number, r.room_monthlyrent, CONCAT(u.fname, ' ', u.lname) AS resident_name 
        FROM rooms r
        LEFT JOIN roomassignments ra ON r.room_id = ra.room_id
        LEFT JOIN users u ON ra.user_id = u.id";

if ($room_id) {
    $sql .= " WHERE r.room_id = ?";
}

$stmt = $conn->prepare($sql);
if ($room_id) {
    $stmt->bind_param("i", $room_id);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="Css_Admin/adminmanageuser.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/0.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.16.9/xlsx.full.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
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
        
        <h2>Resident Room View</h2>
        
    </div>
    

                              <!-- AYUSIN ANG ROOM ASSIGN AND REASSINGN HUHU -->
    <!-- Main content -->
    <div class="main-content">
    <div class="container">

    <div class="d-flex justify-content-start">
    <butto type="button" class="btn" onclick="window.history.back();">
    <i class="fas fa-arrow-left fa-2x me-1"></i></button>
</div>
</div>
<!-- HTML Form with Search and Filter -->
<!-- Search and Filter Section -->
<div class="container mt-1">
    <div class="row mb-4">
        <div class="col-12 col-md-8 mt-3">
            <input type="text" id="searchInput" class="form-control custom-input-small" placeholder="Search for room details...">
        </div>
        <div class="col-6 col-md-2">
            <form method="GET" action="">
                    <label for="roomSelect">Select Room Number</label>
                    <select name="room_id" required onchange="this.form.submit()">
                        <option value="">All</option>
                        <?php foreach ($rooms as $room): ?>
                            <option value="<?php echo htmlspecialchars($room['room_id']); ?>" <?php echo $room['room_id'] == $room_id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($room['room_number']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
            </form>
        </div>
    </div>
</div>

<!-- Room Assignment Table -->
<table class="table table-bordered" id="assignmentTable">
    <thead>
        <tr>
            <th>No.</th>
            <th>Resident</th>
            <th>Room</th>
            <th>Monthly Rent</th>
        </tr>
    </thead>
    <tbody>
        <?php
        if ($result->num_rows > 0) {
            $counter = 1;
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $counter++ . "</td>";
                echo "<td class='resident'>" . htmlspecialchars($row['resident_name']) . "</td>";
                echo "<td class='room'>Room " . htmlspecialchars($row['room_number']) . "</td>";
                echo "<td class='monthly_rent'>₱" . number_format($row['room_monthlyrent'], 2) . "</td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='4'>No room assignments found</td></tr>";
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


    
    <!-- Include jQuery and Bootstrap JS (required for dropdown) -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Hamburgermenu Script -->
    <script>

document.getElementById("searchInput").addEventListener("keyup", function() {
        var value = this.value.toLowerCase();
        var rows = document.querySelectorAll("#assignmentTable tbody tr");

        rows.forEach(function(row) {
            var cells = row.getElementsByTagName("td");
            var matches = false;

            for (var i = 0; i < cells.length; i++) {
                if (cells[i].innerText.toLowerCase().includes(value)) {
                    matches = true;
                    break;
                }
            }

            row.style.display = matches ? "" : "none";
        });
    });
// JavaScript for client-side pagination
const rowsPerPage = 10; // Display 10 rows per page
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

         document.addEventListener('DOMContentLoaded', function() {
        const filterSelect = document.getElementById('filterSelect');
        const searchInput = document.getElementById('searchInput');
        const table = document.getElementById('assignmentTable');
        const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

        function filterTable() {
            const filterBy = filterSelect.value;
            const searchTerm = searchInput.value.toLowerCase();

            Array.from(rows).forEach(row => {
                let cellText = '';

                // Get text based on selected filter
                switch(filterBy) {
                    case 'resident':
                        cellText = row.querySelector('.resident').textContent.toLowerCase();
                        break;
                    case 'current_room':
                        cellText = row.querySelector('.current_room').textContent.toLowerCase();
                        break;
                    case 'new_room':
                        cellText = row.querySelector('.new_room').textContent.toLowerCase();
                        break;
                    case 'monthly_rent':
                        cellText = row.querySelector('.monthly_rent').textContent.toLowerCase();
                        break;
                    default:
                        // Search across all columns if "all" is selected
                        cellText = row.textContent.toLowerCase();
                }

                // Show or hide row based on search match
                row.style.display = cellText.includes(searchTerm) ? '' : 'none';
            });
        }

        // Attach event listeners to filter and search input
        filterSelect.addEventListener('change', filterTable);
        searchInput.addEventListener('keyup', filterTable);
    });

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
