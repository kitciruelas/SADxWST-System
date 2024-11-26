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
    <title>Dormio Landing Page</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="land-img/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
</head>
<body>
    <!-- Header and Navbar -->
    <header class="position-fixed top-0 w-100" style="z-index: 1050;">
        <nav class="navbar navbar-expand-lg navbar-light">
            <div class="container-fluid">
                <a class="navbar-brand" href="#">
                    <img src="logo.png" alt="Dormio Logo" class="img-fluid" style="max-height: 50px;">
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
    <section class="hero-section" style="color: #fdfdfd; background: url('image.png') center/cover no-repeat; height: 100vh; display: flex; justify-content: center; align-items: center; text-align: center; padding: 0 20px;">
    <div class="container-fluid">
        <h1 class="display-4 mb-4" style="font-size: 4rem;">Welcome to Dormio!</h1>
        <p class="lead" style="font-size: 2rem;">Manage your stay, track attendance, and stay connected - all in one place.</p>
    </div>
</section>


  <!-- Room List Section -->
<section id="room-list" class="room-list py-5">

    
    <h1 class="text-center">Rooms</h1>


<!-- Room List -->
<div class="container-fluid">
    <div id="roomCarousel" class="carousel slide" data-bs-ride="carousel">
        <div class="carousel-inner">
            <?php
            if ($result === false) {
                echo "<p>SQL Error: " . htmlspecialchars($conn->error) . "</p>";
            } elseif ($result->num_rows > 0) {
                $cards = array();
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

                    // Store each card HTML in array
                    ob_start();
                    ?>
                    <div class="col-md-4 room-card" data-status="<?php echo htmlspecialchars($status); ?>">
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
                                <img src="path/to/default/image.jpg" alt="No Image Available" class="card-img-top custom-card-img">
                            <?php endif; ?>

                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title">Room: <?php echo htmlspecialchars($row['room_number']); ?></h5>
                                <p class="room-price">
                                    Rent Price: <?php echo number_format($row['room_monthlyrent'], 2); ?> / Monthly
                                </p>
                                <p><?php echo htmlspecialchars($row['room_desc']); ?></p>
                            </div>
                        </div>
                    </div>
                    <?php
                    $cards[] = ob_get_clean();
                }

                // Adjust number of cards per slide based on screen size
                $cardsPerSlide = 3;
                $totalCards = count($cards);
                for ($i = 0; $i < $totalCards; $i += $cardsPerSlide) {
                    $activeClass = ($i === 0) ? 'active' : '';
                    echo '<div class="carousel-item ' . $activeClass . '">';
                    echo '<div class="row justify-content-center">'; // Added justify-content-center
                    
                    // Add cards to this slide
                    for ($j = $i; $j < min($i + $cardsPerSlide, $totalCards); $j++) {
                        echo $cards[$j];
                    }
                    
                    // Fill empty spaces with blank cards if needed
                    for ($k = min($i + $cardsPerSlide, $totalCards); $k < $i + $cardsPerSlide; $k++) {
                        echo '<div class="col-md-4 room-card invisible"></div>';
                    }
                    
                    echo '</div>';
                    echo '</div>';
                }
            } else {
                echo "<p class='text-center'>No rooms available.</p>";
            }
            ?>
        </div>
        
        <!-- Custom Navigation Buttons -->
        <div class="custom-carousel-controls">
            <button class="custom-carousel-btn prev" type="button" data-bs-target="#roomCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Previous</span>
            </button>
            <button class="custom-carousel-btn next" type="button" data-bs-target="#roomCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                <span class="visually-hidden">Next</span>
            </button>
        </div>
    </div>
</div>

<!-- Updated CSS -->
<style>
    /* Carousel Controls */
    .carousel-control-prev,
    .carousel-control-next {
        width: 5%;
        background: none;
        opacity: 1;
    }

    .carousel-control-prev i,
    .carousel-control-next i {
        color: #000;
        text-shadow: none;
        transition: transform 0.3s ease;
    }

    .carousel-control-prev:hover i,
    .carousel-control-next:hover i {
        transform: scale(1.2);
    }

    /* Carousel Items */
    .carousel-item {
        padding: 1rem;
    }

    .carousel-item .row {
        margin: 0 -10px;
    }

    .room-card {
        padding: 0 10px;
    }

    /* Responsive Adjustments */
    @media (max-width: 992px) {
        .carousel-item .row {
            flex-wrap: wrap;
        }
        
        .room-card {
            flex: 0 0 50%;
            max-width: 50%;
            margin-bottom: 20px;
        }
    }

    @media (max-width: 576px) {
        .room-card {
            flex: 0 0 100%;
            max-width: 100%;
        }

        .carousel-control-prev,
        .carousel-control-next {
            width: 10%;
        }
    }

    /* Hide empty card placeholders on mobile */
    @media (max-width: 768px) {
        .room-card.invisible {
            display: none;
        }
    }
