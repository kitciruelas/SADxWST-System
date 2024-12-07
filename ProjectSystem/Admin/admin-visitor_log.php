<?php
session_start();
include '../config/config.php'; // Correct path to your config file

// Function to log activities (ensure this function exists)
function logActivity($conn, $userId, $activityType, $activityDetails) {
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, activity_type, activity_details, activity_timestamp) VALUES (?, ?, ?, NOW())");
    if ($stmt === false) {
        die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
    }

    $bind = $stmt->bind_param("iss", $userId, $activityType, $activityDetails);
    if ($bind === false) {
        die("Bind failed: (" . $stmt->errno . ") " . $stmt->error);
    }

    $exec = $stmt->execute();
    if ($exec === false) {
        error_log("Execute failed: (" . $stmt->errno . ") " . $stmt->error);
    }

    $stmt->close();
}

// Check if the user is logged in
if (!isset($_SESSION['id'])) {
    header("Location: login.php"); // Redirect to login if not logged in
    exit();
}

// Check if the request is a POST request and handle accordingly
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle visitor deletion (set archive status)
    if (isset($_POST['visitor_id']) && !empty($_POST['visitor_id'])) {
        
        $visitor_id = intval($_POST['visitor_id']); // Sanitize input

        // Update the visitor's archive status to 'archived'
        $updateSql = "UPDATE visitors SET archive_status = 'archived' WHERE id = ?";
        $updateStmt = $conn->prepare($updateSql);

        if ($updateStmt) {
            $updateStmt->bind_param("i", $visitor_id);
            if ($updateStmt->execute()) {
                $_SESSION['swal_success'] = [
                    'title' => 'Success!',
                    'text' => 'Visitor deleted successfully!',
                    'icon' => 'success'
                ];
                header("Location: admin-visitor_log.php"); // Redirect after updating
                exit;
            } else {
                $_SESSION['swal_error'] = [
                    'title' => 'Error',
                    'text' => 'Error archiving visitor.',
                    'icon' => 'error'
                ];
            }
            $updateStmt->close();
        } else {
            $_SESSION['swal_error'] = [
                'title' => 'Error',
                'text' => 'Failed to prepare the update statement.',
                'icon' => 'error'
            ];
        }
    }
    // Handle visitor addition
    elseif (isset($_POST['visitor_name'])) {
        $visitorName = trim($_POST['visitor_name']);
        $contactInfo = trim($_POST['contact_info']);
        $purpose = trim($_POST['purpose']);
        $checkInTime = $_POST['check_in_time'];
        $userId = $_SESSION['id']; // Current logged-in staff user

        if (empty($visitorName) || empty($contactInfo) || empty($purpose) || empty($checkInTime) || empty($_POST['visiting_user_id'])) {
            $_SESSION['swal_error'] = [
                'title' => 'Error',
                'text' => 'All fields are required!',
                'icon' => 'error'
            ];
            header("Location: admin-visitor_log.php");
            exit();
        }

        // Validate that the selected visiting_user_id exists in the users table
        $visitingUserId = $_POST['visiting_user_id'];
        $stmt = $conn->prepare("SELECT COUNT(*) FROM `users` WHERE `id` = ?");
        $stmt->bind_param("i", $visitingUserId);
        $stmt->execute();
        $stmt->bind_result($userExists);
        $stmt->fetch();
        $stmt->close();

        if ($userExists == 0) {
            $_SESSION['swal_error'] = [
                'title' => 'Error',
                'text' => 'Invalid user ID!',
                'icon' => 'error'
            ];
            header("Location: admin-visitor_log.php");
            exit();
        }

        // Proceed with inserting the visitor record if everything is valid
        $checkInDatetime = date('Y-m-d') . ' ' . $checkInTime . ':00';
        $stmt = $conn->prepare(
            "INSERT INTO visitors (name, contact_info, purpose, visiting_user_id, check_in_time) 
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("sssis", $visitorName, $contactInfo, $purpose, $visitingUserId, $checkInDatetime);

        if ($stmt->execute()) {
            logActivity($conn, $userId, "Add Visitor", "Visitor '$visitorName' added successfully.");
            $_SESSION['swal_success'] = [
                'title' => 'Success!',
                'text' => 'Visitor added successfully!',
                'icon' => 'success'
            ];
            header("Location: admin-visitor_log.php");
        } else {
            $_SESSION['swal_error'] = [
                'title' => 'Error',
                'text' => 'Error adding visitor: ' . $stmt->error,
                'icon' => 'error'
            ];
            header("Location: admin-visitor_log.php");
        }
        $stmt->close();
        exit();
    }
    // Check-Out Visitor Logic
    elseif (isset($_POST['checkout_id'])) {
        $visitorId = (int)$_POST['checkout_id'];
        $userId = $_SESSION['id']; // Assuming this is the current logged-in user

        $stmt = $conn->prepare(
            "UPDATE visitors SET check_out_time = NOW() WHERE id = ?"
        );
        if ($stmt === false) {
            die("Prepare failed: (" . $conn->errno . ") " . $conn->error);
        }

        $stmt->bind_param("i", $visitorId);

        if ($stmt->execute()) {
            // Fetch visitor's name for logging
            $fetchNameSql = "SELECT name FROM visitors WHERE id = ?";
            $nameStmt = $conn->prepare($fetchNameSql);
            if ($nameStmt) {
                $nameStmt->bind_param("i", $visitorId);
                $nameStmt->execute();
                $nameStmt->bind_result($visitorName);
                $nameStmt->fetch();
                $nameStmt->close();

                logActivity($conn, $userId, "Check-Out Visitor", "Visitor '$visitorName' checked out.");
            }

            $_SESSION['swal_success'] = [
                'title' => 'Success!',
                'text' => 'Check-out successful!',
                'icon' => 'success'
            ];
            header("Location: admin-visitor_log.php");
            exit();
        } else {
            $_SESSION['swal_error'] = [
                'title' => 'Error',
                'text' => 'Error updating check-out time: ' . $stmt->error,
                'icon' => 'error'
            ];
            header("Location: admin-visitor_log.php");
            exit();
        }
    }
    // Handle visitor editing
    elseif (isset($_POST['edit_id'])) {
        $edit_id = intval($_POST['edit_id']);
        $edit_msg = $_POST['edit_msg'];

        // Sanitize and validate inputs
        $name = trim($edit_msg['name']);
        $contactInfo = trim($edit_msg['contact_info']);
        $purpose = trim($edit_msg['purpose']);
        $userId = $_SESSION['id']; // Current logged-in staff user

        // Basic validation
        if (empty($name) || empty($contactInfo) || empty($purpose)) {
            $_SESSION['swal_error'] = [
                'title' => 'Error',
                'text' => 'All fields are required for editing!',
                'icon' => 'error'
            ];
            header("Location: admin-visitor_log.php");
            exit();
        }

        // Use prepared statement for update
        $stmt = $conn->prepare("UPDATE visitors SET name = ?, contact_info = ?, purpose = ? WHERE id = ?");
        if ($stmt === false) {
            $_SESSION['swal_error'] = [
                'title' => 'Error',
                'text' => 'Prepare failed: ' . $conn->error,
                'icon' => 'error'
            ];
            header("Location: admin-visitor_log.php");
            exit();
        }

        $stmt->bind_param("sssi", $name, $contactInfo, $purpose, $edit_id);

        if ($stmt->execute()) {
            logActivity($conn, $userId, "Edit Visitor", "Visitor ID '$edit_id' updated successfully.");
            $_SESSION['swal_success'] = [
                'title' => 'Success!',
                'text' => 'Visitor updated successfully.',
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
        header("Location: admin-visitor_log.php");
        exit();
    }
    else {
        $_SESSION['swal_error'] = [
            'title' => 'Error',
            'text' => 'Invalid request: Missing required parameters.',
            'icon' => 'error'
        ];
        header("Location: admin-visitor_log.php");
        exit();
    }
}

// SQL query to fetch users from the users table
$sql = "SELECT id, fname, lname FROM users";
$result = mysqli_query($conn, $sql);

// Store the options in an array
$options = [];

if (mysqli_num_rows($result) > 0) {
    // Loop through the results and generate options for the dropdown
    while ($row = mysqli_fetch_assoc($result)) {
        // Safely output the user's first and last name using htmlspecialchars
        $fname = htmlspecialchars($row['fname']);
        $lname = htmlspecialchars($row['lname']);
        $options[] = "<option value='" . $row['id'] . "'>" . $fname . " " . $lname . "</option>";
    }
} else {
    // If no users found, add a "No users" option
    $options[] = "<option value=''>No users found</option>";
}

// Combine all options into a single string
$options_string = implode("\n", $options);

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

$query = "
    SELECT v.*, CONCAT(u.fname, ' ', u.lname) AS visiting_person
    FROM visitors v
    LEFT JOIN users u ON v.visiting_user_id = u.id
    WHERE v.archive_status = 'active'
    ORDER BY v.check_out_time IS NOT NULL, v.check_in_time, v.id DESC
";



$result = $conn->query($query);

$conn->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitor Logs</title>
    <link rel="icon" href="../img-icon/visit1.webp" type="image/png">

    <link rel="stylesheet" href="../Admin/Css_Admin/admin_manageuser.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.1/css/jquery.dataTables.min.css">

<!-- Bootstrap CSS -->
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>

<!-- Bootstrap JS -->
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<!-- DataTables Buttons CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.3/css/buttons.dataTables.min.css">

<!-- DataTables Buttons JS -->
<script src="https://cdn.datatables.net/buttons/2.2.3/js/dataTables.buttons.min.js"></script>

<!-- JSZip for Excel Export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>

<!-- pdfMake for PDF Export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>

<!-- DataTables Buttons for exporting -->
<script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.html5.min.js"></script>

<script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.print.min.js"></script>
<!-- Bootstrap 5 CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Bootstrap 5 JS Bundle (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<style>
    .container {
        background-color: transparent;
    }

 /* Enhanced table styles */
.table {
    background-color: white;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
}

.table th, .table td {
    text-align: center !important; /* Force center alignment */
    vertical-align: middle !important; /* Vertically center all content */
}

.table th {
    background-color: #2B228A;
    color: white;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.9rem;
    padding: 15px;
    border: none;
}

/* Add specific alignment for action buttons column if needed */
.table td:last-child {
    text-align: center !important;
}

/* Rest of your existing CSS remains the same */
    .table td {
        padding: 12px 15px;
        border-bottom: 1px solid #eee;
        transition: background-color 0.3s ease;
    }

    .table tbody tr:hover {
        background-color: #f8f9ff;
        transform: translateY(-1px);
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    }

    

    /* Button styling */
    .btn-primary {
        background-color: #2B228A;
        border: none;
        border-radius: 8px;
        padding: 8px 16px;
        transition: all 0.3s ease;
    }

    .btn-primary:hover {
        background-color: #1a1654;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

   /* Pagination styling */
   #pagination {
        margin-top: 20px;
        text-align: center;
    }

    #pagination button {
        background-color: #2B228A;
        color: white;
        border: none;
        padding: 8px 16px;
        margin: 0 5px;
        border-radius: 5px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    #pagination button:disabled {
        background-color:  #2B228A;
        cursor: not-allowed;
    }

    #pagination button:hover:not(:disabled) {
        background-color: #1a1654;
        transform: translateY(-1px);
    }

    #pageIndicator {
        margin: 0 15px;
        font-weight: 600;
    }
          /* Style for DataTables export buttons */
          .dt-buttons {
        margin-bottom: 15px;
    }
    
    .dt-button {
        background-color: #2B228A !important;
        color: white !important;
        border: none !important;
        padding: 5px 15px !important;
        border-radius: 4px !important;
        margin-right: 5px !important;
    }
    
    .dt-button:hover {
        background-color: #1a1555 !important;
    }
