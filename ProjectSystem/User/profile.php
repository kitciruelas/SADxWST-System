<?php
// Check if the user is logged in
session_start();

include '../config/config.php';

// Add this near the top of the file, after session_start()
date_default_timezone_set('Asia/Manila');

// Check if user is logged in and retrieve user ID
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION['id'])) {
    header("location: user-login.php");
    exit;
}

$userId = $_SESSION['id'];



$query = "SELECT fname, lname, mi, suffix, birthdate, age, sex, contact, address, profile_pic, email FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$updateSuccess = false; // Flag for successful update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['firstName'])) {
  // Profile Information Processing
  $firstName = $_POST['firstName'] ?? '';
  $lastName = $_POST['lastName'] ?? '';
  $middleInitial = $_POST['middleInitial'] ?? '';
  $contact = $_POST['contact'] ?? '';
  $address = $_POST['address'] ?? '';
  $birthdate = $_POST['birthdate'] ?? '';
  $profilePic = $user['profile_pic'];

  // Calculate age from birthdate
  $birthdateObj = new DateTime($birthdate);
  $today = new DateTime();
  $age = $birthdateObj->diff($today)->y;

  // Check if age is at least 16
  if ($age < 16) {
      echo "<script>
            Swal.fire({
                title: 'Age Restriction',
                text: 'You must be at least 16 years old to register.',
                icon: 'error',
                confirmButtonColor: '#3085d6'
            }).then((result) => {
                window.history.back();
            });
        </script>";
      exit;
  }

  // Validate and process profile picture upload
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
              echo "<script>
                    Swal.fire({
                        title: 'Upload Error',
                        text: 'Error uploading file. Please try again.',
                        icon: 'error',
                        confirmButtonColor: '#3085d6'
                    });
                </script>";
              exit;
          }
      } else {
          echo "<script>
                Swal.fire({
                    title: 'Invalid File',
                    text: 'Please upload only JPG, JPEG, PNG, or GIF files.',
                    icon: 'error',
                    confirmButtonColor: '#3085d6'
                });
            </script>";
          exit;
      }
  }

  // Update query
  $updateQuery = "UPDATE users SET fname = ?, lname = ?, mi = ?, suffix = ?, contact = ?, address = ?, birthdate = ?, age = ?, sex = ?, profile_pic = ? WHERE id = ?";
  $stmt = $conn->prepare($updateQuery);

  if ($stmt) {
      // Get sex and suffix from POST data
      $sex = $_POST['sex'] ?? '';
      $suffix = $_POST['suffix'] ?? '';
      
      // Update bind_param to include new parameters
      $stmt->bind_param("ssssssssssi", 
          $firstName, 
          $lastName, 
          $middleInitial,
          $suffix,  // Added suffix
          $contact, 
          $address, 
          $birthdate, 
          $age,
          $sex,    // Added sex 
          $profilePic, 
          $userId
      );
      
      if ($stmt->execute()) {
          $updateSuccess = true; // Set flag for success
          echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    title: 'Success!',
                    text: 'Profile updated successfully!',
                    icon: 'success',
                    confirmButtonColor: '#3085d6'
                }).then((result) => {
                    window.location.href = 'profile.php';
                });
            });
        </script>";
      } else {
          echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    title: 'Error!',
                    text: 'Error updating profile: " . htmlspecialchars($stmt->error) . "',
                    icon: 'error',
                    confirmButtonColor: '#3085d6'
                });
            });
        </script>";
      }
      $stmt->close();
  } else {
      echo "Error preparing update statement: " . $conn->error;
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
  // Account Information Processing
  $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
  $newPassword = $_POST['password'] ?? '';
  $confirmPassword = $_POST['confirmPassword'] ?? '';

  $errors = [];
  if (!$email) {
      $errors[] = "Invalid email address.";
  }
  if ($newPassword && $newPassword !== $confirmPassword) {
      $errors[] = "Passwords do not match.";
  }

  if (empty($errors)) {
      $query = "UPDATE users SET email = ?";
      $params = [$email];
      $types = 's';

      if ($newPassword) {
          $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
          $query .= ", password = ?";
          $params[] = $hashedPassword;
          $types .= 's';
      }

      $query .= " WHERE id = ?";
      $params[] = $userId;
      $types .= 'i';

      $stmt = $conn->prepare($query);
      if ($stmt) {
          $stmt->bind_param($types, ...$params);
          if ($stmt->execute()) {
              echo "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            title: 'Success!',
                            text: 'Account information updated successfully!',
                            icon: 'success',
                            confirmButtonColor: '#3085d6'
                        }).then((result) => {
                            window.location.href = 'profile.php';
                        });
                    });
                </script>";
          } else {
              echo "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            title: 'Error!',
                            text: 'Error updating account: " . htmlspecialchars($stmt->error) . "',
                            icon: 'error',
                            confirmButtonColor: '#3085d6'
                        });
                    });
                </script>";
          }
          $stmt->close();
      } else {
          $errors[] = "Error preparing statement: " . htmlspecialchars($conn->error);
      }
  } else {
      // Display validation errors
      echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    title: 'Validation Error',
                    html: '" . implode("<br>", array_map('htmlspecialchars', $errors)) . "',
                    icon: 'error',
                    confirmButtonColor: '#3085d6'
                });
            });
        </script>";
  }
}