</style>

<!-- Add this CSS -->
<style>
    .custom-carousel-controls {
        position: absolute;
        top: 50%;
        width: 100%;
        transform: translateY(-50%);
        display: flex;
        justify-content: space-between;
        pointer-events: none;
    }

    .custom-carousel-btn {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background-color: rgba(255, 255, 255, 0.8);
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
        pointer-events: auto;
        margin: 0 15px;
    }

    .custom-carousel-btn:hover {
        background-color: rgba(255, 255, 255, 1);
        transform: scale(1.1);
    }

    .carousel-control-prev-icon,
    .carousel-control-next-icon {
        filter: invert(1) grayscale(100);
    }
</style>

<!-- Updated JavaScript -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const carousel = new bootstrap.Carousel(document.querySelector('#roomCarousel'), {
            interval: 5000,
            wrap: true,
            touch: true
        });

        // Optional: Pause on hover
        document.querySelector('#roomCarousel').addEventListener('mouseenter', function() {
            carousel.pause();
        });

        document.querySelector('#roomCarousel').addEventListener('mouseleave', function() {
            carousel.cycle();
        });
    });
</script>

<!-- Custom CSS -->
<style>
    .custom-card {
        background-color: white; /* Set a light gray background color */
        border-radius: 8px; /* Rounded corners for a smoother look */
    }

    .custom-card-img {
        height: 200px; /* Adjusted image height */
        object-fit: cover; /* Maintain aspect ratio and fill the image area */
        border-top-left-radius: 8px; /* Rounded top-left corner for consistency */
        border-top-right-radius: 8px; /* Rounded top-right corner for consistency */
    }

    .custom-card-text {
        padding: 15px; /* Add padding inside the card for content */
        color: #333; /* Dark text color for readability */
    }
</style>




</div>
    </section>

    <!-- Features Section -->
<!-- Features Section -->
<section class="features-section custom-padding-bottom py-5 bg-light" style="padding-top: 5rem; padding-bottom: 8rem;">
    <div class="container-fluid"> <!-- Changed to container for more side space -->
        <h2 class="text-center mb-4"><strong>Dorm Life Made Simple</strong></h2> <!-- Made the title bold -->
        <div class="row justify-content-center g-6"> <!-- Center the row content -->
            <div class="col-lg-4 col-md-6 d-flex align-items-stretch">
                <div class="card animate__animated animate__fadeInLeft h-100">
                    <img src="room1.jpg" alt="Apply for a Room" class="card-img-top img-fluid" style="object-fit: cover; transform: scale(1);">
                    <div class="card-body">
                        <h3 class="card-title">APPLY FOR A ROOM</h3>
                        <p class="card-text">Submit your room application online, choosing from available rooms. Our system will guide you through the process, from application to approval.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 d-flex align-items-stretch">
                <div class="card animate__animated animate__fadeInUp h-100">
                    <img src="room2.jpg" alt="Apply for a Room" class="card-img-top img-fluid" style="object-fit: cover; transform: scale(1);">
                    <div class="card-body">
                        <h3 class="card-title">MANAGE YOUR STAY</h3>
                        <p class="card-text">Access your room details, make maintenance requests, and check your rent payment history—all in one place. Tailor your stay to suit your needs.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6 d-flex align-items-stretch">
                <div class="card animate__animated animate__fadeInRight h-100">
                    <img src="room.jpg" alt="Apply for a Room" class="card-img-top img-fluid" style="object-fit: cover; transform: scale(1);">
                    <div class="card-body">
                        <h3 class="card-title">CHECK IN/OUT MADE EASY</h3>
                        <p class="card-text">Seamlessly check in and out of the dormitory with our presence monitoring feature. Staff will ensure your records are updated and secure.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Additional Styles for Centering -->
