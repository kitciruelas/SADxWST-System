<?php
session_start(); // Ensure session is started

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
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add'])) {
    if (isset($_POST['announcement-title']) && isset($_POST['announcement-content'])) {
        $title = mysqli_real_escape_string($conn, $_POST['announcement-title']);
        $content = mysqli_real_escape_string($conn, $_POST['announcement-content']);

        $sql = "INSERT INTO announce (title, content, date_published) VALUES (?, ?, NOW())";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ss", $title, $content);
        
        if (mysqli_stmt_execute($stmt)) {
            logActivity($conn, $_SESSION['id'], 'Add Announcement', 'Added a new announcement with title: ' . $title);
            $_SESSION['swal_success'] = [
                'title' => 'Success!',
                'text' => 'New announcement added successfully!',
                'icon' => 'success'
            ];
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $_SESSION['swal_error'] = [
                'title' => 'Error',
                'text' => 'Error adding announcement: ' . mysqli_error($conn),
                'icon' => 'error'
            ];
        }
    }
}

// Update announcement
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update'])) {
    $announcement_id = mysqli_real_escape_string($conn, $_POST['announcement-id']);
    $updated_title = mysqli_real_escape_string($conn, $_POST['announcement-title']);
    $updated_content = mysqli_real_escape_string($conn, $_POST['announcement-content']);

    $sql = "UPDATE announce SET title = ?, content = ?, date_published = NOW() WHERE announcementId = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ssi", $updated_title, $updated_content, $announcement_id);
    
    if (mysqli_stmt_execute($stmt)) {
        logActivity($conn, $_SESSION['id'], 'Update Announcement', 'Updated announcement ID: ' . $announcement_id);
        $_SESSION['swal_success'] = [
            'title' => 'Success!',
            'text' => 'Announcement updated successfully!',
            'icon' => 'success'
        ];
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    } else {
        $_SESSION['swal_error'] = [
            'title' => 'Error',
            'text' => 'Error updating announcement: ' . mysqli_error($conn),
            'icon' => 'error'
        ];
    }
}

// Delete (archive) announcement
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete'])) {
    $announcementId = mysqli_real_escape_string($conn, $_POST['announcement-id']);

    // Update the archive status instead of deleting
    $archiveSql = "UPDATE announce SET archive_status = 'archived' WHERE announcementId = ?";
    $stmt = mysqli_prepare($conn, $archiveSql);
    mysqli_stmt_bind_param($stmt, "i", $announcementId);
    
    if (mysqli_stmt_execute($stmt)) {
        logActivity($conn, $_SESSION['id'], 'Delete Announcement', 'Archived announcement ID: ' . $announcementId);
        $_SESSION['swal_success'] = [
            'title' => 'Success!',
            'text' => 'Announcement deleted successfully!',
            'icon' => 'success'
        ];
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $_SESSION['swal_error'] = [
            'title' => 'Error',
            'text' => 'Error archiving announcement: ' . mysqli_error($conn),
            'icon' => 'error'
        ];
    }
}

// Toggle display status
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['toggle-display'])) {
    $announcementId = intval($_POST['announcement-id']);
    
    $sql = "SELECT is_displayed FROM announce WHERE announcementId = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $announcementId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (!$result) {
        die("Error retrieving display status: " . mysqli_error($conn));
    }
    
    $announcement = mysqli_fetch_assoc($result);
    
    if ($announcement) {
        $newDisplayStatus = $announcement['is_displayed'] ? 0 : 1;
        $updateSql = "UPDATE announce SET is_displayed = ? WHERE announcementId = ?";
        $stmt = mysqli_prepare($conn, $updateSql);
        mysqli_stmt_bind_param($stmt, "ii", $newDisplayStatus, $announcementId);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['swal_success'] = [
                'title' => 'Success!',
                'text' => 'Display status updated successfully.',
                'icon' => 'success'
            ];
        } else {
            $_SESSION['swal_error'] = [
                'title' => 'Error',
                'text' => 'Error updating display status: ' . mysqli_error($conn),
                'icon' => 'error'
            ];
        }
    } else {
        die("No announcement found with ID: $announcementId");
    }
}

