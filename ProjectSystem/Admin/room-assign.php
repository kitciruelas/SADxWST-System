<?php
session_start();

// Redirect to login if not logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: admin-login.php");
    exit;
}

require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';
include '../config/config.php'; // Ensure this is correct
// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);

}

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $userId = $_POST['user_id'];
    $roomId = $_POST['room_id'];

 

    // Get the room's capacity
    $roomCapacityQuery = "SELECT capacity FROM rooms WHERE room_id = ?";
    if ($stmt = mysqli_prepare($conn, $roomCapacityQuery)) {
        mysqli_stmt_bind_param($stmt, 'i', $roomId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $roomCapacity);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
    }

    // Count current assignments in the room
    $currentAssignmentsQuery = "SELECT COUNT(*) as current_count FROM roomassignments WHERE room_id = ?";
    if ($stmt = mysqli_prepare($conn, $currentAssignmentsQuery)) {
        mysqli_stmt_bind_param($stmt, 'i', $roomId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $currentCount);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
    }

    // Check if the room is at capacity
    if ($currentCount >= $roomCapacity) {
        // Alert for full capacity
        echo "<script>alert('Cannot assign room. Room is at full capacity.');</script>";
    } else {
        // Check if the user already has a room assigned
        $checkAssignmentQuery = "SELECT assignment_id FROM roomassignments WHERE user_id = ?";
        if ($stmt = mysqli_prepare($conn, $checkAssignmentQuery)) {
            mysqli_stmt_bind_param($stmt, 'i', $userId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_store_result($stmt);

            if (mysqli_stmt_num_rows($stmt) > 0) {
                // Update existing room assignment
                $updateRoomQuery = "UPDATE roomassignments SET room_id = ?, assignment_date = CURRENT_DATE WHERE user_id = ?";
                if ($updateRoomStmt = mysqli_prepare($conn, $updateRoomQuery)) {
                    mysqli_stmt_bind_param($updateRoomStmt, 'ii', $roomId, $userId);
                    if (mysqli_stmt_execute($updateRoomStmt)) {
                        echo "<script>alert('Room assignment updated successfully.');</script>";
                    } else {
                        echo "<script>alert('Error updating room assignment.');</script>";
                    }
                    mysqli_stmt_close($updateRoomStmt);
                }
            } else {
                // Insert new room assignment
                $insertRoomQuery = "INSERT INTO roomassignments (user_id, room_id, assignment_date) VALUES (?, ?, CURRENT_DATE)";
                if ($insertRoomStmt = mysqli_prepare($conn, $insertRoomQuery)) {
                    mysqli_stmt_bind_param($insertRoomStmt, 'ii', $userId, $roomId);
                    if (mysqli_stmt_execute($insertRoomStmt)) {
                        echo "<script>alert('Room assigned successfully.');</script>";
                    } else {
                        echo "<script>alert('Error assigning room.');</script>";
                    }
                    mysqli_stmt_close($insertRoomStmt);
                }
            }

            mysqli_stmt_close($stmt);
        }
    }

    // Close the connection
}
// Rent Payment Reminder function
function sendRentPaymentReminder($userId, $roomId, $conn) {
    // Fetch user details
    $userQuery = "SELECT Fname, Lname, email FROM users WHERE id = ?";
    if ($stmt = mysqli_prepare($conn, $userQuery)) {
        mysqli_stmt_bind_param($stmt, 'i', $userId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $firstName, $lastName, $userEmail);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
    }

    // Fetch room's monthly rent
    $roomRentQuery = "SELECT room_monthlyrent FROM rooms WHERE room_id = ?";
    if ($stmt = mysqli_prepare($conn, $roomRentQuery)) {
        mysqli_stmt_bind_param($stmt, 'i', $roomId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $roomRent);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);
    }

    // Rent payment reminder logic
    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com'; // Use Gmail SMTP server
        $mail->SMTPAuth = true;
        $mail->Username = 'dormioph@gmail.com'; // Your Gmail email
        $mail->Password = 'ymrd smvk acxa whdy'; // Your generated Google App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Use STARTTLS encryption
        $mail->Port = 587; // Port for STARTTLS
    
        // Recipients
        $mail->setFrom('dormioph@gmail.com', 'Dormio Ph');
        $mail->addAddress($userEmail, "$firstName $lastName");  // Recipient's email
    
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Upcoming Rent Payment Reminder';
        
        // Calculate 10 minutes before the due date (adjust as necessary)
        $dueDateTime = new DateTime(); // Assuming due date is now + 10 minutes for simplicity
        $reminderDate = $dueDateTime->modify('+10 minutes');
        $currentDate = new DateTime();

        if ($reminderDate->format('Y-m-d H:i') === $currentDate->format('Y-m-d H:i')) {
            // Custom content for rent payment reminder
            $mail->Body = "Dear $firstName $lastName,<br><br>
                           This is a reminder that your rent payment of <strong>$roomRent</strong> is due on <strong>" . $dueDateTime->format('F j, Y H:i') . "</strong>. Please make sure to complete your payment within the next 10 minutes to avoid any penalties or disruptions to your room assignment.<br><br>
                           If you have any questions, please contact the administration.<br><br>
                           Best regards,<br>Maricel Perce<br>Admin";
        
            // Send email
            if ($mail->send()) {
                echo 'Rent payment reminder email has been sent.';
            } else {
                echo 'Failed to send rent payment reminder email.';
            }
        }
    } catch (Exception $e) {
        // Log detailed error for debugging
        error_log("Mailer Error: {$mail->ErrorInfo}");
        echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
    }
}
// Fetch room assignments for all users
$currentAssignmentsQuery = "
    SELECT 
        users.id AS user_id, 
        CONCAT(users.fname, ' ', users.lname) AS resident, 
        rooms.room_number AS assigned_room_number,  -- Renamed to assigned_room_number
        rooms.room_monthlyrent AS assigned_monthly_rent  -- Renamed to assigned_monthly_rent
    FROM users 
    LEFT JOIN roomassignments ON users.id = roomassignments.user_id 
    LEFT JOIN rooms ON roomassignments.room_id = rooms.room_id 
    ORDER BY users.id DESC"; // Ordering by users.id in descending order

