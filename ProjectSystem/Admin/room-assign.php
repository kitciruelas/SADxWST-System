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


$sql = "SELECT users.id, CONCAT(users.fname, ' ', users.lname) AS resident, rooms.room_number, rooms.room_monthlyrent 
        FROM users 
        LEFT JOIN roomassignments ON users.id = roomassignments.user_id 
        LEFT JOIN rooms ON roomassignments.room_id = rooms.room_id 
        ORDER BY users.id DESC"; // Ordering by users.id in descending order

$applicationsResult = $conn->query($sql);




// Fetch available rooms for dropdown
$roomsQuery = "SELECT room_id, room_number FROM rooms WHERE status = 'available'";
$roomsResult = $conn->query($roomsQuery);
$availableRooms = $roomsResult->fetch_all(MYSQLI_ASSOC);


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


            <!-- Room Manager Dropdown Menu -->
            <div class="nav-item dropdown">
                
                <a href="#" class="nav-link active dropdown-toggle" id="roomManagerDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="fas fa-building"></i> 
                    <span>Room Manager</span>
                </a>
                <div class="dropdown-menu" aria-labelledby="roomManagerDropdown" style="background-color: #2B228A; border-radius: 9px;">
                <a class="dropdown-item" href="roomlist.php">
                    <i class="fas fa-list"></i> <span>Room List</span>
                </a>
                <a class="dropdown-item" href="room-assign.php">
                    <i class="fas fa-user-check"></i> <span>Room Assign</span>
                </a>
                <a class="dropdown-item" href="application-room.php">
                    <i class="fas fa-file-alt"></i> <span>Room Application</span>
                </a>
                
            </div>
            <a href="admin-visitor_log.php" class="nav-link"><i class="fas fa-address-book"></i> <span>Log Visitor</span></a>

            </div>

        </div>
        <div class="logout">
            <a href="../config/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
        </div>
    </div>

    <!-- Top bar -->
    <div class="topbar">
        <h2>Room Assign</h2>
    </div>

    <!-- Main content -->
    <div class="main-content">        
        
      <!-- <h2>Room Assignments</h2>-->


      <?php if ($applicationsResult->num_rows > 0): ?>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>No.</th> <!-- Serial Number Column -->
                <th>Resident</th> <!-- Combined Name Column -->
                <th>Room Assigned</th>
                <th>Monthly Rent</th>
                <th>Assign New Room</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $counter = 1; // Initialize counter
            while ($row = $applicationsResult->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $counter++; ?></td> <!-- Display the current counter and increment -->
                    <td><?php echo htmlspecialchars($row['resident']); ?></td> <!-- Use the combined name -->
                    <td><?php echo htmlspecialchars($row['room_number'] ?? 'No Room Assigned'); ?></td>
                    <td><?php echo isset($row['room_monthlyrent']) ? number_format($row['room_monthlyrent'], 2) : 'N/A'; ?></td>
                    <td>
                        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
                            <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                            <select name="room_id" required>
                                <option value="">Select Room</option>
                                <?php foreach ($availableRooms as $room): ?>
                                    <option value="<?php echo htmlspecialchars($room['room_id']); ?>">
                                        <?php echo htmlspecialchars($room['room_number']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-primary">Assign Room</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
<?php else: ?>
    <p>No users found.</p>
<?php endif; ?>


       </div>

            
   
    
    <!-- Include jQuery and Bootstrap JS (required for dropdown) -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Hamburgermenu Script -->
    <script>

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
