<?php
// session_start();

require_once('db_connection\db.php');
// include_once('../DesignPatterns/PackageBuilder.php');
//include_once('../DesignPatterns/PackageObserver.php'); // Include the Observer Pattern

// Database connection

$db = Database::getInstance();
$conn = $db->getConnection();

// Check if we're editing an existing package
$isEditMode = false;
$packageData = null;
$packageDetails = [];

if (isset($_GET['id']) && !empty($_GET['id'])) {
    $package_id = $_GET['id'];
    $isEditMode = true;
    
    // Fetch package data
    $stmt = $conn->prepare("SELECT * FROM packages WHERE package_id = ?");
    $stmt->execute([$package_id]);
    $packageData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$packageData) {
        echo "Package not found!";
        exit;
    }
    
    // Fetch package details if any exist (for full mode packages)
    $stmt = $conn->prepare("SELECT * FROM package_details WHERE package_id = ? ORDER BY step_number");
    $stmt->execute([$package_id]);
    $packageDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch destinations (filtering only for Bangladesh)
$destinations = [];
$stmt = $conn->prepare("SELECT destination_id, name, country, type, cost FROM destinations WHERE country = 'Bangladesh'");
$stmt->execute();
$destinations = $stmt->fetchAll(PDO::FETCH_ASSOC);

