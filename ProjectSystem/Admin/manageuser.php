<?php
session_start();

// Redirect to login if not logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: admin-login.php");
    exit;
}

include '../config/config.php'; // Ensure this is correct

if (!$conn) {
    showErrorMessage("Database connection failed: " . mysqli_connect_error());
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
        
        // Calculate age before insertion
        $birthdateObj = new DateTime($birthdate);
        $today = new DateTime();
        $age = $birthdateObj->diff($today)->y;
        
        // Validate age
        if ($age < 16) {
            echo "<script>
                alert('User must be at least 16 years old.');
            </script>";
            exit;
        }
        
        $sql = "INSERT INTO $table (Fname, Lname, MI, Suffix, Birthdate, Age, Address, contact, Sex, email, password) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("sssssssssss", 
                $fname, 
                $lname, 
                $mi, 
                $suffix, 
                $birthdate,
                $age, // Now passing the calculated age
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
                exit;
            } else {
                echo "<script>
                    alert('Error creating user: " . addslashes($stmt->error) . "');
                </script>";
                exit;
            }
        } else {
            $errorMessage = "Error preparing statement: " . $conn->error;
        }
    }

    // Display error message if any
    if ($errorMessage) {
        showErrorMessage($errorMessage);
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
            echo "<script>
                alert('Registration email has been sent successfully.');
                if (!window.location.hash) {
                    window.location.hash = 'processed';
                }
            </script>";
        } else {
            echo "<script>
                alert('Failed to send registration email: " . addslashes($mail->ErrorInfo) . "');
                window.location.href = window.location.pathname;
            </script>";
        }
    } catch (Exception $e) {
        // Log detailed error for debugging
        error_log("Mailer Error: {$mail->ErrorInfo}");
        
        // Use a helper function to show alert and redirect
        showErrorMessage("Failed to send email. Please try again later.");
        exit;
    }
}

function showErrorMessage($message) {
    echo "<script>
        alert('" . addslashes($message) . "');
        window.location.href = 'manageuser.php';
    </script>";
    exit;
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
        // Input validation
        if (empty($fname) || empty($lname)) {
            throw new Exception("First name and last name are required.");
        }

        // Determine current table based on role
        $currentTable = ($newRole === 'Staff') ? 'staff' : 'users';
        $otherTable = ($newRole === 'Staff') ? 'users' : 'staff';

        // Begin transaction
        $conn->begin_transaction();

        // Check if role change is needed
        $checkRoleQuery = "SELECT role FROM $otherTable WHERE id = ?";
        $stmt = $conn->prepare($checkRoleQuery);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $stmt->bind_result($currentRole);
            $stmt->fetch();

            // Move user to the correct table, including required fields
            $moveQuery = "INSERT INTO $currentTable (id, fname, lname, mi, suffix,Birthdate, role, email, password, created_at, status, age, Address, contact)
                          SELECT id, fname, lname, mi, suffix,Birthdate, ?, email, password, created_at, status, age, Address, contact
                          FROM $otherTable WHERE id = ?";
            $moveStmt = $conn->prepare($moveQuery);
            $moveStmt->bind_param("si", $newRole, $userId);
            if (!$moveStmt->execute()) {
                throw new Exception("Failed to move user: " . $moveStmt->error);
            }

            // Delete from the old table
            $deleteQuery = "DELETE FROM $otherTable WHERE id = ?";
            $deleteStmt = $conn->prepare($deleteQuery);
            $deleteStmt->bind_param("i", $userId);
            if (!$deleteStmt->execute()) {
                throw new Exception("Failed to delete user from old table: " . $deleteStmt->error);
            }
        } else {
            // Update role in the current table along with other details if necessary
            $updateQuery = "UPDATE $currentTable 
                           SET Fname = ?, Lname = ?, MI = ?, Suffix = ?, role = ?
                           WHERE id = ?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param("sssssi", $fname, $lname, $mi, $suffix, $newRole, $userId);
            if (!$stmt->execute()) {
                throw new Exception("Failed to update user: " . $stmt->error);
            }
        }

        $conn->commit();
        echo "<script>
            alert('User updated successfully!');
            window.location.href = 'manageuser.php';
        </script>";
        exit;

    } catch (Exception $e) {
        if ($conn && $conn->errno == 0) {
            $conn->rollback();
        }

        // Safely encode the error message to prevent XSS
        $errorMessage = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');

        echo "<script>
            alert('Error: " . addslashes($errorMessage) . "');
            window.location.href = 'manageuser.php';
        </script>";
        exit;
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
                            echo "<script>
                                alert('User has been successfully archived and deleted!');
                                window.location.href = 'manageuser.php';
                            </script>";
                            exit;
                        } else {
                            showErrorMessage('Error deleting user: ' . $stmtDeleteUser->error);
                        }
                    }

                    // Prepare and execute the delete query for the staff
                    $stmtDeleteStaff = $conn->prepare($deleteStaffQuery);
                    if ($stmtDeleteStaff) {
                        $stmtDeleteStaff->bind_param("i", $userId);
                        if ($stmtDeleteStaff->execute()) {
                            // Optionally alert if staff entry was deleted
                        } else {
                            showErrorMessage('Error deleting staff: ' . $stmtDeleteStaff->error);
                        }
                        $stmtDeleteStaff->close();
                    }
                } else {
                    showErrorMessage('Error archiving staff: ' . $archiveStaff->error);
                }
                $archiveStaff->close();
            } else {
                showErrorMessage('Error preparing statement for staff archiving: ' . $conn->error);
            }
        } else {
            showErrorMessage('Error archiving user: ' . $archiveUsers->error);
        }
        $archiveUsers->close();
    } else {
        showErrorMessage('Error preparing statement for user archiving: ' . $conn->error);
    }
}

