<?php
// ---------------- DATABASE CONNECTION ----------------
session_start();
$host = "localhost";
$user = "root";
$pass = "";
$db = "livestock";

$conn =mysqli_connect($host, $user, $pass, $db);
if ($conn) 
    {
        echo "connection successfully";
    }

    else{
        echo "connection failed";
    }
// ---------------- CREATE TABLES IF NOT EXISTS ----------------
$conn->query("CREATE TABLE IF NOT EXISTS animals (
    tagId VARCHAR(20) PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    animalType VARCHAR(30),
    sex ENUM('Male', 'Female'),
    breed VARCHAR(50),
    birthdate DATE,
    isPregnant TINYINT(1) DEFAULT 0,
    isSick TINYINT(1) DEFAULT 0,
    ownerContact VARCHAR(20),
    ownerEmail VARCHAR(100),
    registrationDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$conn->query("CREATE TABLE IF NOT EXISTS HealthRecords (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tagId VARCHAR(20),
    type VARCHAR(50),
    startDate DATE,
    endDate DATE,
    nextEventDate DATE,
    notes TEXT,
    vetName VARCHAR(50),
    vetContact VARCHAR(20),
    FOREIGN KEY (tagId) REFERENCES animals(tagId) ON DELETE CASCADE
)");

$conn->query("CREATE TABLE IF NOT EXISTS scan_logs (
    logId INT AUTO_INCREMENT PRIMARY KEY,
    tagId VARCHAR(20),
    scanTime DATETIME DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20),
    location VARCHAR(100)
)");

$conn->query("CREATE TABLE IF NOT EXISTS pending_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tagId VARCHAR(20),
    scanTime DATETIME DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) DEFAULT 'pending'
)");

// ---------------- ESP32 API ENDPOINTS ----------------
if(isset($_GET['esp32']) && $_GET['esp32'] == 'animal') {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    
    $tagId = isset($_GET['tagId']) ? $conn->real_escape_string($_GET['tagId']) : '';
    
    if(!$tagId) {
        echo json_encode(['error' => 'No tagId provided']);
        exit;
    }
    
    $result = $conn->query("SELECT * FROM animals WHERE tagId = '$tagId'");
    
    if($result && $row = $result->fetch_assoc()) {
        // Calculate age
        $age = null;
        if($row['birthdate']) {
            $birthDate = new DateTime($row['birthdate']);
            $today = new DateTime();
            $age = $birthDate->diff($today)->y;
        }
        
        // Get latest health record
        $healthRes = $conn->query("SELECT * FROM HealthRecords WHERE tagId='".$row['tagId']."' ORDER BY startDate DESC LIMIT 1");
        $latestHealth = $healthRes->fetch_assoc();
        
        // Log the scan
        $status = ($row['isSick'] == 1) ? 'SICK' : (($row['isPregnant'] == 1) ? 'PREGNANT' : 'HEALTHY');
        $conn->query("INSERT INTO scan_logs (tagId, scanTime, status) VALUES ('$tagId', NOW(), '$status')");
        
        $response = [
            'tagId' => $row['tagId'],
            'name' => $row['name'],
            'age' => $age,
            'animalType' => $row['animalType'],
            'sex' => $row['sex'],
            'breed' => $row['breed'],
            'isPregnant' => (bool)$row['isPregnant'],
            'isSick' => (bool)$row['isSick'],
            'ownerContact' => $row['ownerContact'],
            'healthRecord' => $latestHealth ? [
                'type' => $latestHealth['type'],
                'startDate' => $latestHealth['startDate'],
                'notes' => $latestHealth['notes']
            ] : null
        ];
        
        echo json_encode($response);
    } else {
        $conn->query("INSERT INTO scan_logs (tagId, scanTime, status) VALUES ('$tagId', NOW(), 'UNKNOWN')");
        echo json_encode(['error' => 'Tag not registered', 'tagId' => $tagId]);
    }
    exit;
}

// ---------------- ESP32 REGISTRATION ENDPOINT ----------------
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_GET['esp32']) && $_GET['esp32'] == 'register') {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $tagId = isset($input['tagId']) ? $conn->real_escape_string($input['tagId']) : '';
    
    if(!$tagId) {
        echo json_encode(['success' => false, 'error' => 'No tagId provided']);
        exit;
    }
    
    // Check if already registered
    $check = $conn->query("SELECT tagId FROM animals WHERE tagId = '$tagId'");
    if($check && $check->num_rows > 0) {
        echo json_encode(['success' => false, 'error' => 'Tag already registered', 'tagId' => $tagId]);
    } else {
        $conn->query("INSERT INTO pending_registrations (tagId, status) VALUES ('$tagId', 'pending')");
        $_SESSION['pending_tag'] = $tagId;
        echo json_encode(['success' => true, 'tagId' => $tagId]);
    }
    exit;
}

