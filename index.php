<?php
session_start();
if(!isset($_SESSION['user'])){
    header("Location: login.php");
    exit();
}
$conn = new mysqli("localhost", "root", "", "livestock");
$totalAnimals = $conn->query("SELECT COUNT(*) as total FROM Animals")->fetch_assoc()['total'];
$totalHealth = $conn->query("SELECT COUNT(*) as total FROM healthrecord")->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html>
<head>
<title>Livestock Dashboard</title>
<style>
body{font-family:Arial;background:#f4f6f9}
.container{width:50%;margin:auto;text-align:center;}
.card{display:inline-block;padding:20px;margin:10px;background:#27ae60;color:white;border-radius:8px;}
a{display:block;margin:10px;color:red;text-decoration:none;}
</style>
</head>
<body>
<div class="container">
<h1>Welcome, <?= $_SESSION['user'] ?></h1>
<div class="card">Total Animals: <?= $totalAnimals ?></div>
<div class="card">Total Health Records: <?= $totalHealth ?></div>
<a href="logout.php">Logout</a>
</div>
</body>
</html>