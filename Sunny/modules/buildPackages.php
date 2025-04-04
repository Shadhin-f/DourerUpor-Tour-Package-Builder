<?php
session_start();
include_once('../db_connection/db.php');
include_once('../DesignPatterns/PackageBuilder.php');

// Database connection
$db = Database::getInstance();
$conn = $db->getConnection();

// Fetch destinations (filtering only for Bangladesh)
$destinations = [];
$stmt = $conn->prepare("SELECT destination_id, name, country, type, cost FROM destinations WHERE country = 'Bangladesh'");
$stmt->execute();
$destinations = $stmt->fetchAll(PDO::FETCH_ASSOC);

$user_id = $_SESSION['user_id'] ?? 'unknown'; // Get user ID or default to 'unknown'

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $packageName = $_POST['package_name'];
    $details = $_POST['details'];
    $buildBy = 'User'; // Replace with session user if available
    $imageName = $user_id . '.jpg';
    $imagePath = "../DesignPatterns/uploaded_img/" . $imageName;

    // Handle Image Upload
    if (!empty($_FILES['package_image']['name'])) {
        $allowedExtensions = ['jpg', 'jpeg', 'png'];
        $fileExtension = strtolower(pathinfo($_FILES['package_image']['name'], PATHINFO_EXTENSION));

        if (in_array($fileExtension, $allowedExtensions)) {
            move_uploaded_file($_FILES['package_image']['tmp_name'], $imagePath);
        } else {
            echo "Invalid file type. Only JPG and PNG are allowed.";
            exit;
        }
    }

    // Convert selected destination IDs into full details
    $selectedDestinations = [];
    if (!empty($_POST['destinations'])) {
        $placeholders = implode(',', array_fill(0, count($_POST['destinations']), '?'));
        $stmt = $conn->prepare("SELECT * FROM destinations WHERE destination_id IN ($placeholders)");
        $stmt->execute($_POST['destinations']);
        $selectedDestinations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Initialize the builder and director
    $builder = new TourPackageBuilder($selectedDestinations);
    $director = new PackageDirector($builder);

    // Loop through each destination and pass the user input to the builder
    foreach ($_POST['destinations'] as $index => $destinationId) {
        $moneySaved = $_POST['money_saved'][$index] ?? 0;
        $dayCount = $_POST['day_count'][$index] ?? 1;
        $pickup = !empty($_POST['pickup'][$index]) ? $_POST['pickup'][$index] : NULL;
        $transportType = !empty($_POST['transport_type'][$index]) ? $_POST['transport_type'][$index] : NULL;
        $cost = $_POST['cost'][$index] ?? 0;

        $director->buildPackage($moneySaved, $dayCount, $pickup, $transportType, $cost, $details, $imageName);

    }

    // Get the constructed package
    $package = $builder->getPackage();

    // Insert into packages table
    $stmt = $conn->prepare("INSERT INTO packages (package_name, publish_time, build_by, status, details, image) 
                            VALUES (?, NOW(), ?, 'Pending', ?, ?)");
    $stmt->execute([$packageName, $buildBy, $details, $imageName]);
    $packageId = $conn->lastInsertId();

    // Insert selected destinations into package_details
    $stepNumber = 1;
    foreach ($selectedDestinations as $index => $destination) {
        $pickup = $_POST['pickup'][$index] ?? NULL;
        $transportType = $_POST['transport_type'][$index] ?? NULL;
        $moneySaved = $_POST['money_saved'][$index] ?? 0;
        $dayCount = $_POST['day_count'][$index] ?? 1;
        $cost = $_POST['cost'][$index] ?? 0;

        $stmt = $conn->prepare("INSERT INTO package_details (package_id, destination_id, step_number, money_saved, day_count, pickup, transport_type, cost) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$packageId, $destination['destination_id'], $stepNumber, $moneySaved, $dayCount, $pickup, $transportType, $cost]);
        $stepNumber++;
    }

    echo "Package successfully created!";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Build a Package</title>
    <style>
        body {
            font-family: Arial, sans-serif;
        }
        form {
            width: 50%;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .step, .details-container, .image-container {
            margin-bottom: 20px;
        }
        input, select, textarea {
            width: 100%;
            padding: 10px;
            margin: 5px 0;
            border-radius: 4px;
            border: 1px solid #ccc;
        }
        button {
            background-color: #28a745;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        button:disabled {
            background-color: #ccc;
        }
    </style>
    <script>
        function addStep() {
            let stepsContainer = document.getElementById("steps-container");
            let stepHtml = `
                <div class="step">
                    <select name="destinations[]" onchange="populateFields(this)" required>
                        <option value="" disabled selected>Select Destination</option>
                        <?php foreach ($destinations as $destination): ?>
                            <option value="<?php echo $destination['destination_id']; ?>" data-cost="<?php echo $destination['cost']; ?>">
                                <?php echo htmlspecialchars($destination['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="number" name="cost[]" placeholder="Cost" readonly>
                    <input type="number" name="money_saved[]" placeholder="Money Saved" required>
                    <input type="number" name="day_count[]" placeholder="Days" required>
                    <input type="text" name="pickup[]" placeholder="Pickup (Optional)">
                    <input type="text" name="transport_type[]" placeholder="Transport Type (Optional)">
                    <button type="button" onclick="this.parentElement.remove(); checkPublishButton();">Remove</button>
                </div>`;
            stepsContainer.insertAdjacentHTML('beforeend', stepHtml);
            checkPublishButton();
        }

        function populateFields(selectElement) {
            let selectedOption = selectElement.options[selectElement.selectedIndex];
            let stepDiv = selectElement.parentElement;
            stepDiv.querySelector("input[name='cost[]']").value = selectedOption.getAttribute("data-cost");
        }

        function checkPublishButton() {
            let publishButton = document.getElementById("publish-button");
            let stepsContainer = document.getElementById("steps-container");
            publishButton.disabled = stepsContainer.children.length === 0;
        }
    </script>
</head>
<body>
    <h1>Build Your Own Package</h1>
    <form method="POST" enctype="multipart/form-data">
        <input type="text" name="package_name" placeholder="Package Name" required>
        
        <!-- Details Section -->
        <div class="details-container">
            <h3>Package Details</h3>
            <textarea name="details" rows="5" placeholder="Describe your package..." required></textarea>
        </div>

        <!-- Image Upload -->
        <div class="image-container">
            <h3>Upload Package Image</h3>
            <input type="file" name="package_image" accept=".jpg,.jpeg,.png" required>
        </div>

        <div id="steps-container"></div>
        <button type="button" onclick="addStep()">Add Destination</button>
        <button type="submit" id="publish-button" disabled>Publish</button>
    </form>
</body>
</html>
