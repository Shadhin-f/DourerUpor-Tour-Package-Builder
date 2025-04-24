<?php

require_once 'c:\xampp\htdocs\DourerUpor-Tour-Package-Builder\main\Login\StrategiesForTesting\GitHubLogin.php';

// Simulate GitHub data returned after OAuth
$fakeGitHubData = [
    "email" => "githubuser@example.com",
    "name" => "Github User"
];

try {
    // Create a new GitHubLogin instance
    $login = new GitHubLogin();

    // Call the login method with the simulated data
    $login->login($fakeGitHubData);

    echo "Login successful. User session started." . PHP_EOL;
    echo "Session Email: " . $_SESSION['email'] . PHP_EOL;
    echo "Session User ID: " . $_SESSION['user_id'] . PHP_EOL;

} catch (Exception $e) {
    echo "Error during login: " . $e->getMessage() . PHP_EOL;
}
