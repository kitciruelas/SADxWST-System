<?php
session_start();

// Redirect to login if not logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: admin-login.php");
    exit;
}

include '../config/config.php'; // Ensure this is correct

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$search = isset($_GET['search']) ? $_GET['search'] : ''; // Capture search query if available
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all'; // Capture filter criteria

// Base SQL query
$sql = "SELECT * FROM rooms WHERE 1";

// If there's a search term, apply it to the SQL query based on the filter selected
if (!empty($search)) {
    $search = $conn->real_escape_string($search); // Sanitize user input to prevent SQL injection

    // Apply the search condition based on the filter type
    if ($filter == 'room_number') {
        // Search only by Room Number
        $sql .= " AND room_number LIKE '%$search%'";
    } elseif ($filter == 'capacity') {
        // Search only by Capacity
        $sql .= " AND capacity LIKE '%$search%'";
    } elseif ($filter == 'status') {
        // Search only by Status
        $sql .= " AND status LIKE '%$search%'";
    } else {
        // If no filter is selected or 'all', search across multiple fields
        $sql .= " AND (room_number LIKE '%$search%' OR capacity LIKE '%$search%' OR status LIKE '%$search%')";
    }
}

// Handle form submission for adding new rooms
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Directory where images will be stored
    $target_dir = "uploads/";  
    $room_pic = null;  // Default no image

    // Check if a file is uploaded
    if (isset($_FILES["room_pic"]) && $_FILES["room_pic"]["error"] === 0) {
        // Validate file type (only allow images)
        $imageFileType = strtolower(pathinfo($_FILES["room_pic"]["name"], PATHINFO_EXTENSION));
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($imageFileType, $allowedTypes)) {
            // Create the file path using the uploaded file's name
            $target_file = $target_dir . basename($_FILES["room_pic"]["name"]);
            
            // Move the uploaded file to the target directory
            if (move_uploaded_file($_FILES["room_pic"]["tmp_name"], $target_file)) {
                $room_pic = $target_file;  // Store the file path for database insertion
            } else {
                die("Error uploading image.");
            }
        } else {
            die("Invalid file type. Only JPG, JPEG, PNG & GIF files are allowed.");
        }
    }

    // Validate required fields from POST request
    if (
        empty($_POST['room_number']) || 
        empty($_POST['capacity']) || 
        empty($_POST['monthly_rent']) || 
        empty($_POST['room_desc']) || 
        empty($_POST['status'])
    ) {
        die("Error: Missing required fields.");
    }

    // Use prepared statement to insert room details into the database
    $sql = "INSERT INTO Rooms (room_number, room_desc, room_pic, room_monthlyrent, capacity, status)
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);

    // Bind parameters directly from $_POST array
    $stmt->bind_param(
        "ssssds", 
        $_POST['room_number'], 
        $_POST['room_desc'], 
        $room_pic, 
        $_POST['monthly_rent'], 
        $_POST['capacity'], 
        $_POST['status']
    );

    if ($stmt->execute()) {
        // Success message or redirection after submission
        echo "<script>alert('Room added successfully!'); window.location.href = 'roomlist.php';</script>";
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
}

