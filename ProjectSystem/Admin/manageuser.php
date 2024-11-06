<?php
session_start();

// Redirect to login if not logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: admin-login.php");
    exit;
}

include '../config/config.php'; // Ensure this is correct

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Initialize variable for error message
$errorMessage = '';

// Handle create user request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_user'])) {
    // Collect and sanitize form data
    $fname = trim($_POST['Fname']);
    $lname = trim($_POST['Lname']);
    $mi = trim($_POST['MI']);
    $suffix = trim($_POST['Suffix']);
    $birthdate = trim($_POST['Birthdate']); // Expected format from input: YYYY-MM-DD
    $age = (int) $_POST['Age'];
    $address = trim($_POST['Address']);
    $contact = trim($_POST['contact']);
    $sex = trim($_POST['sex']);
    $role = trim($_POST['Role']);  // General User or Staff
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Handle optional Suffix field
    $suffix = empty($suffix) ? NULL : $suffix;

    // Validate form data
    $errorMessage = '';
    if (empty($fname) || empty($lname) || empty($age) || empty($address) || empty($contact) || empty($sex) || empty($email) || empty($password)) {
        $errorMessage = 'All required fields must be filled.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Invalid email format.';
    } elseif (strlen($password) < 6) {
        $errorMessage = 'Password must be at least 6 characters long.';
    }

    if (!$errorMessage) {
        // Hash password for security
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        // Format the birthdate to ensure it is in 'YYYY-MM-DD' format and not '0000-00-00'
        $birthdateFormatted = date('Y-m-d', strtotime($birthdate));
        if ($birthdateFormatted === '0000-00-00' || !$birthdateFormatted) {
            $errorMessage = 'Birthdate cannot be 0000-00-00 or empty.';
        } else {
            // Select the SQL insert statement based on the role
            $sql = ($role === 'Staff') 
                ? "INSERT INTO staff (Fname, Lname, MI, Suffix, Birthdate, Age, Address, contact, Sex, email, password) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                : "INSERT INTO users (Fname, Lname, MI, Suffix, Birthdate, Age, Address, contact, Sex, email, password) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            // Prepare the statement
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param(
                    "sssssssssss", 
                    $fname, 
                    $lname, 
                    $mi, 
                    $suffix, 
                    $birthdateFormatted, 
                    $age, 
                    $address, 
                    $contact, 
                    $sex, 
                    $email, 
                    $hashedPassword
                );
                
                // Execute the statement and handle potential errors
                if ($stmt->execute()) {
                    echo "<script>alert('User added successfully!');</script>";
                } else {
                    $errorMessage = "Error inserting user: " . htmlspecialchars($stmt->error);
                }
                $stmt->close();
            } else {
                $errorMessage = "Error preparing the statement: " . htmlspecialchars($conn->error);
            }
        }
    }

    // Display any error message
    if (!empty($errorMessage)) {
        echo "<script>alert('$errorMessage');</script>";
    }
}

