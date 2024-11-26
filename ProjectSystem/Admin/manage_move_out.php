<?php
session_start();
include '../config/config.php';

require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // Adjust path as needed
// Add these function definitions after the session_start() and includes
function sendMoveOutApprovalEmail($userEmail, $firstName, $lastName, $roomNumber) {
    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'dormioph@gmail.com';
        $mail->Password = 'ymrd smvk acxa whdy';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // Recipients
        $mail->setFrom('dormioph@gmail.com', 'Dormio Ph');
        $mail->addAddress($userEmail, "$firstName $lastName");

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Move-Out Request Approved';
        $mail->Body = "
            <h2>Move-Out Request Approved</h2>
            <p>Dear $firstName $lastName,</p>
            <p>Your move-out request for Room $roomNumber has been approved.</p>
            <p>Please ensure to complete your move-out process and return your keys to management.</p>
            <p>Best regards,<br>Dormio Ph Management</p>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}

function sendMoveOutRejectionEmail($userEmail, $firstName, $lastName, $roomNumber, $remarks) {
    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'dormioph@gmail.com';
        $mail->Password = 'ymrd smvk acxa whdy';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // Recipients
        $mail->setFrom('dormioph@gmail.com', 'Dormio Ph');
        $mail->addAddress($userEmail, "$firstName $lastName");

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Move-Out Request Rejected';
        $mail->Body = "
            <h2>Move-Out Request Update</h2>
            <p>Dear $firstName $lastName,</p>
            <p>We regret to inform you that your move-out request for Room $roomNumber has been rejected.</p>
            <p>Reason: $remarks</p>
            <p>If you have any questions, please contact the dormitory management.</p>
            <p>Best regards,<br>Dormio Ph Management</p>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}

// Handle request approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id = $_POST['request_id'];
    $action = $_POST['action']; // 'approve' or 'reject'
    $admin_remarks = isset($_POST['admin_remarks']) ? $_POST['admin_remarks'] : '';

    try {
        // Start transaction
        $conn->begin_transaction();

        // Update request status
        $updateRequest = "UPDATE move_out_requests 
                         SET status = ?, 
                             admin_remarks = ?,
                             processed_date = NOW()
                         WHERE request_id = ?";
        $stmt = $conn->prepare($updateRequest);
        $status = ($action === 'approve') ? 'approved' : 'rejected';
        $stmt->bind_param('ssi', $status, $admin_remarks, $request_id);
        $stmt->execute();

        if ($action === 'approve') {
            // Get request details
            $getRequest = "SELECT user_id, room_id FROM move_out_requests WHERE request_id = ?";
            $stmt = $conn->prepare($getRequest);
            $stmt->bind_param('i', $request_id);
            $stmt->execute();
            $request = $stmt->get_result()->fetch_assoc();

            // Remove room assignment immediately
            $deleteRoomAssign = "DELETE FROM roomassignments 
                                WHERE room_id = ? AND user_id = ?";
            $stmt = $conn->prepare($deleteRoomAssign);
            $stmt->bind_param('ii', $request['room_id'], $request['user_id']);
            $stmt->execute();

            // Update room status to available
            $updateRoom = "UPDATE rooms 
                          SET status = 'available'
                          WHERE room_id = ?";
            $stmt = $conn->prepare($updateRoom);
            $stmt->bind_param('i', $request['room_id']);
            $stmt->execute();

            // Log activity
            $activityType = "Move Out Request Approved";
            $activityDetails = "Admin approved move-out request for Room ID: " . $request['room_id'];
            
            $logSql = "INSERT INTO activity_logs (user_id, activity_type, activity_details) 
                       VALUES (?, ?, ?)";
            $stmt = $conn->prepare($logSql);
            $stmt->bind_param('iss', $request['user_id'], $activityType, $activityDetails);
            $stmt->execute();

            // Get user email and details for notification
            $getUserDetails = "SELECT u.email, u.fname, u.lname, r.room_number 
                              FROM users u 
                              JOIN move_out_requests m ON u.id = m.user_id
                              JOIN rooms r ON m.room_id = r.room_id
                              WHERE m.request_id = ?";
            $stmt = $conn->prepare($getUserDetails);
            $stmt->bind_param('i', $request_id);
            $stmt->execute();
            $userDetails = $stmt->get_result()->fetch_assoc();

            // Send approval email
            if ($userDetails) {
                sendMoveOutApprovalEmail(
                    $userDetails['email'],
                    $userDetails['fname'],
                    $userDetails['lname'],
                    $userDetails['room_number']
                );
            }
        } else {
            // Get user email and details for rejection notification
            $getUserDetails = "SELECT u.email, u.fname, u.lname, r.room_number 
                              FROM users u 
                              JOIN move_out_requests m ON u.id = m.user_id
                              JOIN rooms r ON m.room_id = r.room_id
                              WHERE m.request_id = ?";
            $stmt = $conn->prepare($getUserDetails);
            $stmt->bind_param('i', $request_id);
            $stmt->execute();
            $userDetails = $stmt->get_result()->fetch_assoc();

            // Send rejection email
            if ($userDetails) {
                sendMoveOutRejectionEmail(
                    $userDetails['email'],
                    $userDetails['fname'],
                    $userDetails['lname'],
                    $userDetails['room_number'],
                    $admin_remarks
                );
            }
        }

        $conn->commit();
        echo "<script>alert('Request " . ($action === 'approve' ? 'approved' : 'rejected') . " successfully.');</script>";
    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('Error processing request: " . $e->getMessage() . "');</script>";
    }
}

