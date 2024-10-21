<?php
session_start();
include '../config/config.php'; // or require 'config.php';

if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: user-login.php");
    exit;
}

// Handle edit user request
if (isset($_POST['edit_user'])) {
    $userId = intval($_POST['user_id']);
    $Fname = trim($_POST['Fname']);
    $Lname = trim($_POST['Lname']);
    $MI = trim($_POST['MI']);
    $Age = intval($_POST['Age']);
    $Address = trim($_POST['Address']);
    $contact = trim($_POST['contact']);
    $Sex = $_POST['Sex'];

    // Prepare the SQL query to update user
    $stmt = $conn->prepare("UPDATE users SET fname = ?, lname = ?, mi = ?, age = ?, Address = ?, contact = ?, sex = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("sssisssi", $Fname, $Lname, $MI, $Age, $Address, $contact, $Sex, $userId);
        if ($stmt->execute()) {
            echo "<script>alert('User updated successfully!');</script>";
        } else {
            echo "<script>alert('Error: " . $stmt->error . "');</script>";
        }
        $stmt->close();
    } else {
        echo "<script>alert('Error preparing statement: " . $conn->error . "');</script>";
    }
}
// Fetch user data from the database and set session variables
if (isset($_SESSION['id'])) {
    $userId = $_SESSION['id'];

    $sql = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        $_SESSION['Fname'] = $user['fname'];
        $_SESSION['Lname'] = $user['lname'];
        $_SESSION['MI'] = $user['mi'];
        $_SESSION['Age'] = $user['age'];
        $_SESSION['Address'] = $user['address'];
        $_SESSION['contact'] = $user['contact'];
        $_SESSION['Sex'] = $user['sex'];
    }
}


// SQL query to fetch displayed announcements
$sql = "SELECT * FROM announce WHERE is_displayed = 1";
$result = mysqli_query($conn, $sql);

$announcements = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $announcements[] = $row; // Collect displayed announcements
    }
}

// Initialize the announcement variable
$announcement = null;

// Check if an announcement ID is provided
if (isset($_GET['id'])) {
    $announcementId = intval($_GET['id']);
    $sql = "SELECT * FROM announce WHERE announcementId = $announcementId";
    $result = mysqli_query($conn, $sql);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $announcement = mysqli_fetch_assoc($result);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="Css_user/userdash.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="menu" id="hamburgerMenu">
            <i class="fas fa-bars"></i> <!-- Hamburger menu icon -->
        </div>

        <div class="sidebar-nav">
        <a href="#" class="nav-link active"><i class="fas fa-home"></i><span>Home</span></a>
          <!--    <a href="manageuser.php" class="nav-link"><i class="fas fa-users"></i> <span>Manage User</span></a>-->
        </div>
        
        <div class="logout">
            <a href="../config/user-logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
        </div>
    </div>

    <!-- Top bar -->
    <div class="topbar">
        <h2>Welcome to Dormio, <?php echo htmlspecialchars($_SESSION["username"]); ?>!</h2>

        <!-- Button to trigger modal with dynamic data -->
        <?php
        // Set default values for session variables if not set
        $id = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
        $fname = isset($_SESSION['Fname']) ? htmlspecialchars($_SESSION['Fname'], ENT_QUOTES) : '';
        $lname = isset($_SESSION['Lname']) ? htmlspecialchars($_SESSION['Lname'], ENT_QUOTES) : '';
        $mi = isset($_SESSION['MI']) ? htmlspecialchars($_SESSION['MI'], ENT_QUOTES) : '';
        $age = isset($_SESSION['Age']) ? (int)$_SESSION['Age'] : 0;
        $address = isset($_SESSION['Address']) ? htmlspecialchars($_SESSION['Address'], ENT_QUOTES) : '';
        $contact = isset($_SESSION['contact']) ? htmlspecialchars($_SESSION['contact'], ENT_QUOTES) : '';
        $sex = isset($_SESSION['Sex']) ? htmlspecialchars($_SESSION['Sex'], ENT_QUOTES) : '';

        ?>

        <!-- Button to trigger modal with dynamic data -->
<button id="openEditUserModal" class="editUserModal"
    onclick="openEditModal(
        <?php echo $id; ?>, 
        '<?php echo htmlspecialchars(addslashes($fname), ENT_QUOTES); ?>', 
        '<?php echo htmlspecialchars(addslashes($lname), ENT_QUOTES); ?>', 
        '<?php echo htmlspecialchars(addslashes($mi), ENT_QUOTES); ?>', 
        <?php echo $age; ?>, 
        '<?php echo htmlspecialchars(addslashes($address), ENT_QUOTES); ?>', 
        '<?php echo htmlspecialchars(addslashes($contact), ENT_QUOTES); ?>', 
        '<?php echo htmlspecialchars(addslashes($sex), ENT_QUOTES); ?>', 
    )">
    <i class="fa fa-user"></i> Edit User
</button>


        <!-- Modal Content -->
        <div id="editUserModal" class="modal">
            <div class="addmodal-content">
                <span class="close" onclick="closeEditModal()">&times;</span>
                <h2>Edit User</h2>
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
    </div>

    <!-- ANNOUNCEMENT -->
    <div class="main-content">
        <div class="announcement-box">
            <h2><i class="fas fa-bullhorn announcement-icon"></i> Announcements</h2>
            <div class="announcement-container">
                <?php if (!empty($announcements)): ?>
                    <?php foreach ($announcements as $announcement): ?>
                        <div class="announcement-item">
                            <h3><?= htmlspecialchars($announcement['title']) ?></h3>
                            <p><?= htmlspecialchars($announcement['content']) ?></p>
                            <p>Date Published: <?= htmlspecialchars($announcement['date_published']) ?></p>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No announcements to display.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
       // Function to open the edit modal and populate the form
        function openEditModal(id, Fname, Lname, MI, Age, Address, contact, Sex, Role) {
            document.getElementById('editUserId').value = id;
            document.getElementById('editFname').value = Fname;
            document.getElementById('editLname').value = Lname;
            document.getElementById('editMI').value = MI;
            document.getElementById('editAge').value = Age;
            document.getElementById('editAddress').value = Address;
            document.getElementById('editContact').value = contact;
            document.getElementById('editSex').value = Sex;

            document.getElementById('editUserModal').style.display = 'flex'; // Show modal
        }

        // Function to close the modal
        function closeEditModal() {
            document.getElementById('editUserModal').style.display = 'none'; // Hide modal
        }

        // Close modal if user clicks outside of it
        window.onclick = function(event) {
            var editUserModal = document.getElementById('editUserModal');
            if (event.target === editUserModal) {
                closeEditModal();
            }
        }

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