</style>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="menu" id="hamburgerMenu">
            <i class="fas fa-bars"></i> <!-- Hamburger menu icon -->
        </div>

        <div class="sidebar-nav">
  
<a href="dashboard.php" class="nav-link"><i class="fas fa-home"></i> <span>Home</span></a>
            <a href="manageuser.php" class="nav-link"><i class="fas fa-users"></i> <span>Manage User</span></a>
            <a href="admin-room.php" class="nav-link" ><i class="fas fa-building"></i> <span>Room Manager</span> </a>
            <a href="admin-visitor_log.php" class="nav-link active"><i class="fas fa-address-book"></i> <span>Log Visitor</span></a>
            <a href="admin-monitoring.php" class="nav-link"><i class="fas fa-eye"></i> <span>Monitoring</span></a>

            <a href="admin-chat.php" class="nav-link"><i class="fas fa-comments"></i> <span>Group Chat</span></a>
            <a href="rent_payment.php" class="nav-link"><i class="fas fa-money-bill-alt"></i> <span>Rent Payment</span></a>
            <a href="activity-logs.php" class="nav-link"><i class="fas fa-clipboard-list"></i> <span>Activity Logs</span></a>
        </div>
        
        <div class="logout">
        <a href="../config/logout.php" id="logoutLink">
            <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
        </a>
        </div>
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
    </div>

    <!-- Top bar -->
    <div class="topbar">
        <h2>Visitor Log</h2>
    
    </div>
    <div class="main-content">      
    <div class="container mt-1">
     <!-- Search and Filter Section -->
