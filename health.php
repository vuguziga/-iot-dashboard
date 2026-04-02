<?php
$message = "";

$conn =mysqli_connect("localhost", "root", "", "livestock");
if ($conn) {
    echo "connection successfully";
}
else
    {
        echo "connection fail";
    }
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $tagId = $_POST['tagId'];
    $type = $_POST['type'];
    $startDate = $_POST['startDate'];
    $endDate = $_POST['endDate'];
    $nextEventDate = $_POST['nextEventDate'];
    $notes = $_POST['notes'];
    $vetName = $_POST['vetName'];
    $vetContact = $_POST['vetContact'];

    if (empty($tagId) || empty($type) || empty($startDate)) {
        $message = "Tag ID, Type, and Start Date are required!";
    } else {
        $stmt = $conn->prepare("INSERT INTO healthrecord (tagId, type, startDate, endDate, nextEventDate, notes, vetName, vetContact) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssss", $tagId, $type, $startDate, $endDate, $nextEventDate, $notes, $vetName, $vetContact);

        if ($stmt->execute()) {
            $message = "Health record added successfully!";
        } else {
            $message = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}

$conn->close();
?>