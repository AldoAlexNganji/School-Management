<?php
session_start();

// Vérification du rôle de l'utilisateur
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
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

// Récupérer les informations de l'enseignant connecté
$teacher_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM teachers WHERE user_id = :user_id");
$stmt->execute(['user_id' => $teacher_id]);
$teacher = $stmt->fetch(PDO::FETCH_ASSOC);

// Récupérer les matières enseignées par l'enseignant
$stmt = $conn->prepare("SELECT s.id, s.name FROM subjects s JOIN teacher_subjects ts ON s.id = ts.subject_id WHERE ts.teacher_id = :teacher_id");
$stmt->execute(['teacher_id' => $teacher['id']]);
$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les étudiants qui suivent les cours de l'enseignant
$stmt = $conn->prepare("SELECT st.id, st.first_name, st.last_name, ss.subject_id, ss.note 
                        FROM students st 
                        JOIN student_subjects ss ON st.id = ss.student_id 
                        JOIN teacher_subjects ts ON ss.subject_id = ts.subject_id 
                        WHERE ts.teacher_id = :teacher_id");
$stmt->execute(['teacher_id' => $teacher['id']]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);
// Gestion des formulaires
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_teacher'])) {
        // Mettre à jour les informations de l'enseignant
        $first_name = $_POST['first_name'];
        $last_name = $_POST['last_name'];
        $phone = $_POST['phone'];
        $email = $_POST['email'];

        $stmt = $conn->prepare("UPDATE teachers SET first_name = :first_name, last_name = :last_name, phone = :phone, email = :email WHERE id = :id");
        $stmt->execute(['first_name' => $first_name, 'last_name' => $last_name, 'phone' => $phone, 'email' => $email, 'id' => $teacher['id']]);
    } elseif (isset($_POST['update_student_note'])) {
        // Update Student Note
        $student_id = $_POST['student_id'];
        $subject_id = $_POST['subject_id'];
        $note = $_POST['note'];

        try {
            // Start a transaction to ensure atomicity
            $conn->beginTransaction();

            // Step 1: Update the student's note
            $stmt = $conn->prepare("UPDATE student_subjects SET note = :note WHERE student_id = :student_id AND subject_id = :subject_id");
            $stmt->execute(['note' => $note, 'student_id' => $student_id, 'subject_id' => $subject_id]);

            // Commit the transaction
            $conn->commit();

            // Success message
            $success = "La note de l'étudiant a été mise à jour avec succès.";
        } catch (Exception $e) {
            // Rollback the transaction in case of an error
            $conn->rollBack();
            $error = "Erreur lors de la mise à jour de la note: " . $e->getMessage();
        }
    } elseif (isset($_POST['update_teacher_credentials'])) {
        // Update Teacher Username and Password
        $username = $_POST['username'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
        $stmt = $conn->prepare("UPDATE users SET username = :username, password = :password WHERE id = :user_id");
        $stmt->execute(['username' => $username, 'password' => $password, 'user_id' => $teacher_id]);
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord Enseignant</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container teacher-dashboard">
        <h1>Tableau de bord Enseignant</h1>

        <!-- Afficher les informations de l'enseignant -->
        <h3>Mes Informations</h3>
        <form method="POST" action="">
            <label for="first_name">Prénom :</label>
            <input type="text" name="first_name" id="first_name" value="<?= $teacher['first_name'] ?>" required><br><br>
            <label for="last_name">Nom :</label>
            <input type="text" name="last_name" id="last_name" value="<?= $teacher['last_name'] ?>" required><br><br>
            <label for="phone">Téléphone :</label>
            <input type="text" name="phone" id="phone" value="<?= $teacher['phone'] ?>"><br><br>
            <label for="email">Email :</label>
            <input type="email" name="email" id="email" value="<?= $teacher['email'] ?>"><br><br>
            <button type="submit" name="update_teacher">Mettre à jour</button>
        </form>

        <!-- Modifier le nom d'utilisateur et le mot de passe -->
        <form method="POST" action="">
            <input type="text" name="username" placeholder="Nouveau nom d'utilisateur" required>
            <input type="password" name="password" placeholder="Nouveau mot de passe" required>
            <button type="submit" name="update_teacher_credentials">Mettre à jour les identifiants</button>
        </form>

        <!-- Afficher les matières enseignées -->
        <h3>Matières Enseignées</h3>
        <ul>
            <?php foreach ($subjects as $subject): ?>
                <li><?= $subject['name'] ?></li>
            <?php endforeach; ?>
        </ul>

        <!-- Afficher les étudiants qui suivent les cours -->
        <h1>Étudiants</h1>
        <table border="1">
            <tr>
                <th>Prénom</th>
                <th>Nom</th>
                <th>Matière</th>
                <th>Note</th>
                <th>Action</th>
            </tr>
            <?php foreach ($students as $student): ?>
                <tr>
                    <td><?= $student['first_name'] ?></td>
                    <td><?= $student['last_name'] ?></td>
                    <td>
                        <?php
                        $stmt = $conn->prepare("SELECT name FROM subjects WHERE id = :subject_id");
                        $stmt->execute(['subject_id' => $student['subject_id']]);
                        $subject_name = $stmt->fetchColumn();
                        echo $subject_name;
                        ?>
                    </td>
                    <td>
                        <form method="POST" action="">
                            <input type="hidden" name="student_id" value="<?= $student['id'] ?>">
                            <input type="hidden" name="subject_id" value="<?= $student['subject_id'] ?>">
                            <input type="number" name="note" value="<?= $student['note'] ?>" required>
                            <button type="submit" name="update_student_note">Mettre à jour</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</body>
</html>