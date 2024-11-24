<?php
// Check if the user is logged in
session_start();

include '../config/config.php';

// Check if user is logged in and retrieve user ID
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION['id'])) {
    header("location: user-login.php");
    exit;
}

$userId = $_SESSION['id'];



$query = "SELECT fname, lname, mi, suffix, birthdate, age, sex, contact, address, profile_pic, email FROM staff WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$updateSuccess = false; // Flag for successful update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_personal_info'])) {
  // Profile Information Processing
  $firstName = $_POST['firstName'] ?? '';
  $lastName = $_POST['lastName'] ?? '';
  $middleInitial = $_POST['middleInitial'] ?? '';
  $contact = $_POST['contact'] ?? '';
  $birthdate = $_POST['birthdate'] ?? '';
  $suffix = $_POST['suffix'] ?? '';
  $address = $_POST['address'] ?? '';
  $profilePic = $user['profile_pic']; // Default to current profile pic

  // Validate Contact Number
  if (!preg_match('/^09\d{9}$/', $contact)) {
      echo "Invalid contact number. It must start with '09' and be exactly 11 digits.";
      exit;
  }

  // Validate Birthdate
  if (!empty($birthdate)) {
      $birthDateObj = new DateTime($birthdate);
      $currentDate = new DateTime();
      $age = $currentDate->diff($birthDateObj)->y;
      if ($age < 16) {
          echo "You must be at least 16 years old.";
          exit;
      }
  }

  // Process Profile Picture Upload
  if (isset($_FILES['profilePic']) && $_FILES['profilePic']['error'] === UPLOAD_ERR_OK) {
      $targetDir = "../uploads/";
      $fileName = uniqid() . "-" . basename($_FILES["profilePic"]["name"]);
      $targetFilePath = $targetDir . $fileName;
      $fileType = strtolower(pathinfo($targetFilePath, PATHINFO_EXTENSION));
      $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];

      if (in_array($fileType, $allowedTypes)) {
          if (move_uploaded_file($_FILES["profilePic"]["tmp_name"], $targetFilePath)) {
              $profilePic = $targetFilePath; // Use the uploaded file path
          } else {
              echo "Error uploading file.";
              exit;
          }
      } else {
          echo "Invalid file type.";
          exit;
      }
  }

  // Update Profile Info Query
  $updateQuery = "UPDATE staff SET fname = ?, lname = ?, mi = ?, contact = ?, birthdate = ?, suffix = ?, address = ?, profile_pic = ? WHERE id = ?";
  $stmt = $conn->prepare($updateQuery);

  if ($stmt) {
      $stmt->bind_param("ssssssssi", $firstName, $lastName, $middleInitial, $contact, $birthdate, $suffix, $address, $profilePic, $userId);
      if ($stmt->execute()) {
          echo "<script>alert('Profile updated successfully!'); window.location.href = 'profile.php';</script>";
      } else {
          echo "Error executing profile update: " . $stmt->error;
      }
      $stmt->close();
  } else {
      echo "Error preparing update statement: " . $conn->error;
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_account_info'])) {
  $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
  $newPassword = $_POST['password'] ?? '';
  $confirmPassword = $_POST['confirmPassword'] ?? '';

  $errors = [];
  if (!$email) {
      $errors[] = "Invalid email address.";
  }
  if (!empty($newPassword)) {
      if (strlen($newPassword) < 6) {
          $errors[] = "Password must be at least 6 characters long.";
      }
      if ($newPassword !== $confirmPassword) {
          $errors[] = "Passwords do not match.";
      }
  }

  if (empty($errors)) {
      $query = "UPDATE staff SET email = ?";
      $params = [$email];
      $types = 's';

      if (!empty($newPassword)) {
          $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
          $query .= ", password = ?";
          $params[] = $hashedPassword;
          $types .= 's';
      }

      $query .= " WHERE id = ?";
      $params[] = $userId; // Ensure $userId is defined earlier in your code
      $types .= 'i';

      $stmt = $conn->prepare($query);
      if ($stmt) {
          $stmt->bind_param($types, ...$params);
          if ($stmt->execute()) {
              echo "<script>alert('Account Information updated successfully!'); window.location.href = 'profile.php';</script>";
          } else {
              $errors[] = "Error updating account information: " . htmlspecialchars($stmt->error);
          }
          $stmt->close();
      } else {
          $errors[] = "Error preparing statement: " . htmlspecialchars($conn->error);
      }
  }

  if (!empty($errors)) {
      // Display error messages using JavaScript alert
      $errorMessages = implode("\\n", $errors);
      echo "<script>alert('$errorMessages');</script>";
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
    <title>Dashboard</title>

    <link rel="stylesheet" href="../User/Css_user/visitor-logs.css"> <!-- I-load ang custom CSS sa huli -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.3/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">



    <!-- Bootstrap CSS -->
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
        <a href="visitor_log.php" class="nav-link"><i class="fas fa-user-check"></i> <span>Visitor log</span></a>
        <a href="staff-chat.php" class="nav-link"><i class="fas fa-comments"></i> <span>Chat</span></a>
        <a href="admin-monitoring.php" class="nav-link"><i class="fas fa-eye"></i> <span>Monitoring</span></a>

        <a href="rent_payment.php" class="nav-link"><i class="fas fa-money-bill-alt"></i> <span>Rent Payment</span></a>

        </div>
        
        <div class="logout">
        <a href="../config/user-logout.php" onclick="return confirmLogout();">
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
            Personal Information
          </button>
        </h2>
        <div id="personalInfo" class="accordion-collapse">
  <div class="accordion-body">
    <div class="row mb-3">
      <div class="col-md-3">
        <?php
        
        // Check if the profile picture is empty
        if (!empty($user['profile_pic'])) {
            echo '<img src="' . htmlspecialchars($user['profile_pic']) . '" alt="Profile Picture" class="profile-pic" />';
        } else {
            // Display the first letter of the first name as a placeholder
            $firstLetter = strtoupper(substr($user['fname'], 0, 1));
            echo '<div class="profile-pic" style="width: 200px; height: 200px; border-radius: 50%; background-color: #007bff; color: white; display: flex; align-items: center; justify-content: center; font-size: 100px;">' . $firstLetter . '</div>';
        }
        ?>




      </div>
  
      <!-- Other personal info fields go here -->

      <div class="col-md-9 mt-3">
    <div class="container">
        <div class="row mb-3 justify-content-center">
            <div class="col-md-4">
                <label class="form-label">First Name</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['fname']); ?>" readonly />
            </div>
            <div class="col-md-4">
                <label class="form-label">Last Name</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['lname']); ?>" readonly />
            </div>
            <div class="col-md-4">
                <label class="form-label">Middle Name</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['mi']); ?>" readonly />
            </div>
        </div>
        <div class="row mb-3 justify-content-center">
            <div class="col-md-4">
                <label class="form-label">Suffix</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['suffix']); ?>" readonly />
            </div>
            <div class="col-md-4">
                <label class="form-label">Birthdate</label>
                <input type="date" class="form-control" value="<?php echo htmlspecialchars($user['birthdate']); ?>" readonly />
            </div>
            <div class="col-md-4">
                <label class="form-label">Age</label>
                <input type="number" class="form-control" value="<?php echo htmlspecialchars($user['age']); ?>" readonly />
            </div>
        </div>
        <div class="row mb-3 justify-content-center">
            <div class="col-md-4">
                <label class="form-label">Sex</label>
                <select class="form-control" name="sex" disabled>
                    <option value="" disabled>Select Sex</option>
                    <option value="Male" <?php echo ($user['sex'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?php echo ($user['sex'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Contact</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['contact']); ?>" readonly />
            </div>
            <div class="col-md-4">
                <label class="form-label">Address</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['address']); ?>" readonly />
            </div>
        </div>
            
        <div class="text-end mt-3">
            <button type="submit" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editProfileModal">Edit Profile</button>
        </div>
    </div>
</div>

            </div>
          </div>
        </div>
        
      </div>

      <div class="accordion-item mt-3">
  <h2 class="accordion-header">
    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#accountInfo" aria-expanded="false">
    Account Credentials
    </button>
  </h2>  
  <div id="accountInfo" class="accordion-collapse collapse ">
    <div class="accordion-body">
    <div class="row">
    <div class="col-md-4 mb-2">
        <label for="email" class="form-label">Email</label>
        <p class="form-control"><?php echo htmlspecialchars($user['email']); ?></p>
    </div>
    <div class="col-md-4 mb-2">
        <label for="password" class="form-label">New Password</label>
        <p class="form-control"><?php echo !empty($user['password']) ? 'Password Set' : 'Not Set'; ?></p> <!-- Confirm password status text -->
    </div>
    <div class="col-md-4 mb-2">
        <label for="confirmPassword" class="form-label">Confirm Password</label>
        <p class="form-control"><?php echo !empty($user['password']) ? 'Password Set' : 'Not Set'; ?></p> <!-- Confirm password status text -->
    </div>
</div>
<!-- Button to trigger the edit form -->
<div class="text-end mt-3">
    <button type="submit" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editAccountModal">Change Information</button>
</div>

    </div>
  </div>
</div>


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
        <form id="editProfileForm" method="POST" action="profile.php" enctype="multipart/form-data">
          <input type="hidden" name="update_personal_info" value="1" />

          <!-- Upload New Profile Picture -->
          <div class="mb-3">
            <label for="profilePic" class="form-label">Upload New Profile Picture</label>
            <input type="file" class="form-control" name="profilePic" accept="image/*" />
          </div>
          
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
              <input type="number" class="form-control" name="age" value="<?php echo htmlspecialchars($user['age']); ?>" required />
            </div>
          </div>

          <!-- Fourth Row -->
          <div class="row mb-3">
            <div class="col-md-6">
              <label for="sex" class="form-label">Sex</label>
              <select class="form-control" name="sex" required>
                <option value="Male" <?php echo ($user['sex'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                <option value="Female" <?php echo ($user['sex'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                <option value="Other" <?php echo ($user['sex'] === 'Other') ? 'selected' : ''; ?>>Other</option>
              </select>
            </div>
            <div class="col-md-6">
              <label for="contact" class="form-label">Contact</label>
              <input type="text" class="form-control" name="contact" value="<?php echo htmlspecialchars($user['contact']); ?>" required />
            </div>
          </div>

          <!-- Address Field -->
          <div class="mb-3">
            <label for="address" class="form-label">Address</label>
            <input type="text" class="form-control" name="address" value="<?php echo htmlspecialchars($user['address']); ?>" required />
          </div>

          <!-- Submit Button -->
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="editAccountModal" tabindex="-1" aria-labelledby="editAccountModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="accountInfoForm" action="profile.php" method="POST">
        <input type="hidden" name="update_account_info" value="1" />
        
        <div class="modal-header">
          <h5 class="modal-title" id="editAccountModalLabel">Edit Account Credentials</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <!-- Email -->
          <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required />
          </div>

          <!-- Password Field -->
          <div class="mb-3">
            <label for="password" class="form-label">New Password</label>
            <div class="input-group">
              <input type="password" class="form-control" name="password" id="password" placeholder="Leave blank if unchanged" />
              <span class="input-group-text" onclick="togglePassword()">
                <i class="fas fa-eye" id="eyeIcon"></i>
              </span>
            </div>
          </div>

          <!-- Confirm Password Field -->
          <div class="mb-3">
            <label for="confirmPassword" class="form-label">Confirm Password</label>
            <div class="input-group">
              <input type="password" class="form-control" name="confirmPassword" id="confirmPassword" placeholder="Leave blank if unchanged" />
              <span class="input-group-text" onclick="toggleConfirmPassword()">
                <i class="fas fa-eye" id="eyeIconConfirm"></i>
              </span>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Update Account</button>
        </div>
      </form>
    </div>
  </div>
</div>



<!-- Include Popper.js and Bootstrap JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    
    <!-- JavaScript -->
    <script>
      
      function togglePassword() {
  const passwordField = document.getElementById("password");
  const eyeIcon = document.getElementById("eyeIcon");
  if (passwordField.type === "password") {
    passwordField.type = "text";
    eyeIcon.classList.replace("fa-eye", "fa-eye-slash");
  } else {
    passwordField.type = "password";
    eyeIcon.classList.replace("fa-eye-slash", "fa-eye");
  }
}

function toggleConfirmPassword() {
  const confirmPasswordField = document.getElementById("confirmPassword");
  const eyeIconConfirm = document.getElementById("eyeIconConfirm");
  if (confirmPasswordField.type === "password") {
    confirmPasswordField.type = "text";
    eyeIconConfirm.classList.replace("fa-eye", "fa-eye-slash");
  } else {
    confirmPasswordField.type = "password";
    eyeIconConfirm.classList.replace("fa-eye-slash", "fa-eye");
  }
}


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
