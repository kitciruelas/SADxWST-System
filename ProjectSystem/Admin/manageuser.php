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

require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';
// Handle create user request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_user'])) {
    // Collect and sanitize form data
    $fname = trim(mysqli_real_escape_string($conn, $_POST['Fname']));
    $lname = trim(mysqli_real_escape_string($conn, $_POST['Lname']));
    $mi = trim(mysqli_real_escape_string($conn, $_POST['MI']));
    $suffix = trim(mysqli_real_escape_string($conn, $_POST['Suffix']));
    $birthdate = trim($_POST['Birthdate']);
    $address = trim(mysqli_real_escape_string($conn, $_POST['Address']));
    $contact = trim(mysqli_real_escape_string($conn, $_POST['contact']));
    $sex = trim(mysqli_real_escape_string($conn, $_POST['sex']));
    $role = trim(mysqli_real_escape_string($conn, $_POST['Role']));
    $email = trim(mysqli_real_escape_string($conn, $_POST['email']));
    $password = $_POST['password'];

    // Calculate age from birthdate
    $birthdateObj = new DateTime($birthdate);
    $today = new DateTime();
    $age = $birthdateObj->diff($today)->y;

    // Validate form data
    $errorMessage = '';
    
    // Required fields validation
    if (empty($fname)) {
        $errorMessage = 'First name is required.';
    } elseif (empty($lname)) {
        $errorMessage = 'Last name is required.';
    } elseif (empty($birthdate)) {
        $errorMessage = 'Birthdate is required.';
    } elseif (empty($address)) {
        $errorMessage = 'Address is required.';
    } elseif (strlen($address) < 10) {
        $errorMessage = 'Please enter a complete address including House/Unit No., Street, Barangay, City/Municipality, and Province.';
    } elseif (empty($contact)) {
        $errorMessage = 'Contact number is required.';
    } elseif (empty($sex)) {
        $errorMessage = 'Sex is required.';
    } elseif (empty($email)) {
        $errorMessage = 'Email is required.';
    } elseif (empty($password)) {
        $errorMessage = 'Password is required.';
    }

    // Email format validation
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Invalid email format.';
    }

    // Password length validation
    if (!empty($password) && strlen($password) < 6) {
        $errorMessage = 'Password must be at least 6 characters long.';
    }

    // Contact number validation (must start with 09 and be 11 digits)
    if (!empty($contact) && !preg_match('/^09\d{9}$/', $contact)) {
        $errorMessage = 'Contact number must start with 09 and be 11 digits long.';
    }

    // Birthdate validation
    if (!empty($birthdate)) {
        $birthdateObj = DateTime::createFromFormat('Y-m-d', $birthdate);
        $today = new DateTime();
        
        if (!$birthdateObj || $birthdateObj->format('Y-m-d') !== $birthdate) {
            $errorMessage = 'Invalid birthdate format. Please use YYYY-MM-DD.';
        } else {
            // Calculate age
            $age = $today->diff($birthdateObj)->y;
            if ($age < 16) {
                $errorMessage = 'User must be at least 16 years old.';
            }
        }
    }

    // Check for duplicate email
    if (!$errorMessage) {
        $checkEmailSql = "SELECT email FROM users WHERE email = ? UNION SELECT email FROM staff WHERE email = ?";
        $stmt = $conn->prepare($checkEmailSql);
        $stmt->bind_param("ss", $email, $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $errorMessage = 'This email address is already registered.';
        }
        $stmt->close();
    }

    // If no errors, proceed with insertion
    if (!$errorMessage) {
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $table = ($role === 'Staff') ? 'staff' : 'users';
        
        $sql = "INSERT INTO $table (Fname, Lname, MI, Suffix, Birthdate, Age, Address, contact, Sex, email, password) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("sssssssssss", 
                $fname, 
                $lname, 
                $mi, 
                $suffix, 
                $birthdate,
                $age,
                $address, 
                $contact, 
                $sex, 
                $email, 
                $hashedPassword
            );
            
            if ($stmt->execute()) {
                // Send registration email
                sendRegistrationEmail($email, $fname, $lname, $password, $role);
                echo "<script>
                    alert('User added successfully! A confirmation email has been sent.');
                    window.location.href = 'manageuser.php';
                </script>";
            } else {
                $errorMessage = "Error creating user: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $errorMessage = "Error preparing statement: " . $conn->error;
        }
    }

    // Display error message if any
    if ($errorMessage) {
        echo "<script>alert('" . addslashes($errorMessage) . "');</script>";
    }
}

