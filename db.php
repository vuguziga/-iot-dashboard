<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = "localhost";   
$username = "root";
$password = "";
$dbname = "L5NIT";
$conn = mysqli_connect($host, $username, $password, $dbname);

if ($conn) {
  echo "Connection connected"; 
} else {
  // Ereka impamvu nyirizina yo kunanirwa
  echo "SQL error: " . mysqli_connect_error();
}
?>