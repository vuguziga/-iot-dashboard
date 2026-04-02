<?php
session_start();
if(!isset($_SESSION['username'])) header("Location: index.php");

$conn =mysqli_connect("localhost","root","","livestock");
$role = $_SESSION['role'];

function canEdit(){ global $role; return $role=='admin'; }
$message="";

/* ====================== ADD / EDIT ANIMAL ====================== */
if(isset($_POST['add_animal']) && canEdit()){
    $stmt = $conn->prepare("INSERT INTO Animals(tagId,name,animalType,sex,breed,birthdate,ownerContact) VALUES(?,?,?,?,?,?,?)");
    $stmt->bind_param("sssssss",$_POST['tagId'],$_POST['name'],$_POST['animalType'],$_POST['sex'],$_POST['breed'],$_POST['birthdate'],$_POST['ownerContact']);
    $message = $stmt->execute() ? "Animal added!" : "Error!";
}

if(isset($_POST['edit_animal']) && canEdit()){
    $stmt = $conn->prepare("UPDATE Animals SET name=?, animalType=?, sex=?, breed=?, birthdate=?, ownerContact=? WHERE tagId=?");
    $stmt->bind_param("sssssss",$_POST['name'],$_POST['animalType'],$_POST['sex'],$_POST['breed'],$_POST['birthdate'],$_POST['ownerContact'],$_POST['tagId']);
    $message = $stmt->execute() ? "Animal updated!" : "Error!";
}

/* ====================== ADD / EDIT HEALTH ====================== */
if(isset($_POST['add_health']) && canEdit()){
    $stmt = $conn->prepare("INSERT INTO healthrecord(tagId,type,startDate,endDate,nextEventDate,notes,vetName,vetContact) VALUES(?,?,?,?,?,?,?,?)");
    $stmt->bind_param("ssssssss",$_POST['tagId'],$_POST['type'],$_POST['startDate'],$_POST['endDate'],$_POST['nextEventDate'],$_POST['notes'],$_POST['vetName'],$_POST['vetContact']);
    $message = $stmt->execute() ? "Health record added!" : "Error!";
}

if(isset($_POST['edit_health']) && canEdit()){
    $stmt = $conn->prepare("UPDATE healthrecord SET type=?, startDate=?, endDate=?, nextEventDate=?, notes=?, vetName=?, vetContact=? WHERE id=?");
    $stmt->bind_param("sssssssi",$_POST['type'],$_POST['startDate'],$_POST['endDate'],$_POST['nextEventDate'],$_POST['notes'],$_POST['vetName'],$_POST['vetContact'],$_POST['id']);
    $message = $stmt->execute() ? "Health record updated!" : "Error!";
}

/* ====================== DELETE HEALTH ====================== */
if(isset($_GET['delete_health']) && canEdit()){
    $stmt = $conn->prepare("DELETE FROM healthrecord WHERE id=?");
    $stmt->bind_param("i", $_GET['delete_health']);
    $stmt->execute();
}

/* ====================== FETCH DATA ====================== */
$totalAnimals = $conn->query("SELECT COUNT(*) as total FROM Animals")->fetch_assoc()['total'];
$totalHealth = $conn->query("SELECT COUNT(*) as total FROM healthrecord")->fetch_assoc()['total'];

