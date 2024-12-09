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

// Assuming you have a way to get the current user's ID
$userId = $_SESSION['id']; // Example: Get user ID from session

require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // Adjust path as needed

function sendReassignmentEmail($userEmail, $firstName, $lastName, $newRoomNumber) {
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
        $mail->Subject = 'Room Reassignment Approved';
        $mail->Body = "
            <h2>Room Reassignment Approved</h2>
            <p>Dear $firstName $lastName,</p>
            <p>Your room reassignment request has been approved. You have been assigned to Room $newRoomNumber.</p>
            <p>Please ensure to complete your move within the designated timeframe.</p>
            <p>Best regards,<br>Dormio Ph Management</p>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}

function sendRejectionEmail($userEmail, $firstName, $lastName) {
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
        $mail->Subject = 'Room Reassignment Request Rejected';
        $mail->Body = "
            <h2>Room Reassignment Update</h2>
            <p>Dear $firstName $lastName,</p>
            <p>We regret to inform you that your room reassignment request has been rejected.</p>
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

function activity_logs($conn, $userId, $activityType, $activityDetails = null) {
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, activity_type, activity_details) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $userId, $activityType, $activityDetails);
    $stmt->execute();
    $stmt->close();
}

// Assuming $conn is the MySQLi connection

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $reassignmentId = $_POST['reassignment_id'];
    $newStatus = $_POST['status'];

    // Validate status
    if (in_array($newStatus, ['pending', 'approved', 'rejected'])) {
        // Update the reassignment status in the room_reassignments table
        $stmt = $conn->prepare("UPDATE room_reassignments SET status = ? WHERE reassignment_id = ?");
        $stmt->bind_param("si", $newStatus, $reassignmentId);

        if ($stmt->execute()) {
            // Log activity for status update
            activity_logs($conn, $userId, 'Status Update', "Status changed to $newStatus for reassignment ID $reassignmentId");

            // Update success message for both approved and rejected statuses
            $_SESSION['swal_success'] = [
                'title' => 'Success!',
                'text' => ($newStatus === 'rejected') ? 'Reassignment request has been rejected successfully!' : 'Reassignment request has been approved successfully!',
                'icon' => 'success'
            ];

            // Handle both approved and rejected statuses
            if ($newStatus === 'approved' || $newStatus === 'rejected') {
                // Fetch user details for email
                $userQuery = "
                    SELECT u.email, u.fname, u.lname, r.room_number, rr.user_id, rr.new_room_id 
                    FROM room_reassignments rr
                    JOIN users u ON rr.user_id = u.id
                    LEFT JOIN rooms r ON rr.new_room_id = r.room_id
                    WHERE rr.reassignment_id = ?
                ";
                $userStmt = $conn->prepare($userQuery);
                $userStmt->bind_param("i", $reassignmentId);
                $userStmt->execute();
                $userResult = $userStmt->get_result()->fetch_assoc();
                $userStmt->close();

                if ($userResult) {
                    if ($newStatus === 'approved') {
                        // Update roomassignments table
                        $updateAssignmentQuery = "
                            UPDATE roomassignments 
                            SET room_id = ?, assignment_date = CURRENT_DATE
                            WHERE user_id = ?
                        ";
                        $updateAssignmentStmt = $conn->prepare($updateAssignmentQuery);
                        $updateAssignmentStmt->bind_param("ii", $userResult['new_room_id'], $userResult['user_id']);
                        
                        if ($updateAssignmentStmt->execute()) {
                            // Send approval email
                            $emailSent = sendReassignmentEmail(
                                $userResult['email'],
                                $userResult['fname'],
                                $userResult['lname'],
                                $userResult['room_number']
                            );
                            
                            if (!$emailSent) {
                                echo "<script>alert('Room updated but email notification failed.');</script>";
                            }
                            // Log activity for email sent
                            activity_logs($conn, $userId, 'Reassignment request approved', "Approval email sent to {$userResult['email']}");
                        } else {
                            echo "<script>alert('Error updating room assignment: " . $updateAssignmentStmt->error . "');</script>";
                        }
                        $updateAssignmentStmt->close();
                    } else { // rejected
                        $emailSent = sendRejectionEmail(
                            $userResult['email'],
                            $userResult['fname'],
                            $userResult['lname']
                        );
                        
                        if (!$emailSent) {
                            echo "<script>alert('Status updated but rejection email failed.');</script>";
                        }
                        // Log activity for email sent
                        activity_logs($conn, $userId, 'Reassignment request rejected', "Rejection email sent to {$userResult['email']}");
                    }
                }
            }
        } else {
            $_SESSION['swal_error'] = [
                'title' => 'Error',
                'text' => 'Error updating status: ' . $stmt->error,
                'icon' => 'error'
            ];
        }

        $stmt->close();
    } else {
        echo "Invalid status.";
    }
}