$currentAssignmentsResult = $conn->query($currentAssignmentsQuery);
$reassignmentsQuery = "
    SELECT 
        ra.user_id, 
        CONCAT(u.fname, ' ', u.lname) AS resident,
        rn.room_number AS assigned_room_number,  
        rn.room_monthlyrent AS assigned_monthly_rent,
        ra.status AS reassignment_status
    FROM room_reassignments ra
    JOIN users u ON ra.user_id = u.id
    JOIN roomassignments ras ON ra.user_id = ras.user_id  -- Join roomassignments table to get room_id
    JOIN rooms rn ON ras.room_id = rn.room_id  -- Use room_id from roomassignments table
    WHERE ras.room_id IS NOT NULL
    ORDER BY ra.user_id ASC";

$reassignmentsResult = $conn->query($reassignmentsQuery);

// Initialize assignments data
$assignmentsData = [];
if ($currentAssignmentsResult->num_rows > 0) {
    while ($row = $currentAssignmentsResult->fetch_assoc()) {
        $assignmentsData[$row['user_id']] = $row;
    }
}

if ($reassignmentsResult->num_rows > 0) {
    while ($row = $reassignmentsResult->fetch_assoc()) {
        $assignmentsData[$row['user_id']]['assigned_room_number'] = $row['assigned_room_number'];
        $assignmentsData[$row['user_id']]['assigned_monthly_rent'] = $row['assigned_monthly_rent'];
        $assignmentsData[$row['user_id']]['reassignment_status'] = $row['reassignment_status'];
    }
}
// Sample query to fetch available rooms
$availableRoomsQuery = "SELECT room_id, room_number FROM rooms WHERE status = 'available'";
$availableRoomsResult = $conn->query($availableRoomsQuery);

