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


// Check if the form is submitted via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Capture form data
    $roomId = isset($_POST['room_id']) ? intval($_POST['room_id']) : null;
    $comments = isset($_POST['comments']) ? trim($_POST['comments']) : '';

    // Basic validation
    if ($roomId) {
        // Sanitize user input
        $comments = htmlspecialchars($comments);

        // Ensure user is logged in
        $userId = $_SESSION['id'] ?? null;
        if (!$userId) {
            echo "<script>alert('User not logged in. Please log in to apply.') ;window.history.back();</script>";
            exit;
        }

        // Fetch the old room ID based on the current user
        $stmt = $conn->prepare("SELECT room_id FROM roomassignments WHERE user_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stmt->bind_result($oldRoomId);
            $stmt->fetch();
            $stmt->close();
        } else {
            echo "<script>alert('Error: Could not prepare the statement to fetch old room ID.');window.history.back();</script>";
            exit;
        }

        // Check if the selected room is the same as the current room
        if ($oldRoomId === $roomId) {
            echo "<script>alert('You are already assigned to this room. Please select a different room.');window.history.back();</script>";
            exit;
        }

        // Check for existing reassignment requests
        $stmt = $conn->prepare("SELECT status FROM room_reassignments WHERE user_id = ? ORDER BY reassignment_date DESC LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stmt->bind_result($existingStatus);
            $stmt->fetch();
            $stmt->close();

            // Allow reassignment if the last request was approved or rejected
            if ($existingStatus === 'pending') {
                echo "<script>alert('You already have a reassignment request waiting for approval.');window.history.back();</script>";
                exit;
            }
        } else {
            echo "<script>alert('Error: Could not prepare the statement to check existing requests.');window.history.back();</script>";
            exit;
        }

        // If no comments provided, set it to "No comment"
        if (empty($comments)) {
            $comments = "No comment";
        }

        // Insert into the database
        $stmt = $conn->prepare("INSERT INTO room_reassignments (new_room_id, old_room_id, user_id, comment, reassignment_date, status) VALUES (?, ?, ?, ?, NOW(), 'pending')");
        if ($stmt) {
            $stmt->bind_param("iiis", $roomId, $oldRoomId, $userId, $comments);

            if ($stmt->execute()) {
                echo "<script>alert('Application submitted successfully!');</script>";
                echo "<script>window.location.href = '" . $_SERVER['PHP_SELF'] . "';</script>";
                exit;
            } else {
                echo "<script>alert('Error: " . htmlspecialchars($stmt->error) . "');</script>";
            }

            $stmt->close();
        } else {
            echo "<script>alert('Error: Could not prepare the statement for insertion.');</script>";
        }
    } else {
        echo "<script>alert('Invalid room ID. Please try again.');</script>";
    }
}


// Close the database connection
// Query to fetch rooms from the database with limit and offset for pagination
$sql = "SELECT room_id, room_number, room_desc, capacity, room_monthlyrent, status, room_pic FROM rooms LIMIT $limit OFFSET $offset";
$result = $conn->query($sql);

$sql = "SELECT 
            rooms.room_id, 
            room_number, 
            room_monthlyrent, 
            capacity, 
            room_desc, 
            room_pic, 
            status,
            (SELECT COUNT(*) FROM roomassignments WHERE room_id = rooms.room_id) AS current_occupants 
        FROM 
            rooms";
$result = $conn->query($sql);
$conn->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>


<!-- Font Awesome CSS for icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link href="Css_user/userdashboard.css" rel="stylesheet">

<!-- Your custom CSS (placed last to ensure it overrides Bootstrap) -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">


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
        <a href="visitor_log.php" class="nav-link"><i class="fas fa-user-check"></i> <span>Log Visitor</span></a>
        <a href="chat.php" class="nav-link"><i class="fas fa-comments"></i> <span>Chat</span></a>


        </div>
        
        <div class="logout">
            <a href="../config/user-logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
        </div>
    </div>

    <!-- Top bar -->
    <div class="topbar">
        <h2>Welcome to Dormio, <?php echo htmlspecialchars($_SESSION["Fname"]); ?>!</h2>
        

        <!-- Button to trigger modal with dynamic data -->
        <a href="profile.php" class="editUserModal">
    <i class="fa fa-user"></i>
</a>


    


        <!-- Modal Content -->
        
              
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
<div class="filter-dropdown mb-4 text-start">
    <label for="statusFilter" class="me-2">Filter by Status:</label>
    <select id="statusFilter" class="form-select d-inline-block w-auto" onchange="filterRooms()">
        <option value="">All</option>
        <?php 
        $statuses = ['Available', 'Occupied', 'Maintenance']; 
        foreach ($statuses as $status): ?>
            <option value="<?php echo htmlspecialchars($status); ?>">
                <?php echo htmlspecialchars($status); ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>


<!-- Room List -->
<div class="container">
    <div class="row justify-content-center">
    <?php