<style>
    .features-section .row {
        display: flex;
        justify-content: center;
        align-items: center;
    }

    /* Custom Bottom Padding */
    .custom-padding-bottom {
        padding-bottom: 11rem !important;  /* Increased the padding-bottom to add more space */
    }

    .card {
        border-radius: 10px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    /* Add custom padding for more left and right spacing */
    .features-section {
        padding-left: 2rem;
        padding-right: 2rem;
    }
</style>



 <!-- Contact Us Section -->
<section id="contact-us" style="background-color: #e3e7fa; padding: 40px 0;">
    <div class="container-fluid" style="max-width: 1200px; margin: 0 auto;">
        <h2 class="text-center" style="font-weight: bold; margin-bottom: 30px;">CONTACT US</h2>
        <div class="row">
           <!-- Get in Touch Form -->
<div class="col-md-6" style="border: 1px solid #ccc; padding: 20px; border-radius: 5px;">
    <h4 style="margin-bottom: 20px;">Get in Touch</h4>
    <form>
        <div class="form-group" style="margin-bottom: 15px;">
            <label for="name">Name</label>
            <input type="text" class="form-control" id="name" placeholder="Enter your name" style="padding: 10px; width: 100%;">
        </div>
        <div class="form-group" style="margin-bottom: 15px;">
            <label for="email">Email</label>
            <input type="email" class="form-control" id="email" placeholder="Enter your email" style="padding: 10px; width: 100%;">
        </div>
        <div class="form-group" style="margin-bottom: 15px;">
            <label for="subject">Subject</label>
            <input type="text" class="form-control" id="subject" placeholder="Enter subject" style="padding: 10px; width: 100%;">
        </div>
        <div class="form-group" style="margin-bottom: 15px;">
            <label for="message">Message</label>
            <textarea class="form-control" id="message" rows="4" placeholder="Write your message here" style="padding: 10px; width: 100%;"></textarea>
        </div>
        <button type="submit" class="btn btn-primary" style="background-color: #0056b3; border: none; padding: 10px 20px;">Send Message</button>
    </form>
</div>

            <!-- Location Information -->
            <div class="col-md-6">
                <h4 style="margin-bottom: 20px;">Our Location</h4>
                <p><strong>Address:</strong> Purok 2, Inosluban Lipa City</p>
                <p><strong>Phone:</strong> +123 456 7890</p>
                <p><strong>Email:</strong> <a href="mailto:dormio@gmail.com" style="color: #0056b3;">dormio@gmail.com</a></p>

                <!-- Google Maps Embed -->
                <div class="map-container" style="margin-top: 20px;">
                    <iframe
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d31347.122208155236!2d121.1572098!3d13.9422049!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x33bd6e6f73b6eb6f%3A0x60c5d72722509f56!2sInosluban%2C%20Lipa%2C%20Batangas%2C%20Philippines!5e0!3m2!1sen!2sph!4v1691743080705!5m2!1sen!2sph"
                        width="100%"
                        height="300"
                        style="border:0; border-radius: 10px;"
                        allowfullscreen=""
                        loading="lazy"></iframe>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Additional CSS for Styling -->
<style>
    .form-control {
        border: 1px solid #ccc;
        border-radius: 5px;
    }
    .btn-primary:hover {
        background-color: #004494;
    }
</style>




    <!-- Footer -->
    
    <footer id="footer" class="bg-dark text-white text-center py-3">
    <div class="container-fluid">
        <p class="small">Welcome to Dormitory Space, your home away from home. We provide a comfortable and safe living environment with modern amenities, including:</p>

        <div class="row">
            <div class="col-md-4 footer-section">
                <h5 class="h6">Our Services</h5>
                <ul class="list-unstyled mb-0">
                    <li>24/7 Security</li>
                    <li>Free Wi-Fi</li>
                    <li>Laundry Facilities</li>
                    <li>Study Rooms</li>
                    <li>Common Areas</li>
                </ul>
            </div>

            <div class="col-md-4 footer-section">
                <h5 class="h6">Quick Links</h5>
                <div class="footer-links mt-3">
                    <p class="mb-1"><a href="#about" class="text-white">About Us</a></p>
                    <p class="mb-1"><a href="#offers" class="text-white">Offers</a></p>
                    <p class="mb-0"><a href="#contact" class="text-white">Contact Us</a></p>
                </div>
            </div>

            <div class="col-md-4 footer-section">
                <h5 class="h6">Contact Us</h5>
                <p class="mb-1">123 Dormitory St., City, State, ZIP</p>
                <p class="mb-1">Email: <a href="mailto:info@dormitoryspace.com" class="text-white">info@dormitoryspace.com</a></p>
                <p class="mb-0">Phone: (123) 456-7890</p>

                <div class="social-icons mt-2">
                    <p class="mb-0">
                        <a href="#" class="text-white me-2" aria-label="Facebook">
                            <i class="fab fa-facebook" style="font-size: 1.2rem;"></i>
                        </a>
                        <a href="#" class="text-white me-2" aria-label="Twitter">
                            <i class="fab fa-twitter" style="font-size: 1.2rem;"></i>
                        </a>
                        <a href="#" class="text-white" aria-label="Instagram">
                            <i class="fab fa-instagram" style="font-size: 1.2rem;"></i>
                        </a>
                    </p>
                </div>
            </div>
        </div>

        

        <p class="mt-4 small">&copy; 2024 Dormitory Space. All Rights Reserved.</p>
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
