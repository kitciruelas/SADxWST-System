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

// Capture search and filter values
$search = isset($_GET['search']) ? $_GET['search'] : ''; 
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all'; 

// Base SQL query
$sql = "SELECT * FROM rooms WHERE 1";

// Apply search conditions
if (!empty($search)) {
    $search = $conn->real_escape_string($search); // Prevent SQL injection

    switch ($filter) {
        case 'room_number':
            $sql .= " AND room_number LIKE '%$search%'";
            break;
        case 'capacity':
            $sql .= " AND capacity LIKE '%$search%'";
            break;
        case 'status':
            $sql .= " AND status LIKE '%$search%'";
            break;
        default:
            $sql .= " AND (room_number LIKE '%$search%' OR capacity LIKE '%$search%' OR status LIKE '%$search%')";
            break;
    }
}

// Handle form submission for adding a new room
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['room_id'])) {
    $target_dir = "../uploads/";
    $room_pic = null;

    // Handle image upload if any
    if (isset($_FILES["room_pic"]) && $_FILES["room_pic"]["error"] === 0) {
        $imageFileType = strtolower(pathinfo($_FILES["room_pic"]["name"], PATHINFO_EXTENSION));
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($imageFileType, $allowedTypes)) {
            $target_file = $target_dir . basename($_FILES["room_pic"]["name"]);
            if (move_uploaded_file($_FILES["room_pic"]["tmp_name"], $target_file)) {
                $room_pic = $target_file;
            } else {
                die("Error uploading image.");
            }
        } else {
            die("Invalid file type. Only JPG, JPEG, PNG & GIF files are allowed.");
        }
    }

    // Validate required fields
    if (empty($_POST['room_number']) || empty($_POST['capacity']) || empty($_POST['room_monthlyrent']) || empty($_POST['room_desc']) || empty($_POST['status'])) {
        die("Error: Missing required fields.");
    }

    // Check if the room number already exists
    $sql_check = "SELECT COUNT(*) FROM Rooms WHERE room_number = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("s", $_POST['room_number']);
    $stmt_check->execute();
    $stmt_check->bind_result($count);
    $stmt_check->fetch();
    $stmt_check->close();

    if ($count > 0) {
        // If room number exists, show an error
        echo "<script>alert('Error: Room number already exists!'); window.location.href = 'roomlist.php';</script>";
    } else {
        // Insert room data using prepared statement
        $sql = "INSERT INTO Rooms (room_number, room_desc, room_pic, room_monthlyrent, capacity, status) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssds", $_POST['room_number'], $_POST['room_desc'], $room_pic, $_POST['room_monthlyrent'], $_POST['capacity'], $_POST['status']);

        if ($stmt->execute()) {
            // Get the total number of rooms after the new insertion
            $sql_count = "SELECT COUNT(*) FROM Rooms";
            $stmt_count = $conn->prepare($sql_count);
            $stmt_count->execute();
            $stmt_count->bind_result($total_rooms);
            $stmt_count->fetch();
            $stmt_count->close();

            // Success message showing the total number of rooms
            echo "<script>alert('Room added successfully! Total rooms: $total_rooms'); window.location.href = 'roomlist.php';</script>";
        } else {
            echo "Error: " . $stmt->error;
        }

        $stmt->close();
    }
}


/// Handle form submission for updating a room
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['room_id'])) {
    $room_id = $_POST['room_id'];

    // Check if the room number already exists for a different room
    $sql_check = "SELECT COUNT(*) FROM Rooms WHERE room_number = ? AND room_id != ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("si", $_POST['room_number'], $room_id);
    $stmt_check->execute();
    $stmt_check->bind_result($count);
    $stmt_check->fetch();
    $stmt_check->close();

    if ($count > 0) {
        // If room number already exists for another room, show an error
        echo "<script>alert('Error: Room number already exists for another room!'); window.location.href = 'roomlist.php';</script>";
    } else {
        // Update room data using prepared statement
        $sql = "UPDATE rooms SET room_number = ?, capacity = ?, room_monthlyrent = ?, room_desc = ?, status = ? WHERE room_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sidssi', $_POST['room_number'], $_POST['capacity'], $_POST['room_monthlyrent'], $_POST['room_desc'], $_POST['status'], $room_id);

        if ($stmt->execute()) {
            // Get the total number of rooms after the update
            $sql_count = "SELECT COUNT(*) FROM Rooms";
            $stmt_count = $conn->prepare($sql_count);
            $stmt_count->execute();
            $stmt_count->bind_result($total_rooms);
            $stmt_count->fetch();
            $stmt_count->close();

            // Success message showing the total number of rooms
            echo "<script>alert('Room updated successfully! Total rooms: $total_rooms'); window.location.href = 'roomlist.php';</script>";
        } else {
            echo "Error updating room: " . $stmt->error;
        }

        $stmt->close();
    }
}


