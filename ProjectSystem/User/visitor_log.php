<?php
session_start();
include '../config/config.php'; // Correct path to your config file

// Check if the user is logged in
if (!isset($_SESSION['id'])) {
    header("Location: login.php"); // Redirect to login if not logged in
    exit();
}

// Store the logged-in user's ID
$userId = $_SESSION['id'];

// **Handle POST Requests**

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // **Add Visitor Logic**
    if (isset($_POST['visitor_name'])) {
        $visitorName = trim($_POST['visitor_name']);
        $contactInfo = trim($_POST['contact_info']);
        $purpose = trim($_POST['purpose']);
        $checkInTime = $_POST['check_in_time'];

        // Validate input fields
        if (empty($visitorName) || empty($contactInfo) || empty($purpose) || empty($checkInTime)) {
            echo "<script>alert('All fields are required!'); window.history.back();</script>";
            exit();
        }
        if (!preg_match('/^(0?[9]\d{9}|[9]\d{9})$/', $contactInfo)) {
            echo "<script>alert('Contact Info must be a valid Philippine phone number (10 or 11 digits).');</script>";
            exit();
        }

        // Format datetime
        $currentDate = date('Y-m-d');
        $checkInDatetime = $currentDate . ' ' . $checkInTime . ':00';

        // Insert visitor into the database
        $stmt = $conn->prepare(
            "INSERT INTO visitors (name, contact_info, purpose, visiting_user_id, check_in_time) 
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("sssis", $visitorName, $contactInfo, $purpose, $userId, $checkInDatetime);

        if ($stmt->execute()) {
            echo "<script>alert('Visitor added successfully!'); window.location.href='visitor_log.php';</script>";
            exit();
        } else {
            echo "<script>alert('Error adding visitor: " . $stmt->error . "'); window.history.back();</script>";
            exit();
        }
    }

    // **Check-Out Logic**
    if (isset($_POST['visitor_id'])) {
        $visitorId = (int)$_POST['visitor_id'];

        $stmt = $conn->prepare(
            "UPDATE visitors SET check_out_time = NOW() WHERE id = ? AND visiting_user_id = ?"
        );
        $stmt->bind_param("ii", $visitorId, $userId);

        if ($stmt->execute()) {
            echo "<script>alert('Check-out successful!'); window.location.href='visitor_log.php';</script>";
            exit();
        } else {
            echo "<script>alert('Error updating check-out time: " . $stmt->error . "'); window.history.back();</script>";
            exit();
        }
    }

    // **Delete Visitor Logic**
    if (isset($_POST['delete_visitor_id'])) {
        $visitorId = (int)$_POST['delete_visitor_id'];

        $stmt = $conn->prepare(
            "DELETE FROM visitors WHERE id = ? AND visiting_user_id = ?"
        );
        $stmt->bind_param("ii", $visitorId, $userId);

        if ($stmt->execute()) {
            echo "<script>alert('Visitor deleted successfully!'); window.location.href='visitor_log.php';</script>";
            exit();
        } else {
            echo "<script>alert('Error deleting visitor: " . $stmt->error . "'); window.history.back();</script>";
            exit();
        }
    }
}



// **Filter Logic**: Handle filter selection from the dropdown
$filter = $_GET['filter'] ?? ''; // Get the selected filter (if any)
$dateCondition = ''; // Initialize

if ($filter === 'today') {
    $dateCondition = "AND DATE(check_in_time) = CURDATE()";
} elseif ($filter === 'this_week') {
    $dateCondition = "AND YEARWEEK(check_in_time, 1) = YEARWEEK(CURDATE(), 1)";
} elseif ($filter === 'this_month') {
    $dateCondition = "AND MONTH(check_in_time) = MONTH(CURDATE()) AND YEAR(check_in_time) = YEAR(CURDATE())";
}

// **Fetch Visitors for the Logged-in User Only**
$sql = "SELECT v.*, CONCAT(u.fname, ' ', u.lname) AS visiting_person 
        FROM visitors v 
        LEFT JOIN users u ON v.visiting_user_id = u.id 
        WHERE v.visiting_user_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

$conn->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="Css_user/v-log.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap CSS -->
<link href="https://stackpath.bootstrapcdn.com/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">

