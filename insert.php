<?php
header('Content-Type: application/json');

// --- Database connection ---
$host = "localhost";
$db_name = "L5NIT"; // make sure this exists!
$username = "root";
$password = ""; // niba ufite password shyiramo

$conn = new mysqli($host, $username, $password, $db_name);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit;
}

// --- Get JSON input from POST ---
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['temperature']) || !isset($input['humidity'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$temperature = floatval($input['temperature']);
$humidity = floatval($input['humidity']);

// --- Prepare statement ---
$stmt = $conn->prepare("INSERT INTO sensor_data (temperature, humidity) VALUES (?, ?)");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare failed: ' . $conn->error]);
    exit;
}

$stmt->bind_param("dd", $temperature, $humidity);

// --- Execute statement ---
if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Data inserted successfully',
        'inserted' => [
            'id' => $stmt->insert_id,
            'temperature' => $temperature,
            'humidity' => $humidity
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Insert failed: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>