$conn->close();


?>

<!-- Add this JavaScript at the end of your body section -->
<?php if ($updateSuccess): ?>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    var successModal = new bootstrap.Modal(document.getElementById('successModal'));
    successModal.show();
  });
</script>
<?php endif; ?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile</title>
    <link rel="icon" href="../img-icon/profile1.webp" type="image/png">

    <link rel="stylesheet" href="../Admin/Css_Admin/admin_manageuser.css"> <!-- I-load ang custom CSS sa huli -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <!-- Add SweetAlert2 CSS and JS in the head section -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Bootstrap CSS -->
    <style>
        /* Add this style block in the head section or in your CSS file */
        .accordion-button {
            background: linear-gradient(to right, #007bff, #0056b3) !important;
            color: white !important;
            font-weight: 500;
        }

        .accordion-button:not(.collapsed) {
            background: linear-gradient(to right, #007bff, #0056b3) !important;
            color: white !important;
        }

        .accordion-button::after {
            filter: brightness(0) invert(1);
        }

        .accordion-button:focus {
            box-shadow: none;
            border-color: rgba(0,0,0,.125);
        }

        /* Style for the icons in the accordion headers */
        .accordion-button i {
            margin-right: 10px;
            color: white;
        }

        .credential-box {
            transition: all 0.3s ease;
        }

        .credential-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .credential-icon-container {
            transition: all 0.3s ease;
        }
        
        .credential-icon-container:hover {
            transform: scale(1.05);
        }

        .form-floating > label {
            color: #6c757d;
        }

        .form-floating > input {
            background-color: #f8f9fa;
        }

        .form-floating > input:focus {
            background-color: #fff;
        }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="menu" id="hamburgerMenu">
            <i class="fas fa-bars"></i> <!-- Hamburger menu icon -->
        </div>

        <div class="sidebar-nav">
        <a href="#" class="nav-link active"><i class="fas fa-home"></i><span>Home</span></a>
        <a href="user_room.php" class="nav-link"><i class="fas fa-key"></i> <span>Room Assign</span></a>
        <a href="visitor_log.php" class="nav-link"><i class="fas fa-user-check"></i> <span>Log Visitor</span></a>
        <a href="chat.php" class="nav-link"><i class="fas fa-comments"></i> <span>Chat</span></a>
        <a href="user-payment.php" class="nav-link"><i class="fas fa-money-bill-alt"></i> <span>Payment History</span></a>
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
        <h2>Profile Settings</h2>

    </div>

    </div>
    
    <div class="main-content">  

    <div class="container mt-5"></div>
    
  <div class="card p-4">
  <a href="user-dashboard.php" class="back-link">
  <i class="fas fa-arrow-left icon fa-2x mb-2"></i></a> 
    <div class="accordion" id="profileSettingsAccordion">
      
      <!-- Personal Information -->
      <div class="accordion-item">
       
        <h2 class="accordion-header">
          <button class="accordion-button" type="button"  data-bs-target="#personalInfo" aria-expanded="true">
            <i class="fas fa-user me-2"></i> Personal Information
          </button>
        </h2>
        <div id="personalInfo" class="accordion-collapse">
  <div class="accordion-body">
    <div class="row">
        <!-- Profile Picture Column -->
        <div class="col-md-3 text-center">
            <div class="profile-container mb-3">
                <?php if (!empty($user['profile_pic'])): ?>
                    <img src="<?php echo htmlspecialchars($user['profile_pic']); ?>" 
                         alt="Profile Picture" 
                         class="profile-pic img-fluid rounded-circle shadow" 
                         style="width: 200px; height: 200px; object-fit: cover;" />
                <?php else: ?>
                    <div class="profile-pic rounded-circle shadow d-flex align-items-center justify-content-center" 
                         style="width: 200px; height: 200px; background: linear-gradient(45deg, #007bff, #00bfff); margin: 0 auto;">
                        <span style="font-size: 80px; color: white;">
                            <?php echo strtoupper(substr($user['fname'], 0, 1)); ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>
            <button type="button" class="btn btn-outline-primary btn-lg mb-3" 
                    data-toggle="modal" data-target="#userProfileModal">
                <i class="fas fa-qrcode me-2"></i> QR Code
            </button>
        </div>

        <!-- Personal Info Fields -->
        <div class="col-md-9">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="row g-3">
                        <!-- Name Section -->
                        <div class="col-md-4">
                            <div class="form-floating">
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['fname']); ?>" readonly />
                                <label><i class="fas fa-user me-2"></i>First Name</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating">
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['lname']); ?>" readonly />
                                <label><i class="fas fa-user me-2"></i>Last Name</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating">
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['mi']); ?>" readonly />
                                <label><i class="fas fa-user me-2"></i>Middle Name</label>
                            </div>
                        </div>

                        <!-- Personal Details -->
                        <div class="col-md-4">
                            <div class="form-floating">
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['suffix']); ?>" readonly />
                                <label><i class="fas fa-user-tag me-2"></i>Suffix</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating">
                                <input type="date" class="form-control" value="<?php echo htmlspecialchars($user['birthdate']); ?>" readonly />
                                <label><i class="fas fa-calendar me-2"></i>Birthdate</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating">
                                <input type="number" class="form-control" value="<?php echo htmlspecialchars($user['age']); ?>" readonly />
                                <label><i class="fas fa-birthday-cake me-2"></i>Age</label>
                            </div>
                        </div>

                        <!-- Contact Information -->
                        <div class="col-md-4">
                            <div class="form-floating">
                                <select class="form-control" disabled>
                                    <option value="Male" <?php echo ($user['sex'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo ($user['sex'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                                </select>
                                <label><i class="fas fa-venus-mars me-2"></i>Sex</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating">
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['contact']); ?>" readonly />
                                <label><i class="fas fa-phone me-2"></i>Contact</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-floating">
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['address']); ?>" readonly />
                                <label><i class="fas fa-map-marker-alt me-2"></i>Address</label>
                            </div>
                        </div>
                    </div>

                    <!-- Edit Button -->
                    <div class="text-end mt-4">
                        <button type="button" class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                            <i class="fas fa-edit me-2"></i>Edit Profile
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

            </div>
          </div>
        </div>
        
      </div>

      <!-- Account Credentials Section -->
      <div class="accordion" id="profileSettingsAccordion">
          <div class="accordion-item mt-3">
              <h2 class="accordion-header" id="accountInfoHeader">
                  <button class="accordion-button collapsed w-100" type="button" 
                          data-bs-toggle="collapse" 
                          data-bs-target="#accountInfo" 
                          aria-expanded="false" 
                          aria-controls="accountInfo">
                      <i class="fas fa-lock me-2"></i> Account Credentials
                  </button>
              </h2>
              <div id="accountInfo" class="accordion-collapse collapse" 
                   aria-labelledby="accountInfoHeader" 
                   data-bs-parent="#profileSettingsAccordion">
                  <div class="accordion-body">
                      <div class="row">
                          <!-- Icon/Logo Column -->
                          <div class="col-md-3 text-center">
                              <div class="credential-icon-container mb-3">
                                  <div class="rounded-circle shadow d-flex align-items-center justify-content-center" 
                                       style="width: 200px; height: 200px; background: linear-gradient(45deg, #007bff, #00bfff); margin: 0 auto;">
                                      <i class="fas fa-shield-alt fa-5x" style="color: white;"></i>
                                  </div>
                              </div>
                              <button type="button" class="btn btn-outline-primary btn-lg mb-3">
                                  <i class="fas fa-lock me-2"></i> Security Status
                              </button>
                          </div>

                          <!-- Credentials Info Fields -->
                          <div class="col-md-9">
                              <div class="card shadow-sm">
                                  <div class="card-body">
                                      <div class="row g-3">
                                          <!-- Email Section -->
                                          <div class="col-md-6">
                                              <div class="form-floating">
                                                  <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" readonly />
                                                  <label><i class="fas fa-envelope me-2"></i>Email Address</label>
                                              </div>
                                          </div>
                                          <div class="col-md-6">
                                              <div class="form-floating">
                                                  <input type="text" class="form-control" value="Active" readonly />
                                                  <label><i class="fas fa-check-circle me-2"></i>Account Status</label>
                                              </div>
                                          </div>

                                          <!-- Security Section -->
                                          <div class="col-md-6">
                                              <div class="form-floating">
                                                  <input type="text" class="form-control" value="Password Protected" readonly />
                                                  <label><i class="fas fa-key me-2"></i>Password Status</label>
                                              </div>
                                          </div>
                                          <div class="col-md-6">
                                              <div class="form-floating">
                                                  <input type="text" class="form-control" value="Standard" readonly />
                                                  <label><i class="fas fa-shield-alt me-2"></i>Security Level</label>
                                              </div>
                                          </div>

                                          <!-- Additional Security Info -->
                                          <div class="col-md-6">
                                              <div class="form-floating">
                                                  <input type="text" class="form-control" value="<?php echo date('Y-m-d H:i:s'); ?>" readonly />
                                                  <label><i class="fas fa-clock me-2"></i>Last Updated</label>
                                              </div>
                                          </div>
                                          <div class="col-md-6">
                                              <div class="form-floating">
                                                  <input type="text" class="form-control" value="Email Verified" readonly />
                                                  <label><i class="fas fa-user-check me-2"></i>Verification Status</label>
                                              </div>
                                          </div>
                                      </div>

                                      <!-- Add this inside your Account Credentials card body, after the existing fields -->
                                      <div class="row g-3 mt-3">
                                          <!-- Last Login Section -->
                                          <div class="col-md-4">
                                              <div class="security-box p-3 border rounded bg-light">
                                                  <div class="d-flex align-items-center mb-2">
                                                      <i class="fas fa-clock fa-2x text-primary me-2"></i>
                                                      <h5 class="mb-0">Last Login</h5>
                                                  </div>
                                                  <p class="text-muted mb-1">Date: <?php echo date('M d, Y'); ?></p>
                                                  <p class="text-muted mb-0">Time: <?php echo date('h:i:s A'); ?></p>
                                              </div>
                                          </div>

                                          <!-- Recent Activity Section -->
                                          <div class="col-md-4">
                                              <div class="security-box p-3 border rounded bg-light">
                                                  <div class="d-flex align-items-center mb-2">
                                                      <i class="fas fa-history fa-2x text-primary me-2"></i>
                                                      <h5 class="mb-0">Recent Activity</h5>
                                                  </div>
                                                  <ul class="list-unstyled mb-0 small">
                                                      <li class="mb-1">
                                                          <i class="fas fa-sign-in-alt text-success me-1"></i> Login - <?php echo date('M d, Y h:i A'); ?>
                                                      </li>
                                                      <li class="mb-1">
                                                          <i class="fas fa-user-edit text-info me-1"></i> Profile Updated - <?php echo date('M d, Y h:i A', strtotime('-1 day')); ?>
                                                      </li>
                                                      <li>
                                                          <i class="fas fa-sign-out-alt text-warning me-1"></i> Logout - <?php echo date('M d, Y h:i A', strtotime('-2 day')); ?>
                                                      </li>
                                                  </ul>
                                              </div>
                                          </div>

                                          <!-- Login Devices Section -->
                                          <div class="col-md-4">
                                              <div class="security-box p-3 border rounded bg-light">
                                                  <div class="d-flex align-items-center mb-2">
                                                      <i class="fas fa-mobile-alt fa-2x text-primary me-2"></i>
                                                      <h5 class="mb-0">Login Devices</h5>
                                                  </div>
                                                  <div class="devices-list">
                                                      <div class="device-item mb-2 d-flex justify-content-between align-items-center">
                                                          <div>
                                                              <i class="fas fa-laptop text-secondary me-2"></i>
                                                              <span>Windows PC</span>
                                                              <small class="text-success d-block">Current Device</small>
                                                          </div>
                                                          <button class="btn btn-sm btn-outline-danger" onclick="signOutDevice('device1')">
                                                              <i class="fas fa-sign-out-alt"></i>
                                                          </button>
                                                      </div>
                                                      
                                                     
                                                  </div>
                                              </div>
                                          </div>
                                      </div>

                                      <!-- Add this CSS -->
                                      <style>
                                          .security-box {
                                              height: 100%;
                                              transition: all 0.3s ease;
                                          }

                                          .security-box:hover {
                                              transform: translateY(-5px);
                                              box-shadow: 0 4px 15px rgba(0,0,0,0.1);
                                          }

                                          .device-item {
                                              padding: 8px;
                                              border-radius: 6px;
                                              transition: background-color 0.3s ease;
                                          }

                                          .device-item:hover {
                                              background-color: #f8f9fa;
                                          }

                                          .devices-list {
                                              max-height: 200px;
                                              overflow-y: auto;
                                          }

                                          .devices-list::-webkit-scrollbar {
                                              width: 5px;
                                          }

                                          .devices-list::-webkit-scrollbar-track {
                                              background: #f1f1f1;
                                          }

                                          .devices-list::-webkit-scrollbar-thumb {
                                              background: #888;
                                              border-radius: 5px;
                                          }
                                      </style>

                                      <!-- Add this JavaScript -->
                                      <script>
    function signOutDevice(deviceId) {
        Swal.fire({
            title: 'Sign Out Device?',
            text: 'Are you sure you want to sign out from this device?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sign Out',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Success!',
                    text: 'Device has been signed out successfully.',
                    icon: 'success',
                    confirmButtonColor: '#3085d6'
                }).then(() => {
                    // Here you would typically make an API call to sign out the device
                    // For now, we'll just remove the device from the UI
                    const deviceElement = document.querySelector(`[onclick="signOutDevice('${deviceId}')"]`).closest('.device-item');
                    deviceElement.style.opacity = '0';
                    setTimeout(() => {
                        deviceElement.style.display = 'none';
                        // Redirect to user-dashboard.php after sign out
                        window.location.href = 'user-login.php';
                    }, 300);
                });
            }
        });
    }
                                          // Add a function to format dates nicely
                                          function formatDate(date) {
                                              const options = { 
                                                  year: 'numeric', 
                                                  month: 'short', 
                                                  day: 'numeric', 
                                                  hour: '2-digit', 
                                                  minute: '2-digit',
                                                  hour12: true,
                                                  timeZone: 'Asia/Manila'
                                              };
                                              return new Date(date).toLocaleDateString('en-US', options);
                                          }
                                      </script>

                                      <!-- Edit Button -->
                                      <div class="text-end mt-4">
                                          <button type="button" class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#editAccountModal">
                                              <i class="fas fa-edit me-2"></i>Change Information
                                          </button>
                                      </div>
                                  </div>
                              </div>
                          </div>
                      </div>
                  </div>
              </div>
          </div>
      </div>

