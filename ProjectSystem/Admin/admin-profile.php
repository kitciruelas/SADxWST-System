<?php
session_start();
include '../config/config.php';

// Check if admin is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION['id'])) {
    header("location: login.php");
    exit;
}

$adminId = $_SESSION['id']; // Assuming the admin ID is stored in the session

// Fetch admin details
$query = "SELECT fname, lname, username, email, profile_pic FROM admin WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $adminId);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();

// Handle form submission for profile information
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['updateProfile'])) {
    // Profile Information Processing
    $firstName = $_POST['firstName'] ?? '';
    $lastName = $_POST['lastName'] ?? '';
    $username = $_POST['username'] ?? ''; // New username field
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $profilePic = $admin['profile_pic'];

    // Validate profile picture upload
    if (isset($_FILES['profilePic']) && $_FILES['profilePic']['error'] === UPLOAD_ERR_OK) {
        $targetDir = "../uploads/";
        $fileName = uniqid() . "-" . basename($_FILES["profilePic"]["name"]);
        $targetFilePath = $targetDir . $fileName;
        $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($fileType, $allowedTypes)) {
            if (move_uploaded_file($_FILES["profilePic"]["tmp_name"], $targetFilePath)) {
                $profilePic = $targetFilePath;
            } else {
                echo "Error uploading file.";
            }
        } else {
            echo "Invalid file type.";
        }
    }

    // Validate input
    if (empty($firstName) || empty($lastName) || empty($username)) {
        echo "First name, last name, and username are required.";
    } else {
        // Prepare the update query for profile info, including username
        $updateQuery = "UPDATE admin SET fname = ?, lname = ?, username = ?, profile_pic = ? WHERE id = ?";
        $stmt = $conn->prepare($updateQuery);

        if ($stmt) {
            $stmt->bind_param("ssssi", $firstName, $lastName, $username,  $profilePic, $adminId);
            if ($stmt->execute()) {
                echo "<script>alert('Profile updated successfully!'); window.location.href = 'admin-profile.php';</script>";
                exit();
            } else {
                echo "Error executing profile update: " . $stmt->error;
            }
            $stmt->close();
        } else {
            echo "Error preparing update statement: " . $conn->error;
        }
    }
}

// Handle form submission for account information
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['updateAccount'])) {
    // Account Information Processing
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $newPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
  
    $errors = [];
    if (!$email) {
        $errors[] = "Invalid email address.";
    }
    if ($newPassword) {
        if (strlen($newPassword) < 6) {
            $errors[] = "Password must be at least 6 characters long.";
        }
        if ($newPassword !== $confirmPassword) {
            $errors[] = "Passwords do not match.";
        }
    }
  
    if (empty($errors)) {
        $adminId = $_SESSION['id']; // Assuming admin ID is stored in the session
        if (!$adminId) {
            $errors[] = "User ID is not defined.";
        }

        if (empty($errors)) {
            $query = "UPDATE admin SET email = ?";
            $params = [$email];
            $types = 's';
  
            if ($newPassword) {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $query .= ", password = ?";
                $params[] = $hashedPassword;
                $types .= 's';
            }
  
            $query .= " WHERE id = ?";
            $params[] = $adminId;
            $types .= 'i';
  
            $stmt = $conn->prepare($query);
            if ($stmt) {
                $stmt->bind_param($types, ...$params);
                if ($stmt->execute()) {
                    echo "<script>alert('Account Information updated successfully!'); window.location.href = 'admin-profile.php';</script>";
                } else {
                    $errors[] = "Error updating account information: " . htmlspecialchars($stmt->error);
                }
                $stmt->close();
            } else {
                $errors[] = "Error preparing statement: " . htmlspecialchars($conn->error);
            }
        }
    }

    // Display errors as an alert
    if (!empty($errors)) {
        echo "<script>";
        foreach ($errors as $error) {
            echo "alert('" . addslashes($error) . "');";
        }
        echo "</script>";
    }
}


$conn->close();
?>




