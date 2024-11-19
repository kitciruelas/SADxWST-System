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
    <link rel="stylesheet" href="Css_Admin/admin-manageuser.css">
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
            <a href="admin-chat.php" class="nav-link"><i class="fas fa-comments"></i> <span>Group Chat</span></a>
            <a href="rent_payment.php" class="nav-link"><i class="fas fa-money-bill-alt"></i> <span>Rent Payment</span></a>
            <a href="activity-logs.php" class="nav-link"><i class="fas fa-clipboard-list"></i> <span>Activity Logs</span></a>
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
     <!-- Search and Filter Section -->
<div class="row mb-4">
    <div class="col-12 col-md-8">
        <input type="text" id="searchInput" class="form-control custom-input-small" placeholder="Search for room details...">
    </div>
    <div class="col-6 col-md-2">
        <select id="filterSelect" class="form-select">
            <option value="all" selected>Filter by</option>
            <option value="name">Name</option>
            <option value="contact_info">Contact Info</option>
            <option value="purpose">Purpose</option>
            <option value="visiting_person">Visiting Person</option>
        </select>
    </div>
    <div class="col-6 col-md-2">

    <!-- Sort by Dropdown -->
    <select id="sortSelect" class="form-select" style="width: 100%;" onchange="sortTable()">
        <option value="all" selected>Sort by</option>
        <option value="resident_asc">Resident (A to Z)</option>
        <option value="resident_desc">Resident (Z to A)</option>
        <option value="check_in_asc">Check-In Time (Earliest)</option>
        <option value="check_in_desc">Check-In Time (Latest)</option>
        <option value="check_out_asc">Check-Out Time (Earliest)</option>
        <option value="check_out_desc">Check-Out Time (Latest)</option>
    </select>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-bordered" id="visitorTable">
        <thead class="table-light">
            <tr>
                <th scope="col">No.</th>
                <th scope="col" onclick="sortTable(1)">Visiting Person</th>
                <th scope="col" onclick="sortTable(2)">Contact Info</th>
                <th scope="col" onclick="sortTable(3)">Purpose</th>
                <th scope="col" onclick="sortTable(4)">Resident Name</th>
                <th scope="col" onclick="sortTable(5)">Check-In</th>
                <th scope="col" onclick="sortTable(6)">Check-Out</th>
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
                    <td class="name"><?= htmlspecialchars($row['name']) ?></td>
                    <td class="contact_info"><?= htmlspecialchars($row['contact_info']) ?></td>
                    <td class="purpose"><?= htmlspecialchars($row['purpose']) ?></td>
                    <td class="visiting_person"><?= htmlspecialchars($row['visiting_person']) ?></td>
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
