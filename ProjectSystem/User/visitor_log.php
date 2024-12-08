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

    // Function to log activities
    function logActivity($conn, $userId, $activityType, $activityDetails) {
        $sql = "INSERT INTO activity_logs (user_id, activity_type, activity_details) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $userId, $activityType, $activityDetails);
        if (!$stmt->execute()) {
            error_log("Failed to log activity: " . $stmt->error);
        }
        $stmt->close();
    }

    // Add Visitor Logic
    if (isset($_POST['visitor_name'])) {
        $visitorName = trim($_POST['visitor_name']);
        $contactInfo = trim($_POST['contact_info']);
        $purpose = trim($_POST['purpose']);
        $checkInTime = $_POST['check_in_time'];

        // Validate required fields
        if (empty($visitorName) || empty($contactInfo) || empty($purpose) || empty($checkInTime)) {
            $_SESSION['swal_error'] = [
                'title' => 'Error',
                'text' => 'All fields are required!',
                'icon' => 'error'
            ];
            header("Location: visitor_log.php");
            exit();
        }

        // Validate contact info format
        if (!preg_match('/^(0?[9]\d{9}|[9]\d{9})$/', $contactInfo)) {
            $_SESSION['swal_error'] = [
                'title' => 'Invalid Contact Info',
                'text' => 'Contact Info must be a valid Philippine phone number (10 or 11 digits).',
                'icon' => 'error'
            ];
            header("Location: visitor_log.php");
            exit();
        }

        // Format check-in datetime
        $checkInDatetime = date('Y-m-d') . ' ' . $checkInTime . ':00';

        // Use prepared statement for insert
        $stmt = $conn->prepare(
            "INSERT INTO visitors (name, contact_info, purpose, visiting_user_id, check_in_time) 
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("sssis", $visitorName, $contactInfo, $purpose, $userId, $checkInDatetime);

        if ($stmt->execute()) {
            // Log the activity
            logActivity($conn, $userId, "Add Visitor", "Visitor '$visitorName' added successfully.");
            
            $_SESSION['swal_success'] = [
                'title' => 'Success!',
                'text' => 'Visitor added successfully!',
                'icon' => 'success'
            ];
        } else {
            $_SESSION['swal_error'] = [
                'title' => 'Error',
                'text' => 'Error adding visitor: ' . $stmt->error,
                'icon' => 'error'
            ];
        } 
        $stmt->close();
        header("Location: visitor_log.php");
        exit();
    }

    // Check-Out Visitor Logic
    if (isset($_POST['visitor_id'])) {
        $visitorId = (int)$_POST['visitor_id'];

        $stmt = $conn->prepare(
            "UPDATE visitors SET check_out_time = NOW() WHERE id = ? AND visiting_user_id = ?"
        );
        $stmt->bind_param("ii", $visitorId, $userId);

        if ($stmt->execute()) {
            // Fetch visitor's name for logging
            $fetchNameSql = "SELECT name FROM visitors WHERE id = ?";
            $nameStmt = $conn->prepare($fetchNameSql);
            $nameStmt->bind_param("i", $visitorId);
            $nameStmt->execute();
            $nameStmt->bind_result($visitorName);
            $nameStmt->fetch();
            $nameStmt->close();

            logActivity($conn, $userId, "Check-Out Visitor", "Visitor '$visitorName' checked out.");
            $_SESSION['swal_success'] = [
                'title' => 'Success!',
                'text' => 'Check-out successful!',
                'icon' => 'success'
            ];
            header("Location: visitor_log.php");
            exit();
        } else {
            $_SESSION['swal_error'] = [
                'title' => 'Error',
                'text' => 'Error updating check-out time: ' . $stmt->error,
                'icon' => 'error'
            ];
            header("Location: visitor_log.php");
            exit();
        }
    }

    // Archive Visitor Logic
    if (isset($_POST['delete_visitor_id'])) {
        $visitorId = (int)$_POST['delete_visitor_id'];

        // Fetch visitor's name for logging
        $fetchNameSql = "SELECT name FROM visitors WHERE id = ?";
        $nameStmt = $conn->prepare($fetchNameSql);
        $nameStmt->bind_param("i", $visitorId);
        $nameStmt->execute();
        $nameStmt->bind_result($visitorName);
        $nameStmt->fetch();
        $nameStmt->close();

        // Update archive_status to 'archived'
        $archiveSql = "UPDATE visitors SET archive_status = 'archived' WHERE id = ? AND visiting_user_id = ?";
        $stmt = $conn->prepare($archiveSql);
        $stmt->bind_param("ii", $visitorId, $userId);

        if ($stmt->execute()) {
            logActivity($conn, $userId, "Archive Visitor", "Visitor '$visitorName' archived.");
            $_SESSION['swal_success'] = [
                'title' => 'Success!',
                'text' => 'Visitor deleted successfully!',
                'icon' => 'success'
            ];
        } else {
            $_SESSION['swal_error'] = [
                'title' => 'Error',
                'text' => 'Error archiving visitor: ' . $stmt->error,
                'icon' => 'error'
            ];
        }
        $stmt->close();
        header("Location: visitor_log.php");
        exit();
    }

    // Edit Visitor Logic
    if (isset($_POST['edit_id']) && isset($_POST['edit_msg'])) {
        $visitorId = (int)$_POST['edit_id'];
        $name = mysqli_real_escape_string($conn, $_POST['edit_msg']['name']);
        $contactInfo = mysqli_real_escape_string($conn, $_POST['edit_msg']['contact_info']);
        $purpose = mysqli_real_escape_string($conn, $_POST['edit_msg']['purpose']);

        // Validate contact info format
        if (!preg_match('/^(0?[9]\d{9}|[9]\d{9})$/', $contactInfo)) {
            $_SESSION['swal_error'] = [
                'title' => 'Invalid Contact Info',
                'text' => 'Contact Info must be a valid Philippine phone number (10 or 11 digits).',
                'icon' => 'error'
            ];
            header("Location: visitor_log.php");
            exit();
        }

        if (empty($name) || empty($contactInfo) || empty($purpose)) {
            $_SESSION['swal_error'] = [
                'title' => 'Error',
                'text' => 'All fields are required!',
                'icon' => 'error'
            ];
            header("Location: visitor_log.php");
            exit();
        }

        // Use prepared statement for update
        $stmt = $conn->prepare("UPDATE visitors SET name = ?, contact_info = ?, purpose = ? WHERE id = ? AND visiting_user_id = ?");
        $stmt->bind_param("sssii", $name, $contactInfo, $purpose, $visitorId, $userId);

        if ($stmt->execute()) {
            logActivity($conn, $userId, "Edit Visitor", "Visitor ID '$visitorId' updated successfully.");
            $_SESSION['swal_success'] = [
                'title' => 'Success!',
                'text' => 'Visitor updated successfully',
                'icon' => 'success'
            ];
        } else {
            $_SESSION['swal_error'] = [
                'title' => 'Error',
                'text' => 'Failed to update visitor: ' . $stmt->error,
                'icon' => 'error'
            ];
        }
        $stmt->close();
        header("Location: visitor_log.php");
        exit();
    }
}