// Handle edit user request
if (isset($_POST['edit_user'])) {
    $userId = intval($_POST['user_id']);
    $fname = trim($_POST['Fname']);
    $lname = trim($_POST['Lname']);
    $mi = trim($_POST['MI']);
    $suffix = trim($_POST['Suffix']);
    $birthdate = trim($_POST['Birthdate']);
    $age = intval($_POST['Age']);
    $address = trim($_POST['Address']);
    $contact = trim($_POST['contact']);
    $sex = trim($_POST['Sex']);

    // Validate birthdate format (YYYY-MM-DD)
    if (!DateTime::createFromFormat('Y-m-d', $birthdate)) {
        echo "<script>alert('Invalid date format for birthdate. Please use YYYY-MM-DD.');</script>";
        return; // Stop execution if the date is invalid
    }

    // Function to execute update and handle errors
    function executeUpdate($conn, $query, $params, $types) {
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param($types, ...$params); // Spread operator to unpack parameters
            if ($stmt->execute()) {
                return true;
            } else {
                echo "<script>alert('Error executing query: " . htmlspecialchars($stmt->error) . "');</script>";
                return false;
            }
        } else {
            echo "<script>alert('Error preparing statement: " . htmlspecialchars($conn->error) . "');</script>";
            return false;
        }
    }

    // Prepare queries
    $userQuery = "UPDATE users SET Fname = ?, Lname = ?, MI = ?, Suffix = ?, Birthdate = ?, Age = ?, Address = ?, contact = ?, Sex = ? WHERE id = ?";
    $staffQuery = "UPDATE staff SET Fname = ?, Lname = ?, MI = ?, Suffix = ?, Birthdate = ?, Age = ?, Address = ?, contact = ?, Sex = ? WHERE id = ?";

    // Define parameter types
    $userTypes = "sssssssisi"; // 9 strings, 1 integer
    $staffTypes = "sssssssisi"; // 9 strings, 1 integer

    // Execute updates
    $userUpdateSuccess = executeUpdate($conn, $userQuery, [$fname, $lname, $mi, $suffix, $birthdate, $age, $address, $contact, $sex, $userId], $userTypes);
    $staffUpdateSuccess = executeUpdate($conn, $staffQuery, [$fname, $lname, $mi, $suffix, $birthdate, $age, $address, $contact, $sex, $userId], $staffTypes);

    if ($userUpdateSuccess || $staffUpdateSuccess) { // Update is successful if either operation succeeds
        echo "<script>alert('User updated successfully!'); closeEditModal();</script>";
    }
}


