<?php
require_once 'c:\xampp\htdocs\DourerUpor-Tour-Package-Builder\main\Login\StrategiesForTesting\NormalLogin.php';

// Simulate login form submission
$fakeLoginData = [
    "loginEmail" => "normaluser@example.com",
    "loginPassword" => "userpassword"  // Make sure this matches the password in your DB
];

try {
    // Create a new NormalLogin instance
    $login = new NormalLogin();

    // Call the login method with the simulated data
    $login->login($fakeLoginData);

    echo "Login attempted." . PHP_EOL;
    echo "Session Email: " . ($_SESSION['email'] ?? 'Not Set') . PHP_EOL;
    echo "Session User ID: " . ($_SESSION['user_id'] ?? 'Not Set') . PHP_EOL;

} catch (Exception $e) {
    echo "Error during login: " . $e->getMessage() . PHP_EOL;
}
