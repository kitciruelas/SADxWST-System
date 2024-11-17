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
    $role = trim($_POST['Role']); // Role comes from the form

    // Function to execute update and handle errors
    function executeUpdate($conn, $query, $params, $types) {
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param($types, ...$params); // Spread operator to unpack parameters
            if ($stmt->execute()) {
                return true;
            } else {
                echo "<script>alert('Error executing query: " . htmlspecialchars($stmt->error) . "');</script>";
                // Log error for debugging
                error_log("Error executing query: " . $stmt->error);
                return false;
            }
        } else {
            echo "<script>alert('Error preparing statement: " . htmlspecialchars($conn->error) . "');</script>";
            // Log error for debugging
            error_log("Error preparing statement: " . $conn->error);
            return false;
        }
    }

    // Step 1: Get the current role of the user from the database
    $currentRoleQuery = "SELECT Role FROM users WHERE id = ?";
    $stmt = $conn->prepare($currentRoleQuery);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($currentRole);
    $stmt->fetch();
    $stmt->close();

    // Check if the new role is different from the current role
    if ($role !== $currentRole) {
        // Proceed only if the role is different
        if ($role === 'Staff') {
            // If the user is moving to staff from general user
            $copyQuery = "INSERT INTO staff (Fname, Lname, MI, Suffix, Role, email, password, age, address, contact, sex) 
                          SELECT Fname, Lname, MI, Suffix, ?, email, password, age, address, contact, sex 
                          FROM users WHERE id = ?";
            $types = "si"; // Role (string) and user_id (integer)
            echo "Copy Query: " . $copyQuery . "<br>"; // Debug: check query
            $copySuccess = executeUpdate($conn, $copyQuery, [$role, $userId], $types);

            if ($copySuccess) {
                // Step 2: Delete the user from the `users` table after copying data to `staff`
                $deleteQuery = "DELETE FROM users WHERE id = ?";
                $deleteSuccess = executeUpdate($conn, $deleteQuery, [$userId], "i");

                if ($deleteSuccess) {
                    echo "<script>alert('User moved to staff successfully!'); closeEditModal();</script>";
                } else {
                    echo "<script>alert('Error deleting user from users table.');</script>";
                }
            } else {
                echo "<script>alert('Error copying user data to staff table.');</script>";
            }
        } else if ($role === 'General User') {
            // If the user is moving back to general user from staff
            $copyQuery = "INSERT INTO users (Fname, Lname, MI, Suffix, Role, email, password, age, address, contact, sex) 
                          SELECT Fname, Lname, MI, Suffix, ?, email, password, age, address, contact, sex 
                          FROM staff WHERE id = ?";
            $types = "si"; // Role (string) and user_id (integer)
            echo "Copy Query: " . $copyQuery . "<br>"; // Debug: check query
            $copySuccess = executeUpdate($conn, $copyQuery, [$role, $userId], $types);

            if ($copySuccess) {
                // Step 3: Delete the user from the `staff` table after copying data to `users`
                $deleteQuery = "DELETE FROM staff WHERE id = ?";
                $deleteSuccess = executeUpdate($conn, $deleteQuery, [$userId], "i");

                if ($deleteSuccess) {
                    echo "<script>alert('User moved to general users successfully!'); closeEditModal();</script>";
                } else {
                    echo "<script>alert('Error deleting user from staff table.');</script>";
                }
            } else {
                echo "<script>alert('Error copying user data to users table.');</script>";
            }
        }
    } else {
        // If the role has not changed, just update other fields (fname, lname, etc.)
        $updateQuery = "UPDATE " . ($role === 'Staff' ? 'staff' : 'users') . " 
                        SET Fname = ?, Lname = ?, MI = ?, Suffix = ?, Role = ? WHERE id = ?";
        $types = "sssssi"; // 4 strings, 1 integer for role update
        $updateSuccess = executeUpdate($conn, $updateQuery, [$fname, $lname, $mi, $suffix, $role, $userId], $types);

        if ($updateSuccess) {
            echo "<script>alert('User details updated successfully!'); closeEditModal();</script>";
        } else {
            echo "<script>alert('Error updating user details.');</script>";
        }
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
$rowsPerPage = 10;
$currentPage = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$startRow = ($currentPage - 1) * $rowsPerPage;

// Base SQL query to count total rows with filters and search
$totalRowsQuery = "SELECT COUNT(*) AS total FROM (";
if ($filter === 'General User') {
    $totalRowsQuery .= "SELECT id, Fname, Lname, MI, Suffix, Birthdate, Age, Address, contact, Sex, role, created_at FROM users";
} elseif ($filter === 'Staff') {
    $totalRowsQuery .= "SELECT id, Fname, Lname, MI, Suffix, Birthdate, Age, Address, contact, Sex, role, created_at FROM staff";
} else {
    $totalRowsQuery .= "
        SELECT id, Fname, Lname, MI, Suffix, Birthdate, Age, Address, contact, Sex, role, created_at FROM users
        UNION ALL
        SELECT id, Fname, Lname, MI, Suffix, Birthdate, Age, Address, contact, Sex, role, created_at FROM staff
    ";
}

$totalRowsQuery .= ") AS combined_data";

// Add search filter if applicable
if (!empty($search)) {
    $totalRowsQuery .= " WHERE (Fname LIKE '%$search%' OR Lname LIKE '%$search%' OR MI LIKE '%$search%' OR role LIKE '%$search%')";
}

// Execute the query
$totalRowsResult = $conn->query($totalRowsQuery);
if ($totalRowsResult === false) {
    die("Error: " . $conn->error);
}

$totalRows = $totalRowsResult->fetch_assoc()['total'];

$totalPages = ceil($totalRows / $rowsPerPage);

// Main SQL query to fetch data with filters, sorting, and pagination
$sql = "
    SELECT id, Fname, Lname, MI, Suffix, created_at, Sex, Role 
    FROM (
";

// Apply filter to select from either `users` or `staff`, or both if no specific filter is set
if ($filter === 'General User') {
    $sql .= "
        SELECT id, Fname, Lname, MI, Suffix, created_at, Sex, 'General User' AS Role 
        FROM users
    ";
} elseif ($filter === 'Staff') {
    $sql .= "
        SELECT id, Fname, Lname, MI, Suffix, created_at, Sex, 'Staff' AS Role 
        FROM staff
    ";
} else {
    // No specific filter, so combine both tables
    $sql .= "
        SELECT id, Fname, Lname, MI, Suffix, created_at, Sex, 'General User' AS Role 
        FROM users
        UNION ALL
        SELECT id, Fname, Lname, MI, Suffix, created_at, Sex, 'Staff' AS Role 
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


$sort = isset($_GET['sort']) ? $_GET['sort'] : '';

// Set default sorting order (if no selection is made)
$order = 'fname ASC'; // Default: Name (A to Z)

switch ($sort) {
    case 'name_asc':
        $order = 'fname ASC';  // Sort by name A to Z
        break;
    case 'name_desc':
        $order = 'fname DESC'; // Sort by name Z to A
        break;
    default:
        $order = 'fname ASC';  // Default sorting order if no choice is selected
        break;
}

// Now modify your SQL query to use the sorting logic
$sql = "SELECT * FROM users ORDER BY $order";



?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="Css_Admin/manageuser.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>


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
            <a href="admin-monitoring.php" class="nav-link"><i class="fas fa-eye"></i> <span>Presence Monitoring</span></a>

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
    <div class="search-container" style="display: flex; align-items: center; justify-content: space-between; width: 100%;"> <!-- Flex for search and filter -->
    <form method="GET" action="" class="search-form mt-5" style="position: relative; flex-grow: 1;"> <!-- Search form -->
        <input type="text" id="searchInput" name="search" placeholder="Search for names, roles, etc." 
            value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" 
            style="padding-left: 30px; padding-right: 30px; width: 100%; box-sizing: border-box;">
        <button type="submit" style="display: none;"></button>
        <span style="position: absolute; top: 40%; left: 8px; transform: translateY(-50%); color: #ccc;">
            <i class="fas fa-search"></i>
        </span>
    </form>

    <!-- Filter Form -->
    <form method="GET" action="" class="filter-form mt-5" style="margin-left: 10px;"> <!-- Filter form -->
        <label for="filter">Filter by Role:</label>
        <select name="filter" id="filter" onchange="this.form.submit()">
            <option value="all" <?php if ($filter === 'all') echo 'selected'; ?>>All</option>
            <option value="General User" <?php if ($filter === 'General User') echo 'selected'; ?>>General User</option>
            <option value="Staff" <?php if ($filter === 'Staff') echo 'selected'; ?>>Staff</option>
        </select>
    </form>
<!-- Sort Form -->
<!-- Sort Form -->
<form id="sortForm" class="filter-form mt-5" style="margin-left: 10px;">
    <label for="sort">Sort by:</label>
    <select name="sort" id="sort">
        <option value="" selected>Select Sort</option>
        <option value="name_asc">Name (A to Z)</option>
        <option value="name_desc">Name (Z to A)</option>
    </select>
</form>


    <!-- Add User Button -->
    <div class="button" style="margin-left: 10px;">
        <button id="createButton" class="btn btn-primary" onclick="showModal()">Add User</button>
    </div>

</div>

<table id="userTable" class="table display nowrap">

    <thead>
        
        <tr>
            
            <th>No.</th>
            <th>Name</th>
            <th>Role</th>
            <th>Account Created</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php
    if ($result === false) {
        echo "Error: " . htmlspecialchars($conn->error);
    } else {
        if ($result->num_rows > 0) {
            $no = 1;
            while ($row = $result->fetch_assoc()) {
                $userId = $row['id'];
                $fullName = htmlspecialchars($row['Fname']) . ' ';
                if (!empty($row['MI'])) {
                    $fullName .= htmlspecialchars(substr($row['MI'], 0, 1)) . '. ';
                }
                $fullName .= htmlspecialchars($row['Lname']);
                if (!empty($row['Suffix'])) {
                    $fullName .= ' ' . htmlspecialchars($row['Suffix']);
                }

                $role = isset($row['Role']) ? htmlspecialchars($row['Role']) : 'N/A';
                $accountCreated = !empty($row['created_at']) ? new DateTime($row['created_at']) : null;
                $formattedAccountCreated = $accountCreated ? $accountCreated->format('F d, Y') : 'N/A';

                echo "<tr>
                    <td data-label='No.'>" . $no . "</td>
                    <td data-label='Name'>" . $fullName . "</td>
                    <td data-label='Role'>" . $role . "</td>
                    <td data-label='Account Created'>" . htmlspecialchars($formattedAccountCreated) . "</td>
                    <td>
                    

                        <!-- Edit Button -->
                        <button class='btn-edit' onclick='editUserModal(
                            $userId,
                            \"" . htmlspecialchars($row['Fname']) . "\",
                            \"" . htmlspecialchars($row['Lname']) . "\",
                            \"" . htmlspecialchars($row['MI'] ?? '') . "\",
                            \"" . htmlspecialchars($row['Suffix'] ?? '') . "\",
                            \"" . htmlspecialchars($role) . "\"
                        )'>Edit</button>

                        <!-- Delete Button -->
                        <form method='POST' action='' style='display:inline'>
                            <input type='hidden' name='user_id' value='$userId'>
                            <input type='hidden' name='delete_user' value='1'>
                            <button type='submit' class='btn-delete' onclick='return confirm(\"Are you sure you want to Deactivate this user?\");'>Deactivate</button>
                        </form>
                    </td>
                </tr>";
                $no++;
            }
        } else {
            echo "<tr><td colspan='5'>No users found.</td></tr>";
        }
    }
    ?>
    </tbody>
</table>


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
<!-- Modal -->
<div id="userModal" class="modal" style="display: none;">
    <div class="addmodal-content">
        <span class="close" onclick="hideModal()" style="cursor: pointer; font-size: 24px;">&times;</span>
        <h2 id="add-user">Add New User</h2>
        <form method="POST" action="" onsubmit="return validateForm();">
            
            <!-- Personal Info Page -->
            <div id="personalInfoPage" class="form-page">
                <p class="section-description">Please provide users personal information.</p>
                <div class="form-grid">
                    <!-- Row 1 -->
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
                        <input type="text" id="suffix" name="Suffix">
                    </div>
                </div>
                <div class="form-grid">
                    <!-- Row 2 -->
                    <div class="form-group">
                        <label for="birthdate">Birthdate:</label>
                        <input type="date" id="birthdate" name="Birthdate" required onchange="calculateAge()">
                    </div>
                    <div class="form-group">
                        <label for="age">Age:</label>
                        <input type="number" id="age" name="Age" required readonly>
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
    <input type="text" id="contact" name="contact" required pattern="^09\d{9}$" title="Contact number must start with '09' and be exactly 11 digits long." onchange="validateContact()">
</div>

                </div>
                <div class="form-group">
                    <label for="address">Address:</label>
                    <input type="text" id="address" name="Address" required>
                </div>
            </div>

            <!-- Account Info Page -->
            <div id="accountInfoPage" class="form-page" style="display: none;">
                <p class="section-description">Now, please provide users account details.</p>
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
    <label for="password">Password</label>
    <div class="password-container">
        <input type="password" name="password" id="password" required placeholder=" " />
        <i class="eye-icon fas fa-eye-slash" title="Show Password" onclick="togglePasswordVisibility('password', this)"></i>
    </div>
</div>

            </div>

            <!-- Buttons -->
            <button type="button" onclick="nextPage()" id="nextButton">Next</button>
            <button type="button" onclick="previousPage()" id="previousButton" style="display: none;">Previous</button>
            <button type="submit" name="create_user" style="display: none;" id="submitButton">Add User</button>

        </form>
    </div>
</div>



<style>
    /* Password Container to position icon correctly */
    .password-container {
    position: relative;
    display: inline-block;
    width: 100%;
}


.eye-icon {
    position: absolute;
    right: 5px;
    top: 40%;
    transform: translateY(-50%);
    cursor: pointer;
    font-size: 18px; /* Adjust the size of the icon */
}


/* Input with padding for the icon */
.password-container input {
    padding-right: 30px; /* Add padding on the right to avoid text overlap with the icon */
}

/* Optional: Add additional padding to the form-group for better spacing */
.form-group {
    margin-bottom: 15px;
}

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
/* Modal Content */
.addmodal-content {
    max-height: 80vh; /* Adjust the height as needed */
    overflow-y: auto; /* Enables vertical scrolling */
    padding: 20px;
    background-color: white;
    border-radius: 10px;
    box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
}

/* Modal Background */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    overflow: auto;
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
                    <label for="editRole">Role:</label>
                    <select id="editRole" name="Role" required>
                        <option value="General User">General User</option>
                        <option value="Staff">Staff</option>
                    </select>
                </div>
            </div>
            <button type="submit" name="edit_user">Update</button>
        </form>
    </div>
</div>



<!-- jsPDF -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

        <!-- JavaScript for modal functionality -->
        <script>
 $(document).ready(function () {
    $('#userTable').DataTable({
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'copy',
                className: 'btn btn-primary',
                exportOptions: {
                    columns: ':not(:last-child)'  // Exclude the last column (Actions)
                }
            },
            {
                extend: 'csv',
                className: 'btn btn-success',
                exportOptions: {
                    columns: ':not(:last-child)'  // Exclude the last column (Actions)
                }
            },
            {
                extend: 'excel',
                className: 'btn btn-info',
                exportOptions: {
                    columns: ':not(:last-child)'  // Exclude the last column (Actions)
                }
            },
            {
                extend: 'pdf',
                className: 'btn btn-danger',
                customize: function (doc) {
                    // Modify the header for PDF output
                    doc.content[1].table.widths = ['10%', '30%', '30%', '30%']; // Adjust column widths
                    doc.content[1].table.body[0] = [
                        { text: 'No.', bold: true },
                        { text: 'Name', bold: true },
                        { text: 'Role', bold: true },
                        { text: 'Account Created', bold: true }
                    ]; // Modify the table header in PDF
                },
                exportOptions: {
                    columns: ':not(:last-child)'  // Exclude the last column (Actions)
                }
            },
            {
                extend: 'print',
                className: 'btn btn-warning',
                customize: function (win) {
                    // Modify the print layout for better styling
                    $(win.document.body).find('th').css({
                        'background-color': '#4CAF50',  // Header background color
                        'color': 'white',  // Header text color
                        'font-weight': 'bold',  // Bold the header text
                        'text-align': 'center'  // Center align the header text
                    });

                    // Optionally, you can also style the body of the table or add other customizations
                    $(win.document.body).find('td').css({
                        'font-size': '14px',  // Example: Change font size for table data
                    });
                },
                exportOptions: {
                    columns: ':not(:last-child)'  // Exclude the last column (Actions)
                }
            }
        ],
        responsive: false,
        searching: false,
        paging: false,
        info: false,
        autoWidth: false
    });
});


        // Store the original rows when the page loads
        var originalRows = Array.from(document.querySelectorAll('#userTable tbody tr'));