// Display only active announcements from the database in descending order by date
$sql = "SELECT * FROM announce WHERE archive_status = 'active' ORDER BY date_published DESC";
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
    <link rel="stylesheet" href="../Admin/Css_Admin/admin_manageuser.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css">

<!-- Include Font Awesome for Icons if not already included -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
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

    <!-- DataTables Export Dependencies -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.68/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.68/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>

    <title>Announcements</title>
    <link rel="icon" href="../img-icon/bell1.webp" type="image/png">

    <style>
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
        background-color: #cccccc;
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
</style>

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
        <a href="../config/logout.php" id="logoutLink">
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
<div class="topbar">
        
        <h2>Announcement</h2>
        
    </div>
<!-- Back button -->
<div class="main-content">
    <div class="container">
        <div class="container mt-4">
            <!-- Search, Filter, Sort, and Add Announcement Row -->
            <div class="row g-3 mb-4">
                <!-- Search Box -->
                <div class="col-lg-3 col-md-6">
                    <div class="search-box w-100">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" class="form-control h-100" placeholder="Search announcements...">
                    </div>
                </div>

                <!-- Filter Select -->
                <div class="col-lg-3 col-md-6">
                    <select id="filterSelect" class="form-select h-100">
                        <option value="all">All Announcements</option>
                        <option value="displayed">Displayed Only</option>
                        <option value="hidden">Hidden Only</option>
                    </select>
                </div>

                <!-- Sort Select -->
                <div class="col-lg-3 col-md-6">
                    <select id="sortSelect" class="form-select h-100">
                        <option value="newest">Newest First</option>
                        <option value="oldest">Oldest First</option>
                        <option value="title">Title (A-Z)</option>
                    </select>
                </div>

                <!-- Add New Button -->
                <div class="col-lg-3 col-md-6">
                    <button class="btn btn-primary w-100 h-100" id="add-new-button">
                        <i class="fas fa-plus me-2"></i>Add New
                    </button>
                </div>
            </div>

            <!-- Reset Button -->
            <div class="row mb-3">
                <div class="col-12">
                    <button id="resetFilters" class="btn btn-secondary btn-sm">
                        <i class="fas fa-undo me-1"></i>Reset Filters
                    </button>
                </div>
            </div>

            <!-- Announcements Table -->
            <div class="announcements mt-3">
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
                                            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" style="display:inline;" class="toggle-display-form">
                                                <input type="hidden" name="announcement-id" value="<?= $announcement['announcementId'] ?>">
                                                <button type="button" class="btn btn-sm toggle-display-button <?= $announcement['is_displayed'] ? 'btn-warning' : 'btn-success' ?>">
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
        </div>
    </div>
</div>

