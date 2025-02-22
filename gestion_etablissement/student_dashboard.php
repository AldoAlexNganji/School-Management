<?php
session_start();

// Vérification du rôle de l'utilisateur
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: index.php');
    exit();
}

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

// Récupérer les informations de l'étudiant connecté
$student_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM students WHERE user_id = :user_id");
$stmt->execute(['user_id' => $student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Récupérer les matières suivies par l'étudiant
$stmt = $conn->prepare("SELECT s.name, ss.note FROM subjects s JOIN student_subjects ss ON s.id = ss.subject_id WHERE ss.student_id = :student_id");
$stmt->execute(['student_id' => $student['id']]);
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Gestion des formulaires
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_student'])) {
        // Mettre à jour les informations de l'étudiant
        $first_name = $_POST['first_name'];
        $last_name = $_POST['last_name'];
        $phone = $_POST['phone'];
        $email = $_POST['email'];

        $stmt = $conn->prepare("UPDATE students SET first_name = :first_name, last_name = :last_name, phone = :phone, email = :email WHERE id = :id");
        $stmt->execute(['first_name' => $first_name, 'last_name' => $last_name, 'phone' => $phone, 'email' => $email, 'id' => $student['id']]);
    } elseif (isset($_POST['update_student_credentials'])) {
        // Update Student Username and Password
        $username = $_POST['username'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
        $stmt = $conn->prepare("UPDATE users SET username = :username, password = :password WHERE id = :user_id");
        $stmt->execute(['username' => $username, 'password' => $password, 'user_id' => $student_id]);
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord Étudiant</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container student-dashboard">
        <h1>Tableau de bord Étudiant</h1>

        <!-- Afficher les informations de l'étudiant -->
        <h2>Mes Informations</h2>
        <form method="POST" action="">
            <label for="first_name">Prénom :</label>
            <input type="text" name="first_name" id="first_name" value="<?= $student['first_name'] ?>" required><br><br>
            <label for="last_name">Nom :</label>
            <input type="text" name="last_name" id="last_name" value="<?= $student['last_name'] ?>" required><br><br>
            <label for="phone">Téléphone :</label>
            <input type="text" name="phone" id="phone" value="<?= $student['phone'] ?>"><br><br>
            <label for="email">Email :</label>
            <input type="email" name="email" id="email" value="<?= $student['email'] ?>"><br><br>
            <button type="submit" name="update_student">Mettre à jour</button>
        </form>

        <!-- Modifier le nom d'utilisateur et le mot de passe -->
        <form method="POST" action="">
            <input type="text" name="username" placeholder="Nouveau nom d'utilisateur" required>
            <input type="password" name="password" placeholder="Nouveau mot de passe" required>
            <button type="submit" name="update_student_credentials">Mettre à jour les identifiants</button>
        </form>

        <!-- Display the student's notes -->
        <h2>Mes Notes</h2>
        <table border="1">
            <tr>
                <th>Matière</th>
                <th>Note</th>
            </tr>
            <?php foreach ($subjects as $subject): ?>
                <tr>
                    <td><?= $subject['name'] ?></td>
                    <td><?= $subject['note'] ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</body>
</html>