// Handle filter and search from the query string
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : 'active';

// Base queries for users and staff with status condition
$usersQuery = "SELECT 
    id,
    COALESCE(Fname, '') as Fname,
    COALESCE(Lname, '') as Lname,
    COALESCE(MI, '') as MI,
    COALESCE(Suffix, '') as Suffix,
    COALESCE(status, 'active') as status,
    COALESCE(Birthdate, '') as Birthdate,
    COALESCE(Age, '') as Age,
    COALESCE(Address, '') as Address,
    COALESCE(contact, '') as contact,
    COALESCE(Sex, '') as Sex,
    COALESCE(email, '') as email,
    'General User' as role,
    COALESCE(created_at, CURRENT_TIMESTAMP) as created_at
FROM users 
WHERE status = '$status'";

$staffQuery = "SELECT 
    id,
    COALESCE(Fname, '') as Fname,
    COALESCE(Lname, '') as Lname,
    COALESCE(MI, '') as MI,
    COALESCE(Suffix, '') as Suffix,
    COALESCE(status, 'active') as status,
    COALESCE(Birthdate, '') as Birthdate,
    COALESCE(Age, '') as Age,
    COALESCE(Address, '') as Address,
    COALESCE(contact, '') as contact,
    COALESCE(Sex, '') as Sex,
    COALESCE(email, '') as email,
    'Staff' as role,
    COALESCE(created_at, CURRENT_TIMESTAMP) as created_at
FROM staff 
WHERE status = '$status'";

// Add search conditions if search term exists
if (!empty($search)) {
    $searchCondition = " AND (
        Fname LIKE '%$search%' OR 
        Lname LIKE '%$search%' OR 
        MI LIKE '%$search%' OR 
        email LIKE '%$search%' OR
        CONCAT(Fname, ' ', Lname) LIKE '%$search%' OR
        CONCAT(Fname, ' ', MI, ' ', Lname) LIKE '%$search%'
    )";
    $usersQuery .= $searchCondition;
    $staffQuery .= $searchCondition;
}

// Execute query based on filter
if ($filter === 'General User') {
    $sql = $usersQuery;
} elseif ($filter === 'Staff') {
    $sql = $staffQuery;
} else {
    // If no specific filter, combine both queries
    $sql = "($usersQuery) UNION ALL ($staffQuery) ORDER BY created_at DESC";
}

// Execute the query
$result = $conn->query($sql);

if (!$result) {
    die("Query failed: " . $conn->error);
}

// Add status indicator if viewing inactive users
if ($status === 'inactive') {
    echo '<div class="col-12 mb-3">
        <div class="alert alert-info">
            Viewing Inactive Users - 
            <a href="manageuser.php?status=active" class="alert-link">Switch to Active Users</a>
        </div>
    </div>';
}