<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile</title>
    <link rel="icon" href="img-icon/profile.png" type="image/png">

    <link rel="stylesheet" href="../User/Css_user/visitor-logs.css"> <!-- I-load ang custom CSS sa huli -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>



    <!-- Bootstrap CSS -->
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="menu" id="hamburgerMenu">
            <i class="fas fa-bars"></i> <!-- Hamburger menu icon -->
        </div>

        <div class="sidebar-nav">
        <a href="#" class="nav-link active" ><i class="fas fa-home"></i> <span>Home</span></a>
            <a href="manageuser.php" class="nav-link"><i class="fas fa-users"></i> <span>Manage User</span></a>
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
        <h2>Profile Settings</h2>

    </div>

    </div>
    
    <div class="main-content mt-5">  

    <div class="container mt-5"></div>
    
  <div class="card p-4">
  <a href="dashboard.php" class="back-link">
  <i class="fas fa-arrow-left icon fa-2x mb-2"></i></a> 
    <div class="accordion" id="profileSettingsAccordion">
      
  <!-- Personal Information -->
<div class="accordion-item">
    <h2 class="accordion-header">
        <button class="accordion-button" type="button" data-bs-toggle="" data-bs-target="#personalInfo" aria-expanded="true">
            Personal Information
        </button>
    </h2>
    <div id="personalInfo" class="accordion-collapse collapse show">
        <div class="accordion-body">
            <div class="row mb-3">
                <div class="col-md-3 text-center">
                    <?php
                    // Check if the profile picture is empty
                    if (!empty($admin['profile_pic'])) {
                        echo '<img src="' . htmlspecialchars($admin['profile_pic']) . '" alt="Profile Picture" class="profile-pic" style="width: 200px; height: 200px; border-radius: 50%;" />';
                    } else {
                        // Display the first letter of the first name as a placeholder
                        $firstLetter = strtoupper(substr($admin['fname'], 0, 1));
                        echo '<div class="profile-pic" style="width: 200px; height: 200px; border-radius: 50%; background-color: #007bff; color: white; display: flex; align-items: center; justify-content: center; font-size: 100px;">' . $firstLetter . '</div>';
                    }
                    ?>
                    
                </div>

                <!-- Other personal info fields go here -->
                <div class="col-md-9">
                    <div class="container">
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <label class="form-label">Firstname</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($admin['fname']); ?>" readonly />
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Lastname</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($admin['lname']); ?>" readonly />
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($admin['username']); ?>" readonly />
                            </div>
                        </div>
                      
                        <div class="text-end mt-3">
<!-- Button to trigger Edit Profile Modal -->
<button type="button" class="btn btn-primary mt-5" data-bs-toggle="modal" data-bs-target="#editProfileModal">
    Edit Profile
</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Account Information -->
<div class="accordion-item mt-3">
    <h2 class="accordion-header">
        <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#accountInfo" aria-expanded="false">
        Account Credentials
        </button>
    </h2>  
    <div id="accountInfo" class="accordion-collapse collapse">
        <div class="accordion-body">
            <div class="row">
                <div class="col-md-4 mb-2">
                    <label for="email" class="form-label">Email</label>
                    <p class="form-control"><?php echo htmlspecialchars($admin['email']); ?></p>
                </div>
                <div class="col-md-4 mb-2">
                    <label for="password" class="form-label">New Password</label>
                    <p class="form-control"><?php echo !empty($admin['password']) ? 'Password Set' : 'Not Set'; ?></p> <!-- Confirm password status text -->
                </div>
                <div class="col-md-4 mb-2">
                    <label for="confirmPassword" class="form-label">Confirm Password</label>
                    <p class="form-control"><?php echo !empty($admin['password']) ? 'Password Set' : 'Not Set'; ?></p> <!-- Confirm password status text -->
                </div>
            </div>
            <!-- Button to trigger the edit form -->
            <div class="text-end mt-3">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editAccountModal">Change Information</button>
            </div>
        </div>
    </div>
</div>


