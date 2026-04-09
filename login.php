<?php
session_start();
$conn =mysqli_connect("localhost", "root", "", "livestock");
if($conn){
 echo "Connection successfully";
}
else
    {
        echo "connection failed";
    }

$message = "";

if(isset($_POST['login'])){
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE username=?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result->num_rows > 0){
        $user = $result->fetch_assoc();
        if(password_verify($password, $user['password'])){
            $_SESSION['user'] = $username;
            header("Location: index.php");
            exit();
        } else {
            $message = "Wrong password!";
        }
    } else {
        $message = "User not found!";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Livestock Login</title>
<style>
body{font-family:Arial;text-align:center;background:#f4f6f9}
form{background:white;padding:20px;margin-top:100px;display:inline-block;border-radius:8px}
input{display:block;margin:10px auto;padding:10px;width:200px}
button{padding:10px;background:#27ae60;color:white;border:none;cursor:pointer}
button:hover{background:#1e8449}
p{color:red}
</style>
</head>
<body>

<h2>Livestock Login</h2>
<?php if($message) echo "<p>$message</p>"; ?>

<form method="POST">
<input type="text" name="username" placeholder="Username" required>
<input type="password" name="password" placeholder="Password" required>
<button name="login">Login</button>
</form>

</body>
</html>