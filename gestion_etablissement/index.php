<?php
session_start();

// Connexion à la base de données
$host = 'localhost';
$db = 'school_management';
$user = 'root';
$pass = '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Vérification du formulaire de connexion
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $role = $_POST['role'];

    // Vérification des identifiants
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = :username AND role = :role");
    $stmt->execute(['username' => $username, 'role' => $role]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];

        // Redirection en fonction du rôle
        switch ($user['role']) {
            case 'admin':
                header('Location: admin_dashboard.php');
                break;
            case 'teacher':
                header('Location: teacher_dashboard.php');
                break;
            case 'student':
                header('Location: student_dashboard.php');
                break;
        }
        exit();
    } else {
        $error = "Identifiants incorrects.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container">
        <h1>Connexion</h1>
        <?php if (isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
        <form method="POST" action="">
            <label for="role">Rôle :</label>
            <select name="role" id="role" required>
                <option value="admin">Admin</option>
                <option value="teacher">Enseignant</option>
                <option value="student">Étudiant</option>
            </select><br><br>
            <label for="username">Nom d'utilisateur :</label>
            <input type="text" name="username" id="username" required><br><br>
            <label for="password">Mot de passe :</label>
            <input type="password" name="password" id="password" required><br><br>
            <button type="submit">Se connecter</button>
        </form>
    </div>
</body>
</html>