<?php
session_start();
include '../config/config.php'; // Ensure the correct path to your config file

// Ensure the user is logged in
if (!isset($_SESSION['id'])) {
    echo "<script>alert('You must be logged in to view or submit feedback.'); window.location.href = 'login.php';</script>";
    exit;
}

$userId = $_SESSION['id'];

// Fetch the room assignment for the logged-in user
$query = "
    SELECT ra.assignment_id, r.room_number, r.room_pic, r.room_monthlyrent, ra.assignment_date
    FROM RoomAssignments ra
    JOIN Rooms r ON ra.room_id = r.room_id
    WHERE ra.user_id = ?
";
$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Error preparing statement: " . $conn->error);
}
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();

// Check if the user has an assigned room
$roomAssignment = $result->num_rows > 0 ? $result->fetch_assoc() : null;
// Handle feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if feedback is provided
    if (isset($_POST['feedback']) && !empty($_POST['feedback'])) {
        // Sanitize and escape input
        $feedback = $conn->real_escape_string($_POST['feedback']);
        $userId = $_SESSION['id'];  // Assuming the user ID is stored in the session

        // Fetch user's first name (fname) from the users table
        $sql = "SELECT fname FROM users WHERE id = ?";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->bind_result($userFname);
            $stmt->fetch();
            $stmt->close();
        } else {
            echo "<script>alert('Error fetching user details: " . $conn->error . "');</script>";
            exit;
        }

        // Check if assignment_id is set and valid
        if (isset($_POST['assignment_id']) && !empty($_POST['assignment_id'])) {
            $assignment_id = $_POST['assignment_id']; // Get assignment ID from POST data

            // Insert feedback into the database
            $sql = "INSERT INTO roomfeedback (user_id, assignment_id, feedback) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);

            if ($stmt) {
                // Bind parameters: 'i' for integer (user_id, assignment_id), 's' for string (feedback)
                $stmt->bind_param('iis', $userId, $assignment_id, $feedback);

                // Execute and check if successful
                if ($stmt->execute()) {
                    echo "<script>alert('Feedback submitted successfully.');</script>";

                    // Log the activity for feedback submission
                    $activityType = "Feedback Submission";
                    $activityDetails = "$userFname submitted feedback: '$feedback'";

                    // Insert activity log
                    $logSql = "INSERT INTO activity_logs (user_id, activity_type, activity_details) VALUES (?, ?, ?)";
                    $logStmt = $conn->prepare($logSql);

                    if ($logStmt) {
                        $logStmt->bind_param("iss", $userId, $activityType, $activityDetails);
                        $logStmt->execute();
                        $logStmt->close();
                    } else {
                        echo "<script>alert('Error logging activity: " . $conn->error . "');</script>";
                    }
                } else {
                    echo "<script>alert('Error submitting feedback: " . $stmt->error . "');</script>";
                }

                // Close the prepared statement
                $stmt->close();
            } else {
                echo "<script>alert('Error preparing feedback statement: " . $conn->error . "');</script>";
            }
        } else {
            echo "<script>alert('Invalid or missing assignment ID.');</script>";
        }
    }
}

// Query to get the current assignment_id (adjust query as necessary)
$query = "SELECT assignment_id FROM roomassignments WHERE user_id = '$userId' ORDER BY assignment_date DESC ";
$result = mysqli_query($conn, $query);

// Check if assignment exists and fetch the assignment_id
if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    $assignment_id = $row['assignment_id'];
} else {
    $assignment_id = null; // Handle the case where no assignment is found
}


// Fetch the previous feedback for the logged-in user
$query = "SELECT feedback FROM roomfeedback WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$previousFeedback = '';

if ($result->num_rows > 0) {
    $previousFeedback = $result->fetch_assoc()['feedback'];
}

// Fetch all feedback from the database, but only for the logged-in user
$query = "SELECT f.id, f.feedback, f.submitted_at 
          FROM roomfeedback f 
          WHERE f.user_id = ? 
          ORDER BY f.submitted_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $userId);
$stmt->execute();
$feedbackResult = $stmt->get_result();