<!-- Add/Update Announcement Modal -->
<div class="modal fade" id="announcementModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add New Announcement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="announcementForm">
                    <input type="hidden" id="announcement-id" name="announcement-id">
                    <div class="mb-3">
                        <label for="announcement-title" class="form-label">Title</label>
                        <input type="text" class="form-control" id="announcement-title" name="announcement-title" required>
                    </div>
                    <div class="mb-3">
                        <label for="announcement-content" class="form-label">Content</label>
                        <textarea class="form-control" id="announcement-content" name="announcement-content" rows="4" required></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="saveButton">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
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

    /* Add these new styles for the search box */
    .search-box {
        position: relative;
    }

    .search-box i {
        position: absolute;
        left: 10px;
        top: 50%;
        transform: translateY(-50%);
        color: #6c757d;
        z-index: 1;
    }

    .search-box input {
        padding-left: 35px;
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

    /* Search box styling */
    .search-box {
        position: relative;
        margin-bottom: 15px;
    }

    .search-box i {
        position: absolute;
        left: 10px;
        top: 50%;
        transform: translateY(-50%);
        color: #6c757d;
        z-index: 1;
    }

    .search-box input {
        padding-left: 35px;
        border-radius: 4px;
        border: 1px solid #ced4da;
    }

    /* Select boxes styling */
    .form-select {
        margin-bottom: 15px;
        border-radius: 4px;
        border: 1px solid #ced4da;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .search-box, .form-select, #add-new-button {
            margin-bottom: 10px;
        }
    }

    /* Filter controls styling */
    .search-box {
        position: relative;
    }

    .search-box i {
        position: absolute;
        left: 10px;
        top: 50%;
        transform: translateY(-50%);
        color: #6c757d;
        z-index: 1;
    }

    .search-box input,
    .form-select,
    #add-new-button {
        height: 38px;           /* Consistent height for all elements */
        margin-bottom: 0;       /* Remove bottom margin */
    }

    .search-box input {
        padding-left: 35px;
        border-radius: 4px;
        border: 1px solid #ced4da;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .search-box, 
        .form-select, 
        #add-new-button {
            margin-bottom: 10px !important;
        }
        
        .row > div:last-child #add-new-button {
            margin-bottom: 0 !important;
        }
    }

    .row .form-control,
    .row .form-select,
    .row .btn {
        height: 38px !important; /* or your preferred height */
    }
</style>

<!-- JScript -->

