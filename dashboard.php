<?php
// dashboard.php

// Database connection
$host = "localhost";
$db_name = "L5NIT";
$username = "root";
$password = "";

$conn = new mysqli($host, $username, $password, $db_name);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// Fetch historical data (latest 20 entries)
$historySql = "SELECT temperature, humidity, created_at FROM sensor_data ORDER BY created_at DESC LIMIT 20";
$historyResult = $conn->query($historySql);

// Function to get latest sensor data (for AJAX)
if (isset($_GET['latest'])) {
    $latestSql = "SELECT temperature, humidity, created_at FROM sensor_data ORDER BY created_at DESC LIMIT 1";
    $latestResult = $conn->query($latestSql);
    if ($latestResult && $latestResult->num_rows > 0) {
        $row = $latestResult->fetch_assoc();
        echo json_encode($row);
    } else {
        echo json_encode(['temperature' => null, 'humidity' => null]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Sensor Dashboard</title>
<style>
  body {
    font-family: Arial, sans-serif;
    margin: 0; padding: 20px;
    background: white;
  }
  h1 {
    text-align: center;
  }
  .cards {
    display: flex;
    justify-content: center;
    gap: 20px;
    margin-bottom: 30px;
    flex-wrap: wrap;
  }
  .card {
    background: brown;
    border-radius: 10px;
    padding: 20px 40px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    min-width: 150px;
    text-align: center;
  }
  /* Add this inside your <style> block, replacing or updating existing table styles */
table {
    width: 100%;
    border-collapse: collapse;
    background: orange;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border: 1px solid #ddd; /* outer border for the table */
}

th, td {
    padding: 12px 15px;
    text-align: center;
    border-bottom: 1px solid #ddd; /* horizontal lines */
    border-right: 1px solid #ddd;  /* vertical lines */
}

th:last-child, td:last-child {
    border-right: none; /* remove extra right border on last column */
}

th {
    background-color: #2196F3;
    color: white;
}
  .temperature {
    font-size: 3rem;
    font-weight: bold;
  }
  .humidity {
    font-size: 3rem;
    font-weight: bold;
  }
  /* Color coding for temperature */
  .temp-low { color: #21f0f3; }      /* Blue for cold */
  .temp-medium { color: #aaff00; }   /* Orange for moderate */
  .temp-high { color: #f44336; }     /* Red for hot */

  table {
    width: 100%;
    border-collapse: collapse;
    background: black;
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  }
  th, td {
    padding: 12px 15px;
    text-align: center;
    border-bottom: 1px solid #ddd;
  }
  th {
    background-color: #2196F3;
    color: white;
  }
  tr:nth-child(even) {
    background-color: #f9f7fc;
  }

  /* Responsive */
  @media (max-width: 600px) {
    .cards {
      flex-direction: column;
      gap: 15px;
    }
  }
</style>
</head>
<body>

<h1>Sensor Data Dashboard</h1>

<div class="cards">
  <div class="card">
    <div>Temperature (°C)</div>
    <div id="temperature" class="temperature temp-low">--</div>
  </div>
  <div class="card">
    <div>Humidity (%)</div>
    <div id="humidity" class="humidity">--</div>
  </div>
</div>

<h2>Historical Data (Latest 20 readings)</h2>
<table>
  <thead>
    <tr>
      <th>Temperature (°C)</th>
      <th>Humidity (%)</th>
      <th>Timestamp</th>
    </tr>
  </thead>
  <tbody>
    <?php
    if ($historyResult && $historyResult->num_rows > 0) {
        while ($row = $historyResult->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($row['temperature']) . "</td>";
            echo "<td>" . htmlspecialchars($row['humidity']) . "</td>";
            echo "<td>" . htmlspecialchars($row['created_at']) . "</td>";
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='3'>No data available</td></tr>";
    }
    ?>
  </tbody>
</table>

<script>
// Function to set temperature color based on value
function getTempClass(temp) {
  if (temp < 20) return 'temp-low';
  if (temp < 30) return 'temp-medium';
  return 'temp-high';
}

// AJAX to fetch latest data every 10 seconds
function fetchLatestData() {
  fetch('dashboard.php?latest=1')
    .then(response => response.json())
    .then(data => {
      if (data.temperature !== null) {
        const tempElem = document.getElementById('temperature');
        tempElem.textContent = data.temperature.toFixed(2);
        tempElem.className = 'temperature ' + getTempClass(data.temperature);

        const humElem = document.getElementById('humidity');
        humElem.textContent = data.humidity.toFixed(2);
      }
    })
    .catch(error => {
      console.error('Error fetching latest data:', error);
    });
}

// Initial fetch
fetchLatestData();

// Refresh every 10 seconds (10000 ms)
setInterval(fetchLatestData, 10000);
</script>

</body>
</html>

<?php
$conn->close();
?>