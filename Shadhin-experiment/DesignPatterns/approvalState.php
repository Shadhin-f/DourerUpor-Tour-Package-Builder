<?php
interface PackageState {
    public function approve($package);
    public function reject($package);
    public function getPendingCount();
    public function getStateName();
}

// PendingState.php
class PendingState implements PackageState {
    public function approve($package) {
        $package->setState(new ApprovedState());
        return true;
    }
    
    public function reject($package) {
        $package->setState(new RejectedState());
        return true;
    }
    
    public function getPendingCount() {
        // Connect to DB and count pending packages
        $db = Database::getInstance();
        $conn = $db->getConnection();
        $stmt = $conn->prepare("SELECT COUNT(*) FROM packages WHERE status = 'pending'");
        $stmt->execute();
        return $stmt->fetchColumn();
    }
    
    public function getStateName() {
        return 'pending';
    }
}

// ApprovedState.php
class ApprovedState implements PackageState {
    public function approve($package) {
        // Package is already approved
        return false;
    }
    
    public function reject($package) {
        // $package->setState(new RejectedState());
        return false;
    }
    
    public function getPendingCount() {
        return 0;
    }
    
    public function getStateName() {
        return 'approved';
    }
}

// RejectedState.php
class RejectedState implements PackageState {
    public function approve($package) {
        $package->setState(new ApprovedState());
        return true;
    }
    
    public function reject($package) {
        // Package is already rejected
        return false;
    }
    
    public function getPendingCount() {
        return 0;
    }
    
    public function getStateName() {
        return 'rejected';
    }
}


class Package {
    private $packageId;
    private $packageName;
    private $state;
    private $conn;
    
    public function __construct($packageId = null) {
        $this->state = new PendingState();
        $this->conn = Database::getInstance()->getConnection();
        
        if ($packageId) {
            $this->packageId = $packageId;
            $this->loadPackage();
        }
    }
    
    // Add the getId method
    public function getId() {
        return $this->packageId;
    }
    
    // Add the getPackageInfo method
    public function getPackageInfo($packageId) {
        $stmt = $this->conn->prepare("SELECT p.*, u.name as creator_name 
                                     FROM packages p 
                                     JOIN user u ON p.build_by = u.id 
                                     WHERE p.package_id = ?");
        $stmt->execute([$packageId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function loadPackage() {
        $stmt = $this->conn->prepare("SELECT package_name, status FROM packages WHERE package_id = ?");
        $stmt->execute([$this->packageId]);
        $package = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($package) {
            $this->packageName = $package['package_name'];
            
            // Set the appropriate state based on the database status
            switch($package['status']) {
                case 'approved':
                    $this->state = new ApprovedState();
                    break;
                case 'rejected':
                    $this->state = new RejectedState();
                    break;
                default:
                    $this->state = new PendingState();
            }
        }
    }
    
    public function setState(PackageState $state) {
        $this->state = $state;
        
        // Update the package status in the database
        $stmt = $this->conn->prepare("UPDATE packages SET status = ? WHERE package_id = ?");
        $stmt->execute([$state->getStateName(), $this->packageId]);
    }
    
    public function approve() {
        if ($this->state->approve($this)) {
            // Notify the package creator that their package was approved
            $this->notifyCreator('approved');
            return true;
        }
        return false;
    }
    
    public function reject($feedback = '') {
        if ($this->state->reject($this)) {
            // Update the database with rejection feedback
            $stmt = $this->conn->prepare("UPDATE packages SET rejection_feedback = ? WHERE package_id = ?");
            $stmt->execute([$feedback, $this->packageId]);
            
            // Notify the creator that their package was rejected with feedback
            $this->notifyCreator('rejected', $feedback);
            return true;
        }
        return false;
    }
    
    private function notifyCreator($action, $feedback = '') {
        // Get the user who built this package
        $stmt = $this->conn->prepare("SELECT build_by FROM packages WHERE package_id = ?");
        $stmt->execute([$this->packageId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $userId = $result['build_by'];
            
            if ($action === 'rejected' && !empty($feedback)) {
                $message = "Your package '{$this->packageName}' has been {$action}. Feedback: {$feedback}";
            } else {
                $message = "Your package '{$this->packageName}' has been {$action}.";
            }
            
            // Insert notification
            $stmt = $this->conn->prepare("INSERT INTO notifications (user_id, package_id, message, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$userId, $this->packageId, $message]);
        }
    }
    
    public function getPendingCount() {
        return $this->state->getPendingCount();
    }
    
    public function getStateName() {
        return $this->state->getStateName();
    }
}