$availableRooms = [];
if ($availableRoomsResult->num_rows > 0) {
    while ($room = $availableRoomsResult->fetch_assoc()) {
        $availableRooms[] = $room;
    }
} else {
    echo "No available rooms found.";
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['room_id'])) {
    $userId = $_POST['user_id'];
    $roomId = $_POST['room_id'];

    // Perform the room assignment in the database
    $query = "UPDATE roomassignments SET room_id = ? WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $roomId, $userId);
    $success = $stmt->execute();

    // Set a flag if the assignment is successful
    if ($success) {
        $_SESSION['assignment_success'] = true;
    }
}


$conn->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room Assign</title>
    <link rel="icon" href="img-icon/rassign.png" type="image/png">

    <link rel="stylesheet" href="Css_Admin/admin_manageuser.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/0.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.16.9/xlsx.full.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">

    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="menu" id="hamburgerMenu">
            <i class="fas fa-bars"></i>
        </div>
        <div class="sidebar-nav">
            <a href="dashboard.php" class="nav-link"><i class="fas fa-home"></i> <span>Home</span></a>
            <a href="manageuser.php" class="nav-link"><i class="fas fa-users"></i> <span>Manage User</span></a>
            <a href="admin-room.php" class="nav-link" id="roomManagerDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="fas fa-building"></i> <span>Room Manager</span>
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

<script>
function confirmLogout() {
    return confirm("Are you sure you want to log out?");
}
</script>
        </div>
    </div>

    <!-- Top bar -->
    <div class="topbar">
        
        <h2>Room Assign</h2>
        
    </div>
    

                              <!-- AYUSIN ANG ROOM ASSIGN AND REASSINGN HUHU -->
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
    <div class="col-12 col-md-8 mt-1">
        <input type="text" id="searchInput" class="form-control custom-input-small" placeholder="Search...">
    </div>
    <div class="col-6 col-md-2 mt-1">
        <select id="filterSelect" class="form-select">
            <option value="all">All</option>
            <option value="resident">By Resident</option>
            <option value="room">By Room Number</option>
            <option value="rent">By Monthly Rent</option>
        </select>
    </div>
    <div class="col-6 col-md-2 mt-1">
        <select id="sortSelect" class="form-select">
            <option value="">Sort By</option>
            <option value="resident-asc">Resident (A-Z)</option>
            <option value="resident-desc">Resident (Z-A)</option>
            <option value="room-asc">Room Number (Low-High)</option>
            <option value="room-desc">Room Number (High-Low)</option>
            <option value="rent-asc">Monthly Rent (Low-High)</option>
            <option value="rent-desc">Monthly Rent (High-Low)</option>
        </select>
    </div>