<!-- Edit Profile Modal -->
<div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="editProfileModalLabel">
          <i class="fas fa-user-edit me-2"></i>Edit Profile
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      
      <div class="modal-body">
        <form id="editProfileForm" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" enctype="multipart/form-data">
          <!-- Profile Picture Preview -->
          <div class="text-center mb-4">
            <div class="profile-preview-container">
              <img id="profilePreview" src="<?php echo !empty($user['profile_pic']) ? htmlspecialchars($user['profile_pic']) : '../assets/default-avatar.png'; ?>" 
                   class="rounded-circle preview-img" style="width: 150px; height: 150px; object-fit: cover;">
            </div>
            <div class="mt-2">
              <label for="profilePic" class="btn btn-outline-primary">
                <i class="fas fa-camera me-2"></i>Change Photo
              </label>
              <input type="file" class="d-none" id="profilePic" name="profilePic" accept="image/*" onchange="previewImage(this)">
            </div>
          </div>

          <!-- Rest of the form fields remain the same -->
          <!-- First Row -->
          <div class="row mb-3">
            <div class="col-md-6">
              <label for="firstName" class="form-label">First Name</label>
              <input type="text" class="form-control" name="firstName" value="<?php echo htmlspecialchars($user['fname']); ?>" required />
            </div>
            <div class="col-md-6">
              <label for="lastName" class="form-label">Last Name</label>
              <input type="text" class="form-control" name="lastName" value="<?php echo htmlspecialchars($user['lname']); ?>" required />
            </div>
          </div>
          
          <!-- Second Row -->
          <div class="row mb-3">
            <div class="col-md-6">
              <label for="middleInitial" class="form-label">Middle Name</label>
              <input type="text" class="form-control" name="middleInitial" value="<?php echo htmlspecialchars($user['mi']); ?>" />
            </div>
            <div class="col-md-6">
              <label for="suffix" class="form-label">Suffix</label>
              <input type="text" class="form-control" name="suffix" value="<?php echo htmlspecialchars($user['suffix']); ?>" />
            </div>
          </div>

          <!-- Third Row -->
          <div class="row mb-3">
            <div class="col-md-6">
              <label for="birthdate" class="form-label">Birthdate</label>
              <input type="date" class="form-control" name="birthdate" value="<?php echo htmlspecialchars($user['birthdate']); ?>" required />
            </div>
            <div class="col-md-6">
              <label for="age" class="form-label">Age</label>
              <input type="number" class="form-control" name="age" value="<?php echo htmlspecialchars($user['age']); ?>" readonly />
            </div>
          </div>

          <!-- Additional Fields in the Fourth Row -->
          <div class="row mb-3">
            <div class="col-md-6">
              <label for="sex" class="form-label">Sex</label>
              <select class="form-control" name="sex" required>
                <option value="Male" <?php echo ($user['sex'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                <option value="Female" <?php echo ($user['sex'] === 'Female') ? 'selected' : ''; ?>>Female</option>
              </select>
            </div>
            <div class="col-md-6">
                          <label for="contact" class="form-label">Contact</label>
                          <input type="tel" class="form-control" name="contact" 
                                 value="<?php echo htmlspecialchars($user['contact']); ?>" 
                                 pattern="09\d{9}" 
                                 title="Please enter a valid 11-digit contact number starting with 09" 
                                 required />
                        </div>
          </div>
          
          <!-- Address Field -->
          <div class="mb-3">
            <label for="address" class="form-label">Address</label>
            <input type="text" class="form-control" name="address" value="<?php echo htmlspecialchars($user['address']); ?>" required />
          </div>
          
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
              <i class="fas fa-times me-2"></i>Cancel
            </button>
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-save me-2"></i>Save Changes
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Edit Account Modal -->
<div class="modal fade" id="editAccountModal" tabindex="-1" aria-labelledby="editAccountModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title" id="editAccountModalLabel">
          <i class="fas fa-lock me-2"></i>Edit Account Information
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      
      <div class="modal-body">
        <form id="editAccountForm" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST">
          <!-- Email Field -->
          <div class="mb-4">
            <label for="email" class="form-label">Email Address</label>
            <div class="input-group">
              <span class="input-group-text"><i class="fas fa-envelope"></i></span>
              <input type="email" class="form-control" id="email" name="email" 
                     value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>
          </div>



<!-- New Password Field -->
<div class="mb-4">
  <label for="password" class="form-label">New Password</label>
  <div class="input-group">
    <span class="input-group-text"><i class="fas fa-key"></i></span>
    <input type="password" class="form-control" id="password" name="password" 
           placeholder="Leave blank to keep current password"
           minlength="6">
    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
      <i class="fas fa-eye"></i>
    </button>
  </div>
  <div id="passwordStrength" class="mt-2 d-none">
    <div class="progress" style="height: 5px;">
      <div class="progress-bar" role="progressbar" style="width: 0%"></div>
    </div>
    <small class="text-muted">Password strength: <span id="strengthText">None</span></small>
  </div>
  <div class="password-requirements mt-2">
    <small class="text-muted d-block">Password must contain:</small>
    <ul class="list-unstyled">
      <li><small class="text-muted"><i class="fas fa-circle fa-xs me-2"></i>At least 6 characters</small></li>
      <li><small class="text-muted"><i class="fas fa-circle fa-xs me-2"></i>One uppercase letter (A-Z)</small></li>
      <li><small class="text-muted"><i class="fas fa-circle fa-xs me-2"></i>One lowercase letter (a-z)</small></li>
      <li><small class="text-muted"><i class="fas fa-circle fa-xs me-2"></i>One number (0-9)</small></li>
      <li><small class="text-muted"><i class="fas fa-circle fa-xs me-2"></i>One special character (!@#$%^&*)</small></li>
    </ul>
  </div>
</div>


          <!-- Confirm Password Field -->
          <div class="mb-4">
            <label for="confirmPassword" class="form-label">Confirm New Password</label>
            <div class="input-group">
              <span class="input-group-text"><i class="fas fa-lock"></i></span>
              <input type="password" class="form-control" id="confirmPassword" name="confirmPassword" 
                     placeholder="Leave blank to keep current password"
                     minlength="6">
              <button class="btn btn-outline-secondary" type="button" id="toggleConfirmPassword">
                <i class="fas fa-eye"></i>
              </button>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
              <i class="fas fa-times me-2"></i>Cancel
            </button>
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-save me-2"></i>Save Changes
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Updated JavaScript with password strength check -->
<script>
// ... existing code ...

document.addEventListener('DOMContentLoaded', function() {
    // Password strength checker with requirement validation
    function checkPasswordStrength(password) {
        let strength = 0;
        const requirements = {
            length: password.length >= 6,
            lowercase: /[a-z]/.test(password),
            uppercase: /[A-Z]/.test(password),
            numbers: /[0-9]/.test(password),
            special: /[!@#$%^&*(),.?":{}|<>]/.test(password)
        };
        
        // Update requirement indicators
        const requirementsList = document.querySelectorAll('.password-requirements li');
        requirementsList[0].classList.toggle('text-success', requirements.length);
        requirementsList[1].classList.toggle('text-success', requirements.uppercase);
        requirementsList[2].classList.toggle('text-success', requirements.lowercase);
        requirementsList[3].classList.toggle('text-success', requirements.numbers);
        requirementsList[4].classList.toggle('text-success', requirements.special);
        
        // Update icons
        requirementsList.forEach(item => {
            const icon = item.querySelector('i');
            if (item.classList.contains('text-success')) {
                icon.classList.remove('fa-circle');
                icon.classList.add('fa-check-circle');
            } else {
                icon.classList.remove('fa-check-circle');
                icon.classList.add('fa-circle');
            }
        });
        
        // Calculate strength
        Object.values(requirements).forEach(req => {
            if (req) strength += 20;
        });
        
        return strength;
    }

// ... rest of existing code ...
    // Update password strength indicator
    const passwordInput = document.getElementById('password');
    const strengthDiv = document.getElementById('passwordStrength');
    const progressBar = strengthDiv.querySelector('.progress-bar');
    const strengthText = document.getElementById('strengthText');

    passwordInput.addEventListener('input', function() {
        if (this.value) {
            strengthDiv.classList.remove('d-none');
            const strength = checkPasswordStrength(this.value);
            progressBar.style.width = strength + '%';
            
            // Update progress bar color and text
            if (strength < 40) {
                progressBar.className = 'progress-bar bg-danger';
                strengthText.textContent = 'Weak';
            } else if (strength < 80) {
                progressBar.className = 'progress-bar bg-warning';
                strengthText.textContent = 'Medium';
            } else {
                progressBar.className = 'progress-bar bg-success';
                strengthText.textContent = 'Strong';
            }
        } else {
            strengthDiv.classList.add('d-none');
        }
    });

    // Toggle password visibility
    function togglePasswordVisibility(inputId, buttonId) {
        const input = document.getElementById(inputId);
        const button = document.getElementById(buttonId);
        
        button.addEventListener('click', function() {
            const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', type);
            
            // Toggle eye icon
            const icon = button.querySelector('i');
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
        });
    }

    // Initialize password toggles
    togglePasswordVisibility('password', 'togglePassword');
    togglePasswordVisibility('confirmPassword', 'toggleConfirmPassword');

    // Form validation
    const editAccountForm = document.getElementById('editAccountForm');
    if (editAccountForm) {
        editAccountForm.addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;

            if (password) {  // Only validate if a new password is being set
                if (password.length < 6) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Error!',
                        text: 'Password must be at least 6 characters long!',
                        icon: 'error',
                        confirmButtonColor: '#3085d6'
                    });
                    return;
                }

                if (password !== confirmPassword) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Error!',
                        text: 'Passwords do not match!',
                        icon: 'error',
                        confirmButtonColor: '#3085d6'
                    });
                    return;
                }
            }

            // Show loading state
            Swal.fire({
                title: 'Updating Account',
                text: 'Please wait...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
        });
    }
});
</script>