function sendRegistrationEmail($userEmail, $firstName, $lastName, $password, $role) {
    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com'; // Use Gmail SMTP server
        $mail->SMTPAuth = true;
        $mail->Username = 'dormioph@gmail.com'; // Your Gmail email
        $mail->Password = 'ymrd smvk acxa whdy'; // Your generated Google App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; // Use STARTTLS encryption
        $mail->Port = 587; // Port for STARTTLS
    
        // Disable SSL certificate verification (for testing purposes)
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
    
        // Recipients
        $mail->setFrom('dormioph@gmail.com', 'Dormio Ph');
        $mail->addAddress($userEmail, "$firstName $lastName");  // Recipient's email
    
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Registration Successful';
        
        if ($role === 'Staff') {
            // Custom content for staff registration
            $mail->Body = "Dear $firstName $lastName,<br><br>
                           We are excited to welcome you as a member of our staff! Your registration has been successfully completed.<br><br>
                           <strong>Important Reminders for Your Role:</strong><br>
                           <ul>
                               <li><strong>Communication is Key:</strong> Please maintain regular communication with the administration for any updates or instructions regarding your duties.</li>
                               <li><strong>Work Schedule:</strong> Be sure to review and follow your assigned work schedule and responsibilities. Make sure you're aware of any upcoming shifts or changes.</li>
                               <li><strong>Reporting Procedures:</strong> Follow the appropriate reporting procedures when on duty, and for any incidents or important updates related to your role.</li>
                           </ul>
                           <br>Below are your login details for accessing the system:<br><br>
                           <strong>Email:</strong> $userEmail<br>
                           <strong>Password:</strong> $password<br><br>
                           For your security, we highly recommend that you change your password upon your first login.<br><br>
                           Should you have any questions or require assistance, please don't hesitate to reach out to the administration team.<br><br>
                           We are happy to have you on board!<br><br>
                           Best regards,<br>Maricel Perce<br>Admin";
        } else {
            // Content for general users (non-staff)
            $mail->Body = "Dear $firstName $lastName,<br><br>
                           We are pleased to inform you that your registration has been completed! Welcome to our community!<br><br>
                           <strong>Important Reminder About Your Stay:</strong><br>
                           <ul>
                               <li><strong>Communication is Essential:</strong> To ensure a smooth transition, please notify the appropriate personnel about your plans to vacate the premises.</li>
                               <li><strong>Check-Out Procedures:</strong> Familiarize yourself with any specific check-out procedures that may apply.</li>
                               <li><strong>Final Room Inspection:</strong> Be prepared for a final inspection of your room before departure.</li>
                           </ul>
                           <br>Your registration was successful. Below are your login details:<br><br>
                           <strong>Email:</strong> $userEmail<br>
                           <strong>Password:</strong> $password<br><br>
                           For your security, we recommend that you change your password after logging in.<br><br>
                           If you have any questions or need assistance, please get in touch with the dormitory staff.<br><br>
                           Thank you for being a part of our community!<br><br>
                           Best regards,<br>Maricel Perce<br>Dorm Staff";
        }
    
        // Send email
        if ($mail->send()) {
            echo 'Registration email has been sent.';
        } else {
            echo 'Failed to send registration email.';
        }
    } catch (Exception $e) {
        // Log detailed error for debugging
        error_log("Mailer Error: {$mail->ErrorInfo}");
        echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
    }
}