// Get total rows for pagination
$totalRows = $result->num_rows;
$rowsPerPage = 10;
$totalPages = ceil($totalRows / $rowsPerPage);
$currentPage = isset($_GET['page']) ? max(1, min($totalPages, intval($_GET['page']))) : 1;
$offset = ($currentPage - 1) * $rowsPerPage;

// Add pagination to the main query
$sql .= " LIMIT $offset, $rowsPerPage";
$result = $conn->query($sql);

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
    $userId = intval($_POST['user_id']);
    
    // Use prepared statement to prevent SQL injection
    $sql = "SELECT status FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $currentStatus = $row['status'];
        $newStatus = ($currentStatus === 'active') ? 'inactive' : 'active';

        $updateSql = "UPDATE users SET status = ? WHERE id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("si", $newStatus, $userId);
        
        if ($updateStmt->execute()) {
            $conn->commit();
            echo "<script>
                alert('Status successfully updated to ' . ucfirst($newStatus));
                window.location.href = 'manageuser.php';
            </script>";
            exit;
        } else {
            header("Location: " . $_SERVER['PHP_SELF'] . "?status_update=error");
            exit;
        }
    }
}

// Add this after your existing database connection code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $userId = intval($_POST['user_id']);
    $role = $_POST['role'];
    
    // Determine which table to update based on role
    $table = ($role === 'Staff') ? 'staff' : 'users';
    
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // Get current status using prepared statement
        $query = "SELECT status FROM $table WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            // Toggle the status
            $newStatus = ($row['status'] === 'active') ? 'inactive' : 'active';
            
            // Update the status using prepared statement
            $updateQuery = "UPDATE $table SET status = ? WHERE id = ?";
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bind_param("si", $newStatus, $userId);
            
            if ($updateStmt->execute()) {
                $conn->commit();
                echo "<script>
                    alert('Status successfully updated to " . ucfirst($newStatus) . "');
                    window.location.href = 'manageuser.php';
                </script>";
                exit;
            } else {
                // Rollback on update failure
                $conn->rollback();
                echo "<script>
                    alert('Failed to update status');
                    window.location.href = 'manageuser.php';
                </script>";
                exit;
            }
        } else {
            // Rollback if user not found
            $conn->rollback();
            echo "<script>
                alert('User not found');
                window.location.href = 'manageuser.php';
            </script>";
            exit;
        }
    } catch (Exception $e) {
        // Ensure rollback on any error
        if ($conn->connect_errno != 0) {
            $conn->rollback();
        }
        echo "<script>
            alert('Error: " . addslashes($e->getMessage()) . "');
            window.location.href = 'manageuser.php';
        </script>";
        exit;
    }
}

// Add this function at the top of your file
function getRoleDisplay($role) {
    if ($role === 'Staff') {
        return 'Staff';
    }
    return 'General User';
}

// Add this with your other POST handlers
if (isset($_POST['reactivate_user'])) {
    $userId = intval($_POST['user_id']);
    $role = $_POST['role'];
    
    // Determine which table to update
    $table = ($role === 'Staff') ? 'staff' : 'users';
    
    // Update user status to active
    $updateQuery = "UPDATE $table SET status = 'active' WHERE id = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        echo "<script>
            alert('User reactivated successfully');
            window.location.href = 'manageuser.php';
        </script>";
        exit;
    } else {
        echo "<script>
            alert('Error reactivating user: " . $stmt->error . "');
            window.location.href = 'manageuser.php';
        </script>";
        exit;
    }
}

// Add this at the top with your other PHP code
if (isset($_GET['error'])) {
    $error = $_GET['error'];
    switch($error) {
        case 'empty_fields':
            echo "<script>alert('Please fill out all required fields.');</script>";
            break;
        case 'invalid_contact':
            echo "<script>alert('Contact number must start with 09 and be 11 digits long.');</script>";
            break;
        case 'age_requirement':
            echo "<script>alert('User must be at least 16 years old.');</script>";
            break;
        case 'invalid_address':
            echo "<script>alert('Please enter a complete address including House/Unit No., Street, Barangay, City/Municipality, and Province.');</script>";
            break;
    }
}

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users</title>
    <link rel="icon" href="../img-icon/manage1.webp" type="image/png">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="Css_Admin/admin_manageuser.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<style>