<!-- Add these additional styles -->
<style>
.progress {
    background-color: #e9ecef;
    border-radius: 0.25rem;
    overflow: hidden;
}

.progress-bar {
    transition: width 0.3s ease;
}

.input-group-text {
    background-color: #f8f9fa;
    border-right: none;
}

.input-group .form-control {
    border-left: none;
}

.input-group .form-control:focus {
    border-color: #ced4da;
    box-shadow: none;
}

.input-group .btn-outline-secondary {
    border-color: #ced4da;
    color: #6c757d;
}

.input-group .btn-outline-secondary:hover {
    background-color: #f8f9fa;
    color: #6c757d;
}

.modal-content {
    border-radius: 0.5rem;
}

.modal-header {
    border-top-left-radius: 0.5rem;
    border-top-right-radius: 0.5rem;
}
</style>

<!-- Add this JavaScript after your existing scripts -->
<script>
// Function to preview uploaded image
function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('profilePreview').src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
    }
}

// Initialize Bootstrap modal
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all modals
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        new bootstrap.Modal(modal);
    });

    // Handle edit profile button click
    const editProfileBtn = document.querySelector('[data-bs-target="#editProfileModal"]');
    if (editProfileBtn) {
        editProfileBtn.addEventListener('click', function() {
            const editProfileModal = new bootstrap.Modal(document.getElementById('editProfileModal'));
            editProfileModal.show();
        });
    }

    // Handle form submission
    const editProfileForm = document.getElementById('editProfileForm');
    if (editProfileForm) {
        editProfileForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Show loading state
            Swal.fire({
                title: 'Updating Profile',
                text: 'Please wait...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            // Submit form
            this.submit();
        });
    }
});
</script>

