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
    <title>Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap CSS -->
<link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">

<!-- FontAwesome for Icons -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>


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
            <a href="#" class="nav-link active" ><i class="fas fa-home"></i> <span>Home</span></a>
            <a href="manageuser.php" class="nav-link"><i class="fas fa-users"></i> <span>Manage User</span></a>
            <a href="admin-room.php" class="nav-link"><i class="fas fa-building"></i> <span>Room Manager</span></a>
            <a href="admin-visitor_log.php" class="nav-link"><i class="fas fa-address-book"></i> <span>Log Visitor</span></a>
            <a href="admin-monitoring.php" class="nav-link"><i class="fas fa-eye"></i> <span>Monitoring</span></a>
            <a href="admin-chat.php" class="nav-link"><i class="fas fa-comments"></i> <span>Group Chat</span></a>
            <a href="rent_payment.php" class="nav-link"><i class="fas fa-money-bill-alt"></i> <span>Rent Payment</span></a>
            <a href="activity-logs.php" class="nav-link"><i class="fas fa-clipboard-list"></i> <span>Activity Logs</span></a>
        

        </div>
        
        <div class="logout">
        <a href="../config/logout.php" onclick="return confirmLogout();">
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
        <h2>Welcome to Dormio, <?php echo htmlspecialchars($_SESSION["username"]); ?>!</h2>

    <!-- Button to trigger modal with dynamic data -->
    <a href="admin-profile.php" class="editUserModal">
    <i class="fa fa-user fa-2x"></i>
</a>

      

</div>


    </div>
                <!-- ANNOUNCEMENT -->

    
    <div class="main-content">
    <div class="container mt-2">

<!-- Announcements Section -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h2><i class="fas fa-bullhorn"></i> Announcements</h2>
            </div>
            <div class="card-body">
                <?php if (!empty($announcements)): ?>
                    <?php foreach ($announcements as $announcement): ?>
                        <div class="announcement-item mb-3">
                            <h3><?= htmlspecialchars($announcement['title']) ?></h3>
                            <p><?= htmlspecialchars($announcement['content']) ?></p>
                            <p class="text-muted">Date Published: <?= htmlspecialchars($announcement['date_published']) ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No announcements to display.</p>
                <?php endif; ?>
                <a href="announcement.php" class="nav-link mt-3"><i class="fas fa-bell"></i> <span>See All Announcements</span></a>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    

<!-- Total Revenue (Bar Chart) -->
<div class="col-lg-4 col-md-6 mb-4">
    <div class="card text-center">
        <div class="card-header bg-primary text-white">
            Monthly Revenue (Rent Payments)
        </div>
        <div class="card-body">
            <!-- Graph - Canvas for Total Revenue (Bar Chart) -->
            <canvas id="totalRevenueChart" width="200" height="200"></canvas>
        </div>
    </div>
</div>

<script>
// Assuming months and total_revenue are populated correctly in PHP
var months = <?php echo json_encode($months); ?>;  // Array of months (e.g., ['January', 'February', ...])
var totalRevenue = <?php echo json_encode($total_revenue); ?>;  // Array of total revenue per month

var totalRevenueData = {
    labels: months,  // Use months as x-axis labels
    datasets: [{
        label: 'Total Revenue (₱)',  // Label for the dataset
        data: totalRevenue,  // Array of total revenue values for each month
        backgroundColor: 'rgba(75, 192, 192, 0.2)',  // Bar color
        borderColor: 'rgba(75, 192, 192, 1)',  // Border color
        borderWidth: 1
    }]
};

// Create the Total Revenue chart (Bar chart)
var totalRevenueCtx = document.getElementById('totalRevenueChart').getContext('2d');
var totalRevenueChart = new Chart(totalRevenueCtx, {
    type: 'bar',  // Bar chart
    data: totalRevenueData,
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,  // Start the Y-axis at 0
                title: {
                    display: true,
                    text: 'Total Revenue (₱)'  // Y-axis label
                }
            }
        }
    }
});

</script>


<!-- Rent Status Chart (Bar chart) -->
<div class="col-lg-4 col-md-6 mb-4">
    <div class="card text-center">
        <div class="card-header bg-primary text-white">
            Room Status
        </div>
        <div class="card-body">
            <!-- Rent Status Icon -->

            <!-- Graph - Canvas for Rent Status (Bar Chart) -->
            <canvas id="rentStatusChart" width="200" height="200"></canvas>
        </div>
    </div>
</div>

<script>
// Data for the Rent Status chart (Bar chart)
var rentStatusData = {
    labels: ['Available', 'Occupied', 'Maintenance'], // Rent status categories
    datasets: [{
        label: 'Rooms',
        data: [<?php echo $available_rooms; ?>, <?php echo $occupied_rooms; ?>, <?php echo $maintenance_rooms; ?>], // Data for each status
        backgroundColor: [
            'rgba(75, 192, 192, 0.2)', // Color for Available
            'rgba(255, 99, 132, 0.2)', // Color for Occupied
            'rgba(255, 159, 64, 0.2)'  // Color for Maintenance
        ],
        borderColor: [
            'rgba(75, 192, 192, 1)', // Border color for Available
            'rgba(255, 99, 132, 1)', // Border color for Occupied
            'rgba(255, 159, 64, 1)'  // Border color for Maintenance
        ],
        borderWidth: 1
    }]
};

