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



// SQL query to fetch users from the users table
$sql = "SELECT id, fname, lname FROM users";
$result = mysqli_query($conn, $sql);

// Store the options in an array
$options = [];

if (mysqli_num_rows($result) > 0) {
    // Loop through the results and generate options for the dropdown
    while ($row = mysqli_fetch_assoc($result)) {
        // Safely output the user's first and last name using htmlspecialchars
        $fname = htmlspecialchars($row['fname']);
        $lname = htmlspecialchars($row['lname']);
        $options[] = "<option value='" . $row['id'] . "'>" . $fname . " " . $lname . "</option>";
    }
} else {
    // If no users found, add a "No users" option
    $options[] = "<option value=''>No users found</option>";
}

// Close the database connection

// Combine all options into a single string
$options_string = implode("\n", $options);

if (isset($_POST['user_id'])) {
    $user_id = $_POST['user_id'];

    // Query to get room number for the selected user
    $sql = "SELECT room_id FROM roomassignments WHERE user_id = '$user_id'";
    $result = mysqli_query($conn, $sql);

    if ($row = mysqli_fetch_assoc($result)) {
        $room_number = $row['room_id'];
    } else {
        $room_number = ''; // If no room is assigned
    }
}


// Check if the form is submitted (for both create and update)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Check if it's a payment creation (no payment_id in the form)
    if (!isset($_POST['payment_id'])) {
        // Capture form data for creating a payment
        $user_id = mysqli_real_escape_string($conn, $_POST['user_id']);
        $amount = mysqli_real_escape_string($conn, $_POST['amount']);
        $payment_date = mysqli_real_escape_string($conn, $_POST['payment_date']);
        $status = mysqli_real_escape_string($conn, $_POST['status']);
        $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
        $reference_number = isset($_POST['reference_number']) ? mysqli_real_escape_string($conn, $_POST['reference_number']) : NULL;

        // Prepare SQL query to insert payment data
        $query = "INSERT INTO rentpayment (user_id, amount, payment_date, status, payment_method, reference_number) 
                  VALUES ('$user_id', '$amount', '$payment_date', '$status', '$payment_method', '$reference_number')";

        // Execute the query
        if (mysqli_query($conn, $query)) {
            header('Location: rent_payment.php?message=Payment created successfully');
        } else {
            header('Location: rent_payment.php?error=Failed to create payment');
        }
    }

    // Check if it's a payment update (payment_id is present)
    elseif (isset($_POST['payment_id'])) {
        // Update Payment
        $payment_id = mysqli_real_escape_string($conn, $_POST['payment_id']);
        $amount = mysqli_real_escape_string($conn, $_POST['amount']);
        $status = mysqli_real_escape_string($conn, $_POST['status']);
        $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
        $reference_number = !empty($_POST['reference_number']) ? mysqli_real_escape_string($conn, $_POST['reference_number']) : NULL;

        // Prepare SQL query to update the payment
        $sql = "UPDATE rentpayment 
                SET amount = '$amount', 
                    status = '$status', 
                    payment_method = '$payment_method', 
                    reference_number = " . ($reference_number ? "'$reference_number'" : "NULL") . " 
                WHERE payment_id = '$payment_id'";

        // Execute the query
        if (mysqli_query($conn, $sql)) {
            header('Location: rent_payment.php?message=Payment updated successfully');
        } else {
            header('Location: rent_payment.php?error=Failed to update payment');
        }
    }
}
// Check if the delete request is set
if (isset($_GET['delete_payment_id'])) {
    $payment_id = mysqli_real_escape_string($conn, $_GET['delete_payment_id']);

    // Prepare the SQL query to delete the payment
    $query = "DELETE FROM rentpayment WHERE payment_id = '$payment_id'";

    // Execute the query
    if (mysqli_query($conn, $query)) {
        // Redirect to the same page with a success message
        header('Location: rent_payment.php?message=Payment deleted successfully');
    } else {
        // Redirect with an error message if deletion fails
        header('Location: rent_payment.php?error=Failed to delete payment');
    }

    exit();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="Css_Admin/admin-manageuser.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">

<!-- DataTables Buttons Plugin CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.3/css/buttons.dataTables.min.css">

<!-- DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">

<!-- DataTables Buttons Plugin CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.3/css/buttons.dataTables.min.css">

<!-- jQuery (required by DataTables) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>

<!-- DataTables Buttons Plugin JS -->
<script src="https://cdn.datatables.net/buttons/2.2.3/js/dataTables.buttons.min.js"></script>

<!-- Buttons JS (for exporting functionalities) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/vfs_fonts/2.0.1/vfs_fonts.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.print.min.js"></script>


</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="menu" id="hamburgerMenu">
            <i class="fas fa-bars"></i>
        </div>
        <div class="sidebar-nav">
            <a href="dashboard.php" class="nav-link"><i class="fas fa-user-cog"></i> <span>Profile</span></a>
            <a href="manageuser.php" class="nav-link"><i class="fas fa-users"></i> <span>Manage User</span></a>
            <a href="admin-room.php" class="nav-link" id="roomManagerDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="fas fa-building"></i> <span>Room Manager</span>
            <a href="admin-visitor_log.php" class="nav-link"><i class="fas fa-address-book"></i> <span>Log Visitor</span></a>
            <a href="admin-monitoring.php" class="nav-link"><i class="fas fa-eye"></i> <span>Monitoring</span></a>

            <a href="admin-chat.php" class="nav-link"><i class="fas fa-comments"></i> <span>Group Chat</span></a>
            <a href="rent_payment.php" class="nav-link"><i class="fas fa-money-bill-alt"></i> <span>Rent Payment</span></a>
            <a href="activity-logs.php" class="nav-link active"><i class="fas fa-clipboard-list"></i> <span>Activity Logs</span></a>

        </div>
        <div class="logout">
            <a href="../config/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
        </div>
    </div>

    <!-- Top bar -->
    <div class="topbar">
        <h2>Activity Logs</h2>
    </div>

    <!-- Main content -->
    <div class="main-content">
    <div class="container">

<div class="d-flex justify-content-start">
       
    <div class="container mt-1">
    <div class="row mb-4">
    <div class="col-12 col-md-6">
        <input type="text" id="searchInput" class="form-control custom-input-small" placeholder="Search for room details..." onkeyup="filterTable()">
    </div>
    
    <div class="col-6 col-md-2">
        <select id="filterSelect" class="form-select" onchange="filterTable()">
            <option value="all" selected>Filter by</option>
            <option value="room_number">Room Number</option>
            <option value="amount">Amount</option>
            <option value="status">Status</option>
        </select>
    </div>

    <div class="col-6 col-md-2">
    <select name="sort" class="form-select" id="sort" onchange="applySort()">
            <option value="" selected>Select Sort</option>
        <option value="room_number_asc">Room Number (Low to High)</option>
        <option value="room_number_desc">Room Number (High to Low)</option>
        <option value="amount_asc">Amount (Low to High)</option>
        <option value="amount_desc">Amount (High to Low)</option>
        <option value="payment_date_asc">Payment Date (Oldest to Newest)</option>
        <option value="payment_date_desc">Payment Date (Newest to Oldest)</option>
    </select>
</div>

    <div class="col-6 col-md-2">
        <!-- Button to trigger modal -->
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPaymentModal">
            Add Rent Payment
        </button>
    </div>
</div>


<!-- Rent Payment Table -->
<table class="table table-bordered" id="paymentTable">
    <thead class="table-light">
        <tr>
            <th>No</th>
            <th>Resident Name</th>
            <th>Room Number</th>
            <th>Amount</th>
            <th>Payment Date</th>
            <th>Status</th>
            <th>Payment Method</th>
            <th>Reference Number (if online)</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody>
        <?php
        // Fetch rent payment data
        $sql = "SELECT rp.payment_id, u.fname, u.lname, r.room_number, rp.amount, rp.payment_date, rp.status, rp.payment_method, rp.reference_number
                FROM rentpayment rp
                INNER JOIN users u ON rp.user_id = u.id
                INNER JOIN roomassignments ra ON rp.user_id = ra.user_id
                INNER JOIN rooms r ON ra.room_id = r.room_id"; 

        $result = mysqli_query($conn, $sql);
        $payments = [];

        if (mysqli_num_rows($result) > 0) {
            $no = 1; // Row number counter
            while ($row = mysqli_fetch_assoc($result)) {
                $payments[] = $row; // Store the data in an array for easier processing in JS
                echo "<tr>";
                echo "<td>" . $no++ . "</td>";
                echo "<td>" . htmlspecialchars($row['fname']) . " " . htmlspecialchars($row['lname']) . "</td>";
                echo "<td>" . htmlspecialchars($row['room_number']) . "</td>";
                echo "<td>" . number_format($row['amount'], 2) . "</td>";
                echo "<td>" . $row['payment_date'] . "</td>";
                echo "<td>" . ucfirst($row['status']) . "</td>";
                echo "<td>" . ucfirst($row['payment_method']) . "</td>";
                echo $row['payment_method'] == 'online banking' ? "<td>" . htmlspecialchars($row['reference_number']) . "</td>" : "<td>-</td>";
                echo "<td>
                        <button type='button' class='btn btn-primary btn-sm edit-btn mb-1' 
                            data-bs-toggle='modal' 
                            data-bs-target='#editPaymentModal' 
                            data-id='" . htmlspecialchars($row['payment_id']) . "' 
                            data-amount='" . htmlspecialchars($row['amount']) . "' 
                            data-status='" . htmlspecialchars($row['status']) . "' 
                            data-method='" . htmlspecialchars($row['payment_method']) . "' 
                            data-reference='" . htmlspecialchars($row['reference_number']) . "'>
                            Edit
                        </button>
                        <form method='GET' action='rent_payment.php' style='display:inline;' onsubmit='return confirmDelete()'>
                            <input type='hidden' name='delete_payment_id' value='" . htmlspecialchars($row['payment_id']) . "' />
                            <button type='submit' class='btn btn-danger btn-sm'>Delete</button>
                        </form>
                    </td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='9'>No payments found.</td></tr>";
        }
        ?>
    </tbody>
</table>

<script>
    // Confirmation before deletion
    function confirmDelete() {
        return confirm("Are you sure you want to delete this payment?");
    }
</script>




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
        </div>
    </div>
<!-- Modal for creating rent payment -->
<div class="modal fade" id="createPaymentModal" tabindex="-1" aria-labelledby="createPaymentModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="createPaymentModalLabel">Create Rent Payment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form method="POST" action="rent_payment.php">
          <!-- User ID: Select dropdown with all users -->
          <div class="mb-3">
            <label for="user_id" class="form-label">Resident Name</label>
            <select name="user_id" id="user_id" class="form-control" required onchange="updateRoomNumber()">
              <option value="">Select Resident</option>
              <?php echo $options_string; ?>
            </select>
          </div>

          <!-- Amount -->
          <div class="mb-3">
            <label for="amount" class="form-label">Amount</label>
            <input type="number" step="0.01" name="amount" id="amount" class="form-control" required>
          </div>

          <!-- Payment Date -->
          <div class="mb-3">
            <label for="payment_date" class="form-label">Payment Date</label>
            <input type="date" name="payment_date" id="payment_date" class="form-control" required>
          </div>

          <!-- Status -->
          <div class="mb-3">
            <label for="status" class="form-label">Status</label>
            <select name="status" id="status" class="form-control" required>
              <option value="pending">Pending</option>
              <option value="paid">Paid</option>
              <option value="overdue">Overdue</option>
            </select>
          </div>

          <!-- Payment Method -->
          <div class="mb-3">
            <label for="payment_method" class="form-label">Payment Method</label>
            <select name="payment_method" id="payment_method" class="form-control" required onchange="toggleReferenceNumber()">
            <option value="">Select Payment Method</option>

              <option value="cash">Cash</option>
              <option value="online banking">Online Banking</option>
            </select>
          </div>

          <!-- Reference Number (hidden initially) -->
          <div class="mb-3" id="reference_number_div" style="display: none;">
            <label for="reference_number" class="form-label">Reference Number</label>
            <input type="text" name="reference_number" id="reference_number" class="form-control">
          </div>

          <!-- Submit Button -->
          <button type="submit" class="btn btn-success">Create Payment</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Edit Payment Modal -->
<div class="modal fade" id="editPaymentModal" tabindex="-1" aria-labelledby="editPaymentModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="rent_payment.php">
                <div class="modal-header">
                    <h5 class="modal-title" id="editPaymentModalLabel">Edit Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- Hidden Payment ID -->
                    <input type="hidden" id="payment_id" name="payment_id">

                    <!-- Amount -->
                    <div class="mb-3">
                        <label for="edit_amount" class="form-label">Amount</label>
                        <input type="number" step="0.01" class="form-control" id="edit_amount" name="amount" required>
                    </div>

                    <!-- Status -->
                    <div class="mb-3">
                        <label for="edit_status" class="form-label">Status</label>
                        <select class="form-select" id="edit_status" name="status" required>
                            <option value="paid">Paid</option>
                            <option value="pending">Pending</option>
                            <option value="overdue">Overdue</option>
                        </select>
                    </div>

                    <!-- Payment Method -->
                    <div class="mb-3">
                        <label for="edit_payment_method" class="form-label">Payment Method</label>
                        <select class="form-select" id="edit_payment_method" name="payment_method" required onchange="toggleReferenceField('edit')">
                            <option value="cash">Cash</option>
                            <option value="online banking">Online Banking</option>
                        </select>
                    </div>

                    <!-- Reference Number -->
                    <div class="mb-3" id="edit_reference_div" style="display: none;">
                        <label for="edit_reference_number" class="form-label">Reference Number</label>
                        <input type="text" class="form-control" id="edit_reference_number" name="reference_number">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- JavaScript to show/hide reference number field -->
<script>
    function applySort() {
    var sortValue = document.getElementById("sort").value;
    
    // If a sort option is selected, reload the page with the sort parameter in the URL
    if (sortValue) {
        window.location.href = window.location.pathname + "?sort=" + sortValue;
    } else {
        // If no sort option is selected, reload the page without the sort parameter
        window.location.href = window.location.pathname;
    }
}

// Bind data to the Edit Payment Modal
document.querySelectorAll('.edit-btn').forEach(button => {
    button.addEventListener('click', function () {
        const paymentId = this.dataset.id;
        const amount = this.dataset.amount;
        const status = this.dataset.status;
        const method = this.dataset.method;
        const reference = this.dataset.reference;

        // Set modal fields
        document.getElementById('payment_id').value = paymentId;
        document.getElementById('edit_amount').value = amount;
        document.getElementById('edit_status').value = status;
        document.getElementById('edit_payment_method').value = method;

        // Handle reference number visibility
        if (method === 'online banking' || method === 'credit card') {
            document.getElementById('edit_reference_div').style.display = 'block';
            document.getElementById('edit_reference_number').value = reference;
        } else {
            document.getElementById('edit_reference_div').style.display = 'none';
            document.getElementById('edit_reference_number').value = '';
        }
    });
});

// Toggle Reference Number Field (Reused Functionality)
function toggleReferenceField(prefix) {
    const paymentMethod = document.getElementById(`${prefix}_payment_method`).value;
    const referenceDiv = document.getElementById(`${prefix}_reference_div`);
    const referenceField = document.getElementById(`${prefix}_reference_number`);

    if (paymentMethod === 'online banking' || paymentMethod === 'credit card') {
        referenceDiv.style.display = 'block';
        referenceField.required = true;
    } else {
        referenceDiv.style.display = 'none';
        referenceField.value = '';
        referenceField.required = false;
    }
}

// Store data from PHP to JavaScript
var payments = <?php echo json_encode($payments); ?>;

function filterTable() {
    let searchInput = document.getElementById("searchInput").value.toLowerCase();
    let filterSelect = document.getElementById("filterSelect").value;
    let table = document.getElementById("paymentTable");
    let rows = table.getElementsByTagName("tr");
    let colIndex = -1;

    // Determine the column to filter by
    switch (filterSelect) {
        case "room_number":
            colIndex = 2;
            break;
        case "amount":
            colIndex = 3;
            break;
        case "status":
            colIndex = 5;
            break;
        default:
            colIndex = -1;
            break;
    }

    for (let i = 1; i < rows.length; i++) {
        let cells = rows[i].getElementsByTagName("td");
        let match = false;

        // Search across all columns if no filter is selected
        if (filterSelect === "all") {
            for (let j = 0; j < cells.length; j++) {
                if (cells[j] && cells[j].innerText.toLowerCase().includes(searchInput)) {
                    match = true;
                    break;
                }
            }
        } else if (cells[colIndex] && cells[colIndex].innerText.toLowerCase().includes(searchInput)) {
            match = true;
        }

        rows[i].style.display = match ? "" : "none";
    }
}


// Apply sorting based on selected option
function applySort() {
    const sortValue = document.getElementById('sort').value;
    const table = document.getElementById('paymentTable');
    const rows = Array.from(table.querySelectorAll('tbody tr'));

    // Sort rows based on selected option
    rows.sort((rowA, rowB) => {
        let cellA, cellB;

        switch (sortValue) {
            case 'room_number_asc':
                cellA = rowA.querySelector('td:nth-child(3)').textContent.trim(); // Room Number
                cellB = rowB.querySelector('td:nth-child(3)').textContent.trim();
                return cellA.localeCompare(cellB); // Ascending order
            case 'room_number_desc':
                cellA = rowA.querySelector('td:nth-child(3)').textContent.trim();
                cellB = rowB.querySelector('td:nth-child(3)').textContent.trim();
                return cellB.localeCompare(cellA); // Descending order
            case 'amount_asc':
                cellA = parseFloat(rowA.querySelector('td:nth-child(4)').textContent.trim().replace(/[^\d.-]/g, ''));
                cellB = parseFloat(rowB.querySelector('td:nth-child(4)').textContent.trim().replace(/[^\d.-]/g, ''));
                return cellA - cellB; // Ascending order
            case 'amount_desc':
                cellA = parseFloat(rowA.querySelector('td:nth-child(4)').textContent.trim().replace(/[^\d.-]/g, ''));
                cellB = parseFloat(rowB.querySelector('td:nth-child(4)').textContent.trim().replace(/[^\d.-]/g, ''));
                return cellB - cellA; // Descending order
            case 'payment_date_asc':
                cellA = new Date(rowA.querySelector('td:nth-child(5)').textContent.trim());
                cellB = new Date(rowB.querySelector('td:nth-child(5)').textContent.trim());
                return cellA - cellB; // Ascending order (Oldest to Newest)
            case 'payment_date_desc':
                cellA = new Date(rowA.querySelector('td:nth-child(5)').textContent.trim());
                cellB = new Date(rowB.querySelector('td:nth-child(5)').textContent.trim());
                return cellB - cellA; // Descending order (Newest to Oldest)
            default:
                return 0;
        }
    });

    // Append sorted rows back to the table body
    rows.forEach(row => table.querySelector('tbody').appendChild(row));
}


  function toggleReferenceNumber() {
    var paymentMethod = document.getElementById('payment_method').value;
    var referenceNumberDiv = document.getElementById('reference_number_div');
    
    // Show reference number input if 'online banking' is selected
    if (paymentMethod === 'online banking') {
      referenceNumberDiv.style.display = 'block';
    } else {
      referenceNumberDiv.style.display = 'none';
    }
  }
</script>

<script>
// Function to update the room number when a user is selected
function updateRoomNumber() {
  var userId = document.getElementById('user_id').value;
  
  if (userId) {
    // Make a simple form submission to update the room number for the selected user
    // For the sake of simplicity, we are using PHP to populate the room number on page reload
    document.getElementById('assignment_id').value = '<?php echo isset($room_number) ? $room_number : ''; ?>';
  } else {
    document.getElementById('assignment_id').value = '';
  }
}
</script>





    

   
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>


    <!-- Bootstrap JS and Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Hamburger Menu Script -->
    <script>

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

        document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        const filterSelect = document.getElementById('filterSelect');
        const tableBody = document.getElementById('room-table-body');
        const rows = tableBody.getElementsByTagName('tr');

        // Search Input Event Listener
        searchInput.addEventListener('input', function() {
            filterTable();
        });

        // Filter Dropdown Event Listener
        filterSelect.addEventListener('change', function() {
            filterTable();
        });

        // Function to Filter the Table Rows
        function filterTable() {
            const searchText = searchInput.value.toLowerCase();
            const filterBy = filterSelect.value;

            for (let i = 0; i < rows.length; i++) {
                let row = rows[i];
                let cells = row.getElementsByTagName('td');
                let match = false;

                if (cells.length > 0) {
                    let roomNumber = cells[1].textContent.toLowerCase();
                    let capacity = cells[3].textContent.toLowerCase();
                    let status = cells[4].textContent.toLowerCase();

                    switch (filterBy) {
                        case 'room_number':
                            match = roomNumber.includes(searchText);
                            break;
                        case 'capacity':
                            match = capacity.includes(searchText);
                            break;
                        case 'status':
                            match = status.includes(searchText);
                            break;
                        default:
                            match = roomNumber.includes(searchText) || 
                                    capacity.includes(searchText) || 
                                    status.includes(searchText);
                            break;
                    }

                    // Show or hide row based on match
                    row.style.display = match ? '' : 'none';
                }
            }
        }
    });
    </script>
</body>
</html>