<!-- Add these styles -->
<style>
.preview-img {
    border: 3px solid #fff;
    box-shadow: 0 0 10px rgba(0,0,0,0.2);
    transition: all 0.3s ease;
}

.preview-img:hover {
    transform: scale(1.05);
}

.modal-header {
    border-bottom: 2px solid #dee2e6;
}

.modal-footer {
    border-top: 2px solid #dee2e6;
}

.form-control:focus {
    border-color: #80bdff;
    box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
}
</style>

<!-- Modal Structure -->
<div class="modal fade" id="userProfileModal" tabindex="-1" role="dialog" aria-labelledby="userProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userProfileModalLabel">My QR Code</h5>
               
            </div>
                    <div class="card-body">
                    <?php
require '../phpqrcode/qrlib.php';
include '../config/config.php';

function getUserById($user_id) {
    global $conn;
    $stmt = $conn->prepare("
        SELECT id
        FROM users 
        WHERE id = ?
    ");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();
        $stmt->close();
        return $data;
    }
    return null;
}

// Determine the user ID from GET or SESSION
$user_id = isset($_GET['id']) ? intval($_GET['id']) : (isset($_SESSION['id']) ? intval($_SESSION['id']) : null);
if (!$user_id) {
    die("<div class='alert alert-warning'>No user ID provided.</div>");
}

