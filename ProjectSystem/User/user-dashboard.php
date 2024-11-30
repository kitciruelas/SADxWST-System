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
    // Debug logging
    error_log("POST data received: " . print_r($_POST, true));
    
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
            $_SESSION['swal_error'] = [
                'title' => 'Error!',
                'text' => 'User not logged in. Please log in to apply.',
                'icon' => 'error'
            ];
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }

        // Fetch the old room ID and number based on the current user
        $stmt = $conn->prepare("SELECT ra.room_id, r.room_number 
                               FROM roomassignments ra 
                               JOIN rooms r ON ra.room_id = r.room_id 
                               WHERE ra.user_id = ? 
                               ORDER BY ra.assignment_date DESC 
                               LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $_SESSION['swal_error'] = [
                    'title' => 'Error!',
                    'text' => 'No current room assignment found.',
                    'icon' => 'error'
                ];
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }
            
            $currentRoom = $result->fetch_assoc();
            $oldRoomId = $currentRoom['room_id'];
            $oldRoomNumber = $currentRoom['room_number'];
            $stmt->close();
        } else {
            $_SESSION['swal_error'] = [
                'title' => 'Error!',
                'text' => 'Could not fetch current room assignment.',
                'icon' => 'error'
            ];
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }

        // Fetch the new room details
        $stmt = $conn->prepare("SELECT room_number, status FROM rooms WHERE room_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $roomId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $_SESSION['swal_error'] = [
                    'title' => 'Error!',
                    'text' => 'Selected room does not exist.',
                    'icon' => 'error'
                ];
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }
            
            $newRoom = $result->fetch_assoc();
            $newRoomNumber = $newRoom['room_number'];
            
            // Check if room is under maintenance
            if ($newRoom['status'] === 'maintenance') {
                $_SESSION['swal_error'] = [
                    'title' => 'Error!',
                    'text' => 'This room is under maintenance and cannot be selected.',
                    'icon' => 'error'
                ];
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }
            
            $stmt->close();
        } else {
            $_SESSION['swal_error'] = [
                'title' => 'Error!',
                'text' => 'Could not fetch new room details.',
                'icon' => 'error'
            ];
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }

        // Check if trying to reassign to same room
        if ($oldRoomId === $roomId) {
            $_SESSION['swal_error'] = [
                'title' => 'Error!',
                'text' => 'You are already assigned to this room.',
                'icon' => 'error'
            ];
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        }

        // Check for existing pending reassignment requests
        $stmt = $conn->prepare("SELECT reassignment_id FROM room_reassignments 
                               WHERE user_id = ? AND status = 'pending'");
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $_SESSION['swal_error'] = [
                    'title' => 'Error!',
                    'text' => 'You already have a pending reassignment request.',
                    'icon' => 'error'
                ];
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            }
            $stmt->close();
        }

        // Insert the reassignment request
        $stmt = $conn->prepare("INSERT INTO room_reassignments 
                               (user_id, old_room_id, new_room_id, comment, status) 
                               VALUES (?, ?, ?, ?, 'pending')");
        if ($stmt) {
            $stmt->bind_param("iiis", $userId, $oldRoomId, $roomId, $comments);
            
            if ($stmt->execute()) {
                // Log the activity
                $activityType = "Room Reassignment Request";
                $activityDetails = "Requested reassignment from Room $oldRoomNumber to Room $newRoomNumber. Reason: $comments";
                
                $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, activity_type, activity_details) 
                                         VALUES (?, ?, ?)");
                if ($logStmt) {
                    $logStmt->bind_param("iss", $userId, $activityType, $activityDetails);
                    $logStmt->execute();
                    $logStmt->close();
                }

                $_SESSION['swal_success'] = [
                    'title' => 'Success!',
                    'text' => 'Reassignment request submitted successfully!',
                    'icon' => 'success'
                ];
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            } else {
                $_SESSION['swal_error'] = [
                    'title' => 'Error!',
                    'text' => 'Error submitting request: ' . htmlspecialchars($stmt->error),
                    'icon' => 'error'
                ];
                header("Location: " . $_SERVER['PHP_SELF']);
            }
            $stmt->close();
        } else {
            $_SESSION['swal_error'] = [
                'title' => 'Error!',
                'text' => 'Error preparing request: ' . htmlspecialchars($conn->error),
                'icon' => 'error'
            ];
            header("Location: " . $_SERVER['PHP_SELF']);
        }
    } else {
        $_SESSION['swal_error'] = [
            'title' => 'Error!',
            'text' => 'Invalid room ID provided.',
            'icon' => 'error'
        ];
        header("Location: " . $_SERVER['PHP_SELF']);
    }
}

