<?php
session_start();
include '../config/config.php'; // Ensure correct path to your config file

// Check if the user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: user-login.php");
    exit;
}
date_default_timezone_set('Asia/Manila');

// Check for user ID in session
if (isset($_SESSION['id'])) { 
    $user_id = $_SESSION['id']; 

    // Capture the current login time
    $login_time = date('Y-m-d H:i:s');

    // Prepare and bind the SQL statement
    $stmt = $conn->prepare("UPDATE users SET login_time = ? WHERE id = ?");

    // Check if the statement was prepared successfully
    if ($stmt) {
        // Bind parameters: "si" means string and integer
        $stmt->bind_param("si", $login_time, $user_id);

        // Execute the statement
        if ($stmt->execute()) {
            // Store the login time in the session for display
            $_SESSION['login_time'] = $login_time;

            // Display the login time
            echo "Login time (Philippines): " . htmlspecialchars($_SESSION['login_time']);
        } else {
            echo "Error updating login time: " . htmlspecialchars($stmt->error);
        }

        // Close the statement
        $stmt->close();
    } else {
        echo "Error preparing statement: " . htmlspecialchars($conn->error);
    }
} else {
    echo "User is not logged in.";
}


// Handle user update request (edit user)
if (isset($_POST['edit_user'])) {
    $userId = intval($_POST['user_id']);
    $Fname = trim($_POST['Fname']);
    $Lname = trim($_POST['Lname']);
    $MI = trim($_POST['MI']);
    $Age = intval($_POST['Age']);
    $Address = trim($_POST['Address']);
    $contact = trim($_POST['contact']);
    $Sex = $_POST['Sex'];

    // Prepare the SQL query to update the user
    $stmt = $conn->prepare("UPDATE users SET fname = ?, lname = ?, mi = ?, age = ?, address = ?, contact = ?, sex = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("sssisssi", $Fname, $Lname, $MI, $Age, $Address, $contact, $Sex, $userId);
        if ($stmt->execute()) {
            echo "<script>alert('User updated successfully!');</script>";
        } else {
            echo "<script>alert('Error: " . $stmt->error . "');</script>";
        }
        $stmt->close();
    } else {
        echo "<script>alert('Error preparing statement: " . $conn->error . "');</script>";
    }
}

// Fetch user data from the database to set session variables
if (isset($_SESSION['id'])) {
    $userId = $_SESSION['id'];

    $sql = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        // Set user session variables
        $_SESSION['Fname'] = $user['fname'];
        $_SESSION['Lname'] = $user['lname'];
        $_SESSION['MI'] = $user['mi'];
        $_SESSION['Age'] = $user['age'];
        $_SESSION['Address'] = $user['address'];
        $_SESSION['contact'] = $user['contact'];
        $_SESSION['Sex'] = $user['sex'];
    }
    $stmt->close();
}

// Fetch announcements
$sql = "SELECT * FROM announce WHERE is_displayed = 1";
$announcements = [];
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $announcements[] = $row; // Collect displayed announcements
    }
}

// Fetch specific announcement based on ID
$announcement = null;
if (isset($_GET['id'])) {
    $announcementId = intval($_GET['id']);
    $sql = "SELECT * FROM announce WHERE announcementId = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $announcementId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        $announcement = $result->fetch_assoc();
    }
    $stmt->close();
}

// Query for pending room applications count
$applicationsQuery = "SELECT COUNT(*) AS pendingApplications FROM RoomApplications WHERE status = 'pending'";
$applicationsResult = $conn->query($applicationsQuery);
$pendingApplications = $applicationsResult->fetch_assoc()['pendingApplications'];

// Set limit of records per page for pagination
$limit = 6;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Query to fetch rooms for pagination
$sql = "SELECT room_id, room_number, room_desc, capacity, room_monthlyrent, status, room_pic FROM rooms LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $limit, $offset);
$stmt->execute();
$roomsResult = $stmt->get_result();

// Query to get total number of rooms (for pagination calculation)
$totalRoomsQuery = "SELECT COUNT(*) AS total FROM rooms";
$totalResult = $conn->query($totalRoomsQuery);
$totalRooms = $totalResult->fetch_assoc()['total'];

// Calculate total pages for pagination
$totalPages = ceil($totalRooms / $limit);