// Modify the query section before HTML
$where_conditions = [];
$params = [];
$param_types = '';

// Handle search
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $where_conditions[] = "(u.fname LIKE ? OR u.lname LIKE ? OR r.room_number LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $param_types .= 'sss';
}

// Handle status filter
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $where_conditions[] = "mor.status = ?";
    $params[] = $_GET['status'];
    $param_types .= 's';
}

// Build the WHERE clause
$where_clause = !empty($where_conditions) ? " WHERE " . implode(" AND ", $where_conditions) : "";

// Handle sorting
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'request_date';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Whitelist allowed sort columns
$allowed_sort_columns = ['request_date', 'target_date', 'room_number', 'status'];
if (!in_array($sort_column, $allowed_sort_columns)) {
    $sort_column = 'request_date';
}

$query = "SELECT mor.*, 
          u.fname, u.lname, 
          r.room_number,
          DATEDIFF(mor.target_date, CURDATE()) as days_until_moveout
          FROM move_out_requests mor
          JOIN users u ON mor.user_id = u.id
          JOIN rooms r ON mor.room_id = r.room_id
          $where_clause
          ORDER BY $sort_column $sort_order";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Move-out Requests</title>
    <link rel="stylesheet" href="Css_Admin/admin_manageuser.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .requests-container {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 25px;
    padding: 20px 0;
}

.request-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    padding: 20px;
    transition: transform 0.2s, box-shadow 0.2s;
    border: 1px solid #e0e0e0;
}

.request-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
}

.request-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
}

.request-header h3 {
    margin: 0;
    color: #2c3e50;
    font-size: 1.2rem;
}

.room-number {
    color: #3498db;
    font-weight: bold;
}

.status-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-badge.pending {
    background-color: #fff3cd;
    color: #856404;
}

.status-badge.approved {
    background-color: #d4edda;
    color: #155724;
}

.status-badge.rejected {
    background-color: #f8d7da;
    color: #721c24;
}

.request-details {
    padding: 10px 0;
}

.request-details p {
    margin: 12px 0;
    color: #555;
    line-height: 1.6;
}

.request-details i {
    width: 20px;
    color: #666;
    margin-right: 8px;
}

.tenant-name {
    color: #2c3e50;
    font-weight: 600;
}

.days-urgent {
    color: #dc3545;
    font-weight: bold;
}

.days-warning {
    color: #ffc107;
    font-weight: bold;
}

.days-normal {
    color: #28a745;
    font-weight: bold;
}

.action-buttons {
    display: flex;
    gap: 10px;
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #eee;
}

.btn {
    flex: 1;
    padding: 10px 15px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.btn.approve {
    background-color: #28a745;
    color: white;
}

.btn.approve:hover {
    background-color: #218838;
}

.btn.reject {
    background-color: #dc3545;
    color: white;
}

.btn.reject:hover {
    background-color: #c82333;
}

.btn i {
    font-size: 0.9rem;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 1000;
}

.modal-content {
    background-color: #fff;
    border-radius: 12px;
    padding: 25px;
    width: 90%;
    max-width: 500px;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}

.modal-content h3 {
    margin: 0 0 20px 0;
    color: #2c3e50;
}

.modal-content textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    margin-bottom: 20px;
    min-height: 100px;
    resize: vertical;
}

