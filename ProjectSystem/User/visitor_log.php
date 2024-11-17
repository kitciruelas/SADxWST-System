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

    // **Add Visitor Logic**
    if (isset($_POST['visitor_name'])) {
        $visitorName = trim($_POST['visitor_name']);
        $contactInfo = trim($_POST['contact_info']);
        $purpose = trim($_POST['purpose']);
        $checkInTime = $_POST['check_in_time'];

        // Validate input fields
        if (empty($visitorName) || empty($contactInfo) || empty($purpose) || empty($checkInTime)) {
            echo "<script>alert('All fields are required!'); window.history.back();</script>";
            exit();
        }
        if (!preg_match('/^(0?[9]\d{9}|[9]\d{9})$/', $contactInfo)) {
            echo "<script>alert('Contact Info must be a valid Philippine phone number (10 or 11 digits).');</script>";
            exit();
        }

        // Format datetime
        $currentDate = date('Y-m-d');
        $checkInDatetime = $currentDate . ' ' . $checkInTime . ':00';

        // Insert visitor into the database
        $stmt = $conn->prepare(
            "INSERT INTO visitors (name, contact_info, purpose, visiting_user_id, check_in_time) 
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("sssis", $visitorName, $contactInfo, $purpose, $userId, $checkInDatetime);

        if ($stmt->execute()) {
            echo "<script>alert('Visitor added successfully!'); window.location.href='visitor_log.php';</script>";
            exit();
        } else {
            echo "<script>alert('Error adding visitor: " . $stmt->error . "'); window.history.back();</script>";
            exit();
        }
    }

    // **Check-Out Logic**
    if (isset($_POST['visitor_id'])) {
        $visitorId = (int)$_POST['visitor_id'];

        $stmt = $conn->prepare(
            "UPDATE visitors SET check_out_time = NOW() WHERE id = ? AND visiting_user_id = ?"
        );
        $stmt->bind_param("ii", $visitorId, $userId);

        if ($stmt->execute()) {
            echo "<script>alert('Check-out successful!'); window.location.href='visitor_log.php';</script>";
            exit();
        } else {
            echo "<script>alert('Error updating check-out time: " . $stmt->error . "'); window.history.back();</script>";
            exit();
        }
    }

   // **Archive Visitor Logic**
if (isset($_POST['delete_visitor_id'])) {  // Using delete trigger as archive action
    $visitorId = (int)$_POST['delete_visitor_id'];

    // Start a transaction
    $conn->begin_transaction();

    // Step 1: Archive the visitor by copying it to the archive table
    $archiveSql = "INSERT INTO visitors_archive (id, name, contact_info, purpose, visiting_user_id, check_in_time, check_out_time, archived_at)
                   SELECT id, name, contact_info, purpose, visiting_user_id, check_in_time, check_out_time, NOW()
                   FROM visitors WHERE id = ? AND visiting_user_id = ?";
    $stmt = $conn->prepare($archiveSql);
    $stmt->bind_param("ii", $visitorId, $userId);

    if (!$stmt->execute()) {
        echo "<script>alert('Error archiving visitor: " . $stmt->error . "'); window.history.back();</script>";
        $conn->rollback();
        exit();
    }
    $stmt->close();

    // Step 2: Delete the visitor from the original `visitors` table
    $deleteSql = "DELETE FROM visitors WHERE id = ? AND visiting_user_id = ?";
    $stmt = $conn->prepare($deleteSql);
    $stmt->bind_param("ii", $visitorId, $userId);

    if (!$stmt->execute()) {
        echo "<script>alert('Error deleting visitor: " . $stmt->error . "'); window.history.back();</script>";
        $conn->rollback();
        exit();
    }
    $stmt->close();

    // Commit the transaction
    $conn->commit();

    echo "<script>alert('Visitor Deleted successfully!'); window.location.href='visitor_log.php';</script>";
    exit();
}

}



// Assuming you're using $_GET['filter'] to fetch the filter value
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';
$sql = "SELECT v.*, CONCAT(u.fname, ' ', u.lname) AS visiting_person 
        FROM visitors v 
        LEFT JOIN users u ON v.visiting_user_id = u.id";

// Modify query based on selected filter
if ($filter == 'today') {
    $sql .= " WHERE DATE(v.check_in_time) = CURDATE()"; // Filter for today's visits
} elseif ($filter == 'this_week') {
    $sql .= " WHERE WEEK(v.check_in_time) = WEEK(CURDATE())"; // Filter for this week's visits
} elseif ($filter == 'this_month') {
    $sql .= " WHERE MONTH(v.check_in_time) = MONTH(CURDATE())"; // Filter for this month's visits
}

$result = $conn->query($sql);
// Assuming you're using $_GET['sort'] to get the sort parameter
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name'; // Default sort by name

$sql = "SELECT v.*, CONCAT(u.fname, ' ', u.lname) AS visiting_person 
        FROM visitors v 
        LEFT JOIN users u ON v.visiting_user_id = u.id 
        ORDER BY v.$sort"; // Sorting by the selected field (name, check_in_time, or check_out_time)

$result = $conn->query($sql);