// ---------------- CHECK PENDING REGISTRATION ----------------
if(isset($_GET['action']) && $_GET['action'] == 'checkPending') {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    
    $result = $conn->query("SELECT tagId FROM pending_registrations WHERE status = 'pending' ORDER BY scanTime DESC LIMIT 1");
    if($result && $row = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'tagId' => $row['tagId']]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

// ---------------- CLEAR PENDING REGISTRATION ----------------
if(isset($_GET['action']) && $_GET['action'] == 'clearPending') {
    header('Content-Type: application/json');
    $tagId = isset($_GET['tagId']) ? $conn->real_escape_string($_GET['tagId']) : '';
    if($tagId) {
        $conn->query("DELETE FROM pending_registrations WHERE tagId = '$tagId'");
    }
    echo json_encode(['success' => true]);
    exit;
}

// ---------------- AJAX HANDLERS ----------------
if(isset($_GET['action'])){
    $action = $_GET['action'];
    header('Content-Type: application/json');

    if($action=="getAnimals"){
        $res = $conn->query("SELECT * FROM animals ORDER BY tagId DESC");
        $data=[];
        while($row=$res->fetch_assoc()){
            $healthRes = $conn->query("SELECT * FROM HealthRecords WHERE tagId='".$row['tagId']."' ORDER BY startDate DESC LIMIT 1");
            $row['latestHealth'] = $healthRes->fetch_assoc();
            $data[]=$row;
        }
        echo json_encode($data);
        exit;
    }

    if($action=="getHealth"){
        $res=$conn->query("SELECT * FROM HealthRecords ORDER BY tagId, startDate DESC");
        $data=[];
        while($row=$res->fetch_assoc()) $data[]=$row;
        echo json_encode($data);
        exit;
    }
    
    if($action=="getDashboardStats"){
        $total = $conn->query("SELECT COUNT(*) as count FROM animals")->fetch_assoc()['count'];
        $sick = $conn->query("SELECT COUNT(*) as count FROM animals WHERE isSick = 1")->fetch_assoc()['count'];
        $pregnant = $conn->query("SELECT COUNT(*) as count FROM animals WHERE isPregnant = 1")->fetch_assoc()['count'];
        $healthy = $total - $sick;
        
        $scansToday = $conn->query("SELECT COUNT(*) as count FROM scan_logs WHERE DATE(scanTime) = CURDATE()")->fetch_assoc()['count'];
        
        $recentScans = $conn->query("
            SELECT s.*, a.name 
            FROM scan_logs s 
            LEFT JOIN animals a ON s.tagId = a.tagId 
            ORDER BY s.scanTime DESC 
            LIMIT 10
        ");
        $scans = [];
        while($row = $recentScans->fetch_assoc()) {
            $scans[] = $row;
        }
        
        // Get sick animals for alerts
        $sickAnimals = $conn->query("SELECT tagId, name FROM animals WHERE isSick = 1");
        $alerts = [];
        while($sickAnimal = $sickAnimals->fetch_assoc()) {
            $alerts[] = [
                'tagId' => $sickAnimal['tagId'],
                'name' => $sickAnimal['name'],
                'alertType' => 'Sick - Needs immediate attention'
            ];
        }
        
        echo json_encode([
            'stats' => [
                'total' => $total,
                'sick' => $sick,
                'pregnant' => $pregnant,
                'healthy' => $healthy,
                'scansToday' => $scansToday
            ],
            'recentScans' => $scans,
            'alerts' => $alerts
        ]);
        exit;
    }
    
    if($action=="getScanLogs"){
        $res = $conn->query("
            SELECT s.*, a.name, a.animalType 
            FROM scan_logs s 
            LEFT JOIN animals a ON s.tagId = a.tagId 
            ORDER BY s.scanTime DESC 
            LIMIT 50
        ");
        $data = [];
        while($row = $res->fetch_assoc()) $data[] = $row;
        echo json_encode($data);
        exit;
    }

    if($action=="deleteAnimal"){
        $tagId = $conn->real_escape_string($_GET['tagId']);
        $conn->query("DELETE FROM HealthRecords WHERE tagId='$tagId'");
        $conn->query("DELETE FROM scan_logs WHERE tagId='$tagId'");
        $sql="DELETE FROM animals WHERE tagId='$tagId'";
        echo json_encode($conn->query($sql)===TRUE ? ["status"=>"success","message"=>"Deleted"] : ["status"=>"error","message"=>$conn->error]);
        exit;
    }
}

// ---------------- POST / ADD ANIMAL (FIXED) ----------------
if($_SERVER['REQUEST_METHOD']=="POST" && !isset($_GET['action']) && !isset($_GET['esp32'])){
    header('Content-Type: application/json');
    
    // Check if JSON or form data
    $input = file_get_contents("php://input");
    $data = json_decode($input, true);
    
    // If not JSON, try to get from POST
    if(!$data) {
        $data = $_POST;
    }
    
    $tagId = isset($data['tagId']) ? $conn->real_escape_string($data['tagId']) : '';
    $name = isset($data['name']) ? $conn->real_escape_string($data['name']) : '';
    $animalType = isset($data['animalType']) ? $conn->real_escape_string($data['animalType']) : '';
    $sex = isset($data['sex']) ? $conn->real_escape_string($data['sex']) : 'Male';
    $breed = isset($data['breed']) ? $conn->real_escape_string($data['breed']) : '';
    $birthdate = isset($data['birthdate']) ? $conn->real_escape_string($data['birthdate']) : null;
    $isPregnant = isset($data['isPregnant']) ? ($data['isPregnant'] ? 1 : 0) : 0;
    $isSick = isset($data['isSick']) ? ($data['isSick'] ? 1 : 0) : 0;
    $ownerContact = isset($data['ownerContact']) ? $conn->real_escape_string($data['ownerContact']) : '';
    $ownerEmail = isset($data['ownerEmail']) ? $conn->real_escape_string($data['ownerEmail']) : '';
    
    // Validate required fields
    if(empty($tagId) || empty($name)) {
        echo json_encode(["status"=>"error","message"=>"Tag ID and Name are required"]);
        exit;
    }
    
    // Check if tag already exists
    $check = $conn->query("SELECT tagId FROM animals WHERE tagId = '$tagId'");
    if($check && $check->num_rows > 0) {
        echo json_encode(["status"=>"error","message"=>"Tag ID already exists!"]);
        exit;
    }
    
    $sql = "INSERT INTO animals (tagId, name, animalType, sex, breed, birthdate, isPregnant, isSick, ownerContact, ownerEmail) 
            VALUES ('$tagId', '$name', '$animalType', '$sex', '$breed', '$birthdate', '$isPregnant', '$isSick', '$ownerContact', '$ownerEmail')";
    
    if($conn->query($sql) === TRUE) {
        // Clear pending registration
        $conn->query("DELETE FROM pending_registrations WHERE tagId='$tagId'");
        echo json_encode(["status"=>"success","message"=>"Animal added successfully", "tagId"=>$tagId]);
    } else {
        echo json_encode(["status"=>"error","message"=>$conn->error]);
    }
    exit;
}

// ---------------- UPDATE ANIMAL ----------------
if($_SERVER['REQUEST_METHOD']=="PUT"){
    header('Content-Type: application/json');
    parse_str(file_get_contents("php://input"), $data);
    
    $tagId = $conn->real_escape_string($data['tagId']);
    $name = $conn->real_escape_string($data['name']);
    $animalType = $conn->real_escape_string($data['animalType']);
    $sex = $conn->real_escape_string($data['sex']);
    $breed = $conn->real_escape_string($data['breed']);
    $birthdate = $conn->real_escape_string($data['birthdate']);
    $isPregnant = isset($data['isPregnant']) ? 1 : 0;
    $isSick = isset($data['isSick']) ? 1 : 0;
    $ownerContact = $conn->real_escape_string($data['ownerContact']);
    $ownerEmail = isset($data['ownerEmail']) ? $conn->real_escape_string($data['ownerEmail']) : '';

    $sql = "UPDATE animals SET 
            name='$name',
            animalType='$animalType',
            sex='$sex',
            breed='$breed',
            birthdate='$birthdate',
            isPregnant='$isPregnant',
            isSick='$isSick',
            ownerContact='$ownerContact',
            ownerEmail='$ownerEmail'
            WHERE tagId='$tagId'";
            
    echo json_encode($conn->query($sql)===TRUE ? ["status"=>"success","message"=>"Animal updated"] : ["status"=>"error","message"=>$conn->error]);
    exit;
}

// ---------------- ADD HEALTH RECORD ----------------
if($_SERVER['REQUEST_METHOD']=="POST" && isset($_GET['action']) && $_GET['action']=="addHealth"){
    header('Content-Type: application/json');
    $data=json_decode(file_get_contents("php://input"), true);
    
    $tagId = $conn->real_escape_string($data['tagId']);
    $type = $conn->real_escape_string($data['type']);
    $startDate = $conn->real_escape_string($data['startDate']);
    $endDate = $conn->real_escape_string($data['endDate']);
    $nextEventDate = $conn->real_escape_string($data['nextEventDate']);
    $notes = $conn->real_escape_string($data['notes']);
    $vetName = $conn->real_escape_string($data['vetName']);
    $vetContact = $conn->real_escape_string($data['vetContact']);
    
    $sql="INSERT INTO HealthRecords (tagId,type,startDate,endDate,nextEventDate,notes,vetName,vetContact)
          VALUES ('$tagId','$type','$startDate','$endDate','$nextEventDate','$notes','$vetName','$vetContact')";
          
    echo json_encode($conn->query($sql)===TRUE ? ["status"=>"success","message"=>"Health record added"] : ["status"=>"error","message"=>$conn->error]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Livestock RFID Tracker</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; }
        .navbar { background: linear-gradient(135deg, #1a5f3a, #0d3b22); color: white; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
        .navbar a { color: white; text-decoration: none; padding: 0.5rem 1rem; border-radius: 5px; }
        .navbar a:hover { background: rgba(255,255,255,0.2); }
        .container { max-width: 1400px; margin: 0 auto; padding: 1.5rem; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: white; border-radius: 12px; padding: 1.5rem; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .stat-number { font-size: 2rem; font-weight: bold; }
        .stat-card.blue .stat-number { color: #3b82f6; }
        .stat-card.red .stat-number { color: #ef4444; }
        .stat-card.green .stat-number { color: #10b981; }
        .stat-card.purple .stat-number { color: #8b5cf6; }
        .form-container { background: white; border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .form-title { font-size: 1.25rem; font-weight: bold; margin-bottom: 1rem; border-bottom: 2px solid #1a5f3a; padding-bottom: 0.5rem; }
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1rem; }
        .form-group label { display: block; font-size: 0.85rem; font-weight: 500; margin-bottom: 0.25rem; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 0.5rem; border: 1px solid #ddd; border-radius: 6px; }
        .checkbox-group { display: flex; gap: 1rem; margin: 1rem 0; }
        button { background: #1a5f3a; color: white; border: none; padding: 0.5rem 1.5rem; border-radius: 6px; cursor: pointer; }
        button:hover { background: #0d3b22; }
        button.danger { background: #795a64; }
        .table-container { background: white; border-radius: 12px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; }
        .badge { padding: 0.25rem 0.5rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .badge-sick { background: #fee2e2; color: #dc26d0; }
        .badge-pregnant { background: #f3e8ff; color: #9333ea; }
        .badge-healthy { background: #dcfce7; color: #16a34a; }
        .register-mode-bar { background: #fef3c7; border: 1px solid #956a209e; border-radius: 8px; padding: 1rem; margin-bottom: 1rem; display: none; }
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000; }
        .modal-content { background: white; border-radius: 12px; padding: 2rem; max-width: 500px; width: 90%; }
    </style>
</head>
<body>
    <nav class="navbar">
        <div><h1><i class="fas fa-tachometer-alt"></i> Smart Livestock RFID Tracker</h1><small>Animal Identification & Monitoring System</small></div>
        <div style="display: flex; gap: 1rem;">
            <a href="#" onclick="showDashboard()"><i class="fas fa-home"></i> Dashboard</a>
            <a href="#" onclick="showAnimals()"><i class="fas fa-paw"></i> Animals</a>
            <a href="#" onclick="showHealth()"><i class="fas fa-heartbeat"></i> Health</a>
            <a href="#" onclick="showScans()"><i class="fas fa-qrcode"></i> Scans</a>
            <a href="#" onclick="activateRegisterMode()"><i class="fas fa-rfid"></i> Register Mode</a>
        </div>
    </nav>

    <div class="container">
        <!-- Register Mode Bar -->
        <div id="registerModeBar" class="register-mode-bar">
            <div style="display: flex; justify-content: space-between;">
                <div><i class="fas fa-qrcode"></i> <strong>Registration Mode Active!</strong> Scan RFID tag with ESP32</div>
                <button onclick="deactivateRegisterMode()" style="background:#f59e0b;">Deactivate</button>
            </div>
            <div id="pendingTagInfo" style="margin-top: 0.5rem;"></div>
        </div>

        <!-- Dashboard Section -->
        <div id="dashboardSection">
            <div class="stats-grid" id="statsGrid"></div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem;">
                <div class="form-container"><div class="form-title"><i class="fas fa-history"></i> Recent RFID Scans</div><div id="recentScansList"></div></div>
                <div class="form-container"><div class="form-title"><i class="fas fa-bell"></i> Health Alerts</div><div id="alertsList"></div></div>
            </div>
            <div class="form-container"><div class="form-title"><i class="fas fa-chart-bar"></i> Health Records Chart</div><canvas id="healthChart" height="100"></canvas></div>
        </div>

        <!-- Animals Section -->
        <div id="animalsSection" style="display: none;">
            <div class="form-container">
                <div class="form-title"><i class="fas fa-plus-circle"></i> Add / Edit Animal</div>
                <input type="hidden" id="editTagId">
                <div class="form-row">
                    <div class="form-group"><label>RFID Tag ID *</label><input type="text" id="tagId" placeholder="Scan or enter tag ID" class="font-mono"></div>
                    <div class="form-group"><label>Animal Name *</label><input type="text" id="name" placeholder="Enter animal name"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Type</label><select id="animalType" onchange="updateBreedOptions()"><option value="">Select</option><option value="Cow">Cow</option><option value="Goat">Goat</option><option value="Sheep">Sheep</option></select></div>
                    <div class="form-group"><label>Breed</label><select id="breed"><option value="">Select Breed</option></select></div>
                    <div class="form-group"><label>Sex</label><select id="sex"><option value="Male">Male</option><option value="Female">Female</option></select></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Birthdate</label><input type="date" id="birthdate"></div>
                    <div class="form-group"><label>Owner Contact</label><input type="text" id="ownerContact" placeholder="Phone number"></div>
                    <div class="form-group"><label>Owner Email</label><input type="email" id="ownerEmail" placeholder="For notifications"></div>
                </div>
                <div class="checkbox-group">
                    <label><input type="checkbox" id="isPregnant"> 🤰 Pregnant</label>
                    <label><input type="checkbox" id="isSick"> 🤒 Sick</label>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <button onclick="saveAnimal()"><i class="fas fa-save"></i> Save Animal</button>
                    <button onclick="clearAnimalForm()" style="background:#6b7280;">Clear</button>
                </div>
            </div>

            <div class="form-container">
                <div class="form-title"><i class="fas fa-list"></i> Animal Registry</div>
                <div class="table-container">
                    <table style="width:100%">
                        <thead><tr><th>Tag ID</th><th>Name</th><th>Type</th><th>Breed</th><th>Sex</th><th>Status</th><th>Owner</th><th>Actions</th></tr></thead>
                        <tbody id="animalsTableBody"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Health Section -->
        <div id="healthSection" style="display: none;">
            <div class="form-container">
                <div class="form-title"><i class="fas fa-notes-medical"></i> Add Health Record</div>
                <div class="form-row">
                    <div class="form-group"><label>Animal</label><select id="healthTagId"></select></div>
                    <div class="form-group"><label>Type</label><select id="type"><option value="vaccination">Vaccination</option><option value="disease">Disease</option><option value="pregnancy">Pregnancy</option></select></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Start Date</label><input type="date" id="startDate"></div>
                    <div class="form-group"><label>End Date</label><input type="date" id="endDate"></div>
                    <div class="form-group"><label>Next Event</label><input type="date" id="nextEventDate"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Vet Name</label><input type="text" id="vetName"></div>
                    <div class="form-group"><label>Vet Contact</label><input type="text" id="vetContact"></div>
                </div>
                <div class="form-group"><label>Notes</label><textarea id="notes" rows="2"></textarea></div>
                <button onclick="addHealth()"><i class="fas fa-plus"></i> Add Health Record</button>
            </div>
            <div class="form-container"><div class="form-title"><i class="fas fa-table"></i> Health Records</div><div class="table-container"><table style="width:100%"><thead><tr><th>Tag ID</th><th>Type</th><th>Start Date</th><th>Next Event</th><th>Vet</th><th>Notes</th></tr></thead><tbody id="healthRecordsBody"></tbody></table></div></div>
        </div>

        <!-- Scans Section -->
        <div id="scansSection" style="display: none;">
            <div class="form-container"><div class="form-title"><i class="fas fa-history"></i> RFID Scan History</div><div class="table-container"><table style="width:100%"><thead><tr><th>Time</th><th>Tag ID</th><th>Animal</th><th>Status</th></tr></thead><tbody id="scansBody"></tbody></table></div></div>
        </div>
    </div>

    <script>
        let breedOptions = { "Cow":["Friesian","Jersey","Ankole"], "Goat":["Boer","Saanen"], "Sheep":["Dorper","Merino"] };
        let registerModeActive = false;
        let pollInterval = null;
        let chart = null;

        function showDashboard() {
            document.getElementById('dashboardSection').style.display = 'block';
            document.getElementById('animalsSection').style.display = 'none';
            document.getElementById('healthSection').style.display = 'none';
            document.getElementById('scansSection').style.display = 'none';
            loadDashboard();
        }
        function showAnimals() {
            document.getElementById('dashboardSection').style.display = 'none';
            document.getElementById('animalsSection').style.display = 'block';
            document.getElementById('healthSection').style.display = 'none';
            document.getElementById('scansSection').style.display = 'none';
            loadAnimals();
            loadHealthTagOptions();
        }
        function showHealth() {
            document.getElementById('dashboardSection').style.display = 'none';
            document.getElementById('animalsSection').style.display = 'none';
            document.getElementById('healthSection').style.display = 'block';
            document.getElementById('scansSection').style.display = 'none';
            loadHealthRecords();
            loadHealthTagOptions();
        }
        function showScans() {
            document.getElementById('dashboardSection').style.display = 'none';
            document.getElementById('animalsSection').style.display = 'none';
            document.getElementById('healthSection').style.display = 'none';
            document.getElementById('scansSection').style.display = 'block';
            loadScanLogs();
        }

        function updateBreedOptions() {
            let type = document.getElementById("animalType").value;
            let breedSelect = document.getElementById("breed");
            breedSelect.innerHTML = "<option value=''>Select Breed</option>";
            if(breedOptions[type]) breedOptions[type].forEach(b => breedSelect.innerHTML += `<option value="${b}">${b}</option>`);
        }

        function loadDashboard() {
            fetch(window.location.href + '?action=getDashboardStats')
                .then(res => res.json())
                .then(data => {
                    document.getElementById('statsGrid').innerHTML = `
                        <div class="stat-card blue"><div><h3>Total</h3><div class="stat-number">${data.stats.total}</div></div><i class="fas fa-database"></i></div>
                        <div class="stat-card red"><div><h3>Sick</h3><div class="stat-number">${data.stats.sick}</div></div><i class="fas fa-ambulance"></i></div>
                        <div class="stat-card green"><div><h3>Healthy</h3><div class="stat-number">${data.stats.healthy}</div></div><i class="fas fa-check"></i></div>
                        <div class="stat-card purple"><div><h3>Pregnant</h3><div class="stat-number">${data.stats.pregnant}</div></div><i class="fas fa-baby"></i></div>
                        <div class="stat-card"><div><h3>Scans Today</h3><div class="stat-number">${data.stats.scansToday}</div></div><i class="fas fa-qrcode"></i></div>
                    `;
                    let scansHtml = '';
                    data.recentScans.forEach(s => {
                        let badge = s.status === 'SICK' ? '<span class="badge badge-sick">SICK</span>' : (s.status === 'PREGNANT' ? '<span class="badge badge-pregnant">PREGNANT</span>' : '<span class="badge badge-healthy">HEALTHY</span>');
                        scansHtml += `<div style="padding:0.5rem; border-bottom:1px solid #486d30;"><strong>${s.name || s.tagId}</strong> ${badge}<br><small>${s.scanTime}</small></div>`;
                    });
                    document.getElementById('recentScansList').innerHTML = scansHtml || '<p>No scans yet</p>';
                    
                    let alertsHtml = '';
                    data.alerts.forEach(a => alertsHtml += `<div style="background:violet; padding:0.5rem; margin:0.25rem 0; border-radius:6px;"><i class="fas fa-exclamation-triangle"></i> <strong>${a.name}</strong> - ${a.alertType}</div>`);
                    document.getElementById('alertsList').innerHTML = alertsHtml || '<p class="badge badge-healthy">No alerts</p>';
                    loadHealthChart();
                });
        }

        function loadHealthChart() {
            fetch(window.location.href + '?action=getHealth')
                .then(res => res.json())
                .then(data => {
                    let grouped = {};
                    data.forEach(h => {
                        if(!grouped[h.tagId]) grouped[h.tagId] = {vacc:0, disease:0, preg:0};
                        if(h.type == "vaccination") grouped[h.tagId].vacc++;
                        if(h.type == "disease") grouped[h.tagId].disease++;
                        if(h.type == "pregnancy") grouped[h.tagId].preg++;
                    });
                    let ctx = document.getElementById("healthChart").getContext('2d');
                    if(chart) chart.destroy();
                    chart = new Chart(ctx, {
                        type: 'bar',
                        data: { labels: Object.keys(grouped), datasets: [
                            {label:'Vaccination', data:Object.values(grouped).map(v=>v.vacc), backgroundColor:'#10b981'},
                            {label:'Disease', data:Object.values(grouped).map(v=>v.disease), backgroundColor:'#ef4444'},
                            {label:'Pregnancy', data:Object.values(grouped).map(v=>v.preg), backgroundColor:'#8b5cf6'}
                        ]},
                        options: { responsive: true, scales: { x: { stacked: true }, y: { stacked: true } } }
                    });
                });
        }

        function loadAnimals() {
            fetch(window.location.href + '?action=getAnimals')
                .then(res => res.json())
                .then(data => {
                    let html = '';
                    data.forEach(a => {
                        let statusBadge = a.isSick ? '<span class="badge badge-sick">Sick</span>' : (a.isPregnant ? '<span class="badge badge-pregnant">Pregnant</span>' : '<span class="badge badge-healthy">Healthy</span>');
                        html += `<tr>
                            <td class="font-mono">${a.tagId}</td><td><strong>${a.name}</strong></td><td>${a.animalType}</td><td>${a.breed || '-'}</td><td>${a.sex}</td>
                            <td>${statusBadge}</td><td>${a.ownerContact || '-'}</td>
                            <td><button onclick="editAnimal(${JSON.stringify(a).replace(/"/g, '&quot;')})">Edit</button> <button class="danger" onclick="deleteAnimal('${a.tagId}')">Delete</button></td>
                         </tr>`;
                    });
                    document.getElementById('animalsTableBody').innerHTML = html;
                });
        }

        function saveAnimal() {
            let tagId = document.getElementById("tagId").value;
            let editTagId = document.getElementById("editTagId").value;
            
            if(!tagId) { alert("Please enter RFID Tag ID"); return; }
            if(!document.getElementById("name").value) { alert("Please enter animal name"); return; }
            
            let data = {
                tagId: tagId,
                name: document.getElementById("name").value,
                animalType: document.getElementById("animalType").value,
                sex: document.getElementById("sex").value,
                breed: document.getElementById("breed").value,
                birthdate: document.getElementById("birthdate").value,
                isPregnant: document.getElementById("isPregnant").checked ? 1 : 0,
                isSick: document.getElementById("isSick").checked ? 1 : 0,
                ownerContact: document.getElementById("ownerContact").value,
                ownerEmail: document.getElementById("ownerEmail").value
            };
            
            let method = editTagId ? "PUT" : "POST";
            let url = window.location.href;
            let body = method === "POST" ? JSON.stringify(data) : Object.keys(data).map(k => k + '=' + encodeURIComponent(data[k])).join('&');
            
            fetch(url, {
                method: method,
                headers: {'Content-Type': method === "POST" ? "application/json" : "application/x-www-form-urlencoded"},
                body: body
            }).then(res => res.json()).then(res => {
                alert(res.message);
                if(res.status === "success") {
                    clearAnimalForm();
                    loadAnimals();
                    loadDashboard();
                    if(method === "POST") fetch(url + '?action=clearPending&tagId=' + tagId);
                }
            }).catch(err => alert("Error: " + err));
        }

        function editAnimal(a) {
            document.getElementById("editTagId").value = a.tagId;
            document.getElementById("tagId").value = a.tagId;
            document.getElementById("name").value = a.name;
            document.getElementById("animalType").value = a.animalType;
            updateBreedOptions();
            setTimeout(() => document.getElementById("breed").value = a.breed, 100);
            document.getElementById("sex").value = a.sex;
            document.getElementById("birthdate").value = a.birthdate;
            document.getElementById("isPregnant").checked = a.isPregnant == 1;
            document.getElementById("isSick").checked = a.isSick == 1;
            document.getElementById("ownerContact").value = a.ownerContact || '';
            document.getElementById("ownerEmail").value = a.ownerEmail || '';
        }

        function clearAnimalForm() {
            document.getElementById("editTagId").value = '';
            document.getElementById("tagId").value = '';
            document.getElementById("name").value = '';
            document.getElementById("animalType").value = '';
            document.getElementById("sex").value = 'Male';
            document.getElementById("breed").innerHTML = "<option value=''>Select Breed</option>";
            document.getElementById("birthdate").value = '';
            document.getElementById("isPregnant").checked = false;
            document.getElementById("isSick").checked = false;
            document.getElementById("ownerContact").value = '';
            document.getElementById("ownerEmail").value = '';
        }

        function deleteAnimal(tagId) {
            if(!confirm("Delete this animal?")) return;
            fetch(window.location.href + '?action=deleteAnimal&tagId=' + tagId)
                .then(res => res.json())
                .then(res => { alert(res.message); loadAnimals(); loadDashboard(); });
        }

        function loadHealthTagOptions() {
            fetch(window.location.href + '?action=getAnimals')
                .then(res => res.json())
                .then(data => {
                    let options = '<option value="">Select Animal</option>';
                    data.forEach(a => options += `<option value="${a.tagId}">${a.tagId} - ${a.name}</option>`);
                    document.getElementById("healthTagId").innerHTML = options;
                });
        }

        function loadHealthRecords() {
            fetch(window.location.href + '?action=getHealth')
                .then(res => res.json())
                .then(data => {
                    let html = '';
                    data.forEach(h => html += `<tr><td class="font-mono">${h.tagId}</td><td>${h.type}</td><td>${h.startDate || '-'}</td><td>${h.nextEventDate || '-'}</td><td>${h.vetName || '-'}</td><td>${h.notes || '-'}</td></tr>`);
                    document.getElementById('healthRecordsBody').innerHTML = html;
                });
        }

        function addHealth() {
            let data = {
                tagId: document.getElementById("healthTagId").value,
                type: document.getElementById("type").value,
                startDate: document.getElementById("startDate").value,
                endDate: document.getElementById("endDate").value,
                nextEventDate: document.getElementById("nextEventDate").value,
                vetName: document.getElementById("vetName").value,
                vetContact: document.getElementById("vetContact").value,
                notes: document.getElementById("notes").value
            };
            if(!data.tagId) { alert("Select an animal"); return; }
            fetch(window.location.href + '?action=addHealth', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(data)
            }).then(res => res.json()).then(res => {
                alert(res.message);
                if(res.status === "success") {
                    document.getElementById("startDate").value = '';
                    document.getElementById("endDate").value = '';
                    document.getElementById("nextEventDate").value = '';
                    document.getElementById("vetName").value = '';
                    document.getElementById("vetContact").value = '';
                    document.getElementById("notes").value = '';
                    loadHealthRecords();
                    loadDashboard();
                }
            });
        }

        function loadScanLogs() {
            fetch(window.location.href + '?action=getScanLogs')
                .then(res => res.json())
                .then(data => {
                    let html = '';
                    data.forEach(s => {
                        let badge = s.status === 'SICK' ? '<span class="badge badge-sick">SICK</span>' : (s.status === 'PREGNANT' ? '<span class="badge badge-pregnant">PREGNANT</span>' : '<span class="badge badge-healthy">HEALTHY</span>');
                        html += `<tr><td>${s.scanTime}</td><td class="font-mono">${s.tagId}</td><td>${s.name || '-'}</td><td>${badge}</td></tr>`;
                    });
                    document.getElementById('scansBody').innerHTML = html;
                });
        }

        function activateRegisterMode() {
            registerModeActive = true;
            document.getElementById('registerModeBar').style.display = 'block';
            if(pollInterval) clearInterval(pollInterval);
            pollInterval = setInterval(() => {
                if(!registerModeActive) return;
                fetch(window.location.href + '?action=checkPending')
                    .then(res => res.json())
                    .then(data => {
                        if(data.success && data.tagId) {
                            document.getElementById('pendingTagInfo').innerHTML = `<i class="fas fa-check-circle"></i> Tag scanned: <strong>${data.tagId}</strong> <button onclick="document.getElementById('tagId').value='${data.tagId}'; showAnimals(); deactivateRegisterMode();">Use This Tag</button>`;
                        }
                    });
            }, 2000);
        }

        function deactivateRegisterMode() {
            registerModeActive = false;
            document.getElementById('registerModeBar').style.display = 'none';
            if(pollInterval) clearInterval(pollInterval);
        }

        // Auto refresh every 30 seconds
        setInterval(() => {
            if(document.getElementById('dashboardSection').style.display !== 'none') loadDashboard();
            if(document.getElementById('animalsSection').style.display !== 'none') loadAnimals();
            if(document.getElementById('healthSection').style.display !== 'none') { loadHealthRecords(); loadHealthTagOptions(); }
            if(document.getElementById('scansSection').style.display !== 'none') loadScanLogs();
        }, 30000);

        loadDashboard();
    </script>
</body>
</html>