// Handle room deletion
if (isset($_GET['delete_room_id'])) {
    $room_id = intval($_GET['delete_room_id']);
    $sql = "DELETE FROM rooms WHERE room_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $room_id);

    if ($stmt->execute()) {
        // Use JavaScript for alert and redirection after success
        echo "<script>
                alert('Room deleted successfully');
                window.location.href = 'roomlist.php?delete_success=1';
              </script>";
        exit(); // Ensure no further code is executed after redirection
    } else {
        echo "Error deleting room: " . $conn->error;
    }

    $stmt->close();
}



// Fetch room details for editing
$editRoom = null;
if (isset($_GET['edit_room_id'])) {
    $room_id = intval($_GET['edit_room_id']);
    $sql = "SELECT * FROM rooms WHERE room_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $room_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $editRoom = $result->fetch_assoc();
    }
    $stmt->close();
}

// Fetch all room data for the room listing
$sql = "SELECT room_id, room_number, room_desc, capacity, room_monthlyrent, status, room_pic FROM rooms";
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
                <a class="dropdown-item" href="roomlist.php">
                    <i class="fas fa-list"></i> <span>Room List</span>
                </a>
                <a class="dropdown-item" href="room-assign.php">
                    <i class="fas fa-user-check"></i> <span>Room Assign</span>
                </a>
                <a class="dropdown-item" href="application-room.php">
                    <i class="fas fa-file-alt"></i> <span>Room Application</span>
                </a>

            </div>
            <a href="admin-visitor_log.php" class="nav-link"><i class="fas fa-address-book"></i> <span>Log Visitor</span></a>

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
                <th>No</th> <!-- Changed ID to No -->
                <th>Room Number</th>
                <th>Room Description</th>
                <th>Capacity</th>
                <th>Monthly Rent</th> <!-- Monthly Rent Column -->
                <th>Status</th>
                <th>Room Picture</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody id="room-table-body">
            <?php
            if ($result->num_rows > 0) {
                $counter = 1; // Initialize counter for No column
                while ($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . $counter++ . "</td>"; // Increment counter for each row
                    echo "<td>" . $row["room_number"] . "</td>";
                    echo "<td>" . $row["room_desc"] . "</td>";
                    echo "<td>" . $row["capacity"] . "</td>";
                    echo "<td>" . number_format($row["room_monthlyrent"], 2) . "</td>"; // Monthly Rent, formatted with 2 decimals
                    echo "<td>" . $row["status"] . "</td>";
                    echo "<td>";
                    if (!empty($row["room_pic"])) {
                        echo "<img src='" . $row["room_pic"] . "' alt='Room Image' width='100'>";
                    } else {
                        echo "No Image";
                    }
                    echo "</td>";
                    echo "<td>";
                    echo "<a href='?edit_room_id=" . $row["room_id"] . "' class='custom-btn edit-btn'>Edit</a>";
                    echo "<form method='GET' action='roomlist.php' style='display:inline;' onsubmit='return confirmDelete()'>
                    <input type='hidden' name='delete_room_id' value='" . $row["room_id"] . "' />
                    <button type='submit' class='custom-btn delete-btn'>Delete</button>
                  </form>";
                    echo "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='8'>No rooms found</td></tr>"; // Update colspan to match number of columns
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
        </div>
    </div>
    <?php if ($editRoom): ?>
    <div class="modal fade show" id="editRoomModal" tabindex="-1" aria-labelledby="editRoomModalLabel" aria-modal="true" style="display:block; background-color: rgba(0,0,0,0.5);">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editRoomModalLabel">Edit Room</h5>
                    <a href="roomlist.php" class="btn-close" aria-label="Close"></a>
                </div>
                <div class="modal-body">
                    <form id="editRoomForm" action="roomlist.php" method="post">
                        <!-- Hidden input for room_id -->
                        <input type="hidden" id="edit_room_id" name="room_id" value="<?php echo isset($editRoom['room_id']) ? htmlspecialchars($editRoom['room_id']) : ''; ?>">

                        <!-- Room Number -->
                        <div class="mb-3">
                            <label for="room_number" class="form-label">Room Number</label>
                            <input type="text" class="form-control" id="edit_room_number" name="room_number" value="<?php echo isset($editRoom['room_number']) ? htmlspecialchars($editRoom['room_number']) : ''; ?>" required>
                        </div>

                        <!-- Capacity -->
                        <div class="mb-3">
                            <label for="capacity" class="form-label">Capacity</label>
                            <input type="number" class="form-control" id="edit_capacity" name="capacity" value="<?php echo isset($editRoom['capacity']) ? htmlspecialchars($editRoom['capacity']) : ''; ?>" required>
                        </div>

                        <!-- Monthly Rent -->
                        <div class="mb-3">
                            <label for="monthly_rent" class="form-label">Monthly Rent</label>
                            <input type="number" step="0.01" class="form-control" id="edit_monthly_rent" name="room_monthlyrent" value="<?php echo isset($editRoom['room_monthlyrent']) ? htmlspecialchars($editRoom['room_monthlyrent']) : ''; ?>" required>
                        </div>

                        <!-- Description -->
                        <div class="mb-3">
                            <label for="room_desc" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_room_desc" name="room_desc" required><?php echo isset($editRoom['room_desc']) ? htmlspecialchars($editRoom['room_desc']) : ''; ?></textarea>
                        </div>

                        <!-- Status -->
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="edit_status" name="status" required>
                                <option value="available" <?php echo isset($editRoom['status']) && $editRoom['status'] == 'available' ? 'selected' : ''; ?>>Available</option>
                                <option value="occupied" <?php echo isset($editRoom['status']) && $editRoom['status'] == 'occupied' ? 'selected' : ''; ?>>Occupied</option>
                                <option value="maintenance" <?php echo isset($editRoom['status']) && $editRoom['status'] == 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                            </select>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" class="btn btn-primary">Save changes</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>



    <!-- Add Room Modal -->
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
                        <label for="room_monthlyrent" class="form-label">Monthly Rent</label>
                        <input type="number" step="0.01" class="form-control" id="room_monthlyrent" name="room_monthlyrent" placeholder="Monthly Rent" required>
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
                    <button type="submit" class="btn btn-primary">ADD</button>
                </div>
            </form>
        </div>
    </div>
</div>


    <!-- Bootstrap JS and Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Hamburger Menu Script -->
    <script>
         function openEditModal(roomId) {
        // Fetch room data using AJAX or populate it using server-side rendering
        fetch('get_room.php?room_id=' + roomId)
            .then(response => response.json())
            .then(room => {
                // Populate modal fields with room data
                document.getElementById('edit_room_id').value = room.room_id;
                document.getElementById('edit_room_number').value = room.room_number;
                document.getElementById('edit_capacity').value = room.capacity;
                document.getElementById('edit_monthly_rent').value = room.monthly_rent;
                document.getElementById('edit_room_desc').value = room.room_desc;
                document.getElementById('edit_status').value = room.status;

                // Show the modal
                const editRoomModal = new bootstrap.Modal(document.getElementById('editRoomModal'));
                editRoomModal.show();
            })
            .catch(error => console.error('Error:', error));
    }

    function confirmDelete() {
    return confirm("Are you sure you want to delete this room?");
}
     // JavaScript for client-side pagination
     const rowsPerPage = 10; // Limit to 10 rows per page
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
