<?php
$host = "localhost";
$db   = "school_bell";
$user = "root";
$pass = "";

$conn =mysqli_connect($host, $user, $pass, $db);

if ($conn) 
    {
    echo "connected";
}
else
    {
        echo "does not connected";
    }
?>