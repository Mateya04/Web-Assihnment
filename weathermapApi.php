<?php
$apiKey = "d572957e551dee7c15a1de750a488898";
$city = "";
$weatherData = null;
$errorMessage = "";

// MySQL connection
$conn = new mysqli("localhost", "root", "", "weather_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['clear']) && $_POST['clear'] == '1') {
        // Clear the history
        $conn->query("DELETE FROM searches");
    } else {
        $city = trim($_POST["city"] ?? '');

        if ($city !== '') {
            $apiUrl = "https://api.openweathermap.org/data/2.5/weather?q=" . urlencode($city) . "&appid=" . $apiKey . "&units=metric";
            $response = @file_get_contents($apiUrl);
            if ($response === FALSE) {
                $weatherData = null;
                $errorMessage = "Unable to reach weather service. Please try again later.";
            } else {
                $weatherData = json_decode($response, true);
                if (!$weatherData || !isset($weatherData["cod"])) {
                    $errorMessage = "Unexpected response from weather service.";
                } elseif ($weatherData["cod"] != 200) {
                    $errorMessage = htmlspecialchars($weatherData["message"] ?: 'City not found or API error.');
                } else {
                    $stmt = $conn->prepare("INSERT INTO searches (city) VALUES (?)");
                    $stmt->bind_param("s", $city);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        } else {
            $errorMessage = "Please enter a city name.";
        }
    }
}

// Load search history
$historyResult = $conn->query("SELECT city, searched_at FROM searches ORDER BY id DESC LIMIT 10");
?>

<!DOCTYPE html>
<html>
<head>
    <title>PHP Weather Dashboard</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(to right, #74ebd5, #ACB6E5);
            color: #333;
            margin: 0;
            padding: 20px;
        }

        h1 {
            text-align: center;
            color: #222;
        }

        form {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 20px;
        }

        input[type="text"], select, button {
            padding: 10px 15px;
            border-radius: 5px;
            border: 1px solid #ccc;
            font-size: 16px;
        }

        button {
            background-color: #3498db;
            color: white;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        button:hover {
            background-color: #2980b9;
        }

        button[name="clear"] {
            background-color: #e74c3c;
        }

        button[name="clear"]:hover {
            background-color: #c0392b;
        }

        .weather {
            background: #ffffffcc;
            border-radius: 10px;
            padding: 20px;
            max-width: 500px;
            margin: 0 auto 20px auto;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            text-align: center;
        }

        .weather img {
            width: 80px;
            height: 80px;
        }

        .weather p {
            font-size: 18px;
            margin: 5px 0;
        }

        .error {
            text-align: center;
            color: #e74c3c;
            font-weight: bold;
        }

        .history {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffffaa;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .history h3 {
            text-align: center;
            margin-bottom: 10px;
        }

        ul {
            list-style: none;
            padding-left: 0;
        }

        ul li {
            margin: 5px 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 16px;
        }

    </style>
</head>
<body>
    <h1>Weather Dashboard</h1>

    <form method="POST">
        <input type="text" name="city" placeholder="Enter city name" required value="<?= htmlspecialchars($city) ?>" />
        <button type="submit">Get Weather</button>
        <button type="submit" name="clear" value="1" onclick="return confirm('Are you sure you want to clear the search history?')">Clear History</button>
    </form>

    <?php if ($errorMessage): ?>
        <p class="error"><?= $errorMessage ?></p>
    <?php endif; ?>

    <?php if ($weatherData && $weatherData["cod"] == 200): ?>
        <div class="weather">
            <h2>Weather in <?= htmlspecialchars($weatherData["name"]) ?></h2>
            <p><strong>Temperature:</strong> <?= $weatherData["main"]["temp"] ?> °C</p>
            <p><strong>Condition:</strong> <?= htmlspecialchars($weatherData["weather"][0]["description"]) ?></p>
            <p><strong>Humidity:</strong> <?= $weatherData["main"]["humidity"] ?>%</p>
        </div>
    <?php endif; ?>

    <div class="history">
        <h3>Search History</h3>
        <ul>
            <?php while ($row = $historyResult->fetch_assoc()): ?>
                <li><?= htmlspecialchars($row['city']) ?> (<?= $row['searched_at'] ?>)</li>
            <?php endwhile; ?>
        </ul>
    </div>
</body>
</html>

<?php $conn->close(); ?>