$sql = "SELECT v.*, CONCAT(u.fname, ' ', u.lname) AS visiting_person 
        FROM visitors v 
        LEFT JOIN users u ON v.visiting_user_id = u.id 
        WHERE v.visiting_user_id = ? 
        ORDER BY v.check_in_time DESC";  // Assuming you want to order by check-in time, adjust the field as needed


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
    <title>Dashboard</title>
    <link rel="stylesheet" href="Css_user/visitor-logs.css">
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
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="menu" id="hamburgerMenu">
            <i class="fas fa-bars"></i> <!-- Hamburger menu icon -->
        </div>

        <div class="sidebar-nav">
        <a href="user-dashboard.php" class="nav-link"><i class="fas fa-home"></i><span>Home</span></a>
        <a href="user_room.php" class="nav-link"><i class="fas fa-key"></i> <span>Room Assign</span></a>
        <a href="visitor_log.php" class="nav-link active"><i class="fas fa-user-check"></i> <span>Log Visitor</span></a>
        <a href="chat.php" class="nav-link"><i class="fas fa-comments"></i> <span>Chat</span></a>

        </div>
        
        <div class="logout">
            <a href="../config/user-logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
        </div>
    </div>

    <!-- Top bar -->
    <div class="topbar">
        <h2>Visitor Log</h2>

    </div>
    <div class="main-content">      
    <div class="container mt-1">
    <div class="d-flex justify-content-between align-items-center mb-3 mt-3">
    <!-- Search Input -->
    <div class="input-group w-50">
        <input type="text" class="form-control" id="searchInput" placeholder="Search for visitors..." onkeyup="searchTable()">
    </div>

    <!-- Filter Section -->
  <!-- Filter by Dropdown -->
<div class="d-flex align-items-center">
    <label for="filterSelect" class="form-label me-2">Filter by:</label>
    <select class="form-select w-auto" id="filterSelect" onchange="filterTable()">
        <option value="">All</option>
        <option value="today">Today</option>
        <option value="this_week">This Week</option>
        <option value="this_month">This Month</option>
    </select>
</div>


    <!-- Sort by Dropdown -->
    <div class="d-flex align-items-center">
        <label for="sortSelect" class="form-label me-2">Sort by:</label>
        <select class="form-select w-auto" id="sortSelect" onchange="sortTable()">
            <option value="name" selected>Name</option>
            <option value="check_in_time">Check-In Time</option>
            <option value="check_out_time">Check-Out Time</option>
        </select>
    </div>

    <!-- Log Visitor Button -->
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#visitorModal">Log Visitor</button>
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
                <th scope="col">Name</th>
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
                    <td><?= htmlspecialchars($row['visiting_person']) ?></td>
                    <td><?= $checkInDateTime ?></td>
                    <td><?= $checkOutDateTime ?></td>
                    <td>
                        <form action="visitor_log.php" method="post" style="display:inline;">
                            <input type="hidden" name="visitor_id" value="<?= $row['id'] ?>">
                            <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirm('Are you sure you want to check out this visitor?')" <?= $isCheckedOut ? 'disabled' : '' ?>>Check-Out</button>
                        </form>
                        <form action="visitor_log.php" method="post" style="display:inline;">
                            <input type="hidden" name="delete_visitor_id" value="<?= $row['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure?')">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; else: ?>
                <tr>
                    <td colspan="8" class="text-center">No visitors found.</td>
                </tr>
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

<!-- Log Visitor Modal -->
<div class="modal fade" id="visitorModal" tabindex="-1" aria-labelledby="visitorModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="visitorModalLabel">Log New Visitor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
    <i class="fas fa-times"></i> <!-- Font Awesome icon for close -->
</button>
            </div>
            <div class="modal-body">
                <form action="visitor_log.php" method="post" onsubmit="return validateForm()">
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
                    <div class="mb-3">
                        <label for="visitingUser" class="form-label">Name</label>
                        <input type="hidden" name="visiting_user_id" value="<?php echo $_SESSION['id']; ?>">
                        <input type="text" class="form-control" id="visitingUser" value="<?php echo htmlspecialchars($_SESSION['Fname'] . ' ' . $_SESSION['Lname']); ?>" readonly>
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
    const compareFunc = (a, b) => {
        let valueA, valueB;
        if (sortBy === "name") {
            valueA = a.querySelector("td:nth-child(5)").textContent.trim().toLowerCase();
            valueB = b.querySelector("td:nth-child(5)").textContent.trim().toLowerCase();
        } else if (sortBy === "check_in_time") {
            valueA = new Date(a.querySelector("td:nth-child(6)").textContent);
            valueB = new Date(b.querySelector("td:nth-child(6)").textContent);
        } else if (sortBy === "check_out_time") {
            valueA = new Date(a.querySelector("td:nth-child(7)").textContent);
            valueB = new Date(b.querySelector("td:nth-child(7)").textContent);
        }
        
        if (valueA < valueB) return -1;
        if (valueA > valueB) return 1;
        return 0;
    };

    // Sort the rows based on the selected option
    rows.sort(compareFunc);

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
    </script>
</body>
</html>
