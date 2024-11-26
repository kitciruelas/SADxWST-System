<?php
session_start();
include '../config/config.php'; // Ensure the correct path to your config file

// Add this at the very beginning of the file, right after session_start()
$sweetAlertMessages = [];

// Ensure the user is logged in
if (!isset($_SESSION['id'])) {
    $sweetAlertMessages[] = [
        'title' => 'Access Denied',
        'text' => 'You must be logged in to view or submit feedback.',
        'icon' => 'warning',
        'redirect' => 'login.php'
    ];
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['feedback'])) {
    if (!empty($_POST['feedback'])) {
        $feedback = $conn->real_escape_string($_POST['feedback']);
        $userId = $_SESSION['id'];

        // Fetch user's first name
        $sql = "SELECT fname FROM users WHERE id = ?";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->bind_result($userFname);
            $stmt->fetch();
            $stmt->close();

            // Check if assignment_id exists
            if (isset($_POST['assignment_id']) && !empty($_POST['assignment_id'])) {
                $assignment_id = $_POST['assignment_id'];
                
                // Insert feedback
                $sql = "INSERT INTO roomfeedback (user_id, assignment_id, feedback) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);

                if ($stmt) {
                    $stmt->bind_param('iis', $userId, $assignment_id, $feedback);

                    if ($stmt->execute()) {
                        $sweetAlertMessages[] = [
                            'title' => 'Success!',
                            'text' => 'Feedback submitted successfully.',
                            'icon' => 'success',
                            'redirect' => 'user_room.php'
                        ];
                    } else {
                        $sweetAlertMessages[] = [
                            'title' => 'Error!',
                            'text' => 'Error submitting feedback: ' . $stmt->error,
                            'icon' => 'error'
                        ];
                    }
                    $stmt->close();
                }
            } else {
                $sweetAlertMessages[] = [
                    'title' => 'Error!',
                    'text' => 'Invalid or missing assignment ID.',
                    'icon' => 'error'
                ];
            }
        } else {
            $sweetAlertMessages[] = [
                'title' => 'Error!',
                'text' => 'Error fetching user details: ' . $conn->error,
                'icon' => 'error'
            ];
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

// Handle feedback deletion
if (isset($_GET['delete_id'])) {
    $feedbackIdToDelete = $_GET['delete_id'];

    // Verify ownership and delete feedback
    $deleteQuery = "SELECT user_id, feedback FROM roomfeedback WHERE id = ?";
    $stmt = $conn->prepare($deleteQuery);
    $stmt->bind_param('i', $feedbackIdToDelete);
    $stmt->execute();
    $deleteResult = $stmt->get_result();

    if ($deleteResult->num_rows > 0) {
        $feedbackData = $deleteResult->fetch_assoc();
        if ($feedbackData['user_id'] == $userId) {
            $deleteQuery = "DELETE FROM roomfeedback WHERE id = ?";
            $stmt = $conn->prepare($deleteQuery);
            
            if ($stmt) {
                $stmt->bind_param('i', $feedbackIdToDelete);
                
                if ($stmt->execute()) {
                    // Log activity
                    $activityType = "Feedback Deletion";
                    $activityDetails = "$userFname deleted their feedback";
                    
                    $logSql = "INSERT INTO activity_logs (user_id, activity_type, activity_details) VALUES (?, ?, ?)";
                    $logStmt = $conn->prepare($logSql);
                    $logStmt->bind_param("iss", $userId, $activityType, $activityDetails);
                    $logStmt->execute();

                    $sweetAlertMessages[] = [
                        'title' => 'Success!',
                        'text' => 'Feedback deleted successfully.',
                        'icon' => 'success'
                    ];
                } else {
                    $sweetAlertMessages[] = [
                        'title' => 'Error!',
                        'text' => 'Error deleting feedback: ' . $stmt->error,
                        'icon' => 'error'
                    ];
                }
            }
        } else {
            $sweetAlertMessages[] = [
                'title' => 'Error!',
                'text' => 'You cannot delete feedback that does not belong to you.',
                'icon' => 'error'
            ];
        }
    }
}

// Handle feedback update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['feedbackText'])) {
    $feedbackId = $_POST['feedback_id'];
    $feedbackText = $_POST['feedbackText'];

    if (!empty($feedbackText)) {
        $sql = "UPDATE roomfeedback SET feedback = ? WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $stmt->bind_param('sii', $feedbackText, $feedbackId, $userId);
            if ($stmt->execute()) {
                // Log activity
                $activityType = "Feedback Update";
                $activityDetails = "User updated their feedback";
                
                $logSql = "INSERT INTO activity_logs (user_id, activity_type, activity_details) VALUES (?, ?, ?)";
                $logStmt = $conn->prepare($logSql);
                $logStmt->bind_param("iss", $userId, $activityType, $activityDetails);
                $logStmt->execute();

                $sweetAlertMessages[] = [
                    'title' => 'Success!',
                    'text' => 'Feedback updated successfully.',
                    'icon' => 'success'
                ];
            } else {
                $sweetAlertMessages[] = [
                    'title' => 'Error!',
                    'text' => 'Error updating feedback: ' . $stmt->error,
                    'icon' => 'error'
                ];
            }
            $stmt->close();
        }
    } else {
        $sweetAlertMessages[] = [
            'title' => 'Error!',
            'text' => 'Feedback cannot be empty.',
            'icon' => 'error'
        ];
    }
}

