<?php
use PHPUnit\Framework\TestCase;

require_once 'C:\xampp\htdocs\DourerUpor-Tour-Package-Builder\main\db_connection\db.php';
require_once 'C:\xampp\htdocs\DourerUpor-Tour-Package-Builder\main\DesignPatterns\approvalState.php';

class StatePatternTest extends TestCase
{
    private $conn;
    private $packageId;

    protected function setUp(): void
    {
        $this->conn = Database::getInstance()->getConnection();
        $stmt = $this->conn->prepare("INSERT INTO packages (package_name, status, build_by) VALUES (?, ?, ?)");
        $stmt->execute(['Test Package', 'pending', 1]);
        $this->packageId = $this->conn->lastInsertId();
    }

    protected function tearDown(): void
    {
        $this->conn->prepare("DELETE FROM packages WHERE package_id = ?")->execute([$this->packageId]);
        $this->conn->prepare("DELETE FROM notifications WHERE package_id = ?")->execute([$this->packageId]);
    }

    public function testInitialStateIsPending()
    {
        echo "Running test: testInitialStateIsPending\n"; // Debugging
        $package = new Package($this->packageId);
        $state = $package->getStateName();
        echo "Initial State: $state\n"; // Debugging
        $this->assertEquals('pending', $state);
    }

    public function testApproveChangesStateToApproved()
    {
        echo "Running test: testApproveChangesStateToApproved\n"; // Debugging
        $package = new Package($this->packageId);
        $package->approve();
        $state = $package->getStateName();
        echo "Current State after approve: $state\n"; // Debugging
        $this->assertEquals('approved', $state);
    }

    public function testRejectAfterApprovalFails()
    {
        echo "Running test: testRejectAfterApprovalFails\n"; // Debugging
        $package = new Package($this->packageId);
        $package->approve();
        echo "State after approval: " . $package->getStateName() . "\n"; // Debugging

        $result = $package->reject("Feedback");
        echo "Reject result: $result\n"; // Debugging
        $state = $package->getStateName();
        echo "State after reject attempt: $state\n"; // Debugging

        $this->assertFalse($result); // We expect it to fail 
        $this->assertEquals('approved', $state); // And stay 'approved'
    }
}