<div class="row mb-1">
    <!-- Search Input -->
    <div class="col-12 col-md-6">
        <form method="GET" action="" class="search-form">
            <div class="input-group">
                <input type="text" id="searchInput" name="search" class="form-control custom-input-small" 
                    placeholder="Search for visitors, residents, etc..." 
                    value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                <span class="input-group-text">
                    <i class="fas fa-search"></i>
                </span>
            </div>
        </form>
    </div>

    <!-- Filter Dropdown -->
    <div class="col-6 col-md-2 mt-2">
        <select id="filterSelect" class="form-select" onchange="filterTable()">
            <option value="all" selected>Filter by</option>
            <option value="name">Visiting Person</option>
            <option value="contact_info">Contact Info</option>
            <option value="purpose">Purpose</option>
            <option value="visiting_person">Resident Name</option>
        </select>
    </div>

    <!-- Sort Dropdown -->
    <div class="col-6 col-md-2 mt-2">
        <select id="sortSelect" class="form-select" onchange="sortTable()">
            <option value="" selected>Sort by</option>
            <option value="resident_asc">Resident (A to Z)</option>
            <option value="resident_desc">Resident (Z to A)</option>
            <option value="check_in_asc">Check-In (Earliest)</option>
            <option value="check_in_desc">Check-In (Latest)</option>
            <option value="check_out_asc">Check-Out (Earliest)</option>
            <option value="check_out_desc">Check-Out (Latest)</option>
        </select>
    </div>
        <div class="col-6 col-md-2 mt-2">

     <!-- <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#visitorModal">
            <i class="fas fa-plus"></i> Log New Visitor
        </button> -->
        </div>
