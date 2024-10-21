<?php
// Define the database connection parameters
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "dormio_db";

// Connect to the database
$conn = mysqli_connect($servername, $username, $password, $dbname);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Process form submission to add new announcement
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit'])) {
    if (isset($_POST['announcement-title']) && isset($_POST['announcement-content'])) {
        $title = $_POST['announcement-title'];
        $content = $_POST['announcement-content'];

        // Insert the new announcement into the database with the current date
        $sql = "INSERT INTO announce (title, content, date_published) VALUES ('$title', '$content', NOW())";
        if (mysqli_query($conn, $sql)) {
            echo "<script>alert('New announcement added successfully');</script>";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } else {
            echo "Error: " . $sql . "<br>" . mysqli_error($conn);
        }
    }
}

// Check if the update form is submitted
if (isset($_POST['update'])) {
    $announcement_id = $_POST['announcement-id'];
    $updated_title = $_POST['announcement-title'];
    $updated_content = $_POST['announcement-content'];

    $sql = "UPDATE announce SET title = '$updated_title', content = '$updated_content' WHERE announcementId = $announcement_id";
    if (mysqli_query($conn, $sql)) {
        echo "<script>alert('Announcement updated successfully');</script>";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        echo "Error updating announcement: " . mysqli_error($conn);
    }
}

// Check if the delete form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete'])) {
    $announcementId = $_POST['announcement-id'];

    $sql = "DELETE FROM announce WHERE announcementId = $announcementId";
    if (mysqli_query($conn, $sql)) {
        echo "<script>alert('Announcement deleted successfully');</script>";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } else {
        echo "Error deleting announcement: " . mysqli_error($conn);
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['toggle-display'])) {

    $announcementId = intval(value: $_POST['announcement-id']);
    
    // Get current display status
    $sql = "SELECT is_displayed FROM announce WHERE announcementId = $announcementId";
    $result = mysqli_query($conn, $sql);
    if (!$result) {
        die("Error retrieving display status: " . mysqli_error($conn));
    }
    
    $announcement = mysqli_fetch_assoc($result);
    
    if ($announcement) {
        // Toggle the display status
        $newDisplayStatus = $announcement['is_displayed'] ? 0 : 1;
        $updateSql = "UPDATE announce SET is_displayed = $newDisplayStatus WHERE announcementId = $announcementId";
        if (mysqli_query($conn, $updateSql)) {
            echo "<script>alert('Display status updated successfully.');</script>";
        } else {
            die("Error updating display status: " . mysqli_error($conn));
        }
    } else {
        die("No announcement found with ID: $announcementId");
    }
}


// Display announcements from the database
$sql = "SELECT * FROM announce";
$result = mysqli_query($conn, $sql);

$announcements = [];
if (mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $announcements[] = $row;
    }
}

