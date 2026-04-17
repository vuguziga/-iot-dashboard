<?php
session_start();
require "belldb.php";

if(isset($_POST['login'])){
    $user = $_POST['username'];
    $pass = $_POST['password'];

    $res = $conn->query("SELECT * FROM users WHERE username='$user'");

    if($res->num_rows > 0){
        $row = $res->fetch_assoc();

        if(password_verify($pass, $row['password_hash'])){
            $_SESSION['user'] = $row['username'];
            header("Location:dashboar_bell.php");
            exit();
        } else {
            $error = "Wrong password";
        }
    } else {
        $error = "User not found";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Login</title>
<style>
body{font-family:Arial;background:#2f3542;color:white;text-align:center;margin-top:100px;}
input{padding:10px;margin:5px;width:200px;}
button{padding:10px 20px;background:#1e90ff;color:white;border:none;}
</style>
</head>
<body>

<h2>Smart Bell Login</h2>

<?php if(isset($error)) echo "<p style='color:red'>$error</p>"; ?>

<form method="POST">
<input type="text" name="username" placeholder="Username" required><br>
<input type="password" name="password" placeholder="Password" required><br>
<button name="login">Login</button>
</form>

</body>
</html>