<!-- jQuery (needed for Bootstrap's JavaScript plugins) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Bootstrap JS -->
<script src="https://stackpath.bootstrapcdn.com/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>

</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="menu" id="hamburgerMenu">
            <i class="fas fa-bars"></i> <!-- Hamburger menu icon -->
        </div>

        <div class="sidebar-nav">
        <a href="user-dashboard.php" class="nav-link"><i class="fas fa-home"></i><span>Home</span></a>
        <a href="user_room.php" class="nav-link"><i class="fas fa-key"></i> <span>Room Assign</span></a>
        <a href="visitor_log.php" class="nav-link active"><i class="fas fa-user-check"></i> <span>Log Visitor</span></a>
        </div>
        
        <div class="logout">
            <a href="../config/user-logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
        </div>
    </div>

    <!-- Top bar -->
    <div class="topbar">
        <h2>Visitor Log</h2>

    </div>
    <div class="main-content">      
    <div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="input-group w-25">
            <label class="input-group-text" for="filterSelect">Filter by</label>
            <select class="form-select" id="filterSelect" name="filter" 
                onchange="location = 'visitor_log.php?filter=' + this.value;">
                <option value="">Choose...</option>
                <option value="today" <?= $filter === 'today' ? 'selected' : '' ?>>Today</option>
                <option value="this_week" <?= $filter === 'this_week' ? 'selected' : '' ?>>This Week</option>
                <option value="this_month" <?= $filter === 'this_month' ? 'selected' : '' ?>>This Month</option>
            </select>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#visitorModal">Log Visitor</button>
    </div>

    <!-- Visitor Log Table -->
    <div class="table-responsive">
        <table class="table table-bordered">
            <thead class="table-light">
                <tr>
                    <th scope="col">No.</th>
                    <th scope="col">Name</th>
                    <th scope="col">Contact Info</th>
                    <th scope="col">Purpose</th>
                    <th scope="col">Visiting Person</th>
                    <th scope="col">Check-In</th>
                    <th scope="col">Check-Out</th>
                    <th scope="col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): 
                    $counter = 1;
                    while ($row = $result->fetch_assoc()):
                        $isCheckedOut = !empty($row['check_out_time']);
                        $checkInTime = date("g:i A", strtotime($row['check_in_time']));
                        $checkOutTime = $isCheckedOut ? date("g:i A", strtotime($row['check_out_time'])) : 'N/A';
                ?>
                    <tr>
                        <td><?= $counter++ ?></td>
                        <td><?= htmlspecialchars($row['name']) ?></td>
                        <td><?= htmlspecialchars($row['contact_info']) ?></td>
                        <td><?= htmlspecialchars($row['purpose']) ?></td>
                        <td><?= htmlspecialchars($row['visiting_person']) ?></td>
                        <td><?= $checkInTime ?></td>
                        <td><?= $checkOutTime ?></td>
                        <td>
                            <form action="visitor_log.php" method="post" style="display:inline;">
                                <input type="hidden" name="visitor_id" value="<?= $row['id'] ?>">
                                <button type="submit" class="btn btn-secondary btn-sm" 
                                    <?= $isCheckedOut ? 'disabled' : '' ?>>Check-Out</button>
                            </form>
                            <form action="visitor_log.php" method="post" style="display:inline;">
                                <input type="hidden" name="delete_visitor_id" value="<?= $row['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm" 
                                    onclick="return confirm('Are you sure?')">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endwhile; else: ?>
                    <tr><td colspan="8" class="text-center">No visitors found</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Log Visitor Modal -->
<div class="modal fade" id="visitorModal" tabindex="-1" aria-labelledby="visitorModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="visitorModalLabel">Log New Visitor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
    <i class="fas fa-times"></i> <!-- Font Awesome icon for close -->
</button>
            </div>
            <div class="modal-body">
                <form action="visitor_log.php" method="post" onsubmit="return validateForm()">
                    <div class="mb-3">
                        <label for="visitorName" class="form-label">Name</label>
                        <input type="text" class="form-control" id="visitorName" name="visitor_name" required>
                    </div>
                    <div class="mb-3">
                    <label for="contactInfo" class="form-label">Contact Info</label>
                    <input type="text" class="form-control" id="contactInfo" name="contact_info" 
                        required pattern="^(0?[9]\d{9}|[9]\d{9})$" title="Must be a valid Philippine phone number starting with 09 (10 or 11 digits)">
                </div>

                    <div class="mb-3">
                        <label for="purpose" class="form-label">Purpose</label>
                        <input type="text" class="form-control" id="purpose" name="purpose" required>
                    </div>
                    <div class="mb-3">
                        <label for="visitingUser" class="form-label">Visiting Person</label>
                        <input type="hidden" name="visiting_user_id" value="<?php echo $_SESSION['id']; ?>">
                        <input type="text" class="form-control" id="visitingUser" value="<?php echo htmlspecialchars($_SESSION['Fname'] . ' ' . $_SESSION['Lname']); ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="checkInTime" class="form-label">Check-In Time</label>
                        <input type="time" class="form-control" id="checkInTime" name="check_in_time" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Save and Check-In</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript Libraries -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>


<!-- Include jQuery and Bootstrap JS (required for dropdown) -->
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>

    
    <!-- JavaScript -->
    <script>

function validateForm() {
        const contactInfo = document.getElementById('contact_info').value;

        // Regular expression for 10-11 digit numbers
        const contactPattern = /^\d{10,11}$/;

        if (!contactPattern.test(contactInfo)) {
            alert('Contact Info must be a number with 10 or 11 digits.');
            return false; // Prevent form submission
        }
        return true; // Allow form submission if valid
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