document.getElementById('sort').addEventListener('change', function() {
    var sortValue = this.value;
    sortTable(sortValue);
});

function sortTable(sortValue) {
    var rows;
    
    // If no sort is selected, return to the original order
    if (sortValue === '') {
        rows = originalRows;  // Use the original order
    } else if (sortValue === 'name_asc') {
        rows = originalRows.sort(function(a, b) {
            var nameA = a.querySelector('td[data-label="Name"]').textContent.trim();
            var nameB = b.querySelector('td[data-label="Name"]').textContent.trim();
            return nameA.localeCompare(nameB);
        });
    } else if (sortValue === 'name_desc') {
        rows = originalRows.sort(function(a, b) {
            var nameA = a.querySelector('td[data-label="Name"]').textContent.trim();
            var nameB = b.querySelector('td[data-label="Name"]').textContent.trim();
            return nameB.localeCompare(nameA);
        });
    }

    // Reorder the rows in the table
    var tbody = document.querySelector('#userTable tbody');
    tbody.innerHTML = '';  // Clear the table body
    rows.forEach(function(row) {
        tbody.appendChild(row);  // Append rows in the desired order
    });
}
// Function to validate the contact number
function validateContact() {
    var contact = document.getElementById("contact").value;
    var contactRegex = /^09\d{9}$/; // Starts with '09' and is exactly 11 digits long

    // If the contact number doesn't match the pattern, show an alert
    if (!contactRegex.test(contact)) {
        alert("Please enter a valid contact number starting with '09' and exactly 11 digits long.");
        // Optionally, reset the input field or focus on it
        document.getElementById("contact").value = "";
        document.getElementById("contact").focus();
    }
}