// Handle delete (archive) user request
if (isset($_POST['delete_user'])) {
    $userId = intval($_POST['user_id']);
    
    // Archive user from users table
    $archiveUsers = $conn->prepare("INSERT INTO users_archive (id, fname, lname, mi, age, address, contact, sex, role, email, password, created_at, archived_at)
                                    SELECT id, fname, lname, mi, age, address, contact, sex, role, email, password, created_at, NOW()
                                    FROM users WHERE id = ?");
    
    if ($archiveUsers) {
        $archiveUsers->bind_param("i", $userId);
        if ($archiveUsers->execute()) {
            // Prepare delete statement for users after archiving
            $stmtUsers = $conn->prepare("DELETE FROM users WHERE id = ?");
            if ($stmtUsers) {
                $stmtUsers->bind_param("i", $userId);
                if ($stmtUsers->execute()) {
                    echo "<script>alert('User deleted successfully!');</script>";
                } else {
                    echo "<script>alert('Error deleting user: " . $stmtUsers->error . "');</script>";
                }
                $stmtUsers->close();
            }
        } else {
            echo "<script>alert('Error archiving user: " . $archiveUsers->error . "');</script>";
        }
        $archiveUsers->close();
    } else {
        echo "<script>alert('Error preparing statement for user archiving: " . $conn->error . "');</script>";
    }
    
    // Archive staff if applicable
    $archiveStaff = $conn->prepare("INSERT INTO staff_archive (id, fname, lname, mi, age, address, contact, sex, role, email, password, created_at, archived_at)
                                    SELECT id, fname, lname, mi, age, address, contact, sex, role, email, password, created_at, NOW()
                                    FROM staff WHERE id = ?");
    
    if ($archiveStaff) {
        $archiveStaff->bind_param("i", $userId);
        if ($archiveStaff->execute()) {
            // Prepare delete statement for staff after archiving
            $stmtStaff = $conn->prepare("DELETE FROM staff WHERE id = ?");
            if ($stmtStaff) {
                $stmtStaff->bind_param("i", $userId);
                if ($stmtStaff->execute()) {
                    // Optionally alert if staff entry was deleted
                } else {
                    echo "<script>alert('Error deleting staff: " . $stmtStaff->error . "');</script>";
                }
                $stmtStaff->close();
            }
        } else {
            echo "<script>alert('Error archiving staff: " . $archiveStaff->error . "');</script>";
        }
        $archiveStaff->close();
    } else {
        echo "<script>alert('Error preparing statement for staff archiving: " . $conn->error . "');</script>";
    }
}

// Handle filter and search from the query string
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';  // Prevent SQL injection

// Pagination setup
$rowsPerPage = 10; // Show 10 records per page
$currentPage = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1; // Default to page 1
$startRow = ($currentPage - 1) * $rowsPerPage; // Calculate starting row

// Base SQL query to count total rows with filters and search
$totalRowsQuery = "SELECT COUNT(*) AS total FROM (";
if ($filter === 'General User') {
    $totalRowsQuery .= "SELECT id, Fname, Lname, MI, Suffix, Birthdate, Age, Address, contact, Sex, 'General User' AS Role FROM users";
} elseif ($filter === 'Staff') {
    $totalRowsQuery .= "SELECT id, Fname, Lname, MI, Suffix, Birthdate, Age, Address, contact, Sex, 'Staff' AS Role FROM staff";
} else {
    $totalRowsQuery .= "
        SELECT id, Fname, Lname, MI, Suffix, Birthdate, Age, Address, contact, Sex, 'General User' AS Role FROM users
        UNION ALL
        SELECT id, Fname, Lname, MI, Suffix, Birthdate, Age, Address, contact, Sex, 'Staff' AS Role FROM staff";
}

// Add search filter if applicable
$totalRowsQuery .= ") AS combined_data";
if (!empty($search)) {
    $totalRowsQuery .= " WHERE (Fname LIKE '%$search%' OR Lname LIKE '%$search%' OR Role LIKE '%$search%')";
}

// Execute total row count query
$totalRowsResult = $conn->query($totalRowsQuery);
if ($totalRowsResult === false) {
    die("Error: " . $conn->error);
}

$totalRows = $totalRowsResult->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $rowsPerPage);
$sql = "
    SELECT id, Fname, Lname, MI, Suffix, Birthdate, Age, Address, contact, Sex, Role 
    FROM (
";

// Apply filter to select from either `users` or `staff`, or both if no specific filter is set
if ($filter === 'General User') {
    $sql .= "
        SELECT id, Fname, Lname, MI, Suffix, Birthdate, Age, Address, contact, Sex, 'General User' AS Role 
        FROM users
    ";
} elseif ($filter === 'Staff') {
    $sql .= "
        SELECT id, Fname, Lname, MI, Suffix, Birthdate, Age, Address, contact, Sex, 'Staff' AS Role 
        FROM staff
    ";
} else {
    // No specific filter, so combine both tables
    $sql .= "
        SELECT id, Fname, Lname, MI, Suffix, Birthdate, Age, Address, contact, Sex, 'General User' AS Role 
        FROM users
        UNION ALL
        SELECT id, Fname, Lname, MI, Suffix, Birthdate, Age, Address, contact, Sex, 'Staff' AS Role 
        FROM staff
    ";
}

$sql .= ") AS combined_data";

// Apply search filter if a search term is provided
if (!empty($search)) {
    $sql .= " WHERE (Fname LIKE '%$search%' OR Lname LIKE '%$search%' OR Role LIKE '%$search%')";
}

// Apply sorting and pagination
$sql .= " ORDER BY id DESC LIMIT $startRow, $rowsPerPage";

$result = $conn->query($sql);
if ($result === false) {
    die("Error: " . $conn->error);
}

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
    



</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="menu" id="hamburgerMenu">
            <i class="fas fa-bars"></i>
        </div>
        <div class="sidebar-nav">
            <a href="dashboard.php" class="nav-link"><i class="fas fa-user-cog"></i> <span>Profile</span></a>
            <a href="#" class="nav-link active"><i class="fas fa-users"></i> <span>Manage User</span></a>
            <a href="admin-room.php" class="nav-link"><i class="fas fa-building"></i> <span>Room Manager</span></a>
            <a href="admin-visitor_log.php" class="nav-link"><i class="fas fa-address-book"></i> <span>Log Visitor</span></a>

        </div>
        <div class="logout">
            <a href="../config/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
        </div>
    </div>

    <!-- Top bar -->
    <div class="topbar">
        <h2>Manage Users</h2>
    </div>

    
    <!-- Main content -->
    <div class="main-content">   
    <!-- Search Form -->
    <div class="search-container" style="flex: 1; margin-right: auto;"> <!-- Flex for search -->
        <form method="GET" action="" class="search-form" style="position: relative; width: 100%;"> <!-- Search form -->
            <input type="text" id="searchInput" name="search" placeholder="Search for names, roles, etc." 
                value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" 
                style="padding-left: 30px; padding-right: 30px; width: 100%; box-sizing: border-box;">
            <button type="submit" style="display: none;"></button>
            <span style="position: absolute; top: 40%; left: 8px; transform: translateY(-50%); color: #ccc;">
                <i class="fas fa-search"></i>
            </span>
        </form>
    </div>


        <!-- User Table Structure -->
        <div class="table-container">
   <!-- Add User Button -->
   
    <!-- Filter Form -->
    <div class="container" style="display: flex; align-items: center; justify-content: space-between;"> <!-- Main container -->
    <!-- Filter Form -->
    <form method="GET" action="" class="filter-form" style="margin-left: 10px;"> <!-- Filter form -->
        <label for="filter">Filter by Role:</label>
        <select name="filter" id="filter" onchange="this.form.submit()">
            <option value="all" <?php if ($filter === 'all') echo 'selected'; ?>>All</option>
            <option value="General User" <?php if ($filter === 'General User') echo 'selected'; ?>>General User</option>
            <option value="Staff" <?php if ($filter === 'Staff') echo 'selected'; ?>>Staff</option>
        </select>
    </form> 

    <!-- Add User Button -->
    <div class="button" style="margin-left: auto;"> <!-- Flex for button -->
        <button  onclick="showModal()" id="createButton" class="btn" data-bs-toggle="modal" data-bs-target="#userModal">Add User</button>
    </div>
</div>

<table id="userTable" class="table">
    <thead>
        <tr>
            <th>No.</th>
            <th>Name</th>
            <th>Age</th>
            <th>Birthdate</th>
            <th>Address</th>
            <th>Contact No.</th>
            <th>Sex</th>
            <th>Role</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php

if ($result === false) {
    // Output the error if the query fails
    echo "Error: " . htmlspecialchars($conn->error);
} else {
    // Check if there are rows returned
    if ($result->num_rows > 0) {
        $no = 1;
        while ($row = $result->fetch_assoc()) {
            // Process user details
            $userId = $row['id'];
            $fullName = htmlspecialchars($row['Fname']) . ' ';
            
            // Handle middle initial if it exists
            if (!empty($row['MI'])) {
                $fullName .= htmlspecialchars(substr($row['MI'], 0, 1)) . '. ';
            }
            $fullName .= htmlspecialchars($row['Lname']);
            
            // Append the suffix if it exists
            if (!empty($row['Suffix'])) {
                $fullName .= ' ' . htmlspecialchars($row['Suffix']);
            }

            // Safely format the birthdate
            $birthdate = !empty($row['Birthdate']) && $row['Birthdate'] !== '0000-00-00' ? new DateTime($row['Birthdate']) : null;
            $formattedBirthdate = $birthdate ? $birthdate->format('F d, Y') : 'N/A'; // Handle missing or invalid birthdate

            // Use isset to prevent undefined index warnings
            $address = isset($row['Address']) ? htmlspecialchars($row['Address']) : 'N/A';
            $contact = isset($row['contact']) ? htmlspecialchars($row['contact']) : 'N/A';
            $sex = isset($row['Sex']) ? htmlspecialchars($row['Sex']) : 'N/A';
            $role = isset($row['Role']) ? htmlspecialchars($row['Role']) : 'N/A';
            $mi = isset($row['MI']) ? htmlspecialchars($row['MI']) : '';
            $suffix = isset($row['Suffix']) ? htmlspecialchars($row['Suffix']) : '';

            // Display user data in table rows
            echo "<tr>
                <td data-label='No.'>" . $no . "</td>
                <td data-label='Name'>" . $fullName . "</td>
                <td data-label='Age'>" . intval($row['Age']) . "</td>
                <td data-label='Birthdate'>" . htmlspecialchars($formattedBirthdate) . "</td>
                <td data-label='Address'>" . $address . "</td>
                <td data-label='Contact No.'>" . $contact . "</td>
                <td data-label='Sex'>" . $sex . "</td>
                <td data-label='Role'>" . $role . "</td>
                <td>
                    <button class='btn-edit' onclick='openEditModal(
                        $userId,
                        \"" . htmlspecialchars($row['Fname']) . "\",
                        \"" . htmlspecialchars($row['Lname']) . "\",
                        \"$mi\",
                        " . intval($row['Age']) . ",
                        \"$address\",
                        \"$contact\",
                        \"$sex\",
                        \"$role\",
                        \"$suffix\",
                        \"" . htmlspecialchars($row['Birthdate'] ?? '') . "\"
                    )'>Edit</button>
                    <form method='POST' action='' style='display:inline'>
                        <input type='hidden' name='user_id' value='$userId'>
                        <input type='hidden' name='delete_user' value='1'>
                        <button type='submit' class='btn-delete' onclick='return confirm(\"Are you sure you want to delete this user?\");'>Delete</button>
                    </form>
                </td>
            </tr>";

            $no++;
        }
    } else {
        echo "<tr><td colspan='9'>No users found.</td></tr>";
    }
}
?>


    </tbody>
</table>

 
</div>
<div style="text-align: center;">
    <!-- Previous Page Button -->
    <button <?php if ($currentPage <= 1) { echo 'disabled style="background-color: #ddd; cursor: not-allowed;"'; } ?> 
        onclick="window.location.href='?page=<?php echo $currentPage - 1; ?>'">
        Previous
    </button>

    <!-- Page Indicator -->
    <span>Page <?php echo $currentPage; ?> of <?php echo $totalPages; ?></span>

    <!-- Next Page Button -->
    <button <?php if ($currentPage >= $totalPages) { echo 'disabled style="background-color: #ddd; cursor: not-allowed;"'; } ?> 
        onclick="window.location.href='?page=<?php echo $currentPage + 1; ?>'">
        Next
    </button>
</div>
</div>


</div>


        </div>
    

        <!-- Add User Modal -->
        <div id="userModal" class="modal">
    <div class="addmodal-content">
    <span class="close" onclick="hideModal()" style="cursor: pointer; font-size: 24px;">&times;</span>
    <h2 id="add-user">Add New User</h2>
        <form method="POST" action="" onsubmit="return validateForm();">
            <div class="form-grid">
                <div class="form-group">
                    <label for="fname">First Name:</label>
                    <input type="text" id="fname" name="Fname" required>
                </div>
                <div class="form-group">
                    <label for="lname">Last Name:</label>
                    <input type="text" id="lname" name="Lname" required>
                </div>
                <div class="form-group">
                    <label for="mi">Middle Name:</label>
                    <input type="text" id="mi" name="MI">
                </div>
                <div class="form-group">
    <label for="suffix">Suffix (Optional):</label>
    <input type="text" id="suffix" name="Suffix"> <!-- Optional by default -->
</div>

<div class="form-group">
                <label for="birthdate">Birthdate:</label>
                <input type="date" id="birthdate" name="Birthdate" class="form-control" required>
            </div>
                <div class="form-group">
                    <label for="age">Age:</label>
                    <input type="number" id="age" name="Age" required>
                </div>
                <div class="form-group">
                    <label for="sex">Sex:</label>
<select id="sex" name="sex" required>
    <option value="" disabled selected>Select Sex</option>
    <option value="Male">Male</option>
    <option value="Female">Female</option>
</select>

                </div>
                <div class="form-group">
                    <label for="contact">Contact Number:</label>
                    <input type="text" id="contact" name="contact" required>
                </div>
             
                <div class="form-group">
                    <label for="address">Address:</label>
                    <input type="text" id="address" name="Address" required>
                </div>
                <div class="form-group">
                    <label for="role">Role:</label>
                    <select id="role" name="Role" required>
                    <option value="" disabled selected>Select Role</option>

                        <option value="General User">General User</option>
                        <option value="Staff">Staff</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>
            </div>
            <button type="submit" name="create_user">Add User</button>
        </form>
    </div>
</div>

<style>
    /* Modal Background */
.modal {
    display: none; /* Hidden by default */
    position: fixed; /* Stay in place */
    z-index: 1050; /* Sit on top */
    left: 0;
    top: 0;
    width: 100%; /* Full width */
    height: 100%; /* Full height */
    overflow: auto; /* Enable scroll if needed */
    background-color: rgba(0, 0, 0, 0.5); /* Black w/ opacity */
}
</style>
<!-- Edit User Modal -->
<div id="editUserModal" class="modal">
    <div class="addmodal-content">
        <span class="close" onclick="closeEditModal()" style="cursor:pointer;">&times;</span>
        <h2 id="edituser">Edit User</h2>
        <form method="POST" action="" onsubmit="return validateForm();">
            <input type="hidden" id="editUserId" name="user_id">
            <div class="form-grid">
                <div class="form-group">
                    <label for="editFname">First Name:</label>
                    <input type="text" id="editFname" name="Fname" required>
                </div>
                <div class="form-group">
                    <label for="editLname">Last Name:</label>
                    <input type="text" id="editLname" name="Lname" required>
                </div>
                <div class="form-group">
                    <label for="editMI">Middle Name:</label>
                    <input type="text" id="editMI" name="MI" required>
                </div>
                <div class="form-group">
                    <label for="editSuffix">Suffix:</label>
                    <input type="text" id="editSuffix" name="Suffix">
                </div>
                <div class="form-group">
                    <label for="editBirthdate">Birthdate:</label>
                    <input type="date" id="editBirthdate" name="Birthdate" required>
                    </div>
                <div class="form-group">
                    <label for="editAge">Age:</label>
                    <input type="number" id="editAge" name="Age" required>
                </div>
                <div class="form-group">
                    <label for="editAddress">Address:</label>
                    <input type="text" id="editAddress" name="Address" required>
                </div>
                <div class="form-group">
                    <label for="editContact">Contact Number:</label>
                    <input type="text" id="editContact" name="contact" required pattern="[0-9]{10,11}" title="Please enter a valid contact number (10-11 digits)">
                </div>
                <div class="form-group">
                    <label for="editSex">Sex:</label>
                    <select id="editSex" name="Sex">
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
            </div>
            <button type="submit" name="edit_user">Update</button>
        </form>
    </div>
</div>



        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

        <!-- JavaScript for modal functionality -->
        <script>


function hideModal() {
    const modal = document.getElementById("userModal");
    modal.style.display = "none"; // Hide the modal
}

            
   document.addEventListener('DOMContentLoaded', function() {
    const rowsPerPage = 10; // Limit to 10 rows per page
    let currentPage = 1;
    const rows = document.querySelectorAll('#room-table-body tr');
    const totalPages = Math.ceil(rows.length / rowsPerPage);

    // Show the initial set of rows
    showPage(currentPage);
    generatePageLinks();

    function showPage(page) {
        const start = (page - 1) * rowsPerPage;
        const end = start + rowsPerPage;
        
        // Loop through all rows and display or hide them based on the current page
        rows.forEach((row, index) => {
            row.style.display = (index >= start && index < end) ? '' : 'none';
        });

        // Update the page indicator
        document.getElementById('pageIndicator').innerText = `Page ${page}`;

        // Disable/enable buttons based on the current page
        document.getElementById('prevPage').disabled = page === 1;
        document.getElementById('nextPage').disabled = page === totalPages;

        // Update active page link
        document.querySelectorAll('#pageLinks a').forEach(link => {
            link.classList.remove('active');
        });
        document.querySelector(`#pageLinks a[data-page="${page}"]`).classList.add('active');
    }

    function generatePageLinks() {
        const pageLinksContainer = document.getElementById('pageLinks');
        pageLinksContainer.innerHTML = '';

        for (let i = 1; i <= totalPages; i++) {
            const pageLink = document.createElement('a');
            pageLink.href = "#";
            pageLink.textContent = i;
            pageLink.dataset.page = i;
            pageLink.onclick = function(event) {
                event.preventDefault();
                currentPage = parseInt(this.dataset.page);
                showPage(currentPage);
            };

            pageLinksContainer.appendChild(pageLink);
        }
    }

    // Function to go to the next page
    function nextPage() {
        if (currentPage < totalPages) {
            currentPage++;
            showPage(currentPage);
        }
    }

    // Function to go to the previous page
    function prevPage() {
        if (currentPage > 1) {
            currentPage--;
            showPage(currentPage);
        }
    }

    // Attach nextPage and prevPage functions to window (global scope) so they can be accessed by button clicks
    window.nextPage = nextPage;
    window.prevPage = prevPage;
});

       

function checkSearch() {
    var input = document.getElementById('searchInput').value.trim();

    // Automatically submit the form if the input is cleared
    if (input === '') {
        document.getElementById('searchForm').submit();  // Submits the form to reset the search
    }
}

          // Show the Add User Modal
// Show the Add User Modal
function showModal() {
    document.getElementById('userModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';  // Prevent background scroll when modal is open
}

// Hide the Add User Modal
function hideModal() {
    document.getElementById('userModal').style.display = 'none';
    document.body.style.overflow = 'auto';  // Restore background scrolling
}

// Show the Edit User Modal
function openEditModal(id, Fname, Lname, MI, Age, Address, contact, Sex, Role,Suffix,Birthdate) {
    document.getElementById('editUserId').value = id;
    document.getElementById('editFname').value = Fname;
    document.getElementById('editLname').value = Lname;
    document.getElementById('editMI').value = MI;
    document.getElementById('editAge').value = Age;
    document.getElementById('editAddress').value = Address;
    document.getElementById('editContact').value = contact;
    document.getElementById('editSex').value = Sex;

    // Added fields for Suffix and Birthdate
    document.getElementById('editSuffix').value = Suffix;
    document.getElementById('editBirthdate').value = Birthdate;

    document.getElementById('editUserModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';  // Prevent background scroll when modal is open
}

// Hide the Edit User Modal
function closeEditModal() {
    document.getElementById('editUserModal').style.display = 'none';
    document.body.style.overflow = 'auto';  // Restore background scrolling
}


// Close modal when clicking outside the content
window.onclick = function(event) {
    var userModal = document.getElementById('userModal');
    var editUserModal = document.getElementById('editUserModal');
    
    // Check if the click was outside the modal content
    if (event.target === userModal) {
        hideModal();
    }
    if (event.target === editUserModal) {
        closeEditModal();
    }
}

            //hamburgermenu
            const hamburgerMenu = document.getElementById('hamburgerMenu');
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.add('collapsed');

            hamburgerMenu.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                const icon = hamburgerMenu.querySelector('i');
                icon.classList.toggle('fa-bars');
                icon.classList.toggle('fa-times');
            });

            function validateForm() {
                const email = document.getElementById('email') ? document.getElementById('email').value : '';
                const password = document.getElementById('password') ? document.getElementById('password').value : '';
                const contact = document.getElementById('contact') ? document.getElementById('contact').value : '';

                // Validate email format
                if (email && !email.includes("@")) {
                    alert("Please enter a valid email.");
                    return false;
                }

                // Validate password length
                if (password && password.length < 6) {
                    alert("Password must be at least 6 characters.");
                    return false;
                }

                // Validate contact number is exactly 11 digits
                const contactRegex = /^\d{11}$/;
                if (contact && !contactRegex.test(contact)) {
                    alert("Contact number must be exactly 11 digits.");
                    return false;
                }

                return true;
            }
            
        </script>
    </body>
</html>

<?php
$conn->close();
?>
