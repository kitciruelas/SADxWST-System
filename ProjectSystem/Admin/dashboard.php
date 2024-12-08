<?php
session_start();
include '../config/config.php'; // or require 'config.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: index.php");
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




// ... existing code ...

// Display only active and displayed announcements from the database in descending order by date
$sql = "SELECT * FROM announce WHERE is_displayed = 1 AND archive_status = 'active' ORDER BY date_published DESC";
$result = mysqli_query($conn, $sql);

// ... existing code ...

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

// Assuming $conn is your database connection
$sql = "SELECT COUNT(*) AS checkins_today FROM presencemonitoring WHERE DATE(check_in) = CURDATE()";
$result = $conn->query($sql);
$checkins_today = 0;

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $checkins_today = $row['checkins_today'];
}

// Calculate percentage change
//$change = $checkins_yesterday > 0 ? (($checkins_today - $checkins_yesterday) / $checkins_yesterday) * 100 : 0;
//$arrow = $change > 0 ? 'fa-arrow-up text-success' : ($change < 0 ? 'fa-arrow-down text-danger' : 'fa-minus text-muted');
//$percentage_change = abs($change); // Use absolute value for display

// SQL to count rooms by status
$query = "
    SELECT 
        status, 
        COUNT(*) AS room_count 
    FROM rooms 
    GROUP BY status
";

// Run your database query and fetch the data
$result = mysqli_query($conn, $query);

// Initialize the counters
$available_rooms = 0;
$occupied_rooms = 0;
$maintenance_rooms = 0;

// Loop through the results to assign the counts
while ($row = mysqli_fetch_assoc($result)) {
    if ($row['status'] == 'available') {
        $available_rooms = $row['room_count'];
    } elseif ($row['status'] == 'occupied') {
        $occupied_rooms = $row['room_count'];
    } elseif ($row['status'] == 'maintenance') {
        $maintenance_rooms = $row['room_count'];
    }
}

// Initialize arrays for months and total revenue
$months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
$total_revenue = array_fill(0, 12, 0);  // Default all months to 0 revenue

// Query to get the total revenue for each month (Paid and Overdue)
$query = "SELECT YEAR(payment_date) AS year, MONTH(payment_date) AS month, SUM(amount) AS total_revenue
          FROM rentpayment
          WHERE status  
          GROUP BY YEAR(payment_date), MONTH(payment_date)
          ORDER BY YEAR(payment_date), MONTH(payment_date)";

// Execute query and fetch results
$result = mysqli_query($conn, $query);

// Fill the revenue array with actual data
while ($row = mysqli_fetch_assoc($result)) {
    $month = $row['month'] - 1;  // Convert to 0-based index (January = 0)
    $total_revenue[$month] = $row['total_revenue'];  // Set the revenue for that month
}


?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home</title>
    <link rel="icon" href="../img-icon/logo.png" type="image/png">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap CSS -->
<link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">

