<?php
session_start();

// Redirect to login if not logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: admin-login.php");
    exit;
}

include '../config/config.php'; // Ensure this is correct

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Capture search and filter values
$search = isset($_GET['search']) ? $_GET['search'] : ''; 
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all'; 

// Base SQL query
$sql = "SELECT * FROM rooms WHERE 1";

// Apply search conditions
if (!empty($search)) {
    $search = $conn->real_escape_string($search); // Prevent SQL injection

    switch ($filter) {
        case 'room_number':
            $sql .= " AND room_number LIKE '%$search%'";
            break;
        case 'capacity':
            $sql .= " AND capacity LIKE '%$search%'";
            break;
        case 'status':
            $sql .= " AND status LIKE '%$search%'";
            break;
        default:
            $sql .= " AND (room_number LIKE '%$search%' OR capacity LIKE '%$search%' OR status LIKE '%$search%')";
            break;
    }
}

if (isset($_GET['delete_attendance_id'])) {
    // Get the ID of the attendance record to be deleted
    $delete_id = $_GET['delete_attendance_id'];

    // Validate if the ID is numeric (optional but recommended)
    if (is_numeric($delete_id)) {
        // Prepare the DELETE SQL query
        $sql = "DELETE FROM presencemonitoring WHERE attendance_id = ?";

        // Prepare the statement
        if ($stmt = $conn->prepare($sql)) {
            // Bind the parameter to the query
            $stmt->bind_param("i", $delete_id);

            // Execute the query
            if ($stmt->execute()) {
                // Success message
                echo "<script>alert('Record deleted successfully'); window.location.href='admin-monitoring.php';</script>";
            } else {
                // Error message on failure
                echo "<script>alert('Error deleting record');</script>";
            }

            // Close the statement
            $stmt->close();
        } else {
            // Error in preparing the query
            echo "<script>alert('Error preparing the query');</script>";
        }
    } else {
        echo "<script>alert('Invalid ID');</script>";
    }
}
$query = "
SELECT 
    pm.attendance_id AS ID,
    CONCAT(u.fname, ' ', u.lname) AS Resident_Name,
    r.room_number AS Room_Number,
    DATE_FORMAT(pm.check_in, '%Y-%m-%d %h:%i %p') AS Check_In_Time,  -- Format check_in with AM/PM
    DATE_FORMAT(pm.check_out, '%Y-%m-%d %h:%i %p') AS Check_Out_Time, -- Format check_out with AM/PM
    pm.date AS Date
FROM 
    presencemonitoring pm
JOIN 
    users u ON pm.user_id = u.id
JOIN 
    roomassignments ra ON ra.user_id = u.id
JOIN 
    rooms r ON ra.room_id = r.room_id
ORDER BY 
    pm.check_in DESC;  -- Sorting by check_in in descending order
";


$result = $conn->query($query);

$conn->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Presence Monitoring</title>
    <link rel="icon" href="../img-icon/logo.png" type="image/png">

    <link rel="stylesheet" href="../Admin/Css_Admin/style.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.1/css/jquery.dataTables.min.css">

<!-- Bootstrap CSS -->
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>

<!-- Bootstrap JS -->
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<!-- DataTables Buttons CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.3/css/buttons.dataTables.min.css">

<!-- DataTables Buttons JS -->
<script src="https://cdn.datatables.net/buttons/2.2.3/js/dataTables.buttons.min.js"></script>

<!-- JSZip for Excel Export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>

<!-- pdfMake for PDF Export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>

<!-- DataTables Buttons for exporting -->
<script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.html5.min.js"></script>

<script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.print.min.js"></script>
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

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="menu" id="hamburgerMenu">
            <i class="fas fa-bars"></i>
        </div>
        <div class="sidebar-nav">
        <a href="dashboard.php" class="nav-link"><i class="fas fa-home"></i> <span>Home</span></a>
            <a href="manageuser.php" class="nav-link"><i class="fas fa-users"></i> <span>Manage User</span></a>
            <a href="admin-room.php" class="nav-link" ><i class="fas fa-building"></i> <span>Room Management</span> </a>
            <a href="admin-visitor_log.php" class="nav-link"><i class="fas fa-address-book"></i> <span>Log Visitor</span></a>
            <a href="admin-monitoring.php" class="nav-link active"><i class="fas fa-eye"></i> <span>Presence Monitoring</span></a>

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

    <!-- Top bar -->
    <div class="topbar">
        <h2>Presence Monitoring</h2>
    </div>

    <!-- Main content -->
    <div class="main-content">
    <div class="container">

    <div class="container mt-1">
    <!-- Search and Filter Section -->
    <!-- Room Table Filter Section -->