// Fetch Visitors based on filters and sorting
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name'; // Default sort by name

$sql = "SELECT v.*, CONCAT(u.fname, ' ', u.lname) AS visiting_person 
        FROM visitors v 
        LEFT JOIN users u ON v.visiting_user_id = u.id 
        WHERE v.visiting_user_id = ? AND v.archive_status = 'active'";

// Modify query based on selected filter
if ($filter == 'today') {
    $sql .= " AND DATE(v.check_in_time) = CURDATE()"; // Filter for today's visits
} elseif ($filter == 'this_week') {
    $sql .= " AND WEEK(v.check_in_time) = WEEK(CURDATE())"; // Filter for this week's visits
} elseif ($filter == 'this_month') {
    $sql .= " AND MONTH(v.check_in_time) = MONTH(CURDATE())"; // Filter for this month's visits
}

$sql .= " ORDER BY v." . $sort;

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
    <title>Visitor Log</title>
    <link rel="icon" href="../img-icon/visit1.webp" type="image/png">

    <link rel="stylesheet" href="../Admin/Css_Admin/style.css"> <!-- I-load ang custom CSS sa huli -->
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
<style>
.main-content {
   padding-top: 80px;
}
</style>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <?php
    // Display SweetAlert messages
    if (isset($_SESSION['swal_error'])) {
        $error = $_SESSION['swal_error'];
        echo "<script>
            Swal.fire({
                icon: '{$error['icon']}',
                title: '{$error['title']}',
                text: '{$error['text']}',
                confirmButtonColor: '#3085d6'
            });
        </script>";
        unset($_SESSION['swal_error']);
    }

    if (isset($_SESSION['swal_success'])) {
        $success = $_SESSION['swal_success'];
        echo "<script>
            Swal.fire({
                icon: '{$success['icon']}',
                title: '{$success['title']}',
                text: '{$success['text']}',
                confirmButtonColor: '#3085d6'
            });
        </script>";
        unset($_SESSION['swal_success']);
    }
    ?>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="menu" id="hamburgerMenu">
            <i class="fas fa-bars"></i> <!-- Hamburger menu icon -->
        </div>

        <div class="sidebar-nav">
        <a href="user-dashboard.php" class="nav-link"><i class="fas fa-home"></i><span>Home</span></a>
        <a href="user_room.php" class="nav-link"><i class="fas fa-key"></i> <span>Room Assign</span></a>
        <a href="visitor_log.php" class="nav-link active"><i class="fas fa-user-check"></i> <span>Log Visitor</span></a>
        <a href="chat.php" class="nav-link"><i class="fas fa-comments"></i> <span>Group Chat</span></a>
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
        <h2>Visitor Log</h2>

    </div>
    <div class="main-content">      
    <div class="container mt-1">
        <div class="controls-wrapper mb-4">
            <div class="row g-4 align-items-center">
                <!-- Search Input -->
                <div class="col-12 col-md-5 mt-2">
                    <form method="GET" action="" class="search-form">
                        <div class="input-group">
                            <input type="text" id="searchInput" name="search" class="form-control custom-input-small" 
                                placeholder="Search for visitors..." 
                                value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                            <span class="input-group-text">
                                <i class="fas fa-search"></i>
                            </span>
                        </div>
                    </form>
                </div>

                <!-- Filter Section -->
                <div class="col-6 col-md-2">
                    <select id="filterSelect" class="form-select" onchange="filterTable()">
                        <option value="">All</option>
                        <option value="today">Today</option>
                        <option value="this_week">This Week</option>
                        <option value="this_month">This Month</option>
                    </select>
                </div>

                <!-- Sort Section -->
                <div class="col-6 col-md-2">
                    <select id="sortSelect" class="form-select" onchange="sortTable()">
                        <option value="" selected>Sort by</option>
                        <option value="Visiting Person_asc">Visiting Person (A to Z)</option>
                        <option value="Visiting Person_desc">Visiting Person (Z to A)</option>
                        <option value="check_in_asc">Check-In (Earliest)</option>
                        <option value="check_in_desc">Check-In (Latest)</option>
                        <option value="check_out_asc">Check-Out (Earliest)</option>
                        <option value="check_out_desc">Check-Out (Latest)</option>
                    </select>
                </div>

                <!-- Log Visitor Button -->
                <div class="col-md-3 text-end">
                    <button class="btn btn-primary w-50" data-bs-toggle="modal" data-bs-target="#visitorModal">
                        <i class="fas fa-plus me-2"></i>Log Visitor
                    </button>
                </div>
            </div>
        </div>

        <!-- Visitor Log Table -->
        <div class="table-responsive">
            <table class="table table-bordered" id="visitorTable">
                <thead class="table-light">
                    <tr>
                        <th scope="col">No.</th>
                        <th scope="col">Visiting Person</th>
                        <th scope="col">Contact Info</th>
                        <th scope="col">Purpose</th>
                       <!--  <th scope="col">Name</th>-->
                        <th scope="col">Check-In</th>
                        <th scope="col">Check-Out</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    date_default_timezone_set('Asia/Manila'); // Set timezone to Philippine Time

                    if ($result->num_rows > 0): 
                        $counter = 1;
                        while ($row = $result->fetch_assoc()):
                            $isCheckedOut = !empty($row['check_out_time']);
                            $checkInDateTime = date("Y-m-d g:i A", strtotime($row['check_in_time'])); // Date with AM/PM
                            $checkOutDateTime = $isCheckedOut ? date("Y-m-d g:i A", strtotime($row['check_out_time'])) : 'N/A'; // Check-out date and time or 'N/A'
                    ?>
                        <tr>
                            <td><?= $counter++ ?></td>
                            <td><?= htmlspecialchars($row['name']) ?></td>
                            <td><?= htmlspecialchars($row['contact_info']) ?></td>
                            <td><?= htmlspecialchars($row['purpose']) ?></td>
                           <!--  <td><?= htmlspecialchars($row['visiting_person']) ?></td>-->
                            <td><?= $checkInDateTime ?></td>
                            <td><?= $checkOutDateTime ?></td>
                            <td>
                            <div class="d-flex justify-content-center gap-2">
                            <?php if (!$isCheckedOut): ?>
                                <button type="button" class="btn btn-success btn-sm" onclick="handleCheckout(<?= $row['id'] ?>)">Check-Out</button>
                            <?php else: ?>
                                <button type="button" class="btn btn-secondary btn-sm" disabled>Checked-Out</button>
                            <?php endif; ?>
                            
                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editVisitorModal<?= $row['id'] ?>">Edit</button>

                            <button type="button" class="btn btn-danger btn-sm" onclick="handleDelete(<?= $row['id'] ?>)">
                                Delete
                            </button>
                        </div>
                            </td>
                        </tr>

              <!-- Edit Modal -->
    <div class="modal fade" id="editVisitorModal<?= $row['id'] ?>" tabindex="-1" aria-labelledby="editVisitorModalLabel<?= $row['id'] ?>" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editVisitorModalLabel<?= $row['id'] ?>">Edit Visitor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="visitor_log.php" method="post">
                        <input type="hidden" name="edit_id" value="<?= $row['id'] ?>">
                        <div class="mb-3">
                            <label for="editVisitorName<?= $row['id'] ?>" class="form-label">Visitor's Name</label>
                            <input type="text" class="form-control" id="editVisitorName<?= $row['id'] ?>" name="edit_msg[name]" value="<?= htmlspecialchars($row['name']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="editContactInfo<?= $row['id'] ?>" class="form-label">Contact Info</label>
                            <input type="text" class="form-control" id="editContactInfo<?= $row['id'] ?>" name="edit_msg[contact_info]" value="<?= htmlspecialchars($row['contact_info']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="editPurpose<?= $row['id'] ?>" class="form-label">Purpose</label>
                            <input type="text" class="form-control" id="editPurpose<?= $row['id'] ?>" name="edit_msg[purpose]" value="<?= htmlspecialchars($row['purpose']) ?>" required>
                        </div>
                        <div class="text-center">
                            <button type="submit" class="btn btn-success">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>


                    <?php endwhile; else: ?>
                        <tr>
                            <td colspan="8" class="text-center">No visitors found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

        </div>

        <style>
            
        
            /* Controls section styling */
          
            /* Search input styling */
            .input-group .form-control {
                border-radius: 8px;
                padding: 0.75rem 2.25rem 0.75rem 1rem;
                border: 2px solid #e0e0e0;
                transition: all 0.3s ease;
            }

            .input-group .form-control:focus {
                border-color: #2B228A;
                box-shadow: 0 0 0 0.2rem rgba(43, 34, 138, 0.1);
            }

            /* Filter and Sort controls */
            .form-select {
                border-radius: 8px;
                border: 2px solid #e0e0e0;
                background-color: white;
                cursor: pointer;
                min-width: 160px;
            }

            .form-select:focus {
                border-color: #2B228A;
                box-shadow: 0 0 0 0.2rem rgba(43, 34, 138, 0.1);
            }

            /* Labels */
            .form-label {
                color: #495057;
                font-weight: 500;
                margin-bottom: 0;
            }

            /* Log Visitor button */
            .btn-primary {
                background-color: #2B228A;
                border: none;
                padding: 0.75rem 1.5rem;
                border-radius: 8px;
                font-weight: 500;
                transition: all 0.3s ease;
            }

            .btn-primary:hover {
                background-color: #201b68;
                transform: translateY(-1px);
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            }

            /* Table styling */
            .table-responsive {
                border-radius: 10px;
                overflow: hidden;
                box-shadow: 0 0 15px rgba(0,0,0,0.05);
            }

            .table {
                margin-bottom: 0;
            }

            .table thead th {
                background-color: #2B228A;
                color: white;
                font-weight: 500;
                padding: 1rem;
                border: none;
                white-space: nowrap;
            }

            .table tbody tr {
                transition: all 0.3s ease;
            }

            .table tbody tr:hover {
                background-color: #f8f9fa;
            }

            .table td {
                padding: 1rem;
                vertical-align: middle;
                border-color: #e9ecef;
            }

            /* Action buttons */
            .btn-sm {
                padding: 0.4rem 0.8rem;
                font-size: 0.875rem;
                border-radius: 6px;
                margin: 0 0.2rem;
            }

            .btn-secondary {
                background-color: #6c757d;
                border: none;
            }

            .btn-danger {
                background-color: #dc3545;
                border: none;
            }

            /* Responsive adjustments */
            @media (max-width: 768px) {
                .container {
                    padding: 1rem;
                }
                
                .controls-wrapper {
                    flex-direction: column;
                    gap: 1rem;
                }
                
                .input-group {
                    width: 100% !important;
                }
                
                .d-flex {
                    flex-direction: column;
                    gap: 1rem;
                }
                
                .form-select {
                    width: 100%;
                }
            }
            .btn-close {
    background: none;
    border: none;
    padding: 0.5rem;
    cursor: pointer;
    opacity: 0.75;
    transition: opacity 0.15s;
}