$user_id = $_SESSION['user_id'] ?? 'unknown';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $packageName = $_POST['package_name'];
    $details = $_POST['details'];
    $buildBy = $user_id;
    $mode = $_POST['mode'] ?? 'basic';
    
    // Handle package ID (new or existing)
    if ($isEditMode) {
        $packageId = $packageData['package_id'];
    } else {
        $stmt = $conn->query("SELECT MAX(package_id) as max_id FROM packages");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $packageId = ($result['max_id'] ?? 0) + 1;
    }

    $imageName = $packageData['image'] ?? ($packageId . '.jpg');
    $imagePath = "img/package-cover/" . $imageName;

    // Handle image upload if a new image is provided
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





    $builder = new TourPackageBuilder();  //creating an object of the TourPackageBuilder class
    $director = new PackageDirector($builder);  //object is stored in the variable $builder

    if ($mode === 'full')  //These lines collect form inputs submitted by the user.
     {
        $destinationIds = $_POST['destinations'] ?? [];
        $moneySaved = $_POST['money_saved'] ?? [];
        $dayCount = $_POST['day_count'] ?? [];
        $pickupIds = $_POST['pickup'] ?? [];
        $transportType = $_POST['transport_type'] ?? [];
        $transportCost = $_POST['transport_cost'] ?? [];

        if (!empty($destinationIds)) //This block gets destination names from the database.
        {  
            $placeholders = implode(',', array_fill(0, count($destinationIds), '?'));
            $stmt = $conn->prepare("SELECT destination_id, name FROM destinations WHERE destination_id IN ($placeholders)");
            $stmt->execute($destinationIds);
            $selectedDestinations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $director->buildFullPackage(
            $packageName,
            $destinationIds,
            $moneySaved,
            $dayCount,
            $pickupIds,
            $transportType,
            $transportCost,
            $details,
            $imageName
        );

        $package = $builder->getPackage();

        if ($isEditMode) {
            // Update existing package
            $stmt = $conn->prepare("UPDATE packages SET package_name = ?, details = ?, image = ?, status = 'pending', rejection_feedback = NULL WHERE package_id = ?");
            $stmt->execute([$packageName, $details, $imageName, $packageId]);

            // Delete existing package details
            $stmt = $conn->prepare("DELETE FROM package_details WHERE package_id = ?");
            $stmt->execute([$packageId]);
            
            // Notify followers about the update
            $packageSubject = PackageSubject::getInstance();  //Calls the getInstance() method of the PackageSubject class.
            $packageSubject->loadFollowersAsObservers($packageId, $conn);
            $packageSubject->notify($packageId, "Package '{$packageName}' has been updated and you will be notified when it's available again!");
        } else {
            // Insert new package
            $stmt = $conn->prepare("INSERT INTO packages (package_id, package_name, publish_time, build_by, status, details, image) 
                                VALUES (?, ?, NOW(), ?, 'pending', ?, ?)");
            $stmt->execute([$packageId, $packageName, $buildBy, $details, $imageName]);
        }

        if (!empty($destinationIds)) {
            $stepNumber = 1;
            for ($i = 0; $i < count($destinationIds); $i++) {
                $stmt = $conn->prepare("INSERT INTO package_details (package_id, destination_id, step_number, money_saved, day_count, pickup, transport_type, cost) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

                $pickupValue = null;
                if (!empty($pickupIds[$i])) {
                    // Verify the pickup ID actually exists in the destinations table
                    $checkStmt = $conn->prepare("SELECT destination_id FROM destinations WHERE destination_id = ?");
                    $checkStmt->execute([$pickupIds[$i]]);
                    if ($checkStmt->fetch()) {
                        $pickupValue = $pickupIds[$i];
                    }
                }
                
                $stmt->execute([
                    $packageId,
                    $destinationIds[$i],
                    $stepNumber,
                    $moneySaved[$i] ?? 0,
                    $dayCount[$i] ?? 1,
                    $pickupValue,  // Use the validated pickup value
                    $transportType[$i] ?? null,
                    $transportCost[$i] ?? 0
                ]);
                $stepNumber++;
            }
        }
    } else { // Basic mode
        // Use the dedicated method for basic package creation
        $director->buildBasicPackage(
            $packageName,
            $details,
            $imageName
        );

        $package = $builder->getPackage();

        if ($isEditMode) {
            // Update existing package
            $stmt = $conn->prepare("UPDATE packages SET package_name = ?, details = ?, image = ?, status = 'pending', rejection_feedback = NULL WHERE package_id = ?");
            $stmt->execute([$packageName, $details, $imageName, $packageId]);
            
            // Notify followers about the update
            $packageSubject = PackageSubject::getInstance();
            $packageSubject->loadFollowersAsObservers($packageId, $conn);
            $packageSubject->notify($packageId, "Package '{$packageName}' has been updated.");
        } else {
            // Insert new package
            $stmt = $conn->prepare("INSERT INTO packages (package_id, package_name, publish_time, build_by, status, details, image) 
                                VALUES (?, ?, NOW(), ?, 'Pending', ?, ?)");
            $stmt->execute([$packageId, $packageName, $buildBy, $details, $imageName]);
        }
    }

    // echo "Package successfully " . ($isEditMode ? "updated!" : "created!");
    // header("Location: " . ($isEditMode ? "profile.php" : "buildAndShare.php"));

    echo "<script>
            alert('Package successfully " . ($isEditMode ? "updated!" : "created! wait for the admin approval!") . "');
            window.location.href = '" . ($isEditMode ? "profile.php" : "buildAndShare.php") . "';
        </script>";



    // Redirect to profile page after saving
    // header("Location: ../profile.php");
    exit;
}

// Rest of your existing code remains the same
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isEditMode ? 'Edit' : 'Build' ?> a Package</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  
    
    <link rel="stylesheet" type="text/css" href="modules/moduleCSS/buildPackages.css?v=<?php echo time(); ?>">
    <script>
        let destinationList = <?php echo json_encode($destinations); ?>;
        let isEditMode = <?php echo $isEditMode ? 'true' : 'false'; ?>;
        let existingPackageDetails = <?php echo !empty($packageDetails) ? json_encode($packageDetails) : '[]'; ?>;

        function toggleMode(mode) {
            const basicBtn = document.getElementById('basic-mode-btn');
            const fullBtn = document.getElementById('full-mode-btn');
            const fullModeSection = document.getElementById('full-mode-section');
            const modeInput = document.getElementById('mode-input');
            const publishButton = document.getElementById('publish-button');
            
            // Toggle required attributes based on mode
            const stepsContainer = document.getElementById('steps-container');
            const inputs = document.querySelectorAll('.step input[required]');
            
            if (mode === 'basic') {
                basicBtn.classList.add('active');
                fullBtn.classList.remove('active');
                fullModeSection.style.display = 'none';
                modeInput.value = 'basic';
                publishButton.disabled = false;
                
                // Remove required attribute from full mode inputs
                inputs.forEach(input => {
                    input.removeAttribute('required');
                });
            } else {
                basicBtn.classList.remove('active');
                fullBtn.classList.add('active');
                fullModeSection.style.display = 'block';
                modeInput.value = 'full';
                checkPublishButton();
                
                // Add required attribute back to full mode inputs
                inputs.forEach(input => {
                    input.setAttribute('required', '');
                });
            }
        }

        function addStep(destinationData = null) {
            let stepCount = document.querySelectorAll('.step').length + 1;
            let container = document.getElementById("steps-container");
            let step = document.createElement("div");
            step.className = "step";
            
            // Pre-fill values if we have destination data
            const destinationId = destinationData ? destinationData.destination_id : '';
            const destinationName = destinationData ? getDestinationNameById(destinationId) : '';
            const destinationCost = destinationData ? getDestinationCostById(destinationId) : '';
            const moneySaved = destinationData ? destinationData.money_saved : '';
            const dayCount = destinationData ? destinationData.day_count : '';
            const pickupId = destinationData ? destinationData.pickup : '';
            const pickupName = pickupId ? getDestinationNameById(pickupId) : '';
            const transportType = destinationData ? destinationData.transport_type : '';
            const transportCost = destinationData ? destinationData.cost : '';
            
            step.innerHTML = `
                <h4><i class="fas fa-map-marker-alt"></i> Step ${stepCount}</h4>
                <button type="button" class="remove-step" onclick="this.parentElement.remove(); renumberSteps(); checkPublishButton();"><i class="fas fa-times"></i></button>
                
                <div class="step-grid">
                    <div class="form-group step-full">
                        <label for="destination-${stepCount}">Destination</label>
                        <div class="input-icon-wrapper">
                            <i class="fas fa-search input-icon"></i>
                            <input type="text" id="destination-${stepCount}" class="form-control has-icon" placeholder="Search destination" oninput="selectDestination(this)" list="dest-list" required value="${destinationName}">
                            <datalist id="dest-list">
                                ${destinationList.map(dest => `<option data-id="${dest.destination_id}" data-cost="${dest.cost}" value="${dest.name}"></option>`).join('')}
                            </datalist>
                        </div>
                        <input type="hidden" name="destinations[]" value="${destinationId}">
                    </div>
                    
                    <div class="form-group">
                        <label>Destination Cost</label>
                        <div class="input-icon-wrapper">
                            <i class="fas fa-dollar-sign input-icon"></i>
                            <input type="number" name="cost[]" class="form-control has-icon" placeholder="Cost" readonly value="${destinationCost}">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Money Saved</label>
                        <div class="input-icon-wrapper">
                            <i class="fas fa-piggy-bank input-icon"></i>
                            <input type="number" name="money_saved[]" class="form-control has-icon" placeholder="Amount saved" required value="${moneySaved}">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Day Count</label>
                        <div class="input-icon-wrapper">
                            <i class="fas fa-calendar-day input-icon"></i>
                            <input type="number" name="day_count[]" class="form-control has-icon" placeholder="Number of days" required value="${dayCount}">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Pickup Point</label>
                        <div class="input-icon-wrapper">
                            <i class="fas fa-map-pin input-icon"></i>
                            <input type="text" class="form-control has-icon" placeholder="Search pickup point" oninput="selectPickup(this)" list="pickup-list" value="${pickupName}">
                            <datalist id="pickup-list">
                                ${destinationList.map(dest => `<option data-id="${dest.destination_id}" value="${dest.name}"></option>`).join('')}
                            </datalist>
                        </div>
                        <input type="hidden" name="pickup[]" value="${pickupId}">
                    </div>
                    
                    <div class="form-group">
                        <label>Transport Type</label>
                        <div class="input-icon-wrapper">
                            <i class="fas fa-bus input-icon"></i>
                            <input type="text" name="transport_type[]" class="form-control has-icon" placeholder="Bus, Train, etc." value="${transportType}">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Transport Cost</label>
                        <div class="input-icon-wrapper">
                            <i class="fas fa-dollar-sign input-icon"></i>
                            <input type="number" name="transport_cost[]" class="form-control has-icon" placeholder="Transport cost" value="${transportCost}">
                        </div>
                    </div>
                </div>
            `;
            container.appendChild(step);
            checkPublishButton();
        }
        
        function getDestinationNameById(id) {
            for (let dest of destinationList) {
                if (dest.destination_id == id) {
                    return dest.name;
                }
            }
            return '';
        }
        
        function getDestinationCostById(id) {
            for (let dest of destinationList) {
                if (dest.destination_id == id) {
                    return dest.cost;
                }
            }
            return '';
        }

        function renumberSteps() {
            const steps = document.querySelectorAll('.step h4');
            steps.forEach((step, index) => {
                step.innerHTML = `<i class="fas fa-map-marker-alt"></i> Step ${index + 1}`;
            });
        }

        function selectDestination(input) {
            let parentElement = input.closest('.step');
            let hiddenInput = parentElement.querySelector('input[name="destinations[]"]');
            let costInput = parentElement.querySelector('input[name="cost[]"]');
            let options = document.getElementById("dest-list").options;
            
            for (let option of options) {
                if (option.value === input.value) {
                    hiddenInput.value = option.dataset.id;
                    costInput.value = option.dataset.cost;
                    break;
                }
            }
        }

        function selectPickup(input) {
            let parentElement = input.closest('.step');
            let hiddenPickup = parentElement.querySelector('input[name="pickup[]"]');
            let options = document.getElementById("pickup-list").options;
            
            // Reset the pickup ID if the input is cleared
            if (!input.value.trim()) {
                hiddenPickup.value = '';
                return;
            }
            
            // Only set the pickup ID if a valid match is found
            let found = false;
            for (let option of options) {
                if (option.value === input.value) {
                    hiddenPickup.value = option.dataset.id;
                    found = true;
                    break;
                }
            }
            
            // If no match was found, clear the input and hidden value
            if (!found) {
                hiddenPickup.value = '';
                // Optionally alert the user
                // alert('Invalid pickup location selected');
            }
        }

        function checkPublishButton() {
            const modeInput = document.getElementById('mode-input');
            if (modeInput.value === 'basic') {
                document.getElementById('publish-button').disabled = false;
                return; // Always enabled in basic mode
            }
            
            let btn = document.getElementById('publish-button');
            let steps = document.getElementById('steps-container');
            btn.disabled = steps.children.length === 0;
        }

        function updateFileName() {
            const fileInput = document.getElementById('package-image');
            const fileName = document.getElementById('file-name');
            
            if (fileInput.files.length > 0) {
                fileName.textContent = fileInput.files[0].name;
            } else {
                fileName.textContent = '<?php echo $isEditMode ? basename($packageData['image']) : ''; ?>';
            }
        }

        window.onload = function() {
            // If in edit mode and we have package details, determine the mode
            if (isEditMode) {
                if (existingPackageDetails.length > 0) {
                    toggleMode('full');
                    // Add steps for each existing package detail
                    existingPackageDetails.forEach(detail => {
                        addStep(detail);
                    });
                } else {
                    toggleMode('basic');
                }
                
                // Set the file name if there's an existing image
                const fileName = document.getElementById('file-name');
                if (fileName) {
                    fileName.textContent = '<?php echo $isEditMode && isset($packageData['image']) ? basename($packageData['image']) : ''; ?>';
                }
            } else {
                toggleMode('basic'); // Start in basic mode for new packages
            }
        }

        // Add form validation function
        function validateForm() {
            const mode = document.getElementById('mode-input').value;
            
            if (mode === 'basic') {
                // Only validate basic fields for basic mode
                const packageName = document.getElementById('package-name').value;
                const packageDetails = document.getElementById('package-details').value;
                const packageImage = document.getElementById('package-image').files;
                
                if (!packageName || !packageDetails) {
                    alert('Please fill in all required fields.');
                    return false;
                }
                
                // Only require image for new packages, not when editing
                if (!isEditMode && packageImage.length === 0) {
                    alert('Please select an image for the package.');
                    return false;
                }
                
                return true;
            } else {
                // Full validation for full mode
                const steps = document.getElementById('steps-container');
                if (steps.children.length === 0) {
                    alert('Please add at least one destination to your package.');
                    return false;
                }
                
                // Check if all required fields in steps are filled
                const requiredInputs = steps.querySelectorAll('input[required]');
                for (let input of requiredInputs) {
                    if (!input.value) {
                        alert('Please fill in all required fields in your destinations.');
                        input.focus();
                        return false;
                    }
                }
                
                return true;
            }
        }
    </script>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-suitcase"></i> <?php echo $isEditMode ? 'Edit' : 'Build' ?> Your Travel Package</h1>
        
        <div class="form-container">
            <form method="POST" enctype="multipart/form-data" onsubmit="return validateForm()">
                <input type="hidden" id="mode-input" name="mode" value="basic">
                
                <div class="toggle-container">
                    <button type="button" id="basic-mode-btn" class="toggle-btn" onclick="toggleMode('basic')">
                        <i class="fas fa-cube"></i> Basic Mode
                    </button>
                    <button type="button" id="full-mode-btn" class="toggle-btn" onclick="toggleMode('full')">
                        <i class="fas fa-cubes"></i> Full Mode
                    </button>
                </div>
                
                <div class="form-group">
                    <label for="package-name">Package Name</label>
                    <div class="input-icon-wrapper">
                        <i class="fas fa-tag input-icon"></i>
                        <input type="text" id="package-name" name="package_name" class="form-control has-icon" placeholder="Enter package name" required value="<?php echo $isEditMode ? htmlspecialchars($packageData['package_name']) : ''; ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="package-details">Package Details</label>
                    <textarea id="package-details" name="details" class="form-control" placeholder="Describe your package..." required><?php echo $isEditMode ? htmlspecialchars($packageData['details']) : ''; ?></textarea>
                </div>

                <div class="form-group">
                    <label>Package Image</label>
                    <div class="file-input-wrapper">
                        <label class="file-input-label">
                            <i class="fas fa-cloud-upload-alt"></i> Choose Image
                            <input type="file" id="package-image" name="package_image" class="file-input" accept=".jpg,.jpeg,.png" <?php echo !$isEditMode ? 'required' : ''; ?> onchange="updateFileName()">
                        </label>
                        <div id="file-name" class="file-name"><?php echo $isEditMode ? basename($packageData['image']) : ''; ?></div>
                    </div>
                    <?php if ($isEditMode && !empty($packageData['image'])): ?>
                    <div style="margin-top: 10px; text-align: center;">
                        <img src="img/package-cover/<?php echo $packageData['image']; ?>" alt="Current Image" style="max-width: 200px; max-height: 150px; border-radius: 5px;">
                        <p>Current image</p>
                    </div>
                    <?php endif; ?>
                </div>

                <div id="full-mode-section" class="full-mode">
                    <h3><i class="fas fa-map-signs"></i> Destinations</h3>
                    <p>Add the destinations to include in your package.</p>
                    
                    <div id="steps-container" class="steps-container"></div>
                    
                    <button type="button" class="btn btn-outline" onclick="addStep()">
                        <i class="fas fa-plus"></i> Add Destination
                    </button>
                </div>
                
                <div class="action-buttons">
                    <button type="button" class="btn btn-outline" onclick="window.location.href='../profile.php'">
                        <i class="fas fa-arrow-left"></i> Cancel
                    </button>
                    <button type="submit" id="publish-button" class="btn btn-success">
                        <i class="fas fa-paper-plane"></i> <?php echo $isEditMode ? 'Update' : 'Publish' ?> Package
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>

