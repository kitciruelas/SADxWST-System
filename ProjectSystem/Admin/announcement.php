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

    $sql = "UPDATE announce 
    SET title = '$updated_title', 
        content = '$updated_content', 
        date_published = NOW() 
    WHERE announcementId = $announcement_id";
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

    // Step 1: Copy the announcement to the archive table
    $archiveSql = "INSERT INTO announce_archive (announcementId, title, content, date_published, is_displayed)
                   SELECT announcementId, title, content, date_published, is_displayed
                   FROM announce WHERE announcementId = $announcementId";

    if (mysqli_query($conn, $archiveSql)) {
        // Step 2: Delete the announcement from the original table
        $deleteSql = "DELETE FROM announce WHERE announcementId = $announcementId";
        if (mysqli_query($conn, $deleteSql)) {
            echo "<script>alert('Announcement archived and deleted successfully');</script>";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } else {
            echo "Error deleting announcement: " . mysqli_error($conn);
        }
    } else {
        echo "Error archiving announcement: " . mysqli_error($conn);
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


// Display announcements from the database in ascending order by date
$sql = "SELECT * FROM announce ORDER BY date_published DESC";
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="Css_Admin/admin-announce.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
   <!-- DataTables CSS -->
   <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>

    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.68/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.68/vfs_fonts.js"></script>

    <title>Announcements</title>
   
</head>
<body>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="menu" id="hamburgerMenu">
        <i class="fas fa-bars"></i> <!-- Hamburger menu icon -->
    </div>

    <div class="sidebar-nav">
        <a href="#" class="nav-link active" ><i class="fas fa-home"></i> <span>Home</span></a>
        <a href="manageuser.php" class="nav-link"><i class="fas fa-users"></i><span>Manage User</span></a>
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
<!-- Back button -->

<div class="container">
<!-- Back button -->


<!-- Announcement -->
<div class="centered-content ">
<button class="back-button" onclick="location.href='dashboard.php'"><i class="fas fa-arrow-left fa-2x"></i></button>
<h2 class="announcements-heading mb-4"> 
    <i class="fas fa-bullhorn announcement-icon"></i> Announcements
</h2>
</div>

   <!-- Announcement Options -->
<div class="announcement-options">
    <div class="announcement-option">
        <p>Create New Announcement</p>
        <p>Notify all</p>
    </div>
    <div class="add-announcement btn btn-primary d-inline-flex align-items-center" id="add-new-button">
    <i class="fas fa-plus me-2"></i> Add Announcement
</div>

<!-- Include Font Awesome for Icons if not already included -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">

</div>

<!-- Search and Filter Container -->
<div class="row mb-4 align-items-center">
    <!-- Search Container -->
    <div class="col-12 col-md-8">
        <div class="search-container">
            <input type="text" id="announcement-search" class="form-control custom-input-small" placeholder="Search announcements...">
            <i class="fas fa-search search-icon"></i>
            
        </div>
    </div>

    <!-- Filter Select -->
  <!-- Filter Select -->
<div class="col-12 col-md-4">
    <select id="filterSelect" class="form-select" onchange="filterTable()">
        <option value="all" selected>Filter by</option>
        <option value="title">Title</option>
        <option value="content">Content</option>
        <option value="date">Date</option>
    </select>
</div>

</div>
<style>
/* Filter Select Styles */
#filterSelect {
    font-size: 1rem;
    padding: 0.375rem 1.5rem 0.375rem 1rem; /* Adjust padding for a balanced look */
    border-radius: 0.375rem; /* Rounded corners */
    border: 1px solid #ced4da; /* Border color */
    background-color: #fff; /* White background */
    appearance: none; /* Remove default arrow */
    -webkit-appearance: none; /* Safari support */
    -moz-appearance: none; /* Firefox support */
}

#filterSelect:focus {
    border-color: #80bdff; /* Blue border on focus */
    outline: none; /* Remove outline */
}

/* Adding a custom arrow icon for select dropdown */
#filterSelect::after {
    content: "▼";
    font-size: 0.8rem;
    color: #666;
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
}

/* Align the filter select to the left */
.col-12.col-md-4 {
    display: flex;
    justify-content: flex-start; /* Align the select to the left */
    margin-bottom: 20px; /* Adjust bottom margin */
}

@media (max-width: 767px) {
    /* Ensuring the filter select is full-width on smaller screens */
    #filterSelect {
        width: 100%;
    }
}

</style>


