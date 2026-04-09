<?php
$conn =mysqli_connect("localhost", "root", "", "livestock");

if ($conn) {
     echo "Connection successfully";
} 
else
    {
        echo "connection failed";
    }

$username = "faustin";
$password = "1234";
echo password_hash("1234", PASSWORD_DEFAULT);
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// DELETE old user (optional)
$conn->query("DELETE FROM users WHERE username='admin'");

$stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
$stmt->bind_param("ss", $username, $hashedPassword);

$stmt->execute();

echo "User created successfully!<br>Username: $username<br>Password: $password";
?>