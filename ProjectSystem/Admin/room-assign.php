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
// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $userId = $_POST['user_id'];
    $roomId = $_POST['room_id'];

    // Get the room's capacity
    $roomCapacityQuery = "SELECT capacity FROM rooms WHERE room_id = ?";
    $capacityStmt = $conn->prepare($roomCapacityQuery);
    $capacityStmt->bind_param('i', $roomId);
    $capacityStmt->execute();
    $capacityResult = $capacityStmt->get_result();
    $roomData = $capacityResult->fetch_assoc();
    $roomCapacity = $roomData['capacity'];

    // Count current assignments in the room
    $currentAssignmentsQuery = "SELECT COUNT(*) as current_count FROM roomassignments WHERE room_id = ?";
    $currentAssignmentsStmt = $conn->prepare($currentAssignmentsQuery);
    $currentAssignmentsStmt->bind_param('i', $roomId);
    $currentAssignmentsStmt->execute();
    $currentAssignmentsResult = $currentAssignmentsStmt->get_result();
    $currentAssignmentsData = $currentAssignmentsResult->fetch_assoc();
    $currentCount = $currentAssignmentsData['current_count'];

    // Check if the room is at capacity
    if ($currentCount >= $roomCapacity) {
        // Alert for full capacity
        echo "<script>alert('Cannot assign room. Room is at full capacity.');</script>";
    } else {
        // Check if the user already has a room assigned
        $checkAssignmentQuery = "SELECT assignment_id FROM roomassignments WHERE user_id = ?";
        $assignmentStmt = $conn->prepare($checkAssignmentQuery);
        $assignmentStmt->bind_param('i', $userId);
        $assignmentStmt->execute();
        $assignmentStmt->store_result();

        if ($assignmentStmt->num_rows > 0) {
            // Update existing room assignment
            $updateRoomQuery = "UPDATE roomassignments SET room_id = ?, assignment_date = CURRENT_DATE WHERE user_id = ?";
            $updateRoomStmt = $conn->prepare($updateRoomQuery);
            $updateRoomStmt->bind_param('ii', $roomId, $userId);
            
            if ($updateRoomStmt->execute()) {
                echo "<script>alert('Room assignment updated successfully.');</script>";
            } else {
                echo "<script>alert('Error updating room assignment.');</script>";
            }
            $updateRoomStmt->close();
        } else {
            // Insert new room assignment
            $insertRoomQuery = "INSERT INTO roomassignments (user_id, room_id, assignment_date) VALUES (?, ?, CURRENT_DATE)";
            $insertRoomStmt = $conn->prepare($insertRoomQuery);
            $insertRoomStmt->bind_param('ii', $userId, $roomId);
            
            if ($insertRoomStmt->execute()) {
                echo "<script>alert('Room assigned successfully.');</script>";
            } else {
                echo "<script>alert('Error assigning room.');</script>";
            }
            $insertRoomStmt->close();
        }
        $assignmentStmt->close();
    }
    // Close the capacity check statement
    $capacityStmt->close();
    // Close the current assignments check statement
    $currentAssignmentsStmt->close();
}

// Fetch room assignments for all users
$currentAssignmentsQuery = "
    SELECT 
        users.id AS user_id, 
        CONCAT(users.fname, ' ', users.lname) AS resident, 
        rooms.room_number AS assigned_room_number,  -- Renamed to assigned_room_number
        rooms.room_monthlyrent AS assigned_monthly_rent  -- Renamed to assigned_monthly_rent
    FROM users 
    LEFT JOIN roomassignments ON users.id = roomassignments.user_id 
    LEFT JOIN rooms ON roomassignments.room_id = rooms.room_id 
    ORDER BY users.id DESC"; // Ordering by users.id in descending order

$currentAssignmentsResult = $conn->query($currentAssignmentsQuery);

// Fetch reassignment data
$reassignmentsQuery = "
    SELECT 
        ra.user_id, 
        CONCAT(u.fname, ' ', u.lname) AS resident,
        rn.room_number AS assigned_room_number,  
        rn.room_monthlyrent AS assigned_monthly_rent,
        ra.status AS reassignment_status
    FROM room_reassignments ra
    JOIN users u ON ra.user_id = u.id
    JOIN rooms rn ON ra.new_room_id = rn.room_id  
    ORDER BY ra.user_id ASC";

$reassignmentsResult = $conn->query($reassignmentsQuery);

// Initialize assignments data
$assignmentsData = [];
if ($currentAssignmentsResult->num_rows > 0) {
    while ($row = $currentAssignmentsResult->fetch_assoc()) {
        $assignmentsData[$row['user_id']] = $row;
    }
}

