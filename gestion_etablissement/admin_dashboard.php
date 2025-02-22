<?php
session_start();

// Vérification du rôle de l'utilisateur
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
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

// Récupérer tous les enseignants et étudiants
$teachers = $conn->query("SELECT * FROM teachers")->fetchAll(PDO::FETCH_ASSOC);
$students = $conn->query("SELECT * FROM students")->fetchAll(PDO::FETCH_ASSOC);
$subjects = $conn->query("SELECT * FROM subjects")->fetchAll(PDO::FETCH_ASSOC);

// Récupérer la moyenne des notes par matière
$stmt = $conn->prepare("SELECT s.id, s.name, AVG(ss.note) AS average_note 
                        FROM subjects s 
                        LEFT JOIN student_subjects ss ON s.id = ss.subject_id 
                        GROUP BY s.id");
$stmt->execute();
$subject_averages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les étudiants et leurs notes pour une matière spécifique
if (isset($_GET['subject_id'])) {
    $subject_id = $_GET['subject_id'];
    $stmt = $conn->prepare("SELECT st.id, st.first_name, st.last_name, ss.note 
                            FROM students st 
                            JOIN student_subjects ss ON st.id = ss.student_id 
                            WHERE ss.subject_id = :subject_id");
    $stmt->execute(['subject_id' => $subject_id]);
    $students_in_subject = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Gestion des formulaires
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['create_subject'])) {
        // Créer une matière
        $name = $_POST['subject_name'];
        $max_note = $_POST['max_note'];
        $stmt = $conn->prepare("INSERT INTO subjects (name, max_note) VALUES (:name, :max_note)");
        $stmt->execute(['name' => $name, 'max_note' => $max_note]);
    } elseif (isset($_POST['create_teacher'])) {
        // Créer un enseignant
        $username = $_POST['username']; // Nouveau champ pour le nom d'utilisateur
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Hacher le mot de passe
        $first_name = $_POST['first_name'];
        $last_name = $_POST['last_name'];
        $phone = $_POST['phone'];
        $email = $_POST['email'];
        $date_hired = $_POST['date_hired'];
        $subject_id = $_POST['subject_id'];

        // Insérer dans la table `users` (créer un utilisateur)
        $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (:username, :password, 'teacher')");
        $stmt->execute(['username' => $username, 'password' => $password]);
        $user_id = $conn->lastInsertId(); // Récupérer l'ID de l'utilisateur créé

        // Insérer dans la table `teachers` (créer un enseignant)
        $stmt = $conn->prepare("INSERT INTO teachers (user_id, first_name, last_name, phone, email, date_hired) VALUES (:user_id, :first_name, :last_name, :phone, :email, :date_hired)");
        $stmt->execute(['user_id' => $user_id, 'first_name' => $first_name, 'last_name' => $last_name, 'phone' => $phone, 'email' => $email, 'date_hired' => $date_hired]);

        // Récupérer l'ID de l'enseignant créé
        $teacher_id = $conn->lastInsertId();

        // Lier l'enseignant à la matière
        $stmt = $conn->prepare("INSERT INTO teacher_subjects (teacher_id, subject_id) VALUES (:teacher_id, :subject_id)");
        $stmt->execute(['teacher_id' => $teacher_id, 'subject_id' => $subject_id]);
    } elseif (isset($_POST['create_student'])) {
        // Créer un étudiant
        $username = $_POST['username']; // Nouveau champ pour le nom d'utilisateur
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // Hacher le mot de passe
        $first_name = $_POST['first_name'];
        $last_name = $_POST['last_name'];
        $phone = $_POST['phone'];
        $email = $_POST['email'];
        $date_saved = $_POST['date_saved'];
        $subject_id = $_POST['subject_id'];

        // Insérer dans la table `users` (créer un utilisateur)
        $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (:username, :password, 'student')");
        $stmt->execute(['username' => $username, 'password' => $password]);
        $user_id = $conn->lastInsertId(); // Récupérer l'ID de l'utilisateur créé

        // Insérer dans la table `students` (créer un étudiant)
        $stmt = $conn->prepare("INSERT INTO students (user_id, first_name, last_name, phone, email, date_saved) VALUES (:user_id, :first_name, :last_name, :phone, :email, :date_saved)");
        $stmt->execute(['user_id' => $user_id, 'first_name' => $first_name, 'last_name' => $last_name, 'phone' => $phone, 'email' => $email, 'date_saved' => $date_saved]);

        // Récupérer l'ID de l'étudiant créé
        $student_id = $conn->lastInsertId();

        // Lier l'étudiant à la matière
        $stmt = $conn->prepare("INSERT INTO student_subjects (student_id, subject_id) VALUES (:student_id, :subject_id)");
        $stmt->execute(['student_id' => $student_id, 'subject_id' => $subject_id]);
    } elseif (isset($_POST['update_teacher'])) {
        // Update Teacher Information
        $teacher_id = $_POST['teacher_id'];
        $first_name = $_POST['first_name'];
        $last_name = $_POST['last_name'];
        $phone = $_POST['phone'];
        $email = $_POST['email'];
        $date_hired = $_POST['date_hired'];

        try {
            // Start a transaction to ensure atomicity
            $conn->beginTransaction();

            // Step 1: Update the teacher's information
            $stmt = $conn->prepare("UPDATE teachers SET first_name = :first_name, last_name = :last_name, phone = :phone, email = :email, date_hired = :date_hired WHERE id = :teacher_id");
            $stmt->execute(['first_name' => $first_name, 'last_name' => $last_name, 'phone' => $phone, 'email' => $email, 'date_hired' => $date_hired, 'teacher_id' => $teacher_id]);

            // Commit the transaction
            $conn->commit();

            // Success message
            $success = "Les informations de l'enseignant ont été mises à jour avec succès.";
        } catch (Exception $e) {
            // Rollback the transaction in case of an error
            $conn->rollBack();
            $error = "Erreur lors de la mise à jour des informations: " . $e->getMessage();
        }
    } elseif (isset($_POST['update_teacher_credentials'])) {
        // Update Teacher Credentials (username and password)
        $teacher_id = $_POST['teacher_id'];
        $username = $_POST['username'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

        try {
            // Start a transaction to ensure atomicity
            $conn->beginTransaction();

            // Step 1: Get the user_id of the teacher
            $stmt = $conn->prepare("SELECT user_id FROM teachers WHERE id = :teacher_id");
            $stmt->execute(['teacher_id' => $teacher_id]);
            $user_id = $stmt->fetchColumn();

            if ($user_id) {
                // Step 2: Update the username and password in the `users` table
                $stmt = $conn->prepare("UPDATE users SET username = :username, password = :password WHERE id = :user_id");
                $stmt->execute(['username' => $username, 'password' => $password, 'user_id' => $user_id]);
            }

            // Commit the transaction
            $conn->commit();

            // Success message
            $success = "Les identifiants de l'enseignant ont été mis à jour avec succès.";
        } catch (Exception $e) {
            // Rollback the transaction in case of an error
            $conn->rollBack();
            $error = "Erreur lors de la mise à jour des identifiants: " . $e->getMessage();
        }
    } elseif (isset($_POST['delete_teacher'])) {
        // Delete Teacher
        $teacher_id = $_POST['teacher_id'];

        try {
            // Start a transaction to ensure atomicity
            $conn->beginTransaction();

            // Step 1: Get the user_id of the teacher
            $stmt = $conn->prepare("SELECT user_id FROM teachers WHERE id = :teacher_id");
            $stmt->execute(['teacher_id' => $teacher_id]);
            $user_id = $stmt->fetchColumn();

            if ($user_id) {
                // Step 2: Delete associated records from the teacher_subjects table
                $stmt = $conn->prepare("DELETE FROM teacher_subjects WHERE teacher_id = :teacher_id");
                $stmt->execute(['teacher_id' => $teacher_id]);

                // Step 3: Delete the teacher record
                $stmt = $conn->prepare("DELETE FROM teachers WHERE id = :teacher_id");
                $stmt->execute(['teacher_id' => $teacher_id]);

                // Step 4: Delete the user record
                $stmt = $conn->prepare("DELETE FROM users WHERE id = :user_id");
                $stmt->execute(['user_id' => $user_id]);
            }

            // Commit the transaction
            $conn->commit();

            // Success message
            $success = "L'enseignant a été supprimé avec succès.";
        } catch (Exception $e) {
            // Rollback the transaction in case of an error
            $conn->rollBack();
            $error = "Erreur lors de la suppression de l'enseignant: " . $e->getMessage();
        }
    } elseif (isset($_POST['delete_student'])) {
        // Delete Student
        $student_id = $_POST['student_id'];

        try {
            // Start a transaction to ensure atomicity
            $conn->beginTransaction();

            // Step 1: Get the user_id of the student
            $stmt = $conn->prepare("SELECT user_id FROM students WHERE id = :student_id");
            $stmt->execute(['student_id' => $student_id]);
            $user_id = $stmt->fetchColumn();

            if ($user_id) {
                // Step 2: Delete associated records from the student_subjects table
                $stmt = $conn->prepare("DELETE FROM student_subjects WHERE student_id = :student_id");
                $stmt->execute(['student_id' => $student_id]);

                // Step 3: Delete the student record
                $stmt = $conn->prepare("DELETE FROM students WHERE id = :student_id");
                $stmt->execute(['student_id' => $student_id]);

                // Step 4: Delete the user record
                $stmt = $conn->prepare("DELETE FROM users WHERE id = :user_id");
                $stmt->execute(['user_id' => $user_id]);
            }

            // Commit the transaction
            $conn->commit();
        } catch (PDOException $e) {
            // Rollback the transaction in case of an error
            $conn->rollBack();
            die("Error deleting student: " . $e->getMessage());
        }
    } elseif (isset($_POST['update_student_credentials'])) {
        // Update Student Username and Password
        $student_id = $_POST['student_id'];
        $username = $_POST['username'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

        try {
            // Start a transaction to ensure atomicity
            $conn->beginTransaction();

            // Step 1: Get the user_id of the student
            $stmt = $conn->prepare("SELECT user_id FROM students WHERE id = :student_id");
            $stmt->execute(['student_id' => $student_id]);
            $user_id = $stmt->fetchColumn();

            if ($user_id) {
                // Step 2: Update the username and password in the `users` table
                $stmt = $conn->prepare("UPDATE users SET username = :username, password = :password WHERE id = :user_id");
                $stmt->execute(['username' => $username, 'password' => $password, 'user_id' => $user_id]);
            }

            // Commit the transaction
            $conn->commit();

            // Success message
            $success = "Les identifiants de l'étudiant ont été mis à jour avec succès.";
        } catch (Exception $e) {
            // Rollback the transaction in case of an error
            $conn->rollBack();
            $error = "Erreur lors de la mise à jour des identifiants: " . $e->getMessage();
        }
    } elseif (isset($_POST['update_student'])) {
        // Update Student Information
        $student_id = $_POST['student_id'];
        $first_name = $_POST['first_name'];
        $last_name = $_POST['last_name'];
        $phone = $_POST['phone'];
        $email = $_POST['email'];
        $date_saved = $_POST['date_saved'];

        try {
            // Start a transaction to ensure atomicity
            $conn->beginTransaction();

            // Step 1: Update the student's information
            $stmt = $conn->prepare("UPDATE students SET first_name = :first_name, last_name = :last_name, phone = :phone, email = :email, date_saved = :date_saved WHERE id = :student_id");
            $stmt->execute(['first_name' => $first_name, 'last_name' => $last_name, 'phone' => $phone, 'email' => $email, 'date_saved' => $date_saved, 'student_id' => $student_id]);

            // Step 2: Update the student's notes (if provided)
            if (isset($_POST['notes'])) {
                foreach ($_POST['notes'] as $subject_id => $note) {
                    $stmt = $conn->prepare("UPDATE student_subjects SET note = :note WHERE student_id = :student_id AND subject_id = :subject_id");
                    $stmt->execute(['note' => $note, 'student_id' => $student_id, 'subject_id' => $subject_id]);
                }
            }

            // Commit the transaction
            $conn->commit();

            // Success message
            $success = "Les informations de l'étudiant ont été mises à jour avec succès.";
        } catch (Exception $e) {
            // Rollback the transaction in case of an error
            $conn->rollBack();
            $error = "Erreur lors de la mise à jour des informations: " . $e->getMessage();
        }
    } elseif (isset($_POST['update_subject'])) {
        // Update Subject
        $subject_id = $_POST['subject_id'];
        $name = $_POST['name'];
        $max_note = $_POST['max_note'];

        try {
            // Start a transaction to ensure atomicity
            $conn->beginTransaction();

            // Step 1: Update the subject's information
            $stmt = $conn->prepare("UPDATE subjects SET name = :name, max_note = :max_note WHERE id = :subject_id");
            $stmt->execute(['name' => $name, 'max_note' => $max_note, 'subject_id' => $subject_id]);

            // Commit the transaction
            $conn->commit();

            // Success message
            $success = "La matière a été mise à jour avec succès.";
        } catch (Exception $e) {
            // Rollback the transaction in case of an error
            $conn->rollBack();
            $error = "Erreur lors de la mise à jour de la matière: " . $e->getMessage();
        }
    } elseif (isset($_POST['delete_subject'])) {
        // Delete Subject
        $subject_id = $_POST['subject_id'];

        try {
            // Start a transaction to ensure atomicity
            $conn->beginTransaction();

            // Step 1: Delete associated records from the teacher_subjects table
            $stmt = $conn->prepare("DELETE FROM teacher_subjects WHERE subject_id = :subject_id");
            $stmt->execute(['subject_id' => $subject_id]);

            // Step 2: Delete associated records from the student_subjects table
            $stmt = $conn->prepare("DELETE FROM student_subjects WHERE subject_id = :subject_id");
            $stmt->execute(['subject_id' => $subject_id]);

            // Step 3: Delete the subject
            $stmt = $conn->prepare("DELETE FROM subjects WHERE id = :subject_id");
            $stmt->execute(['subject_id' => $subject_id]);

            // Commit the transaction
            $conn->commit();

            // Success message
            $success = "La matière a été supprimée avec succès.";
        } catch (Exception $e) {
            // Rollback the transaction in case of an error
            $conn->rollBack();
            $error = "Erreur lors de la suppression de la matière: " . $e->getMessage();
        }
    } elseif (isset($_POST['add_student_subject'])) {
        // Add or Update Student Subject and Note
        $student_id = $_POST['student_id'];
        $subject_id = $_POST['subject_id'];
        $note = $_POST['note'];

        try {
            // Start a transaction to ensure atomicity
            $conn->beginTransaction();

            // Step 1: Check if the student is already enrolled in the subject
            $stmt = $conn->prepare("SELECT * FROM student_subjects WHERE student_id = :student_id AND subject_id = :subject_id");
            $stmt->execute(['student_id' => $student_id, 'subject_id' => $subject_id]);
            $existing_record = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing_record) {
                // Step 2: Update the note if the student is already enrolled
                $stmt = $conn->prepare("UPDATE student_subjects SET note = :note WHERE student_id = :student_id AND subject_id = :subject_id");
                $stmt->execute(['note' => $note, 'student_id' => $student_id, 'subject_id' => $subject_id]);
            } else {
                // Step 3: Add the student to the subject if not already enrolled
                $stmt = $conn->prepare("INSERT INTO student_subjects (student_id, subject_id, note) VALUES (:student_id, :subject_id, :note)");
                $stmt->execute(['student_id' => $student_id, 'subject_id' => $subject_id, 'note' => $note]);
            }

            // Commit the transaction
            $conn->commit();

            // Success message
            $success = "La matière a été ajoutée/mise à jour avec succès.";
        } catch (Exception $e) {
            // Rollback the transaction in case of an error
            $conn->rollBack();
            $error = "Erreur lors de l'ajout/mise à jour de la matière: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de bord Admin</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container admin-dashboard">
        <h1>Tableau de bord Admin</h1>

        <!-- Afficher les messages d'erreur ou de succès -->
        <?php if (isset($error)) echo "<p style='color:red;'>$error</p>"; ?>
        <?php if (isset($success)) echo "<p style='color:green;'>$success</p>"; ?>

        <!-- Afficher la moyenne des notes par matière -->
        <h1>Moyenne des notes par matière</h1>
        <table border="1">
            <tr>
                <th>Matière</th>
                <th>Moyenne</th>
            </tr>
            <?php foreach ($subject_averages as $subject): ?>
                <tr>
                    <td><?= $subject['name'] ?></td>
                    <td><?= $subject['average_note'] ? round($subject['average_note'], 2) : 'N/A' ?></td>
                </tr>
            <?php endforeach; ?>
        </table>

        <!-- Afficher les étudiants et leurs notes pour une matière spécifique -->
        <h2>Étudiants par matière</h2>
        <form method="GET" action="">
            <label for="subject_id">Sélectionnez une matière :</label>
            <select name="subject_id" id="subject_id" required>
                <?php foreach ($subjects as $subject): ?>
                    <option value="<?= $subject['id'] ?>"><?= $subject['name'] ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Voir les étudiants</button>
        </form>

        <?php if (isset($students_in_subject)): ?>
            <h3>Étudiants et leurs notes</h3>
            <table border="1">
                <tr>
                    <th>Prénom</th>
                    <th>Nom</th>
                    <th>Note</th>
                </tr>
                <?php foreach ($students_in_subject as $student): ?>
                    <tr>
                        <td><?= $student['first_name'] ?></td>
                        <td><?= $student['last_name'] ?></td>
                        <td><?= $student['note'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    
        <!-- Afficher toutes les matières -->
        <h1>Matières</h1>
        <table border="1">
            <tr>
                <th>Nom</th>
                <th>Note maximale</th>
                <th>Actions</th>
            </tr>
            <?php foreach ($subjects as $subject): ?>
                <tr>
                    <td><?= $subject['name'] ?></td>
                    <td><?= $subject['max_note'] ?></td>
                    <td>
                        <!-- Formulaire pour mettre à jour une matière -->
                        <form method="POST" action="">
                            <input type="hidden" name="subject_id" value="<?= $subject['id'] ?>">
                            <input type="text" name="name" value="<?= $subject['name'] ?>" required><br>
                            <input type="number" name="max_note" value="<?= $subject['max_note'] ?>" required><br>
                            <button type="submit" name="update_subject">Mettre à jour</button>
                        </form>

                        <!-- Formulaire pour supprimer une matière -->
                        <form method="POST" action="">
                            <input type="hidden" name="subject_id" value="<?= $subject['id'] ?>">
                            <button type="submit" name="delete_subject">Supprimer</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table> 
        <!-- Formulaire pour créer une matière --> 
        <h3>Créer une matière</h3>
        <form method="POST" action="">
            <label for="subject_name">Nom de la matière :</label>
            <input type="text" name="subject_name" id="subject_name" required><br><br>
            <label for="max_note">Note maximale :</label>
            <input type="number" name="max_note" id="max_note" required><br><br>
            <button type="submit" name="create_subject">Créer</button>
        </form>

        <!-- Afficher tous les enseignants -->
        <h1>Enseignants</h1>
        <table border="1">
            <tr>
                <th>Prénom</th>
                <th>Nom</th>
                <th>Téléphone</th>
                <th>Email</th>
                <th>Date d'embauche</th>
                <th>Actions</th>
            </tr>
            <?php foreach ($teachers as $teacher): ?>
                <tr>
                    <td><?= $teacher['first_name'] ?></td>
                    <td><?= $teacher['last_name'] ?></td>
                    <td><?= $teacher['phone'] ?></td>
                    <td><?= $teacher['email'] ?></td>
                    <td><?= $teacher['date_hired'] ?></td>
                    <td>
                        <!-- Formulaire pour mettre à jour les informations de l'enseignant -->
                        <form method="POST" action="">
                            <input type="hidden" name="teacher_id" value="<?= $teacher['id'] ?>">
                            <label for="first_name">Prénom :</label>
                            <input type="text" name="first_name" value="<?= $teacher['first_name'] ?>" required><br>
                            <label for="last_name">Nom :</label>
                            <input type="text" name="last_name" value="<?= $teacher['last_name'] ?>" required><br>
                            <label for="phone">Téléphone :</label>
                            <input type="text" name="phone" value="<?= $teacher['phone'] ?>"><br>
                            <label for="email">Email :</label>
                            <input type="email" name="email" value="<?= $teacher['email'] ?>"><br>
                            <label for="date_hired">Date d'embauche :</label>
                            <input type="date" name="date_hired" value="<?= $teacher['date_hired'] ?>" required><br>
                            <button type="submit" name="update_teacher">Mettre à jour</button>
                        </form>

                        <!-- Formulaire pour mettre à jour les identifiants de l'enseignant -->
                        <form method="POST" action="">
                            <input type="hidden" name="teacher_id" value="<?= $teacher['id'] ?>">
                            <label for="username">Nom d'utilisateur :</label>
                            <input type="text" name="username" placeholder="Nouveau nom d'utilisateur" required><br>
                            <label for="password">Mot de passe :</label>
                            <input type="password" name="password" placeholder="Nouveau mot de passe" required><br>
                            <button type="submit" name="update_teacher_credentials">Mettre à jour les identifiants</button>
                        </form>

                        <!-- Formulaire pour supprimer un enseignant -->
                        <form method="POST" action="">
                            <input type="hidden" name="teacher_id" value="<?= $teacher['id'] ?>">
                            <button type="submit" name="delete_teacher">Supprimer</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
        <!-- Formulaire pour créer un enseignant -->
        <h3>Créer un enseignant</h3>
        <form method="POST" action="">
            <label for="username">Nom d'utilisateur :</label>
            <input type="text" name="username" id="username" required><br><br>
            <label for="password">Mot de passe :</label>
            <input type="password" name="password" id="password" required><br><br>
            <label for="first_name">Prénom :</label>
            <input type="text" name="first_name" id="first_name" required><br><br>
            <label for="last_name">Nom :</label>
            <input type="text" name="last_name" id="last_name" required><br><br>
            <label for="phone">Téléphone :</label>
            <input type="text" name="phone" id="phone"><br><br>
            <label for="email">Email :</label>
            <input type="email" name="email" id="email"><br><br>
            <label for="date_hired">Date d'embauche :</label>
            <input type="date" name="date_hired" id="date_hired" required><br><br>
            <label for="subject_id">Matière enseignée :</label>
            <select name="subject_id" id="subject_id" required>
                <?php foreach ($subjects as $subject): ?>
                    <option value="<?= $subject['id'] ?>"><?= $subject['name'] ?></option>
                <?php endforeach; ?>
            </select><br><br>
            <button type="submit" name="create_teacher">Créer</button>
        </form>

        <!-- Afficher tous les étudiants -->
        <h1>Étudiants</h1>
        <table border="1">
            <tr>
                <th>Prénom</th>
                <th>Nom</th>
                <th>Téléphone</th>
                <th>Email</th>
                <th>Date d'inscription</th>
                <th>Actions</th>
            </tr>
            <?php foreach ($students as $student): ?>
                <tr>
                    <td><?= $student['first_name'] ?></td>
                    <td><?= $student['last_name'] ?></td>
                    <td><?= $student['phone'] ?></td>
                    <td><?= $student['email'] ?></td>
                    <td><?= $student['date_saved'] ?></td>
                    <td>
                        <!-- Formulaire pour mettre à jour les informations de l'étudiant -->
                        <form method="POST" action="">
                            <input type="hidden" name="student_id" value="<?= $student['id'] ?>">
                            <label for="first_name">Prénom :</label>
                            <input type="text" name="first_name" value="<?= $student['first_name'] ?>" required><br>
                            <label for="last_name">Nom :</label>
                            <input type="text" name="last_name" value="<?= $student['last_name'] ?>" required><br>
                            <label for="phone">Téléphone :</label>
                            <input type="text" name="phone" value="<?= $student['phone'] ?>"><br>
                            <label for="email">Email :</label>
                            <input type="email" name="email" value="<?= $student['email'] ?>"><br>
                            <label for="date_saved">Date d'inscription :</label>
                            <input type="date" name="date_saved" value="<?= $student['date_saved'] ?>" required><br>

                            <!-- Afficher les notes de l'étudiant -->
                            <h4>Notes</h4>
                            <?php
                            $stmt = $conn->prepare("SELECT s.id, s.name, ss.note FROM subjects s JOIN student_subjects ss ON s.id = ss.subject_id WHERE ss.student_id = :student_id");
                            $stmt->execute(['student_id' => $student['id']]);
                            $student_notes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            ?>
                            <?php foreach ($student_notes as $note): ?>
                                <label for="note_<?= $note['id'] ?>"><?= $note['name'] ?> :</label>
                                <input type="number" name="notes[<?= $note['id'] ?>]" value="<?= $note['note'] ?>"><br>
                            <?php endforeach; ?>

                            <button type="submit" name="update_student">Mettre à jour</button>
                        </form>

                        <!-- Formulaire pour ajouter une matière et une note -->
                        <form method="POST" action="">
                            <input type="hidden" name="student_id" value="<?= $student['id'] ?>">
                            <label for="subject_id">Matière :</label>
                            <select name="subject_id" id="subject_id" required>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?= $subject['id'] ?>"><?= $subject['name'] ?></option>
                                <?php endforeach; ?>
                            </select><br>
                            <label for="note">Note :</label>
                            <input type="number" name="note" id="note" required><br>
                            <button type="submit" name="add_student_subject">Ajouter/Mettre à jour la matière</button>
                        </form>

                        <!-- Formulaire pour mettre à jour les identifiants de l'étudiant -->
                        <form method="POST" action="">
                            <input type="hidden" name="student_id" value="<?= $student['id'] ?>">
                            <label for="username">Nom d'utilisateur :</label>
                            <input type="text" name="username" placeholder="Nouveau nom d'utilisateur" required><br>
                            <label for="password">Mot de passe :</label>
                            <input type="password" name="password" placeholder="Nouveau mot de passe" required><br>
                            <button type="submit" name="update_student_credentials">Mettre à jour les identifiants</button>
                        </form>

                        <!-- Formulaire pour supprimer un étudiant -->
                        <form method="POST" action="">
                            <input type="hidden" name="student_id" value="<?= $student['id'] ?>">
                            <button type="submit" name="delete_student">Supprimer</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
        <!-- Formulaire pour créer un étudiant -->
        <h3>Créer un étudiant</h3>
        <form method="POST" action="">
            <label for="username">Nom d'utilisateur :</label>
            <input type="text" name="username" id="username" required><br><br>
            <label for="password">Mot de passe :</label>
            <input type="password" name="password" id="password" required><br><br>
            <label for="first_name">Prénom :</label>
            <input type="text" name="first_name" id="first_name" required><br><br>
            <label for="last_name">Nom :</label>
            <input type="text" name="last_name" id="last_name" required><br><br>
            <label for="phone">Téléphone :</label>
            <input type="text" name="phone" id="phone"><br><br>
            <label for="email">Email :</label>
            <input type="email" name="email" id="email"><br><br>
            <label for="date_saved">Date d'inscription :</label>
            <input type="date" name="date_saved" id="date_saved" required><br><br>
            <label for="subject_id">Matière suivie :</label>
            <select name="subject_id" id="subject_id" required>
                <?php foreach ($subjects as $subject): ?>
                    <option value="<?= $subject['id'] ?>"><?= $subject['name'] ?></option>
                <?php endforeach; ?>
            </select><br><br>
            <button type="submit" name="create_student">Créer</button>
        </form>
    </div>
</body>
</html>