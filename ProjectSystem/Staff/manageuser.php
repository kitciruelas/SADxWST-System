<?php
session_start();

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.html");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="Css_user/usermanage.css"> <!-- Link to the CSS file -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="menu" id="hamburgerMenu">
            <i class="fas fa-bars"></i> <!-- Hamburger menu icon -->
        </div>
        <div class="sidebar-nav">
            <a href="user-dashboard.php" class="nav-link"><i class="fas fa-user"></i> <span>Profile</span></a>
            <a href="#" class="nav-link"><i class="fas fa-users"></i> <span>Manage User</span></a>
        </div>
        <div class="logout">
            <a href="../config/user-logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
        </div>
    </div>

    <!-- Top bar -->
    <div class="topbar">
        <h2>Welcome to Dormio, <?php echo htmlspecialchars($_SESSION["username"]); ?>!</h2>
        <div class="logo"></div>
    </div>

    <!-- Main content -->
    <div class="main-content">
        <h2>Manage Users</h2>
        <p>Content goes here...</p>
    </div>

    <<script>
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
</script>

</body>
</html>



