<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../../settings/connection.php';

// Checking if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Fetching the user information from the database
$userInfo = [];
$sql = "SELECT email, created_at, username, profile_picture FROM Users WHERE user_id = :user_id";
try {
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    $userInfo = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo 'Query failed: ' . $e->getMessage();
    exit();
}

// Fetching countries too from the database
$countries = [];
$sql = "SELECT country_id, country_name FROM Countries";
try {
    $stmt = $conn->query($sql);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $countries[$row['country_id']] = $row['country_name'];
    }
} catch (PDOException $e) {
    echo 'Query failed: ' . $e->getMessage();
    exit();
}

// Fetching upcoming trips for the logged-in user with trips created
$upcomingTrips = [];
$sql = "SELECT * FROM Travel_Preferences WHERE user_id = :user_id ORDER BY travel_date DESC";
try {
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':user_id', $_SESSION['user_id']);
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $upcomingTrips[] = $row;
    }
} catch (PDOException $e) {
    echo 'Query failed: ' . $e->getMessage();
    exit();
}

// Function to convert relative path to web-accessible path
function convertPathToWeb($relativePath) {
    $baseUrl = '/MyTravelPal/'; // Adjust to your project's base URL
    $relativePath = ltrim($relativePath, './');
    return $baseUrl . $relativePath;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TravelPal Dashboard</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <style>
        :root {
            --primary-color: #007BFF;
            --background-color: #f5f7fa;
            --dark-background-color: #121212;
            --text-color: #333;
            --dark-text-color: #f5f7fa;
            --card-bg-color: white;
            --dark-card-bg-color: #1e1e1e;
        }
        body, html {
            height: 100%;
            margin: 0;
            font-family: 'Roboto', sans-serif;
            scroll-behavior: smooth;
            background-color: var(--background-color);
            color: var(--text-color);
        }

        .profile-picture {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 20px;
        }
        .dark-mode .profile-picture {
            border: 2px solid var(--dark-text-color);
        }

        .dark-mode {
            background-color: var(--dark-background-color);
            color: var(--dark-text-color);
        }
        .dark-mode .header,
        .dark-mode .sidebar,
        .dark-mode .section,
        .dark-mode .footer {
            background-color: var(--dark-background-color);
            color: var(--dark-text-color);
        }
        .dark-mode .header .dashboard,
        .dark-mode .header .create-trip,
        .dark-mode .header .view-travelers,
        .dark-mode .header .dark-mode-toggle {
            background: rgba(255, 255, 255, 0.1);
        }
        .dark-mode .header .dashboard:hover,
        .dark-mode .header .create-trip:hover,
        .dark-mode .header .view-travelers:hover,
        .dark-mode .header .dark-mode-toggle:hover {
            background-color: rgba(255, 255, 255, 0.3);
        }
        .dark-mode .sidebar a {
            color: var(--dark-text-color);
        }
        .dark-mode .sidebar a:hover {
            background-color: #1e1e1e;
        }
        .dark-mode .card {
            background-color: var(--dark-card-bg-color);
        }
        .dark-mode .card .btn {
            background-color: var(--primary-color);
        }
        .dark-mode .card .btn:hover {
            background-color: #0056b3;
        }
        .dark-mode .card p, .dark-mode .card h3 {
            color: var(--dark-text-color);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            width: 100%;
            position: fixed;
            top: 0;
            z-index: 1000;
            background-color: rgba(0, 0, 0, 0.7);
        }
        .header .logo {
            font-size: 24px;
            font-weight: bold;
            color: white;
        }
        .header .user-menu {
            display: flex;
            align-items: center;
            position: relative;
        }
        .header .dashboard, .header .create-trip, .header .view-travelers, .header .dark-mode-toggle {
            color: white;
            font-weight: bold;
            text-decoration: none;
            font-size: 16px;
            cursor: pointer;
            margin-right: 10px;
            padding: 10px 20px;
            border: none;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 5px;
        }
        .header .dashboard:hover, .header .create-trip:hover, .header .view-travelers:hover, .header .dark-mode-toggle:hover {
            background-color: rgba(255, 255, 255, 0.3);
        }
        .header .username {
            padding: 10px 20px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-size: 16px;
            cursor: pointer;
            position: relative;
        }
        .header .dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            background-color: #fff;
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
            z-index: 1;
            display: none;
            border-radius: 5px;
            overflow: hidden;
            min-width: 150px;
        }
        .header .dropdown a {
            color: black;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            transition: background-color 0.3s;
        }
        .header .dropdown a:hover {
            background-color: #f1f1f1;
        }
        .sidebar {
            position: fixed;
            top: 70px;
            left: 0;
            width: 250px;
            height: 100%;
            background-color: var(--primary-color);
            padding-top: 20px;
            color: white;
        }
        .sidebar a {
            padding: 15px;
            text-decoration: none;
            font-size: 18px;
            color: white;
            display: block;
            transition: background-color 0.3s;
        }
        .sidebar a:hover {
            background-color: #0056b3;
        }
        .trip-button {
            background-color: #007BFF;
            color: white;
            border: none;
            padding: 10px 20px;
            font-size: 16px;
            border-radius: 5px;
            cursor: pointer;
            display: block; 
            width: 100%; 
            margin: 0; 

        .trip-button:hover {
            background-color: #0056b3;
        }

        .main-content {
            margin-left: 250px;
            padding: 80px 20px 20px;
            transition: filter 0.3s;
        }
        .section {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: var(--card-bg-color);
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 50px;
        }
        .dark-mode .section {
            background: var(--dark-card-bg-color);
        }
        .section h2 {
            font-size: 28px;
            margin-bottom: 20px;
            color: var(--text-color);
            cursor: pointer; /* ps: Makes the header look clickable */
        }
        .dark-mode .section h2 {
            color: var(--dark-text-color);
        }
        .section p {
            font-size: 18px;
            color: #666;
        }
        .dark-mode .section p {
            color: var(--dark-text-color);
        }
        .dashboard-section {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .card {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            background-color: var(--card-bg-color);
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 20px;
            transition: transform 0.3s;
            width: 100%;
        }
        .card-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .card img {
            max-width: 100%;
            height: auto;
            border-radius: 10px;
            margin-top: 20px;
        }
        .card .btn {
            display: inline-block;
            margin-top: 10px;
            padding: 10px 20px;
            font-size: 16px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            background-color: var(--primary-color);
            color: white;
            text-decoration: none;
            margin-right: 10px;
        }
        .card .btn:hover {
            background-color: #0056b3;
        }
        .footer {
            background-color: #333;
            color: white;
            text-align: center;
            padding: 20px 0;
        }
        .footer a {
            color: var(--primary-color);
            text-decoration: none;
        }
        .footer a:hover {
            text-decoration: underline;
        }
        .hidden {
            display: none;
        }
        .profile-picture {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 20px;
        }
        .dark-mode .profile-picture {
            border: 2px solid var(--dark-text-color);
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background-color: white;
            margin: auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 600px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: stretch;
        }
        .dark-mode .modal-content {
            background-color: var(--dark-card-bg-color);
        }
        .close {
            color: #aaa;
            align-self: flex-end;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover,
        .close:focus {
            color: black;
            text-decoration: none;
            cursor: pointer;
        }
        .dark-mode .close {
            color: var(--dark-text-color);
        }
        .modal-content form {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .modal-content label {
            font-weight: bold;
            margin-top: 10px;
        }
        .modal-content input,
        .modal-content textarea,
        .modal-content select {
            padding: 5px;
            margin-top: 5px;
            border-radius: 5px;
            border: 3px solid #ccc;
            font-size: 16px;
            width: 100%;
        }
        .modal-content .radio-group {
            display: flex;
            flex-direction: row;
            gap: 10px;
            margin-top: 10px;
        }
        .modal-content .radio-group label {
            margin: 1;
        }
        .modal-content button {
            margin-top: 20px;
            padding: 10px;
            font-size: 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            background-color: var(--primary-color);
            color: white;
        }
        .modal-content button:hover {
            background-color: #0056b3;
        }
    </style>
    <script src="https://kit.fontawesome.com/a076d05399.js"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
</head>
<body>
    <div class="header">
        <div class="logo">TravelPal</div>
        <?php if (isset($_SESSION['username'])): ?>
            <div class="user-menu">
                <a href="HomePage.php" class="dashboard">HomePage</a>
                <button class="create-trip" onclick="toggleCreateTripModal()">Create New Trip</button>
                <a href="view_travelers.php" class="view-travelers">View All Travelers</a>
                <button class="dark-mode-toggle" onclick="toggleDarkMode()">Toggle Dark Mode</button>
                <div class="username" onclick="toggleDropdown()">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></div>
                <div class="dropdown">
                    <a href="Dashboard.php">Profile</a>
                    <a href="/MyTravelPal/action/logout.php">Log Out</a>
                </div>
            </div>
        <?php else: ?>
            <a href="../LandingPage.php"></a>
        <?php endif; ?>
    </div>
    <div class="sidebar">
        <a href="messages.php"><i class="fas fa-envelope"></i> Messages</a>
        <a href="DataAnalysis.php"><i class="fas fa-user"></i> Data Analysis</a>
    </div>
    <div class="main-content">
        <div class="section">
            <h2>Welcome to your Dashboard</h2>
            <p>Here you can create and manage all your upcoming trips, view messages, and update your profile.</p>
        </div>
        <div class="section">
            <h2>Profile Overview <button class="btn" style="float:right;" onclick="editProfile()">Edit</button></h2>
            <div class="dashboard-section">
                <div class="card">
                    <div class="card-content">
                        <h3>Your Profile</h3>
                        <?php 
                        // Default avatar URL
                        $defaultAvatar = 'https://rawcdn.githack.com/Kabi12Blessing/72892025_ChurningPrediction/c2e416446c7e05056259be4e948de42f070a8e6c/266033.png';
                        // Convert absolute path to web-accessible relative path
                        $profilePicturePath = empty($userInfo['profile_picture']) ? $defaultAvatar : convertPathToWeb($userInfo['profile_picture']);
                        ?>
                        <img src="<?= htmlspecialchars($profilePicturePath) ?>" alt="Profile Picture" class="profile-picture" style="width: 150px; height: 150px; border-radius: 50%; object-fit: cover;">
                        <form action="../../action/upload_profile_picture.php" method="post" enctype="multipart/form-data">
                            <input type="file" name="profile_picture" accept="image/*" required>
                            <button type="submit" class="btn">Upload Picture</button>
                            <button type="button" class="btn" onclick="deleteProfilePicture()">Delete Picture</button>
                        </form>
                        <div id="profileView">
                            <p>Name: <span id="profileName"><?= htmlspecialchars($_SESSION['username']) ?></span></p>
                            <p>Email: <span id="profileEmail"><?= htmlspecialchars($userInfo['email']) ?></span></p>
                        </div>
                        <form id="profileEdit" class="hidden" action="../../action/update_profile.php" method="post">
                            <p>Name: <input type="text" name="username" id="editName" value="<?= htmlspecialchars($_SESSION['username']) ?>"></p>
                            <p>Email: <input type="email" name="email" id="editEmail" value="<?= htmlspecialchars($userInfo['email']) ?>" readonly></p>
                            <button type="submit" class="btn">Save</button>
                            <button type="button" class="btn" onclick="cancelEdit()">Cancel</button>
                        </form>
                        <p>Member since: <?= date('F Y', strtotime($userInfo['created_at'])) ?></p>
                    </div>
                </div>
            </div>
        </div>
        <div class="section">
        <h2>Upcoming Trips</h2>
            <div id="upcomingTrips" class="dashboard-section">
                <?php foreach ($upcomingTrips as $trip): ?>
                    <div class="card" id="trip-<?= $trip['preference_id']; ?>">
                        <div class="card-content">
                        <button class="trip-button" onclick="toggleTripDetails(this)">Trip to <?= htmlspecialchars($countries[$trip['destination_country_id']]) ?></button>
                            <div class="trip-details hidden">
                                <p>Travelling from: <?= htmlspecialchars($countries[$trip['origin_country_id']]) ?></p>
                                <p>To: <?= htmlspecialchars($countries[$trip['destination_country_id']]) ?></p>
                                <p>Departure: <?= htmlspecialchars($trip['travel_date']) ?></p>
                                <p>Return: <?= htmlspecialchars($trip['return_date']) ?></p>
                                <p>Description: <?= htmlspecialchars($trip['description']) ?></p>
                                <p>Budget: <?= htmlspecialchars($trip['budget']) ?></p>
                                <p>Travelers: <?= htmlspecialchars($trip['number_of_travelers']) ?></p>
                                <p>Accommodation: <?= htmlspecialchars($trip['accommodation_type']) ?></p>
                                <h3>Type of travelers I'm looking for</h3>
                                <p>Has Extra Space: <?= $trip['has_extra_space'] ? 'Yes' : 'No' ?></p>
                                <p>Needs Space: <?= $trip['needs_space'] ? 'Yes' : 'No' ?></p>
                                <p>Preferred Gender: <?= htmlspecialchars($trip['preferences']) ?></p>
                                <button class="btn" onclick="openEditTripModal(<?= $trip['preference_id']; ?>)">Update</button>
                                <button class="btn" onclick="deleteTrip(<?= $trip['preference_id']; ?>)">Delete</button>
                                <button class="btn" onclick="redirectToTravelers()">Find a Travel Match</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        </div>
        
        </div>
    </div>

<!-- Modal for creating a new trip -->
<div id="tripModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="toggleCreateTripModal()">&times;</span>
        <h2>Create a New Trip</h2>
        <form id="createTripForm" action="../../../MyTravelPal/action/new_trip_action.php" method="post" enctype="multipart/form-data" onsubmit="return validateTripForm()">
            <label for="origin_country">Origin Country:</label>
            <select id="origin_country" name="origin_country" required>
                <option value="">Select Origin</option>
                <?php foreach ($countries as $country_id => $country_name): ?>
                    <option value="<?= $country_id ?>"><?= $country_name ?></option>
                <?php endforeach; ?>
            </select>
            
            <label for="destination_country">Destination Country:</label>
            <select id="destination_country" name="destination_country" required>
                <option value="">Select Destination</option>
                <?php foreach ($countries as $country_id => $country_name): ?>
                    <option value="<?= $country_id ?>"><?= $country_name ?></option>
                <?php endforeach; ?>
            </select>
            
            <label for="departure">Departure Date:</label>
            <input type="date" id="departure" name="departure" required>
            
            <label for="return">Return Date:</label>
            <input type="date" id="return" name="return" required>
            
            <label for="description">Description:</label>
            <textarea id="description" name="description"></textarea>
            
            <label for="budget">Budget in $:</label>
            <input type="number" id="budget" name="budget" required>
            
            <label for="travelers">Number of Travelers:</label>
            <input type="number" id="travelers" name="travelers" required min="1" max="5">
            
            <label for="accommodation">Accommodation Type:</label>
            <select id="accommodation" name="accommodation" required>
                <option value="hotel">Hotel</option>
                <option value="hostel">Hostel</option>
                <option value="airbnb">Airbnb</option>
                <option value="other">Other</option>
            </select>
            
            <div class="radio-group">
                <label>Looking for someone:</label>
                <label><input type="radio" name="space" value="has_extra_space" required> Having Extra Space</label>
                <label><input type="radio" name="space" value="needs_extra_space" required> Needs Extra Space</label>
            </div>
            
            <div class="radio-group">
                <label>Preferred Gender:</label>
                <label><input type="radio" name="gender" value="male" required> Male</label>
                <label><input type="radio" name="gender" value="female" required> Female</label>
                <label><input type="radio" name="gender" value="other" required> Other</label>
            </div>
            
            <button type="submit">Create Trip</button>
        </form>
    </div>
</div>

<script>
    function validateTripForm() {
        // Get form values
        var originCountry = document.getElementById('origin_country').value;
        var destinationCountry = document.getElementById('destination_country').value;
        var departureDate = document.getElementById('departure').value;
        var returnDate = document.getElementById('return').value;
        var budget = document.getElementById('budget').value;
        var travelers = document.getElementById('travelers').value;
        var today = new Date().toISOString().split('T')[0]; // Current date in 'YYYY-MM-DD' format

        // Check if origin and destination are the same
        if (originCountry === destinationCountry) {
            alert("Origin and destination cannot be the same.");
            return false;
        }

        // Check if departure date is in the past
        if (departureDate < today) {
            alert("The departure date cannot be in the past.");
            return false;
        }

        // Check if return date is before departure date
        if (returnDate < departureDate) {
            alert("The return date cannot be before the departure date.");
            return false;
        }

        // Check if budget is a positive number
        if (budget <= 0) {
            alert("Budget must be a positive number.");
            return false;
        }

        // Check if the number of travelers is at least 1
        if (travelers < 1) {
            alert("There must be at least one traveler.");
            return false;
        }

        return true; // Allow form submission if all validations pass
    }
</script>



   <!-- Modal for editing a trip -->
<div id="editTripModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeEditTripModal()">&times;</span>
        <h2>Edit Trip</h2>
        <form id="editTripForm" action="../../../MyTravelPal/action/edit_trip.php" method="post" enctype="multipart/form-data" onsubmit="return validateEditTripForm()">
            <input type="hidden" name="preference_id" id="edit_preference_id">
            
            <label for="edit_origin_country">Origin Country:</label>
            <select id="edit_origin_country" name="origin_country" required>
                <option value="">Select Origin</option>
                <?php foreach ($countries as $country_id => $country_name): ?>
                    <option value="<?= $country_id ?>"><?= $country_name ?></option>
                <?php endforeach; ?>
            </select>
            
            <label for="edit_destination_country">Destination Country:</label>
            <select id="edit_destination_country" name="destination_country" required>
                <option value="">Select Destination</option>
                <?php foreach ($countries as $country_id => $country_name): ?>
                    <option value="<?= $country_id ?>"><?= $country_name ?></option>
                <?php endforeach; ?>
            </select>
            
            <label for="edit_departure_date">Departure Date:</label>
            <input type="date" id="edit_departure_date" name="departure_date" required>
            
            <label for="edit_return_date">Return Date:</label>
            <input type="date" id="edit_return_date" name="return_date" required>
            
            <label for="edit_description">Description:</label>
            <textarea id="edit_description" name="description"></textarea>
            
            <label for="edit_budget">Budget in $:</label>
            <input type="number" id="edit_budget" name="budget" required>
            
            <label for="edit_travelers">Number of Travelers:</label>
            <input type="number" id="edit_travelers" name="number_of_travelers" required min="1" max="5">
            
            <label for="edit_accommodation">Accommodation Type:</label>
            <select id="edit_accommodation" name="accommodation_type" required>
                <option value="hotel">Hotel</option>
                <option value="hostel">Hostel</option>
                <option value="airbnb">Airbnb</option>
                <option value="other">Other</option>
            </select>
            
            <div class="radio-group">
                <label>Looking for someone:</label>
                <label><input type="radio" name="space" value="has_extra_space" id="edit_space_has_extra_space" required> Having Extra Space</label>
                <label><input type="radio" name="space" value="needs_extra_space" id="edit_space_needs_extra_space" required> Needs Extra Space</label>
            </div>
            
            <div class="radio-group">
                <label>Preferred Gender:</label>
                <label><input type="radio" name="gender" value="male" id="edit_gender_male" required> Male</label>
                <label><input type="radio" name="gender" value="female" id="edit_gender_female" required> Female</label>
                <label><input type="radio" name="gender" value="other" id="edit_gender_other" required> Other</label>
            </div>
            
            <button type="submit">Save Changes</button>
        </form>
    </div>
</div>

<script>
    function validateEditTripForm() {
        // Get form values
        var originCountry = document.getElementById('edit_origin_country').value;
        var destinationCountry = document.getElementById('edit_destination_country').value;
        var departureDate = document.getElementById('edit_departure_date').value;
        var returnDate = document.getElementById('edit_return_date').value;
        var budget = document.getElementById('edit_budget').value;
        var travelers = document.getElementById('edit_travelers').value;
        var today = new Date().toISOString().split('T')[0]; // Current date in 'YYYY-MM-DD' format

        // Check if origin and destination are the same
        if (originCountry === destinationCountry) {
            alert("Origin and destination cannot be the same.");
            return false;
        }

        // Check if departure date is in the past
        if (departureDate < today) {
            alert("The departure date cannot be in the past.");
            return false;
        }

        // Check if return date is before departure date
        if (returnDate < departureDate) {
            alert("The return date cannot be before the departure date.");
            return false;
        }

        // Check if budget is a positive number
        if (budget <= 0) {
            alert("Budget must be a positive number.");
            return false;
        }

        // Check if the number of travelers is at least 1
        if (travelers < 1) {
            alert("There must be at least one traveler.");
            return false;
        }

        return true; // Allow form submission if all validations pass
    }
</script>


    <div class="footer">
        <p>&copy; 2024 TravelPal. All rights reserved. | <a href="/view/privacy-policy.php">Privacy Policy</a> | <a href="/view/terms-of-service.php">Terms of Service</a></p>
    </div>

    <script>
         $("#editTripForm").submit(function(event) {
            event.preventDefault();
            var formData = $(this).serialize();

            $.ajax({
                type: "POST",
                url: "../../action/edit_trip.php",
                data: formData,
                success: function(response) {
                    if (response.trim() === "success") {
                        var preferenceId = $('#edit_preference_id').val();
                        var updatedTrip = {
                            destination: $('#edit_destination_country option:selected').text(),
                            origin: $('#edit_origin_country option:selected').text(),
                            departure: $('#edit_departure_date').val(),
                            return: $('#edit_return_date').val(),
                            budget: $('#edit_budget').val(),
                            description: $('#edit_description').val(),
                            travelers: $('#edit_travelers').val(),
                            accommodation: $('#edit_accommodation option:selected').text(),
                            has_extra_space: $('input[name="space"]:checked').val() === 'has_extra_space' ? 'Yes' : 'No',
                            needs_space: $('input[name="space"]:checked').val() === 'needs_extra_space' ? 'Yes' : 'No',
                            preferences: $('input[name="gender"]:checked').val()
                        };

                        // Update the trip card content
                        var tripCard = $('#trip-' + preferenceId);
                        tripCard.find('h3').text('Trip to ' + updatedTrip.destination);
                        tripCard.find('.trip-details').html(`
                            <p>Travelling from: ${updatedTrip.origin}</p>
                            <p>To: ${updatedTrip.destination}</p>
                            <p>Departure: ${updatedTrip.departure}</p>
                            <p>Return: ${updatedTrip.return}</p>
                            <p>Description: ${updatedTrip.description}</p>
                            <p>Budget: ${updatedTrip.budget}</p>
                            <p>Travelers: ${updatedTrip.travelers}</p>
                            <p>Accommodation: ${updatedTrip.accommodation}</p>
                            <h3>Type of travelers I'm looking for</h3>
                            <p>Has Extra Space: ${updatedTrip.has_extra_space}</p>
                            <p>Needs Space: ${updatedTrip.needs_space}</p>
                            <p>Preferred Gender: ${updatedTrip.preferences}</p>
                            <button class="btn" onclick="openEditTripModal(${preferenceId})">Update</button>
                            <button class="btn" onclick="deleteTrip(${preferenceId})">Delete</button>
                        `);

                        alert("Trip updated successfully!");
                        closeEditTripModal();
                    } else {
                        alert("Failed to update trip: " + response);
                    }
                },
                error: function(xhr, status, error) {
                    console.error(xhr.responseText);
                    alert("An error occurred while updating the trip.");
                }
            });
        });

        function editProfile() {
            document.getElementById('profileView').style.display = 'none';
            document.getElementById('profileEdit').style.display = 'block';
        }

        function cancelEdit() {
            document.getElementById('profileView').style.display = 'block';
            document.getElementById('profileEdit').style.display = 'none';
        }


        function toggleDropdown() {
            var dropdown = document.querySelector('.dropdown');
            dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
        }

        function toggleCreateTripModal() {
            var modal = document.getElementById('tripModal');
            var mainContent = document.querySelector('.main-content');
            if (modal.style.display === 'flex') {
                modal.style.display = 'none';
                mainContent.classList.remove('blur');
            } else {
                modal.style.display = 'flex';
                mainContent.classList.add('blur');
            }
        }

        function openEditTripModal(preferenceId) {
            $.ajax({
                url: '../../../MyTravelPal/action/get_trip.php',
                type: 'GET',
                data: { preference_id: preferenceId },
                success: function(data) {
                    var trip = JSON.parse(data);
                    $('#edit_preference_id').val(trip.preference_id);
                    $('#edit_origin_country').val(trip.origin_country_id);
                    $('#edit_destination_country').val(trip.destination_country_id);
                    $('#edit_departure_date').val(trip.travel_date);
                    $('#edit_return_date').val(trip.return_date);
                    $('#edit_description').val(trip.description);
                    $('#edit_budget').val(trip.budget);
                    $('#edit_travelers').val(trip.number_of_travelers);
                    $('#edit_accommodation').val(trip.accommodation_type);
                    if (trip.has_extra_space == 1) {
                        $('#edit_space_has_extra_space').prop('checked', true);
                    } else {
                        $('#edit_space_needs_extra_space').prop('checked', true);
                    }
                    if (trip.preferences == 'male') {
                        $('#edit_gender_male').prop('checked', true);
                    } else if (trip.preferences == 'female') {
                        $('#edit_gender_female').prop('checked', true);
                    } else {
                        $('#edit_gender_other').prop('checked', true);
                    }

                    $('#editTripModal').css('display', 'flex');
                },
                error: function() {
                    alert('Failed to fetch trip data.');
                }
            });
        }

        function closeEditTripModal() {
            $('#editTripModal').css('display', 'none');
        }

        function deleteProfilePicture() {
            if (confirm('Are you sure you want to delete your profile picture?')) {
                $.ajax({
                    url: '../../action/delete_profile_picture.php',
                    type: 'POST',
                    success: function(response) {
                        var result = JSON.parse(response);
                        if (result.status === 'success') {
                            alert('Profile picture deleted successfully.');
                            // Update the profile picture to default
                            var defaultAvatar = 'https://rawcdn.githack.com/Kabi12Blessing/72892025_ChurningPrediction/c2e416446c7e05056259be4e948de42f070a8e6c/266033.png';
                            document.querySelector('.profile-picture').src = defaultAvatar;
                        } else {
                            alert('Error: ' + result.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error(xhr.responseText);
                        alert('An error occurred while deleting the profile picture.');
                    }
                });
            }
        }

        function redirectToTravelers() {
        window.location.href = 'view_travelers.php';
    }
    function deleteTrip(preferenceId) {
    if (confirm('Are you sure you want to delete this trip?')) {
        $.ajax({
            url: '../../../MyTravelPal/action/delete_trip.php', // Correct path to the PHP script
            type: 'GET',
            data: { preference_id: preferenceId },
            success: function(response) {
                if (response.trim() === 'success') {
                    $('#trip-' + preferenceId).remove(); // Remove the trip from the DOM
                    alert('Trip deleted successfully.');
                } else {
                    alert('Failed to delete the trip.');
                }
            },
            error: function() {
                alert('Error occurred while deleting the trip.');
            }
        });
    }
}




        function toggleTripDetails(element) {
            var details = element.nextElementSibling;
            if (details.classList.contains('hidden')) {
                details.classList.remove('hidden');
            } else {
                details.classList.add('hidden');
            }
        }

        // Close the dropdown if the user clicks outside of it
        window.onclick = function(event) {
            if (!event.target.matches('.username')) {
                var dropdowns = document.querySelectorAll('.dropdown');
                for (var i = 0; i < dropdowns.length; i++) {
                    var openDropdown = dropdowns[i];
                    if (openDropdown.style.display === 'block') {
                        openDropdown.style.display = 'none';
                    }
                }
            }

            // Close the modal if the user clicks outside of it
            if (event.target == document.getElementById('tripModal')) {
                document.getElementById('tripModal').style.display = 'none';
                document.querySelector('.main-content').classList.remove('blur');
            }
            if (event.target == document.getElementById('editTripModal')) {
                document.getElementById('editTripModal').style.display = 'none';
                document.querySelector('.main-content').classList.remove('blur');
            }
        }

        function toggleDarkMode() {
            document.body.classList.toggle('dark-mode');
            // Save dark mode preference in local storage
            if (document.body.classList.contains('dark-mode')) {
                localStorage.setItem('darkMode', 'enabled');
            } else {
                localStorage.setItem('darkMode', 'disabled');
            }
        }

        // Apply dark mode preference on page load
        document.addEventListener('DOMContentLoaded', (event) => {
            if (localStorage.getItem('darkMode') === 'enabled') {
                document.body.classList.add('dark-mode');
            }
        });
    </script>
</body>
</html>
