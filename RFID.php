<?php
$conn = new mysqli("localhost", "root", "", "livestock");
if ($conn->connect_error) die("Connection failed");

/* ======================
   STATS
====================== */
$totalAnimals = $conn->query("SELECT COUNT(*) as total FROM Animals")->fetch_assoc()['total'];
$totalHealth = $conn->query("SELECT COUNT(*) as total FROM healthrecord")->fetch_assoc()['total'];

$message = "";

/* ======================
   ADD ANIMAL
====================== */
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

/* ======================
   ADD HEALTH
====================== */
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

/* ======================
   DELETE HEALTH
====================== */
if(isset($_GET['delete_health'])){
    $stmt = $conn->prepare("DELETE FROM healthrecord WHERE id=?");
    $stmt->bind_param("i", $_GET['delete_health']);
    $stmt->execute();
}

/* ======================
   SEARCH
====================== */
$search="";
if(isset($_GET['search'])){
    $search=$_GET['search'];
    $animals=$conn->query("SELECT * FROM Animals WHERE name LIKE '%$search%'");
}else{
    $animals=$conn->query("SELECT * FROM Animals");
}

/* ======================
   FETCH TAGS
====================== */
$tags = $conn->query("SELECT tagId FROM Animals");

/* ======================
   FETCH HEALTH
====================== */
$health = $conn->query("SELECT * FROM healthrecord");
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Livestock Dashboard</title>

<!-- FONT AWESOME -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
body{font-family:Arial;background:#f4f6f9}
.container{width:30%;margin:auto}

/* HEADER */
h1{text-align:center}

/* CARDS */
.card{
display:inline-block;
padding:20px;
margin:10px;
background:#27ae60;
color:white;
border-radius:8px;
}

/* FORM */
form{
background:violet;
padding:4px;
margin:4px;
border-radius:1px;
}

label{display:block;margin-top:10px}

input,select{
width:80%;
padding:8px;
margin-top:5px;
border:1px solid #ccc;
border-radius:5px;
}

/* BUTTON */
button{
margin-top:10px;
padding:10px;
background:#27ae60;
color:pink;
border:none;
cursor:pointer;
}

button:hover{background:#1e8449}

/* TABLE */
table{
width:90%;
background:white;
border-collapse:collapse;
margin-top:10px;
}

th,td{
padding:10px;
border:1px solid yellow;
text-align:center;
}

th{
background:red;
color:black;
}

/* BADGES */
.badge{
padding:4px 9px;
border-radius:10px;
color:white;
font-size:13px;
}

.sick{background:green;}
.healthy{background:green;}
.pregnant{background:green;}
.vaccine{background:green;}
</style>
</head>

<body>
<div class="container">

<h1><i class="fa-solid fa-cow"></i> Livestock Dashboard</h1>

<!-- STATS -->
<div class="card"><i class="fa-solid fa-cow"></i> Animals: <?= $totalAnimals ?></div>
<div class="card"><i class="fa-solid fa-notes-medical"></i> Records: <?= $totalHealth ?></div>

<?php if($message) echo "<p>$message</p>"; ?>

<!-- SEARCH -->
<form method="GET">
<input type="text" name="search" placeholder="Search animal name">
<button><i class="fa-solid fa-search"></i> Search</button>
</form>

<!-- ADD ANIMAL -->
<h2><i class="fa-solid fa-plus"></i> Add Animal</h2>
<form method="POST">

<label>Tag ID:</label>
<input type="text" name="tagId" required>

<label>Name:</label>
<input type="text" name="name" required>

<label>Type:</label>
<input type="text" name="animalType">

<label>Sex:</label>
<select name="sex">
<option>Male</option>
<option>Female</option>
</select>

<label>Breed:</label>
<input type="text" name="breed">

<label>Birthdate:</label>
<input type="date" name="birthdate">

<label>Owner Contact:</label>
<input type="text" name="ownerContact">

<button name="add_animal"><i class="fa-solid fa-save"></i> Save</button>
</form>

<!-- ANIMAL TABLE -->
<table>
<tr>
<th>Tag</th><th>NAME</th><th>Type</th>
</tr>

<?php while($row=$animals->fetch_assoc()): ?>
<tr>
<td><?= $row['tagId'] ?></td>
<td><?= $row['name'] ?></td>
<td><?= $row['animalType'] ?></td>
</tr>
<?php endwhile; ?>
</table>

<!-- HEALTH -->
<h2><i class="fa-solid fa-stethoscope"></i> Health Record</h2>

<form method="POST">

<label>Animal</label>
<select name="tagId" required>
<option value="">Select Animal</option>
<?php while($t=$tags->fetch_assoc()): ?>
<option value="<?= $t['tagId'] ?>"><?= $t['tagId'] ?></option>
<?php endwhile; ?>
</select>

<label>Type</label>
<select name="type">
<option>Healthy</option>
<option>Sick</option>
<option>Pregnant</option>
<option>Vaccination</option>
</select>

<label>Start Date:</label>
<input type="date" name="startDate">

<label>End Date:</label>
<input type="date" name="endDate">

<label>Next Event:</label>
<input type="date" name="nextEventDate">

<label>Notes:</label>
<input type="text" name="notes">

<label>Vet Name:</label>
<input type="text" name="vetName">

<label>Vet Contact:</label>
<input type="text" name="vetContact">

<button name="add_health"><i class="fa-solid fa-save"></i> Save</button>
</form>

<!-- HEALTH TABLE -->
<table>
<tr>
<th>ID</th><th>TAG</th><th>Type</th><th>Start</th><th>Action</th>
</tr>

<?php while($row=$health->fetch_assoc()): ?>
<tr>
<td><?= $row['id'] ?></td>
<td><?= $row['tagId'] ?></td>

<td>
<?php
$type = strtolower($row['type']);

if($type=="sick"){
echo "<span class='badge sick'><i class='fa-solid fa-virus'></i> Sick</span>";
}elseif($type=="healthy"){
echo "<span class='badge healthy'><i class='fa-solid fa-heart'></i> Healthy</span>";
}elseif($type=="pregnant"){
echo "<span class='badge pregnant'><i class='fa-solid fa-baby'></i> Pregnant</span>";
}elseif($type=="vaccination"){
echo "<span class='badge vaccine'><i class='fa-solid fa-syringe'></i> Vaccination</span>";
}
?>
</td>

<td><?= $row['startDate'] ?></td>

<td>
<a href="?delete_health=<?= $row['id'] ?>" onclick="return confirm('Delete?')">
<i class="fa-solid fa-trash"></i>
</a>
</td>

</tr>
<?php endwhile; ?>

</table>

</div>
</body>
</html>