<script>
    $(document).ready(function() {
        // Initialize DataTable with export buttons and no search
        var table = $('#announcementTable').DataTable({
            dom: 'Bfrtip',
            buttons: [
                'copy', 'csv', 'excel', 'pdf', 'print'
            ],
            pageLength: 10,
            responsive: true,
            order: [[3, 'desc']], 
            searching: false,
            info: false,
            lengthChange: false
        });

        // Custom search functionality
        $('#searchInput').on('keyup', function() {
            var searchText = $(this).val().toLowerCase();
            
            table.rows().every(function() {
                var rowNode = this.node();
                var title = $(rowNode).find('td.title').text().toLowerCase();
                var content = $(rowNode).find('td.content').text().toLowerCase();
                var date = $(rowNode).find('td.date_published').text().toLowerCase();
                
                if (title.includes(searchText) || content.includes(searchText) || date.includes(searchText)) {
                    $(rowNode).show();
                } else {
                    $(rowNode).hide();
                }
            });
        });

        // Filter functionality
        $('#filterSelect').on('change', function() {
            var filterValue = $(this).val();
            
            table.rows().every(function() {
                var rowNode = this.node();
                var displayButton = $(rowNode).find('button[name="toggle-display"]');
                var isDisplayed = displayButton.hasClass('btn-warning');
                
                if (filterValue === 'all') {
                    $(rowNode).show();
                } else if (filterValue === 'displayed' && isDisplayed) {
                    $(rowNode).show();
                } else if (filterValue === 'hidden' && !isDisplayed) {
                    $(rowNode).show();
                } else {
                    $(rowNode).hide();
                }
            });
        });

        // Sort functionality
        $('#sortSelect').on('change', function() {
            var sortValue = $(this).val();
            
            switch(sortValue) {
                case 'newest':
                    table.order([3, 'desc']).draw(); // Date column
                    break;
                case 'oldest':
                    table.order([3, 'asc']).draw(); // Date column
                    break;
                case 'title':
                    table.order([1, 'asc']).draw(); // Title column
                    break;
            }
        });

        // Reset button functionality (optional)
        $('#resetFilters').on('click', function() {
            $('#searchInput').val('');
            $('#filterSelect').val('all');
            $('#sortSelect').val('newest');
            table.order([3, 'desc']).draw();
            table.rows().every(function() {
                $(this.node()).show();
            });
        });

        // Initialize modal functionality
        const modal = new bootstrap.Modal(document.getElementById('announcementModal'));

        // Handle Add New button click
        $('#add-new-button').click(function() {
            $('#modalTitle').text('Add New Announcement');
            $('#announcement-id').val('');
            $('#announcement-title').val('');
            $('#announcement-content').val('');
            $('#announcementForm').attr('action', '<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>');
            $('#announcementForm').data('action', 'add');
            modal.show();
        });

        // Handle Update button click
        $('.update-button').click(function() {
            const id = $(this).data('id');
            const title = $(this).closest('tr').find('.title').text();
            const content = $(this).closest('tr').find('.content').text();

            $('#modalTitle').text('Update Announcement');
            $('#announcement-id').val(id);
            $('#announcement-title').val(title);
            $('#announcement-content').val(content);
            $('#announcementForm').attr('action', '<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>');
            $('#announcementForm').data('action', 'update');
            modal.show();
        });

        // Handle form submission
        $('#announcementForm').submit(function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const action = $(this).data('action');
            formData.append(action, '1'); // Append either 'add' or 'update'

            $.ajax({
                url: $(this).attr('action'),
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    modal.hide();
                    location.reload();
                },
                error: function(xhr, status, error) {
                    alert('Error: ' + error);
                }
            });
        });

       
    });

    // Sidebar functionality
    const hamburgerMenu = document.getElementById('hamburgerMenu');
    const sidebar = document.getElementById('sidebar');

    // Initially set sidebar to collapsed
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
    
    // Sidebar active link handling
    const sidebarLinks = document.querySelectorAll('.sidebar a');
    sidebarLinks.forEach(link => {
        link.addEventListener('click', function() {
            sidebarLinks.forEach(link => link.classList.remove('active'));
            this.classList.add('active');
        });
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
   

        // Handle Delete button click
        $('.delete-button').click(function() {
            const id = $(this).data('id');
            Swal.fire({
                title: 'Are you sure?',
                text: "This action cannot be undone!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    const form = $('<form>', {
                        'method': 'post',
                        'action': '<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>'
                    }).append($('<input>', {
                        'type': 'hidden',
                        'name': 'delete',
                        'value': '1'
                    })).append($('<input>', {
                        'type': 'hidden',
                        'name': 'announcement-id',
                        'value': id
                    }));
                    $(document.body).append(form);
                    form.submit();
                }
            });
        });

        // Existing SweetAlert for session messages
        <?php if (isset($_SESSION['swal_success'])): ?>
            Swal.fire({
                title: '<?= $_SESSION['swal_success']['title'] ?>',
                text: '<?= $_SESSION['swal_success']['text'] ?>',
                icon: '<?= $_SESSION['swal_success']['icon'] ?>'
            });
            <?php unset($_SESSION['swal_success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['swal_error'])): ?>
            Swal.fire({
                title: '<?= $_SESSION['swal_error']['title'] ?>',
                text: '<?= $_SESSION['swal_error']['text'] ?>',
                icon: '<?= $_SESSION['swal_error']['icon'] ?>'
            });
            <?php unset($_SESSION['swal_error']); ?>
        <?php endif; ?>
</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle Toggle Display button click
        document.querySelectorAll('.toggle-display-button').forEach(button => {
            button.addEventListener('click', function() {
                const form = this.closest('.toggle-display-form');
                const actionText = this.textContent.trim();

                Swal.fire({
                    title: 'Are you sure?',
                    text: `You want to ${actionText.toLowerCase()} this announcement?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: `Yes, ${actionText.toLowerCase()} it!`
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Add a hidden input to indicate toggle action
                        const toggleInput = document.createElement('input');
                        toggleInput.type = 'hidden';
                        toggleInput.name = 'toggle-display';
                        toggleInput.value = '1';
                        form.appendChild(toggleInput);

                        form.submit();
                    }
                });
            });
        });
    });
</script>

</body>
</html>
