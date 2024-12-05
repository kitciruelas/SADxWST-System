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
    if (isset($_POST['feedback_id'])) {
        $feedback_id = intval($_POST['feedback_id']);
        
        // Debug line - you can remove this after confirming it works
        error_log("Attempting to delete feedback ID: " . $feedback_id);
        
        $sql = "DELETE FROM roomfeedback WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $feedback_id);
        
        if ($stmt->execute()) {
            // Add debug line
            error_log("Successfully deleted feedback ID: " . $feedback_id);
            header("Location: view-feedback.php");
            exit();
        } else {
            // Add error logging
            error_log("Error deleting feedback: " . $conn->error);
            echo "Error deleting record: " . $conn->error;
        }
        $stmt->close();
    }
}

$sql = "SELECT 
    f.id AS feedback_id,
    r.room_number,
    CONCAT(u.fname, ' ', u.lname) AS resident_name,
    f.feedback,
    f.submitted_at
FROM roomfeedback f
LEFT JOIN users u ON f.user_id = u.id
LEFT JOIN roomassignments ra ON f.assignment_id = ra.assignment_id
LEFT JOIN rooms r ON ra.room_id = r.room_id
ORDER BY f.submitted_at DESC";






$stmt = $conn->prepare($sql);

$stmt->execute();
$result = $stmt->get_result();




?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback</title>
    <link rel="icon" href="../img-icon/feed.webp" type="image/png">

    <link rel="stylesheet" href="../Admin/Css_Admin/admin_manageuser.css">
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
        <i class="fas fa-bars"></i> <!-- Hamburger menu icon -->
    </div>

    <div class="sidebar-nav">
    <a href="user-dashboard.php" class="nav-link active"><i class="fas fa-home"></i><span>Home</span></a>
        <a href="admin-room.php" class="nav-link"><i class="fas fa-building"></i> <span>Room Manager</span></a>
        <a href="admin-visitor_log.php" class="nav-link"><i class="fas fa-user-check"></i> <span>Visitor log</span></a>
        <a href="admin-monitoring.php" class="nav-link"><i class="fas fa-eye"></i> <span>Monitoring</span></a>

        <a href="staff-chat.php" class="nav-link"><i class="fas fa-comments"></i> <span>Chat</span></a>

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

    <!-- Top bar -->
    <div class="topbar">
        
        <h2>Room Feedback</h2>
        
    </div>
    

                              <!-- AYUSIN ANG ROOM ASSIGN AND REASSINGN HUHU -->
    <!-- Main content -->
    <div class="main-content">
    <div class="container">

    <div class="d-flex justify-content-start">
    <a href="user-dashboard.php" class="btn btn-back">
        <i class="fas fa-arrow-left fa-2x me-1"></i>
    </a>
</div>
<!-- HTML Form with Search and Filter -->
<!-- Search and Filter Section -->
<div class="container">
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
        <div class="col-6 col-md-2 mt-2">
            <select id="filterSelect" class="form-select">
                <option value="all" selected>Filter by</option>
                <option value="resident">Resident</option>
                <option value="room_number">Room Number</option>
                <option value="feedback">Feedback</option>
            </select>
        </div>

        <!-- Sort Dropdown -->
        <div class="col-6 col-md-2 mt-2">
            <select id="sortSelect" class="form-select">
                <option value="all" selected>Sort by</option>
                <option value="resident_asc">Resident (A to Z)</option>
                <option value="resident_desc">Resident (Z to A)</option>
                <option value="room_asc">Room (Low to High)</option>
                <option value="room_desc">Room (High to Low)</option>
            </select>
        </div>
    </div>
</div>