// Handle edit user request
if (isset($_POST['edit_user'])) {
    $userId = intval($_POST['user_id']);
    $fname = trim($_POST['Fname']);
    $lname = trim($_POST['Lname']);
    $mi = trim($_POST['MI']);
    $suffix = trim($_POST['Suffix']);
    $newRole = trim($_POST['Role']);

    try {
        // Determine current table based on role
        $currentTable = ($newRole === 'Staff') ? 'staff' : 'users';
        
        // Begin transaction
        $conn->begin_transaction();

        // Get user data from current table
        $getData = "SELECT * FROM $currentTable WHERE id = ?";
        $stmt = $conn->prepare($getData);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $userData = $result->fetch_assoc();

        if (!$userData) {
            // User not found in current table, check the other table
            $otherTable = ($currentTable === 'staff') ? 'users' : 'staff';
            $getData = "SELECT * FROM $otherTable WHERE id = ?";
            $stmt = $conn->prepare($getData);
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $userData = $result->fetch_assoc();

            if ($userData) {
                // User found in other table, need to move them
                $insertQuery = "INSERT INTO $currentTable 
                              (Fname, Lname, MI, Suffix, Birthdate, Age, Address, 
                               contact, Sex, email, password, status) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($insertQuery);
                $stmt->bind_param("sssssissssss", 
                    $fname, 
                    $lname, 
                    $mi, 
                    $suffix, 
                    $userData['Birthdate'],
                    $userData['age'],
                    $userData['Address'],
                    $userData['contact'],
                    $userData['sex'],
                    $userData['email'],
                    $userData['password'],
                    $userData['status']
                );

                if (!$stmt->execute()) {
                    throw new Exception("Error inserting into new table: " . $stmt->error);
                }

                // Delete from old table
                $deleteQuery = "DELETE FROM $otherTable WHERE id = ?";
                $stmt = $conn->prepare($deleteQuery);
                $stmt->bind_param("i", $userId);
                
                if (!$stmt->execute()) {
                    throw new Exception("Error deleting from old table: " . $stmt->error);
                }

                $conn->commit();
                echo "<script>
                    alert('User role successfully changed to $newRole');
                    window.location.reload();
                </script>";
            }
        } else {
            // User found in current table, just update their information
            $updateQuery = "UPDATE $currentTable 
                           SET Fname = ?, Lname = ?, MI = ?, Suffix = ? 
                           WHERE id = ?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param("ssssi", $fname, $lname, $mi, $suffix, $userId);
            
            if (!$stmt->execute()) {
                throw new Exception("Error updating user information: " . $stmt->error);
            }
            
            $conn->commit();
            echo "<script>
                alert('User information updated successfully');
                window.location.reload();
            </script>";
        }
    } catch (Exception $e) {
        if ($conn->connect_errno != 0) {
            $conn->rollback();
        }
        echo "<script>
            alert('Error: " . addslashes($e->getMessage()) . "');
            window.location.reload();
        </script>";
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
            // Archive staff if applicable
            $archiveStaff = $conn->prepare("INSERT INTO staff_archive (id, fname, lname, mi, age, address, contact, sex, role, email, password, created_at, archived_at)
                                            SELECT id, fname, lname, mi, age, address, contact, sex, role, email, password, created_at, NOW()
                                            FROM staff WHERE id = ?");
            
            if ($archiveStaff) {
                $archiveStaff->bind_param("i", $userId);
                if ($archiveStaff->execute()) {
                    // Proceed to delete the user and staff after archiving
                    $deleteUserQuery = "DELETE FROM users WHERE id = ?";
                    $deleteStaffQuery = "DELETE FROM staff WHERE id = ?";
                    
                    // Prepare and execute the delete query for the user
                    $stmtDeleteUser = $conn->prepare($deleteUserQuery);
                    if ($stmtDeleteUser) {
                        $stmtDeleteUser->bind_param("i", $userId);
                        if ($stmtDeleteUser->execute()) {
                            echo "<script>alert('User and staff archived and deleted successfully!');</script>";
                        } else {
                            echo "<script>alert('Error deleting user: " . $stmtDeleteUser->error . "');</script>";
                        }
                        $stmtDeleteUser->close();
                    }

                    // Prepare and execute the delete query for the staff
                    $stmtDeleteStaff = $conn->prepare($deleteStaffQuery);
                    if ($stmtDeleteStaff) {
                        $stmtDeleteStaff->bind_param("i", $userId);
                        if ($stmtDeleteStaff->execute()) {
                            // Optionally alert if staff entry was deleted
                        } else {
                            echo "<script>alert('Error deleting staff: " . $stmtDeleteStaff->error . "');</script>";
                        }
                        $stmtDeleteStaff->close();
                    }
                } else {
                    echo "<script>alert('Error archiving staff: " . $archiveStaff->error . "');</script>";
                }
                $archiveStaff->close();
            } else {
                echo "<script>alert('Error preparing statement for staff archiving: " . $conn->error . "');</script>";
            }
        } else {
            echo "<script>alert('Error archiving user: " . $archiveUsers->error . "');</script>";
        }
        $archiveUsers->close();
    } else {
        echo "<script>alert('Error preparing statement for user archiving: " . $conn->error . "');</script>";
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
    SELECT id, Fname, Lname, MI, Suffix, created_at, Sex, Role, status 
    FROM (
";

// Apply filter to select from either `users` or `staff`, or both if no specific filter is set
if ($filter === 'General User') {
    $sql .= "
        SELECT id, Fname, Lname, MI, Suffix, created_at, Sex, 'General User' AS Role, status 
        FROM users
    ";
} elseif ($filter === 'Staff') {
    $sql .= "
        SELECT id, Fname, Lname, MI, Suffix, created_at, Sex, 'Staff' AS Role, status 
        FROM staff
    ";
} else {
    // No specific filter, so combine both tables
    $sql .= "
        SELECT id, Fname, Lname, MI, Suffix, created_at, Sex, 'General User' AS Role, status 
        FROM users
        UNION ALL
        SELECT id, Fname, Lname, MI, Suffix, created_at, Sex, 'Staff' AS Role, status 
        FROM staff
    ";
}

$sql .= ") AS combined_data";

// Apply search filter if a search term is provided
if (!empty($search)) {
    $sql .= " WHERE (Fname LIKE '%$search%' OR Lname LIKE '%$search%' OR Role LIKE '%$search%')";
}

// Apply sorting and pagination
$sql .= " ORDER BY created_at DESC LIMIT $startRow, $rowsPerPage
";

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle-status'])) {
    // Get user ID
    $userId = intval($_POST['user_id']);  // Ensure this is an integer for security

    // Fetch current status from the database
    $sql = "SELECT status FROM users WHERE id = $userId";
    $result = mysqli_query($conn, $sql);

    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $currentStatus = $row['status'];

        // Toggle the status: active -> inactive, inactive -> active
        $newStatus = ($currentStatus === 'active') ? 'inactive' : 'active';

        // Update the user's status in the database
        $updateSql = "UPDATE users SET status = '$newStatus' WHERE id = $userId";
        if (mysqli_query($conn, $updateSql)) {
            echo "<script>alert('User status updated successfully.');</script>";
        } else {
            echo "<script>alert('Error updating status: " . mysqli_error($conn) . "');</script>";
        }
    } else {
        echo "<script>alert('No user found with ID: $userId');</script>";
    }
}

// Add this after your existing database connection code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $userId = intval($_POST['user_id']);
    $role = $_POST['role'];
    
    // Determine which table to update based on role
    $table = ($role === 'Staff') ? 'staff' : 'users';
    
    try {
        // Get current status
        $query = "SELECT status FROM $table WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            // Toggle the status
            $newStatus = ($row['status'] === 'active') ? 'inactive' : 'active';
            
            // Update the status
            $updateQuery = "UPDATE $table SET status = ? WHERE id = ?";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bind_param("si", $newStatus, $userId);
            
            if ($updateStmt->execute()) {
                echo json_encode(['success' => true, 'newStatus' => $newStatus]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Failed to update status']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'User not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <link rel="icon" href="img-icon/user.png" type="image/png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="Css_Admin/admin_manageuser.css">
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
            <a href="dashboard.php" class="nav-link"><i class="fas fa-home"></i> <span>Home</span></a>
            <a href="#" class="nav-link active"><i class="fas fa-users"></i> <span>Manage User</span></a>
            <a href="admin-room.php" class="nav-link"><i class="fas fa-building"></i> <span>Room Manager</span></a>
            <a href="admin-visitor_log.php" class="nav-link"><i class="fas fa-address-book"></i> <span>Log Visitor</span></a>
            <a href="admin-monitoring.php" class="nav-link"><i class="fas fa-eye"></i> <span>Presence Monitoring</span></a>
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
    <select name="sort" id="sort">
        <option value="" selected>Sort by</option>
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
    if ($result && $result->num_rows > 0) {
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
                <td>" . $no . "</td>
                <td>" . $fullName . "</td>
                <td>" . $role . "</td>
                <td>" . $formattedAccountCreated . "</td>
                <td>
                    <button class='btn-edit' onclick='editUserModal(\"$userId\", \"$row[Fname]\", \"$row[Lname]\", \"$row[MI]\", \"$row[Suffix]\", \"$role\", \"$role\")'>Edit</button>
                    <button class='btn-status " . (isset($row['status']) && $row['status'] === 'inactive' ? 'inactive' : '') . "' 
                            onclick='toggleStatus(\"$userId\", \"$role\", this)'>
                        " . (isset($row['status']) ? ucfirst($row['status']) : 'Active') . "
                    </button>
                </td>
            </tr>";
            $no++;
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
<div id="userModal" class="modal">
    <div class="addmodal-content">
        <span class="close" onclick="hideModal()">&times;</span>
        <h2 id="add-user">Add New User</h2>
        <form method="POST" action="" onsubmit="return validateForm();">
            <!-- Personal Info Page -->
            <div id="personalInfoPage" class="form-page">
                <p class="section-description">Please provide user's personal information.</p>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="fname">First Name</label>
                        <input type="text" id="fname" name="Fname" required>
                    </div>
                    <div class="form-group">
                        <label for="lname">Last Name</label>
                        <input type="text" id="lname" name="Lname" required>
                    </div>
                    <div class="form-group">
                        <label for="mi">Middle Name</label>
                        <input type="text" id="mi" name="MI">
                    </div>
                    <div class="form-group">
                        <label for="suffix">Suffix</label>
                        <input type="text" id="suffix" name="Suffix">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="birthdate">Birthdate</label>
                        <input type="date" id="birthdate" name="Birthdate" required onchange="calculateAge()">
                    </div>
                    <div class="form-group">
                        <label for="age">Age</label>
                        <input type="number" id="age" name="Age" readonly>
                    </div>
                    <div class="form-group">
                        <label for="sex">Sex</label>
                        <select id="sex" name="sex" required>
                            <option value="" disabled selected>Select Sex</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="contact">Contact Number</label>
                        <input type="text" id="contact" name="contact" required pattern="^09\d{9}$">
                    </div>
                </div>

                <div class="form-group">
                    <label for="address">Address</label>
                    <input type="text" id="address" name="Address" placeholder="House/Unit No., Street, Barangay, City/Municipality, Province" required>
                </div>
            </div>

            <!-- Account Info Page -->
            <div id="accountInfoPage" class="form-page" style="display: none;">
                <p class="section-description">Now, please provide user's account details.</p>
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
                    <div class="password-container">
                        <input type="password" id="password" name="password" required>
                        <i class="eye-icon fas fa-eye-slash" onclick="togglePasswordVisibility('password', this)"></i>
                    </div>
                </div>
            </div>

            <!-- Navigation Buttons -->
            <div class="modal-buttons">
                <button type="button" id="previousButton" onclick="previousPage()" style="display: none;">Previous</button>
                <button type="button" id="nextButton" onclick="nextPage()">Next</button>
                <button type="submit" id="submitButton" name="create_user" style="display: none;">Add User</button>
            </div>
        </form>
    </div>
</div>

<style>
/* Modal styles */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    justify-content: center;
    align-items: center;
    overflow: hidden; /* Prevent background scroll */
}

.addmodal-content {
    background-color: white;
    padding: 2rem;
    border-radius: 8px;
    width: 90%;
    max-width: 600px;
    max-height: 90vh; /* Increased from 80vh for better visibility */
    overflow-y: auto;
    position: relative;
    margin: 20px auto; /* Center the modal */
    
    /* Smooth scrolling */
    scrollbar-width: thin;
    scrollbar-color: #2B228A #f1f1f1;
    
    /* Custom scrollbar for webkit browsers */
    &::-webkit-scrollbar {
        width: 8px;
    }
    
    &::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }
    
    &::-webkit-scrollbar-thumb {
        background: #2B228A;
        border-radius: 4px;
    }
    
    &::-webkit-scrollbar-thumb:hover {
        background: #1a1552;
    }
}

/* Form styles */
.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 1rem;
    margin-bottom: 1rem;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 1rem;
}

/* Password field styles */
.password-container {
    position: relative;
}

.eye-icon {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
    color: #666;
}

/* Button styles */
.modal-buttons {
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
    margin-top: 1.5rem;
}

.modal-buttons button {
    padding: 0.5rem 1rem;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 500;
}

#nextButton, #submitButton {
    background-color: #2B228A;
    color: white;
}

#previousButton {
    background-color: #6c757d;
    color: white;
}