// Move connection close to the end of the file
// $conn->close(); // This should be the last line before the HTML starts

// Move the room query here and keep connection open
$sql = "SELECT 
    r.room_id, 
    r.room_number, 
    r.room_desc, 
    r.capacity, 
    r.room_monthlyrent, 
    r.status, 
    r.room_pic,
    (SELECT COUNT(*) FROM roomassignments WHERE room_id = r.room_id) AS current_occupants 
FROM rooms r";

$result = $conn->query($sql);

if ($result === false) {
    error_log("Query Error: " . $conn->error);
} 

// Process POST request for room reassignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['room_id'])) {
    // Debug logging
    error_log("POST data received: " . print_r($_POST, true));
    
    // Capture form data with additional validation
    $roomId = isset($_POST['room_id']) ? intval($_POST['room_id']) : null;
    $comments = isset($_POST['comments']) ? trim($_POST['comments']) : '';

    // Debug logging
    error_log("Processed roomId: " . var_export($roomId, true));

    // Enhanced validation
    if (!$roomId || $roomId <= 0) {
        echo "<script>alert('Invalid room ID: Room ID must be a positive number.');window.history.back();</script>";
        exit;
    }

    // Verify room exists and is available
    $checkRoom = $conn->prepare("SELECT room_id, status FROM rooms WHERE room_id = ?");
    if ($checkRoom) {
        $checkRoom->bind_param("i", $roomId);
        $checkRoom->execute();
        $result = $checkRoom->get_result();
        
        if ($result->num_rows === 0) {
            echo "<script>alert('Invalid room ID: Room does not exist.');window.history.back();</script>";
            $checkRoom->close();
            exit;
        }

        $roomData = $result->fetch_assoc();
        if ($roomData['status'] === 'maintenance') {
            echo "<script>alert('This room is under maintenance and cannot be selected.');window.history.back();</script>";
            $checkRoom->close();
            exit;
        }
        $checkRoom->close();
    }

    // Ensure user is logged in
    $userId = $_SESSION['id'] ?? null;
    if (!$userId) {
        echo "<script>alert('User not logged in. Please log in to apply.');window.history.back();</script>";
        exit;
    }

    // Get current room assignment
    $stmt = $conn->prepare("SELECT ra.room_id, r.room_number 
                           FROM roomassignments ra 
                           JOIN rooms r ON ra.room_id = r.room_id 
                           WHERE ra.user_id = ? 
                           ORDER BY ra.assignment_date DESC 
                           LIMIT 1");
    
    if (!$stmt) {
        error_log("Error preparing statement: " . $conn->error);
        echo "<script>alert('Database error. Please try again later.');window.history.back();</script>";
        exit;
    }

    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo "<script>alert('No current room assignment found. Please contact administrator.');window.history.back();</script>";
        $stmt->close();
        exit;
    }

    $currentAssignment = $result->fetch_assoc();
    $oldRoomId = $currentAssignment['room_id'];
    $stmt->close();

    // Check for pending reassignment requests
    $checkPending = $conn->prepare("SELECT reassignment_id FROM room_reassignments 
                                  WHERE user_id = ? AND status = 'pending'");
    $checkPending->bind_param("i", $userId);
    $checkPending->execute();
    $pendingResult = $checkPending->get_result();
    
    if ($pendingResult->num_rows > 0) {
        echo "<script>alert('You already have a pending room reassignment request.');window.history.back();</script>";
        $checkPending->close();
        exit;
    }
    $checkPending->close();

    // Insert reassignment request
    $stmt = $conn->prepare("INSERT INTO room_reassignments (user_id, old_room_id, new_room_id, comment, status) 
                           VALUES (?, ?, ?, ?, 'pending')");
    
    if (!$stmt) {
        error_log("Error preparing insert statement: " . $conn->error);
        echo "<script>alert('Database error. Please try again later.');window.history.back();</script>";
        exit;
    }

    $stmt->bind_param("iiis", $userId, $oldRoomId, $roomId, $comments);
    
    if ($stmt->execute()) {
        echo "<script>alert('Room reassignment request submitted successfully!');window.location.href = '" . $_SERVER['PHP_SELF'] . "';</script>";
        exit;
    } else {
        error_log("Error executing insert: " . $stmt->error);
        echo "<script>alert('Error submitting request. Please try again.');window.history.back();</script>";
    }
    $stmt->close();
}
// Debug: Print the SQL query
echo "<!-- SQL Query: " . htmlspecialchars($sql) . " -->";

if ($result === false) {
    echo "Query Error: " . $conn->error;
} else {
    // Debug: Print the first row
    $firstRow = $result->fetch_assoc();
    echo "<!-- First Row Data: " . print_r($firstRow, true) . " -->";
    // Reset the result pointer
    $result->data_seek(0);
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home</title>


<!-- Font Awesome CSS for icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link rel="stylesheet" href="../Admin/Css_Admin/admin_manageuser.css"> <!-- I-load ang custom CSS sa huli -->

<!-- Your custom CSS (placed last to ensure it overrides Bootstrap) -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Include SweetAlert CSS and JS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    .main-content{
        padding-top: 20;
    }
    /* Announcement Box Styling */
    .announcement-box {
        background: #fff;
        border-radius: 15px;
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        padding: 25px;
        margin: 20px 30px; /* Adjusted margin */
        max-width: calc(100% - 60px); /* Account for left/right margin */
    }

    .announcement-box h2 {
        color: #2c3e50;
        font-size: 28px;
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 2px solid #e74c3c;
    }

    .announcement-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        padding: 10px;
        text-align: center; /* Center text content */
    }

    .announcement-item {
        background: #ffffff;
        border: none;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        transition: all 0.3s ease;
        margin-bottom: 0;
        display: flex;
        flex-direction: column;
        align-items: center; /* Center items vertically */
        justify-content: center; /* Center items horizontally */
    }

    .announcement-item::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        background: #3498db;
    }

    .announcement-item:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
    }

    .announcement-item h3 {
        color: #2c3e50;
        font-size: 20px;
        margin-bottom: 15px;
        font-weight: 600;
        text-align: center; /* Center heading */
    }

    .announcement-item p {
        color: #555;
        line-height: 1.6;
        margin-bottom: 12px;
        text-align: center; /* Center paragraphs */
    }

    /* Updated Room Card Styling */
    .room-card {
        width: calc((100% - 60px) / 3); /* Exactly 3 cards per row with 30px gaps */
        margin: 0; /* Remove margin as we're using gap */
        min-width: 300px;
    }

    .card {
        height: 100%;
        border: none;
        border-radius: 15px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        background: #fff;
    }

    .card-img-top {
        height: 220px;
        object-fit: cover;
        border-top-left-radius: 15px;
        border-top-right-radius: 15px;
    }

    .card-body {
        padding: 20px;
        display: flex;
        flex-direction: column;
    }

    .card-title {
        font-size: 18px;
        margin-bottom: 10px;
    }

    .room-price {
        font-size: 18px;
        margin-bottom: 10px;
    }

    .status-badge {
        position: absolute;
        top: 15px;
        right: 15px;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 600;
        z-index: 1;
    }

    /* Container adjustments */
    .container {
        max-width: 1400px;
        padding: 0 30px; /* Consistent with announcement box */
        margin: 30px auto; /* Add top/bottom margin */
    }

    .row {
        display: flex;
        flex-wrap: wrap;
        gap: 30px; /* Consistent gap between cards */
        justify-content: flex-start;
    }

    .col-md-3 {
        padding: 10px; /* Even spacing between cards */
    }

    /* Responsive adjustments */
    @media (max-width: 1200px) {
        .room-card {
            width: calc((100% - 30px) / 2); /* 2 cards per row */
        }
    }

    @media (max-width: 768px) {
        .room-card {
            width: 100%; /* 1 card per row */
        }
        
        .container {
            padding: 0 15px;
        }
        
        .announcement-box {
            margin: 20px 15px;
            padding: 20px;
        }
    }

    .apply-btn {
        width: 100%;
        padding: 12px;
        font-size: 16px;
        font-weight: 600;
        border-radius: 8px;
        background: linear-gradient(135deg, #3498db, #2980b9);
        border: none;
        color: white;
        transition: all 0.3s ease;
    }

    .apply-btn:hover {
        background: linear-gradient(135deg, #2980b9, #2573a7);
        transform: translateY(-2px);
    }

    .btn-warning, .btn-danger {
        width: 100%;
        padding: 12px;
        font-size: 16px;
        font-weight: 600;
        border-radius: 8px;
    }

    /* Status Badge */
    .status-badge {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 15px;
    }

    .status-available {
        background-color: #2ecc71;
        color: white;
    }

    .status-occupied {
        background-color: #e74c3c;
        color: white;
    }

    .status-maintenance {
        background-color: #f1c40f;
        color: white;
    }

    /* Room Box Styling */
    .room-box {
        background: #fff;
        border-radius: 15px;
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        padding: 25px;
        margin: 20px 30px; /* Adjusted margin */
        max-width: calc(100% - 60px); /* Account for left/right margin */
    }

    .room-box h2 {
        color: #2c3e50;
        font-size: 28px;
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 2px solid #e74c3c;
    }

    .room-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        padding: 10px;
    }

    .room-item {
        background: #ffffff;
        border: none;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        transition: all 0.3s ease;
        margin-bottom: 0; /* Remove bottom margin */
    }

    .room-item::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        background: #3498db;
    }

    .room-item:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
    }

    .room-item h3 {
        color: #2c3e50;
        font-size: 20px;
        margin-bottom: 15px;
        font-weight: 600;
    }

    .room-item p {
        color: #555;
        line-height: 1.6;
        margin-bottom: 12px;
    }

    .room-image {
        position: relative;
    }

    .room-image img {
        width: 100%;
        height: 220px;
        object-fit: cover;
        border-top-left-radius: 15px;
        border-top-right-radius: 15px;
    }

    .status-badge {
        position: absolute;
        top: 15px;
        right: 15px;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 600;
        z-index: 1;
    }

    .room-details {
        padding: 20px;
        display: flex;
        flex-direction: column;
    }

    .room-price {
        font-size: 18px;
        margin-bottom: 10px;
    }

    .occupancy {
        display: flex;
        align-items: center;
        margin-bottom: 10px;
    }

    .occupancy i {
        margin-right: 5px;
    }

    .description {
        margin-bottom: 12px;
    }

    .btn-maintenance {
        width: 100%;
        padding: 12px;
        font-size: 16px;
        font-weight: 600;
        border-radius: 8px;
        background: linear-gradient(135deg, #e74c3c, #c0392b);
        border: none;
        color: white;
        transition: all 0.3s ease;
    }

    .btn-maintenance:hover {
        background: linear-gradient(135deg, #c0392b, #992d22);
        transform: translateY(-2px);
    }

    .btn-apply {
        width: 100%;
        padding: 12px;
        font-size: 16px;
        font-weight: 600;
        border-radius: 8px;
        background: linear-gradient(135deg, #3498db, #2980b9);
        border: none;
        color: white;
        transition: all 0.3s ease;
    }

    .btn-apply:hover {
        background: linear-gradient(135deg, #2980b9, #2573a7);
        transform: translateY(-2px);
    }

    .btn-occupied {
        width: 100%;
        padding: 12px;
        font-size: 16px;
        font-weight: 600;
        border-radius: 8px;
        background: linear-gradient(135deg, #e74c3c, #c0392b);
        border: none;
        color: white;
        transition: all 0.3s ease;
    }

    .btn-occupied:hover {
        background: linear-gradient(135deg, #c0392b, #992d22);
        transform: translateY(-2px);
    }

    /* Room Header with Filter */
    .room-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 2px solid #3498db;
    }

    .room-header h2 {
        margin: 0;
        padding: 0;
        border: none;
    }

    .filter-container {
        min-width: 200px;
    }

    .form-select {
        padding: 8px 12px;
        border-radius: 8px;
        border: 2px solid #3498db;
        background-color: white;
        color: #2c3e50;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .form-select:hover {
        border-color: #2980b9;
    }

    .form-select:focus {
        outline: none;
        box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
    }

    @media (max-width: 768px) {
        .room-header {
            flex-direction: column;
            gap: 15px;
        }

        .filter-container {
            width: 100%;
        }
    }

    /* Modal Styles */
    .modal-content {
        border: none;
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    }

    .modal-header {
        background: linear-gradient(135deg, #3498db, #2980b9);
        color: white;
        border-top-left-radius: 15px;
        border-top-right-radius: 15px;
        padding: 20px;
    }

    .modal-title {
        font-size: 1.25rem;
        font-weight: 600;
    }

    .modal-title i {
        margin-right: 10px;
    }

    .btn-close {
        color: white;
        opacity: 1;
    }

    .modal-body {
        padding: 25px;
    }

    /* Room Details Section */
    .room-details-section {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
        margin-bottom: 25px;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 10px;
    }

    .detail-item {
        display: flex;
        align-items: flex-start;
        gap: 12px;
    }

    .detail-item i {
        color: #3498db;
        font-size: 1.2rem;
        margin-top: 3px;
    }

    .detail-item label {
        font-size: 0.9rem;
        color: #666;
        margin-bottom: 2px;
    }

    .detail-item p {
        font-size: 1.1rem;
        font-weight: 600;
        color: #2c3e50;
        margin: 0;
    }

    /* Reasons Section */
    .reasons-section {
        margin-bottom: 20px;
    }

    .reasons-section label {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 10px;
        color: #2c3e50;
        font-weight: 500;
    }

    .reasons-section textarea {
        border: 2px solid #e0e0e0;
        border-radius: 10px;
        padding: 12px;
        font-size: 1rem;
        transition: all 0.3s ease;
    }

    .reasons-section textarea:focus {
        border-color: #3498db;
        box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        outline: none;
    }

    /* Modal Footer */
    .modal-footer {
        border-top: 1px solid #eee;
        padding: 20px;
    }

    .btn {
        padding: 10px 20px;
        border-radius: 8px;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .btn i {
        margin-right: 8px;
    }

    .btn-secondary {
        background-color: #e0e0e0;
        border: none;
        color: #333;
    }

    .btn-secondary:hover {
        background-color: #d0d0d0;
    }

    .btn-primary {
        background: linear-gradient(135deg, #3498db, #2980b9);
        border: none;
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #2980b9, #2573a7);
        transform: translateY(-1px);
    }

    /* Responsive Adjustments */
    @media (max-width: 768px) {
        .room-details-section {
            grid-template-columns: 1fr;
            gap: 15px;
        }

        .modal-dialog {
            margin: 10px;
        }
    }

    /* Room Description Section */
    .room-description-section {
        margin-bottom: 20px;
    }

    .room-description-section label {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 10px;
        color: #2c3e50;
        font-weight: 500;
    }

    .room-description-section textarea {
        border: 2px solid #e0e0e0;
        border-radius: 10px;
        padding: 12px;
        font-size: 1rem;
        transition: all 0.3s ease;
    }

    .room-description-section textarea:focus {
        border-color: #3498db;
        box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        outline: none;
    }

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

    /* Update existing topbar styles if needed */
    .topbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 30px;
        background: white;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
</style>
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
        <a href="user-payment.php" class="nav-link"><i class="fas fa-money-bill-alt"></i> <span>Payment History</span></a>



        </div>
        
        <div class="logout">
        <a href="../config/user-logout.php" id="logoutLink">
            <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
        </a>
        </div>
    </div>

    <!-- Top bar -->
    <div class="topbar">
        <h2>Welcome to Dormio, <?php echo htmlspecialchars($_SESSION["username"]); ?>!</h2>
        
        <!-- Profile Button -->
        <a href="profile.php" class="profile-btn">
            <i class="fas fa-user"></i>
            <span>Profile</span>
        </a>
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
    <!-- Room Cards Container -->
    <div class="room-box">
        <div class="room-header">
            <h2><i class="fas fa-door-open"></i> Available Rooms</h2>
            <div class="filter-container">
                <select id="statusFilter" class="form-select" onchange="filterRooms()">
                    <option value="">All Rooms</option>
                    <option value="available">Available</option>
                    <option value="occupied">Occupied</option>
                    <option value="maintenance">Maintenance</option>
                </select>
            </div>
        </div>
        <div class="room-container">
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
                    <div class="room-item">
                        <div class="room-image">
                            <?php if (!empty($row['room_pic']) && file_exists("../uploads/" . $row['room_pic'])): ?>
                                <img src="<?php echo htmlspecialchars("../uploads/" . $row['room_pic']); ?>" 
                                     alt="Room Image" 
                                     onclick="openModal('<?php echo htmlspecialchars("../uploads/" . $row['room_pic']); ?>')">
                            <?php else: ?>
                                <img src="path/to/default/image.jpg" alt="No Image Available">
                            <?php endif; ?>
                            <div class="status-badge <?php echo 'status-'.strtolower($status); ?>">
                                <?php echo htmlspecialchars($status); ?>
                            </div>
                        </div>
                        <div class="room-details">
                            <h3>Room <?php echo htmlspecialchars($row['room_number']); ?></h3>
                            <p class="room-price">₱<?php echo number_format($row['room_monthlyrent'], 2); ?> / Monthly</p>
                            <p class="occupancy">
                                <i class="fas fa-users"></i>
                                <?php echo htmlspecialchars($currentOccupants) . '/' . htmlspecialchars($totalCapacity); ?> people
                            </p>
                            <p class="description"><?php echo htmlspecialchars($row['room_desc']); ?></p>
                            
                            <?php if ($status === 'Maintenance'): ?>
                                <button class="btn-maintenance" disabled>Under Maintenance</button>
                            <?php elseif ($currentOccupants < $totalCapacity): ?>
                                <button class="btn-apply" data-bs-toggle="modal" data-bs-target="#applyModal"
                                    data-room-id="<?php echo htmlspecialchars($row['room_id']); ?>"
                                    data-room-number="<?php echo htmlspecialchars($row['room_number']); ?>"
                                    data-room-price="<?php echo htmlspecialchars($row['room_monthlyrent']); ?>"
                                    data-room-capacity="<?php echo htmlspecialchars($row['capacity']); ?>"
                                    data-room-desc="<?php echo htmlspecialchars($row['room_desc']); ?>"
                                    data-current-occupants="<?php echo htmlspecialchars($currentOccupants); ?>"
                                    data-room-status="<?php echo htmlspecialchars($row['status']); ?>">
                                    Request Reassignment
                                </button>
                            <?php else: ?>
                                <button class="btn-occupied" disabled>Fully Occupied</button>
                            <?php endif; ?>
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


    </div>
   



            
    </div>
    </div>
    
    <!-- Modal HTML Structure -->
<div class="modal fade" id="applyModal" tabindex="-1" aria-labelledby="applyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="applyModalLabel">
                    <i class="fas fa-exchange-alt"></i> Room Reassignment Request
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="applyForm" method="POST">
                    <input type="hidden" name="room_id" id="room_id_input">
                    
                    <!-- Room Details Section -->
                    <div class="room-details-section">
                        <div class="detail-item">
                            <i class="fas fa-door-open"></i>
                            <div>
                                <label>Room Number:</label>
                                <p id="modalRoomNumber"></p>
                            </div>
                        </div>
                        
                        <div class="detail-item">
                            <i class="fas fa-money-bill-wave"></i>
                            <div>
                                <label>Monthly Rent:</label>
                                <p id="modalRoomPrice"></p>
                            </div>
                        </div>
                        
                        <div class="detail-item">
                            <i class="fas fa-users"></i>
                            <div>
                                <label>Capacity:</label>
                                <p id="modalRoomCapacity"></p>
                            </div>
                        </div>
                        
                        <div class="detail-item">
                            <i class="fas fa-info-circle"></i>
                            <div>
                                <label>Status:</label>
                                <p id="modalRoomStatus"></p>
                            </div>
                        </div>
                    </div>

                    <!-- Reasons Textarea -->
                    <div class="form-group mt-3">
                        <label for="comments">
                            <i class="fas fa-comment-alt"></i> Reason for Reassignment
                        </label>
                        <textarea 
                            id="comments" 
                            name="comments" 
                            class="form-control" 
                            required
                            placeholder="Please explain why you want to be reassigned to this room..."
                            rows="4"
                        ></textarea>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Submit Request
                        </button>
                    </div>
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
        const filterValue = document.getElementById('statusFilter').value.toLowerCase();
        const roomItems = document.querySelectorAll('.room-item');

        roomItems.forEach(item => {
            const statusBadge = item.querySelector('.status-badge');
            const status = statusBadge.textContent.toLowerCase().trim();

            if (filterValue === '' || status === filterValue) {
                item.style.display = '';
            } else {
                item.style.display = 'none';
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

    <!-- Add this JavaScript code after your existing scripts -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // When the modal is about to be shown
            const applyModal = document.getElementById('applyModal');
            applyModal.addEventListener('show.bs.modal', function (event) {
                // Button that triggered the modal
                const button = event.relatedTarget;
                
                // Extract data from data-* attributes
                const roomId = button.getAttribute('data-room-id');
                const roomNumber = button.getAttribute('data-room-number');
                const roomPrice = parseFloat(button.getAttribute('data-room-price')).toFixed(2);
                const roomCapacity = button.getAttribute('data-room-capacity');
                const currentOccupants = button.getAttribute('data-current-occupants');
                const roomStatus = button.getAttribute('data-room-status');
                const roomDesc = button.getAttribute('data-room-desc');

                // Update the modal's content
                const modal = this;
                modal.querySelector('#room_id_input').value = roomId;
                modal.querySelector('#modalRoomNumber').textContent = `Room ${roomNumber}`;
                modal.querySelector('#modalRoomPrice').textContent = `₱${roomPrice} / Monthly`;
                modal.querySelector('#modalRoomCapacity').textContent = `${currentOccupants}/${roomCapacity} people`;
                modal.querySelector('#modalRoomStatus').textContent = roomStatus;
            });
        });
    </script>

    <!-- Function to display SweetAlert messages based on session variables -->
    <script>
        function displayAlerts() {
            <?php if (isset($_SESSION['swal_error'])): ?>
                Swal.fire({
                    title: '<?php echo $_SESSION['swal_error']['title']; ?>',
                    text: '<?php echo $_SESSION['swal_error']['text']; ?>',
                    icon: '<?php echo $_SESSION['swal_error']['icon']; ?>',
                    confirmButtonText: 'OK'
                });
                <?php unset($_SESSION['swal_error']); // Clear the session variable after displaying ?>
            <?php endif; ?>

            <?php if (isset($_SESSION['swal_success'])): ?>
                Swal.fire({
                    title: '<?php echo $_SESSION['swal_success']['title']; ?>',
                    text: '<?php echo $_SESSION['swal_success']['text']; ?>',
                    icon: '<?php echo $_SESSION['swal_success']['icon']; ?>',
                    confirmButtonText: 'OK'
                });
                <?php unset($_SESSION['swal_success']); // Clear the session variable after displaying ?>
            <?php endif; ?>
        }

        // Call the function when the document is ready
        document.addEventListener('DOMContentLoaded', function() {
            displayAlerts();
        });
    </script>

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
</body>
</html>
