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
            echo "<script>alert('Reassignment status updated successfully.');</script>";

            // Proceed only if the status is approved
            if ($newStatus === 'approved') {
                // Fetch reassignment details to get user_id, old_room_id, and new_room_id
                $fetchDetailsQuery = "
                    SELECT user_id, old_room_id, new_room_id
                    FROM room_reassignments
                    WHERE reassignment_id = ?
                ";
                $fetchDetailsStmt = $conn->prepare($fetchDetailsQuery);
                $fetchDetailsStmt->bind_param("i", $reassignmentId);
                $fetchDetailsStmt->execute();
                $reassignmentDetails = $fetchDetailsStmt->get_result()->fetch_assoc();
                $fetchDetailsStmt->close();

                if ($reassignmentDetails) {
                    $userId = $reassignmentDetails['user_id'];
                    $newRoomId = $reassignmentDetails['new_room_id'];

                    // Update the roomassignments table for the new room
                    $updateAssignmentQuery = "
                        UPDATE roomassignments
                        SET room_id = ?, assignment_date = CURRENT_DATE
                        WHERE user_id = ?
                    ";
                    $updateAssignmentStmt = $conn->prepare($updateAssignmentQuery);
                    $updateAssignmentStmt->bind_param("ii", $newRoomId, $userId);

                    if ($updateAssignmentStmt->execute()) {
                        echo "<script>alert('User\'s room assignment updated to new room successfully.');</script>";
                    } else {
                        echo "Error updating room assignment: " . $updateAssignmentStmt->error;
                    }

                    $updateAssignmentStmt->close();
                } else {
                    echo "<script>alert('Reassignment details not found.');</script>";
                }
            }
        } else {
            echo "Error updating status: " . $stmt->error;
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
    ORDER BY rr.reassignment_id ASC
";

$result = $conn->query($sql);

$conn->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="Css_Admin/adminmanageuser.css">
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
            <a href="admin-room.php" class="nav-link active " id="roomManagerDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="fas fa-building"></i> <span>Room Manager</span>

            <a href="admin-visitor_log.php" class="nav-link"><i class="fas fa-address-book"></i> <span>Log Visitor</span></a>
            <a href="admin-monitoring.php" class="nav-link"><i class="fas fa-eye"></i> <span>Presence Monitoring</span></a>


       

        </div>
        <div class="logout">
            <a href="../config/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
        </div>
    </div>

    <!-- Top bar -->
    <div class="topbar">
        <h2>Room Reassign</h2>
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
    <div class="row mb-4">
        <div class="col-12 col-md-8">
            <input type="text" id="searchInput" class="form-control custom-input-small" placeholder="Search for room details...">
        </div>
        <div class="col-6 col-md-2">
            <select id="filterSelect" class="form-select">
                <option value="all" selected>Filter by</option>
                <option value="resident">Resident</option>
                <option value="old_room_number">Old Room Number</option>
                <option value="new_room">Reassign Room</option>
                <option value="monthly_rent">Monthly Rent</option>
                <option value="status">Status</option>
            </select>
        </div>
    </div>

   <!-- Table Section -->
<div>
    <table id="assignmentTable" class="table table-bordered">
        <thead>
            <tr>
                <th>No.</th>
                <th>Old Room Number</th>
                <th>Resident</th>
                <th>Reassign Room</th>
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
                        <td class="old_room_number"><?php echo htmlspecialchars($row['old_room_number'] ?? 'N/A'); ?></td>
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

            
   
    
    <!-- Include jQuery and Bootstrap JS (required for dropdown) -->
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Hamburgermenu Script -->
    <script>
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
    
</body>
</html>