// Modify the nextPage function to include contact validation
function nextPage() {
    var currentPage = document.querySelector(".form-page:not([style*='display: none'])");
    var inputs = currentPage.querySelectorAll("input[required], select[required]");
    var valid = true;

    // Check if any required input is empty or invalid
    inputs.forEach(function(input) {
        if (input.type === "text" && input.id === "contact") {
            if (!validateContactNumber(input.value)) {
                valid = false;
                input.style.borderColor = "red"; // Highlight invalid contact number
                alert("Please enter a valid contact number starting with '09' and followed by 8-9 digits.");
            } else {
                input.style.borderColor = ""; // Reset border color if valid
            }
        } else if (input.value.trim() === "") {
            valid = false;
            input.style.borderColor = "red"; // Highlight empty required fields
        } else {
            input.style.borderColor = ""; // Reset border color if filled
        }
    });

    if (valid) {
        // Hide current page and show next page
        currentPage.style.display = "none";
        var nextPage = currentPage.nextElementSibling;
        if (nextPage) {
            nextPage.style.display = "block";
        }

        // Show Previous button on the second page
        if (nextPage && nextPage.id === "accountInfoPage") {
            document.getElementById("previousButton").style.display = "inline-block";
            document.getElementById("nextButton").style.display = "none"; // Hide Next button if it's the last page
            document.getElementById("submitButton").style.display = "inline-block";
        }
    }
}


