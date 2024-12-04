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

// Add new query for move-out requests
$moveOutQuery = "SELECT COUNT(*) AS pendingMoveOuts FROM move_out_requests WHERE status = 'pending'";
$moveOutResult = $conn->query($moveOutQuery);
$pendingMoveOuts = $moveOutResult->fetch_assoc()['pendingMoveOuts'];



    


$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Manage</title>
    <link rel="icon" href="img-icon/room.png" type="image/png">

    <link rel="stylesheet" href="../Admin/Css_Admin/admin_manageuser.css">
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
        <a href="user-dashboard.php" class="nav-link"><i class="fas fa-home"></i><span>Home</span></a>
        <a href="admin-room.php" class="nav-link active"><i class="fas fa-building"></i> <span>Room Manager</span></a>
        <a href="admin-visitor_log.php" class="nav-link"><i class="fas fa-user-check"></i> <span>Visitor log</span></a>
        <a href="admin-monitoring.php" class="nav-link"><i class="fas fa-eye"></i> <span>Monitoring</span></a>

        <a href="staff-chat.php" class="nav-link"><i class="fas fa-comments"></i> <span>Chat</span></a>

        <a href="rent_payment.php" class="nav-link"><i class="fas fa-money-bill-alt"></i> <span>Rent Payment</span></a>

        </div>
        <div class="logout">
        <a href="../config/user-logout.php" id="logoutLink">
            <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
        </a>
        </div>
        <script>
    document.getElementById('logoutLink').addEventListener('click', function(event) {
        event.preventDefault(); // Prevent the default link behavior
        const logoutUrl = this.href; // Store the logout URL

        Swal.fire({
            title: 'Are you sure?',
            text: "You want to log out?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, log me out!'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Logging out...',
                    text: 'Please wait while we log you out.',
                    allowOutsideClick: false,
                    onBeforeOpen: () => {
                        Swal.showLoading(); // Show loading indicator
                    },
                    timer: 2000, // Auto-close after 2 seconds
                    timerProgressBar: true, // Show progress bar
                    willClose: () => {
                        window.location.href = logoutUrl; // Redirect to logout URL
                    }
                });
            }
        });
    });
    </script>
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
                <div class="col-lg-6 col-md-6 mb-4">
                    <div class="card dashboard-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="card-icon bg-primary">
                                    <i class="fas fa-building fa-2x text-white"></i>
                                </div>
                                <div class="stat-card-info text-right">
                                    <h6 class="text-muted mb-2">Total Rooms</h6>
                                    <h2 class="mb-0 font-weight-bold"><?php echo $roomCount; ?></h2>
                                </div>
                            </div>
                            <div class="card-details mt-3">
                                <p class="text-muted mb-2">Manage and view the list of rooms</p>
                                <a href="roomlist.php" class="btn btn-primary btn-sm">Manage <i class="fas fa-arrow-right ml-1"></i></a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Room Assign Card -->
                <div class="col-lg-6 col-md-6 mb-4">
                    <div class="card dashboard-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="card-icon bg-success">
                                    <i class="fas fa-key fa-2x text-white"></i>
                                </div>
                                <div class="stat-card-info text-right">
                                    <h6 class="text-muted mb-2">Assigned Rooms</h6>
                                    <h2 class="mb-0 font-weight-bold"><?php echo $assignedRooms; ?></h2>
                                </div>
                            </div>
                            <div class="card-details mt-3">
                                <p class="text-muted mb-2">Assign rooms to users</p>
                                <a href="room-assign.php" class="btn btn-success btn-sm">Manage <i class="fas fa-arrow-right ml-1"></i></a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Reassign Room Card -->
                <div class="col-lg-6 col-md-6 mb-4">
                    <div class="card dashboard-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="card-icon bg-warning">
                                    <i class="fas fa-exchange-alt fa-2x text-white"></i>
                                </div>
                                <div class="stat-card-info text-right">
                                    <h6 class="text-muted mb-2">Pending Reassignments</h6>
                                    <h2 class="mb-0 font-weight-bold"><?php echo $pendingApplications; ?></h2>
                                </div>
                            </div>
                            <div class="card-details mt-3">
                                <p class="text-muted mb-2">Room reassignment requests</p>
                                <a href="application-room.php" class="btn btn-warning btn-sm">Manage <i class="fas fa-arrow-right ml-1"></i></a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Move Out Management Card -->
                <div class="col-lg-6 col-md-6 mb-4">
                    <div class="card dashboard-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="card-icon bg-danger">
                                    <i class="fas fa-door-open fa-2x text-white"></i>
                                </div>
                                <div class="stat-card-info text-right">
                                    <h6 class="text-muted mb-2">Pending Move-outs</h6>
                                    <h2 class="mb-0 font-weight-bold"><?php echo $pendingMoveOuts; ?></h2>
                                </div>
                            </div>
                            <div class="card-details mt-3">
                                <p class="text-muted mb-2">Manage move-out requests</p>
                                <a href="manage_move_out.php" class="btn btn-danger btn-sm">Manage <i class="fas fa-arrow-right ml-1"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
    .dashboard-card {
        border: none;
        border-radius: 15px;
        box-shadow: 0 0 20px rgba(0,0,0,0.1);
        transition: transform 0.2s;
    }

    .dashboard-card:hover {
        transform: translateY(-5px);
    }

    .card-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .bg-primary { background: #4e73df !important; }
    .bg-success { background: #1cc88a !important; }
    .bg-warning { background: #f6c23e !important; }
    .bg-danger { background: #e74a3b !important; }

    .stat-card-info h2 {
        font-size: 2rem;
        color: #5a5c69;
    }

    .btn-primary {
        background: #4e73df;
        border: none;
        padding: 8px 15px;
        border-radius: 8px;
    }

    .btn-success {
        background: #1cc88a;
        border: none;
        padding: 8px 15px;
        border-radius: 8px;
    }

    .btn-warning {
        background: #f6c23e;
        border: none;
        padding: 8px 15px;
        border-radius: 8px;
        color: white;
    }

    .btn-danger {
        background: #e74a3b;
        border: none;
        padding: 8px 15px;
        border-radius: 8px;
    }

    .btn-primary:hover { background: #2e59d9; }
    .btn-success:hover { background: #169b6b; }
    .btn-warning:hover { background: #dfa826; }
    .btn-danger:hover { background: #be3c30; }

    .text-muted {
        color: #858796 !important;
    }

    .card-details {
        border-top: 1px solid rgba(0,0,0,0.05);
        padding-top: 15px;
    }

    .stat-card-info h6 {
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    </style>

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