// Create the Rent Status chart (Bar chart)
var rentStatusCtx = document.getElementById('rentStatusChart').getContext('2d');
var rentStatusChart = new Chart(rentStatusCtx, {
    type: 'bar', // Bar chart
    data: rentStatusData,
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true // Start Y-axis at 0
            }
        }
    }
});
</script>



<div class="col-lg-4 col-md-6 mb-4">
    <div class="card text-center">
        <div class="card-header bg-primary text-white">
            Check-ins Today
        </div>
        <div class="card-body">
 
            <!-- Graph - Canvas for Chart -->
            <canvas id="checkinsChart" width="200" height="200"></canvas>
        </div>
    </div>
</div>
</div>



<!-- Row 1: Total Users, Active Staff, Visitor Log -->
<div class="row mb-4">
    <!-- Total Users Card -->
    <div class="col-lg-4 col-md-6 mb-4">
        <div class="card text-center">
            <div class="card-body">
                <i class="user fa-3x"><?php echo $userCount; ?></i>
                <h5 class="card-title mt-3">Total Users</h5>
                <p class="card-text">Manage and view the list of users.</p>
                <div class="progress mt-2">
                    <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo min(($userCount / 100) * 100, 100); ?>%;" aria-valuenow="<?php echo $userCount; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <a href="manageuser.php" class="btn btn-primary mt-3">View Details</a>
            </div>
        </div>
    </div>

    <!-- Active Staff Card -->
    <div class="col-lg-4 col-md-6 mb-4">
        <div class="card text-center">
            <div class="card-body">
                <i class="staff fa-3x"><?php echo $staffCount; ?></i>
                <h5 class="card-title mt-3">Active Staff</h5>
                <p class="card-text">Manage and view active staff.</p>
                <div class="progress mt-2">
                    <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo min(($staffCount / 100) * 100, 100); ?>%;" aria-valuenow="<?php echo $staffCount; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <a href="manageuser.php" class="btn btn-primary mt-3">View Details</a>
            </div>
        </div>
    </div>

    <!-- Visitor Log Card -->
    <div class="col-lg-4 col-md-6 mb-4">
        <div class="card text-center">
            <div class="card-body">
                <i class="visit fa-3x"><?php echo $visitorCount; ?> </i>
                <h5 class="card-title mt-3">Visitor Log</h5>
                <p class="card-text">Track visitor entries and activities.</p>
                <div class="progress mt-2">
                    <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo min(($visitorCount / 100) * 100, 100); ?>%;" aria-valuenow="<?php echo $visitorCount; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <a href="admin-visitor_log.php" class="btn btn-primary mt-3">View Details</a>
            </div>
        </div>
    </div>
</div>


<div class="row mb-4">
<div class="col-12">
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h2><i class="fas fa-comment-dots"></i> Feedback</h2>
        </div>
        <div class="card-body">
        <?php
// SQL query to select the first 6 feedback entries, ordered by submission date
$sql = "SELECT f.id AS feedback_id, r.room_number, CONCAT(u.fname, ' ', u.lname) AS resident_name, 
                f.feedback, f.submitted_at
        FROM rooms r
        LEFT JOIN roomassignments ra ON r.room_id = ra.room_id
        LEFT JOIN users u ON ra.user_id = u.id
        INNER JOIN roomfeedback f ON u.id = f.user_id
        ORDER BY f.submitted_at DESC
        LIMIT 6"; // Limit to 6 feedback entries

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    // Start row for layout
    echo "<div class='row'>";
    // Loop through and display each feedback entry
    while ($row = $result->fetch_assoc()) {
        echo "<div class='col-12 col-md-6 col-lg-4 mb-4'>"; // Use col-md-6 for 2-in-1 layout, col-lg-4 for 3 items on large screens
        echo "<div class='card' style='border: 1px solid #e0e0e0; border-radius: 8px;'>";
        echo "<div class='card-body'>";
        echo "<h5 class='card-title'>" . htmlspecialchars($row['resident_name']) . "</h5>"; // Resident Name
        echo "<p class='card-text'><strong>Room:</strong> " . htmlspecialchars($row['room_number']) . "</p>"; // Room Number
        echo "<p class='card-text'><strong>Feedback:</strong> " . htmlspecialchars($row['feedback']) . "</p>"; // Feedback Text
        echo "<p class='card-text'><small class='text-muted'>Submitted on: " . htmlspecialchars($row['submitted_at']) . "</small></p>"; // Submission Date
        echo "</div>"; // End of card-body
        echo "</div>"; // End of card
        
        echo "</div>"; // End of col
    }
    // End row for layout
    echo "</div>";
} else {
    echo "<p>No feedback available.</p>";
}

?>
<a href="view-feedback.php" class="nav-link mt-3"><i class="fas fa-comment-dots"></i> <span>See All Feedback</span></a>

        </div>
        
    </div>
</div>




</div>
</div>



        
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>


    <!-- Script -->

    <script>

        
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



