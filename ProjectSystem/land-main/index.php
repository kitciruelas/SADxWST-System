<?php
session_start();



include '../config/config.php'; // Ensure this is correct
// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

date_default_timezone_set('Asia/Manila');


// Set limit of records per page for pagination
$limit = 6;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Query to fetch rooms for pagination
$sql = "SELECT room_id, room_number, room_desc, capacity, room_monthlyrent, status, room_pic FROM rooms LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $limit, $offset);
$stmt->execute();
$roomsResult = $stmt->get_result();

// Query to get total number of rooms (for pagination calculation)
$totalRoomsQuery = "SELECT COUNT(*) AS total FROM rooms";
$totalResult = $conn->query($totalRoomsQuery);
$totalRooms = $totalResult->fetch_assoc()['total'];

// Calculate total pages for pagination
$totalPages = ceil($totalRooms / $limit);


// Check if the form is submitted via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Capture form data
    $roomId = isset($_POST['room_id']) ? intval($_POST['room_id']) : null;
    $comments = isset($_POST['comments']) ? trim($_POST['comments']) : '';

    // Basic validation
    if ($roomId) {
        // Sanitize user input
        $comments = htmlspecialchars($comments);

        // Ensure user is logged in
        $userId = $_SESSION['id'] ?? null;
        if (!$userId) {
            echo "<script>alert('User not logged in. Please log in to apply.') ;window.history.back();</script>";
            exit;
        }

        // Fetch the old room ID based on the current user
        $stmt = $conn->prepare("SELECT room_id FROM roomassignments WHERE user_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stmt->bind_result($oldRoomId);
            $stmt->fetch();
            $stmt->close();
        } else {
            echo "<script>alert('Error: Could not prepare the statement to fetch old room ID.');window.history.back();</script>";
            exit;
        }

        // Check if the selected room is the same as the current room
        if ($oldRoomId === $roomId) {
            echo "<script>alert('You are already assigned to this room. Please select a different room.');window.history.back();</script>";
            exit;
        }

        // Check for existing reassignment requests
        $stmt = $conn->prepare("SELECT status FROM room_reassignments WHERE user_id = ? ORDER BY reassignment_date DESC LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stmt->bind_result($existingStatus);
            $stmt->fetch();
            $stmt->close();

            // Allow reassignment if the last request was approved or rejected
            if ($existingStatus === 'pending') {
                echo "<script>alert('You already have a reassignment request waiting for approval.');window.history.back();</script>";
                exit;
            }
        } else {
            echo "<script>alert('Error: Could not prepare the statement to check existing requests.');window.history.back();</script>";
            exit;
        }

        // If no comments provided, set it to "No comment"
        if (empty($comments)) {
            $comments = "No comment";
        }

        // Insert into the database
        $stmt = $conn->prepare("INSERT INTO room_reassignments (new_room_id, old_room_id, user_id, comment, reassignment_date, status) VALUES (?, ?, ?, ?, NOW(), 'pending')");
        if ($stmt) {
            $stmt->bind_param("iiis", $roomId, $oldRoomId, $userId, $comments);

            if ($stmt->execute()) {
                echo "<script>alert('Application submitted successfully!');</script>";
                echo "<script>window.location.href = '" . $_SERVER['PHP_SELF'] . "';</script>";
                exit;
            } else {
                echo "<script>alert('Error: " . htmlspecialchars($stmt->error) . "');</script>";
            }

            $stmt->close();
        } else {
            echo "<script>alert('Error: Could not prepare the statement for insertion.');</script>";
        }
    } else {
        echo "<script>alert('Invalid room ID. Please try again.');</script>";
    }
}


// Close the database connection
// Query to fetch rooms from the database with limit and offset for pagination
$sql = "SELECT room_id, room_number, room_desc, capacity, room_monthlyrent, status, room_pic FROM rooms LIMIT $limit OFFSET $offset";
$result = $conn->query($sql);

$sql = "SELECT 
            rooms.room_id, 
            room_number, 
            room_monthlyrent, 
            capacity, 
            room_desc, 
            room_pic, 
            status,
            (SELECT COUNT(*) FROM roomassignments WHERE room_id = rooms.room_id) AS current_occupants 
        FROM 
            rooms";