.modal-buttons {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

.modal-buttons button {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s ease;
}

.modal-buttons button:first-child {
    background-color: #6c757d;
    color: white;
}

.modal-buttons button:first-child:hover {
    background-color: #5a6268;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .requests-container {
        grid-template-columns: 1fr;
    }
    
    .modal-content {
        width: 95%;
        margin: 10px;
    }
}

.back-button {
    position: absolute;
    left: 20px;
    top: 20px;
}

.back-button .btn {
    background: none;
    border: none;
    color: #333;
    cursor: pointer;
    padding: 10px;
    transition: transform 0.2s;
    text-decoration: none;
}

.back-button .btn:hover {
    transform: scale(1.1);
    text-decoration: none;
}

.back-button .fas {
    font-size: 2em;
}

/* Adjust the container to account for the back button */
.container {
    position: relative;
    padding-top: 60px; /* Add space for the back button */
}

.filters-container {
    margin: 20px 0;
    background: #fff;
    padding: 15px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.filters-form {
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: nowrap;
}

.search-box {
    flex: 2;
}

.search-box input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.filter-box, .sort-box {
    flex: 1;
}

.filter-box select, .sort-box select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: white;
}

.filter-btn, .reset-btn {
    padding: 8px 15px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 600;
    white-space: nowrap;
    background: #6c757d;
    color: white;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 5px;
    margin-top: 10px;
}

.filter-btn {
    background: #007bff;
    color: white;
}

.reset-btn i {
    font-size: 0.9em;
}

.reset-btn:hover {
    background: #5a6268;
}

@media (max-width: 1200px) {
    .filters-form {
        flex-wrap: wrap;
    }
    
    .search-box {
        flex: 1 1 100%;
    }
    
    .filter-box, .sort-box {
        flex: 1 1 auto;
    }
}
    </style>