$animals = $conn->query("SELECT * FROM Animals");
$health = $conn->query("SELECT * FROM healthrecord");
$tags = $conn->query("SELECT tagId FROM Animals");
?>
<!DOCTYPE html>
<html>
<head>
<title>Livestock Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
body{font-family:Arial;background:#f4f6f9}
.container{width:80%;margin:auto}
h1{text-align:center}
.card{display:inline-block;padding:20px;margin:10px;background:#27ae60;color:white;border-radius:8px;}
table{width:100%;border-collapse:collapse;margin-top:10px;}
th,td{padding:10px;border:1px solid yellow;text-align:center;}
th{background:red;color:black;}
button{padding:5px;margin:2px;}
</style>
</head>
<body>
<div class="container">
<h1>Livestock Dashboard (<?= strtoupper($role) ?>)</h1>
<div class="card"><i class="fa-solid fa-cow"></i> Animals: <?= $totalAnimals ?></div>
<div class="card"><i class="fa-solid fa-notes-medical"></i> Records: <?= $totalHealth ?></div>
<p style="color:green"><?= $message ?></p>

<!-- Animals Table -->
<h2>Animals</h2>
<table>
<tr><th>Tag</th><th>Name</th><th>Type</th><th>Action</th></tr>
<?php while($a=$animals->fetch_assoc()): ?>
<tr>
<td><?= $a['tagId'] ?></td>
<td><a href="?animal=<?= $a['tagId'] ?>"><?= $a['name'] ?></a></td>
<td><?= $a['animalType'] ?></td>
<td>
<?php if(canEdit()): ?>
<a href="javascript:void(0)" onclick="editAnimal('<?= $a['tagId'] ?>','<?= $a['name'] ?>','<?= $a['animalType'] ?>','<?= $a['sex'] ?>','<?= $a['breed'] ?>','<?= $a['birthdate'] ?>','<?= $a['ownerContact'] ?>')">Edit</a>
<?php else: ?>
View
<?php endif; ?>
</td>
</tr>
<?php endwhile; ?>
</table>

<!-- Health Table -->
<h2>Health Records</h2>
<table>
<tr><th>ID</th><th>Tag</th><th>Type</th><th>Start</th><th>Action</th></tr>
<?php while($h=$health->fetch_assoc()): ?>
<tr>
<td><?= $h['id'] ?></td>
<td><?= $h['tagId'] ?></td>
<td><?= $h['type'] ?></td>
<td><?= $h['startDate'] ?></td>
<td>
<?php if(canEdit()): ?>
<a href="javascript:void(0)" onclick="editHealth(<?= $h['id'] ?>,'<?= $h['tagId'] ?>','<?= $h['type'] ?>','<?= $h['startDate'] ?>','<?= $h['endDate'] ?>','<?= $h['nextEventDate'] ?>','<?= $h['notes'] ?>','<?= $h['vetName'] ?>','<?= $h['vetContact'] ?>')">Edit</a> |
<a href="?delete_health=<?= $h['id'] ?>" onclick="return confirm('Delete?')">Delete</a>
<?php else: ?>
View
<?php endif; ?>
</td>
</tr>
<?php endwhile; ?>
</table>

<script>
function editAnimal(tag,name,type,sex,breed,birth,owner){
    let form = document.createElement('form');
    form.method='POST';
    form.innerHTML=`<input type="hidden" name="tagId" value="${tag}">
    Name: <input type="text" name="name" value="${name}"><br>
    Type: <input type="text" name="animalType" value="${type}"><br>
    Sex: <input type="text" name="sex" value="${sex}"><br>
    Breed: <input type="text" name="breed" value="${breed}"><br>
    Birth: <input type="date" name="birthdate" value="${birth}"><br>
    Owner: <input type="text" name="ownerContact" value="${owner}"><br>
    <button name="edit_animal">Save</button>`;
    document.body.appendChild(form);
    form.submit();
}

function editHealth(id,tag,type,start,end,next,notes,vet,contact){
    let form=document.createElement('form');
    form.method='POST';
    form.innerHTML=`<input type="hidden" name="id" value="${id}">
    Tag: <input type="text" name="tagId" value="${tag}"><br>
    Type: <input type="text" name="type" value="${type}"><br>
    Start: <input type="date" name="startDate" value="${start}"><br>
    End: <input type="date" name="endDate" value="${end}"><br>
    Next: <input type="date" name="nextEventDate" value="${next}"><br>
    Notes: <input type="text" name="notes" value="${notes}"><br>
    Vet Name: <input type="text" name="vetName" value="${vet}"><br>
    Vet Contact: <input type="text" name="vetContact" value="${contact}"><br>
    <button name="edit_health">Save</button>`;
    document.body.appendChild(form);
    form.submit();
}
</script>
</div>
</body>
</html>