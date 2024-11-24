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

// Query for room data
$roomCountQuery = "SELECT COUNT(*) AS totalRooms FROM rooms";
$roomCountResult = $conn->query($roomCountQuery);
$roomCount = $roomCountResult->fetch_assoc()['totalRooms'];

// Query for assigned room data
$assignedRoomQuery = "SELECT COUNT(*) AS assignedRooms FROM roomassignments";
$assignedRoomResult = $conn->query($assignedRoomQuery);
$assignedRooms = $assignedRoomResult->fetch_assoc()['assignedRooms'];

// Query for room applications
$applicationsQuery = "SELECT COUNT(*) AS pendingApplications FROM  room_reassignments WHERE status = 'pending'";
$applicationsResult = $conn->query($applicationsQuery);
$pendingApplications = $applicationsResult->fetch_assoc()['pendingApplications'];



    


$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Manage</title>
    <link rel="icon" href="img-icon/room.png" type="image/png">

    <link rel="stylesheet" href="Css_Admin/admin_manageuser.css">
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
            <a href="dashboard.php" class="nav-link"><i class="fas fa-home"></i> <span>Home</span></a>
            <a href="manageuser.php" class="nav-link"><i class="fas fa-users"></i> <span>Manage User</span></a>

            <!-- Room Manager Dropdown Menu -->
            <a href="admin-room.php" class="nav-link active " id="roomManagerDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="fas fa-building"></i> <span>Room Manager</span>

            <a href="admin-visitor_log.php" class="nav-link"><i class="fas fa-address-book"></i> <span>Log Visitor</span></a>
            <a href="admin-monitoring.php" class="nav-link"><i class="fas fa-eye"></i> <span>Presence Monitoring</span></a>
            <a href="admin-chat.php" class="nav-link"><i class="fas fa-comments"></i> <span>Group Chat</span></a>
            <a href="rent_payment.php" class="nav-link"><i class="fas fa-money-bill-alt"></i> <span>Rent Payment</span></a>
            <a href="activity-logs.php" class="nav-link"><i class="fas fa-clipboard-list"></i> <span>Activity Logs</span></a>

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
        <h2>Room Manage</h2>
    </div>

    <!-- Main content -->
    <div class="main-content">        
        
        <div class="container mt-5">
            <div class="row">
                <!-- Room List Card -->
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <span class="badge-status"><?php echo $roomCount; ?> Rooms Available</span>
                            <i class="fas fa-list fa-3x"></i>
                            <h5 class="card-title mt-3">Room List</h5>
                            <p class="card-text">Manage and view the list of rooms.</p>
                            <div class="progress mt-2">
                                <div class="progress-bar" role="progressbar" style="width: <?php echo ($roomCount / 100) * 100; ?>%;" aria-valuenow="<?php echo $roomCount; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <a href="roomlist.php" class="btn btn-primary mt-3">View Manage</a>
                        </div>
                    </div>
                </div>


                <!-- Room Assign Card -->
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <span class="badge-status"><?php echo $assignedRooms; ?> Assigned</span>
                            <i class="fas fa-user-check fa-3x"></i>
                            <h5 class="card-title mt-3">Room Assign</h5>
                            <p class="card-text">Assign rooms to users.</p>
                            <div class="progress mt-2">
                                <div class="progress-bar" role="progressbar" style="width: <?php echo ($assignedRooms / 100) * 100; ?>%;" aria-valuenow="<?php echo $assignedRooms; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <a href="room-assign.php" class="btn btn-primary mt-3">View Manage</a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <span class="badge-status"><?php echo $pendingApplications; ?> Pending Reassign</span>
                            <i class="fas fa-file-alt fa-3x"></i>
                            <h5 class="card-title mt-3">Reassign Room</h5>
                            <p class="card-text">Apply for room allocation.</p>
                            <div class="progress mt-2">
                                <div class="progress-bar" role="progressbar" style="width: <?php echo ($pendingApplications / 100) * 100; ?>%;" aria-valuenow="<?php echo $pendingApplications; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <a href="application-room.php" class="btn btn-primary mt-3">View Manage</a>
                        </div>
                    </div>
                </div>
            </div>
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