// Handle the form submission for room application
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the user_id from the session
    $userId = $_SESSION['id'];

    // Get room_id and comments from the POST request
    $roomId = isset($_POST['room_id']) ? intval($_POST['room_id']) : null;
    $comments = isset($_POST['comments']) ? $_POST['comments'] : '';

    // Validate form data
    if ($roomId) {
        // Check if the user has already applied for this room
        $checkStmt = $conn->prepare("SELECT status FROM RoomApplications WHERE user_id = ? AND room_id = ?");
        $checkStmt->bind_param('ii', $userId, $roomId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {
            // Fetch the current status of the application
            $application = $checkResult->fetch_assoc();
            $status = $application['status'];

            // Show different messages based on the status of the application
            if ($status == 'pending') {
                echo "<script>alert('You have already applied for this room, waiting for approval.');</script>";
            } elseif ($status == 'approved') {
                echo "<script>alert('Your application for this room has already been approved.');</script>";
            } elseif ($status == 'rejected') {
                echo "<script>alert('Your application for this room has been rejected.');</script>";
            }
        } else {
            // Check room capacity
            $capacityCheckStmt = $conn->prepare("SELECT capacity, (SELECT COUNT(*) FROM RoomApplications WHERE room_id = ? AND status = 'approved') AS current_occupancy FROM Rooms WHERE room_id = ?");
            $capacityCheckStmt->bind_param('ii', $roomId, $roomId);
            $capacityCheckStmt->execute();
            $capacityResult = $capacityCheckStmt->get_result();

            if ($capacityResult->num_rows > 0) {
                $room = $capacityResult->fetch_assoc();
                $capacity = $room['capacity'];
                $currentOccupancy = $room['current_occupancy'];

                // Check if the room is occupied
                if ($currentOccupancy >= $capacity) {
                    echo "<script>alert('The room is currently occupied.');</script>";
                } else {
                    // Prepare the SQL query to insert the application
                    $stmt = $conn->prepare("INSERT INTO RoomApplications (user_id, room_id, application_date, status, comments) 
                                            VALUES (?, ?, CURDATE(), 'pending', ?)");
                    $stmt->bind_param('iis', $userId, $roomId, $comments);

                    // Execute the query
                    if ($stmt->execute()) {
                        echo "<script>alert('Your application has been submitted successfully.');</script>";
                    } else {
                        echo "<script>alert('Error submitting application: " . $stmt->error . "');</script>";
                    }

                    // Close the statement
                    $stmt->close();
                }
            } else {
                echo "<script>alert('Room not found.');</script>";
            }

            // Close the capacity check statement
            $capacityCheckStmt->close();
        }

        // Close the check statement
        $checkStmt->close();
    } else {
        echo "<script>alert('Invalid room ID.');</script>";
    }
}

// Get the selected filter from the URL, default to an empty string
$filter = isset($_GET['status']) ? $_GET['status'] : '';

$statuses = ['Available', 'Occupied', 'Maintenance'];

// Add WHERE clause if filter is set
if ($filter !== '') {
    $sql .= " WHERE status = '" . mysqli_real_escape_string($conn, $filter) . "'";
}


// Query to fetch rooms from the database with limit and offset for pagination
$sql = "SELECT room_id, room_number, room_desc, capacity, room_monthlyrent, status, room_pic FROM rooms LIMIT $limit OFFSET $offset";
$result = $conn->query($sql);

$conn->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="Css_user/users-dash.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
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
        <a href="#" class="nav-link active"><i class="fas fa-home"></i><span>Home</span></a>
        <a href="user_room.php" class="nav-link"><i class="fas fa-key"></i> <span>Room Assign</span></a>
        </div>
        
        <div class="logout">
            <a href="../config/user-logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
        </div>
    </div>

    <!-- Top bar -->
    <div class="topbar">
        <h2>Welcome to Dormio, <?php echo htmlspecialchars($_SESSION["username"]); ?>!</h2>
        <!-- Button to trigger modal with dynamic data -->
        <?php
        // Set default values for session variables if not set
        $id = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
        $fname = isset($_SESSION['Fname']) ? htmlspecialchars($_SESSION['Fname'], ENT_QUOTES) : '';
        $lname = isset($_SESSION['Lname']) ? htmlspecialchars($_SESSION['Lname'], ENT_QUOTES) : '';
        $mi = isset($_SESSION['MI']) ? htmlspecialchars($_SESSION['MI'], ENT_QUOTES) : '';
        $age = isset($_SESSION['Age']) ? (int)$_SESSION['Age'] : 0;
        $address = isset($_SESSION['Address']) ? htmlspecialchars($_SESSION['Address'], ENT_QUOTES) : '';
        $contact = isset($_SESSION['contact']) ? htmlspecialchars($_SESSION['contact'], ENT_QUOTES) : '';
        $sex = isset($_SESSION['Sex']) ? htmlspecialchars($_SESSION['Sex'], ENT_QUOTES) : '';

        ?>

        <!-- Button to trigger modal with dynamic data -->
<button id="openEditUserModal" class="editUserModal"
    onclick="openEditModal(
        <?php echo $id; ?>, 
        '<?php echo htmlspecialchars(addslashes($fname), ENT_QUOTES); ?>', 
        '<?php echo htmlspecialchars(addslashes($lname), ENT_QUOTES); ?>', 
        '<?php echo htmlspecialchars(addslashes($mi), ENT_QUOTES); ?>', 
        <?php echo $age; ?>, 
        '<?php echo htmlspecialchars(addslashes($address), ENT_QUOTES); ?>', 
        '<?php echo htmlspecialchars(addslashes($contact), ENT_QUOTES); ?>', 
        '<?php echo htmlspecialchars(addslashes($sex), ENT_QUOTES); ?>', 
    )">
    
    <i class="fa fa-user"></i></button>
    


        <!-- Modal Content -->
        <div id="editUserModal" class="modal">
            <div class="addmodal-content">
                <span class="close" onclick="closeEditModal()">&times;</span>
                <h2>Edit Profile</h2>
                <div class="card p-2 shadow-sm">
                <h6 class="mb-0 text-left">
    Login time (Philippines): 
    <?php 
    // Check if login_time is set in the session before displaying it
    echo isset($_SESSION['login_time']) ? htmlspecialchars($_SESSION['login_time']) : 'Not logged in';
    ?>
