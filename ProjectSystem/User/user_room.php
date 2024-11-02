<?php
session_start();
include '../config/config.php'; // Ensure correct path to your config file

// Assuming the user is logged in and their ID is stored in $_SESSION['id']
$userId = $_SESSION['id'];

// Fetch the room assignment for the logged-in user
// SQL query to fetch room assignments, including room picture
$query = "
    SELECT ra.assignment_id, r.room_number, r.room_pic, ra.assignment_date
    FROM RoomAssignments ra
    JOIN Rooms r ON ra.room_id = r.room_id
    WHERE ra.user_id = ?
";




$stmt = $conn->prepare($query);
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();

// Check if the user has an assigned room
if ($result->num_rows > 0) {
    $roomAssignment = $result->fetch_assoc();
} else {
    $roomAssignment = null; // No room assigned
}


$conn->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="Css_user/usersdash.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="menu" id="hamburgerMenu">
            <i class="fas fa-bars"></i> <!-- Hamburger menu icon -->
        </div>

        <div class="sidebar-nav">
        <a href="user-dashboard.php" class="nav-link"><i class="fas fa-home"></i><span>Home</span></a>
        <a href="user_room.php" class="nav-link active"><i class="fas fa-key"></i> <span>Room Assign</span></a>
        <a href="visitor_log.php" class="nav-link"><i class="fas fa-user-check"></i> <span>Log Visitor</span></a>

        </div>
        
        <div class="logout">
            <a href="../config/user-logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
        </div>
    </div>

    <!-- Top bar -->
    <div class="topbar">
        <h2>Your Room Assign</h2>

    </div>
    <div class="main-content">      

    <div class="container h-100">
    <div class="row d-flex justify-content-center align-items-center" style="min-height: 100vh;">
        <div class="col-12 col-md-8 col-lg-6">
            <div class="card shadow-sm w-100 mt-3">
                <div class="card-body text-center">
                    <!-- PHP Room Assignment Logic -->
                    <?php if ($roomAssignment): ?>
                        <h5 class="card-title"><strong>Room:</strong> <?php echo htmlspecialchars($roomAssignment['room_number']); ?></h5>
                        <p class="card-text"><strong>Assign Date:</strong> <?php echo htmlspecialchars($roomAssignment['assignment_date']); ?></p>

                        <?php if (!empty($roomAssignment['room_pic'])): ?>
                            <!-- Display the room picture -->
                            <img src="<?php echo htmlspecialchars($roomAssignment['room_pic']); ?>" alt="Room Picture" class="img-fluid rounded" style="max-width: 500px; height: auto;">
                        <?php else: ?>
                            <p>No room picture available.</p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="card-text">You have not been assigned a room yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

      
    </div>






<!-- Include jQuery and Bootstrap JS (required for dropdown) -->
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>

    
    <!-- JavaScript -->
    <script>

        // Sidebar toggle
        const hamburgerMenu = document.getElementById('hamburgerMenu');
        const sidebar = document.getElementById('sidebar');

        sidebar.classList.add('collapsed');
        hamburgerMenu.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            const icon = hamburgerMenu.querySelector('i');
            if (sidebar.classList.contains('collapsed')) {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            } else {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times');
            }
        });
    </script>
</body>
</html>