if ($reassignmentsResult->num_rows > 0) {
    while ($row = $reassignmentsResult->fetch_assoc()) {
        $assignmentsData[$row['user_id']]['assigned_room_number'] = $row['assigned_room_number'];
        $assignmentsData[$row['user_id']]['assigned_monthly_rent'] = $row['assigned_monthly_rent'];
        $assignmentsData[$row['user_id']]['reassignment_status'] = $row['reassignment_status'];
    }
}
// Sample query to fetch available rooms
$availableRoomsQuery = "SELECT room_id, room_number FROM rooms WHERE status = 'available'";
$availableRoomsResult = $conn->query($availableRoomsQuery);

$availableRooms = [];
if ($availableRoomsResult->num_rows > 0) {
    while ($room = $availableRoomsResult->fetch_assoc()) {
        $availableRooms[] = $room;
    }
} else {
    echo "No available rooms found.";
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['room_id'])) {
    $userId = $_POST['user_id'];
    $roomId = $_POST['room_id'];

    // Perform the room assignment in the database
    $query = "UPDATE room_assignments SET room_id = ? WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $roomId, $userId);
    $success = $stmt->execute();

    // Set a flag if the assignment is successful
    if ($success) {
        $_SESSION['assignment_success'] = true;
    }
}


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
        
        <h2>Room Assign</h2>
        
    </div>
    

                              <!-- AYUSIN ANG ROOM ASSIGN AND REASSINGN HUHU -->
    <!-- Main content -->
    <div class="main-content">
    <div class="container">

    <div class="d-flex justify-content-start">
    <a href="admin-room.php" class="btn " onclick="window.location.reload();">
    <i class="fas fa-arrow-left fa-2x me-1"></i></a>

</div>
</div>
<div class="container mt-1">
    <!-- Search and Filter Section -->
    <div class="row mb-4">
        <div class="col-12 col-md-8">
            <input type="text" id="searchInput" class="form-control custom-input-small" placeholder="Search for room details...">
        </div>
        <div class="col-6 col-md-2">
            <select id="filterSelect" class="form-select">
               
            <option value="all" selected>Filter by</option>
                <option value="resident">Resident</option>
                <option value="current_room">Current Room</option>
                <option value="new_room">New Room</option>
                <option value="monthly_rent">Monthly Rent</option>
        
            </select>
        </div>
    </div>



    <?php if (!empty($assignmentsData)): ?>
    <table class="table table-bordered" id="assignmentTable">
        <thead>
            <tr>
                <th>No.</th>
                <th>Resident</th>
                <th>Room</th>
                <th>Monthly Rent</th>
                <th>Assign New Room</th>
            </tr>
        </thead>
        <tbody id="room-table-body">
            <?php 
            $counter = 1;
            foreach ($assignmentsData as $row):
                // Skip the row if the user was successfully assigned
                if (isset($_SESSION['assigned_user_id']) && $_SESSION['assigned_user_id'] == $row['user_id']) {
                    continue;
                }

                $hasAssignedRoom = !empty($row['assigned_room_number']);
                $isPending = (isset($row['reassignment_status']) && $row['reassignment_status'] == 'pending');
                
                // Show the form only if the room is not assigned or has a pending reassignment
                $showAssignForm = !$hasAssignedRoom || $isPending;
            ?>
                <tr>
                    <td><?php echo $counter++; ?></td>
                    <td class="resident"><?php echo htmlspecialchars($row['resident']); ?></td>
                    <td class="room"><?php echo htmlspecialchars($row['assigned_room_number'] ?? 'No Room Assigned'); ?></td>
                    <td class="monthly_rent"><?php echo isset($row['assigned_monthly_rent']) ? number_format($row['assigned_monthly_rent'], 2) : 'N/A'; ?></td>
                    <td>
                        <?php if ($showAssignForm): ?>
                            <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
                                <input type="hidden" name="user_id" value="<?php echo $row['user_id']; ?>">
                                <select name="room_id" required>
                                    <option value="">Select Room</option>
                                    <?php foreach ($availableRooms as $room): ?>
                                        <option value="<?php echo htmlspecialchars($room['room_id']); ?>">
                                            <?php echo htmlspecialchars($room['room_number']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-primary">Assign</button>
                            </form>
                        <?php else: ?>
                            <span class="text-success">Room Assignment Active</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php unset($_SESSION['assigned_user_id']); // Clear the flag after display ?>
<?php else: ?>
    <p>No room assignments found.</p>
<?php endif; ?>





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