<!-- Announcements Table -->
<div class="announcements">
    <?php if (!empty($announcements)): ?>
        <div class="table-responsive">
    <table class="table table-bordered table-striped" id="announcementTable">
        <thead class="thead-dark">
            <tr>
                <th scope="col">No.</th>
                <th scope="col">Title</th>
                <th scope="col">Content</th>
                <th scope="col">Date</th>
                <th scope="col">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $counter = 1; 
            foreach ($announcements as $announcement): ?>
                <tr>
                    <td><?= $counter++ ?></td>
                    <td class="title"><?= htmlspecialchars($announcement['title']) ?></td>
                    <td class="content"><?= htmlspecialchars($announcement['content']) ?></td>
                    <td class="date_published"><?= htmlspecialchars($announcement['date_published']) ?></td>
                    <td>
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" style="display:inline;">
                            <input type="hidden" name="announcement-id" value="<?= $announcement['announcementId'] ?>">
                            <button type="submit" name="toggle-display" class="btn btn-sm <?= $announcement['is_displayed'] ? 'btn-warning' : 'btn-success' ?>">
                                <?= $announcement['is_displayed'] ? 'Hide' : 'Display' ?>
                            </button>
                        </form>
                        <button class="btn btn-sm btn-primary update-button" data-id="<?= $announcement['announcementId'] ?>">
                            <i class="far fa-edit"></i> Edit
                        </button>
                        <button class="btn btn-sm btn-danger delete-button" data-id="<?= $announcement['announcementId'] ?>">Delete</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

    <?php else: ?>
        <p class="alert alert-info text-center">No announcements yet.</p>
    <?php endif; ?>
</div>
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
            <button type="button" class="cancel-announcement" id="cancel-update">Cancel</button>
        </form>
    </div>

<!-- JScript -->

<script>

 
// Function to filter the table based on search and filter selection
function filterTable() {
    var filterValue = document.getElementById("filterSelect").value.toLowerCase(); // Get selected filter option
    var searchValue = document.getElementById("announcement-search").value.toLowerCase(); // Get search input value

    // Get all rows in the table
    var rows = document.querySelectorAll("#announcementTable tbody tr");

    // Loop through each row
    rows.forEach(function(row) {
        // Get text content for each column (Title, Content, Date)
        var title = row.querySelector(".title").textContent.toLowerCase();
        var content = row.querySelector(".content").textContent.toLowerCase();
        var date = row.querySelector(".date_published").textContent.toLowerCase();

        var showRow = false;

        // Apply the filter based on selected filter option
        if (filterValue === "all" || (filterValue === "title" && title.includes(searchValue)) || 
            (filterValue === "content" && content.includes(searchValue)) || 
            (filterValue === "date" && date.includes(searchValue))) {
            showRow = true;
        }

        // Show or hide the row based on the filter and search criteria
        if (showRow) {
            row.style.display = "";
        } else {
            row.style.display = "none";
        }
    });
}

// Add event listener for search input to trigger filtering on typing
document.getElementById("announcement-search").addEventListener("input", filterTable);

// Add event listener for the filter select to trigger filtering on change
document.getElementById("filterSelect").addEventListener("change", filterTable);

// Add event listener for search input to trigger filtering on typing
document.getElementById("announcement-search").addEventListener("input", filterTable);

// Add event listener for the filter select to trigger filtering on change
document.getElementById("filterSelect").addEventListener("change", filterTable);

  $(document).ready(function () {
    $('#announcementTable').DataTable({
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'copy',
                text: 'Copy',
                className: 'btn btn-primary'
            },
            {
                extend: 'csv',
                text: 'Export CSV',
                className: 'btn btn-success',
                exportOptions: {
                    columns: ':not(:last-child)' // Exclude the last column (if any)
                }
            },
            {
                extend: 'excel',
                text: 'Export Excel',
                className: 'btn btn-info',
                exportOptions: {
                    columns: ':not(:last-child)'
                }
            },
            {
                extend: 'pdf',
                text: 'Export PDF',
                className: 'btn btn-danger',
                exportOptions: {
                    columns: ':not(:last-child)'
                },
                title: '',  // Empty title to remove it
                customize: function(doc) {
                    // Remove title and customize the PDF appearance
                    doc.content[1].table.widths = ['10%', '30%', '40%', '20%'];

                    // Style the table header
                    doc.content[1].table.body[0].forEach(function (header) {
                        header.alignment = 'center';
                        header.bold = true;
                        header.fillColor = '#4CAF50'; // Set background color for headers
                        header.color = 'white'; // Set text color for headers
                    });

                    // Align table cells to the center
                    doc.content[1].table.body.slice(1).forEach(function (row) {
                        row.forEach(function (cell) {
                            cell.alignment = 'center';
                        });
                    });

                    doc.styles = {
                        tableHeader: {
                            bold: true,
                            fontSize: 12,
                            color: 'black'
                        }
                    };

                    doc.pageMargins = [20, 20, 20, 20];
                }
            },
            {
                extend: 'print',
                text: 'Print',
                className: 'btn btn-warning',
                exportOptions: {
                    columns: ':not(:last-child)'
                },
                title: '',  // Empty title to remove it
                customize: function(win) {
                    // Customize print layout (no title)
                    $(win.document.body).find('th').css({
                        'background-color': '#4CAF50', // Set background color for headers
                        'color': 'white',               // Set text color for headers
                        'font-weight': 'bold',          // Set bold text for headers
                        'text-align': 'center'         // Align text to the center for headers
                    });

                    $(win.document.body).find('td').css({
                        'text-align': 'center' // Align text to the center for table cells
                    });
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
