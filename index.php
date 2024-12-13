<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_PORT', 'port');
define('DB_USER', 'username');
define('DB_PASS', 'password');
define('DB_NAME', 'auth');

// Function to connect to the database
function db_connect() {
    $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    if ($mysqli->connect_error) {
        die('Connect Error (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
    // Set charset to utf8
    if (!$mysqli->set_charset("utf8")) {
        die("Error loading character set utf8: " . $mysqli->error);
    }
    return $mysqli;
}

// Function to generate the next username in format "number#1"
function generate_username($mysqli) {
    $query = "SELECT MAX(CAST(SUBSTRING_INDEX(username, '#', 1) AS UNSIGNED)) AS max_num FROM account";
    $result = $mysqli->query($query);
    if ($result) {
        $row = $result->fetch_assoc();
        $next_num = isset($row['max_num']) ? intval($row['max_num']) + 1 : 1;
        return $next_num . '#1';
    } else {
        // In case of query failure, default to 1#1
        return '1#1';
    }
}

function sanitize_input($data) {
    return htmlspecialchars(trim($data));
}

$errors = [];
$success_message = "";
$email = "";
$realmlist = 'Set Portal "Legion Project"'; // Added realmlist variable. Replace with your server connection details.

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? sanitize_input($_POST['email']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 6) { // Example: minimum 6 characters
        $errors[] = "Password must be at least 6 characters long.";
    }

    if (empty($errors)) {
        $mysqli = db_connect();

        $mysqli->begin_transaction();

        try {
            $stmt = $mysqli->prepare("SELECT id FROM battlenet_accounts WHERE email = ?");
            if (!$stmt) {
                throw new Exception("Prepare statement failed: " . $mysqli->error);
            }
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                throw new Exception("Email is already taken.");
            }
            $stmt->close();

            $username = generate_username($mysqli);

            $sha_pass_hash = sha1(strtoupper($username) . ":" . strtoupper($password));

            $stmt = $mysqli->prepare("INSERT INTO battlenet_accounts (email, sha_pass_hash, balans, activate) VALUES (?, ?, 30, 1)");
            if (!$stmt) {
                throw new Exception("Prepare statement failed: " . $mysqli->error);
            }
            $stmt->bind_param("ss", $email, $sha_pass_hash);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            $battlenet_account_id = $stmt->insert_id;
            $stmt->close();

            $stmt = $mysqli->prepare("INSERT INTO account (username, sha_pass_hash, email, battlenet_account) VALUES (?, ?, ?, ?)");
            if (!$stmt) {
                throw new Exception("Prepare statement failed: " . $mysqli->error);
            }
            $stmt->bind_param("sssi", $username, $sha_pass_hash, $email, $battlenet_account_id);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            $account_id = $stmt->insert_id;
            $stmt->close();

            $mysqli->commit();

    		$success_message = "Registration successful! <strong>{$email}</strong>, Welcome to WoW Legion.";

            $email = "";

        } catch (Exception $e) {
            $mysqli->rollback();
            $errors[] = $e->getMessage();
        }

        $mysqli->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>WoW Legion Registration</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #fafafa;
            margin: 0;
            padding: 0;
            display: flex;
            min-height: 100vh;
            flex-direction: column;
        }

        .container {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px 20px;
        }

        .registration-form {
            background-color: #ffffff;
            padding: 30px 40px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }

        .registration-form h2 {
            margin-bottom: 20px;
            color: #333333;
            text-align: center;
        }

        .error-message {
            color: #e74c3c;
            background-color: #fdecea;
            border: 1px solid #f5c6cb;
            padding: 10px 15px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-size: 0.9em;
        }

        .success-message {
            color: #2ecc71;
            background-color: #eafaf1;
            border: 1px solid #c3e6cb;
            padding: 10px 15px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-size: 0.9em;
        }

        label {
            display: block;
            margin-bottom: 5px;
            color: #555555;
            font-weight: 500;
        }

        input[type="email"],
        input[type="password"] {
            width: 92%;
            padding: 10px 14px;
            margin-bottom: 20px;
            border: 1px solid #cccccc;
            border-radius: 4px;
            font-size: 1em;
            transition: border-color 0.3s;
        }

        input[type="email"]:focus,
        input[type="password"]:focus {
            border-color: #4CAF50;
            outline: none;
            box-shadow: 0 0 5px rgba(76, 175, 80, 0.3);
        }

        button[type="submit"] {
            width: 100%;
            background-color: #4CAF50;
            color: white;
            padding: 12px;
            border: none;
            border-radius: 4px;
            font-size: 1em;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        button[type="submit"]:hover {
            background-color: #45a049;
        }

        .realmlist {
            margin-top: 20px;
            padding: 15px;
            background-color: #f5f5f5;
            border-left: 4px solid #4CAF50;
            border-radius: 4px;
        }

        .realmlist p {
            margin: 0;
            color: #333333;
            font-weight: 500;
        }

        footer {
            background-color: #ffffff;
            padding: 15px 20px;
            text-align: center;
            border-top: 1px solid #e0e0e0;
        }

        footer p {
            margin: 0;
            color: #777777;
            font-size: 0.9em;
        }

        @media (max-width: 480px) {
            .registration-form {
                padding: 20px;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="registration-form">
        <h2>Register</h2>

        <?php
        if (!empty($success_message)) {
            echo "<p class='success-message'>{$success_message}</p>";
        }

        if (!empty($errors)) {
            foreach ($errors as $error) {
                echo "<p class='error-message'>{$error}</p>";
            }
        }
        ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" novalidate>
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($email); ?>" placeholder="you@example.com">

            <label for="password">Password</label>
            <input type="password" id="password" name="password" required minlength="6" placeholder="Enter your password">

            <button type="submit">Register</button>
        </form>

        <div class="realmlist">
            <p><?php echo htmlspecialchars($realmlist); ?></p>
        </div>
    </div>
</div>

<footer>
    <p>&copy; <?php echo date("Y"); ?> WoW Legion Project</p>
</footer>

</body>
</html>
