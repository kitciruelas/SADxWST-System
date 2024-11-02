<?php
session_start();



include '../config/config.php'; // Ensure this is correct
// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Query for room data
$roomCountQuery = "SELECT COUNT(*) AS totalRooms FROM rooms";
$roomCountResult = $conn->query($roomCountQuery);
$roomCount = $roomCountResult->fetch_assoc()['totalRooms'];

// Set limit of records per page
$limit = 6;

// Get the current page number from the URL, default to page 1
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// Calculate the starting point (offset)
$offset = ($page - 1) * $limit;

// Query to fetch rooms from the database with limit and offset for pagination
$sql = "SELECT room_id, room_number, room_desc, capacity, room_monthlyrent, status, room_pic FROM rooms LIMIT $limit OFFSET $offset";
$result = $conn->query($sql);

// Query to get the total number of rooms (for pagination calculation)
$totalRoomsQuery = "SELECT COUNT(*) AS total FROM rooms";
$totalResult = $conn->query($totalRoomsQuery);
$totalRooms = $totalResult->fetch_assoc()['total'];

// Calculate total pages
$totalPages = ceil($totalRooms / $limit);
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
    <link rel="stylesheet" href="land-img/style-land.css">
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
                            <a class="nav-link" href="#footer">About Us</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#room-list">Room</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#contact-us">Contact</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link btn btn-primary text-white" href="../User/user-login.php">Login</a>
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

<div class="container">
    <div class="row">

        <!-- Loop through the database records and display each room -->
        <?php
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                ?>
                <div class="col-md-4">
                    <div class="room-card">
                        <!-- Display room image -->
                        <img src="<?php echo $row['room_pic']; ?>" alt="Room Image">
                        

                        <!-- Rent Price -->
                        <p class="room-price">Rent Price: <?php echo number_format($row['room_monthlyrent'], 2); ?> / Monthly</p>
                        
                         <!-- Room Number -->
                         <h5>Room: <?php echo htmlspecialchars($row['room_number']); ?></h5>
                         
                        <!-- Room Capacity -->
                        <p>Capacity: <?php echo htmlspecialchars($row['capacity']); ?> people</p>

                        <!-- Room Description -->
                        <p><?php echo htmlspecialchars($row['room_desc']); ?></p>

                        <!-- Room Status -->
                        <p>Status: <?php echo htmlspecialchars($row['status']); ?></p>

                        <!-- Apply Button -->
                        <button class="apply-btn" id="applyNowBtn">Apply Now!</button>
                    </div>
                </div>
                <?php
            }
        } else {
            echo "<p>No rooms available.</p>";
        }
        ?>

    </div>

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
