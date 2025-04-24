<?php
// Start session
session_start();
$_SESSION['current-page'] = 'profile';
$active_page = 'profile';

// Set user ID (using ID 3 as requested)

$userId = $_SESSION['user_id'] ?? 'unknown';
// $userId = 1;
// Database connection
require_once 'db_connection/db.php';
$db = Database::getInstance();
$conn = $db->getConnection();

// Function to get user data
function getUserData($conn, $userId) {
    $query = "SELECT id, name, email, dob, country FROM user WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$userId]);
    
    if ($stmt->rowCount() > 0) {
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        return null;
    }
}

// Function to get packages created by user
function getCreatedPackages($conn, $userId) {
    $query = "SELECT package_id, package_name, publish_time, status, rejection_feedback FROM packages WHERE build_by = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$userId]);
    
    $packages = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $packages[] = $row;
    }
    
    return $packages;
}

// Function to get packages followed by user
function getFollowedPackages($conn, $userId) {
    $query = "SELECT p.package_id, p.package_name, pf.time 
              FROM package_followers pf 
              JOIN packages p ON pf.package_id = p.package_id 
              WHERE pf.user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$userId]);
    
    $packages = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $packages[] = $row;
    }
    
    return $packages;
}

// Function to get user notifications
// function getUserNotifications($conn, $userId, $limit = 5) {
//     $query = "SELECT n.id, n.message, n.created_at, n.is_read, n.package_id
//               FROM notifications n 
//               WHERE n.user_id = ? 
//               ORDER BY n.created_at DESC
//               LIMIT ?";
//     $stmt = $conn->prepare($query);
//     $stmt->execute([$userId, $limit]);
    
//     $notifications = [];
//     while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
//         $notifications[] = $row;
//     }
    
//     return $notifications;
// }


function getUserNotifications($conn, $userId, $limit = 10) {
    $limit = (int)$limit; // Sanitize to ensure it's an integer

    $query = "SELECT n.id, n.message, n.created_at, n.is_read, n.package_id
              FROM notifications n 
              WHERE n.user_id = ? 
              ORDER BY n.created_at DESC
              LIMIT $limit"; // Inject directly

    $stmt = $conn->prepare($query);
    $stmt->execute([$userId]);

    $notifications = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $notifications[] = $row;
    }

    return $notifications;
}




