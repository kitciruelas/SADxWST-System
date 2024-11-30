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

    // Validate capacity
    if (!is_numeric($_POST['capacity']) || $_POST['capacity'] < 1) {
        echo "<script>alert('Error: Capacity must be at least 1 person.'); window.location.href = 'roomlist.php';</script>";
        exit;
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
        echo "<script>alert('Error: Room already exists!'); window.location.href = 'roomlist.php';</script>";
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


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['room_id'])) {
    $room_id = $_POST['room_id'];

    // Validate capacity
    if (!is_numeric($_POST['capacity']) || $_POST['capacity'] < 1) {
        echo "<script>alert('Error: Capacity must be at least 1 person.'); window.location.href = 'roomlist.php';</script>";
        exit;
    }

    // Check if new capacity is less than current occupants
    $check_occupants_sql = "SELECT COUNT(*) as current_occupants FROM roomassignments WHERE room_id = ?";
    $check_stmt = $conn->prepare($check_occupants_sql);
    $check_stmt->bind_param("i", $room_id);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    $current_occupants = $result->fetch_assoc()['current_occupants'];
    $check_stmt->close();

    if ($current_occupants > $_POST['capacity']) {
        echo "<script>alert('Error: Cannot reduce capacity below current number of occupants (" . $current_occupants . ").'); window.location.href = 'roomlist.php';</script>";
        exit;
    }

    // Check if the room number already exists for a different room
    $sql_check = "SELECT COUNT(*) FROM rooms WHERE room_number = ? AND room_id != ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("si", $_POST['room_number'], $room_id);
    $stmt_check->execute();
    $stmt_check->bind_result($count);
    $stmt_check->fetch();
    $stmt_check->close();

    if ($count > 0) {
        echo "<script>alert('Error: Room already exists for another room!'); window.location.href = 'roomlist.php';</script>";
    } else {
        // Prepare the base SQL update statement
        $sql = "UPDATE rooms SET room_number = ?, capacity = ?, room_monthlyrent = ?, room_desc = ?, status = ?";
        $params = [
            $_POST['room_number'],
            $_POST['capacity'],
            $_POST['room_monthlyrent'],
            $_POST['room_desc'],
            $_POST['status'],
        ];

        // Handle room picture upload
        if (isset($_FILES['room_picture']) && $_FILES['room_picture']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = "../uploads/";
            $fileName = time() . '_' . basename($_FILES['room_picture']['name']);
            $uploadPath = $uploadDir . $fileName;

            // Check if upload directory exists and is writable
            if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
                error_log("Upload directory does not exist or is not writable: " . $uploadDir, 3, 'error.log');
                echo "<script>alert('Upload directory is not accessible.'); window.location.href = 'roomlist.php';</script>";
                exit;
            }

            // Move the uploaded file
            if (move_uploaded_file($_FILES['room_picture']['tmp_name'], $uploadPath)) {
                // Append to SQL statement and parameters if upload is successful
                $sql .= ", room_pic = ?";
                $params[] = $fileName; // Add the new file name to the parameters
            } else {
                error_log("Error moving uploaded file: " . $_FILES['room_picture']['tmp_name'], 3, 'error.log');
                echo "<script>alert('Error uploading room picture.'); window.location.href = 'roomlist.php';</script>";
                exit;
            }
        }

        // Add the room_id to the parameters and complete the SQL statement
        $sql .= " WHERE room_id = ?";
        $params[] = $room_id;

        // Prepare the statement for execution
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            // Determine the type string based on parameters
            $types = str_repeat('s', count($params) - 1) . 'i'; // All parameters are strings except the last one (room_id)
            $stmt->bind_param($types, ...$params);

            // Execute the update
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    echo "<script>alert('Room updated successfully!'); window.location.href = 'roomlist.php';</script>";
                } else {
                    echo "<script>alert('No changes made to the room.'); window.location.href = 'roomlist.php';</script>";
                }
            } else {
                error_log("SQL error: " . $stmt->error, 3, 'error.log');
                echo "<script>alert('Error updating room in database: " . $stmt->error . "'); window.location.href = 'roomlist.php';</script>";
            }
            $stmt->close();
        } else {
            error_log("Prepare statement error: " . $conn->error, 3, 'error.log');
            echo "<script>alert('Error preparing SQL statement.'); window.location.href = 'roomlist.php';</script>";
        }
    }
}



