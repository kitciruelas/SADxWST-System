<?php
session_start();

// Redirect to login if not logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: admin-login.php");
    exit;
}

include '../config/config.php'; // Ensure this is correct

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// Initialize variable for error message
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_user'])) {
    // Collect form data
    $fname = $_POST['Fname'];
    $lname = $_POST['Lname'];
    $mi = $_POST['MI'];
    $age = $_POST['Age'];
    $address = $_POST['Address'];
    $contact = $_POST['contact'];
    $sex = $_POST['Sex'];
    $role = $_POST['Role'];  // General User or Staff
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT); // Hash password

    // Server-side validation
    if (empty($fname) || empty($lname) || empty($age) || empty($address) || empty($contact) || empty($sex) || empty($email) || empty($_POST['password'])) {
        $errorMessage = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Invalid email format.';
    } elseif (strlen($_POST['password']) < 6) {
        $errorMessage = 'Password must be at least 6 characters long.';
    } else {
        // Determine the SQL insert statement based on the role
        if ($role === 'Staff') {
            // Prepare SQL for inserting into staff table
            $sql = "INSERT INTO staff (fname, lname, mi, age, address, contact, sex, email, password) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        } else {
            // Prepare SQL for inserting into users table (General User)
            $sql = "INSERT INTO users (fname, lname, mi, age, address, contact, sex, email, password) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        }

        // Prepare the statement
        if ($stmt = $conn->prepare($sql)) {
            // Bind the parameters for both users or staff tables
            $stmt->bind_param("sssisssss", $fname, $lname, $mi, $age, $address, $contact, $sex, $email, $password);

            // Execute the statement
            if ($stmt->execute()) {
                // Directly display a success message using a JavaScript alert
                if ($role === 'Staff') {
                    echo "<script>alert('Staff member added successfully!');</script>";
                } else {
                    echo "<script>alert('General user added successfully!');</script>";
                }
            } else {
                $errorMessage = "Error inserting user: " . $stmt->error;
            }

            // Close the statement
            $stmt->close();
        } else {
            $errorMessage = "Error preparing the statement: " . $conn->error;
        }
    }

    // Display error message if any
    if (!empty($errorMessage)) {
        echo "<script>alert('$errorMessage');</script>";
    }
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

    $stmt = $conn->prepare("UPDATE users SET Fname = ?, Lname = ?, MI = ?, Age = ?, Address = ?, contact = ?, Sex = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("sssisssi", $Fname, $Lname, $MI, $Age, $Address, $contact, $Sex, $userId);
        if ($stmt->execute()) {
            echo "<script>alert('User updated successfully!'); closeEditModal();</script>";
        } else {
            echo "<script>alert('Error: " . $stmt->error . "');</script>";
        }
        $stmt->close();
    } else {
        echo "<script>alert('Error preparing statement: " . $conn->error . "');</script>";
    }
}
 
// Handle delete user request
if (isset($_POST['delete_user'])) {
    $userId = intval($_POST['user_id']);
    
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");

    if ($stmt) {
        $stmt->bind_param("i", $userId);
        if ($stmt->execute()) {
            echo "<script>alert('User deleted successfully!');</script>";
        } else {
            echo "<script>alert('Error: " . $stmt->error . "');</script>";
        }
        $stmt->close();
    } else {
        echo "<script>alert('Error preparing statement: " . $conn->error . "');</script>";
    }
}


// Handle the filter and search from the query string
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';  // Prevent SQL injection

// Pagination setup
$rowsPerPage = 10; // Show 10 records per page
$currentPage = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1; // Default to page 1
$startRow = ($currentPage - 1) * $rowsPerPage; // Calculate starting row

// Base SQL query to count total rows
$totalRowsQuery = "SELECT COUNT(*) AS total FROM (";  // Counting total rows

// Adjust query based on the filter selection
if ($filter === 'General User') {
    $totalRowsQuery .= "SELECT id, Fname, Lname, MI, Age, Address, contact, Sex, 'General User' AS Role FROM users";
} elseif ($filter === 'Staff') {
    $totalRowsQuery .= "SELECT id, Fname, Lname, MI, Age, Address, contact, Sex, 'Staff' AS Role FROM staff";
} else {
    // 'all' or unspecified - show both General User and Staff
    $totalRowsQuery .= "
        SELECT id, Fname, Lname, MI, Age, Address, contact, Sex, 'General User' AS Role FROM users
        UNION ALL
        SELECT id, Fname, Lname, MI, Age, Address, contact, Sex, 'Staff' AS Role FROM staff";
}

// Apply the search condition only if the search is not empty
if (!empty($search)) {
    $totalRowsQuery .= ") as combined_data WHERE (Fname LIKE '%$search%' OR Lname LIKE '%$search%' OR Role LIKE '%$search%')";
} else {
    $totalRowsQuery .= ") as combined_data";  // No search, show all filtered results
}