if ($result === false) {
    echo "<p>SQL Error: " . htmlspecialchars($conn->error) . "</p>";
} elseif ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $currentOccupants = $row['current_occupants'] ?? 0; 
        $totalCapacity = $row['capacity'];

        // Determine room status
        if ($currentOccupants >= $totalCapacity) {
            $status = 'Occupied';
        } elseif (strtolower($row['status']) === 'maintenance') {
            $status = 'Maintenance';
        } else {
            $status = 'Available';
        }
        ?>
        <!-- Room Card -->
        <div class="col-md-3 room-card mb-4 me-3" data-status="<?php echo htmlspecialchars($status); ?>">
            <div class="card h-100">
                <?php if (!empty($row['room_pic']) && file_exists("../uploads/" . $row['room_pic'])): ?>
                    <img src="<?php echo htmlspecialchars("../uploads/" . $row['room_pic']); ?>" 
                         alt="Room Image" 
                         class="card-img-top" 
                         style="cursor: pointer;" 
                         onclick="openModal('<?php echo htmlspecialchars("../uploads/" . $row['room_pic']); ?>')">
                <?php else: ?>
                    <img src="path/to/default/image.jpg" alt="No Image Available" class="card-img-top"> <!-- Fallback image -->
                <?php endif; ?>

                <div class="card-body d-flex flex-column">
                    <h5 class="card-title">Room: <?php echo htmlspecialchars($row['room_number']); ?></h5>
                    <p class="room-price">
                        Rent Price: <?php echo number_format($row['room_monthlyrent'], 2); ?> / Monthly
                    </p>
                    <p>Capacity: 
                        <?php echo htmlspecialchars($currentOccupants) . '/' . htmlspecialchars($totalCapacity); ?> people
                    </p>
                    <p><?php echo htmlspecialchars($row['room_desc']); ?></p>
                    <p>Status: <?php echo htmlspecialchars($status); ?></p>

                    <div class="mt-auto">
                        <?php if ($status === 'Maintenance'): ?>
                            <button class="btn btn-warning" disabled>Under Maintenance</button>
                        <?php elseif ($currentOccupants < $totalCapacity): ?>
                            <button type="submit" class="btn  apply-btn " data-bs-toggle="modal" data-bs-target="#applyModal"
    data-room-id="<?php echo htmlspecialchars($row['room_id'] ?? ''); ?>"
    data-room-number="<?php echo htmlspecialchars($row['room_number'] ?? ''); ?>"
    data-room-price="<?php echo htmlspecialchars($row['room_monthlyrent'] ?? ''); ?>"
    data-room-capacity="<?php echo htmlspecialchars($row['capacity'] ?? ''); ?>"
    data-room-status="<?php echo htmlspecialchars($row['status'] ?? ''); ?>">
    Request Reassigment
</button>


                        <?php else: ?>
                            <button class="btn btn-danger" disabled>Fully Occupied</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
} else {
    echo "<p class='text-center'>No rooms available.</p>";
}
?>
 
    </div>
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
    
    <!-- Modal HTML Structure -->
<div class="modal fade" id="applyModal" tabindex="-1" aria-labelledby="applyModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="applyModalLabel">Room Reassign</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="applyForm" action="user-dashboard.php" method="POST">
                    <!-- Hidden input for room_id -->
                    <input type="hidden" name="room_id" id="room_id_input">
                    
                    <!-- Room Information (populated dynamically) -->
                    <div class="form-group mb-3">
                        <p><strong>Room Number:</strong> <span id="modalRoomNumber"></span></p>
                    </div>
                    <div class="form-group mb-3">
                        <p><strong>Rent Price:</strong> <span id="modalRoomPrice"></span></p>
                    </div>
                    <div class="form-group mb-3">
                        <p><strong>Capacity:</strong> <span id="modalRoomCapacity"></span></p>
                    </div>
                    <div class="form-group mb-3">
                        <p><strong>Status:</strong> <span id="modalRoomStatus"></span></p>
                    </div>

                    <!-- Optional Comments -->
                    <div class="form-group mb-3">
    <label for="comments">Reasons:</label>
    <textarea id="comments" name="comments" class="form-control" required></textarea>
</div>

                    <button type="submit" class="btn btn-primary">Submit Application</button>
                </form>
            </div>
        </div>
    </div>
</div>


</div>





<!-- Include jQuery and Bootstrap JS (required for dropdown) -->
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>

    
    <!-- JavaScript -->
    <script>

// When the "Apply for Room" button is clicked
document.querySelectorAll('.apply-btn').forEach(button => {
    button.addEventListener('click', function () {
        // Fetch room data from the data-* attributes
        const roomId = this.getAttribute('data-room-id');
        const roomNumber = this.getAttribute('data-room-number');
        const roomPrice = this.getAttribute('data-room-price');
        const roomCapacity = this.getAttribute('data-room-capacity');
        const roomStatus = this.getAttribute('data-room-status');

        // Populate the modal fields with room data
        document.getElementById('room_id_input').value = roomId;
        document.getElementById('modalRoomNumber').textContent = roomNumber;
        document.getElementById('modalRoomPrice').textContent = `₱${roomPrice}`;
        document.getElementById('modalRoomCapacity').textContent = roomCapacity;
        document.getElementById('modalRoomStatus').textContent = roomStatus;
    });
});

function closeModal() {

    $('#applyModal').modal('hide');
}
         // Function to open the modal and set the image source
    function openModal(imageSrc) {
        document.getElementById('modalImage').src = imageSrc;
        document.getElementById('imageModal').style.display = 'block';
    }

    // Function to close the modal
    function closeModal() {
        document.getElementById('imageModal').style.display = 'none';
    }
    function filterRooms() {
    var filterValue = document.getElementById('statusFilter').value.toLowerCase();
    var roomCards = document.querySelectorAll('.room-card');

    roomCards.forEach(function(card) {
        // Get the status from data-status attribute
        var status = card.getAttribute('data-status').toLowerCase();
        
        // Check if the card should be displayed
        if (filterValue === "" || status === filterValue) {
            card.style.display = ""; // Show card
        } else {
            card.style.display = "none"; // Hide card
        }
    });
}

 
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