// Check if there's a delete request
if (isset($_GET['delete_id'])) {
    $feedbackIdToDelete = $_GET['delete_id'];

    // Ensure that the feedback belongs to the logged-in user before deleting it
    $deleteQuery = "SELECT user_id, feedback FROM roomfeedback WHERE id = ?";
    $stmt = $conn->prepare($deleteQuery);
    $stmt->bind_param('i', $feedbackIdToDelete);
    $stmt->execute();
    $deleteResult = $stmt->get_result();

    if ($deleteResult->num_rows > 0) {
        $feedbackData = $deleteResult->fetch_assoc();
        $feedbackOwner = $feedbackData['user_id'];
        $feedbackText = $feedbackData['feedback'];

        if ($feedbackOwner == $userId) {
            // Prepare DELETE query to remove feedback from the database
            $deleteQuery = "DELETE FROM roomfeedback WHERE id = ?";
            $stmt = $conn->prepare($deleteQuery);

            if ($stmt) {
                $stmt->bind_param('i', $feedbackIdToDelete);

                if ($stmt->execute()) {
                    // Fetch user's first name
                    $userQuery = "SELECT fname FROM users WHERE id = ?";
                    $stmtUser = $conn->prepare($userQuery);
                    $stmtUser->bind_param('i', $userId);
                    $stmtUser->execute();
                    $stmtUser->bind_result($userFname);
                    $stmtUser->fetch();
                    $stmtUser->close();

                    // Log activity for deleting feedback
                    $activityType = "Feedback Deletion";
                    $activityDetails = "$userFname deleted their feedback: '$feedbackText'";

                    // Insert activity log
                    $logSql = "INSERT INTO activity_logs (user_id, activity_type, activity_details) VALUES (?, ?, ?)";
                    $logStmt = $conn->prepare($logSql);

                    if ($logStmt) {
                        $logStmt->bind_param("iss", $userId, $activityType, $activityDetails);
                        $logStmt->execute();
                        $logStmt->close();
                    } else {
                        echo "<script>alert('Error logging activity: " . $conn->error . "');</script>";
                    }

                    echo "<script>
                        alert('Feedback deleted successfully.');
                        window.location.href = 'user_room.php';  // Change to the correct feedback list page
                    </script>";
                } else {
                    echo "<script>alert('Error deleting feedback: " . $stmt->error . "');</script>";
                }

                $stmt->close();
            } else {
                echo "<script>alert('Error preparing the delete statement.');</script>";
            }
        } else {
            echo "<script>alert('You cannot delete feedback that does not belong to you.');</script>";
        }
    } else {
        echo "<script>alert('Feedback not found or does not belong to you.');</script>";
    }
}

// Handle feedback update request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['feedbackText'])) {
    $feedbackId = $_POST['feedback_id'];
    $feedbackText = $_POST['feedbackText'];

    // Ensure feedback is not empty
    if (!empty($feedbackText)) {
        // Update the feedback in the database
        $sql = "UPDATE roomfeedback SET feedback = ? WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param('sii', $feedbackText, $feedbackId, $userId);
            if ($stmt->execute()) {
                // Fetch user's first name
                $userQuery = "SELECT fname FROM users WHERE id = ?";
                $stmtUser = $conn->prepare($userQuery);
                $stmtUser->bind_param('i', $userId);
                $stmtUser->execute();
                $stmtUser->bind_result($userFname);
                $stmtUser->fetch();
                $stmtUser->close();

                // Log activity for updating feedback
                $activityType = "Feedback Update";
                $activityDetails = "User '$userFname' updated their feedback to: '$feedbackText'";

                // Insert activity log
                $logSql = "INSERT INTO activity_logs (user_id, activity_type, activity_details) VALUES (?, ?, ?)";
                $logStmt = $conn->prepare($logSql);

                if ($logStmt) {
                    $logStmt->bind_param("iss", $userId, $activityType, $activityDetails);
                    $logStmt->execute();
                    $logStmt->close();
                } else {
                    echo "<script>alert('Error logging activity: " . $conn->error . "');</script>";
                }

                echo "<script>alert('Feedback updated successfully.'); window.location.href = 'user_room.php';</script>";
            } else {
                echo "<script>alert('Error updating feedback: " . $stmt->error . "');</script>";
            }

            $stmt->close();
        } else {
            echo "<script>alert('Error preparing update query.');</script>";
        }
    } 
}