$result = $conn->query($sql);
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dormitory Space Landing Page</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="land-img/stylesland.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">



</head>
<body>
    <!-- Header and Navbar -->
    <header class="position-fixed top-0 w-100" style="z-index: 1050;">
        <nav class="navbar navbar-expand-lg navbar-light">
            <div class="container-fluid">
                <a class="navbar-brand" href="#">
                    <img src="logo.png" alt="Company Logo" class="img-fluid" style="max-height: 50px;">
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                    <ul class="navbar-nav">
                        <li class="nav-item">
                            <a class="nav-link" href="#">Home</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#room-list">Room</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#contact-us">Contact</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#footer">About Us</a>
                        </li>
                       
                        <li class="nav-item">
                            <a class="nav-link btn btn-primary text-white" href="../User/user-login.php">Log in</a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>
    
    
    <!-- Hero Section -->
    <section class="hero-section" style="color: #fdfdfd; text-shadow: 5px 5px 15px rgba(0, 0, 0, 0.7);">
        <div class="container">
            <h1 class="display-4">Welcome to Your Dormitory Space!</h1>
            <p class="lead">Manage your stay, track attendance, and stay connected—all in one place.</p>
        </div>
    </section>

    <!-- Room List Section -->
    <section id="room-list" class="room-list py-5">

    
    <h1 class="text-center">Rooms</h1>


<!-- Room List -->
<div class="container">
<!-- Filter Dropdown -->
<div class="filter-dropdown mb-4 text-start">
    <label for="statusFilter" class="me-2">Filter by Status:</label>
    <select id="statusFilter" class="form-select d-inline-block w-auto" onchange="filterRooms()">
        <option value="">All</option>
        <?php 
        $statuses = ['Available', 'Occupied', 'Maintenance']; 
        foreach ($statuses as $status): ?>
            <option value="<?php echo htmlspecialchars($status); ?>">
                <?php echo htmlspecialchars($status); ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<!-- Room List -->
<!-- Room List -->
<div class="container">
    <div class="row justify-content-center">
    <?php
    if ($result === false) {
        echo "<p>SQL Error: " . htmlspecialchars($conn->error) . "</p>";
    } elseif ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $currentOccupants = $row['current_occupants'] ?? 0; 
            $totalCapacity = $row['capacity'] ?? 0;

            // Determine room status
            if ($currentOccupants >= $totalCapacity) {
                $status = 'Occupied';
            } elseif (strtolower($row['status']) === 'maintenance') {
                $status = 'Maintenance';
            } else {
                $status = 'Available';
            }
            ?>
            <!-- Room Card -->
            <div class="col-md-4 room-card mb-4" data-status="<?php echo htmlspecialchars($status); ?>">
                <div class="card custom-card h-100">
                    <?php 
                    $imagePath = "../uploads/" . ($row['room_pic'] ?? '');
                    if (!empty($row['room_pic']) && file_exists($imagePath)): ?>
                        <img src="<?php echo htmlspecialchars($imagePath); ?>" 
                             alt="Room Image" 
                             class="card-img-top custom-card-img" 
                             style="cursor: pointer;" 
                             onclick="openModal('<?php echo htmlspecialchars($imagePath); ?>')">
                    <?php else: ?>
                        <img src="path/to/default/image.jpg" alt="No Image Available" class="card-img-top custom-card-img"> <!-- Fallback image -->
                    <?php endif; ?>

                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title">Room: <?php echo htmlspecialchars($row['room_number']); ?></h5>
                        <p class="room-price">
                            Rent Price: <?php echo number_format($row['room_monthlyrent'], 2); ?> / Monthly
                        </p>
                        <p>Capacity: 
                            <?php echo htmlspecialchars($currentOccupants) . '/' . htmlspecialchars($totalCapacity); ?> people
                        </p>
                        <p><?php echo htmlspecialchars($row['room_desc']); ?></p>
                        <p>Status: <?php echo htmlspecialchars($status); ?></p>

                        <div class="mt-auto">
                            <?php if ($status === 'Maintenance'): ?>
                                <button class="btn btn-warning" disabled aria-disabled="true">Under Maintenance</button>
                            <?php elseif ($currentOccupants < $totalCapacity): ?>
                                <button type="button" class="btn btn-primary apply-btn" data-bs-toggle="modal" data-bs-target="#applyModal"
                                    data-room-id="<?php echo htmlspecialchars($row['room_id'] ?? ''); ?>"
                                    data-room-number="<?php echo htmlspecialchars($row['room_number'] ?? ''); ?>"
                                    data-room-price="<?php echo htmlspecialchars($row['room_monthlyrent'] ?? ''); ?>"
                                    data-room-capacity="<?php echo htmlspecialchars($row['capacity'] ?? ''); ?>"
                                    data-room-status="<?php echo htmlspecialchars($row['status'] ?? ''); ?>">
                                    Reassign Room
                                </button>
                            <?php else: ?>
                                <button class="btn btn-danger" disabled aria-disabled="true">Fully Occupied</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }
    } else {
        echo "<p class='text-center'>No rooms available.</p>";
    }
    ?>
    </div>
