<?php
require_once 'c:\xampp\htdocs\DourerUpor-Tour-Package-Builder\main\Login\StrategiesForTesting\GoogleLogin.php';

// Simulate Google data returned after OAuth
$fakeGoogleData = [
    "email" => "googleuser@example.com",
    "name" => "Google User"
];

try {
    // Create a new GoogleLogin instance
    $login = new GoogleLogin();

    // Call the login method with the simulated data
    $login->login($fakeGoogleData);

    echo "Login successful. User session started." . PHP_EOL;
    echo "Session Email: " . $_SESSION['email'] . PHP_EOL;
    echo "Session User ID: " . $_SESSION['user_id'] . PHP_EOL;
} catch (Exception $e) {
    echo "Error during login: " . $e->getMessage() . PHP_EOL;
}