</h6>
                </div>
                <form method="POST" action="">
                    <input type="hidden" id="editUserId" name="user_id">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="editFname">First Name:</label>
                            <input type="text" id="editFname" name="Fname" required>
                        </div>
                        <div class="form-group">
                            <label for="editLname">Last Name:</label>
                            <input type="text" id="editLname" name="Lname" required>
                        </div>
                        <div class="form-group">
                            <label for="editMI">Middle Initial:</label>
                            <input type="text" id="editMI" name="MI">
                        </div>
                        <div class="form-group">
                            <label for="editAge">Age:</label>
                            <input type="number" id="editAge" name="Age" required>
                        </div>
                        <div class="form-group">
                            <label for="editAddress">Address:</label>
                            <input type="text" id="editAddress" name="Address" required>
                        </div>
                        <div class="form-group">
                            <label for="editContact">Contact Number:</label>
                            <input type="text" id="editContact" name="contact" required pattern="[0-9]{10,11}" title="Please enter a valid contact number (10-11 digits)">
                        </div>
                        <div class="form-group">
                            <label for="editSex">Sex:</label>
                            <select id="editSex" name="Sex">
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        
                    </div>
                    <button type="submit" name="edit_user">Update</button>
                </form>
            </div>
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
            </div>
        </div>
<div class="container">
<!-- Filter Dropdown -->
<div class="filter-dropdown mb-3">
        <label for="statusFilter">Filter by Status:</label>
        <select id="statusFilter" class="form-select" onchange="filterRooms()">
            <option value="">All</option>
            <?php foreach ($statuses as $status): ?>
                <option value="<?php echo htmlspecialchars($status); ?>" <?php if ($filter == $status) echo 'selected'; ?>>
                    <?php echo htmlspecialchars($status); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="row">
        <!-- Check if any rooms are returned -->
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="col-md-4">
                    <div class="room-card">
                        <img src="<?php echo htmlspecialchars($row['room_pic']); ?>" alt="Room Image">
                        <p class="room-price">Rent Price: <?php echo number_format($row['room_monthlyrent'], 2); ?> / Monthly</p>
                        <h5>Room: <?php echo htmlspecialchars($row['room_number']); ?></h5>
                        <p>Capacity: <?php echo htmlspecialchars($row['capacity']); ?> people</p>
                        <p><?php echo htmlspecialchars($row['room_desc']); ?></p>
                        <p>Status: <?php echo htmlspecialchars($row['status']); ?></p>
                        <button class="btn btn-primary apply-btn" 
                                data-room-id="<?php echo $row['room_id']; ?>" 
                                data-room-number="<?php echo htmlspecialchars($row['room_number']); ?>" 
                                data-room-price="<?php echo htmlspecialchars($row['room_monthlyrent']); ?>"
                                data-room-capacity="<?php echo htmlspecialchars($row['capacity']); ?>" 
                                data-room-status="<?php echo htmlspecialchars($row['status']); ?>" 
                                data-toggle="modal" data-target="#applyModal">
                            Apply Now!
                        </button>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No rooms available for the selected status.</p>
        <?php endif; ?>
    </div>
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
    </div>

    <!-- Modal Structure -->