<div class="row mb-1">
    <!-- Search Input -->
    <div class="col-12 col-md-6">
        <form method="GET" action="" class="search-form">
            <div class="input-group">
                <input type="text" id="searchInput" name="search" class="form-control custom-input-small" 
                    placeholder="Search for residents, rooms, etc..." 
                    value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                <span class="input-group-text">
                    <i class="fas fa-search"></i>
                </span>
            </div>
        </form>
    </div>

    <!-- Filter Dropdown -->
    <div class="col-6 col-md-2 mt-2">
        <select name="filter" id="filterSelect" class="form-select" onchange="this.form.submit()">
            <option value="all">Filter by</option>
            <option value="1">Resident Name</option>
            <option value="2">Room</option>
            <option value="3">Check-In Time</option>
            <option value="4">Check-Out Time</option>
        </select>
    </div>

    <!-- Sort Dropdown -->
    <div class="col-6 col-md-2 mt-2">
        <select name="sort" id="sortSelect" class="form-select">
            <option value="">Sort by</option>
            <option value="resident_asc">Resident Name (A-Z)</option>
            <option value="resident_desc">Resident Name (Z-A)</option>
            <option value="room_asc">Room (Low to High)</option>
            <option value="room_desc">Room (High to Low)</option>
            <option value="checkin_asc">Check-In (Earliest)</option>
            <option value="checkin_desc">Check-In (Latest)</option>
            <option value="checkout_asc">Check-Out (Earliest)</option>
            <option value="checkout_desc">Check-Out (Latest)</option>
        </select>
    </div>
</div>

<!-- Room Table -->
<div class="table-responsive">
    <table class="table table-bordered" id="monitoring">
        <thead class="table-light">
            <tr>
                <th>No.</th>
                <th>Resident Name</th>
                <th>Room</th>
                <th>Check-In Time</th>
                <th>Check-Out Time</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if ($result->num_rows > 0) {
                $rowNumber = 1;  // Initialize counter
                while ($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . $rowNumber++ . "</td>";  // Display and increment row number
                    echo "<td>" . htmlspecialchars($row["Resident_Name"]) . "</td>";
                    echo "<td>" . htmlspecialchars($row["Room_Number"]) . "</td>";
                    echo "<td>" . htmlspecialchars($row["Check_In_Time"]) . "</td>";
                    echo "<td>" . htmlspecialchars($row["Check_Out_Time"]) . "</td>";
                   
                    echo "</tr>";
                }
            }
            ?>
        </tbody>
    </table>
</div>



<!-- Pagination Controls -->
<div id="pagination">
    <button id="prevPage" onclick="prevPage()" disabled>Previous</button>
    <span id="pageIndicator">Page 1</span>
    <button id="nextPage" onclick="nextPage()">Next</button>
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


           <!-- JavaScript Libraries -->
           <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>


<!-- Include jQuery and Bootstrap JS (required for dropdown) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Bootstrap JS and Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Hamburger Menu Script -->
    <script>
 // Initialize DataTable
 function initializeDataTable() {
    return $('#monitoring').DataTable({
        dom: 'Bfrtip',
        buttons: [
            {
                extend: 'copy',
                exportOptions: { columns: ':not(:last-child)' },
                title: 'Presence Monitoring - ' + getFormattedDate()
            },
            {
                extend: 'csv',
                exportOptions: { columns: ':not(:last-child)' },
                title: 'Presence Monitoring - ' + getFormattedDate()
            },
            {
                extend: 'excel',
                exportOptions: { columns: ':not(:last-child)' },
                title: 'Presence Monitoring - ' + getFormattedDate()
            },
            {
                extend: 'pdf',
                exportOptions: { columns: ':not(:last-child)' },
                title: 'Presence Monitoring - ' + getFormattedDate()
            },
            {
                extend: 'print',
                title: '',
                exportOptions: { columns: ':not(:last-child)' },
                customize: customizePrintView
            }
        ],
        pageLength: 10,
        ordering: true,
        searching: false,
        lengthChange: false,
        info: false,
        responsive: true,
        paging: false,
        order: [[3, 'desc']] // Sort by check-in time by default
    });
}

// Custom print view
function customizePrintView(win) {
    var doc = win.document;
    
    $(doc.body)
        .css({
            'font-family': 'Arial, sans-serif',
            'font-size': '12pt'
        })
        .prepend('<h1 style="text-align:center; font-size: 20pt; font-weight: bold;">Presence Monitoring Report</h1>')
        .prepend('<p style="text-align:center; font-size: 12pt; margin-bottom: 20px;">' + getFormattedDate() + '</p><hr>');

    $(doc.body).find('table')
        .addClass('display')
        .css({
            width: '100%',
            borderCollapse: 'collapse',
            marginTop: '20px',
            border: '1px solid #ddd'
        });

    $(doc.body).find('table th, table td').css({
        border: '1px solid #ddd',
        padding: '8px',
        textAlign: 'left'
    });
}