</div>
<div class="table-responsive">
    <table class="table table-bordered" id="visitorTable">
        <thead>
            <tr>
                <th>No.</th>
                <th>Visiting Person</th>
                <th>Contact Info</th>
                <th>Purpose</th>
                <th>Resident Name</th>
                <th>Check-In</th>
                <th>Check-Out</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            if ($result && $result->num_rows > 0): 
                $counter = 1;
                while ($row = $result->fetch_assoc()):
                    $isCheckedOut = !empty($row['check_out_time']);
            ?>
                <tr>
                    <td><?= $counter++ ?></td>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= htmlspecialchars($row['contact_info']) ?></td>
                    <td><?= htmlspecialchars($row['purpose']) ?></td>
                    <td><?= htmlspecialchars($row['visiting_person']) ?></td>
                    <td><?= date("M d, Y g:i A", strtotime($row['check_in_time'])) ?></td>
                    <td><?= $isCheckedOut ? date("M d, Y g:i A", strtotime($row['check_out_time'])) : 'N/A' ?></td>
                    <td>
                        <div class="d-flex justify-content-center gap-2">
                          
                            

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
<!-- Modal Close Button -->
<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>                            </div>
                            <div class="modal-body">
                                <form action="admin-visitor_log.php" method="post">
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
                
            <?php 
                endwhile; 
            else: 
            ?>
                <tr>
                    <td colspan="8">No visitors found</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>