// Function to toggle password visibility
function togglePasswordVisibility(fieldId, icon) {
    var field = document.getElementById(fieldId);
    if (field.type === "password") {
        field.type = "text"; // Show the password
        icon.classList.remove("fa-eye-slash"); // Remove closed eye icon
        icon.classList.add("fa-eye"); // Add open eye icon
        icon.setAttribute("title", "Hide Password"); // Update tooltip text
    } else {
        field.type = "password"; // Hide the password
        icon.classList.remove("fa-eye"); // Remove open eye icon
        icon.classList.add("fa-eye-slash"); // Add closed eye icon
        icon.setAttribute("title", "Show Password"); // Update tooltip text
    }
}

// Function to validate the form on the "Next" button
function nextPage() {
    var currentPage = document.querySelector(".form-page:not([style*='display: none'])");
    var inputs = currentPage.querySelectorAll("input[required], select[required]");
    var valid = true;

    // Check if any required input is empty
    inputs.forEach(function(input) {
        if (input.value.trim() === "") {
            valid = false;
            input.style.borderColor = "red"; // Optional: add red border to highlight the empty input
        } else {
            input.style.borderColor = ""; // Reset border color if filled
        }
    });

    if (valid) {
        // Hide current page and show next page
        currentPage.style.display = "none";
        var nextPage = currentPage.nextElementSibling;
        if (nextPage) {
            nextPage.style.display = "block";
        }

        // Show Previous button on the second page
        if (nextPage && nextPage.id === "accountInfoPage") {
            document.getElementById("previousButton").style.display = "inline-block";
            document.getElementById("nextButton").style.display = "none"; // Hide Next button if it's the last page
            document.getElementById("submitButton").style.display = "inline-block";
        }
    }
}