// Add this near your other database queries at the top
$canRequestMoveOut = false;
$pendingRequest = null;

if ($roomAssignment) {
    // Check if there's any pending request
    $checkRequest = "SELECT * FROM move_out_requests 
                    WHERE user_id = ? 
                    AND status = 'pending' 
                    ORDER BY request_date DESC 
                    LIMIT 1";
    $stmt = $conn->prepare($checkRequest);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $pendingRequest = $stmt->get_result()->fetch_assoc();

    // Can request move out if there's no pending request
    $canRequestMoveOut = !$pendingRequest;
}

// Handle move out request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_move_out'])) {
    $reason = $conn->real_escape_string($_POST['move_out_reason']);
    $targetDate = $conn->real_escape_string($_POST['target_date']);

    // First, get the room_id from RoomAssignments
    $getRoomQuery = "SELECT r.room_id 
                     FROM RoomAssignments ra 
                     JOIN Rooms r ON ra.room_id = r.room_id 
                     WHERE ra.user_id = ? 
                     ORDER BY ra.assignment_date DESC 
                     LIMIT 1";
    $stmt = $conn->prepare($getRoomQuery);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $roomResult = $stmt->get_result();
    
    if ($roomResult->num_rows > 0) {
        $roomData = $roomResult->fetch_assoc();
        $roomId = $roomData['room_id'];

        // Now insert the move out request with the correct room_id
        $insertRequest = "INSERT INTO move_out_requests (user_id, room_id, reason, target_date, request_date, status) 
                         VALUES (?, ?, ?, ?, NOW(), 'pending')";
        $stmt = $conn->prepare($insertRequest);
        $stmt->bind_param('iiss', $userId, $roomId, $reason, $targetDate);
        
        if ($stmt->execute()) {
            // Log the activity
            $activityType = "Move Out Request";
            $activityDetails = "User submitted a move out request for Room " . $roomAssignment['room_number'];
            
            $logSql = "INSERT INTO activity_logs (user_id, activity_type, activity_details) VALUES (?, ?, ?)";
            $logStmt = $conn->prepare($logSql);
            $logStmt->bind_param("iss", $userId, $activityType, $activityDetails);
            $logStmt->execute();

            $sweetAlertMessages[] = [
                'title' => 'Success!',
                'text' => 'Move out request submitted successfully.',
                'icon' => 'success'
            ];
        } else {
            $sweetAlertMessages[] = [
                'title' => 'Error!',
                'text' => 'Error submitting request. Please try again.',
                'icon' => 'error'
            ];
        }
    } else {
        $sweetAlertMessages[] = [
            'title' => 'Error!',
            'text' => 'Could not find room assignment.',
            'icon' => 'error'
        ];
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

<!-- Add these in the <head> section -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    
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
        </div>
    </div>

    <!-- Top bar -->
    <div class="topbar">
        <div class="d-flex justify-content-between align-items-center">
            <h2>My Room</h2>
         
        </div>
    </div>
    <div class="main-content">      
        <div class="container-fluid">
            <div class="row">
                <!-- Room Assignment Column (Full Width) -->
                <div class="col-12"> 
                    <div class="card shadow h-100">
                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-home me-2"></i>Room Details
                            </h5>
                            <?php if ($roomAssignment): ?>
                                <span class="badge bg-success">Assigned</span>
                            <?php else: ?>
                                <span class="badge bg-warning">Unassigned</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php if ($roomAssignment): ?>
                                <!-- Room Image Section -->
                                <div class="room-image-container mb-4">
                                    <?php if (!empty($roomAssignment['room_pic'])): 
                                        $imagePath = "../uploads/" . htmlspecialchars($roomAssignment['room_pic']); 
                                    ?>
                                        <div class="position-relative">
                                            <img src="<?php echo $imagePath; ?>" 
                                                 alt="Room Picture" 
                                                 class="img-fluid rounded shadow-sm" 
                                                 style="width: 100%; height: 450px; object-fit: cover;"> <!-- Increased height -->
                                            <div class="room-number-badge">
                                                Room <?php echo htmlspecialchars($roomAssignment['room_number']); ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="no-image-placeholder">
                                            <i class="fas fa-image"></i>
                                            <p>No room picture available</p>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Room Details Section -->
                                <div class="room-details">
                                    <div class="detail-item">
                                        <i class="fas fa-door-open text-primary"></i>
                                        <span class="label">Room Number:</span>
                                        <span class="value"><?php echo htmlspecialchars($roomAssignment['room_number']); ?></span>
                                    </div>
                                    
                                    <div class="detail-item">
                                        <i class="fas fa-money-bill-wave text-success"></i>
                                        <span class="label">Monthly Rent:</span>
                                        <span class="value">₱<?php echo number_format(htmlspecialchars($roomAssignment['room_monthlyrent']), 2); ?></span>
                                    </div>
                                    
                                    <div class="detail-item">
                                        <i class="fas fa-calendar-alt text-info"></i>
                                        <span class="label">Assigned Date:</span>
                                        <span class="value"><?php echo date('F d, Y', strtotime($roomAssignment['assignment_date'])); ?></span>
                                    </div>
                                </div>

                                <!-- Add Move Out Section Here -->
                                <div class="move-out-section mt-4">
                                    <?php if ($pendingRequest): ?>
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle me-2"></i>
                                            <strong>Pending Move Out Request</strong>
                                            <p class="mb-0">Your move out request submitted on <?php echo date('F d, Y', strtotime($pendingRequest['request_date'])); ?> is pending approval.</p>
                                            <p class="mb-0">Target Date: <?php echo date('F d, Y', strtotime($pendingRequest['target_date'])); ?></p>
                                        </div>
                                    <?php elseif ($canRequestMoveOut): ?>
                                        <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#moveOutModal">
                                            <i class="fas fa-door-open me-2"></i>Request Move Out
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-home fa-4x text-muted mb-3"></i>
                                    <h5 class="text-muted">No Room Assigned Yet</h5>
                                    <p class="text-muted">Please contact the administrator for room assignment.</p>
                                </div>
                            <?php endif; ?>
                        </div>
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
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i>
                                        <?php echo date('F d, Y h:i A', strtotime($row['submitted_at'])); ?>
                                    </small>
                                </div>
                                <p class="mb-3"><?php echo nl2br(htmlspecialchars($row['feedback'])); ?></p>
                                
                                <div class="d-flex justify-content-end gap-2">
                                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editFeedbackModal">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button type="button" class="btn btn-danger btn-sm" 
                                            onclick="confirmDeleteFeedback('user_room.php?delete_id=<?php echo htmlspecialchars($row['id']); ?>')">
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

<!-- Add this floating feedback button -->
<div class="floating-feedback-btn" data-bs-toggle="modal" data-bs-target="#feedbackModal">
    <i class="fas fa-comment-dots"></i>
    <span>Feedback</span>
</div>

<!-- New Feedback Modal -->
<div class="modal fade" id="feedbackModal" tabindex="-1" aria-labelledby="feedbackModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="feedbackModalLabel">
                    <i class="fas fa-comment-dots me-2"></i>Submit Feedback
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="user_room.php" method="POST">
                    <div class="form-group">
                        <textarea id="feedback" name="feedback" class="form-control" 
                                rows="6" required placeholder="Write your feedback here..."
                                style="resize: none;"></textarea>
                        <input type="hidden" name="assignment_id" value="<?php echo htmlspecialchars($assignment_id); ?>">
                    </div>
                    <div class="d-flex justify-content-between mt-3">
                        <button type="button" class="btn btn-outline-primary" 
                                data-bs-toggle="modal" data-bs-target="#allFeedbackModal">
                            <i class="fas fa-history me-2"></i>View Previous Feedback
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane me-2"></i>Submit Feedback
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Add this CSS -->
<style>
/* General Styles */
:root {
    --primary-color: #4e73df;
    --secondary-color: #858796;
    --success-color: #1cc88a;
    --info-color: #36b9cc;
    --warning-color: #f6c23e;
    --danger-color: #e74a3b;
    --light-color: #f8f9fc;
    --dark-color: #5a5c69;
}

body {
    background-color: #f8f9fc;
}

/* Card Styles */
.card {
    border: none;
    border-radius: 15px;
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.25);
}

