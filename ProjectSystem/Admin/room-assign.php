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
// Check if the user is logged in
if (!isset($_SESSION['id'])) {
    echo "Unauthorized access. Please log in.";
    exit;
}

// Handle form submission for room assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $applicationId = intval($_POST['application_id']);
    $roomId = intval($_POST['room_id']);

    // Fetch the user_id associated with the application
    $fetchUserIdQuery = "SELECT user_id FROM RoomApplications WHERE application_id = ?";
    $userStmt = $conn->prepare($fetchUserIdQuery);
    $userStmt->bind_param('i', $applicationId);
    $userStmt->execute();
    $userStmt->bind_result($userId);
    $userStmt->fetch();
    $userStmt->close();

    // Check if the user already has a room assigned
    $checkAssignmentQuery = "SELECT assignment_id FROM RoomAssignments WHERE user_id = ?";
    $assignmentStmt = $conn->prepare($checkAssignmentQuery);
    $assignmentStmt->bind_param('i', $userId);
    $assignmentStmt->execute();
    $assignmentStmt->store_result();

    if ($assignmentStmt->num_rows > 0) {
        // Update existing room assignment
        $updateRoomQuery = "UPDATE RoomAssignments SET room_id = ?, assignment_date = CURRENT_DATE WHERE user_id = ?";
        $updateRoomStmt = $conn->prepare($updateRoomQuery);
        $updateRoomStmt->bind_param('ii', $roomId, $userId);
        if ($updateRoomStmt->execute()) {
            echo "Room assignment updated successfully.";
        } else {
            echo "Error updating room assignment.";
        }
        $updateRoomStmt->close();
    } else {
        // Insert new room assignment
        $insertRoomQuery = "INSERT INTO RoomAssignments (user_id, room_id, assignment_date) VALUES (?, ?, CURRENT_DATE)";
        $insertRoomStmt = $conn->prepare($insertRoomQuery);
        $insertRoomStmt->bind_param('ii', $userId, $roomId);
        if ($insertRoomStmt->execute()) {
            echo "Room assigned successfully.";
        } else {
            echo "Error assigning room.";
        }
        $insertRoomStmt->close();
    }
    $assignmentStmt->close();
}

// SQL query to get approved applications and their current room assignments (if any)
$sql = "
    SELECT ra.application_id, 
           CONCAT(u.fname, ' ', u.lname) AS resident_name, 
           r.room_number, 
           r.room_desc, 
           ra.room_id AS current_room_id, 
           (SELECT room_number FROM rooms WHERE room_id = ass.room_id) AS assigned_room_number
    FROM roomapplications AS ra
    LEFT JOIN rooms AS r ON ra.room_id = r.room_id
    LEFT JOIN users AS u ON ra.user_id = u.id
    LEFT JOIN roomassignments AS ass ON ra.user_id = ass.user_id
    WHERE ra.status = 'approved'
";

$applicationsResult = $conn->query($sql);


// Fetch available rooms from Rooms table
$availableRoomsQuery = "SELECT room_id, room_number FROM Rooms WHERE status = 'available'";
$availableRoomsResult = $conn->query($availableRoomsQuery);

$availableRooms = [];
if ($availableRoomsResult->num_rows > 0) {
    while ($room = $availableRoomsResult->fetch_assoc()) {
        $availableRooms[] = $room;
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
        <h2>Application Room</h2>
    </div>

    <!-- Main content -->
    <div class="main-content">        
        
    <h2>Room Assignments</h2>

    <!-- Display the list of approved applications -->
<?php if ($applicationsResult->num_rows > 0): ?>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Application ID</th>
                <th>Resident</th>
                <th>Current Room Number</th>
                <th>Assigned Room Number</th> <!-- Show the assigned room if any -->
                <th>Assign New Room</th> <!-- Dropdown to assign a new room -->
            </tr>
        </thead>
        <tbody>
            <?php while ($application = $applicationsResult->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($application['application_id']); ?></td>
                    <td><?php echo htmlspecialchars($application['resident_name']); ?></td>
                    <td><?php echo htmlspecialchars($application['room_number']); ?></td>

                    <td>
                        <?php if (!empty($application['assigned_room_number'])): ?>
                            <?php echo htmlspecialchars($application['assigned_room_number']); ?>
                        <?php else: ?>
                            Not yet assigned
                        <?php endif; ?>
                    </td>

                    <td>
                        <!-- Room assignment form -->
                        <form action="room-assign.php" method="POST">
                            <input type="hidden" name="application_id" value="<?php echo $application['application_id']; ?>">

                            <!-- Dropdown for selecting a new room -->
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
    <p>No approved applications available for assignment.</p>
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