// Separate search functionality
$(document).ready(function() {
    $('#searchInput').on('input', function() {
        const searchTerm = $(this).val().toLowerCase();
        const filterValue = $('#filterSelect').val();
        const table = $('#monitoring tbody');

        table.find('tr').each(function() {
            const row = $(this);
            let searchText;

            // Get text based on filter selection
            switch(filterValue) {
                case '1': // Resident Name
                    searchText = row.find('td:nth-child(2)').text().toLowerCase();
                    break;
                case '2': // Room
                    searchText = row.find('td:nth-child(3)').text().toLowerCase();
                    break;
                case '3': // Check-In Time
                    searchText = row.find('td:nth-child(4)').text().toLowerCase();
                    break;
                case '4': // Check-Out Time
                    searchText = row.find('td:nth-child(5)').text().toLowerCase();
                    break;
                default: // Search all columns except Actions
                    searchText = row.find('td:not(:last-child)').text().toLowerCase();
                    break;
            }

            // Show/hide row based on search match
            if (searchText.includes(searchTerm)) {
                row.show();
            } else {
                row.hide();
            }
        });
    });

    // Update search when filter changes
    $('#filterSelect').on('change', function() {
        $('#searchInput').trigger('input');
    });
});

// Separate sort functionality
$('#sortSelect').on('change', function() {
    const value = $(this).val();
    const tbody = $('#monitoring tbody');
    const rows = tbody.find('tr').toArray();

    rows.sort((a, b) => {
        let aVal, bVal;
        
        switch(value) {
            case 'resident_asc':
            case 'resident_desc':
                aVal = $(a).find('td:eq(1)').text();
                bVal = $(b).find('td:eq(1)').text();
                break;
            case 'room_asc':
            case 'room_desc':
                aVal = $(a).find('td:eq(2)').text();
                bVal = $(b).find('td:eq(2)').text();
                break;
            case 'checkin_asc':
            case 'checkin_desc':
                aVal = new Date($(a).find('td:eq(3)').text());
                bVal = new Date($(b).find('td:eq(3)').text());
                break;
            case 'checkout_asc':
            case 'checkout_desc':
                aVal = new Date($(a).find('td:eq(4)').text());
                bVal = new Date($(b).find('td:eq(4)').text());
                break;
            default:
                return 0;
        }

        let comparison = 0;
        if (aVal > bVal) comparison = 1;
        if (aVal < bVal) comparison = -1;

        // Reverse for descending order
        if (value.endsWith('desc')) comparison *= -1;

        return comparison;
    });

    tbody.empty();
    tbody.append(rows);
});

// Initialize everything when document is ready
$(document).ready(function() {
    const table = initializeDataTable();
});

function confirmDelete() {
    return confirm("Are you sure you want to delete this record?");
}

function getFormattedDate() {
    const now = new Date();
    const date = now.getFullYear() + '-' + 
                 String(now.getMonth() + 1).padStart(2, '0') + '-' + 
                 String(now.getDate()).padStart(2, '0');
    const time = String(now.getHours()).padStart(2, '0') + ':' + 
                 String(now.getMinutes()).padStart(2, '0') + ':' + 
                 String(now.getSeconds()).padStart(2, '0');
    return `${date} ${time}`;
}

     // JavaScript for client-side pagination
     const rowsPerPage = 10; // Limit to 10 rows per page
    let currentPage = 1;
    const rows = document.querySelectorAll('#room-table-body tr');
    const totalPages = Math.ceil(rows.length / rowsPerPage);

    // Show the initial set of rows
    showPage(currentPage);

    function showPage(page) {
        const start = (page - 1) * rowsPerPage;
        const end = start + rowsPerPage;
        rows.forEach((row, index) => {
            row.style.display = index >= start && index < end ? '' : 'none';
        });
        document.getElementById('pageIndicator').innerText = `Page ${page}`;
        document.getElementById('prevPage').disabled = page === 1;
        document.getElementById('nextPage').disabled = page === totalPages;

        // Update page indicator
        document.getElementById('pageIndicator').textContent = `Page ${currentPage} of ${totalPages}`;
    }

    function nextPage() {
        if (currentPage < totalPages) {
            currentPage++;
            showPage(currentPage);
        }
    }

    function prevPage() {
        if (currentPage > 1) {
            currentPage--;
            showPage(currentPage);
        }
    }
        const hamburgerMenu = document.getElementById('hamburgerMenu');
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.add('collapsed');

        hamburgerMenu.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            const icon = hamburgerMenu.querySelector('i');
            icon.classList.toggle('fa-bars');
            icon.classList.toggle('fa-times');
        });

    
    </script>
</body>
</html>