.main-content {
    position: relative; /* Change to relative */
    margin-left: 200px; /* Matches sidebar width */
    padding: 20px;
    background-color: #f4f4f4;
    min-height: calc(100vh - 70px); /* Adjust height to exclude top bar */
    transition: all 0.3s ease;
    flex-direction: column; /* Stack items vertically */
    align-items: center; /* Center align items */
}

.main-content.inactive {
    padding-top: 20px; /* Specific to inactive state */
}

.main-content.active {
    padding-top: 80px; /* Specific to active state */
}
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

function confirmStatusChange(currentStatus) {
    return confirm("Are you sure you want to change this user's status from " + currentStatus + "?");
}
</script>
        </div>
    </div>

    <!-- Top bar -->
    <div class="topbar">
        <h2>Manage <?php echo ucfirst($status); ?> Users</h2>
    </div>

    
    <!-- Main content -->
    <div class="main-content <?php echo ($status === 'inactive') ? 'inactive' : 'active'; ?>">
    <!-- Search Form -->    <!-- Search Form -->
    
<div class="row">
        <!-- Search Input -->
        <div class="col-12 col-md-4">
        <form method="GET" action="" class="searchform">
            <div class="input-group">
                <input type="text" id="searchInput" name="search" class="form-control custom-input-small" 
                    placeholder="Search for names, roles, etc..."
                    value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                <span class="input-group-text mb-2">
                    <i class="fas fa-search"></i>
                </span>
            </div>
        </form>
    </div>
    <!-- Filter Dropdown -->
    <div class="col-6 col-md-2 mt-1">
        <form method="GET" action="" id="filterForm">
            <select name="filter" id="filter" class="form-select">
                <option value="all" <?php echo (!isset($_GET['filter']) || $_GET['filter'] === 'all') ? 'selected' : ''; ?>>Filter by Role</option>
                <option value="General User" <?php echo (isset($_GET['filter']) && $_GET['filter'] === 'General User') ? 'selected' : ''; ?>>General User</option>
                <option value="Staff" <?php echo (isset($_GET['filter']) && $_GET['filter'] === 'Staff') ? 'selected' : ''; ?>>Staff</option>
            </select>
        </form>
    </div>

    <!-- Sort Dropdown -->
    <div class="col-6 col-md-2 mt-2">
        <select name="sort" class="form-select" id="sort" onchange="applySort()">
            <option value="" selected>Sort by</option>
            <option value="name_asc">Name (A to Z)</option>
            <option value="name_desc">Name (Z to A)</option>
            <option value="date_asc">Date Created (Oldest to Newest)</option>
            <option value="date_desc">Date Created (Newest to Oldest)</option>
        </select>
    </div>

   <!-- Add View Inactive Button -->
   <div class="col-6 col-md-2 ">
        <?php if ($status === 'inactive'): ?>
            <a href="manageuser.php?status=active" class="btn btn-warning w-100 mt-2">
                View Active Users
            </a>
        <?php else: ?>
            <a href="manageuser.php?status=inactive" class="btn btn-warning w-100 mt-2">
                View Inactive Users
            </a>
        <?php endif; ?>
    </div>

    <!-- Add User Button -->
    <div class="col-6 col-md-2 mb-1">
        <button type="button" class="btn btn-primary w-100" onclick="showModal()">
            Add User
        </button>
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
                <td>" . getRoleDisplay($row['role']) . "</td>
                <td>" . $formattedAccountCreated . "</td>
                <td>
                    <button class='btn btn-primary btn-sm edit-btn' onclick='editUserModal(
                        " . json_encode($row["id"]) . ",
                        " . json_encode($row["Fname"]) . ",
                        " . json_encode($row["Lname"]) . ",
                        " . json_encode($row["MI"]) . ",
                        " . json_encode($row["Suffix"]) . ",
                        " . json_encode($row["role"]) .
                    ")'>Edit</button>
                    <form method='POST' style='display: inline;'>
                        <input type='hidden' name='user_id' value='" . $row['id'] . "'>
                        <input type='hidden' name='role' value='" . $row['role'] . "'>
                        <button type='submit' name='toggle_status' class='btn btn-warning btn-m edit-btn " . strtolower($row['status']) . "' 
                            onclick='return confirmStatusChange(\"" . htmlspecialchars(ucfirst($row['status']), ENT_QUOTES, 'UTF-8') . "\");'>" . 
                            ($row['status'] === 'active' ? 'Deactivate' : 'Reactivate') . "
                        </button>
                    </form>
                </td>
            </tr>";
            $no++;
        }
    }
    ?>
    </tbody>