</head>
<body>
    <!-- Add Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="menu" id="hamburgerMenu">
            <i class="fas fa-bars"></i>
        </div>
        <div class="sidebar-nav">
            <a href="dashboard.php" class="nav-link"><i class="fas fa-home"></i> <span>Home</span></a>
            <a href="manageuser.php" class="nav-link"><i class="fas fa-users"></i> <span>Manage User</span></a>
            <a href="admin-room.php" class="nav-link active"><i class="fas fa-building"></i> <span>Room Manager</span></a>
            <a href="admin-visitor_log.php" class="nav-link"><i class="fas fa-address-book"></i> <span>Log Visitor</span></a>
            <a href="admin-monitoring.php" class="nav-link"><i class="fas fa-eye"></i> <span>Presence Monitoring</span></a>
            <a href="admin-chat.php" class="nav-link"><i class="fas fa-comments"></i> <span>Group Chat</span></a>
            <a href="rent_payment.php" class="nav-link"><i class="fas fa-money-bill-alt"></i> <span>Rent Payment</span></a>
            <a href="activity-logs.php" class="nav-link"><i class="fas fa-clipboard-list"></i> <span>Activity Logs</span></a>
        </div>
        <div class="logout">
            <a href="../config/logout.php" onclick="return confirmLogout();">
                <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
            </a>
        </div>
    </div>

    <!-- Add Topbar -->
    <div class="topbar">
        <h2>Manage Move-out Requests</h2>
    </div>
    <div class="main-content">
        <div class="container">
            <!-- Add back button -->
            <div class="back-button">
                <a href="admin-room.php" class="btn">
                    <i class="fas fa-arrow-left"></i>
                </a>
            </div>
            
            <div class="filters-container">
                <form method="GET" class="filters-form">
                    <div class="search-box">
                        <input type="text" name="search" placeholder="Search by name or room number" 
                               value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                    </div>
                    
                    <div class="filter-box">
                        <select name="status">
                            <option value="">All Statuses</option>
                            <option value="pending" <?php echo isset($_GET['status']) && $_GET['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo isset($_GET['status']) && $_GET['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo isset($_GET['status']) && $_GET['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>

                    <div class="sort-box">
                        <select name="sort">
                            <option value="request_date" <?php echo isset($_GET['sort']) && $_GET['sort'] === 'request_date' ? 'selected' : ''; ?>>Request Date</option>
                            <option value="target_date" <?php echo isset($_GET['sort']) && $_GET['sort'] === 'target_date' ? 'selected' : ''; ?>>Target Date</option>
                            <option value="room_number" <?php echo isset($_GET['sort']) && $_GET['sort'] === 'room_number' ? 'selected' : ''; ?>>Room Number</option>
                            <option value="status" <?php echo isset($_GET['sort']) && $_GET['sort'] === 'status' ? 'selected' : ''; ?>>Status</option>
                        </select>
                        
                        <select name="order">
                            <option value="DESC" <?php echo isset($_GET['order']) && $_GET['order'] === 'DESC' ? 'selected' : ''; ?>>Descending</option>
                            <option value="ASC" <?php echo isset($_GET['order']) && $_GET['order'] === 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                        </select>
                    </div>

                    <button type="submit" class="filter-btn">Apply Filters</button>
                    <a href="manage_move_out.php" class="reset-btn">
                        <i class="fas fa-undo"></i>
                    </a>
                </form>
            </div>
            
            <div class="requests-container">
                <?php while ($request = $result->fetch_assoc()): ?>
                    <div class="request-card <?php echo $request['status']; ?>">
                        <div class="request-header">
                            <h3><i class="fas fa-home"></i> Room <span class="room-number"><?php echo htmlspecialchars($request['room_number']); ?></span></h3>
                            <span class="status-badge <?php echo $request['status']; ?>">
                                <?php echo ucfirst($request['status']); ?>
                            </span>
                        </div>
                        
                        <div class="request-details">
                            <p>
                                <i class="fas fa-user"></i>
                                <strong>Resident:</strong> 
                                <span class="tenant-name"><?php echo htmlspecialchars($request['fname'] . ' ' . $request['lname']); ?></span>
                            </p>
                            <p>
                                <i class="fas fa-calendar-alt"></i>
                                <strong>Request Date:</strong> 
                                <span class="request-date"><?php echo date('F d, Y', strtotime($request['request_date'])); ?></span>
                            </p>
                            <p>
                                <i class="fas fa-calendar-check"></i>
                                <strong>Target Move-out:</strong> 
                                <span class="target-date"><?php echo date('F d, Y', strtotime($request['target_date'])); ?></span>
                            </p>
                            <p>
                                <i class="fas fa-clock"></i>
                                <strong>Days Until Move-out:</strong> 
                                <span class="<?php 
                                    if ($request['days_until_moveout'] <= 7) echo 'days-urgent';
                                    else if ($request['days_until_moveout'] <= 14) echo 'days-warning';
                                    else echo 'days-normal';
                                ?>">
                                    <?php echo $request['days_until_moveout']; ?> days
                                </span>
                            </p>
                            <p>
                                <i class="fas fa-comment"></i>
                                <strong>Reason:</strong><br>
                                <?php echo nl2br(htmlspecialchars($request['reason'])); ?>
                            </p>
                        </div>

                        <?php if ($request['status'] === 'pending'): ?>
                            <div class="action-buttons">
                                <button class="btn approve" onclick="showApprovalModal(<?php echo (int)$request['request_id']; ?>)">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                                <button class="btn reject" onclick="showRejectionModal(<?php echo (int)$request['request_id']; ?>)">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <!-- Approval Modal -->
    <div id="approvalModal" class="modal">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="request_id" id="approval_request_id">
                <input type="hidden" name="action" value="approve">
                <h3>Approve Move-out Request</h3>
                <textarea name="admin_remarks" placeholder="Add any remarks (optional)"></textarea>
                <div class="modal-buttons">
                    <button type="button" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="approve">Approve</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Rejection Modal -->
    <div id="rejectionModal" class="modal">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="request_id" id="rejection_request_id">
                <input type="hidden" name="action" value="reject">
                <h3>Reject Move-out Request</h3>
                <textarea name="admin_remarks" placeholder="Provide reason for rejection" required></textarea>
                <div class="modal-buttons">
                    <button type="button" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="reject">Reject</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function showApprovalModal(requestId) {
            document.getElementById('approval_request_id').value = requestId;
            document.getElementById('approvalModal').style.display = 'block';
        }

        function showRejectionModal(requestId) {
            document.getElementById('rejection_request_id').value = requestId;
            document.getElementById('rejectionModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('approvalModal').style.display = 'none';
            document.getElementById('rejectionModal').style.display = 'none';
        }

        // Add click outside modal to close
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                closeModal();
            }
        }

        // Add hamburger menu functionality
        const hamburgerMenu = document.getElementById('hamburgerMenu');
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.add('collapsed');

        hamburgerMenu.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            const icon = hamburgerMenu.querySelector('i');
            icon.classList.toggle('fa-bars');
            icon.classList.toggle('fa-times');
        });

        // Add logout confirmation
        function confirmLogout() {
            return confirm("Are you sure you want to log out?");
        }
    </script>
</body>
</html> 