</div>

<!-- Custom CSS -->
<style>
    .custom-card {
        width: 300px; /* Increased width */
    }

    .custom-card-img {
        height: 200px; /* Adjusted image height */
        object-fit: cover; /* Maintain aspect ratio and fill the image area */
    }
</style>




    <!-- Pagination Links -->
    <div class="pagination">
         <!-- Pagination Links -->
    <div id="pagination">
        <!-- Previous Page Button -->
        <button <?php if ($page <= 1) { echo 'disabled'; } ?> onclick="window.location.href='?page=<?php echo $page - 1; ?>'">
            Previous
        </button>

        <!-- Page Indicator -->
        <span id="pageIndicator">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>

        <!-- Next Page Button -->
        <button <?php if ($page >= $totalPages) { echo 'disabled'; } ?> onclick="window.location.href='?page=<?php echo $page + 1; ?>'">
            Next
        </button>
    </div>
</div>

</div>
    </section>

    <!-- Features Section -->
    <section class="features-section py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-4">Dorm Life Made Simple</h2>
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="card animate__animated animate__fadeInLeft">
                        <img src="room.jpg" alt="Apply for a Room" class="card-img-top img-fluid">
                        <div class="card-body">
                            <h3 class="card-title">APPLY FOR A ROOM</h3>
                            <p class="card-text">Submit your room application online, choosing from available rooms. Our system will guide you through the process, from application to approval.</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="card animate__animated animate__fadeInUp">
                        <img src="room1.jpg" alt="Manage Your Stay" class="card-img-top img-fluid">
                        <div class="card-body">
                            <h3 class="card-title">MANAGE YOUR STAY</h3>
                            <p class="card-text">Access your room details, make maintenance requests, and check your rent payment history—all in one place. Tailor your stay to suit your needs.</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="card animate__animated animate__fadeInRight">
                        <img src="room2.jpg" alt="Check In/Out" class="card-img-top img-fluid">
                        <div class="card-body">
                            <h3 class="card-title">CHECK IN/OUT MADE EASY</h3>
                            <p class="card-text">Seamlessly check in and out of the dormitory with our presence monitoring feature. Staff will ensure your records are updated and secure.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Us Section -->
    <section id="contact-us" class="py-5">
        <div class="container">
            <h2 class="text-center mb-4">Contact Us</h2>
            <div class="row">
                <div class="col-lg-6 mb-4">
                    <h4>Get in Touch</h4>
                    <form>
                        <div class="mb-3">
                            <label for="name" class="form-label">Name</label>
                            <input type="text" class="form-control" id="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="message" class="form-label">Message</label>
                            <textarea class="form-control" id="message" rows="4" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Send Message</button>
                    </form>
                </div>
                <div class="col-lg-6 mb-4">
                    <h4>Our Location</h4>
                    <p>123 Dormitory Street, City, Country</p>
                    <p>Phone: +123 456 7890</p>
                    <p>Email: info@dormitoryspace.com</p>
                    <iframe
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2655.0932999911765!2d121.16177851555618!3d13.949289190451044!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x33bdf0ab88a6792b%3A0x9cd5ff0283e6a428!2sLipa%2C%20Batangas%2C%20Philippines!5e0!3m2!1sen!2sus!4v1633678988671!5m2!1sen!2sus"
                    width="100%"
                    height="250"
                    style="border:0;"
                    allowfullscreen=""
                    loading="lazy"></iframe>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    
    <footer id="footer" class="bg-dark text-white text-center py-4">
        <div class="container">
            <p>Welcome to Dormitory Space, your home away from home. We provide a comfortable and safe living environment with modern amenities, including:</p>
    
            <div class="row">
                <div class="col-md-4 footer-section">
                    <h5>Our Services</h5>
                    <ul class="list-unstyled mb-0">
                        <li>24/7 Security</li>
                        <li>Free Wi-Fi</li>
                        <li>Laundry Facilities</li>
                        <li>Study Rooms</li>
                        <li>Common Areas</li>
                    </ul>
                </div>
            
                <div class="col-md-4 footer-section">
                    <h5>Quick Links</h5>
                    <div class="footer-links mt-3">
                        <p class="mb-1"><a href="#about" class="text-white">About Us</a></p>
                        <p class="mb-1"><a href="#offers" class="text-white">Offers</a></p>
                        <p class="mb-0"><a href="#contact" class="text-white">Contact Us</a></p>
                    </div>
                </div>
            
                <div class="col-md-4 footer-section">
                    <h5>Contact Us</h5>
                    <p class="mb-1">123 Dormitory St., City, State, ZIP</p>
                    <p class="mb-1">Email: <a href="mailto:info@dormitoryspace.com" class="text-white">info@dormitoryspace.com</a></p>
                    <p class="mb-0">Phone: (123) 456-7890</p>
                    
                    <div class="social-icons mt-2">
                        <p class="mb-0">
                            <a href="#" class="text-white me-2" aria-label="Facebook">
                                <i class="fab fa-facebook"></i>
                            </a>
                            <a href="#" class="text-white me-2" aria-label="Twitter">
                                <i class="fab fa-twitter"></i>
                            </a>
                            <a href="#" class="text-white" aria-label="Instagram">
                                <i class="fab fa-instagram"></i>
                            </a>
                        </p>
                    </div>
                </div>
            </div>
    
            <p class="mt-4">&copy; 2024 Dormitory Space. All Rights Reserved.</p>
        </div>
    </footer>
    
    
    
    

    <!-- Bootstrap JS and FontAwesome -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js"></script>

    <script>
        function filterRooms() {
    var filterValue = document.getElementById('statusFilter').value.toLowerCase();
    var roomCards = document.querySelectorAll('.room-card');

    roomCards.forEach(function(card) {
        // Get the status from data-status attribute
        var status = card.getAttribute('data-status').toLowerCase();
        
        // Check if the card should be displayed
        if (filterValue === "" || status === filterValue) {
            card.style.display = ""; // Show card
        } else {
            card.style.display = "none"; // Hide card
        }
    });
}
        // Get the Apply Now button element
const applyNowBtn = document.getElementById("applyNowBtn");

// When the button is clicked, redirect to login.html
applyNowBtn.onclick = function() {
    window.location.href = "login.html";
}

        // Change navbar background on scroll
        window.onscroll = function() {
            const navbar = document.querySelector('.navbar');
            if (document.body.scrollTop > 50 || document.documentElement.scrollTop > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        };

        // Collapse the navbar on link click
        const navbarLinks = document.querySelectorAll('.navbar-nav .nav-link');
        navbarLinks.forEach(link => {
            link.addEventListener('click', () => {
                const navbarCollapse = document.getElementById('navbarNav');
                const bsCollapse = new bootstrap.Collapse(navbarCollapse, {
                    toggle: false
                });
                bsCollapse.hide();
            });
        });
    </script>
</body>
</html>
