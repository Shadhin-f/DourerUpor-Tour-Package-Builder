<?php
require_once 'LoginStrategy.php';
require_once '../db_connection/db.php';

class GoogleLogin implements LoginStrategy
{
    public function login($data)
    {
        $db = Database::getInstance();
        $conn = $db->getConnection();

        $email = $data["email"];
        $name = $data["name"];

        // Check if the user already exists in the database
        $stmt = $conn->prepare("SELECT * FROM user WHERE email = :email");
        $stmt->execute(['email' => $email]);

        if ($stmt->rowCount() == 0) {
            // If user doesn't exist, register them
            $stmt = $conn->prepare("INSERT INTO user (name, email) VALUES (:name, :email)");
            $stmt->execute(['name' => $name, 'email' => $email]);
        }

        // Fetch the user_id from the database
        $stmt = $conn->prepare("SELECT id FROM user WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Start a session and store user info
        session_start();
        $_SESSION['email'] = $email;
        $_SESSION['user_id'] = $user['id']; // Set the user_id session

        header("Location: ../home.php");
        exit();
    }
}
?>