</table>


<style>

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

    .btn-secondary.active {
        background-color: #6c757d;
        border-color: #6c757d;
        color: white;
    }

    .btn-secondary:not(.active) {
        background-color: #f8f9fa;
        border-color: #6c757d;
        color: #6c757d;
    }

    .btn-secondary:hover {
        background-color: #5a6268;
        border-color: #545b62;
        color: white;
    }

</style>
 
</div>
<!-- Pagination Controls -->
<div class="pagination-container">
    <?php if ($totalPages > 1): ?>
        <!-- Previous button -->
        <a href="?page=<?php echo max(1, $currentPage - 1); ?><?php 
            echo isset($_GET['filter']) ? '&filter=' . htmlspecialchars($_GET['filter']) : ''; 
            echo isset($_GET['search']) ? '&search=' . htmlspecialchars($_GET['search']) : ''; 
            ?>" 
           class="page-btn <?php echo ($currentPage <= 1) ? 'disabled' : ''; ?>">
            Previous
        </a>

        <!-- Page indicator -->
        <span class="page-indicator">
            Page <?php echo $currentPage; ?> of <?php echo $totalPages; ?>
        </span>

        <!-- Next button -->
        <a href="?page=<?php echo min($totalPages, $currentPage + 1); ?><?php 
            echo isset($_GET['filter']) ? '&filter=' . htmlspecialchars($_GET['filter']) : ''; 
            echo isset($_GET['search']) ? '&search=' . htmlspecialchars($_GET['search']) : ''; 
            ?>" 
           class="page-btn <?php echo ($currentPage >= $totalPages) ? 'disabled' : ''; ?>">
            Next
        </a>
    <?php endif; ?>
</div>

<style>
.pagination-container {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 15px;
    margin: 20px 0;
    flex-wrap: wrap;
}

.page-btn {
    padding: 8px 16px;
    background-color: #2B228A;
    color: white;
    text-decoration: none;
    border-radius: 4px;
    transition: background-color 0.3s;
}

.page-btn:hover:not(.disabled) {
    background-color: #1a1654;
    color: white;
}

.page-btn.disabled {
    background-color: #cccccc;
    pointer-events: none;
    cursor: not-allowed;
}

