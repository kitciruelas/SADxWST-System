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
  $profilePic = $user['profile_pic'];

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
              echo "Error uploading file.";
          }
      } else {
          echo "Invalid file type.";
      }
  }

  // Prepare the update query for profile info
  $updateQuery = "UPDATE users SET fname = ?, lname = ?, mi = ?, contact = ?, profile_pic = ? WHERE id = ?";
  $stmt = $conn->prepare($updateQuery);

  if ($stmt) {
      $stmt->bind_param("sssssi", $firstName, $lastName, $middleInitial, $contact, $profilePic, $userId);
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
              echo "<script>alert('Account Information updated successfully!'); window.location.href = 'profile.php';</script>";
          } else {
              $errors[] = "Error updating account information: " . htmlspecialchars($stmt->error);
          }
          $stmt->close();
      } else {
          $errors[] = "Error preparing statement: " . htmlspecialchars($conn->error);
      }
  } else {
      // Display errors if any
      foreach ($errors as $error) {
          echo "<p>$error</p>";
      }
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

    <link rel="stylesheet" href="Css_user/visitor-logs.css"> <!-- I-load ang custom CSS sa huli -->
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
        <a href="#" class="nav-link active"><i class="fas fa-home"></i><span>Home</span></a>
        <a href="user_room.php" class="nav-link"><i class="fas fa-key"></i> <span>Room Assign</span></a>
        <a href="visitor_log.php" class="nav-link"><i class="fas fa-user-check"></i> <span>Log Visitor</span></a>
        <a href="chat.php" class="nav-link"><i class="fas fa-comments"></i> <span>Chat</span></a>
        <a href="user-payment.php" class="nav-link"><i class="fas fa-money-bill-alt"></i> <span>Payment History</span></a>
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
  <div class="container mt-5 mb-3">
    <button type="button" class="btn btn-outline-secondary" data-toggle="modal" data-target="#userProfileModal" style="margin-left: 20px;">
        <i class="fas fa-qrcode fa-3x"></i> QR Code
    </button>
</div>



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
        <form id="editProfileForm" method="profile.php" enctype="multipart/form-data">
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

<!-- Modal for updating account info -->
<div class="modal fade" id="editAccountModal" tabindex="-1" aria-labelledby="editAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="accountInfoForm" action="profile.php" method="POST">
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

                    <!-- Password -->
                    <div class="mb-3">
                        <label for="password" class="form-label">New Password</label>
                        <input type="password" class="form-control" name="password" placeholder="Leave blank if you do not want to change" />
                    </div>

                    <!-- Confirm Password -->
                    <div class="mb-3">
                        <label for="confirmPassword" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" name="confirmPassword" placeholder="Leave blank if you do not want to change" />
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
</body>
</html>