/* Close button */
.close {
    position: absolute;
    right: 1rem;
    top: 1rem;
    font-size: 1.5rem;
    cursor: pointer;
    color: #666;
}

/* Section description */
.section-description {
    margin-bottom: 1.5rem;
    color: #666;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .addmodal-content {
        width: 95%;
        padding: 1.5rem;
    }
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
     
$(document).ready(function() {
    var table = $('#userTable').DataTable({
        dom: 'Bfrtip',
        buttons: [
            'copy', 'csv', 'excel', 'pdf', 'print'
        ],
        order: [[0, 'asc']], // Sort by first column (No.) by default
        pageLength: 10,
        responsive: true,
        searching: false,
        paging:false,
            info: false,
        columnDefs: [
            {
                targets: -1, // Last column (Actions)
                orderable: false,
                searchable: false
            }
        ],
        language: {
            search: "Search:",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries",
            paginate: {
                first: "First",
                last: "Last",
                next: "Next",
                previous: "Previous"
            }
        }
    });

    // Add filter functionality
    $('#filter').on('change', function() {
        var filterValue = $(this).val();
        if (filterValue === 'all') {
            table.column(2).search('').draw(); // Clear filter
        } else {
            table.column(2).search(filterValue).draw(); // Filter by Role column
        }
    });

    // Add sort functionality
    $('#sort').on('change', function() {
        var sortValue = $(this).val();
        switch(sortValue) {
            case 'name_asc':
                table.order([1, 'asc']).draw(); // Sort by Name column ascending
                break;
            case 'name_desc':
                table.order([1, 'desc']).draw(); // Sort by Name column descending
                break;
            default:
                table.order([0, 'asc']).draw(); // Default sort by No. column
        }
    });
});

// Helper function for formatting the date
function getFormattedDate() {
    const date = new Date();
    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}


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
