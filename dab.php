<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "livestock";

$conn =mysqli_connect($servername, $username, $password, $dbname);
 if ($conn) 
    {
    echo "connection successfully";
   }
 else
    {
        
  echo "SQL error: " . mysqli_connect_error();
}
?>