</div>

</div>
<style>
    /* Style for the entire table */
    .table {
        background-color: #f8f9fa; /* Light background for the table */
        border-collapse: collapse; /* Ensures borders don't double up */
    }

    /* Style for table headers */
    .table th {
        background-color: #2B228A; /* Dark background */
        color: #ffffff; /* White text */
        font-weight: bold;
        text-align: center;
        padding: 12px;
        border-bottom: 2px solid #dee2e6; /* Bottom border only */
    }

    /* Style for table rows */
    .table td {
        padding: 10px;
        vertical-align: middle; /* Center content vertically */
        border-bottom: 1px solid #dee2e6; /* Border only at the bottom of each row */
    }

    /* Optional hover effect for rows */
    .table tbody tr:hover {
        background-color: #e9ecef; /* Slightly darker background on hover */
    }

    /* Styling the action buttons */
    .table .btn {
        margin-right: 5px; /* Space between buttons */
    }
    

</style>

<!-- Pagination Controls -->
<div id="pagination">
    <button id="prevPage" onclick="prevPage()" disabled>Previous</button>
    <span id="pageIndicator">Page 1</span>
    <button id="nextPage" onclick="nextPage()">Next</button>
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>

    
    <!-- JavaScript -->
    <script>
$(document).ready(function() {
    // Initialize DataTable
    var table = $('#visitorTable').DataTable({
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'copy',
                exportOptions: {
                    columns: ':not(:last-child)'
                },
                title: 'Visitor Log - ' + getFormattedDate()
            },
            {
                extend: 'csv',
                exportOptions: {
                    columns: ':not(:last-child)'
                },
                title: 'Visitor Log - ' + getFormattedDate()
            },
            {
                extend: 'excel',
                exportOptions: {
                    columns: ':not(:last-child)'
                },
                title: 'Visitor Log - ' + getFormattedDate()
            },
            {
                extend: 'pdf',
                exportOptions: {
                    columns: ':not(:last-child)'
                },
                title: 'Visitor Log - ' + getFormattedDate()
            },
            {
                extend: 'print',
                title: '', // No title
                exportOptions: {
                    columns: ':not(:last-child)'
                },
                customize: function (win) {
                    var doc = win.document;

                    $(doc.body)
                        .css('font-family', 'Arial, sans-serif')
                        .css('font-size', '12pt')
                        .prepend('<h1 style="text-align:center; font-size: 20pt; font-weight: bold;">Visitor Log Report</h1>')
                        .prepend('<p style="text-align:center; font-size: 12pt; margin-bottom: 20px;">' + getFormattedDate() + '</p><hr>');

                    $(doc.body).find('table').addClass('display').css({
                        width: '100%',
                        borderCollapse: 'collapse',
                        marginTop: '20px',
                        border: '1px solid #ddd'
                    });

                    $(doc.body).find('table th, table td').css({
                        border: '1px solid #ddd',
                        padding: '8px',
                        textAlign: 'left'
                    });
                }
            }
        ],
        pageLength: 10,
        ordering: true,
        searching: false,
        lengthChange: false,
        info: false,
        paging: false,
        responsive: true,
        order: [[5, 'desc']], // Sort by check-in time
        columnDefs: [{
            targets: -1,
            orderable: false
        }]
    });

    // Custom search
    $('#searchInput').on('keyup', function() {
        table.search(this.value).draw();
    });

    // Custom filter
    $('#filterSelect').on('change', function() {
        const column = parseInt($(this).val());
        if (!isNaN(column)) {
            table
                .columns().search('')
                .column(column)
                .search($('#searchInput').val())
                .draw();
        } else {
            table
                .search($('#searchInput').val())
                .draw();
        }
    });
});

