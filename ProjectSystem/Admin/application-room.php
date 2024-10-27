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
// Fetch room applications with corresponding room and user details
$sql = "
    SELECT 
    ra.application_id, 
    r.room_number, 
    CONCAT(u.fname, ' ', u.lname) AS resident,
    r.room_desc AS type_application, 
    ra.comments, -- Assuming you want to include the comment field
    ra.status
FROM RoomApplications ra
JOIN users u ON ra.user_id = u.id
JOIN Rooms r ON ra.room_id = r.room_id
ORDER BY ra.application_id

";
$result = $conn->query($sql);

// Check if the user is logged in and if the form is submitted via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['id'])) {
    
    // Get the application ID and new status from the form
    $applicationId = intval($_POST['application_id']);
    $newStatus = $_POST['status'];

    // Ensure the status is valid
    if (in_array($newStatus, ['pending', 'approved', 'rejected'])) {
        // Prepare the SQL query to update the application status
        $stmt = $conn->prepare("UPDATE RoomApplications SET status = ? WHERE application_id = ?");
        if ($stmt) {
            $stmt->bind_param('si', $newStatus, $applicationId);
            
            // Execute the query
            if ($stmt->execute()) {
                // After updating the status, redirect to avoid form resubmission
             header("Location: application-room.php?status=success");
                exit; // Ensure the script stops after the redirect
            } else {
                echo "<script>alert('Error: " . $stmt->error . "');</script>";
            }
            $stmt->close();
        } else {
            echo "<script>alert('Error preparing statement: " . $conn->error . "');</script>";
        }
    } else {
        echo "<script>alert('Invalid status value.');</script>";
    }
} else {
    echo "Unauthorized access or invalid request method.";
}

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
                <a class="dropdown-item" href="roomlist.php" style="color: #ffffff;">
                    <i class="fas fa-list"></i> Room List
                </a>
                <a class="dropdown-item" href="room-assign.php" style="color: #ffffff;">
                    <i class="fas fa-user-check"></i> Room Assign
                </a>
                <a class="dropdown-item" href="application-room.php" style="color: #ffffff;">
                    <i class="fas fa-file-alt"></i> Room Application
                </a>
            </div>
            <a href="admin-visitor_log.php" class="nav-link"><i class="fas fa-address-book"></i> <span>Log Visitor</span></a>

            </div>

        </div>
        <div class="logout">
            <a href="../config/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
        </div>
    </div>

    <!-- Top bar -->
    <div class="topbar">
        <h2>Application Room</h2>
    </div>

    <!-- Main content -->
    <div class="main-content">        
        
    <table class="table table-bordered">
    <thead>
        <tr>
            <th>No.</th>
            <th>Room Number</th>
            <th>Resident</th>
            <th>Type of Application</th>
            <th>Comment</th> <!-- Move Comment to its own column in the right place -->
            <th>Status</th> <!-- Status should come after Comment -->
        </tr>
    </thead>
    <tbody>
        <?php
        if ($result->num_rows > 0) {
            $no = 1; // Counter for row number
            while ($row = $result->fetch_assoc()) {
                ?>
                <tr>
                    <td><?php echo $no++; ?></td>
                    <td><?php echo htmlspecialchars($row['room_number']); ?></td>
                    <td><?php echo htmlspecialchars($row['resident']); ?></td>
                    <td><?php echo htmlspecialchars($row['type_application']); ?></td>
                    
                    <!-- Display the comment in the Comment column -->
                    <td><?php echo !empty($row['comments']) ? htmlspecialchars($row['comments']) : 'No comment'; ?></td>
                    <td> <!-- Status comes after the comment -->
                        <span class="badge 
                            <?php echo ($row['status'] == 'approved') ? 'badge-success' : 
                                       (($row['status'] == 'rejected') ? 'badge-danger' : 'badge-warning'); ?>">
                            <?php echo htmlspecialchars($row['status']); ?>
                        </span>

                        <!-- If the status is pending, show Approve and Reject buttons -->
                        <?php if ($row['status'] == 'pending') { ?>
                            <form method="POST" action="application-room.php" style="display:inline;">
                                <input type="hidden" name="application_id" value="<?php echo $row['application_id']; ?>">

                                <!-- Dropdown for selecting status -->
                                <select id="statusDropdown" name="status" class="form-control form-control-sm" style="display:inline-block; width:auto;">
                                    <option value="pending" <?php echo ($row['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option value="approved" <?php echo ($row['status'] == 'approved') ? 'selected' : ''; ?>>Approved</option>
                                    <option value="rejected" <?php echo ($row['status'] == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                                </select>

                                <!-- Submit button to apply the change -->
                                <button type="submit" class="btn btn-primary btn-sm">Update Status</button>
                            </form>
                        <?php } ?>
                    </td>
                </tr>
                <?php
            }
        } else {
            echo "<tr><td colspan='6'>No applications found.</td></tr>";
        }
        ?>
    </tbody>
</table>


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
