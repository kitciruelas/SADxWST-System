<?php
// Check if the user is logged in
session_start();
require '../config/config.php';

// Check if user is logged in and retrieve user ID
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION['id'])) {
    header("location: user-login.php");
    exit;
}
$userId = $_SESSION['id'];


$query = "SELECT fname, lname, mi, age, sex, contact, address, profile_pic,email FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$updateSuccess = false; // Flag for successful update

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = $_POST['firstName'] ?? '';
    $lastName = $_POST['lastName'] ?? '';
    $middleInitial = $_POST['middleInitial'] ?? '';
    $age = $_POST['age'] ?? '';
    $sex = $_POST['sex'] ?? '';
    $contact = $_POST['contact'] ?? '';
    $address = $_POST['address'] ?? '';
    $profilePic = $user['profile_pic'];

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

    $updateQuery = "UPDATE users SET fname = ?, lname = ?, mi = ?, age = ?, sex = ?, contact = ?, address = ?, profile_pic = ? WHERE id = ?";
    $stmt = $conn->prepare($updateQuery);

    if ($stmt) {
        $stmt->bind_param("sssisissi", $firstName, $lastName, $middleInitial, $age, $sex, $contact, $address, $profilePic, $userId);
        if ($stmt->execute()) {
            $updateSuccess = true; // Set flag on successful update
        } else {
            echo "Error executing update: " . $stmt->error;
        }
        $stmt->close();
    } else {
        echo "Error preparing update statement: " . $conn->error;
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
  <title>Profile Settings</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
   
    .card {
      background-color: #f8f9fa;
      width: 80%; 
      max-width: 900px;
      margin: auto;
      padding: 20px;
      border-radius: 8px;
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }
    .form-control {
      height: 40px;
      font-size: 0.9rem;
    }
    .profile-pic {
      width: 200px;
      height: 200px;
      border-radius: 50%;
      object-fit: cover;
      margin: 0 auto;
      display: block;
    }
    .accordion-button {
      background-color: #007bff;
      color: white;
    }
    .accordion-button:not(.collapsed) {
      background-color: #0056b3;
      color: white;
    }
  </style>
</head>
<body>

<div class="container mt-5">
  <div class="card p-4">
    <h4 class="mb-4 text-center">Profile Settings</h4>

    <div class="accordion" id="profileSettingsAccordion">
      <!-- Personal Information -->
      <div class="accordion-item">
        <h2 class="accordion-header">
          <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#personalInfo" aria-expanded="true">
            Personal Information
          </button>
        </h2>
        <div id="personalInfo" class="accordion-collapse collapse show">
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
                      <label class="form-label">Middle Initial</label>
                      <input type="text" class="form-control" maxlength="1" value="<?php echo htmlspecialchars($user['mi']); ?>" readonly />
                    </div>
                  </div>
                  <div class="row mb-3 justify-content-center">
                    <div class="col-md-4">
                      <label class="form-label">Age</label>
                      <input type="number" class="form-control" value="<?php echo htmlspecialchars($user['age']); ?>" readonly />
                    </div>
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
                  </div>
                  <div class="row mb-3">
                    <div class="col-md-4">
                      <label class="form-label">Address</label>
                      <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['address']); ?>" readonly />
      
                    </div>
                  </div>
                  <div class="text-end">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editProfileModal">Edit Profile</button>
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
      Account Information
    </button>
  </h2>  
  <div id="accountInfo" class="accordion-collapse collapse show">
    <div class="accordion-body">
      <form id="accountInfoForm" method="POST">
        <div class="row"> <!-- Added a row for Bootstrap grid -->
          <div class="col-md-4 mb-2">
            <label for="email" class="form-label">Email</label>
            <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required />
          </div>
          <div class="col-md-4 mb-2">
            <label for="password" class="form-label">New Password</label>
            <input type="password" class="form-control" name="password" placeholder="Leave blank if you do not want to change" />
          </div>
          <div class="col-md-4 mb-2">
            <label for="confirmPassword" class="form-label">Confirm Password</label>
            <input type="password" class="form-control" name="confirmPassword" placeholder="Leave blank if you do not want to change" />
          </div>
        </div>
        <div class="text-end mt-3"> <!-- Added margin top for spacing -->
          <button type="submit" class="btn btn-primary">Update Account</button>
        </div>
      </form>
    </div>
  </div>
</div>


<!-- Edit Profile Modal -->
<div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editProfileModalLabel">Edit Profile</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="editProfileForm" method="POST" enctype="multipart/form-data">
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
              <label for="middleInitial" class="form-label">Middle Initial</label>
              <input type="text" class="form-control" name="middleInitial" maxlength="1" value="<?php echo htmlspecialchars($user['mi']); ?>" />
            </div>
            <div class="col-md-6">
              <label for="age" class="form-label">Age</label>
              <input type="number" class="form-control" name="age" value="<?php echo htmlspecialchars($user['age']); ?>" required />
            </div>
          </div>
          
          <!-- Additional Fields in the Second Row -->
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
          
          <div class="mb-3">
            <label for="address" class="form-label">Address</label>
            <input type="text" class="form-control" name="address" value="<?php echo htmlspecialchars($user['address']); ?>" required />
          </div>
          
          <button type="submit" class="btn btn-primary">Save Changes</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="successModalLabel">Profile Updated</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Your profile has been updated successfully.
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