// Function to go to the previous page
function previousPage() {
    var currentPage = document.querySelector(".form-page:not([style*='display: none'])");
    var previousPage = currentPage.previousElementSibling;
    if (previousPage) {
        currentPage.style.display = "none";
        previousPage.style.display = "block";
    }

    // Show the "Next" button again when going back to the first page
    document.getElementById("nextButton").style.display = "inline-block";
    document.getElementById("previousButton").style.display = "none";
    document.getElementById("submitButton").style.display = "none";
}
    

function togglePasswordVisibility(fieldId, icon) {
    var field = document.getElementById(fieldId);
    if (field.type === "password") {
        field.type = "text"; // Show the password
        icon.classList.remove("fa-eye-slash"); // Remove closed eye icon
        icon.classList.add("fa-eye"); // Add open eye icon
        icon.setAttribute("title", "Hide Password"); // Update tooltip text
    } else {
        field.type = "password"; // Hide the password
        icon.classList.remove("fa-eye"); // Remove open eye icon
        icon.classList.add("fa-eye-slash"); // Add closed eye icon
        icon.setAttribute("title", "Show Password"); // Update tooltip text
    }
}

// Function to calculate age based on birthdate
function calculateAge() {
    var birthdate = document.getElementById("birthdate").value;
    var birthDateObj = new Date(birthdate);
    var age = new Date().getFullYear() - birthDateObj.getFullYear();
    var m = new Date().getMonth() - birthDateObj.getMonth();
    
    if (m < 0 || (m === 0 && new Date().getDate() < birthDateObj.getDate())) {
        age--;
    }
    
    document.getElementById("age").value = age;
}