<!-- Feedback Cards Section -->
<div class="row">
<?php
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<div class='col-12 col-md-4 mb-4'>";
        echo "<div class='card h-100 feedback-card'>";
        
        // Delete button with improved styling
        echo "<form method='POST' action='" . htmlspecialchars($_SERVER['PHP_SELF']) . "' class='delete-form'>";
        echo "<input type='hidden' name='feedback_id' value='" . htmlspecialchars($row['feedback_id']) . "'>";
        echo "<button type='submit' class='btn delete-btn' 
              onclick='return confirm(\"Are you sure you want to delete this feedback?\");'>";
        echo "<i class='bi bi-x-circle'></i>";
        echo "</button>";
        echo "</form>";
        
        echo "<div class='card-body d-flex flex-column'>";
        echo "<div class='header-section'>";
        echo "<h5 class='card-title'>" . htmlspecialchars($row['resident_name']) . "</h5>";
        echo "<h6 class='card-subtitle'>Room " . htmlspecialchars($row['room_number']) . "</h6>";
        echo "</div>";
        echo "<div class='feedback-content'>";
        echo "<p class='card-text'>" . htmlspecialchars($row['feedback']) . "</p>";
        echo "</div>";
        echo "<div class='timestamp mt-auto'>";
        echo "<small>Submitted on: " . htmlspecialchars($row['submitted_at']) . "</small>";
        echo "</div>";
        echo "</div>";
        
        echo "</div>";
        echo "</div>";
    }
} else {
    echo "<div class='col-12 text-center'><p class='no-feedback'>No feedback found.</p></div>";
}
?>
<style>
    /* Container spacing */
    .container.mt-1 {
        padding: 20px;
        max-width: 1400px;
        margin: 0 auto;
    }

    /* Search and filter controls */
    .custom-input-small {
        height: 38px;
        border-radius: 6px;
        border: 1px solid #ced4da;
    }

    .form-select {
        height: 38px;
        border-radius: 6px;
        border: 1px solid #ced4da;
        background-color: white;
    }

    /* Card grid layout */
    .col-12.col-md-4.mb-4 {
        padding: 10px;
    }

    /* Enhanced card styling */
    .card {
        border: none;
        border-radius: 12px;
        box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
        transition: all 0.3s ease;
        background: white;
        height: 100%;
        position: relative;
    }

    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 5px 20px rgba(0, 0, 0, 0.12);
    }

    /* Card content styling */
    .card-body {
        padding: 1.5rem;
    }

    .card-title {
        color: #2B228A;
        font-size: 1.2rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        padding-right: 30px; /* Space for delete button */
    }

    .card-subtitle {
        color: #6c757d;
        font-size: 0.9rem;
        margin-bottom: 1rem;
    }

    .card-text {
        color: #495057;
        font-size: 0.95rem;
        line-height: 1.5;
        margin-bottom: 1rem;
    }

    .card-text small {
        color: #868e96;
        font-size: 0.85rem;
    }

    /* Delete button styling */
    .delete-btn {
        position: absolute;
        top: 15px;
        right: 15px;
        opacity: 1;
        transition: opacity 0.2s ease;
    }

    .card:hover .delete-btn {
        opacity: 1;
    }

    .delete-btn i {
        color: #dc3545;
        transition: color 0.2s ease;
    }

    .delete-btn:hover i {
        color: #c82333;
    }

    /* Back button styling */
    .btn-back {
        color: #2B228A;
        transition: transform 0.2s ease;
        margin-bottom: 1rem;
    }

    .btn-back:hover {
        transform: translateX(-5px);
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .col-12.col-md-4.mb-4 {
            padding: 10px 5px;
        }
        
        .card-body {
            padding: 1rem;
        }
        
        .delete-btn {
            opacity: 1;
        }
    }

    .feedback-card {
        border: none;
        border-radius: 15px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        overflow: hidden;
        background: #ffffff;
    }

    .feedback-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    }

    .header-section {
        border-bottom: 1px solid #eee;
        padding-bottom: 12px;
        margin-bottom: 12px;
    }

    .card-title {
        color: #2B228A;
        font-size: 1.25rem;
        font-weight: 600;
        margin-bottom: 5px;
        padding-right: 35px;
    }

    .card-subtitle {
        color: #666;
        font-size: 1rem;
        font-weight: 500;
    }

    .feedback-content {
        flex-grow: 1;
        padding: 10px 0;
    }

    .feedback-content .card-text {
        color: #444;
        line-height: 1.6;
        margin-bottom: 15px;
    }

    .timestamp {
        color: #888;
        font-size: 0.85rem;
        border-top: 1px solid #eee;
        padding-top: 12px;
    }

    .delete-btn {
        position: absolute;
        top: 15px;
        right: 15px;
        padding: 0;
        background: none;
        border: none;
        opacity: 0;
        transition: opacity 0.2s ease;
    }

    .feedback-card:hover .delete-btn {
        opacity: 1;
    }

    .delete-btn i {
        color: #dc3545;
        font-size: 1.25rem;
        transition: color 0.2s ease;
    }

    .delete-btn:hover i {
        color: #c82333;
    }

    .no-feedback {
        color: #666;
        font-size: 1.1rem;
        padding: 20px;
        background: #f8f9fa;
        border-radius: 10px;
        text-align: center;
    }
</style>
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