.page-indicator {
    font-weight: 500;
    color: #333;
    padding: 8px 12px;
    border-radius: 4px;
    background-color: #f8f9fa;
}
/* Responsive adjustments */
@media (max-width: 768px) {
    .pagination-container {
        gap: 10px;
    }
    
    .page-btn {
        padding: 6px 12px;
        font-size: 14px;
    }
    
    .page-indicator {
        font-size: 14px;
    }
}
</style>
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
        <form method="POST" action="">
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
                    <label for="editMI">Middle Initial:</label>
                    <input type="text" id="editMI" name="MI">
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
            <div class="modal-buttons">
                <button type="submit" name="edit_user" class="btn btn-primary btn-sm edit-btn">Update User</button>
            </div>
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
        dom: '<"dt-buttons"B>rtp',
        buttons: [
            {
                extend: 'copy',
                className: 'custom-btn',
                exportOptions: {
                    columns: [0, 1, 2, 3]
                },
                text: ' Copy',
                // Export all records
                action: function(e, dt, button, config) {
                    // Temporarily remove pagination
                    var oldLength = dt.page.len();
                    dt.page.len(-1).draw();
                    
                    $.fn.dataTable.ext.buttons.copyHtml5.action.call(this, e, dt, button, config);
                    
                    // Restore pagination
                    dt.page.len(oldLength).draw();
                }
            },
            {
                extend: 'csv',
                className: 'custom-btn',
                title: 'Complete Users List - ' + getFormattedDate(),
                exportOptions: {
                    columns: [0, 1, 2, 3],
                    modifier: {
                        page: 'all'
                    }
                },
                text: ' CSV'
            },
            {
                extend: 'excel',
                className: 'custom-btn',
                title: 'Complete Users List - ' + getFormattedDate(),
                exportOptions: {
                    columns: [0, 1, 2, 3],
                    modifier: {
                        page: 'all'
                    }
                },
                text: 'Excel'
            },
            {
                extend: 'pdf',
                className: 'custom-btn',
                title: 'Complete Users List - ' + getFormattedDate(),
                exportOptions: {
                    columns: [0, 1, 2, 3],
                    modifier: {
                        page: 'all'
                    }
                },
                text: 'PDF',
                customize: function(doc) {
                    // Customize PDF output
                    doc.content[1].table.widths = ['10%', '30%', '30%', '30%'];
                    doc.styles.tableHeader = {
                        fillColor: '#2B228A',
                        color: 'white',
                        bold: true
                    };
                    doc.styles.tableBodyEven = {
                        fillColor: '#f8f9fa'
                    };
                    
                    // Add page numbers
                    var objLayout = {};
                    objLayout['hLineWidth'] = function(i) { return .5; };
                    objLayout['vLineWidth'] = function(i) { return .5; };
                    objLayout['hLineColor'] = function(i) { return '#aaa'; };
                    objLayout['vLineColor'] = function(i) { return '#aaa'; };
                    objLayout['paddingLeft'] = function(i) { return 4; };
                    objLayout['paddingRight'] = function(i) { return 4; };
                    doc.content[1].layout = objLayout;
                }
            },
            {
                extend: 'print',
                className: 'custom-btn',
                title: '<h2>Complete Users List</h2><p>Generated on: ' + getFormattedDate() + '</p>',
                exportOptions: {
                    columns: [0, 1, 2, 3],
                    modifier: {
                        page: 'all'
                    }
                },
                text: 'Print',
                customize: function(win) {
                    $(win.document.body)
                        .css('font-family', 'Arial, sans-serif')
                        .css('padding', '20px');
                    
                    // Add custom header styling
                    $(win.document.body).find('h2')
                        .css('text-align', 'center')
                        .css('color', '#2B228A')
                        .css('margin-bottom', '5px');
                    
                    // Style the date
                    $(win.document.body).find('p')
                        .css('text-align', 'center')
                        .css('margin-bottom', '20px')
                        .css('color', '#666');
                    
                    // Style the table
                    $(win.document.body).find('table')
                        .addClass('display')
                        .css('font-size', '14px')
                        .css('border-collapse', 'collapse')
                        .css('width', '100%')
                        .css('margin-bottom', '20px');
                    
                    // Style table headers
                    $(win.document.body).find('thead th')
                        .css('background-color', '#2B228A')
                        .css('color', 'white')
                        .css('padding', '10px')
                        .css('border', '1px solid #ddd')
                        .css('font-weight', 'bold');
                    
                    // Style table cells
                    $(win.document.body).find('tbody td')
                        .css('padding', '8px')
                        .css('border', '1px solid #ddd');
                    
                    // Add zebra striping
                    $(win.document.body).find('tbody tr:nth-child(even)')
                        .css('background-color', '#f8f9fa');
                    
                    // Add footer with page numbers
                    $(win.document.body).append(
                        '<div style="text-align: center; font-size: 12px; color: #666; margin-top: 20px;">' +
                        'Page ' + document.title + ' of ' + document.title +
                        '</div>'
                    );
                }
            }
        ],
        order: [[0, 'asc']],
        pageLength: 10,
        responsive: true,
        searching: false,
        paging: false,
        info: false,
        columnDefs: [
            {
                targets: -1,
                orderable: false,
                searchable: false
            }
        ]
    });

    // Add filter functionality
    $('#filter').on('change', function() {
        var url = new URL(window.location.href);
        url.searchParams.set('filter', $(this).val());
        window.location.href = url.toString();
    });

    // Add sort functionality
    $('#sort').on('change', function() {
        var sortValue = $(this).val();
        switch(sortValue) {
            case 'name_asc':
                table.order([1, 'asc']).draw();
                break;
            case 'name_desc':
                table.order([1, 'desc']).draw();
                break;
            default:
                table.order([0, 'asc']).draw();
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
        document.getElementById("contact").value = "";
        document.getElementById("contact").focus();
    }
}


// Modify the nextPage function to include contact validation
function nextPage() {
    var currentPage = document.querySelector(".form-page:not([style*='display: none'])");
    
    // For Personal Info Page
    if (currentPage.id === "personalInfoPage") {
        // Check required fields
        var fname = document.getElementById("fname").value.trim();
        var lname = document.getElementById("lname").value.trim();
        var birthdate = document.getElementById("birthdate").value;
        var sex = document.getElementById("sex").value;
        var contact = document.getElementById("contact").value;
        var address = document.getElementById("address").value.trim();

        // Required fields validation
        if (!fname) {
            alert("Please enter First Name");
            return false;
        }
        if (!lname) {
            alert("Please enter Last Name");
            return false;
        }
        if (!birthdate) {
            alert("Please enter Birthdate");
            return false;
        }
        if (!sex) {
            alert("Please select Sex");
            return false;
        }
        if (!contact) {
            alert("Please enter Contact Number");
            return false;
        }
        if (!address) {
            alert("Please enter Address");
            return false;
        }

        // Contact number validation
        if (!contact.match(/^09\d{9}$/)) {
            alert("Contact number must start with 09 and be 11 digits long");
            return false;
        }

        // Age validation
        var birthdateObj = new Date(birthdate);
        var today = new Date();
        var age = today.getFullYear() - birthdateObj.getFullYear();
        var m = today.getMonth() - birthdateObj.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < birthdateObj.getDate())) {
            age--;
        }
        if (age < 16) {
            alert("User must be at least 16 years old");
            return false;
        }

        // Address validation
        if (address.length < 10) {
            alert("Please enter a complete address including House/Unit No., Street, Barangay, City/Municipality, and Province");
            return false;
        }
    }
    
    // For Account Info Page
    else if (currentPage.id === "accountInfoPage") {
        var email = document.getElementById("email").value.trim();
        var password = document.getElementById("password").value;
        var role = document.getElementById("role").value;

        // Required fields validation
        if (!role) {
            alert("Please select a Role");
            return false;
        }
        if (!email) {
            alert("Please enter Email");
            return false;
        }
        if (!password) {
            alert("Please enter Password");
            return false;
        }

        // Email format validation
        if (!email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
            alert("Please enter a valid email address");
            return false;
        }

        // Password length validation
        if (password.length < 6) {
            alert("Password must be at least 6 characters long");
            return false;
        }
    }

    // If all validations pass, proceed to next page
    currentPage.style.display = "none";
    var nextPage = currentPage.nextElementSibling;
    if (nextPage) {
        nextPage.style.display = "block";
        
        // Update button visibility
        if (nextPage.id === "accountInfoPage") {
            document.getElementById("previousButton").style.display = "inline-block";
            document.getElementById("nextButton").style.display = "none";
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
        
        // Reset button visibility
        document.getElementById("nextButton").style.display = "inline-block";
        document.getElementById("previousButton").style.display = "none";
        document.getElementById("submitButton").style.display = "none";
    }
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
    
    if (m < 0 || (m === 0 && today.getDate() < birthDateObj.getDate())) {
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
    document.getElementById('editFname').value = fname || '';
    document.getElementById('editLname').value = lname || '';
    document.getElementById('editMI').value = mi || '';
    document.getElementById('editSuffix').value = suffix || '';
    document.getElementById('editRole').value = role || 'General User';

    // Open the modal
    document.getElementById('editUserModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

            
 
       

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
function openEditModal(id, Fname, Lname, MI, Age, Address, contact, Sex, Role, Suffix, Birthdate) {
    document.getElementById('editUserId').value = id;
    document.getElementById('editFname').value = Fname;
    document.getElementById('editLname').value = Lname;
    document.getElementById('editMI').value = MI;
    document.getElementById('editAge').value = Age;
    document.getElementById('editAddress').value = Address;
    document.getElementById('editContact').value = contact;
    document.getElementById('editSex').value = Sex;
    document.getElementById('editRole').value = Role;
    document.getElementById('editSuffix').value = Suffix;
    document.getElementById('editBirthdate').value = Birthdate;

    document.getElementById('editUserModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
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
                    alert('Please enter a valid email.');
                    return false;
                }

                // Validate password length
                if (password && password.length < 6) {
                    alert('Password must be at least 6 characters.');
                    return false;
                }

                // Validate contact number is exactly 11 digits
                const contactRegex = /^\d{11}$/;
                if (contact && !contactRegex.test(contact)) {
                    alert('Contact number must be exactly 11 digits.');
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