.btn-close:hover {
    opacity: 1;
}

.btn-close span {
    font-size: 1.5rem;
    color: #fff;
    line-height: 1;
}
        </style>

        <!-- Log Visitor Modal -->
        <div class="modal fade" id="visitorModal" tabindex="-1" aria-labelledby="visitorModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="visitorModalLabel">Log New Visitor</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
    <span aria-hidden="true">&times;</span>
</button>                    </div>
                    <div class="modal-body">
                        <form action="visitor_log.php" method="post" id="addVisitorForm">
                            <div class="mb-4">
                                <label for="visitorName" class="form-label">Visitor's Name</label>
                                <input type="text" class="form-control custom-input" id="visitorName" name="visitor_name" required>
                            </div>
                            <div class="mb-4">
                                <label for="contactInfo" class="form-label">Contact Info</label>
                                <input type="text" class="form-control custom-input" id="contactInfo" name="contact_info" 
                                    required pattern="^(0?[9]\d{9}|[9]\d{9})$" 
                                    title="Must be a valid Philippine phone number starting with 09 (10 or 11 digits)">
                                <small class="text-muted">Format: 09XXXXXXXXX</small>
                            </div>
                            <div class="mb-4">
                                <label for="purpose" class="form-label">Purpose of Visit</label>
                                <input type="text" class="form-control custom-input" id="purpose" name="purpose" required>
                            </div>
                            <div class="mb-4">
                                <label for="checkInTime" class="form-label">Check-In Time</label>
                                <input type="time" class="form-control custom-input" id="checkInTime" name="check_in_time" required>
                            </div>
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">Save and Check-In</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add this CSS -->
        <style>
            
        /* Modal styles */
        .modal-content {
            border: none;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }

        .modal-header {
            background-color: #2B228A;
            color: white;
            border-top-left-radius: 15px;
            border-top-right-radius: 15px;
            padding: 1.5rem;
        }

        .modal-body {
            padding: 2rem;
        }

        .btn-close {
            filter: brightness(0) invert(1);
        }

        /* Form styles */
        .form-label {
            font-weight: 500;
            color: #2B228A;
            margin-bottom: 0.5rem;
        }

        .custom-input {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 12px;
            transition: all 0.3s ease;
        }

        .custom-input:focus {
            border-color: #2B228A;
            box-shadow: 0 0 0 0.2rem rgba(43, 34, 138, 0.25);
        }

        .text-muted {
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }

        /* Button styles */
        .btn-primary {
            background-color: #2B228A;
            border: none;
            padding: 12px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background-color: #201b68;
            transform: translateY(-1px);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        /* Responsive adjustments */
        @media (max-width: 576px) {
            .modal-body {
                padding: 1.5rem;
            }
            
            .custom-input {
                padding: 10px;
            }
        }
        </style>

        <!-- JavaScript Libraries -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>


        <!-- Include jQuery and Bootstrap JS (required for dropdown) -->
        <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>

            
            <!-- JavaScript -->
            <script>
                // Function to filter the table based on the selected filter (Today, This Week, This Month)
    // Function to filter the table based on the selected filter (Today, This Week, This Month)
    function filterTable() {
        const filterValue = document.getElementById("filterSelect").value;
        const rows = document.querySelectorAll("#visitorTable tbody tr");

        rows.forEach(row => {
            const checkInTimeText = row.querySelector("td:nth-child(6)").textContent; // Check-In column
            const checkOutTimeText = row.querySelector("td:nth-child(7)").textContent; // Check-Out column
            const checkInDate = new Date(checkInTimeText);
            const currentDate = new Date();

            // Set flag to determine whether to display this row
            let shouldDisplay = true;

            if (filterValue === 'today') {
                // Filter by today (compare date only)
                if (checkInDate.toDateString() !== currentDate.toDateString()) {
                    shouldDisplay = false;
                }
            } else if (filterValue === 'this_week') {
                // Filter by this week (compare week number)
                const currentWeek = getWeekNumber(currentDate);
                const checkInWeek = getWeekNumber(checkInDate);
                if (currentWeek !== checkInWeek) {
                    shouldDisplay = false;
                }
            } else if (filterValue === 'this_month') {
                // Filter by this month
                if (checkInDate.getMonth() !== currentDate.getMonth()) {
                    shouldDisplay = false;
                }
            }

            // Show or hide the row based on filter match
            row.style.display = shouldDisplay ? '' : 'none';
        });
    }

    // Function to get the week number of the year for a given date
    function getWeekNumber(date) {
        const tempDate = new Date(date.getTime());
        tempDate.setHours(0, 0, 0, 0);
        tempDate.setDate(tempDate.getDate() + 3 - (tempDate.getDay() + 6) % 7);
        const firstThursday = tempDate.getTime();
        tempDate.setMonth(0, 1);
        return Math.ceil((((firstThursday - tempDate) / 86400000) + 1) / 7);
    }

    // Function to sort the table based on selected sort criteria (Name, Check-In Time, Check-Out Time)
    function sortTable() {
        const sortBy = document.getElementById("sortSelect").value;
        const rows = Array.from(document.querySelectorAll("#visitorTable tbody tr"));
        
        rows.sort((a, b) => {
            let valueA, valueB;
            
            switch (sortBy) {
                case "Visiting Person_asc":
                    valueA = a.querySelector("td:nth-child(2)").textContent.trim().toLowerCase();
                    valueB = b.querySelector("td:nth-child(2)").textContent.trim().toLowerCase();
                    return valueA.localeCompare(valueB);
                case "Visiting Person_desc":
                    valueA = a.querySelector("td:nth-child(2)").textContent.trim().toLowerCase();
                    valueB = b.querySelector("td:nth-child(2)").textContent.trim().toLowerCase();
                    return valueB.localeCompare(valueA);
                case "check_in_asc":
                    valueA = new Date(a.querySelector("td:nth-child(5)").textContent);
                    valueB = new Date(b.querySelector("td:nth-child(5)").textContent);
                    return valueA - valueB;
                case "check_in_desc":
                    valueA = new Date(a.querySelector("td:nth-child(5)").textContent);
                    valueB = new Date(b.querySelector("td:nth-child(5)").textContent);
                    return valueB - valueA;
                case "check_out_asc":
                    valueA = new Date(a.querySelector("td:nth-child(6)").textContent);
                    valueB = new Date(b.querySelector("td:nth-child(6)").textContent);
                    return valueA - valueB;
                case "check_out_desc":
                    valueA = new Date(a.querySelector("td:nth-child(6)").textContent);
                    valueB = new Date(b.querySelector("td:nth-child(6)").textContent);
                    return valueB - valueA;
                default:
                    return 0;
            }
        });

        // Reattach the sorted rows to the table body
        const tbody = document.querySelector("#visitorTable tbody");
        rows.forEach(row => tbody.appendChild(row));
    }

        // JavaScript to filter table based on search input
    function searchTable() {
        const searchInput = document.getElementById("searchInput").value.toLowerCase();
        const table = document.getElementById("visitorTable");
        const rows = table.getElementsByTagName("tr");

        for (let i = 1; i < rows.length; i++) { // Start from 1 to skip the header row
            const cells = rows[i].getElementsByTagName("td");
            let found = false;
            
            // Loop through each cell in the row and check if it contains the search term
            for (let j = 0; j < cells.length; j++) {
                if (cells[j].textContent.toLowerCase().includes(searchInput)) {
                    found = true;
                    break;
                }
            }

            // Show or hide row based on whether it matches the search input
            rows[i].style.display = found ? "" : "none";
        }
    }

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

        function handleCheckout(visitorId) {
            Swal.fire({
                title: 'Are you sure?',
                text: "Do you want to check out this visitor?",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, check out!'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Create and submit the form programmatically
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'visitor_log.php';
                    
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'visitor_id';
                    input.value = visitorId;
                    
                    form.appendChild(input);
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }

        function handleDelete(visitorId) {
            Swal.fire({
                title: 'Are you sure?',
                text: "This action cannot be undone!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Create and submit the form programmatically
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'visitor_log.php';
                    
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'delete_visitor_id';
                    input.value = visitorId;
                    
                    form.appendChild(input);
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }
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

<script>
    document.getElementById('searchInput').addEventListener('input', function() {
        const searchQuery = this.value.toLowerCase();
        const rows = document.querySelectorAll("#visitorTable tbody tr");

        rows.forEach(row => {
            const cells = row.getElementsByTagName('td');
            let match = false;

            for (let i = 0; i < cells.length; i++) {
                if (cells[i].textContent.toLowerCase().includes(searchQuery)) {
                    match = true;
                    break;
                }
            }

            row.style.display = match ? '' : 'none';
        });
    });
</script>
    </body>
    </html>

    