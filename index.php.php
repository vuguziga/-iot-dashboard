<?php
session_start();
if(!isset($_SESSION['user'])){
    header("Location: login.php");
    exit();
}

$conn = new mysqli("localhost", "root", "", "livestock");
if ($conn->connect_error) die("Connection failed");

/* STATS */
$totalAnimals = $conn->query("SELECT COUNT(*) as total FROM Animals")->fetch_assoc()['total'];
$totalHealth = $conn->query("SELECT COUNT(*) as total FROM healthrecord")->fetch_assoc()['total'];

$message = "";

/* ADD ANIMAL */
if(isset($_POST['add_animal'])){
    $stmt = $conn->prepare("INSERT INTO Animals(tagId,name,animalType,sex,breed,birthdate,ownerContact) VALUES(?,?,?,?,?,?,?)");
    $stmt->bind_param("sssssss",
        $_POST['tagId'],
        $_POST['name'],
        $_POST['animalType'],
        $_POST['sex'],
        $_POST['breed'],
        $_POST['birthdate'],
        $_POST['ownerContact']
    );
    $message = $stmt->execute() ? "Animal added!" : "Error!";
}

/* ADD HEALTH */
if(isset($_POST['add_health'])){
    $stmt = $conn->prepare("INSERT INTO healthrecord(tagId,type,startDate,endDate,nextEventDate,notes,vetName,vetContact) VALUES(?,?,?,?,?,?,?,?)");
    $stmt->bind_param("ssssssss",
        $_POST['tagId'],
        $_POST['type'],
        $_POST['startDate'],
        $_POST['endDate'],
        $_POST['nextEventDate'],
        $_POST['notes'],
        $_POST['vetName'],
        $_POST['vetContact']
    );
    $message = $stmt->execute() ? "Health record added!" : "Error!";
}

/* UPDATE HEALTH */
if(isset($_POST['update_health'])){
    $stmt = $conn->prepare("UPDATE healthrecord SET 
        tagId=?, type=?, startDate=?, endDate=?, nextEventDate=?, notes=?, vetName=?, vetContact=? 
        WHERE id=?");

    $stmt->bind_param("ssssssssi",
        $_POST['tagId'],
        $_POST['type'],
        $_POST['startDate'],
        $_POST['endDate'],
        $_POST['nextEventDate'],
        $_POST['notes'],
        $_POST['vetName'],
        $_POST['vetContact'],
        $_POST['id']
    );

    $message = $stmt->execute() ? "Updated!" : "Error!";
}

/* DELETE */
if(isset($_GET['delete_health'])){
    $stmt = $conn->prepare("DELETE FROM healthrecord WHERE id=?");
    $stmt->bind_param("i", $_GET['delete_health']);
    $stmt->execute();
}

/* FETCH */
$animals = $conn->query("SELECT * FROM Animals");
$health = $conn->query("SELECT * FROM healthrecord");
?>

<!DOCTYPE html>
<html>
<head>
<title>Dashboard</title>
<style>
body{font-family:Arial;background:#f4f6f9}
.container{width:50%;margin:auto}
.card{background:#27ae60;color:white;padding:15px;margin:5px;display:inline-block}
table{width:100%;background:white;margin-top:10px;border-collapse:collapse}
th,td{padding:10px;border:1px solid #ccc;text-align:center}
button{padding:8px;background:#27ae60;color:white;border:none}
</style>
</head>

<body>
<div class="container">

<h2>Livestock Dashboard</h2>

<a href="logout.php"><button>Logout</button></a>

<div class="card">Animals: <?= $totalAnimals ?></div>
<div class="card">Records: <?= $totalHealth ?></div>

<?php if($message) echo "<p>$message</p>"; ?>

<h3>Add Animal</h3>
<form method="POST">
<input type="text" name="tagId" placeholder="Tag ID" required>
<input type="text" name="name" placeholder="Name" required>
<input type="text" name="animalType" placeholder="Type">
<input type="text" name="sex" placeholder="Sex">
<input type="text" name="breed" placeholder="Breed">
<input type="date" name="birthdate">
<input type="text" name="ownerContact" placeholder="Owner Contact">
<button name="add_animal">Save</button>
</form>

<h3>Animals</h3>
<table>
<tr><th>Tag</th><th>Name</th><th>Type</th></tr>
<?php while($a=$animals->fetch_assoc()): ?>
<tr>
<td><?= $a['tagId'] ?></td>
<td><?= $a['name'] ?></td>
<td><?= $a['animalType'] ?></td>
</tr>
<?php endwhile; ?>
</table>

</div>
</body>
</html>