// Helper function for formatted date
function getFormattedDate() {
    const date = new Date();
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}
// Function to get the current date and time in a formatted string
function getFormattedDate() {
  var now = new Date();
  var date = now.getFullYear() + '-' + ('0' + (now.getMonth() + 1)).slice(-2) + '-' + ('0' + now.getDate()).slice(-2);
  var time = ('0' + now.getHours()).slice(-2) + ':' + ('0' + now.getMinutes()).slice(-2) + ':' + ('0' + now.getSeconds()).slice(-2);
  return date + ' ' + time;
}

function sortTable() {
    const table = document.getElementById("visitorTable");
    const tbody = table.querySelector("tbody");
    const rows = Array.from(tbody.rows);
    const sortBy = document.getElementById('sortSelect').value;

    // Determine the sorting order (ascending or descending)
    const isDescending = sortBy.includes('desc');

    rows.sort((rowA, rowB) => {
        let valueA, valueB;
        
        switch (sortBy) {
            case 'resident_asc':
            case 'resident_desc':
                valueA = rowA.cells[1].textContent.trim().toLowerCase(); // Name column
                valueB = rowB.cells[1].textContent.trim().toLowerCase();
                break;
            case 'check_in_asc':
            case 'check_in_desc':
                valueA = parseTime(rowA.cells[5].textContent.trim());
                valueB = parseTime(rowB.cells[5].textContent.trim());
                break;
            case 'check_out_asc':
            case 'check_out_desc':
                valueA = parseTime(rowA.cells[6].textContent.trim());
                valueB = parseTime(rowB.cells[6].textContent.trim());
                break;
            default:
                return 0; // No sorting
        }

        // Handle sorting direction
        if (isDescending) {
            return valueA < valueB ? 1 : (valueA > valueB ? -1 : 0);
        } else {
            return valueA < valueB ? -1 : (valueA > valueB ? 1 : 0);
        }
    });

    // Re-append sorted rows to the table
    rows.forEach(row => tbody.appendChild(row));
}

// Helper function to parse time values
function parseTime(timeStr) {
    if (timeStr === 'N/A') return new Date(0); // Handle 'N/A' as earliest time
    const [hours, minutes, period] = timeStr.split(/[:\s]/);
    const hour = (period.toLowerCase() === 'pm' && hours !== '12') ? parseInt(hours) + 12 : parseInt(hours);
    return new Date(1970, 0, 1, hour, minutes); // Use a fixed date for comparison
}

document.addEventListener('DOMContentLoaded', function() { 
        const filterSelect = document.getElementById('filterSelect');
        const searchInput = document.getElementById('searchInput');
        const table = document.getElementById('visitorTable');
        const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

        function filterTable() {
            const filterBy = filterSelect.value;
            const searchTerm = searchInput.value.toLowerCase();

            Array.from(rows).forEach(row => {
                let cellText = '';

                switch(filterBy) {
                    case 'name':
                        cellText = row.querySelector('.name')?.textContent.toLowerCase() || '';
                        break;
                    case 'contact_info':
                        cellText = row.querySelector('.contact_info')?.textContent.toLowerCase() || '';
                        break;
                    case 'purpose':
                        cellText = row.querySelector('.purpose')?.textContent.toLowerCase() || '';
                        break;
                    case 'visiting_person':
                        cellText = row.querySelector('.visiting_person')?.textContent.toLowerCase() || '';
                        break;
                    default:
                        cellText = row.textContent.toLowerCase();
                }

                row.style.display = cellText.includes(searchTerm) ? '' : 'none';
            });
        }

        filterSelect.addEventListener('change', filterTable);
        searchInput.addEventListener('keyup', filterTable);
    });
// Pagination functionality
const rowsPerPage = 10;
let currentPage = 1;

