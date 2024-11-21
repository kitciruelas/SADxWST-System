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


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if feedback_id is set in POST data
    if (isset($_POST['feedback_id'])) {
        // Sanitize input
        $feedback_id = intval($_POST['feedback_id']);

        // Delete feedback query
        $sql = "DELETE FROM roomfeedback WHERE id = ?";

        // Prepare and execute the query
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $feedback_id);

        if ($stmt->execute()) {
            // Directly set JavaScript alert and redirect to view-feedback.php
            echo "<script>alert('Feedback deleted successfully!'); window.location.href = 'view-feedback.php';</script>";
        } else {
            // Directly set JavaScript alert for error and redirect to view-feedback.php
            echo "<script>alert('Error deleting feedback: " . $stmt->error . "'); window.location.href = 'view-feedback.php';</script>";
        }

        // Close the statement
        $stmt->close();
    } else {
        // Directly set JavaScript alert for missing feedback ID and redirect to view-feedback.php
        echo "<script>alert('Invalid request: Feedback ID missing.'); window.location.href = 'view-feedback.php';</script>";
    }
}

$sql = "SELECT f.id AS feedback_id, r.room_number, CONCAT(u.fname, ' ', u.lname) AS resident_name, 
                f.feedback, f.submitted_at
        FROM rooms r
        LEFT JOIN roomassignments ra ON r.room_id = ra.room_id
        LEFT JOIN users u ON ra.user_id = u.id
        INNER JOIN roomfeedback f ON u.id = f.user_id";






$stmt = $conn->prepare($sql);

$stmt->execute();
$result = $stmt->get_result();




?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="Css_Admin/admin-manageuser.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/0.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.16.9/xlsx.full.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

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
            <a href="admin-room.php" class="nav-link"><i class="fas fa-building"></i> <span>Room Manager</span></a>
            <a href="admin-visitor_log.php" class="nav-link"><i class="fas fa-address-book"></i> <span>Log Visitor</span></a>
            <a href="admin-monitoring.php" class="nav-link"><i class="fas fa-eye"></i> <span>Monitoring</span></a>
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
        
        <h2>Room Feedback</h2>
        
    </div>
    

                              <!-- AYUSIN ANG ROOM ASSIGN AND REASSINGN HUHU -->
    <!-- Main content -->
    <div class="main-content">
    <div class="container">

    <div class="d-flex justify-content-start">
    <a href="dashboard.php" class="btn " onclick="window.location.reload();">
    <i class="fas fa-arrow-left fa-2x me-1"></i></a>
</div>
<!-- HTML Form with Search and Filter -->
<!-- Search and Filter Section -->
<div class="container mt-1">
   <!-- Search and Filter Section -->
<!-- Search and Filter Section -->
<div class="row mb-4">
    <div class="col-12 col-md-8">
        <input type="text" id="searchInput" class="form-control custom-input-small" placeholder="Search for room details...">
    </div>
    <div class="col-6 col-md-2">
        <select id="filterSelect" class="form-select">
            <option value="all" selected>Filter by</option>
            <option value="resident">Resident</option>
            <option value="room_number">Room Number</option>
            <option value="feedback">Feedback</option>
        </select>
    </div>
    <div class="col-5 col-md-2 mb-3">
        <select id="sortSelect" class="form-select" style="width: 100%;">
            <option value="all" selected>Sort by</option>
            <option value="resident_asc">Resident (A to Z)</option>
            <option value="resident_desc">Resident (Z to A)</option>
            <option value="room_asc">Room (Low to High)</option>
            <option value="room_desc">Room (High to Low)</option>
        </select>
    </div>
</div>

<!-- Feedback Cards Section -->
<div class="row">
<?php
if ($result->num_rows > 0) {
    $counter = 1;
    while ($row = $result->fetch_assoc()) {
        echo "<div class='col-12 col-md-4 mb-4'>"; // Card container
        echo "<div class='card h-100 position-relative'>"; // Add relative position for the card
        
        // Delete button as an icon
        echo "<form method='POST' action='view-feedback.php' class='delete-form position-absolute' style='top: 10px; right: 10px;' onsubmit='return confirm(\"Are you sure you want to delete this feedback?\");'>";
        echo "<input type='hidden' name='feedback_id' value='" . htmlspecialchars($row['feedback_id']) . "'>";
        echo "<button type='submit' class='btn btn-link text-danger delete-btn p-0' title='Delete Feedback'>";
        echo "<i class='bi bi-x-circle' style='font-size: 1.5rem;'></i>"; // Bootstrap icon for delete
        echo "</button>";
        echo "</form>";
        
        echo "<div class='card-body'>";
        echo "<h5 class='card-title'>" . htmlspecialchars($row['resident_name']) . "</h5>";
        echo "<h6 class='card-subtitle mb-2 text-muted'>Room " . htmlspecialchars($row['room_number']) . "</h6>";
        echo "<p class='card-text'>" . htmlspecialchars($row['feedback']) . "</p>";
        echo "<p class='card-text'><small class='text-muted'>Submitted on: " . htmlspecialchars($row['submitted_at']) . "</small></p>";
        echo "</div>"; // Close card-body
        
        echo "</div>"; // Close card
        echo "</div>"; // Close column
        $counter++;
    }
} else {
    echo "<div class='col-12'><p>No feedback found.</p></div>";
}
?>

<style>
    /* General container styling for card layout */
.col-8.col-md-4 {
    display: flex;
    justify-content: center;
}