</div>

    <?php if (!empty($assignmentsData)): ?>
    <!-- Room Assignment Table -->
    <table class="table table-bordered" id="assignmentTable">
        <thead>
            <tr>
                <th>No.</th>
                <th>Resident</th>
                <th>Room</th>
                <th>Monthly Rent</th>
                <th>Assign Room</th>
            </tr>
        </thead>
        <tbody id="room-table-body">
            <?php
            // Sort the $assignmentsData array based on the selected sort option
            if (isset($_GET['sort'])) {
                switch ($_GET['sort']) {
                    case 'resident_asc':
                        usort($assignmentsData, fn($a, $b) => strcmp($a['resident'], $b['resident']));
                        break;
                    case 'resident_desc':
                        usort($assignmentsData, fn($a, $b) => strcmp($b['resident'], $a['resident']));
                        break;
                    case 'room_asc':
                        usort($assignmentsData, fn($a, $b) => strcmp($a['assigned_room_number'], $b['assigned_room_number']));
                        break;
                    case 'room_desc':
                        usort($assignmentsData, fn($a, $b) => strcmp($b['assigned_room_number'], $a['assigned_room_number']));
                        break;
                    case 'rent_asc':
                        usort($assignmentsData, fn($a, $b) => $a['assigned_monthly_rent'] - $b['assigned_monthly_rent']);
                        break;
                    case 'rent_desc':
                        usort($assignmentsData, fn($a, $b) => $b['assigned_monthly_rent'] - $a['assigned_monthly_rent']);
                        break;
                }
            }
            
            $counter = 1;
            foreach ($assignmentsData as $row):
                if (isset($_SESSION['assigned_user_id']) && $_SESSION['assigned_user_id'] == $row['user_id']) {
                    continue;
                }

                $hasAssignedRoom = !empty($row['assigned_room_number']);
                $isPending = (isset($row['reassignment_status']) && $row['reassignment_status'] == 'pending');
            ?>
                <tr>
                    <td><?php echo $counter++; ?></td>
                    <td class="resident"><?php echo htmlspecialchars($row['resident']); ?></td>
                    <td class="room"><?php echo htmlspecialchars($row['assigned_room_number'] ?? 'No Room Assigned'); ?></td>
                    <td class="monthly_rent"><?php echo isset($row['assigned_monthly_rent']) ? number_format($row['assigned_monthly_rent'], 2) : 'N/A'; ?></td>
                    <td>
                        <form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="POST">
                            <input type="hidden" name="user_id" value="<?php echo $row['user_id']; ?>">
                            <select name="room_id" required>
                                <option value="">Select Room</option>
                                <?php foreach ($availableRooms as $room): ?>
                                    <option value="<?php echo htmlspecialchars($room['room_id']); ?>">
                                        <?php echo htmlspecialchars($room['room_number']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-primary">Assign</button>
                        </form>
                        <?php if ($hasAssignedRoom && !$isPending): ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php unset($_SESSION['assigned_user_id']); ?>
    <?php else: ?>
        <p>No room assignments found.</p>
    <?php endif; ?>


<!-- Pagination Controls -->
<div id="pagination">
    <button id="prevPage" onclick="prevPage()" disabled>Previous</button>
    <span id="pageIndicator">Page 1</span>
    <button id="nextPage" onclick="nextPage()">Next</button>
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
<!-- JavaScript Libraries -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>


<!-- Include jQuery and Bootstrap JS (required for dropdown) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Bootstrap JS and Popper.js -->


    
    <!-- Include jQuery and Bootstrap JS (required for dropdown) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Hamburgermenu Script -->
    <script>
$(document).ready(function () {
    // Initialize DataTable
    var table = $('#assignmentTable').DataTable({
        dom: 'Bfrtip',

        buttons: [
            {
                extend: 'copy',
                className: 'btn btn-secondary',
                exportOptions: {
                    columns: ':not(:last-child)'
                },
                title: 'Room Assignments - ' + getFormattedDate()
            },
            {
                extend: 'csv',
                className: 'btn btn-primary',
                exportOptions: {
                    columns: ':not(:last-child)'
                },
                title: 'Room Assignments - ' + getFormattedDate()
            },
            {
                extend: 'excel',
                className: 'btn btn-success',
                exportOptions: {
                    columns: ':not(:last-child)'
                },
                title: 'Room Assignments - ' + getFormattedDate()
            },
            {
                extend: 'print',
                className: 'btn btn-info',
                title: '',
                exportOptions: {
                    columns: ':not(:last-child)'
                },
                customize: function (win) {
                    var doc = win.document;

                    $(doc.body)
                        .css('font-family', 'Arial, sans-serif')
                        .css('font-size', '12pt')
                        .prepend('<h1 style="text-align:center; font-size: 20pt; font-weight: bold;">Room Assignments Report</h1>')
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
        paging: false,
        searching: false,
        info: false
    });

    // Sorting functionality for custom dropdown
    $('#sort').on('change', function () {
        const sortValue = $(this).val();
        if (sortValue) {
            const [column, direction] = sortValue.split('_');
            const columnIndex = column === 'resident' ? 1 : column === 'room' ? 2 : 3; // Map column to index
            table.order([columnIndex, direction]).draw();
        }
    });
});

// Helper function for formatted date
function getFormattedDate() {
    const date = new Date();
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

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
    const sortSelect = document.getElementById('sortSelect');
    const searchInput = document.getElementById('searchInput');
    const tbody = document.querySelector('#room-table-body');

    function filterTable() {
        const rows = Array.from(tbody.getElementsByTagName('tr'));
        const searchTerm = searchInput.value.toLowerCase().trim();
        const filterType = filterSelect.value;

        rows.forEach(row => {
            let showRow = false;
            const resident = row.querySelector('.resident').textContent.toLowerCase();
            const room = row.querySelector('.room').textContent.toLowerCase();
            const rent = row.querySelector('.monthly_rent').textContent.toLowerCase();

            switch (filterType) {
                case 'all':
                    showRow = resident.includes(searchTerm) || 
                             room.includes(searchTerm) || 
                             rent.includes(searchTerm);
                    break;
                case 'resident':
                    showRow = resident.includes(searchTerm);
                    break;
                case 'room':
                    showRow = room.includes(searchTerm);
                    break;
                case 'rent':
                    showRow = rent.includes(searchTerm);
                    break;
                default:
                    showRow = true;
            }

            row.style.display = showRow ? '' : 'none';
        });

        updateRowNumbers();
        sortTable(); // Apply sorting after filtering
    }

    function sortTable() {
        const rows = Array.from(tbody.getElementsByTagName('tr'))
            .filter(row => row.style.display !== 'none'); // Only sort visible rows
        const sortType = sortSelect.value;

        if (sortType) {
            const [column, direction] = sortType.split('-');

            rows.sort((a, b) => {
                let valueA, valueB;

                switch (column) {
                    case 'resident':
                        valueA = a.querySelector('.resident').textContent.toLowerCase();
                        valueB = b.querySelector('.resident').textContent.toLowerCase();
                        break;
                    case 'room':
                        valueA = a.querySelector('.room').textContent.toLowerCase();
                        valueB = b.querySelector('.room').textContent.toLowerCase();
                        // Handle 'No Room Assigned' case
                        valueA = valueA === 'no room assigned' ? '' : valueA;
                        valueB = valueB === 'no room assigned' ? '' : valueB;
                        break;
                    case 'rent':
                        valueA = parseFloat(a.querySelector('.monthly_rent').textContent.replace(/[^\d.-]/g, '')) || 0;
                        valueB = parseFloat(b.querySelector('.monthly_rent').textContent.replace(/[^\d.-]/g, '')) || 0;
                        return direction === 'asc' ? valueA - valueB : valueB - valueA;
                }

                return direction === 'asc' 
                    ? valueA.localeCompare(valueB)
                    : valueB.localeCompare(valueA);
            });

            // Reorder rows in the table
            rows.forEach(row => tbody.appendChild(row));
        }

        updateRowNumbers();
    }

    function updateRowNumbers() {
        let visibleIndex = 1;
        const rows = tbody.getElementsByTagName('tr');
        
        Array.from(rows).forEach(row => {
            if (row.style.display !== 'none') {
                row.cells[0].textContent = visibleIndex++;
            }
        });
    }

    function resetFilters() {
        if (searchInput.value === '') {
            filterSelect.value = 'all';
            // Don't reset sort selection as it should persist
            filterTable();
        }
    }

    // Event listeners
    searchInput.addEventListener('input', debounce(() => {
        resetFilters();
        filterTable();
    }, 300));

    filterSelect.addEventListener('change', filterTable);
    sortSelect.addEventListener('change', sortTable);

    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    // Initialize table
    filterTable();
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
    
</body>
</html>