<!-- FontAwesome for Icons -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Bootstrap Bundle JS (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom CSS -->
<link rel="stylesheet" href="../Admin/Css_Admin/style.css"> 
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="menu" id="hamburgerMenu">
            <i class="fas fa-bars"></i> <!-- Hamburger menu icon -->
        </div>

        <div class="sidebar-nav">
            <a href="#" class="nav-link active" ><i class="fas fa-home"></i> <span>Home</span></a>
            <a href="manageuser.php" class="nav-link"><i class="fas fa-users"></i> <span>Manage User</span></a>
            <a href="admin-room.php" class="nav-link"><i class="fas fa-building"></i> <span>Room Management</span></a>
            <a href="admin-visitor_log.php" class="nav-link"><i class="fas fa-address-book"></i> <span>Log Visitor</span></a>
            <a href="admin-monitoring.php" class="nav-link"><i class="fas fa-eye"></i> <span>Presence Monitoring</span></a>
            <a href="admin-chat.php" class="nav-link"><i class="fas fa-comments"></i> <span>Group Chat</span></a>
            <a href="rent_payment.php" class="nav-link"><i class="fas fa-money-bill-alt"></i> <span>Rent Payment</span></a>
            <a href="activity-logs.php" class="nav-link"><i class="fas fa-clipboard-list"></i> <span>Activity Logs</span></a>
        
 
        </div>
        
        <div class="logout">
        <a href="../config/logout.php" id="logoutLink">
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
        <h2>Welcome to Dormio, <?php echo htmlspecialchars($_SESSION["username"]); ?>!</h2>

        <a href="admin-profile.php" class="profile-btn">
            <i class="fas fa-user"></i>
            <span>Profile</span>
        </a>
</a><style>
      /* Update existing topbar styles if needed */
      .topbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 30px;
        background: white;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
          /* Profile Button Styling */
    .profile-btn {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        background: linear-gradient(135deg, #3498db, #2980b9);
        color: white;
        border-radius: 20px;
        text-decoration: none;
        transition: all 0.3s ease;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }

    .profile-btn:hover {
        background: linear-gradient(135deg, #2980b9, #2573a7);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        color: white;
        text-decoration: none;
    }

    .profile-btn i {
        font-size: 1.1rem;
    }

    .profile-btn span {
        font-weight: 500;
        font-size: 0.95rem;
    }
    }
</style>

      

</div>


    </div>
                <!-- ANNOUNCEMENT -->

    
    <div class="main-content">
    <div class="container-fluid p-4">

<!-- Announcements Section -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h2 class="mb-0"><i class="fas fa-bullhorn"></i> Announcements</h2>
                <a href="announcement.php" class="btn btn-light btn-sm">
                    <i class="fas fa-bell"></i> See All
                </a>
            </div>
            <div class="card-body">
                <?php if (!empty($announcements)): ?>
                    <?php foreach ($announcements as $announcement): ?>
                        <div class="announcement-item mb-3 p-3 border-bottom">
                            <div class="d-flex justify-content-between align-items-start">
                                <h4 class="text-primary mb-2"><?= htmlspecialchars($announcement['title']) ?></h4>
                                <small class="text-muted">
                                    <?= date('M d, Y', strtotime($announcement['date_published'])) ?>
                                </small>
                            </div>
                            <p class="mb-0"><?= nl2br(htmlspecialchars($announcement['content'])) ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-info-circle fa-2x text-muted mb-3"></i>
                        <p class="text-muted">No announcements to display.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Analytics Overview Cards -->
<div class="row g-4 mb-4">
    <!-- Total Revenue Card -->
    <div class="col-md-4">
        <div class="analytics-card">
            <div class="card-header">
                <h6>Total Revenue</h6>
                <span class="stat-change positive">
                    <i class="fas fa-arrow-up"></i> 
                    <?php 
                        $current_month = date('n') - 1;
                        $prev_month = ($current_month - 1 + 12) % 12;
                        $change = $total_revenue[$prev_month] > 0 
                            ? (($total_revenue[$current_month] - $total_revenue[$prev_month]) / $total_revenue[$prev_month] * 100)
                            : 0;
                        echo number_format(abs($change), 1) . '%';
                    ?>
                </span>
            </div>
            <div class="card-value">
                ₱<?php echo number_format(array_sum($total_revenue), 2); ?>
            </div>
            <div class="card-footer">
                <span class="period">Last 30 days</span>
                <i class="fas fa-chart-line icon"></i>
            </div>
        </div>
    </div>

    <!-- Room Status Card -->
    <div class="col-md-4">
        <div class="analytics-card">
            <div class="card-header">
                <h6>Room Occupancy</h6>
                <span class="stat-change <?php echo $occupied_rooms > ($available_rooms + $maintenance_rooms) / 2 ? 'positive' : 'neutral'; ?>">
                    <i class="fas fa-building"></i> 
                    <?php 
                        $total_rooms = $available_rooms + $occupied_rooms + $maintenance_rooms;
                        echo number_format(($occupied_rooms / $total_rooms) * 100, 1) . '%';
                    ?>
                </span>
            </div>
            <div class="card-value">
                <?php echo $occupied_rooms; ?><span class="total-rooms">/ <?php echo $total_rooms; ?></span>
            </div>
            <div class="card-footer">
                <span class="period">Occupied Rooms</span>
                <i class="fas fa-door-open icon"></i>
            </div>
        </div>
    </div>

    <!-- Check-ins Card -->
    <div class="col-md-4">
        <div class="analytics-card">
            <div class="card-header">
                <h6>Today's Check-ins</h6>
                <span class="stat-change neutral">
                    <i class="fas fa-clock"></i> Active
                </span>
            </div>
            <div class="card-value">
                <?php echo $checkins_today; ?>
            </div>
            <div class="card-footer">
                <span class="period">As of <?php echo date('h:i A'); ?></span>
                <i class="fas fa-user-check icon"></i>
            </div>
        </div>
    </div>
</div>

<style>
    
.analytics-card {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    height: 100%;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.analytics-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
}

.analytics-card .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.analytics-card .card-header h6 {
    color: #64748b;
    font-size: 0.875rem;
    font-weight: 600;
    margin: 0;
}

.analytics-card .card-value {
    font-size: 2rem;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 1rem;
    display: flex;
    align-items: baseline;
    gap: 0.5rem;
}

.analytics-card .total-rooms {
    font-size: 1.25rem;
    color: #94a3b8;
    font-weight: 500;
}

.analytics-card .card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.analytics-card .period {
    font-size: 0.875rem;
    color: #64748b;
}

.analytics-card .icon {
    font-size: 1.25rem;
    color: #64748b;
    opacity: 0.8;
}

.stat-change {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.375rem;
}

.stat-change.positive {
    background: rgba(34, 197, 94, 0.1);
    color: #16a34a;
}

.stat-change.negative {
    background: rgba(239, 68, 68, 0.1);
    color: #dc2626;
}

.stat-change.neutral {
    background: rgba(59, 130, 246, 0.1);
    color: #2563eb;
}

.stat-change i {
    font-size: 0.75rem;
}
</style>
<!-- Row 1: Statistics Cards -->
<div class="row g-4 mb-4">
    <!-- Total Users Card -->
    <div class="col-lg-4 col-md-6">
        <div class="stat-card">
            <div class="stat-icon bg-primary-subtle">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-details">
                <div class="stat-number">
                    <h3><?php echo number_format($userCount); ?></h3>
                    <span class="badge bg-primary-subtle text-primary">
                        <i class="fas fa-chart-line"></i> Total Users
                    </span>
                </div>
                <p class="stat-text">Registered users in the system</p>
                <div class="stat-actions">
                    <div class="progress mb-2">
                        <div class="progress-bar bg-primary" role="progressbar" 
                             style="width: <?php echo min(($userCount / 100) * 100, 100); ?>%">
                        </div>
                    </div>
                    <a href="manageuser.php" class="btn btn-link text-primary p-0">
                        View Details <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Staff Card -->
    <div class="col-lg-4 col-md-6">
        <div class="stat-card">
            <div class="stat-icon bg-success-subtle">
                <i class="fas fa-user-tie"></i>
            </div>
            <div class="stat-details">
                <div class="stat-number">
                    <h3><?php echo number_format($staffCount); ?></h3>
                    <span class="badge bg-success-subtle text-success">
                        <i class="fas fa-user-check"></i> Active Staff
                    </span>
                </div>
                <p class="stat-text">Currently active staff members</p>
                <div class="stat-actions">
                    <div class="progress mb-2">
                        <div class="progress-bar bg-success" role="progressbar" 
                             style="width: <?php echo min(($staffCount / 100) * 100, 100); ?>%">
                        </div>
                    </div>
                    <a href="manageuser.php" class="btn btn-link text-success p-0">
                        View Details <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Visitor Log Card -->
    <div class="col-lg-4 col-md-6">
        <div class="stat-card">
            <div class="stat-icon bg-info-subtle">
                <i class="fas fa-clipboard-list"></i>
            </div>
            <div class="stat-details">
                <div class="stat-number">
                    <h3><?php echo number_format($visitorCount); ?></h3>
                    <span class="badge bg-info-subtle text-info">
                        <i class="fas fa-history"></i> Visitor Entries
                    </span>
                </div>
                <p class="stat-text">Total visitor logs recorded</p>
                <div class="stat-actions">
                    <div class="progress mb-2">
                        <div class="progress-bar bg-info" role="progressbar" 
                             style="width: <?php echo min(($visitorCount / 100) * 100, 100); ?>%">
                        </div>
                    </div>
                    <a href="admin-visitor_log.php" class="btn btn-link text-info p-0">
                        View Details <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Updated Carousel Style -->
<style>
/* Statistics Cards */
.stat-card {
    background: #fff;
    border-radius: 16px;
    padding: 1.5rem;
    position: relative;
    transition: all 0.3s ease;
    border: 1px solid rgba(0,0,0,0.08);
    height: 100%;
    display: flex;
    align-items: flex-start;
    gap: 1.5rem;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 24px rgba(0,0,0,0.1);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.stat-icon i {
    opacity: 0.8;
}

.stat-details {
    flex: 1;
}

.stat-number {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 0.5rem;
}

.stat-number h3 {
    font-size: 1.75rem;
    font-weight: 600;
    margin: 0;
}

.stat-text {
    color: #6c757d;
    margin-bottom: 1rem;
    font-size: 0.9rem;
}

.stat-actions {
    margin-top: auto;
}

.progress {
    height: 6px;
    border-radius: 3px;
    background-color: #e9ecef;
}

.progress-bar {
    border-radius: 3px;
}

.btn-link {
    text-decoration: none;
    font-weight: 500;
    font-size: 0.9rem;
}

.btn-link:hover {
    text-decoration: underline;
}

/* Feedback Carousel Fix */
#feedbackCarousel .carousel-inner {
    height: auto !important;
}

.carousel-item {
    transition: transform 0.6s ease-in-out;
}

.feedback-card {
    height: 100%;
    display: flex;
    flex-direction: column;
}

.feedback-content {
    flex: 1;
}

/* Custom Background Colors */
.bg-primary-subtle {
    background-color: rgba(13, 110, 253, 0.1);
}

.bg-success-subtle {
    background-color: rgba(25, 135, 84, 0.1);
}

.bg-info-subtle {
    background-color: rgba(13, 202, 240, 0.1);
}

/* Badge Styles */
.badge {
    font-weight: 500;
    padding: 0.5em 0.8em;
    font-size: 0.75rem;
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .stat-card {
        padding: 1rem;
        gap: 1rem;
    }

    .stat-icon {
        width: 48px;
        height: 48px;
        font-size: 20px;
    }

    .stat-number h3 {
        font-size: 1.5rem;
    }
}
</style>

<script>
// Remove fixed height from carousel
document.addEventListener('DOMContentLoaded', function() {
    const carousel = document.querySelector('#feedbackCarousel .carousel-inner');
    if (carousel) {
        carousel.style.height = 'auto';
    }
});
</script>

<!-- Charts Row -->
<div class="row g-4 mb-4">
    <!-- Check-ins by Time -->
    <div class="col-md-6">
        <div class="chart-card">
            <div class="chart-header">
                <h5>Check-ins by Time</h5>
                <div class="chart-legend">
                    <span class="legend-item">0</span>
                    <div class="legend-gradient"></div>
                    <span class="legend-item">Max</span>
                </div>
            </div>
            <div class="chart-body">
                <canvas id="checkinsHeatmap" height="300"></canvas>
            </div>
        </div>
    </div>

    <!-- Transaction Activity -->
    <div class="col-md-6">
        <div class="chart-card">
            <div class="chart-header">
                <h5>Monthly Revenue Trend</h5>
                <div class="chart-actions">
                    <button class="btn btn-icon" onclick="toggleChartType('revenueChart')">
                        <i class="fas fa-ellipsis-v"></i>
                    </button>
                </div>
            </div>
            <div class="chart-body">
                <canvas id="revenueChart" height="300"></canvas>
            </div>
        </div>
    </div>
</div>

<style>
.stat-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    margin-bottom: 20px;
    transition: transform 0.2s;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-title {
    color: #666;
    font-size: 14px;
    margin-bottom: 8px;
    font-weight: 500;
}

.stat-value-container {
    display: flex;
    align-items: baseline;
    gap: 8px;
    margin-bottom: 8px;
}

.currency {
    font-size: 20px;
    font-weight: 500;
    color: #4e73df;
}

.stat-value {
    font-size: 28px;
    font-weight: 600;
    color: #2c3e50;
}

.stat-label {
    font-size: 18px;
    color: #95a5a6;
}

.stat-change {
    font-size: 14px;
    padding: 4px 12px;
    border-radius: 20px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.stat-change.positive {
    background: rgba(52, 199, 89, 0.1);
    color: #34c759;
}

.stat-change.negative {
    background: rgba(255, 59, 48, 0.1);
    color: #ff3b30;
}

.stat-period {
    color: #95a5a6;
    font-size: 12px;
}

.chart-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    margin-bottom: 20px;
}

.chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.chart-header h5 {
    color: #2c3e50;
    margin: 0;
}

.chart-legend {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    color: #666;
}

.legend-gradient {
    width: 100px;
    height: 6px;
    background: linear-gradient(to right, #f3f4f6, #4e73df);
    border-radius: 3px;
}

.chart-actions .btn-icon {
    border: none;
    background: none;
    padding: 4px 8px;
    color: #666;
    cursor: pointer;
}

.chart-body {
    position: relative;
    height: 300px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Revenue Chart
    const revenueCtx = document.getElementById('revenueChart').getContext('2d');
    const revenueChart = new Chart(revenueCtx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($months); ?>,
            datasets: [{
                label: 'Revenue (₱)',
                data: <?php echo json_encode($total_revenue); ?>,
                borderColor: '#4e73df',
                backgroundColor: 'rgba(78, 115, 223, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        drawBorder: false
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });

    // Check-ins Heatmap using existing data
    const checkinsCtx = document.getElementById('checkinsHeatmap').getContext('2d');
    const checkinsChart = new Chart(checkinsCtx, {
        type: 'bar',
        data: {
            labels: ['Today'],
            datasets: [{
                label: 'Check-ins',
                data: [<?php echo $checkins_today; ?>],
                backgroundColor: '#4e73df',
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        drawBorder: false
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
});

function toggleChartType(chartId) {
    const chart = Chart.getChart(chartId);
    chart.config.type = chart.config.type === 'line' ? 'bar' : 'line';
    chart.update();
}
</script>


<!-- Feedback Section -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <h2 class="mb-0"><i class="fas fa-comment-dots"></i> Room Feedback</h2>
                <a href="view-feedback.php" class="btn btn-light btn-sm">
                    <i class="fas fa-external-link-alt"></i> View All
                </a>
            </div>
            <div id="feedbackCarousel" class="carousel slide" data-bs-ride="carousel">
                <div class="carousel-inner p-4">
                    <?php
                  $sql = "SELECT f.id AS feedback_id, r.room_number, CONCAT(u.fname, ' ', u.lname) AS resident_name, 
                  f.feedback, f.submitted_at
           FROM rooms r
           LEFT JOIN roomassignments ra ON r.room_id = ra.room_id
           LEFT JOIN users u ON ra.user_id = u.id
           INNER JOIN roomfeedback f ON u.id = f.user_id
           WHERE f.archive_status = 'active'  -- Only select active feedback
           ORDER BY f.submitted_at DESC
           LIMIT 6";

                    $result = $conn->query($sql);

                    if ($result && $result->num_rows > 0) {
                        $active = true;
                        $counter = 0;

                        while ($row = $result->fetch_assoc()) {
                            if ($counter % 3 === 0) {
                                echo '<div class="carousel-item ' . ($active ? 'active' : '') . '">';
                                echo '<div class="row">';
                                $active = false;
                            }

                            echo '<div class="col-md-4 mb-3">';
                            echo '<div class="card h-100 border-0 shadow-sm">';
                            echo '<div class="card-body">';
                            echo '<div class="d-flex justify-content-between align-items-start mb-2">';
                            echo '<h5 class="card-title text-primary mb-0">' . htmlspecialchars($row['resident_name']) . '</h5>';
                            echo '</div>';
                            echo '<p class="card-text small text-muted mb-2">Room ' . htmlspecialchars($row['room_number']) . '</p>';
                            echo '<p class="card-text">' . htmlspecialchars($row['feedback']) . '</p>';
                            echo '<p class="card-text"><small class="text-muted">' . date('M d, Y', strtotime($row['submitted_at'])) . '</small></p>';
                            echo '</div></div></div>';

                            $counter++;
                            if ($counter % 3 === 0 || $counter === $result->num_rows) {
                                echo '</div></div>';
                            }
                        }
                    } else {
                        echo '<div class="carousel-item active"><div class="text-center py-4">';
                        echo '<i class="fas fa-comment-slash fa-2x text-muted mb-3"></i>';
                        echo '<p class="text-muted">No feedback available.</p>';
                        echo '</div></div>';
                    }
                    ?>
                </div>
                
                <?php if ($result && $result->num_rows > 3): ?>
                    <button class="carousel-control-prev" type="button" data-bs-target="#feedbackCarousel" data-bs-slide="prev">
                        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Previous</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#feedbackCarousel" data-bs-slide="next">
                        <span class="carousel-control-next-icon" aria-hidden="true"></span>
                        <span class="visually-hidden">Next</span>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
    #feedbackCarousel {
    position: relative;
}

.carousel-control-prev, .carousel-control-next {
    position: absolute;
    top: 50%;
    transform: translateY(-50%); /* Center the controls vertically */
    z-index: 5; /* Ensure controls are above carousel items */
    border: none; /* Remove border */
    padding: 10px;
    background: none; /* No background */
}

.carousel-control-prev {
    left: 0; /* Position the previous button to the left side */
}

.carousel-control-next {
    right: 0; /* Position the next button to the right side */
}

.carousel-control-prev-icon, .carousel-control-next-icon {
    font-size: 2rem; /* Adjust icon size */
    color: white; /* Optional: change icon color */
}

    .carousel-item {
    transition: transform 0.6s ease-in-out;
}

.card {
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    height: 100%; /* Ensures consistent height for all cards */
}

.carousel-control-prev-icon,
.carousel-control-next-icon {
    background-color: rgba(0, 0, 0, 0.6);
    border-radius: 50%;
}

.carousel-inner {
    padding: 20px;
}

.card-title {
    font-weight: bold;
}

.card-text {
    font-size: 0.9rem;
}

.announcement-item:last-child {
    border-bottom: none !important;
}

.announcement-item:hover {
    background-color: rgba(0,0,0,0.01);
}

.carousel-control-prev,
.carousel-control-next {
    width: 40px;
    height: 40px;
    background-color: rgba(0,0,0,0.2);
    border-radius: 50%;
    top: 50%;
    transform: translateY(-50%);
}

.carousel-control-prev { left: -20px; }
.carousel-control-next { right: -20px; }

.badge {
    padding: 0.5em 0.8em;
    font-weight: 500;
}
</style>

        
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>


    <!-- Script -->

    <script>

document.addEventListener('DOMContentLoaded', function () {
    // Auto-slide interval (optional: 5 seconds)
    const feedbackCarousel = document.querySelector('#feedbackCarousel');
    const carousel = new bootstrap.Carousel(feedbackCarousel, {
        interval: 5000, // Auto-slide every 5 seconds
        pause: 'hover', // Pause when the user hovers over the carousel
        wrap: true,     // Loop back to the first slide after the last
    });

    // Adjust the height of the carousel dynamically to match the tallest card in the current slide
    function adjustCarouselHeight() {
        const activeSlide = document.querySelector('.carousel-item.active');
        if (activeSlide) {
            const cards = activeSlide.querySelectorAll('.card');
            let maxHeight = 0;
            cards.forEach(card => {
                maxHeight = Math.max(maxHeight, card.offsetHeight);
            });
            feedbackCarousel.querySelector('.carousel-inner').style.height = `${maxHeight}px`;
        }
    }

    // Trigger height adjustment on slide change
    feedbackCarousel.addEventListener('slid.bs.carousel', adjustCarouselHeight);

    // Initial height adjustment
    adjustCarouselHeight();
});

        // JavaScript to render the graph

        // Data for the chart
// Sample data for the chart
var checkinsData = {
    labels: ['Today'], // Example label for today
    datasets: [{
        label: 'Check-ins',
        data: [<?php echo $checkins_today; ?>], // Data for check-ins today
        backgroundColor: 'rgba(75, 192, 192, 0.2)', // Bar color
        borderColor: 'rgba(75, 192, 192, 1)', // Border color
        borderWidth: 1
    }]
};

// Create the chart
var ctx = document.getElementById('checkinsChart').getContext('2d');
var checkinsChart = new Chart(ctx, {
    type: 'bar', // Type of chart, can also be 'line'
    data: checkinsData,
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

        // Function to fetch check-ins count for today
const fetchCheckinsToday = () => {
    fetch('dashboard.php')
        .then(response => response.json())
        .then(data => {
            document.getElementById('checkinsToday').textContent = data.checkins_today;
        })
        .catch(error => console.error('Error fetching check-ins:', error));
};

// Call fetchCheckinsToday when the page loads
window.onload = () => {
    initCharts();
    fetchCheckinsToday();
};

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