$totalRowsResult = $conn->query($totalRowsQuery);

// Handle query failure
if ($totalRowsResult === false) {
    die("Error: " . $conn->error);
}

$totalRows = $totalRowsResult->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $rowsPerPage);  // Calculate the total number of pages

// SQL query to fetch data with pagination and search filter
$sql = "
    SELECT id, Fname, Lname, MI, Age, Address, contact, Sex, Role FROM (
";

if ($filter === 'General User') {
    $sql .= "SELECT id, Fname, Lname, MI, Age, Address, contact, Sex, 'General User' AS Role FROM users";
} elseif ($filter === 'Staff') {
    $sql .= "SELECT id, Fname, Lname, MI, Age, Address, contact, Sex, 'Staff' AS Role FROM staff";
} else {
    $sql .= "
        SELECT id, Fname, Lname, MI, Age, Address, contact, Sex, 'General User' AS Role FROM users
        UNION ALL
        SELECT id, Fname, Lname, MI, Age, Address, contact, Sex, 'Staff' AS Role FROM staff";
}

// Apply the search condition only if the search is not empty
if (!empty($search)) {
    $sql .= ") as combined_data WHERE (Fname LIKE '%$search%' OR Lname LIKE '%$search%' OR Role LIKE '%$search%')";
} else {
    $sql .= ") as combined_data";  // No search, show all filtered results
}

$sql .= " ORDER BY Lname ASC LIMIT $startRow, $rowsPerPage";

$result = $conn->query($sql);

// Handle query failure
if ($result === false) {
    die("Error: " . $conn->error);
}


$result = $conn->query($sql);
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="Css_Admin/adminmanageuser.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">



</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="menu" id="hamburgerMenu">
            <i class="fas fa-bars"></i>
        </div>
        <div class="sidebar-nav">
            <a href="dashboard.php" class="nav-link"><i class="fas fa-user-cog"></i> <span>Profile</span></a>
            <a href="#" class="nav-link active"><i class="fas fa-users"></i> <span>Manage User</span></a>
            <a href="admin-room.php" class="nav-link"><i class="fas fa-building"></i> <span>Room Manager</span></a>
            <a href="admin-visitor_log.php" class="nav-link"><i class="fas fa-address-book"></i> <span>Log Visitor</span></a>

        </div>
        <div class="logout">
            <a href="../config/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a>
        </div>
    </div>

    <!-- Top bar -->
    <div class="topbar">
        <h2>Manage Users</h2>
    </div>


    <!-- Main content -->
    <div class="main-content">        
        <div class="button">
         <div class="search-container">
         <form method="GET" action="" class="search-form" style="position: relative;">
    <input type="text" id="searchInput" name="search" placeholder="Search for names, roles, etc." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" style="padding-left: 30px;">
    <button type="submit" style="display: none;"></button>
    <span style="position: absolute; top: 40%; left: 8px; transform: translateY(-50%); color: #ccc;">
        <i class="fas fa-search"></i>
    </span>
</form>
           <button id="createButton" class="btn" data-bs-toggle="modal" data-bs-target="#userModal">Add User</button>

                <!-- <button onclick="printTable()">Print Table</button> -->
         </div>
         
        </div
    

        <!-- User Table Structure -->
        <div class="table-container">
            
            <form method="GET" action="" class="filter-form" style="    margin-right: 5px; ">
        <label for="filter" >Filter by Role:</label>
        <select name="filter" id="filter" onchange="this.form.submit()">
            <option value="all" <?php if ($filter === 'all') echo 'selected'; ?>>All</option>
            <option value="General User" <?php if ($filter === 'General User') echo 'selected'; ?>>General User</option>
            <option value="Staff" <?php if ($filter === 'Staff') echo 'selected'; ?>>Staff</option>
        </select>
    </form> 
        
            <table id="userTable" class="table">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Name</th>
                        <th>Age</th>
                        <th>Address</th>
                        <th>Contact No.</th>
                        <th>Sex</th>
                        <th>Role</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php

                // Execute the query and check for errors
$result = $conn->query($sql);

if ($result === false) {
    // Output the error if the query fails
    echo "Error: " . $conn->error;
} else {
    // Proceed to check if there are rows
    if ($result->num_rows > 0) {
        $no = 1;
        while ($row = $result->fetch_assoc()) {
            // Process the rows and output the table rows
            $userId = $row['id'];
            $fullName = htmlspecialchars($row['Fname']) . ' ';
            if (!empty($row['MI'])) {
                $fullName .= htmlspecialchars($row['MI']) . '. ';
            }
            $fullName .= htmlspecialchars($row['Lname']);

            echo "<tr>
                <td data-label='No.'>" . $no . "</td>
                <td data-label='Name'>" . $fullName . "</td>
                <td data-label='Age'>" . intval($row['Age']) . "</td>
                <td data-label='Address'>" . htmlspecialchars($row['Address']) . "</td>
                <td data-label='Contact No.'>" . htmlspecialchars($row['contact']) . "</td>
                <td data-label='Sex'>" . htmlspecialchars($row['Sex']) . "</td>
                <td data-label='Role'>" . htmlspecialchars($row['Role']) . "</td>
                <td>
                    <button class='btn-edit' onclick='openEditModal($userId, \"" . htmlspecialchars($row['Fname']) . "\", \"" . htmlspecialchars($row['Lname']) . "\", \"" . htmlspecialchars($row['MI']) . "\", " . intval($row['Age']) . ", \"" . htmlspecialchars($row['Address']) . "\", \"" . htmlspecialchars($row['contact']) . "\", \"" . htmlspecialchars($row['Sex']) . "\", \"" . htmlspecialchars($row['Role']) . "\")'>Edit</button>
                    <form method='POST' action='' style='display:inline'>
                        <input type='hidden' name='user_id' value='$userId'>
                        <input type='hidden' name='delete_user' value='1'>
                        <button type='submit' class='btn-delete' onclick='return confirm(\"Are you sure you want to delete this user?\");'>Delete</button>
                    </form>
                </td>
            </tr>";
            $no++;
        }
    } else {
        echo "<tr><td colspan='8'>No users or staff found.</td></tr>";
    }
}
?>

                </tbody>
            </table>
 
</div>
<div style="text-align: center;">
    <!-- Previous Page Button -->
    <button <?php if ($currentPage <= 1) { echo 'disabled style="background-color: #ddd; cursor: not-allowed;"'; } ?> 
        onclick="window.location.href='?page=<?php echo $currentPage - 1; ?>'">
        Previous
    </button>

    <!-- Page Indicator -->
    <span>Page <?php echo $currentPage; ?> of <?php echo $totalPages; ?></span>

    <!-- Next Page Button -->
    <button <?php if ($currentPage >= $totalPages) { echo 'disabled style="background-color: #ddd; cursor: not-allowed;"'; } ?> 
        onclick="window.location.href='?page=<?php echo $currentPage + 1; ?>'">
        Next
    </button>
</div>
</div>


</div>


        </div>
    

        <!-- Add User Modal -->
<div id="userModal" class="modal">
    <div class="addmodal-content">
        <span class="close" onclick="hideModal()" style="cursor:pointer;">&times;</span>
        <h2 id="add-user">Add New User</h2>
        <form method="POST" action="" onsubmit="return validateForm();">
            <div class="form-grid">
                <div class="form-group">
                    <label for="fname">First Name:</label>
                    <input type="text" id="fname" name="Fname" required>
                </div>
                <div class="form-group">
                    <label for="lname">Last Name:</label>
                    <input type="text" id="lname" name="Lname" required>
                </div>
                <div class="form-group">
                    <label for="mi">Middle Initial:</label>
                    <input type="text" id="mi" name="MI">
                </div>
                <div class="form-group">
                    <label for="age">Age:</label>
                    <input type="number" id="age" name="Age" required>
                </div>
                <div class="form-group">
                    <label for="address">Address:</label>
                    <input type="text" id="address" name="Address" required>
                </div>
                <div class="form-group">
                    <label for="contact">Contact Number:</label>
                    <input type="text" id="contact" name="contact" required>
                </div>
                <div class="form-group">
                    <label for="sex">Sex:</label>
                    <select id="sex" name="Sex">
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="role">Role:</label>
                    <select id="role" name="Role" required>
                        <option value="General User">General User</option>
                        <option value="Staff">Staff</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>
            </div>
            <button type="submit" name="create_user">Add User</button>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editUserModal" class="modal">
    <div class="addmodal-content">
        <span class="close" onclick="closeEditModal()" style="cursor:pointer;">&times;</span>
        <h2 id="edituser">Edit User</h2>
        <form method="POST" action="" onsubmit="return validateForm();">
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
                <div class="form-group">
                    
                </div>
            </div>
            <button type="submit" name="edit_user">Update</button>
        </form>
    </div>
</div>


        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

        <!-- JavaScript for modal functionality -->
        <script>
   document.addEventListener('DOMContentLoaded', function() {
    const rowsPerPage = 10; // Limit to 10 rows per page
    let currentPage = 1;
    const rows = document.querySelectorAll('#room-table-body tr');
    const totalPages = Math.ceil(rows.length / rowsPerPage);

    // Show the initial set of rows
    showPage(currentPage);
    generatePageLinks();

    function showPage(page) {
        const start = (page - 1) * rowsPerPage;
        const end = start + rowsPerPage;
        
        // Loop through all rows and display or hide them based on the current page
        rows.forEach((row, index) => {
            row.style.display = (index >= start && index < end) ? '' : 'none';
        });

        // Update the page indicator
        document.getElementById('pageIndicator').innerText = `Page ${page}`;

        // Disable/enable buttons based on the current page
        document.getElementById('prevPage').disabled = page === 1;
        document.getElementById('nextPage').disabled = page === totalPages;

        // Update active page link
        document.querySelectorAll('#pageLinks a').forEach(link => {
            link.classList.remove('active');
        });
        document.querySelector(`#pageLinks a[data-page="${page}"]`).classList.add('active');
    }

    function generatePageLinks() {
        const pageLinksContainer = document.getElementById('pageLinks');
        pageLinksContainer.innerHTML = '';

        for (let i = 1; i <= totalPages; i++) {
            const pageLink = document.createElement('a');
            pageLink.href = "#";
            pageLink.textContent = i;
            pageLink.dataset.page = i;
            pageLink.onclick = function(event) {
                event.preventDefault();
                currentPage = parseInt(this.dataset.page);
                showPage(currentPage);
            };

            pageLinksContainer.appendChild(pageLink);
        }
    }

    // Function to go to the next page
    function nextPage() {
        if (currentPage < totalPages) {
            currentPage++;
            showPage(currentPage);
        }
    }

    // Function to go to the previous page
    function prevPage() {
        if (currentPage > 1) {
            currentPage--;
            showPage(currentPage);
        }
    }

    // Attach nextPage and prevPage functions to window (global scope) so they can be accessed by button clicks
    window.nextPage = nextPage;
    window.prevPage = prevPage;
});

       

function checkSearch() {
    var input = document.getElementById('searchInput').value.trim();

    // Automatically submit the form if the input is cleared
    if (input === '') {
        document.getElementById('searchForm').submit();  // Submits the form to reset the search
    }
}

          // Show the Add User Modal
// Show the Add User Modal
function showModal() {
    document.getElementById('userModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';  // Prevent background scroll when modal is open
}

// Hide the Add User Modal
function hideModal() {
    document.getElementById('userModal').style.display = 'none';
    document.body.style.overflow = 'auto';  // Restore background scrolling
}

// Show the Edit User Modal
function openEditModal(id, Fname, Lname, MI, Age, Address, contact, Sex, Role) {
    document.getElementById('editUserId').value = id;
    document.getElementById('editFname').value = Fname;
    document.getElementById('editLname').value = Lname;
    document.getElementById('editMI').value = MI;
    document.getElementById('editAge').value = Age;
    document.getElementById('editAddress').value = Address;
    document.getElementById('editContact').value = contact;
    document.getElementById('editSex').value = Sex;

    document.getElementById('editUserModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';  // Prevent background scroll when modal is open
}

// Hide the Edit User Modal
function closeEditModal() {
    document.getElementById('editUserModal').style.display = 'none';
    document.body.style.overflow = 'auto';  // Restore background scrolling
}

// Close modal when clicking outside the content
window.onclick = function(event) {
    var userModal = document.getElementById('userModal');
    var editUserModal = document.getElementById('editUserModal');
    
    // Check if the click was outside the modal content
    if (event.target === userModal) {
        hideModal();
    }
    if (event.target === editUserModal) {
        closeEditModal();
    }
}

            //hamburgermenu
            const hamburgerMenu = document.getElementById('hamburgerMenu');
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.add('collapsed');

            hamburgerMenu.addEventListener('click', function() {
                sidebar.classList.toggle('collapsed');
                const icon = hamburgerMenu.querySelector('i');
                icon.classList.toggle('fa-bars');
                icon.classList.toggle('fa-times');
            });

            function validateForm() {
                const email = document.getElementById('email') ? document.getElementById('email').value : '';
                const password = document.getElementById('password') ? document.getElementById('password').value : '';
                const contact = document.getElementById('contact') ? document.getElementById('contact').value : '';

                // Validate email format
                if (email && !email.includes("@")) {
                    alert("Please enter a valid email.");
                    return false;
                }

                // Validate password length
                if (password && password.length < 6) {
                    alert("Password must be at least 6 characters.");
                    return false;
                }

                // Validate contact number is exactly 11 digits
                const contactRegex = /^\d{11}$/;
                if (contact && !contactRegex.test(contact)) {
                    alert("Contact number must be exactly 11 digits.");
                    return false;
                }

                return true;
            }
            
        </script>
    </body>
</html>

<?php
$conn->close();
?>
