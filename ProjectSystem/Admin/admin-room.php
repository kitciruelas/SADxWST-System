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
$applicationsQuery = "SELECT COUNT(*) AS pendingApplications FROM  RoomApplications WHERE status = 'pending'";
$applicationsResult = $conn->query($applicationsQuery);
$pendingApplications = $applicationsResult->fetch_assoc()['pendingApplications'];

// Set limit of records per page
$limit = 6;

// Get the current page number from the URL, default to page 1
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// Calculate the starting point (offset)
$offset = ($page - 1) * $limit;

// Query to fetch rooms from the database with limit and offset for pagination
$sql = "SELECT room_id, room_number, room_desc, capacity, room_monthlyrent, status, room_pic FROM rooms LIMIT $limit OFFSET $offset";
$result = $conn->query($sql);

// Query to get the total number of rooms (for pagination calculation)
$totalRoomsQuery = "SELECT COUNT(*) AS total FROM rooms";
$totalResult = $conn->query($totalRoomsQuery);
$totalRooms = $totalResult->fetch_assoc()['total'];

// Calculate total pages
$totalPages = ceil($totalRooms / $limit);

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
            </div>

        </div>
        <div class="logout">
            <a href="../config/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
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
                            <a href="roomlist.php" class="btn btn-primary mt-3">View Details</a>
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
                            <a href="room-assign.php" class="btn btn-primary mt-3">View Details</a>
                        </div>
                    </div>
                </div>

                <!-- Room Application Card -->
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <span class="badge-status"><?php echo $pendingApplications; ?> Pending Applications</span>
                            <i class="fas fa-file-alt fa-3x"></i>
                            <h5 class="card-title mt-3">Room Application</h5>
                            <p class="card-text">Apply for room allocation.</p>
                            <div class="progress mt-2">
                                <div class="progress-bar" role="progressbar" style="width: <?php echo ($pendingApplications / 100) * 100; ?>%;" aria-valuenow="<?php echo $pendingApplications; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <a href="application-room.php" class="btn btn-primary mt-3">View Details</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

                <!-- Room Display-->

                <h1 class="text-center">Rooms</h1>

<div class="container">
    <div class="row">

        <!-- Loop through the database records and display each room -->
        <?php
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                ?>
                <div class="col-md-4">
                    <div class="room-card">
                        <!-- Display room image -->
                        <img src="<?php echo $row['room_pic']; ?>" alt="Room Image">

                        <!-- Rent Price -->
                        <p class="room-price">Rent Price: <?php echo number_format($row['room_monthlyrent'], 2); ?> / Monthly</p>
                        
                         <!-- Room Number -->
                         <h5>Room: <?php echo htmlspecialchars($row['room_number']); ?></h5>

                        <!-- Room Capacity -->
                        <p>Capacity: <?php echo htmlspecialchars($row['capacity']); ?> people</p>

                        <!-- Room Description -->
                        <p><?php echo htmlspecialchars($row['room_desc']); ?></p>

                        <!-- Room Status -->
                        <p>Status: <?php echo htmlspecialchars($row['status']); ?></p>

                        <button class="apply-btn" id="applyNowBtn">Apply Now!</button>
                                        </div>
                </div>
                <?php
            }
        } else {
            echo "<p>No rooms available.</p>";
        }
        ?>

    </div>

    <!-- Pagination Links -->
    <div class="pagination">
         <!-- Pagination Links -->
    <div id="pagination">
        <!-- Previous Page Button -->
        <button <?php if ($page <= 1) { echo 'disabled'; } ?> onclick="window.location.href='?page=<?php echo $page - 1; ?>'">
            Previous
        </button>

        <!-- Page Indicator -->
        <span id="pageIndicator">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>

        <!-- Next Page Button -->
        <button <?php if ($page >= $totalPages) { echo 'disabled'; } ?> onclick="window.location.href='?page=<?php echo $page + 1; ?>'">
            Next
        </button>
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