<!-- Edit Profile Modal -->
<div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <!-- Modal Header -->
            <div class="modal-header">
                <h5 class="modal-title" id="editProfileModalLabel">Edit Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <!-- Modal Body -->
            <div class="modal-body">
                <form id="editProfileForm" action="admin-profile.php" method="POST" enctype="multipart/form-data">
                    <!-- Profile Picture Upload (2-column layout) -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="profilePic" class="form-label">Upload New Profile Picture</label>
                            <input type="file" class="form-control" name="profilePic" accept="image/*" />
                        </div>
                        <div class="col-md-6">
                            <!-- Display current profile picture (optional) -->
                            <?php if (!empty($admin['profile_pic'])): ?>
                                <img src="<?php echo htmlspecialchars($admin['profile_pic']); ?>" alt="Current Profile Picture" class="img-thumbnail" style="width: 100px; height: 100px;">
                            <?php else: ?>
                                <p>No profile picture uploaded</p>
                            <?php endif; ?>

                            <!-- If a new picture has been uploaded, show it -->
                            <?php if (isset($newProfilePicPath)): ?>
                                <p>New Profile Picture:</p>
                                <img src="<?php echo htmlspecialchars($newProfilePicPath); ?>" alt="New Profile Picture" class="img-thumbnail" style="width: 100px; height: 100px;">
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Editable Fields -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="firstName" class="form-label">First Name</label>
                            <input type="text" class="form-control" name="firstName" value="<?php echo htmlspecialchars($admin['fname']); ?>" required />
                        </div>
                        <div class="col-md-6">
                            <label for="lastName" class="form-label">Last Name</label>
                            <input type="text" class="form-control" name="lastName" value="<?php echo htmlspecialchars($admin['lname']); ?>" required />
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" name="username" value="<?php echo htmlspecialchars($admin['username']); ?>" required />
                        </div>
                       
                    </div>

                    <!-- Submit Button -->
                    <div class="text-end mt-3">
                        <button type="submit" class="btn btn-primary" name="updateProfile">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal for updating account info -->
<div class="modal fade" id="editAccountModal" tabindex="-1" aria-labelledby="editAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="accountInfoForm" action="admin-profile.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="editAccountModalLabel">Edit Account Credentials</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Email -->
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($admin['email'] ?? ''); ?>" required />
                    </div>

                    <!-- New Password -->
                    <div class="mb-3 position-relative">
                        <label for="password" class="form-label">New Password</label>
                        <input type="password" class="form-control" name="password" id="password" placeholder="Leave blank if you do not want to change" />
                        <span toggle="#password" class="fas fa-eye-slash field-icon toggle-password" style="position: absolute; top: 45px; right: 10px; cursor: pointer;"></span>
                    </div>

                    <!-- Confirm Password -->
                    <div class="mb-3 position-relative">
                        <label for="confirmPassword" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" name="confirmPassword" id="confirmPassword" placeholder="Leave blank if you do not want to change" />
                        <span toggle="#confirmPassword" class="fas fa-eye-slash field-icon toggle-password" style="position: absolute; top: 45px; right: 10px; cursor: pointer;"></span>
                    </div>

                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" name="updateAccount">Update Account</button>
                </div>
            </form>
        </div>
    </div>
</div>



<!-- Modal Structure -->
<div class="modal fade" id="userProfileModal" tabindex="-1" role="dialog" aria-labelledby="userProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userProfileModalLabel">My QR Code</h5>
               
            </div>
                  

            <script src="https://kit.fontawesome.com/a076d05399.js"></script>

<!-- Include Popper.js and Bootstrap JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    
    <!-- JavaScript -->
    <script>
        
        document.querySelectorAll('.toggle-password').forEach(function(toggleIcon) {
        toggleIcon.addEventListener('click', function() {
            let targetField = document.querySelector(toggleIcon.getAttribute('toggle'));
            if (targetField.type === "password") {
                targetField.type = "text";
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            } else {
                targetField.type = "password";
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            }
        });
    });
  // Trigger the success modal after a successful operation
document.addEventListener('DOMContentLoaded', function() {
  // Assuming you have an AJAX call or form submission that shows this modal on success
  function showSuccessModal() {
    const successModal = new bootstrap.Modal(document.getElementById('successModal'));
    successModal.show();
  }

  // Example: Automatically show modal after 1 second for testing purposes
});


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
