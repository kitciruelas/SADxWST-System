<?php
session_start();
include '../config/config.php'; // Correct path to your config file

// Check if the user is logged in
if (!isset($_SESSION['id'])) {
    header("Location: login.php"); // Redirect to login if not logged in
    exit();
}


// Check if the request is a POST request and if visitor_id is set
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['visitor_id']) && !empty($_POST['visitor_id'])) {
        
        $visitor_id = intval($_POST['visitor_id']); // Sanitize input

        // Step 1: Archive the visitor by copying to archive table
        $archiveSql = "INSERT INTO visitors_archive (id, name, contact_info, purpose, visiting_user_id, check_in_time, check_out_time, archived_at)
                       SELECT id, name, contact_info, purpose, visiting_user_id, check_in_time, check_out_time, NOW()
                       FROM visitors WHERE id = ?";
        
        $archiveStmt = $conn->prepare($archiveSql);
        if ($archiveStmt) {
            $archiveStmt->bind_param("i", $visitor_id); // Bind visitor ID as integer
            
            if ($archiveStmt->execute()) {
                // Step 2: Delete the visitor from the original table
                $deleteSql = "DELETE FROM visitors WHERE id = ?";
                $deleteStmt = $conn->prepare($deleteSql);

                if ($deleteStmt) {
                    $deleteStmt->bind_param("i", $visitor_id);
                    if ($deleteStmt->execute()) {
                        echo "Visitor archived and deleted successfully.";
                        header("Location: admin-visitor_log.php"); // Redirect after archiving
                        exit;
                    } else {
                        echo "Error deleting visitor from the original table.";
                    }
                    $deleteStmt->close();
                } else {
                    echo "Failed to prepare the delete statement.";
                }
            } else {
                echo "Error archiving visitor.";
            }
            $archiveStmt->close();
        } else {
            echo "Failed to prepare the archive statement.";
        }

    } else {
        echo "Invalid request: Visitor ID is missing or empty.";
    }
} else {
}





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
";
$result = $conn->query($query);

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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap CSS -->
<link href="https://stackpath.bootstrapcdn.com/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">

<!-- jQuery (needed for Bootstrap's JavaScript plugins) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Bootstrap JS -->
<script src="https://stackpath.bootstrapcdn.com/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>

</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="menu" id="hamburgerMenu">
            <i class="fas fa-bars"></i> <!-- Hamburger menu icon -->
        </div>

        <div class="sidebar-nav">
             <a href="dashboard.php" class="nav-link" ><i class="fas fa-user-cog"></i> <span>Admin</span></a>
            <a href="manageuser.php" class="nav-link"><i class="fas fa-users"></i> <span>Manage User</span></a>
            <a href="admin-room.php" class="nav-link"><i class="fas fa-building"></i> <span>Room Manager</span></a>
            <a href="#" class="nav-link active"><i class="fas fa-address-book"></i> <span>Log Visitor</span></a>
            <a href="admin-monitoring.php" class="nav-link"><i class="fas fa-eye"></i> <span>Presence Monitoring</span></a>

        </div>
        
        <div class="logout">
            <a href="../config/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
        </div>
    </div>

    <!-- Top bar -->
    <div class="topbar">
        <h2>Visitor Log</h2>

    </div>
    <div class="main-content">      
    <div class="container mt-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="input-group w-25">
            <label class="input-group-text" for="filterSelect">Filter by</label>
            <select class="form-select" id="filterSelect" name="filter" 
                onchange="location = 'admin-visitor_log.php?filter=' + this.value;">
                <option value="">Choose...</option>
                <option value="today" <?= $filter === 'today' ? 'selected' : '' ?>>Today</option>
                <option value="this_week" <?= $filter === 'this_week' ? 'selected' : '' ?>>This Week</option>
                <option value="this_month" <?= $filter === 'this_month' ? 'selected' : '' ?>>This Month</option>
            </select>
        </div>
    </div>

    <div class="table-responsive">
    <table class="table table-bordered">
        <thead class="table-light">
            <tr>
                <th scope="col">No.</th>
                <th scope="col">Name</th>
                <th scope="col">Contact Info</th>
                <th scope="col">Purpose</th>
                <th scope="col">Visiting Person</th>
                <th scope="col">Check-In</th>
                <th scope="col">Check-Out</th>
                <th scope="col">Actions</th>
            </tr>
        </thead>
        <tbody id="visitor-table-body">
            <?php if ($result->num_rows > 0): 
                $counter = 1;
                while ($row = $result->fetch_assoc()):
                    $isCheckedOut = !empty($row['check_out_time']);
                    $checkInTime = date("g:i A", strtotime($row['check_in_time']));
                    $checkOutTime = $isCheckedOut ? date("g:i A", strtotime($row['check_out_time'])) : 'N/A';
            ?>
                <tr>
                    <td><?= $counter++ ?></td>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= htmlspecialchars($row['contact_info']) ?></td>
                    <td><?= htmlspecialchars($row['purpose']) ?></td>
                    <td><?= htmlspecialchars($row['visiting_person']) ?></td>
                    <td><?= $checkInTime ?></td>
                    <td><?= $checkOutTime ?></td>
                    <td>
                        <form action="admin-visitor_log.php" method="post" style="display:inline;">
                            <input type="hidden" name="visitor_id" value="<?= $row['id']; ?>">
                            <button type="submit" class="btn btn-danger btn-sm" 
                                onclick="return confirm('Are you sure you want to delete this visitor?')">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; else: ?>
                <tr><td colspan="8" class="text-center">No visitors found</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
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




<!-- JavaScript Libraries -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>


<!-- Include jQuery and Bootstrap JS (required for dropdown) -->
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>

    
    <!-- JavaScript -->
    <script>
// JavaScript for client-side pagination
const rowsPerPage = 10; // Display 10 rows per page
let currentPage = 1;
const rows = document.querySelectorAll('#visitor-table-body tr');
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
    </script>
</body>
</html>