/* Card styling */
.card {
    border: 1px solid #e0e0e0;
    border-radius: 10px;
    box-shadow: 0px 4px 6px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0px 8px 15px rgba(0, 0, 0, 0.2);
}

/* Card body styling */
.card-body {
    padding: 10px;
    background-color: #fdfdfd;
}

/* Card title */
.card-title {
    font-size: 18px;
    font-weight: bold;
    color: #333;
    margin-bottom: 10px;
}

/* Card subtitle */
.card-subtitle {
    font-size: 14px;
    color: #888;
    margin-bottom: 15px;
}

/* Card text */
.card-text {
    font-size: 14px;
    color: #555;
    margin-bottom: 10px;
}

/* Text for date */
.card-text small {
    font-size: 12px;
    color: #aaa;
}

/* Button styling */
.btn-danger {
    background-color: #e74c3c;
    border: none;
    color: #fff;
    padding: 8px 12px;
    font-size: 14px;
    border-radius: 5px;
    cursor: pointer;
    transition: background-color 0.3s ease;
}

.btn-danger:hover {
    background-color: #c0392b;
}

.btn-danger:focus {
    outline: none;
    box-shadow: 0px 0px 5px rgba(231, 76, 60, 0.5);
}

</style>
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
        document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const filterSelect = document.getElementById('filterSelect');
    const sortSelect = document.getElementById('sortSelect');
    const feedbackCards = document.querySelectorAll('.card');

    // Search functionality
    searchInput.addEventListener('input', function() {
        const query = searchInput.value.toLowerCase();
        feedbackCards.forEach(function(card) {
            const residentName = card.querySelector('.card-title').textContent.toLowerCase();
            const roomNumber = card.querySelector('.card-subtitle').textContent.toLowerCase();
            const feedbackText = card.querySelector('.card-text').textContent.toLowerCase();
            if (residentName.includes(query) || roomNumber.includes(query) || feedbackText.includes(query)) {
                card.parentElement.style.display = ''; // Show the card
            } else {
                card.parentElement.style.display = 'none'; // Hide the card
            }
        });
    });

    // Filter functionality
    filterSelect.addEventListener('change', function() {
        const filterValue = filterSelect.value;
        feedbackCards.forEach(function(card) {
            const residentName = card.querySelector('.card-title').textContent.toLowerCase();
            const roomNumber = card.querySelector('.card-subtitle').textContent.toLowerCase();
            const feedbackText = card.querySelector('.card-text').textContent.toLowerCase();

            if (filterValue === 'resident' && !residentName.includes(searchInput.value.toLowerCase())) {
                card.parentElement.style.display = 'none';
            } else if (filterValue === 'room_number' && !roomNumber.includes(searchInput.value.toLowerCase())) {
                card.parentElement.style.display = 'none';
            } else if (filterValue === 'feedback' && !feedbackText.includes(searchInput.value.toLowerCase())) {
                card.parentElement.style.display = 'none';
            } else {
                card.parentElement.style.display = ''; // Show the card
            }
        });
    });

    // Sort functionality
    sortSelect.addEventListener('change', function() {
        const sortValue = sortSelect.value;
        let sortedCards = Array.from(feedbackCards);
        sortedCards.sort(function(a, b) {
            const residentA = a.querySelector('.card-title').textContent.toLowerCase();
            const residentB = b.querySelector('.card-title').textContent.toLowerCase();
            const roomA = a.querySelector('.card-subtitle').textContent.toLowerCase();
            const roomB = b.querySelector('.card-subtitle').textContent.toLowerCase();

            if (sortValue === 'resident_asc') {
                return residentA.localeCompare(residentB);
            } else if (sortValue === 'resident_desc') {
                return residentB.localeCompare(residentA);
            } else if (sortValue === 'room_asc') {
                return roomA.localeCompare(roomB);
            } else if (sortValue === 'room_desc') {
                return roomB.localeCompare(roomA);
            }
            return 0;
        });

        // Append sorted cards back to the container
        const cardsContainer = document.querySelector('.row'); // Parent of the feedback cards
        sortedCards.forEach(function(card) {
            cardsContainer.appendChild(card.parentElement);
        });
    });
});


document.getElementById("searchInput").addEventListener("keyup", function() {
        var value = this.value.toLowerCase();
        var rows = document.querySelectorAll("#assignmentTable tbody tr");

        rows.forEach(function(row) {
            var cells = row.getElementsByTagName("td");
            var matches = false;

            for (var i = 0; i < cells.length; i++) {
                if (cells[i].innerText.toLowerCase().includes(value)) {
                    matches = true;
                    break;
                }
            }

            row.style.display = matches ? "" : "none";
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

        function filterTable() {
            const filterBy = filterSelect.value;
            const searchTerm = searchInput.value.toLowerCase();

            Array.from(rows).forEach(row => {
                let cellText = '';

                // Get text based on selected filter
                switch(filterBy) {
                    case 'resident':
                        cellText = row.querySelector('.resident').textContent.toLowerCase();
                        break;
                    case 'current_room':
                        cellText = row.querySelector('.current_room').textContent.toLowerCase();
                        break;
                    case 'new_room':
                        cellText = row.querySelector('.new_room').textContent.toLowerCase();
                        break;
                    case 'monthly_rent':
                        cellText = row.querySelector('.monthly_rent').textContent.toLowerCase();
                        break;
                    default:
                        // Search across all columns if "all" is selected
                        cellText = row.textContent.toLowerCase();
                }

                // Show or hide row based on search match
                row.style.display = cellText.includes(searchTerm) ? '' : 'none';
            });
        }

        // Attach event listeners to filter and search input
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
