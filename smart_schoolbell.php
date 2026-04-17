<?php
session_start();
require "belldb.php";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/* ================= REAL-TIME CHECK API ================= */
if(isset($_GET['live'])){
    $nowDay = date('w');
    $nowTime = date('H:i:00');

    $res = $conn->query("
        SELECT id FROM schedule_entry
        WHERE day_of_week='$nowDay'
        AND ring_time='$nowTime'
        LIMIT 1
    ");

    echo json_encode([
        "time" => date("H:i:s"),
        "ring" => ($res->num_rows > 0) ? 1 : 0
    ]);
    exit;
}

/* ================= STATUS API ================= */
if(isset($_GET['status'])){
    $res = $conn->query("SELECT manual_ring FROM settings LIMIT 1");
    $row = $res->fetch_assoc();

    echo json_encode([
        "time" => date("H:i:s"),
        "ring" => $row['manual_ring']
    ]);
    exit;
}

/* ================= RESET ================= */
if(isset($_GET['reset'])){
    $conn->query("UPDATE settings SET manual_ring=0");
}

/* ================= ADD SCHEDULE ================= */
if(isset($_POST['add_entry'])){
    $day = (int)$_POST['day'];
    $time = $_POST['time'];
    $label = $conn->real_escape_string($_POST['label']);
    $duration = (int)$_POST['duration'];

    if($time && $label){
        $conn->query("
        INSERT INTO schedule_entry (day_of_week, ring_time, label, duration_seconds)
        VALUES ('$day','$time','$label','$duration')
        ");
    }
}

/* ================= DELETE SCHEDULE ================= */
if(isset($_GET['del'])){
    $id = (int)$_GET['del'];
    $conn->query("DELETE FROM schedule_entry WHERE id=$id");
}

/* ================= ADD SPECIAL ================= */
if(isset($_POST['add_special'])){
    $date = $_POST['date'];
    $label = $conn->real_escape_string($_POST['label']);

    if($date && $label){
        $conn->query("
        INSERT INTO special_days (calendar_date,label,is_enabled)
        VALUES ('$date','$label',1)
        ");
    }
}

/* ================= DELETE SPECIAL ================= */
if(isset($_GET['del_special'])){
    $id = (int)$_GET['del_special'];
    $conn->query("DELETE FROM special_days WHERE id=$id");
}

/* ================= ADD USER (FIXED DUPLICATE ERROR) ================= */
if(isset($_POST['add_user'])){
    $user = $conn->real_escape_string($_POST['username']);
    $pass = password_hash($_POST['password'], PASSWORD_DEFAULT);

    if($user){

        $check = $conn->query("SELECT id FROM users WHERE username='$user'");

        if($check->num_rows == 0){
            $conn->query("
            INSERT INTO users (username,password_hash)
            VALUES ('$user','$pass')
            ");
        } else {
            echo "<script>alert('User already exists');</script>";
        }
    }
}

/* ================= MANUAL RING ================= */
if(isset($_POST['ring'])){
    $conn->query("UPDATE settings SET manual_ring=1");
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Smart Bell System</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
body{margin:0;font-family:Arial;background:#f1f2f6;}
.sidebar{width:220px;height:100vh;background:#2f3542;position:fixed;color:white;}
.sidebar h2{text-align:center;padding:15px;}
.sidebar a{display:block;padding:12px;color:white;text-decoration:none;}
.sidebar a:hover{background:#57606f;}
.main{margin-left:220px;padding:20px;}
.card{background:white;padding:15px;margin-bottom:20px;border-radius:6px;}
table{width:100%;border-collapse:collapse;}
th,td{border:1px solid #ddd;padding:8px;text-align:center;}
th{background:#2f3542;color:white;}
.btn{padding:6px 10px;border:none;color:white;border-radius:4px;cursor:pointer;}
.green{background:#2ed573;}
.red{background:#ff4757;}
.blue{background:#1e90ff;}
</style>
</head>

<body>

<div class="sidebar">
<h2>🔔 SmartBell</h2>
<a href="?">Home</a>
<a href="?page=schedule">Schedule</a>
<a href="?page=special">Special</a>
<a href="?page=users">Users</a>
<a href="?page=control">Control</a>
</div>

<div class="main">

<?php $page = $_GET['page'] ?? 'home'; ?>

<!-- ================= HOME ================= -->
<?php if($page=="home"){ ?>
<div class="card">
<h2>System Overview</h2>
<p>Smart Bell Running</p>
</div>

<div class="card">
<h3>Live Monitor</h3>
<p>Time: <span id="clock">--</span></p>
<p>Status: <span id="status">Waiting...</span></p>
</div>
<?php } ?>

<!-- ================= SCHEDULE ================= -->
<?php if($page=="schedule"){ ?>
<div class="card">
<h3>Add Schedule</h3>

<form method="POST">
<select name="day">
<option value="0">Mon</option>
<option value="1">Tue</option>
<option value="2">Wed</option>
<option value="3">Thu</option>
<option value="4">Fri</option>
<option value="5">Sat</option>
<option value="6">Sun</option>
</select>

<input type="time" name="time" required>
<input type="text" name="label" required>
<input type="number" name="duration" value="5">

<button name="add_entry" class="btn blue">Add</button>
</form>
</div>

<div class="card">
<table>
<tr><th>Day</th><th>Time</th><th>Label</th><th>Action</th></tr>

<?php
$res=$conn->query("SELECT * FROM schedule_entry");
$days=["Mon","Tue","Wed","Thu","Fri","Sat","Sun"];
while($r=$res->fetch_assoc()){
?>
<tr>
<td><?=$days[$r['day_of_week']]?></td>
<td><?=$r['ring_time']?></td>
<td><?=$r['label']?></td>
<td>
<a href="?page=schedule&del=<?=$r['id']?>" class="btn red">X</a>
</td>
</tr>
<?php } ?>
</table>
</div>
<?php } ?>

<!-- ================= SPECIAL ================= -->
<?php if($page=="special"){ ?>
<div class="card">
<h3>Add Special Day</h3>

<form method="POST">
<input type="date" name="date" required>
<input type="text" name="label" required>
<button name="add_special" class="btn blue">Add</button>
</form>
</div>

<div class="card">
<table>
<tr><th>Date</th><th>Label</th><th>Action</th></tr>

<?php
$res=$conn->query("SELECT * FROM special_days");
while($r=$res->fetch_assoc()){
?>
<tr>
<td><?=$r['calendar_date']?></td>
<td><?=$r['label']?></td>
<td>
<a href="?page=special&del_special=<?=$r['id']?>" class="btn red">X</a>
</td>
</tr>
<?php } ?>
</table>
</div>
<?php } ?>

<!-- ================= USERS ================= -->
<?php if($page=="users"){ ?>
<div class="card">
<h3>Add User</h3>

<form method="POST">
<input type="text" name="username" required>
<input type="password" name="password" required>
<button name="add_user" class="btn blue">Add</button>
</form>
</div>
<?php } ?>

<!-- ================= CONTROL + REAL TIME ================= -->
<?php if($page=="control"){ ?>
<div class="card">
<h3>Manual Control</h3>
<form method="POST">
<button name="ring" class="btn green">Ring Now</button>
</form>
</div>

<div class="card">
<h3>Real-Time Monitoring</h3>

<p>Time: <span id="rt_time">--</span></p>
<p>Status: <span id="rt_status">Waiting...</span></p>
</div>
<?php } ?>

</div>

<script>

// CLOCK
setInterval(()=>{
document.getElementById("clock").innerText=new Date().toLocaleTimeString();
},1000);

// HOME STATUS CHECK
setInterval(()=>{
fetch('?live=1')
.then(r=>r.json())
.then(d=>{
if(document.getElementById("status")){
document.getElementById("status").innerText=d.ring?"🔔 RINGING":"Idle";
}
});

},1000);

// CONTROL REAL TIME
setInterval(()=>{
fetch('?live=1')
.then(r=>r.json())
.then(d=>{
let t=document.getElementById("rt_time");
let s=document.getElementById("rt_status");

if(t) t.innerText=d.time;

if(s){
s.innerText=d.ring?"🔔 ISHA IRASONA":"Idle";
}

});
},1000);

</script>

</body>
</html>