<div class="modal fade" id="applyModal" tabindex="-1" role="dialog" aria-labelledby="applyModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="applyModalLabel">Apply for Room</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="applyForm" action="user-dashboard.php" method="POST">
                    <!-- Hidden input for room_id -->
                    <input type="hidden" id="roomId" name="room_id">

                    <!-- Room Information (populated dynamically) -->
                    <div class="form-group">
                        <p><strong>Room Number:</strong> <span id="modalRoomNumber"></span></p>
                    </div>
                    <div class="form-group">
                        <p><strong>Rent Price:</strong> <span id="modalRoomPrice"></span></p>
                    </div>
                    <div class="form-group">
                        <p><strong>Capacity:</strong> <span id="modalRoomCapacity"></span></p>
                    </div>
                    <div class="form-group">
                        <p><strong>Status:</strong> <span id="modalRoomStatus"></span></p>
                    </div>

                    <!-- Optional Comments -->
                    <div class="form-group">
                        <label for="comments">Comments (optional):</label>
                        <textarea id="comments" name="comments" class="form-control"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">Submit Application</button>
                </form>
            </div>
        </div>
    </div>
</div>





<!-- Include jQuery and Bootstrap JS (required for dropdown) -->
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>

    
    <!-- JavaScript -->
    <script>
     
     // JavaScript for the dropdown filter
     document.getElementById('statusFilter').addEventListener('change', function() {
        const status = this.value;
        // Redirect to the current page with the selected filter
        window.location.href = window.location.pathname + '?status=' + encodeURIComponent(status);
    });

        // When the "Apply Now!" button is clicked
document.querySelectorAll('.apply-btn').forEach(button => {
    button.addEventListener('click', function () {
        // Fetch room data from the data-* attributes
        const roomId = this.getAttribute('data-room-id');
        const roomNumber = this.getAttribute('data-room-number');
        const roomPrice = this.getAttribute('data-room-price');
        const roomCapacity = this.getAttribute('data-room-capacity');
        const roomStatus = this.getAttribute('data-room-status');

        // Populate the modal fields with room data
        document.getElementById('roomId').value = roomId;
        document.getElementById('modalRoomNumber').textContent = roomNumber;
        document.getElementById('modalRoomPrice').textContent = `$${roomPrice}`;
        document.getElementById('modalRoomCapacity').textContent = roomCapacity;
        document.getElementById('modalRoomStatus').textContent = roomStatus;
    });
});


       // Function to open the edit modal and populate the form
        function openEditModal(id, Fname, Lname, MI, Age, Address, contact, Sex, Role) {
            document.getElementById('editUserId').value = id;
            document.getElementById('editFname').value = Fname;
            document.getElementById('editLname').value = Lname;
            document.getElementById('editMI').value = MI;
            document.getElementById('editAge').value = Age;
            document.getElementById('editAddress').value = Address;
            document.getElementById('editContact').value = contact;
            document.getElementById('editSex').value = Sex;

            document.getElementById('editUserModal').style.display = 'flex'; // Show modal
        }

        // Function to close the modal
        function closeEditModal() {
            document.getElementById('editUserModal').style.display = 'none'; // Hide modal
        }

        // Close modal if user clicks outside of it
        window.onclick = function(event) {
            var editUserModal = document.getElementById('editUserModal');
            if (event.target === editUserModal) {
                closeEditModal();
            }
        }


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