?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="Css_user/users-dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Bootstrap JS (Optional for modal functionality) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    
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
        <a href="chat.php" class="nav-link"><i class="fas fa-comments"></i> <span>Chat</span></a>
        <a href="user-payment.php" class="nav-link"><i class="fas fa-money-bill-alt"></i> <span>Payment History</span></a>


        </div>
        <div class="logout">
        <a href="../config/user-logout.php" onclick="return confirmLogout();">
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
        <h2>My Room</h2>

    </div>
    <div class="main-content">      

    <div class="container h-100">
    <div class="row d-flex justify-content-center align-items-stretch" style="min-height: 100vh;">
        <!-- Room Assignment Column -->
        <div class="col-12 col-md-6 mb-3 d-flex">
            <div class="card shadow-sm w-100 h-100">
                <div class="card-body text-center d-flex flex-column justify-content-between">
                    <?php if ($roomAssignment): ?>
                        <h5 class="card-title"><strong>Room:</strong> <?php echo htmlspecialchars($roomAssignment['room_number']); ?></h5>
                        <p class="card-text"><strong>Monthly Rent:</strong> <?php echo htmlspecialchars($roomAssignment['room_monthlyrent']); ?></p>
                        <p class="card-text"><strong>Assign Date:</strong> <?php echo htmlspecialchars($roomAssignment['assignment_date']); ?></p>

                        <?php if (!empty($roomAssignment['room_pic'])): 
                            $imagePath = "../uploads/" . htmlspecialchars($roomAssignment['room_pic']); 
                        ?>
                            <img src="<?php echo $imagePath; ?>" alt="Room Picture" class="img-fluid rounded" style="max-width: auto; height: auto;">
                        <?php else: ?>
                            <p>No room picture available.</p>
                        <?php endif; ?>

                    <?php else: ?>
                        <p class="card-text">You have not been assigned a room yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

       <!-- Feedback Column -->
<div class="col-12 col-md-6 d-flex">
    <div class="card shadow-sm w-100 h-90">
        <div class="card-body d-flex flex-column justify-content-between">
            <h5 class="card-title text-center"><strong>Feedback</strong></h5>
            
            <!-- Feedback Form -->
            <form action="user_room.php" method="POST" class="d-flex flex-column flex-grow-1">
                <div class="form-group mb-4">
                    <label for="feedback" class="form-label mt-5"></label>
                    <textarea id="feedback" name="feedback" class="form-control" rows="5" required placeholder="Write your feedback here" style="resize: none; height: 200px;"></textarea>
                    <!-- My Feedback button (this just opens the modal) -->
                <div class=" mt-2">
                    <button type="button" class="btn btn-link" data-bs-toggle="modal" data-bs-target="#allFeedbackModal" style="text-decoration: underline;">
                        My Feedback
                    </button>
                </div>
                 <!-- Automatically set the assignment_id -->
                 <input type="hidden" name="assignment_id" value="<?php echo htmlspecialchars($assignment_id); ?>">

<div class="text-center mt-5 mb-2">
    <button type="submit" class="btn btn-primary">Submit Feedback</button>
</div>
                    </div>

            </form>
        </div>
    </div>
</div>
                        
    </div>
</div>

</div>



      
    </div>
<!-- Modal to View All Feedback -->
<div class="modal fade" id="allFeedbackModal" tabindex="-1" aria-labelledby="allFeedbackModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="allFeedbackModalLabel">My Feedback</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php if ($result->num_rows > 0): ?>
                    <!-- Feedback List -->
                    <div class="list-group">
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <div class="list-group-item">
                                <p><strong>Feedback:</strong> <?php echo nl2br(htmlspecialchars($row['feedback'])); ?></p>
                                <p><strong>Submitted At:</strong> <?php echo date('Y-m-d H:i:s', strtotime($row['submitted_at'])); ?></p>
                                
                                <div class="d-flex justify-content-between">
<!-- Edit Button -->
<button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editFeedbackModal">
    <i class="fas fa-edit"></i> Edit this Feedback
</button>


                       <!-- Delete Button -->
                                    <button type="button" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this feedback?') ? window.location.href='user_room.php?delete_id=<?php echo htmlspecialchars($row['id']); ?>' : ''">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </button>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p>You have not submitted any feedback yet.</p>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php
// Assuming $userId is already set from the session
// Fetch the current feedback for the logged-in user
$query = "SELECT id, feedback FROM roomfeedback WHERE user_id = ? ORDER BY submitted_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$currentFeedback = '';

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $currentFeedback = $row['feedback'];
    $feedbackId = $row['id'];
}
?>

<!-- Modal for Editing Feedback -->
<div class="modal fade" id="editFeedbackModal" tabindex="-1" aria-labelledby="editFeedbackModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editFeedbackModalLabel">Edit Feedback</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Form for feedback edit -->
                <form action="user_room.php" method="POST">
                    <!-- Hidden field for feedback ID -->
                    <input type="hidden" name="feedback_id" value="<?php echo htmlspecialchars($feedbackId); ?>" />

                    <!-- Feedback textarea field -->
                    <div class="mb-3">
                        <label for="feedbackText" class="form-label">Feedback</label>
                        <textarea class="form-control" id="feedbackText" name="feedbackText" rows="3" required><?php echo htmlspecialchars($currentFeedback); ?></textarea>
                    </div>
                    <div class="modal-footer d-flex justify-content-center">
    <button type="submit" class="btn btn-primary">Save Changes</button>
</div>

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