$sql = "
    SELECT 
        rr.reassignment_id, 
        CONCAT(u.fname, ' ', u.lname) AS resident,
        COALESCE(ro.room_number, 'N/A') AS old_room_number, -- Use COALESCE to handle NULL old rooms
        rn.room_number AS new_room_number,
        rr.reassignment_date,
        IFNULL(rr.comment, 'No comment') AS comments, -- Correct field name assumed
        rr.status
    FROM room_reassignments rr
    JOIN users u ON rr.user_id = u.id
    LEFT JOIN rooms ro ON rr.old_room_id = ro.room_id
    JOIN rooms rn ON rr.new_room_id = rn.room_id
    ORDER BY rr.reassignment_id DESC
";

$result = $conn->query($sql);

$conn->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Reassignment Requests</title>
    <link rel="icon" href="../img-icon/logo.png" type="image/png">

    <link rel="stylesheet" href="../Admin/Css_Admin/style.css"> 
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/0.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.16.9/xlsx.full.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet"><!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.1/css/jquery.dataTables.min.css">

<!-- Bootstrap CSS -->
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<!-- jQuery (full version) -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>

<!-- DataTables CSS (recommended for styling) -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.10.21/css/jquery.dataTables.min.css">

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.10.21/js/jquery.dataTables.min.js"></script>

<!-- DataTables Buttons CSS (for buttons like export) -->
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.3/css/buttons.dataTables.min.css">

<!-- DataTables Buttons JS (for exporting and other button features) -->
<script src="https://cdn.datatables.net/buttons/2.2.3/js/dataTables.buttons.min.js"></script>

<!-- JSZip for Excel Export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>

<!-- pdfMake for PDF Export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>

<!-- DataTables Buttons for exporting -->
<script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.html5.min.js"></script>

<!-- DataTables Print Buttons JS -->
<script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.print.min.js"></script>