.card-header {
    background: linear-gradient(135deg, var(--primary-color), #3a5cbe);
    border-radius: 15px 15px 0 0 !important;
    padding: 1.5rem;
}

/* Room Details Styles */
.room-details {
    background: var(--light-color);
    border-radius: 12px;
    padding: 1.5rem;
    margin-top: 1.5rem;
}

.detail-item {
    background: white;
    padding: 1rem;
    border-radius: 10px;
    margin-bottom: 1rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    transition: all 0.2s ease;
}

.detail-item:hover {
    transform: translateX(5px);
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.detail-item i {
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    margin-right: 1rem;
    background: var(--light-color);
}

/* Room Image Styles */
.room-image-container {
    position: relative;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.room-number-badge {
    position: absolute;
    top: 20px;
    right: 20px;
    background: rgba(78, 115, 223, 0.9);
    color: white;
    padding: 10px 20px;
    border-radius: 25px;
    font-weight: 600;
    backdrop-filter: blur(5px);
    box-shadow: 0 4px 10px rgba(0,0,0,0.2);
}

/* Floating Feedback Button */
.floating-feedback-btn {
    position: fixed;
    bottom: 30px;
    right: 30px;
    background: var(--primary-color);
    color: white;
    padding: 15px 25px;
    border-radius: 50px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
    z-index: 1000;
    transition: all 0.3s ease;
}

.floating-feedback-btn:hover {
    transform: translateY(-5px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.3);
    background: #3a5cbe;
}

.floating-feedback-btn i {
    font-size: 1.2rem;
}

/* Modal Styles */
.modal-content {
    border: none;
    border-radius: 15px;
}

.modal-header {
    border-radius: 15px 15px 0 0;
    background: linear-gradient(135deg, var(--primary-color), #3a5cbe);
}

.modal-body {
    padding: 2rem;
}

/* Responsive Design */
@media (max-width: 768px) {
    .floating-feedback-btn span {
        display: none;
    }
    
    .floating-feedback-btn {
        padding: 15px;
        border-radius: 50%;
    }
    
    .detail-item {
        flex-direction: column;
        text-align: center;
    }
    
    .detail-item i {
        margin-bottom: 10px;
        margin-right: 0;
    }
}

/* Animation for elements */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.card, .detail-item {
    animation: fadeIn 0.5s ease-out forwards;
}

/* Update existing styles */
.main-content {
    padding: 80px;
    min-height: 100vh;
    background-color: #f8f9fc;
}

.card {
    border: none;
    border-radius: 15px;
    box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
    margin-bottom: 20px;
}

.room-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    padding: 20px;
    background: var(--light-color);
    border-radius: 12px;
}

.detail-item {
    display: flex;
    align-items: center;
    padding: 1.5rem;
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    transition: all 0.2s ease;
}

.room-image-container {
    max-width: 1200px;
    margin: 0 auto;
}

.no-image-placeholder {
    height: 450px;
    background: #f8f9fa;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border-radius: 15px;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .room-details {
        grid-template-columns: 1fr;
    }
    
    .detail-item {
        padding: 1rem;
    }
    
    .room-image-container img {
        height: 300px !important;
    }
}

.move-out-section {
    border-top: 1px solid #e3e6f0;
    padding-top: 20px;
}

.alert {
    border-radius: 10px;
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.alert-info {
    background-color: #e1f0ff;
    color: #0c5460;
}

.alert-warning {
    background-color: #fff3cd;
    color: #856404;
}

#moveOutModal .modal-content {
    border: none;
    border-radius: 15px;
}

#moveOutModal .modal-header {
    border-radius: 15px 15px 0 0;
}

#target_date {
    background-color: #fff;
}

