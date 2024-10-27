<?php
session_start();
include '../config/config.php'; // or require 'config.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: admin-login.php");
    exit;
}
if (!isset($_SESSION['login_time'])) {
    $_SESSION['login_time'] = date('Y-m-d H:i:s'); // Sets the current time on initial login
    
}

// Simulate a username for demonstration purposes

    $userCountQuery = "SELECT COUNT(*) AS totalUsers FROM users"; // Change room to user
    $userCountResult = $conn->query($userCountQuery);
    $userCount = $userCountResult->fetch_assoc()['totalUsers'];

    $userCountQuery = "SELECT COUNT(*) AS totalStaff FROM staff"; // Count staff instead of users
    $userCountResult = $conn->query($userCountQuery);
    $staffCount = $userCountResult->fetch_assoc()['totalStaff']; // Fetching total staff count

    $visitorCountQuery = "SELECT COUNT(*) AS totalVisitors FROM visitors"; // Count visitors instead of staff
    $visitorCountResult = $conn->query($visitorCountQuery);
    $visitorCount = $visitorCountResult->fetch_assoc()['totalVisitors']; // Fetching total visitor count




// SQL query to fetch only displayed announcements
$sql = "SELECT * FROM announce WHERE is_displayed = 1";
$result = mysqli_query($conn, $sql);

$announcements = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $announcements[] = $row; // Collect displayed announcements
    }
}

// Initialize the announcement variable
$announcement = null;

// Check if an announcement ID is provided
if (isset($_GET['id'])) {
    $announcementId = intval($_GET['id']);
    $sql = "SELECT * FROM announce WHERE announcementId = $announcementId";
    $result = mysqli_query($conn, $sql);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $announcement = mysqli_fetch_assoc($result);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap CSS -->
<link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">

<!-- FontAwesome for Icons -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">


<!-- Custom CSS -->
<link rel="stylesheet" href="Css_Admin/dashboard.css">

</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="menu" id="hamburgerMenu">
            <i class="fas fa-bars"></i> <!-- Hamburger menu icon -->
        </div>

        <div class="sidebar-nav">
            <a href="#" class="nav-link active" ><i class="fas fa-user-cog"></i> <span>Admin</span></a>
            <a href="manageuser.php" class="nav-link"><i class="fas fa-users"></i> <span>Manage User</span></a>
            <a href="admin-room.php" class="nav-link"><i class="fas fa-building"></i> <span>Room Manager</span></a>
            <a href="admin-visitor_log.php" class="nav-link"><i class="fas fa-address-book"></i> <span>Log Visitor</span></a>


        </div>
        
        <div class="logout">
            <a href="../config/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
        </div>
    </div>

    <!-- Top bar -->
    <div class="topbar">
        <h2>Welcome to Dormio, <?php echo htmlspecialchars($_SESSION["username"]); ?>!</h2>

        <p>Login time: <?php echo htmlspecialchars($_SESSION['login_time']); ?></p>

      

</div>


    </div>
                <!-- ANNOUNCEMENT -->

    
    <div class="main-content">
        <div class="announcement-box">
            <h2><i class="fas fa-bullhorn announcement-icon"></i> Announcements</h2>
            
            <div class="announcement-container">
    <?php if (!empty($announcements)): ?>
        <?php foreach ($announcements as $announcement): ?>
            <div class="announcement-item">
                <h3><?= htmlspecialchars($announcement['title']) ?></h3>
                <p><?= htmlspecialchars($announcement['content']) ?></p>
                <p>Date Published: <?= htmlspecialchars($announcement['date_published']) ?></p>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No announcements to display.</p>
    <?php endif; ?>

    <a href="announcement.php" class="nav-link"><i class="fas fa-bell"></i> <span>See Announcement</span></a>
    </div>
        </div>

        
     
        <div class="dashboard container">
    <!-- Row for Grid Alignment -->
    <div class="row">
    <div class="col-lg-4 col-md-6 mb-4">
        <div class="card text-center">
            <div class="card-body">
                <span class="badge-status"><?php echo $userCount; ?> Total Users</span>
                <i class="fas fa-user fa-3x"></i>
                <h5 class="card-title mt-3">Total Users</h5>
                <p class="card-text">Manage and view the list of users.</p>
                <div class="progress mt-2">
                    <div class="progress-bar" role="progressbar" style="width: <?php echo ($userCount / 100) * 100; ?>%;" aria-valuenow="<?php echo $userCount; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <a href="manageuser.php" class="btn btn-primary mt-3">View Details</a>
            </div>
        </div>
    </div>

    <div class="col-lg-4 col-md-6 mb-4">
    <div class="card text-center">
        <div class="card-body">
            <span class="badge-status"><?php echo $staffCount; ?> Total Staff</span> <!-- Updated to reflect staff count -->
            <i class="fas fa-user-tie fa-3x"></i>
            <h5 class="card-title mt-3">Active Staff</h5> <!-- Updated title -->
            <p class="card-text">Manage and view active staff.</p> <!-- Updated description -->
            <div class="progress mt-2">
                <div class="progress-bar" role="progressbar" style="width: <?php echo ($staffCount / 100) * 100; ?>%;" aria-valuenow="<?php echo $staffCount; ?>" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <a href="manageuser.php" class="btn btn-primary mt-3">View Details</a> <!-- Updated link if needed -->
        </div>
    </div>
</div>

<div class="col-lg-4 col-md-6 mb-4">
    <div class="card text-center">
        <div class="card-body">
            <span class="badge-status"><?php echo $visitorCount; ?> Total Visitor Log</span>
            <i class="fas fa-user-friends fa-3x"></i>
            <h5 class="card-title mt-3">Visitor Log</h5>
            <p class="card-text">Manage and track visitor entries and activities.</p>
            <div class="progress mt-2">
                <div class="progress-bar" role="progressbar" style="width: <?php echo ($visitorCount / 100) * 100; ?>%;" aria-valuenow="<?php echo $visitorCount; ?>" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
            <a href="manageuser.php" class="btn btn-primary mt-3">View Details</a>
        </div>
    </div>
</div>




        
       
</div>

    <!-- Script -->

    <script>

// Form validation function
function validateForm() {
    let fname = document.getElementById('editFname').value;
    let lname = document.getElementById('editLname').value;
  age = document.getElementById('editAge').value;
    let contact = document.getElementById('editContact').value;
    
    if (!fname || !lname || !age || !contact) {
        alert('Please fill out all required fields.');
        return false;
    }
    
    return true;
}

    
</script>
<script>
    const hamburgerMenu = document.getElementById('hamburgerMenu');
    const sidebar = document.getElementById('sidebar');

    // Initially set sidebar to collapsed
    sidebar.classList.add('collapsed');

    hamburgerMenu.addEventListener('click', function() {
        sidebar.classList.toggle('collapsed');  // Toggle sidebar collapse state
        
        // Change the icon based on the sidebar state
        const icon = hamburgerMenu.querySelector('i');
        if (sidebar.classList.contains('collapsed')) {
            icon.classList.remove('fa-times'); // Change to hamburger icon
            icon.classList.add('fa-bars');
        } else {
            icon.classList.remove('fa-bars'); // Change to close icon
            icon.classList.add('fa-times');
        }
    });
  // Get all sidebar links
  const sidebarLinks = document.querySelectorAll('.sidebar a');

  // Loop through each link
  sidebarLinks.forEach(link => {
    link.addEventListener('click', function() {
      // Remove the active class from all links
      sidebarLinks.forEach(link => link.classList.remove('active'));
      
      // Add the active class to the clicked link
      this.classList.add('active');
    });
  });
  
  
</script>
</body>
</html>