// Function to count unread notifications
function countUnreadNotifications($conn, $userId) {
    $query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
    $stmt = $conn->prepare($query);
    $stmt->execute([$userId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result['count'] ?? 0;
}

// Function to display a notification -- Work from here 
function displayNotification($notification) {
    $readClass = $notification['is_read'] ? 'read' : 'unread';
    $formattedDate = formatDate($notification['created_at'], true);
    
    return "<div class='notification-item {$readClass}' data-id='{$notification['id']}'>
                <div class='notification-message'>{$notification['message']}</div>
                <div class='notification-time'>{$formattedDate}</div>
            </div>";
}

// Get data from database
$userData = getUserData($conn, $userId);
$createdPackages = getCreatedPackages($conn, $userId);
$followedPackages = getFollowedPackages($conn, $userId);
$notifications = getUserNotifications($conn, $userId);
$unreadCount = countUnreadNotifications($conn, $userId);

// Generate user initials
function getInitials($name) {
    $words = explode(' ', $name);
    $initials = '';
    foreach ($words as $word) {
        $initials .= strtoupper(substr($word, 0, 1));
    }
    return $initials;
}

// Format date
function formatDate($dateString, $includeTime = false) {
    $date = new DateTime($dateString);
    return $includeTime ? $date->format('M j, Y g:i A') : $date->format('F j, Y');
}

// Mark notifications as read when viewed
if (isset($_POST['mark_read']) && $_POST['mark_read'] == 1) {
    $notificationId = $_POST['notification_id'] ?? 0;
    
    if ($notificationId > 0) {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$notificationId, $userId]);
        
        echo json_encode(['success' => true]);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" type="text/css" href="profile.css?v=<?php echo time(); ?>">
    <style>
        /* Additional styles for notifications */
        .notification-item {
            padding: 12px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
        }
        
        .notification-item:hover {
            background-color: #f9f9f9;
        }
        
        .notification-item.unread {
            background-color: #f0f7ff;
        }
        
        .notification-message {
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .notification-time {
            font-size: 12px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($userData): ?>
        <!-- User Profile Card -->
        <div class="profile-card">
            <div class="profile-header">
                <!-- Notification Icon -->
                <div class="notification-container">
                    <div class="notification-icon" id="notificationIcon">
                        <i class="fas fa-bell" style="color: white; font-size: 18px;"></i>
                        <?php if ($unreadCount > 0): ?>
                        <div class="notification-badge"><?php echo $unreadCount; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Notification Dropdown -->
                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="notification-header">
                            <div class="notification-title">Notifications</div>
                            <div class="notification-count"><?php echo $unreadCount; ?> new</div>
                        </div>
                        <div class="notification-list">
                            <?php 
                            if (count($notifications) > 0) {
                                foreach ($notifications as $notification) {
                                    echo displayNotification($notification);
                                }
                            } else {
                                echo "<div class='no-notifications'>No notifications yet.</div>";
                            }
                            ?>
                        </div>
                    </div>
                </div>
                
                <div class="profile-img"><?php echo getInitials($userData['name']); ?></div>
                <h2 class="profile-name"><?php echo htmlspecialchars($userData['name']); ?></h2>
                <p class="profile-email"><?php echo htmlspecialchars($userData['email']); ?></p>
            </div>
            <div class="profile-details">
                <div class="detail-item">
                    <div class="detail-label">Name</div>
                    <div class="detail-value"><?php echo htmlspecialchars($userData['name']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Email</div>
                    <div class="detail-value"><?php echo htmlspecialchars($userData['email']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Country</div>
                    <div class="detail-value"><?php echo htmlspecialchars($userData['country']); ?></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Date of Birth</div>
                    <div class="detail-value"><?php echo formatDate($userData['dob']); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Rest of the profile code remains unchanged -->
        
        <!-- Created Packages Section -->
        <div class="section-card">
            <div class="section-title">Packages Created by You</div>
            <div class="section-content">
                <?php if (count($createdPackages) > 0): ?>
                    <?php foreach ($createdPackages as $package): ?>
                       <!-- Inside the Created Packages Section -->
                        <div class="package-item">
                            <div class="package-info">
                                <a href="package.php?id=<?php echo $package['package_id']; ?>" class="package-name">
                                    <?php echo htmlspecialchars($package['package_name']); ?>
                                </a>
                                <div class="package-date">Created on: <?php echo formatDate($package['publish_time']); ?></div>
                                <div class="package-status <?php echo $package['status']; ?>">
                                    Status: <?php echo ucfirst($package['status']); ?>
                                </div>
                                
                                <?php if ($package['status'] == 'rejected' && !empty($package['rejection_feedback'])): ?>
                                <div class="rejection-feedback">
                                    <i class="fas fa-info-circle"></i> Feedback: <?php echo htmlspecialchars($package['rejection_feedback']); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <a href="buildAndShare.php?id=<?php echo $package['package_id']; ?>" class="btn btn-small">
                                    <?php echo ($package['status'] == 'rejected') ? 'Edit & Resubmit' : 'Edit'; ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-packages">You haven't created any packages yet.</div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Followed Packages Section -->
        <div class="section-card">
            <div class="section-title">Packages You Follow</div>
            <div class="section-content">
                <?php if (count($followedPackages) > 0): ?>
                    <?php foreach ($followedPackages as $package): ?>
                        <div class="package-item">
                            <div class="package-info">
                                <a href="package.php?id=<?php echo $package['package_id']; ?>" class="package-name">
                                    <?php echo htmlspecialchars($package['package_name']); ?>
                                </a>
                                <div class="package-date">Followed on: <?php echo formatDate($package['time']); ?></div>
                            </div>
                            <!-- <div>
                                <a href="unfollow-package.php?id=<?php echo $package['package_id']; ?>&user_id=<?php echo $userId; ?>" class="btn btn-small">Unfollow</a>
                            </div> -->
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="no-packages">You're not following any packages.</div>
                <?php endif; ?>
            </div>
        </div>
        <?php else: ?>
            <div class="error">User not found!</div>
        <?php endif; ?>
    </div>

    <script>
        // Toggle notification dropdown
        document.getElementById('notificationIcon').addEventListener('click', function() {
            document.getElementById('notificationDropdown').classList.toggle('show');
        });
        
        // Close the dropdown when clicking outside of it
        window.addEventListener('click', function(event) {
            if (!event.target.matches('.notification-icon') && 
                !event.target.matches('.fas.fa-bell')) {
                var dropdown = document.getElementById('notificationDropdown');
                if (dropdown.classList.contains('show')) {
                    dropdown.classList.remove('show');
                }
            }
        });
        
        // Mark notifications as read when clicked
        document.querySelectorAll('.notification-item').forEach(item => {
            item.addEventListener('click', function() {
                const notificationId = this.getAttribute('data-id');
                if (this.classList.contains('unread')) {
                    fetch('mark_notification_read.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'mark_read=1&notification_id=' + notificationId
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            this.classList.remove('unread');
                            this.classList.add('read');
                            
                            // Update unread count in the badge
                            const badge = document.querySelector('.notification-badge');
                            const countElement = document.querySelector('.notification-count');
                            let currentCount = parseInt(badge.textContent);
                            currentCount--;
                            
                            if (currentCount <= 0) {
                                badge.style.display = 'none';
                            } else {
                                badge.textContent = currentCount;
                            }
                            
                            countElement.textContent = currentCount + ' new';
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>