/* Add these styles */
.modal-content {
    border: none;
    border-radius: 15px;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

.modal-header {
    border-radius: 15px 15px 0 0;
    border-bottom: none;
}

.modal-header .btn-close {
    background-color: rgba(0, 0, 0, 0.1);
    border-radius: 50%;
    padding: 0.5rem;
}

.modal-body {
    padding: 1.5rem;
}

.alert-info {
    background-color: #f8f9fa;
    border-left: 4px solid #17a2b8;
    border-radius: 4px;
}

.alert-info ul {
    padding-left: 20px;
}

textarea.form-control {
    resize: none;
}

.form-control:focus {
    border-color: #ffc107;
    box-shadow: 0 0 0 0.2rem rgba(255, 193, 7, 0.25);
}

.btn-warning {
    background-color: #ffc107;
    border-color: #ffc107;
    color: #000;
}

.btn-warning:hover {
    background-color: #e0a800;
    border-color: #d39e00;
    color: #000;
}

/* Add animation for modal */
.modal.fade .modal-dialog {
    transform: scale(0.8);
    transition: transform 0.3s ease-out;
}

.modal.show .modal-dialog {
    transform: scale(1);
}

/* Add to your existing styles */
.current-date {
    background: #fff;
    padding: 8px 15px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    font-size: 1rem;
    color: #4e73df;
    display: flex;
    align-items: center;
}

.current-date i {
    margin-right: 8px;
    color: #4e73df;
}

/* Update existing topbar styles */
.topbar {
    padding: 1rem 2rem;
    background: #f8f9fc;
    border-bottom: 1px solid #e3e6f0;
}

.topbar h2 {
    margin-bottom: 0;
}
</style>

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

    <!-- Move Out Request Modal -->
    <div class="modal fade" id="moveOutModal" tabindex="-1" aria-labelledby="moveOutModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title" id="moveOutModalLabel">
                        <i class="fas fa-door-open me-2"></i>Request Move Out
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="user_room.php" method="POST">
                        <div class="mb-3">
                            <label for="move_out_reason" class="form-label">Reason for Moving Out <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="move_out_reason" name="move_out_reason" 
                                    rows="4" required 
                                    placeholder="Please provide your reason for moving out..."></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="target_date" class="form-label">Target Move Out Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="target_date" name="target_date" 
                                   min="<?php echo date('Y-m-d'); ?>" 
                                   required>
                            <small class="text-muted">Please select your intended move-out date</small>
                        </div>

                        <div class="alert alert-info mb-3">
                            <i class="fas fa-info-circle me-2"></i>
                            Please note:
                            <ul class="mb-0 mt-2">
                                <li>Your request will need administrator approval</li>
                                <li>Ensure all pending payments are settled</li>
                                <li>Room inspection will be scheduled before move-out</li>
                            </ul>
                        </div>

                        <input type="hidden" name="request_move_out" value="1">
                        
                        <div class="d-flex justify-content-end gap-2">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-paper-plane me-2"></i>Submit Request
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Update the JavaScript to allow today's date
    document.addEventListener('DOMContentLoaded', function() {
        // Set minimum date to today
        const today = new Date();
        const todayFormatted = today.toISOString().split('T')[0];
        document.getElementById('target_date').min = todayFormatted;
        
        // Set maximum date to 3 months from now
        const maxDate = new Date();
        maxDate.setMonth(maxDate.getMonth() + 3);
        const maxDateFormatted = maxDate.toISOString().split('T')[0];
        document.getElementById('target_date').max = maxDateFormatted;
    });
    </script>

    <script>
    // Function to confirm feedback deletion
    function confirmDeleteFeedback(deleteUrl) {
        Swal.fire({
            title: 'Are you sure?',
            text: 'Do you want to delete this feedback?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = deleteUrl;
            }
        });
        return false;
    }

    // Function to show success message after form submission
    function showSuccessMessage(message) {
        Swal.fire({
            title: 'Success!',
            text: message,
            icon: 'success',
            confirmButtonColor: '#3085d6'
        }).then((result) => {
            window.location.reload();
        });
    }

    // Function to show error message
    function showErrorMessage(message) {
        Swal.fire({
            title: 'Error!',
            text: message,
            icon: 'error',
            confirmButtonColor: '#3085d6'
        });
    }
    </script>

<script>
function confirmLogout() {
    Swal.fire({
        title: 'Logout Confirmation',
        text: 'Are you sure you want to log out?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, logout',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '../config/user-logout.php';
        }
    });
    return false;
}
</script>

<?php if (!empty($sweetAlertMessages)): ?>
<script>
    <?php foreach ($sweetAlertMessages as $message): ?>
    Swal.fire({
        title: <?php echo json_encode($message['title']); ?>,
        text: <?php echo json_encode($message['text']); ?>,
        icon: <?php echo json_encode($message['icon']); ?>,
        confirmButtonColor: '#3085d6'
    })<?php if (isset($message['redirect'])): ?>.then((result) => {
        window.location.href = <?php echo json_encode($message['redirect']); ?>;
    })<?php endif; ?>;
    <?php endforeach; ?>
</script>
<?php endif; ?>
</body>
</html>