// Handle room archiving instead of deletion
if (isset($_GET['delete_room_id'])) {
    $room_id = intval($_GET['delete_room_id']); // Sanitize input

    // Step 1: Archive the room by copying it to the archive table
    $archiveSql = "INSERT INTO rooms_archive (room_id, room_number, room_desc, room_pic, room_monthlyrent, capacity, status, created_at, archived_at)
                   SELECT room_id, room_number, room_desc, room_pic, room_monthlyrent, capacity, status, created_at, NOW()
                   FROM rooms WHERE room_id = ?";
    
    $archiveStmt = $conn->prepare($archiveSql);
    $archiveStmt->bind_param('i', $room_id);

    if ($archiveStmt->execute()) {
        // Step 2: Delete the room from the original table after archiving
        $deleteSql = "DELETE FROM rooms WHERE room_id = ?";
        $deleteStmt = $conn->prepare($deleteSql);
        $deleteStmt->bind_param('i', $room_id);

        if ($deleteStmt->execute()) {
            // Use JavaScript for alert and redirection after success
            echo "<script>
                    alert('Room archived and deleted successfully');
                    window.location.href = 'roomlist.php?delete_success=1';
                  </script>";
            exit(); // Ensure no further code is executed after redirection
        } else {
            echo "Error deleting room from the original table: " . $conn->error;
        }

        $deleteStmt->close();
    } else {
        echo "Error archiving room: " . $archiveStmt->error;
    }

    $archiveStmt->close();
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

// Query to get rooms, capacities, and current occupants
$query = "
    SELECT 
        r.room_id,
        r.room_number,
        r.room_desc,
        r.capacity AS totalCapacity,
        r.room_monthlyrent,
        r.status,
        r.room_pic,
        COUNT(ra.assignment_id) AS currentOccupants
    FROM 
        rooms r
    LEFT JOIN 
        roomassignments ra ON r.room_id = ra.room_id
    GROUP BY 
        r.room_id
";
$result = $conn->query($query);




?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Room List</title>
    <link rel="icon" href="img-icon/rlist.png" type="image/png">

    <link rel="stylesheet" href="Css_Admin/admin_manageuser.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">

<!-- DataTables Buttons Plugin CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.3/css/buttons.dataTables.min.css">

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">

<!-- DataTables Buttons Plugin CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.3/css/buttons.dataTables.min.css">

<!-- jQuery (required by DataTables) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>

<!-- DataTables Buttons Plugin JS -->
<script src="https://cdn.datatables.net/buttons/2.2.3/js/dataTables.buttons.min.js"></script>

<!-- Buttons JS (for exporting functionalities) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/vfs_fonts/2.0.1/vfs_fonts.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.print.min.js"></script>

    
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
            <a href="admin-room.php" class="nav-link active" id="roomManagerDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="fas fa-building"></i> <span>Room Manager</span>
            <a href="admin-visitor_log.php" class="nav-link"><i class="fas fa-address-book"></i> <span>Log Visitor</span></a>
            <a href="admin-monitoring.php" class="nav-link"><i class="fas fa-eye"></i> <span>Monitoring</span></a>

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
        <h2>Room List</h2>
    </div>

    <!-- Main content -->
    <div class="main-content">
    <div class="container">
    <div class="d-flex justify-content-start">
    <a href="admin-room.php" class="btn " onclick="window.location.reload();">
    <i class="fas fa-arrow-left fa-2x me-1"></i></a>

</div>      
    <div class="container mt-1">
   <!-- Search and Filter Section -->
<div class="row mb-1">
    <!-- Search Input -->
    <div class="col-12 col-md-6">
        <form method="GET" action="" class="search-form">
            <div class="input-group">
                <input type="text" id="searchInput" name="search" class="form-control custom-input-small" 
                    placeholder="Search for rooms, capacity, etc..." 
                    value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                <span class="input-group-text">
                    <i class="fas fa-search"></i>
                </span>
            </div>
        </form>
    </div>

    <!-- Filter Dropdown -->
    <div class="col-6 col-md-2">
        <select id="filterSelect" name="filter" class="form-select" onchange="this.form.submit()">
            <option value="all" <?php if ($filter === 'all') echo 'selected'; ?>>Filter by</option>
            <option value="room_number" <?php if ($filter === 'room_number') echo 'selected'; ?>>Room</option>
            <option value="capacity" <?php if ($filter === 'capacity') echo 'selected'; ?>>Capacity</option>
            <option value="status" <?php if ($filter === 'status') echo 'selected'; ?>>Monthly Rent</option>
        </select>
    </div>

    <!-- Sort Dropdown -->
    <div class="col-6 col-md-2">
        <select name="sort" class="form-select" id="sort" onchange="applySort()">
            <option value="" selected>Sort by</option>
            <option value="capacity_asc">Capacity (Low to High)</option>
            <option value="capacity_desc">Capacity (High to Low)</option>
            <option value="rent_asc">Monthly Rent (Low to High)</option>
            <option value="rent_desc">Monthly Rent (High to Low)</option>
        </select>
    </div>

    <!-- Add Room Button -->
    <div class="col-6 col-md-2">
        <button type="button" class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#roomModal">
            Add Room
        </button>
    </div>
</div>
<table  class="table table-bordered" id="roomtable">
    <thead class="table-light">
        <tr>
            <th>No</th>
            <th>Room</th>
            <th>Room Description</th>
            <th>Capacity</th>
            <th>Monthly Rent</th>
            <th>Status</th>
            <th>Room Picture</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody id="room-table-body">
    <?php
// Query to get rooms, capacities, and current occupants
$query = "
    SELECT 
        r.room_id,
        r.room_number,
        r.room_desc,
        r.capacity AS totalCapacity,
        r.room_monthlyrent,
        r.status,
        r.room_pic,
        COUNT(ra.assignment_id) AS currentOccupants
    FROM 
        rooms r
    LEFT JOIN 
        roomassignments ra ON r.room_id = ra.room_id
    GROUP BY 
        r.room_id
";

$result = $conn->query($query);

if ($result->num_rows > 0) {
    $counter = 1;
    while ($row = $result->fetch_assoc()) {
        // Check if room status is not maintenance before updating
        if ($row["status"] !== 'maintenance') {
            $currentOccupants = $row["currentOccupants"];
            $totalCapacity = $row["totalCapacity"];
            $status = ($currentOccupants >= $totalCapacity) ? 'occupied' : 'available';

            // Only update status if it's different from the current status
            if ($status !== $row["status"]) {
                $updateStatusQuery = "UPDATE rooms SET status = '$status' WHERE room_id = " . $row["room_id"];
                $conn->query($updateStatusQuery);
            }
        }

        echo "<tr>";
        echo "<td>" . $counter++ . "</td>";
        echo "<td>" . htmlspecialchars($row["room_number"]) . "</td>";
        echo "<td>" . htmlspecialchars($row["room_desc"]) . "</td>";
        echo "<td>" . htmlspecialchars($row["currentOccupants"]) . "/" . htmlspecialchars($row["totalCapacity"]) . " people</td>";
        echo "<td>" . number_format($row["room_monthlyrent"], 2) . "</td>";
        echo "<td>" . htmlspecialchars($row["status"]) . "</td>"; // Display status
        echo "<td>";

        if (!empty($row["room_pic"])) {
            $imagePath = "../uploads/" . htmlspecialchars($row["room_pic"]);
            if (file_exists($imagePath)) {
                echo "<img src='" . $imagePath . "' alt='Room Image' width='100'>";
            } else {
                echo "Image not found";
            }
        } else {
            echo "No Image";
        }

        echo "</td>";
        echo "<td>";
        echo "<a href='?edit_room_id=" . htmlspecialchars($row["room_id"]) . "' class='btn btn-primary btn-sm edit-btn'>Edit</a>";
        echo "<form method='GET' action='roomlist.php' style='display:inline;' onsubmit='return confirmDelete()'>
                <input type='hidden' name='delete_room_id' value='" . htmlspecialchars($row["room_id"]) . "' />
                <button type='submit' class='btn btn-danger btn-m'>Delete</button>
              </form>";
        echo "</td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='8'>No rooms found</td></tr>";
}
?>

    </tbody>
</table>





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
                    <form id="editRoomForm" action="roomlist.php" method="post" enctype="multipart/form-data">
                        <input type="hidden" id="edit_room_id" name="room_id" value="<?php echo htmlspecialchars($editRoom['room_id'] ?? ''); ?>">

                        <!-- Room Number -->
                        <div class="mb-3">
                            <label for="edit_room_number" class="form-label">Room</label>
                            <input type="text" class="form-control" id="edit_room_number" name="room_number" value="<?php echo htmlspecialchars($editRoom['room_number'] ?? ''); ?>" required>
                        </div>

                        <!-- Capacity -->
                        <div class="mb-3">
                            <label for="edit_capacity" class="form-label">Capacity</label>
                            <input type="number" class="form-control" id="edit_capacity" name="capacity" value="<?php echo htmlspecialchars($editRoom['capacity'] ?? ''); ?>" required>
                        </div>

                        <!-- Monthly Rent -->
                        <div class="mb-3">
                            <label for="edit_monthly_rent" class="form-label">Monthly Rent</label>
                            <input type="number" step="0.01" class="form-control" id="edit_monthly_rent" name="room_monthlyrent" value="<?php echo htmlspecialchars($editRoom['room_monthlyrent'] ?? ''); ?>" required>
                        </div>

                        <!-- Description -->
                        <div class="mb-3">
                            <label for="edit_room_desc" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_room_desc" name="room_desc" required><?php echo htmlspecialchars($editRoom['room_desc'] ?? ''); ?></textarea>
                        </div>

                        <!-- Current Room Picture -->
                        <?php if (!empty($editRoom['room_pic'])): ?>
                            <div class="mb-3">
                                <label class="form-label">Current Room Picture</label>
                                <div>
                                    <img src="../uploads/<?php echo htmlspecialchars($editRoom['room_pic']); ?>" alt="Current Room Picture" onerror="this.onerror=null; this.src='path/to/default-image.jpg';" style="max-width: 100%; height: 200px;">
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Upload New Picture -->
                        <div class="mb-3">
                            <label for="room_pic" class="form-label">Upload New Picture (optional)</label>
                            <input type="file" class="form-control" id="room_pic" name="room_picture" accept="image/*">
                        </div>

                        <!-- Status -->
                        <div class="mb-3">
                            <label for="edit_status" class="form-label">Status</label>
                            <select class="form-select" id="edit_status" name="status" required>
                                <option value="available" <?php echo isset($editRoom['status']) && $editRoom['status'] == 'available' ? 'selected' : ''; ?>>Available</option>
                                <option value="occupied" <?php echo isset($editRoom['status']) && $editRoom['status'] == 'occupied' ? 'selected' : ''; ?>>Occupied</option>
                                <option value="maintenance" <?php echo isset($editRoom['status']) && $editRoom['status'] == 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                            </select>
                        </div>

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
                        <label for="room_number" class="form-label">Room</label>
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
                    <button type="submit" class="btn btn-primary">Add Room</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- JavaScript Libraries -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>


<!-- Include jQuery and Bootstrap JS (required for dropdown) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Bootstrap JS and Popper.js -->



    <!-- Bootstrap JS and Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Hamburger Menu Script -->
    <script>
$(document).ready(function () {
    // Initialize DataTable
    var table = $('#roomtable').DataTable({
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'copy',
                exportOptions: {
                    columns: ':not(:last-child):not(:nth-child(7))'
                },
                title: 'Room List - ' + getFormattedDate()
            },
            {
                extend: 'csv',
                exportOptions: {
                    columns: ':not(:last-child):not(:nth-child(7))'
                },
                title: 'Room List - ' + getFormattedDate()
            },
            {
                extend: 'excel',
                exportOptions: {
                    columns: ':not(:last-child):not(:nth-child(7))'
                },
                title: 'Room List - ' + getFormattedDate()
            },
            {
                extend: 'print',
                title: '', // No title
                exportOptions: {
                    columns: ':not(:last-child):not(:nth-child(7))'
                },
                customize: function (win) {
                    var doc = win.document;

                    $(doc.body)
                        .css('font-family', 'Arial, sans-serif')
                        .css('font-size', '12pt')
                        .prepend('<h1 style="text-align:center; font-size: 20pt; font-weight: bold;">Room List Report</h1>')
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

    // Sorting functionality
    $('#sort').on('change', function () {
        const sortValue = $(this).val();
        if (sortValue) {
            const [column, direction] = sortValue.split('_');
            const columnIndex = column === 'capacity' ? 3 : 4;
            table.order([columnIndex, direction]).draw();
        }
    });
});

// Helper function for formatted date
function getFormattedDate() {
    const date = new Date();
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}



function applySort() {
    const sortValue = document.getElementById("sort").value;
    const table = document.getElementById("room-table");
    const rows = Array.from(table.rows).slice(1); // Exclude the header row
    let sortedRows;

    switch (sortValue) {
        case "name_asc":
            sortedRows = rows.sort((a, b) => {
                const nameA = a.cells[1].textContent.trim();
                const nameB = b.cells[1].textContent.trim();
                return nameA.localeCompare(nameB);
            });
            break;
        case "name_desc":
            sortedRows = rows.sort((a, b) => {
                const nameA = a.cells[1].textContent.trim();
                const nameB = b.cells[1].textContent.trim();
                return nameB.localeCompare(nameA);
            });
            break;
        case "capacity_asc":
            sortedRows = rows.sort((a, b) => {
                const capacityA = parseInt(a.cells[3].textContent.trim());
                const capacityB = parseInt(b.cells[3].textContent.trim());
                return capacityA - capacityB;
            });
            break;
        case "capacity_desc":
            sortedRows = rows.sort((a, b) => {
                const capacityA = parseInt(a.cells[3].textContent.trim());
                const capacityB = parseInt(b.cells[3].textContent.trim());
                return capacityB - capacityA;
            });
            break;
        case "rent_asc":
            sortedRows = rows.sort((a, b) => {
                const rentA = parseFloat(a.cells[4].textContent.trim().replace(/[^\d.-]/g, ""));
                const rentB = parseFloat(b.cells[4].textContent.trim().replace(/[^\d.-]/g, ""));
                return rentA - rentB;
            });
            break;
        case "rent_desc":
            sortedRows = rows.sort((a, b) => {
                const rentA = parseFloat(a.cells[4].textContent.trim().replace(/[^\d.-]/g, ""));
                const rentB = parseFloat(b.cells[4].textContent.trim().replace(/[^\d.-]/g, ""));
                return rentB - rentA;
            });
            break;
        default:
            sortedRows = rows; // No sorting applied if no selection is made
    }

    sortedRows.forEach(row => table.appendChild(row)); // Reorder rows in the table
}     function openEditModal(roomId) {
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
        document.getElementById('pageIndicator').textContent = `Page ${page} of ${totalPages}`;
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