function updatePagination() {
    const table = document.getElementById('visitorTable');
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    const totalPages = Math.ceil(rows.length / rowsPerPage);
    
    // Update page indicator
    document.getElementById('pageIndicator').textContent = `Page ${currentPage} of ${totalPages}`;
    
    // Enable/disable buttons
    document.getElementById('prevPage').disabled = currentPage === 1;
    document.getElementById('nextPage').disabled = currentPage === totalPages;
    
    // Show/hide rows
    const startIndex = (currentPage - 1) * rowsPerPage;
    const endIndex = startIndex + rowsPerPage;
    
    Array.from(rows).forEach((row, index) => {
        row.style.display = (index >= startIndex && index < endIndex) ? '' : 'none';
    });
}
function nextPage() {
    const table = document.getElementById('visitorTable');
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
    const totalPages = Math.ceil(rows.length / rowsPerPage);
    
    if (currentPage < totalPages) {
        currentPage++;
        updatePagination();
    }
}

function prevPage() {
    if (currentPage > 1) {
        currentPage--;
        updatePagination();
    }
}

// Initialize pagination when the document loads
document.addEventListener('DOMContentLoaded', function() {
    updatePagination();
});

function validateForm() {
        const contactInfo = document.getElementById('contactInfo').value;

        // Regular expression for Philippine phone numbers starting with 09
        const contactPattern = /^(0?[9]\d{9}|[9]\d{9})$/;

        if (!contactPattern.test(contactInfo)) {
            alert('Contact Info must be a valid Philippine phone number starting with 09 (10 or 11 digits).');
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

<!-- Log Visitor Modal -->
<div class="modal fade" id="visitorModal" tabindex="-1" aria-labelledby="visitorModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="visitorModalLabel">Log New Visitor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
                </button>
            </div>
            <div class="modal-body">
                <form action="admin-visitor_log.php" method="post" onsubmit="return validateForm()">
                    <div class="mb-3">
                        <label for="visitorName" class="form-label">Visiting Person</label>
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
                    
                    <!-- Resident Selection Dropdown -->
                    <div class="mb-3">
                        <label for="visitingUser" class="form-label">Resident Name</label>
                        <select class="form-control" name="visiting_user_id" id="visitingUser" required>
                            <option value="" selected>Select Resident</option>
                            <?php echo $options_string; ?>
                        </select>
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

<?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        Visitor logged successfully!
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
 <!-- JavaScript HandleDelete Function -->
 <script>
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
                form.action = 'admin-visitor_log.php'; // Ensure this matches your PHP file
                
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'visitor_id'; // Align with PHP's expected POST parameter
                input.value = visitorId;
                
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        });
    }
    </script>

    <!-- SweetAlert Messages -->
    <?php if (isset($_SESSION['swal_success'])): ?>
        <script>
            Swal.fire({
                title: <?= json_encode($_SESSION['swal_success']['title']) ?>,
                text: <?= json_encode($_SESSION['swal_success']['text']) ?>,
                icon: <?= json_encode($_SESSION['swal_success']['icon']) ?>,
                confirmButtonText: 'OK'
            });
        </script>
        <?php unset($_SESSION['swal_success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['swal_error'])): ?>
        <script>
            Swal.fire({
                title: <?= json_encode($_SESSION['swal_error']['title']) ?>,
                text: <?= json_encode($_SESSION['swal_error']['text']) ?>,
                icon: <?= json_encode($_SESSION['swal_error']['icon']) ?>,
                confirmButtonText: 'OK'
            });
        </script>
        <?php unset($_SESSION['swal_error']); ?>
    <?php endif; ?>

    <!-- JavaScript HandleCheckout Function -->
    <script>
    function handleCheckout(visitorId) {
        Swal.fire({
            title: 'Are you sure?',
            text: "Do you want to check out this visitor?",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, check out!'
        }).then((result) => {
            if (result.isConfirmed) {
                // Create and submit the form programmatically
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'admin-visitor_log.php'; // Ensure this matches your PHP file
                
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'checkout_id'; // Align with PHP's expected POST parameter
                input.value = visitorId;
                
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        });
    }
    </script>
</body> 
</html>