// Close the database connection
mysqli_close($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="Css_user/announcements.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <title>Announcements</title>
   
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
<!-- Back button -->

<div class="container">
<!-- Back button -->


<!-- Announcement -->
<div class="centered-content">
<button class="back-button" onclick="location.href='user-dashboard.php'"><i class="fas fa-arrow-left"></i></button>
    <h2><i class="fas fa-bullhorn announcement-icon"></i> Announcements</h2>
</div>

    <div class="announcement-options">
        <div class="announcement-option">
            <p>Create New Announcement</p>
            <p>Notify all</p>
        </div>

        <div class="add-announcement" id="add-new-button">
            Add New Announcement
        </div>
    </div>

    <div class="search-container">
        <input type="text" id="announcement-search" placeholder="Search announcements...">
        <span class="search-icon">&#128269;</span>
    </div>

    <div id="announcement-form" class="popup-form" style="display: none;">
        <h3 id="form-title">Add New Announcement</h3>
        <form id="announcement-form-inner" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <input type="hidden" id="announcement-id" name="announcement-id">
            <div class="input-container">
                <label for="announcement-title">Title:</label>
                <input type="text" id="announcement-title" name="announcement-title" required>
            </div>
            <div class="input-container">
                <label for="announcement-content">Content:</label>
                <textarea id="announcement-content" name="announcement-content" required></textarea>
            </div>
            <button type="submit" name="submit" id="submit-button">Submit</button>
            <button type="button" class="cancel-announcement" id="cancel-announcement">Cancel</button>
        </form>
    </div>
    
    <!-- update announcement -->

    <div id="update-form" class="popup-form" style="display: none;">
        <h3 id="form-title">Update Announcement</h3>
        <form id="update-form-inner" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <input type="hidden" id="update-announcement-id" name="announcement-id">
            <div class="input-container">
                <label for="update-announcement-title">Title:</label>
                <input type="text" id="update-announcement-title" name="announcement-title" required>
            </div>
            <div class="input-container">
                <label for="update-announcement-content">Content:</label>
                <textarea id="update-announcement-content" name="announcement-content" required></textarea>
            </div>
            <button type="submit" name="update" id="update-button">Update</button>
            <button type="button" class="cancel-update" id="cancel-update">Cancel</button>
        </form>
    </div>

<!-- Announcemnet table -->

    <div class="announcements">
        <?php if (!empty($announcements)): ?>
            <table border="1">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Title</th>
                        <th>Content</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $counter = 1; 
                    foreach ($announcements as $announcement): ?>
                        <tr>
                            <td><?= $counter++ ?></td>
                            <td><?= htmlspecialchars($announcement['title']) ?></td>
                            <td><?= htmlspecialchars($announcement['content']) ?></td>
                            <td><?= htmlspecialchars($announcement['date_published']) ?></td>
                            <td>
                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" style="display:inline;">
                                    <input type="hidden" name="announcement-id" value="<?= $announcement['announcementId'] ?>">
                                    <button type="submit" name="toggle-display">
                                        <?= $announcement['is_displayed'] ? 'Hide' : 'Display' ?>
                                    </button>
                                </form>
                                
                                <button class="update-button" data-id="<?= $announcement['announcementId'] ?>">
                                    <i class="far fa-edit update-icon"></i>Edit
                                </button>
                                <button class="delete-button" data-id="<?= $announcement['announcementId'] ?>">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="no-announcements">No announcements yet.</p>
        <?php endif; ?>
    </div>

</div>

<!-- JScript -->

<script>
    // Function to search announcements
    function searchAnnouncements() {
        var query = document.getElementById('announcement-search').value.toLowerCase();
        var announcements = document.querySelectorAll('.announcements table tbody tr');

        announcements.forEach(function(announcement) {
            var title = announcement.querySelector('td:nth-child(2)').textContent.toLowerCase();
            var content = announcement.querySelector('td:nth-child(3)').textContent.toLowerCase();

            // Check if query matches title or content, and display or hide accordingly
            if (title.includes(query) || content.includes(query)) {
                announcement.style.display = 'table-row';
            } else {
                announcement.style.display = 'none';
            }
        });
    }

    // Attach search event listener to the input field
    document.getElementById('announcement-search').addEventListener('input', searchAnnouncements);

    // Rest of the script...
    document.getElementById('add-new-button').addEventListener('click', function() {
        document.getElementById('announcement-form').style.display = 'block';
    });

    document.getElementById('cancel-announcement').addEventListener('click', function() {
        document.getElementById('announcement-form').style.display = 'none';
    });

    document.getElementById('cancel-update').addEventListener('click', function() {
        document.getElementById('update-form').style.display = 'none';
    });

    var updateButtons = document.querySelectorAll('.update-button');
    updateButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            var title = this.parentNode.parentNode.querySelector('td:nth-child(2)').textContent;
            var content = this.parentNode.parentNode.querySelector('td:nth-child(3)').textContent;

            document.getElementById('update-announcement-id').value = id;
            document.getElementById('update-announcement-title').value = title;
            document.getElementById('update-announcement-content').value = content;

            document.getElementById('update-form').style.display = 'block';
        });
    });

    var deleteButtons = document.querySelectorAll('.delete-button');
    deleteButtons.forEach(function(button) {
        button.addEventListener('click', function() {
            var id = this.getAttribute('data-id');

            if (confirm('Are you sure you want to delete this announcement?')) {
                var form = document.createElement('form');
                form.setAttribute('method', 'post');
                form.setAttribute('action', '<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>');
                form.innerHTML = '<input type="hidden" name="delete" value="1"><input type="hidden" name="announcement-id" value="' + id + '">';
                document.body.appendChild(form);
                form.submit();
            }
        });
    });
    
    const hamburgerMenu = document.getElementById('hamburgerMenu');
    const sidebar = document.getElementById('sidebar');

    // Initially set sidebar to collapsed
    sidebar.classList.add('collapsed');

    hamburgerMenu.addEventListener('click', function() {
        sidebar.classList.toggle('collapsed');  // Toggle sidebar collapse state
        
        // Change the icon based on the sidebar state
        const icon = hamburgerMenu.querySelector('i');
        if (sidebar.classList.contains('collapsed')) {
            icon.classList.remove('fa-times'); // Change to hamburger icon
            icon.classList.add('fa-bars');
        } else {
            icon.classList.remove('fa-bars'); // Change to close icon
            icon.classList.add('fa-times');
        }
    });
    
    // Get all sidebar links
    const sidebarLinks = document.querySelectorAll('.sidebar a');

    // Loop through each link
    sidebarLinks.forEach(link => {
        link.addEventListener('click', function() {
            // Remove the active class from all links
            sidebarLinks.forEach(link => link.classList.remove('active'));
            
            // Add the active class to the clicked link
            this.classList.add('active');
        });
    });
</script>

</body>
</html>
