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
   UPDATE HEALTH
====================== */
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

    $message = $stmt->execute() ? "Updated successfully!" : "Error updating!";
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
   EDIT FETCH
====================== */
$editData = null;
if(isset($_GET['edit_health'])){
    $id = $_GET['edit_health'];
    $result = $conn->query("SELECT * FROM healthrecord WHERE id=$id");
    $editData = $result->fetch_assoc();
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
.container{width:40%;margin:auto}

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
padding:6px;
margin:6px;
border-radius:5px;
}

label{display:block;margin-top:10px}

input,select{
width:90%;
padding:8px;
margin-top:5px;
border-radius:5px;
}

/* BUTTON */
button{
margin-top:10px;
padding:10px;
background:#27ae60;
color:white;
border:none;
cursor:pointer;
}

button:hover{background:#1e8449}

/* TABLE */
table{
width:100%;
background:white;
border-collapse:collapse;
margin-top:10px;
}

th,td{
padding:10px;
border:1px solid #ccc;
text-align:center;
}

th{
background:red;
color:white;
}

/* BADGES */
.badge{
padding:4px 8px;
border-radius:10px;
color:white;
}

.sick{background:red;}
.healthy{background:green;}
.pregnant{background:orange;}
.vaccine{background:blue;}

/* ICON LINKS */
a i{
margin:0 6px;
color:black;
}

a i:hover{
color:#27ae60;
}
</style>
</head>

<body>
<div class="container">

<h1><i class="fa-solid fa-cow"></i> Livestock Dashboard</h1>

<div class="card"><i class="fa-solid fa-cow"></i> Animals: <?= $totalAnimals ?></div>
<div class="card"><i class="fa-solid fa-notes-medical"></i> Records: <?= $totalHealth ?></div>

<?php if($message) echo "<p>$message</p>"; ?>

<!-- SEARCH -->
<form method="GET">
<input type="text" name="search" placeholder="Search animal">
<button><i class="fa-solid fa-search"></i> Search</button>
</form>

<!-- ADD ANIMAL -->
<h3><i class="fa-solid fa-plus"></i> Add Animal</h3>
<form method="POST">
<input type="text" name="tagId" placeholder="Tag ID" required>
<input type="text" name="name" placeholder="Name" required>
<input type="text" name="animalType" placeholder="Type">
<select name="sex"><option>Male</option><option>Female</option></select>
<input type="text" name="breed" placeholder="Breed">
<input type="date" name="birthdate">
<input type="text" name="ownerContact" placeholder="Owner Contact">
<button name="add_animal"><i class="fa-solid fa-save"></i> Save</button>
</form>

<!-- ANIMALS TABLE -->
<table>
<tr><th>Tag</th><th>Name</th><th>Type</th></tr>
<?php while($row=$animals->fetch_assoc()): ?>
<tr>
<td><?= $row['tagId'] ?></td>
<td><?= $row['name'] ?></td>
<td><?= $row['animalType'] ?></td>
</tr>
<?php endwhile; ?>
</table>

<!-- HEALTH FORM -->
<h3><i class="fa-solid fa-stethoscope"></i> Health Record</h3>
<form method="POST">

<input type="hidden" name="id" value="<?= $editData['id'] ?? '' ?>">

<select name="tagId" required>
<option value="">Select Animal</option>
<?php 
$tags2 = $conn->query("SELECT tagId FROM Animals");
while($t=$tags2->fetch_assoc()): ?>
<option value="<?= $t['tagId'] ?>"
<?= (isset($editData) && $editData['tagId']==$t['tagId'])?'selected':'' ?>>
<?= $t['tagId'] ?>
</option>
<?php endwhile; ?>
</select>

<select name="type">
<option <?= ($editData['type'] ?? '')=="Healthy"?'selected':'' ?>>Healthy</option>
<option <?= ($editData['type'] ?? '')=="Sick"?'selected':'' ?>>Sick</option>
<option <?= ($editData['type'] ?? '')=="Pregnant"?'selected':'' ?>>Pregnant</option>
<option <?= ($editData['type'] ?? '')=="Vaccination"?'selected':'' ?>>Vaccination</option>
</select>

<input type="date" name="startDate" value="<?= $editData['startDate'] ?? '' ?>">
<input type="date" name="endDate" value="<?= $editData['endDate'] ?? '' ?>">
<input type="date" name="nextEventDate" value="<?= $editData['nextEventDate'] ?? '' ?>">
<input type="text" name="notes" placeholder="Notes" value="<?= $editData['notes'] ?? '' ?>">
<input type="text" name="vetName" placeholder="Vet Name" value="<?= $editData['vetName'] ?? '' ?>">
<input type="text" name="vetContact" placeholder="Vet Contact" value="<?= $editData['vetContact'] ?? '' ?>">

<button name="<?= $editData ? 'update_health' : 'add_health' ?>">
<i class="fa-solid fa-save"></i>
<?= $editData ? 'Update' : 'Save' ?>
</button>

</form>

<!-- HEALTH TABLE -->
<table>
<tr><th>ID</th><th>Tag</th><th>Type</th><th>Start</th><th>Action</th></tr>

<?php while($row=$health->fetch_assoc()): ?>
<tr>
<td><?= $row['id'] ?></td>
<td><?= $row['tagId'] ?></td>

<td>
<?php
$type=strtolower($row['type']);
if($type=="sick") echo "<span class='badge sick'><i class='fa-solid fa-virus'></i> Sick</span>";
elseif($type=="healthy") echo "<span class='badge healthy'><i class='fa-solid fa-heart'></i> Healthy</span>";
elseif($type=="pregnant") echo "<span class='badge pregnant'><i class='fa-solid fa-baby'></i> Pregnant</span>";
elseif($type=="vaccination") echo "<span class='badge vaccine'><i class='fa-solid fa-syringe'></i> Vaccination</span>";
?>
</td>

<td><?= $row['startDate'] ?></td>

<td>
<a href="?edit_health=<?= $row['id'] ?>">
<i class="fa-solid fa-pen"></i>
</a>

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