// Fetch all room data from the Rooms table
$sql = "SELECT room_id, room_number, room_desc, room_pic, room_monthlyrent, capacity, status FROM Rooms";
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
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

            <!-- Room Manager Dropdown Menu -->
            <div class="nav-item dropdown">
                <a href="admin-room.php" class="nav-link dropdown-toggle" id="roomManagerDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="fas fa-building"></i> 
                    <span>Room Manager</span>
                </a>
                <div class="dropdown-menu" aria-labelledby="roomManagerDropdown" style="background-color: #2B228A; border-radius: 9px;">
                <!-- Dropdown Menu Items -->
                    <a class="nav-link active dropdown-item" href="roomlist.php">
                        <i class="fas fa-list"></i> Room List
                    </a>
                    <a class="nav-link dropdown-item" href="room-assign.php">
                        <i class="fas fa-user-check"></i> Room Assign
                    </a>
                    <a class="nav-link dropdown-item" href="application-room.php">
                        <i class="fas fa-file-alt"></i> Room Application
                    </a>

            </div>
            </div>

        </div>
        <div class="logout">
            <a href="../config/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
        </div>
    </div>

    <!-- Top bar -->
    <div class="topbar">
        <h2>Room Manage</h2>
    </div>

    <!-- Main content -->
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
                <option value="room_number">Room Number</option>
                <option value="capacity">Capacity</option>
                <option value="status">Status</option>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <button type="button" class="custom-btns" data-bs-toggle="modal" data-bs-target="#roomModal">Add Room</button>
        </div>
    </div>
            <!-- Room Table -->
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Room Number</th>
                            <th>Room Description</th>
                            <th>Capacity</th>
                            <th>Status</th>
                            <th>Room Picture</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="room-table-body">
                        <?php
                        if ($result->num_rows > 0) {
                            while($row = $result->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td>" . $row["room_id"] . "</td>";
                                echo "<td>" . $row["room_number"] . "</td>";
                                echo "<td>" . $row["room_desc"] . "</td>";
                                echo "<td>" . $row["capacity"] . "</td>";
                                echo "<td>" . $row["status"] . "</td>";
                                echo "<td>";
                                if (!empty($row["room_pic"])) {
                                    echo "<img src='" . $row["room_pic"] . "' alt='Room Image' width='100'>";
                                } else {
                                    echo "No Image";
                                }
                                echo "</td>";
                                echo "<td>";
                                echo "<button class='custom-btn'>Edit</button>";
                                echo "<button class='custom-btn delete-btn' onclick='deleteRoom(" . $row["room_id"] . ")'>Delete</button>";


                                echo "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='7'>No rooms found</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add/Edit Room Modal -->
    <div class="modal fade" id="roomModal" tabindex="-1" aria-labelledby="roomModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="roomModalLabel">Add Room</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="room-form" method="post" enctype="multipart/form-data">
                    <div class="modal-body">
                        <!-- Form Fields -->
                        <div class="mb-3">
                            <label for="room_number" class="form-label">Room Number</label>
                            <input type="text" class="form-control" id="room_number" name="room_number" placeholder="Room Number" required>
                        </div>

                        <div class="mb-3">
                            <label for="capacity" class="form-label">Capacity</label>
                            <input type="number" class="form-control" id="capacity" name="capacity" placeholder="Capacity" required>
                        </div>

                        <div class="mb-3">
                            <label for="monthly_rent" class="form-label">Monthly Rent</label>
                            <input type="number" step="0.01" class="form-control" id="monthly_rent" name="monthly_rent" placeholder="Monthly Rent" required>
                        </div>

                        <div class="mb-3">
                            <label for="room_desc" class="form-label">Description</label>
                            <textarea class="form-control" id="room_desc" name="room_desc" placeholder="Room Description" required></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="room_pic" class="form-label">Room Picture</label>
                            <input type="file" class="form-control" id="room_pic" name="room_pic" accept="image/*">
                        </div>

                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="">Select Status</option>
                                <option value="available">Available</option>
                                <option value="occupied">Occupied</option>
                                <option value="maintenance">Maintenance</option>
                            </select>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS and Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Hamburger Menu Script -->
    <script>

        const hamburgerMenu = document.getElementById('hamburgerMenu');
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.add('collapsed');

        hamburgerMenu.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            const icon = hamburgerMenu.querySelector('i');
            icon.classList.toggle('fa-bars');
            icon.classList.toggle('fa-times');
        });

        document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        const filterSelect = document.getElementById('filterSelect');
        const tableBody = document.getElementById('room-table-body');
        const rows = tableBody.getElementsByTagName('tr');

        // Search Input Event Listener
        searchInput.addEventListener('input', function() {
            filterTable();
        });

        // Filter Dropdown Event Listener
        filterSelect.addEventListener('change', function() {
            filterTable();
        });

        // Function to Filter the Table Rows
        function filterTable() {
            const searchText = searchInput.value.toLowerCase();
            const filterBy = filterSelect.value;

            for (let i = 0; i < rows.length; i++) {
                let row = rows[i];
                let cells = row.getElementsByTagName('td');
                let match = false;

                if (cells.length > 0) {
                    let roomNumber = cells[1].textContent.toLowerCase();
                    let capacity = cells[3].textContent.toLowerCase();
                    let status = cells[4].textContent.toLowerCase();

                    switch (filterBy) {
                        case 'room_number':
                            match = roomNumber.includes(searchText);
                            break;
                        case 'capacity':
                            match = capacity.includes(searchText);
                            break;
                        case 'status':
                            match = status.includes(searchText);
                            break;
                        default:
                            match = roomNumber.includes(searchText) || 
                                    capacity.includes(searchText) || 
                                    status.includes(searchText);
                            break;
                    }

                    // Show or hide row based on match
                    row.style.display = match ? '' : 'none';
                }
            }
        }
    });
    </script>
</body>
</html>