// Function to hide the modal
function hideModal() {
    document.getElementById("userModal").style.display = "none";
}

// Function to show the modal
function showModal() {
    document.getElementById("userModal").style.display = "block";
}

// Function to calculate age based on birthdate
function calculateAge() {
    var birthdate = document.getElementById('birthdate').value;
    var ageField = document.getElementById('age');
    
    if (birthdate) {
        var birthDateObj = new Date(birthdate);
        var today = new Date();
        var age = today.getFullYear() - birthDateObj.getFullYear();
        var m = today.getMonth() - birthDateObj.getMonth();

        // If the birthday hasn't occurred yet this year, subtract 1 from age
        if (m < 0 || (m === 0 && today.getDate() < birthDateObj.getDate())) {
            age--;
        }

        // Update the age field with the calculated age
        ageField.value = age;
    }
}


function editUserModal(userId, fname, lname, mi, suffix, role) {
    // Populate the modal fields with the passed user data
    document.getElementById('editUserId').value = userId;
    document.getElementById('editFname').value = fname;
    document.getElementById('editLname').value = lname;
    document.getElementById('editMI').value = mi;
    document.getElementById('editSuffix').value = suffix;

    // Populate the role dropdown
    document.getElementById('editRole').value = role;

    // Open the modal
    document.getElementById('editUserModal').style.display = 'block';
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
