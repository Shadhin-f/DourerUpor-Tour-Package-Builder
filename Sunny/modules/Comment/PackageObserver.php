<?php
// Subject (Observable) interface
interface Subject
{
    public function attach(Observer $observer, $package_id);
    public function detach(Observer $observer, $package_id);
    public function notify($package_id, $message);
}

// Observer interface
interface Observer
{
    public function update($package_id, $message); //parameter a package_id and ekta msg pass
}



// Concrete Subject implementation
class PackageSubject implements Subject
{
    private $observers = []; //array of observer
    private static $instance = null;  //ensures that there is only one object of PackageSubject



    // Singleton pattern to have one observer manager
    private function __construct()
    {}    //constructor is private so that no one can create a new object using new.


    public static function getInstance()
    {
        if (self::$instance === null) { //checking obj ache kina
            self::$instance = new self(); //if not then create one
        }
        return self::$instance;
    }




    public function attach(Observer $observer, $package_id)
    {
        if (!isset($this->observers[$package_id])) {
            $this->observers[$package_id] = []; //If that package has no followers yet, make an empty list for it.
        }
        $this->observers[$package_id][] = $observer;  //Add the observer (user) to that list
    }

    public function detach(Observer $observer, $package_id)
    {
        if (isset($this->observers[$package_id])) {
            foreach ($this->observers[$package_id] as $key => $obs) {
                if ($obs === $observer) {
                    unset($this->observers[$package_id][$key]);
                    break;
                }
            }
        }
    }
    //Check if followers exist.
    //If one matches the observer we want to remove, delete it.


    
    public function notify($package_id, $message)
    {
        if (isset($this->observers[$package_id])) {
            foreach ($this->observers[$package_id] as $observer) {
                $observer->update($package_id, $message);
            }

        } else {
            error_log("No observers found for package_id: $package_id");
        }
    }

    // If followers exist: Loop through each observer and call their update() method.






    // Load all followers as observers for a package
    public function loadFollowersAsObservers($package_id, $conn) //package_id: The ID of the package we are working on.

                                                                       // conn: The database connection to run SQL queries.
    {
        $stmt = $conn->prepare("SELECT user_id FROM package_followers WHERE package_id = ?");
        $stmt->execute([$package_id]); //Runs the query with the real $package_id value.

        $followers = $stmt->fetchAll(PDO::FETCH_ASSOC); //Store the result in $followers //associative array



        foreach ($followers as $follower) {
            $observer = new UserObserver($follower['user_id'], $conn);  //Pass the user_id and the conn to the constructor of UserObserver.
            $this->attach($observer, $package_id);  
        }
    }
}

// Concrete Observer implementation
class UserObserver implements Observer
{
    private $user_id;
    private $conn;

    public function __construct($user_id, $conn)
    {
        $this->user_id = $user_id;
        $this->conn = $conn;
    }

    public function update($package_id, $message)
    {
        // Insert notification into database
        $stmt = $this->conn->prepare("INSERT INTO notifications (user_id, package_id, message, created_at, is_read) VALUES (?, ?, ?, NOW(), 0)");
        $stmt->execute([$this->user_id, $package_id, $message]);
    }
}
?>