// Fetch user data (only checking for existence of user_id)
$user = getUserById($user_id);

if ($user) {
    $qr_code_file = '../qrcodes/user_' . $user['id'] . '.png';

    // Generate QR code if it does not already exist
    if (!file_exists($qr_code_file)) {
        $directory = '../qrcodes/';
        if (!is_dir($directory)) mkdir($directory, 0755, true);

        // QR code content with only user ID
        $text = "ID: " . $user['id'];
        
        // Generate and save the QR code
        QRcode::png($text, $qr_code_file, QR_ECLEVEL_L, 10);
    }

    // Display QR code and download link
    echo "<div class='text-center'>";
    echo "<img src='../CSS/logo.png' alt='Dorm logo' style='width: 200px; margin-bottom: 10px;'>";
    echo "<h3 style='font-weight: bold;'>User ID: " . htmlspecialchars($user['id']) . "</h3>";
    echo "<div><img src='$qr_code_file' alt='QR Code for User ID " . $user['id'] . "' class='img-thumbnail'></div>";
    echo "<div class='text-center mt-3'>";
    echo "<a href='$qr_code_file' class='btn btn-outline-secondary' download='Dormio-QR_Code_UserID_" . $user['id'] . ".png'><i class='fas fa-download'></i> Download QR Code</a>";
    echo "</div>";
    echo "</div>";
} else {
    echo "<div class='alert alert-danger'>User not found.</div>";
}
?>