<!-- Bootstrap JS (optional, if you're using Bootstrap components) -->
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
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

<!-- SweetAlert CSS and JS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</head>
<body>
<div class="sidebar" id="sidebar">
        <div class="menu" id="hamburgerMenu">
            <i class="fas fa-bars"></i>
        </div>
        <div class="sidebar-nav">
        <a href="user-dashboard.php" class="nav-link"><i class="fas fa-home"></i><span>Home</span></a>
        <a href="admin-room.php" class="nav-link active"><i class="fas fa-building"></i> <span>Room Management</span></a>
        <a href="admin-visitor_log.php" class="nav-link"><i class="fas fa-user-check"></i> <span>Visitor log</span></a>
        <a href="admin-monitoring.php" class="nav-link"><i class="fas fa-eye"></i> <span>Presence Monitoring</span></a>

        <a href="staff-chat.php" class="nav-link"><i class="fas fa-comments"></i> <span>Group Chat</span></a>

        <a href="rent_payment.php" class="nav-link"><i class="fas fa-money-bill-alt"></i> <span>Rent Payment</span></a>
        </div>
        <div class="logout">
        <a href="../config/user-logout.php" id="logoutLink">
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
    </div>

    <!-- Top bar -->
    <div class="topbar">
        <h2>Room Reassignment Requests</h2>
    </div>

    <!-- Main content -->
    <div class="main-content">
        
    <div class="container">

    <div class="d-flex justify-content-start">
    <a href="admin-room.php" class="btn " onclick="window.location.reload();">
    <i class="fas fa-arrow-left fa-2x me-1"></i></a>
</div>
</div>
<div class="container mt-1">
<!-- Search and Filter Section -->
<div class="row mb-1">
    <!-- Search Input -->
    <div class="col-12 col-md-6">
        <form method="GET" action="" class="search-form">
            <div class="input-group">
                <input type="text" id="searchInput" class="form-control custom-input-small" 
                    placeholder="Search for room details..." 
                    value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                <span class="input-group-text">
                    <i class="fas fa-search"></i>
                </span>
            </div>
        </form>
    </div>

    <!-- Filter Dropdown -->
    <div class="col-6 col-md-2 mt-1">
        <select id="filterSelect" class="form-select">
            <option value="all" selected>Filter by</option>
            <option value="resident">Resident</option>
            <option value="new_room">Reassign Room</option>
            <option value="monthly_rent">Monthly Rent</option>
            <option value="status">Status</option>
        </select>
    </div>

    <!-- Sort Dropdown -->
    <div class="col-6 col-md-2 mt-1">
        <select id="sortSelect" class="form-select">
            <option value="" selected>Sort by</option>
            <option value="resident_asc">Resident (A to Z)</option>
            <option value="resident_desc">Resident (Z to A)</option>
            <option value="new_room_asc">Request Reassignment (Low to High)</option>
            <option value="new_room_desc">Request Reassignment (High to Low)</option>
            <option value="status_asc">Status (A to Z)</option>
            <option value="status_desc">Status (Z to A)</option>
        </select>
    </div>

  
</div>

   <!-- Table Section -->
<div>
    <table id="assignmentTable" class="table table-bordered">
        <thead>
            <tr>
                <th>No.</th>
               <!--  <th>Old Room</th>-->
                <th>Resident</th>
                <th>Request Reassigment</th>
                <th>Comment</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody id="room-table-body">
            <?php
            if ($result->num_rows > 0) {
                $no = 1; // Row counter
                while ($row = $result->fetch_assoc()) {
                    ?>
                    <tr>
                        <td><?php echo $no++; ?></td>
                     <!--     <td class="old_room_number"><?php echo htmlspecialchars($row['old_room_number'] ?? 'N/A'); ?></td>-->
                        <td class="resident"><?php echo htmlspecialchars($row['resident']); ?></td>
                        <td class="new_room"><?php echo htmlspecialchars($row['new_room_number'] ?? 'N/A'); ?></td>
                        <td><?php echo !empty($row['comments']) ? htmlspecialchars($row['comments']) : 'No comment'; ?></td>
                        <td class="status">
                            <!-- Status Badge -->
                            <span class="badge 
                                <?php echo ($row['status'] == 'approved') ? 'bg-success' : 
                                           (($row['status'] == 'rejected') ? 'bg-danger' : 'bg-warning'); ?>">
                                <?php echo htmlspecialchars($row['status']); ?>
                            </span>

                            <!-- Status Update Form for Pending Reassignments -->
                            <?php if ($row['status'] == 'pending') { ?>
                                <form method="POST" action="application-room.php" class="d-inline">
                                    <input type="hidden" name="reassignment_id" value="<?php echo $row['reassignment_id']; ?>">

                                    <!-- Status Dropdown -->
                                    <select name="status" class="form-select form-select-sm d-inline w-auto">
                                        <option value="pending" <?php echo ($row['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                        <option value="approved">Approved</option>
                                        <option value="rejected">Rejected</option>
                                    </select>

                                    <!-- Update Button -->
                                    <button type="submit" class="btn btn-primary btn-sm">Update Status</button>
                                </form>
                            <?php } ?>
                        </td>
                    </tr>
                    <?php
                }
            } else {
                echo "<tr><td colspan='6' class='text-center'>No reassignments found.</td></tr>";
            }
            ?>
        </tbody>
    </table>
</div>

<!-- Pagination Controls -->
<div id="pagination">
    <button id="prevPage" onclick="prevPage()" disabled>Previous</button>
    <span id="pageIndicator">Page 1</span>
    <button id="nextPage" onclick="nextPage()">Next</button>
</div>

<style>
    
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

            <!-- JavaScript Libraries -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>


<!-- Include jQuery and Bootstrap JS (required for dropdown) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
   
    
    <!-- Include jQuery and Bootstrap JS (required for dropdown) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Hamburgermenu Script -->
    <script>
   $(document).ready(function() {
    var table = $('#assignmentTable').DataTable({
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'copy',
                exportOptions: { columns: ':not(:last-child)' },
                title: 'Reassign List Report - ' + getFormattedDate(),
            },
            {
                extend: 'csv',
                exportOptions: { columns: ':not(:last-child)' },
                title: 'Reassign List Report - ' + getFormattedDate(),
            },
            {
                extend: 'excel',
                exportOptions: { columns: ':not(:last-child)' },
                title: 'Reassign List Report - ' + getFormattedDate(),
            },
            {
                extend: 'print',
                exportOptions: { columns: ':not(:last-child)' },
                title: '',
                customize: function(win) {
                    var doc = win.document;
                    $(doc.body).css({
                        fontFamily: 'Arial, sans-serif',
                        fontSize: '12pt',
                        color: '#333333',
                        lineHeight: '1.6',
                        backgroundColor: '#ffffff',
                    });
                    $(doc.body).prepend('<h1 style="text-align:center; font-size: 20pt; font-weight: bold;">Reassign List Report</h1>');
                    $(doc.body).prepend('<p style="text-align:center; font-size: 12pt;">' + getFormattedDate() + '</p><hr>');
                },
            }
        ],
        paging: false,
        searching: false,
        info: false,
    });
});
// Function to get the current date and time in a formatted string
function getFormattedDate() {
    var now = new Date();
    var date = now.getFullYear() + '-' + ('0' + (now.getMonth() + 1)).slice(-2) + '-' + ('0' + now.getDate()).slice(-2);
    var time = ('0' + now.getHours()).slice(-2) + ':' + ('0' + now.getMinutes()).slice(-2) + ':' + ('0' + now.getSeconds()).slice(-2);
    return date + ' ' + time;
}

        // Function to sort the table based on the selected sorting criteria
    document.getElementById('sortSelect').addEventListener('change', function() {
        var table = document.getElementById('assignmentTable');
        var rows = Array.from(table.rows).slice(1); // Get rows except the header
        var sortOption = this.value;

        rows.sort(function(rowA, rowB) {
            var cellA, cellB;

            // Get the cell content based on the selected sort option
            if (sortOption === 'resident_asc' || sortOption === 'resident_desc') {
                cellA = rowA.querySelector('.resident').innerText.toLowerCase();
                cellB = rowB.querySelector('.resident').innerText.toLowerCase();
            } else if (sortOption === 'old_room_asc' || sortOption === 'old_room_desc') {
                cellA = rowA.querySelector('.old_room_number').innerText;
                cellB = rowB.querySelector('.old_room_number').innerText;
            } else if (sortOption === 'new_room_asc' || sortOption === 'new_room_desc') {
                cellA = rowA.querySelector('.new_room').innerText;
                cellB = rowB.querySelector('.new_room').innerText;
            } else if (sortOption === 'status_asc' || sortOption === 'status_desc') {
                cellA = rowA.querySelector('.status').innerText.toLowerCase();
                cellB = rowB.querySelector('.status').innerText.toLowerCase();
            }

            // Compare the values
            if (sortOption.includes('asc')) {
                return cellA > cellB ? 1 : -1; // Ascending order
            } else {
                return cellA < cellB ? 1 : -1; // Descending order
            }
        });

        // Reorder the rows in the table based on the sorting result
        rows.forEach(function(row) {
            table.querySelector('tbody').appendChild(row);
        });
    });
// JavaScript for client-side pagination
const rowsPerPage = 10; // Display 10 rows per page
let currentPage = 1;
const rows = document.querySelectorAll('#room-table-body tr');
const totalPages = Math.ceil(rows.length / rowsPerPage);

// Show the initial set of rows
showPage(currentPage);

function showPage(page) {
    const start = (page - 1) * rowsPerPage;
    const end = start + rowsPerPage;
    rows.forEach((row, index) => {
        row.style.display = index >= start && index < end ? '' : 'none';
    });
    document.getElementById('pageIndicator').innerText = `Page ${page}`;
    document.getElementById('prevPage').disabled = page === 1;
    document.getElementById('nextPage').disabled = page === totalPages;
    // Update page indicator
    document.getElementById('pageIndicator').textContent = `Page ${currentPage} of ${totalPages}`;
}

function nextPage() {
    if (currentPage < totalPages) {
        currentPage++;
        showPage(currentPage);
    }
}

function prevPage() {
    if (currentPage > 1) {
        currentPage--;
        showPage(currentPage);
    }
}



         document.addEventListener('DOMContentLoaded', function() {
    const filterSelect = document.getElementById('filterSelect');
    const searchInput = document.getElementById('searchInput');
    const table = document.getElementById('assignmentTable');
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

    // Function to filter table based on filter selection and search term
    function filterTable() {
        const filterBy = filterSelect.value;
        const searchTerm = searchInput.value.toLowerCase();

        // Iterate through each row in the table
        Array.from(rows).forEach(row => {
            let cellText = '';

            // Get text from the appropriate cell based on the selected filter
            switch(filterBy) {
                case 'resident':
                    cellText = row.querySelector('.resident').textContent.toLowerCase();
                    break;
                case 'old_room_number':
                    cellText = row.querySelector('.old_room_number').textContent.toLowerCase();
                    break;
                case 'new_room':
                    cellText = row.querySelector('.new_room').textContent.toLowerCase();
                    break;
                case 'monthly_rent':
                    cellText = row.querySelector('.monthly_rent')?.textContent.toLowerCase() || ''; // Optional chaining for null safety
                    break;
                case 'status':
                    cellText = row.querySelector('.status').textContent.toLowerCase();
                    break;
                default:
                    // Search across all text in the row if "all" is selected
                    cellText = row.textContent.toLowerCase();
            }

            // Show or hide the row based on whether the cell text includes the search term
            row.style.display = cellText.includes(searchTerm) ? '' : 'none';
        });
    }

    // Attach event listeners to filter selection and search input
    filterSelect.addEventListener('change', filterTable);
    searchInput.addEventListener('keyup', filterTable);
});


        

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
    
    <script>

// Check for session messages and display SweetAlert
document.addEventListener('DOMContentLoaded', function() {
    <?php if (isset($_SESSION['swal_success'])): ?>
        Swal.fire({
            title: '<?php echo $_SESSION['swal_success']['title']; ?>',
            text: '<?php echo $_SESSION['swal_success']['text']; ?>',
            icon: '<?php echo $_SESSION['swal_success']['icon']; ?>',
            confirmButtonText: 'OK'
        });
        <?php unset($_SESSION['swal_success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['swal_error'])): ?>
        Swal.fire({
            title: '<?php echo $_SESSION['swal_error']['title']; ?>',
            text: '<?php echo str_replace("rejected", "success", $_SESSION['swal_error']['text']); ?>',
            icon: '<?php echo $_SESSION['swal_error']['icon']; ?>',
            confirmButtonText: 'OK'
        });
        <?php unset($_SESSION['swal_error']); ?>
    <?php endif; ?>
});

    </script>
</body>
</html>