<!-- Include Popper.js and Bootstrap JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    
    <!-- JavaScript -->
    <script>
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

    <!-- Add this JavaScript to automatically calculate age -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const birthdateInput = document.querySelector('input[name="birthdate"]');
        const ageInput = document.querySelector('input[name="age"]');

        if (birthdateInput && ageInput) {
            birthdateInput.addEventListener('change', function() {
                const birthdate = new Date(this.value);
                const today = new Date();
                let age = today.getFullYear() - birthdate.getFullYear();
                const monthDiff = today.getMonth() - birthdate.getMonth();
                
                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthdate.getDate())) {
                    age--;
                }

                // Check if age is at least 16
                if (age < 16) {
                    Swal.fire({
                        title: 'Age Restriction',
                        text: 'You must be at least 16 years old to register.',
                        icon: 'error',
                        confirmButtonColor: '#3085d6'
                    }).then((result) => {
                        this.value = ''; // Clear the birthdate input
                        ageInput.value = ''; // Clear the age input
                    });
                    return;
                }

                ageInput.value = age;
            });

            // Make age input readonly since it's calculated automatically
            ageInput.readOnly = true;
        }
    });
    </script>

    <!-- Add this JavaScript -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Get all accordion buttons
        const accordionButtons = document.querySelectorAll('.accordion-button');
        
        accordionButtons.forEach(button => {
            button.addEventListener('click', function() {
                // Get the target collapse element
                const target = document.querySelector(this.getAttribute('data-bs-target'));
                
                // If the target is already shown (expanded)
                if (!this.classList.contains('collapsed')) {
                    // Hide it
                    this.classList.add('collapsed');
                    this.setAttribute('aria-expanded', 'false');
                    target.classList.remove('show');
                } else {
                    // Show it
                    this.classList.remove('collapsed');
                    this.setAttribute('aria-expanded', 'true');
                    target.classList.add('show');
                }
                
                // Close other open accordions
                accordionButtons.forEach(otherButton => {
                    if (otherButton !== this) {
                        const otherTarget = document.querySelector(otherButton.getAttribute('data-bs-target'));
                        otherButton.classList.add('collapsed');
                        otherButton.setAttribute('aria-expanded', 'false');
                        if (otherTarget) {
                            otherTarget.classList.remove('show');
                        }
                    }
                });
            });
        });
    });
    </script>

    <!-- Update your existing CSS -->
    <style>
        .accordion-button {
            background: linear-gradient(to right, #007bff, #0056b3) !important;
            color: white !important;
            font-weight: 500;
            padding: 1rem 1.25rem;
            font-size: 1.1rem;
            width: 100% !important;
        }

        .accordion-button:not(.collapsed) {
            background: linear-gradient(to right, #007bff, #0056b3) !important;
            color: white !important;
            box-shadow: none;
        }

        .accordion-button::after {
            filter: brightness(0) invert(1);
            transition: transform 0.2s ease-in-out;
        }

        .accordion-button.collapsed::after {
            transform: rotate(-90deg);
        }

        .accordion-button:not(.collapsed)::after {
            transform: rotate(0deg);
        }

        .accordion-collapse {
            transition: all 0.3s ease-in-out;
        }

        .accordion-collapse.collapse:not(.show) {
            display: none;
        }

        .accordion-collapse.collapsing {
            height: 0;
            overflow: hidden;
            transition: height 0.35s ease;
        }

        .accordion-collapse.collapse.show {
            display: block;
        }
    </style>
    
</body>
</html>
