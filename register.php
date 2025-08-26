<?php
session_start();

$error_message = '';
$success_message = '';

if(isset($_POST['ajouter'])) {
    require_once 'include/database.php';
    require_once 'resetfunc.php';
    // Nettoyage des entrées
    $username = htmlspecialchars(trim($_POST['username']));
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $password = trim($_POST['password']);
    $full_name = htmlspecialchars(trim($_POST['full_name']));
    $role = htmlspecialchars(trim($_POST['role']));
    $fonction = htmlspecialchars(trim($_POST['fonction'] ?? ''));
    $created_at = date('Y-m-d H:i:s');
    $target_file = null;

    // Validation
    if(empty($username) || empty($email) || empty($password) || empty($full_name) || empty($role)) {
        $error_message = "Veuillez remplir tous les champs obligatoires";
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Format d'email invalide";
    } elseif(strlen($password) < 8) {
        $error_message = "Le mot de passe doit contenir au moins 8 caractères";
    } else {
        // Gestion de l'upload
        if(isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 2 * 1024 * 1024; // 2MB
            if(in_array($_FILES['profile_picture']['type'], $allowed_types) && 
               $_FILES['profile_picture']['size'] <= $max_size) {
                $upload_dir = "uploads/";
                if(!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $file_ext = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
                $file_name = uniqid('user_') . '.' . $file_ext;
                $target_file = $upload_dir . $file_name;
                if(!move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
                    $target_file = null;
                }
            }
        }
        // Utilisation de la fonction externe
        $result = reset_or_create_user_password($pdo, $username, $email, $password, $full_name, $role, $target_file, $created_at , $fonction);
        if($result['success']) {
            $_SESSION['success_message'] = "Votre compte a été créé. Vous pouvez maintenant vous connecter.";
            header("Location: index.php");
            exit();
        } else {
            $error_message = $result['message'];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - Gestion Flotte</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4f46e5;
            --primary-light: #6366f1;
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --light-bg: #f8fafc;
            --border-color: #e2e8f0;
        }
        
        body {
           font-family: 'Inter', sans-serif;
            min-height: 100vh;
            background: linear-gradient(rgba(255,255,255,0.80), rgba(255,255,255,0.85)), 
                        url('gf.webp') center/cover no-repeat fixed;
            color: var(--text-dark);
            display: flex;
            align-items: center;
        }
        .register-container {
            max-width: 520px;
            margin: 0 auto;
            width: 100%;
        }
        
        .auth-card {
            border: none;
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(79, 70, 229, 0.08);
            overflow: hidden;
            background: white;
            transition: transform 0.3s ease;
        }
        
        .auth-card:hover {
            transform: translateY(-3px);
        }
        
        .card-body {
            padding: 2.75rem;
        }
        
        .auth-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .auth-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            letter-spacing: -0.5px;
        }
        
        .auth-subtitle {
            color: var(--text-muted);
            font-size: 0.95rem;
            font-weight: 400;
        }
        
        .form-label {
            font-weight: 500;
            font-size: 0.9rem;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
            display: block;
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            padding: 0.875rem 1.25rem;
            border: 1px solid var(--border-color);
            font-size: 0.95rem;
            transition: all 0.25s ease;
            margin-bottom: 1rem;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
        }
        
        .form-control::placeholder {
            color: #94a3b8;
            font-weight: 400;
        }
        
        .btn-auth {
            background-color: var(--primary-color);
            border: none;
            border-radius: 10px;
            padding: 0.875rem;
            font-weight: 600;
            font-size: 1rem;
            letter-spacing: 0.25px;
            transition: all 0.25s ease;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
        }
        
        .btn-auth:hover {
            background-color: #4338ca;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(79, 70, 229, 0.25);
        }
        
        .btn-auth:active {
            transform: translateY(0);
        }
        
        .auth-footer {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.9rem;
            color: var(--text-muted);
        }
        
        .auth-link {
            color: var(--primary-color);
            font-weight: 500;
            text-decoration: none;
            transition: color 0.2s ease;
        }
        
        .auth-link:hover {
            color: #4338ca;
            text-decoration: underline;
        }
        
        .divider {
            display: flex;
            align-items: center;
            margin: 1.75rem 0;
            color: var(--text-muted);
            font-size: 0.85rem;
        }
        
        .divider::before, .divider::after {
            content: "";
            flex: 1;
            border-bottom: 1px solid var(--border-color);
        }
        
        .alert-danger {
            border-radius: 10px;
            padding: 0.875rem 1.25rem;
            background-color: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #b91c1c;
            font-size: 0.9rem;
        }
        
        .required-field::after {
            content: " *";
            color: #dc3545;
        }
        
        .text-muted {
            font-size: 0.85rem;
            display: block;
            margin-top: -0.5rem;
            margin-bottom: 1rem;
            color: var(--text-muted);
        }
        
        .file-upload {
            position: relative;
            overflow: hidden;
            margin-bottom: 1rem;
        }
        
        .file-upload-input {
            position: absolute;
            font-size: 100px;
            opacity: 0;
            right: 0;
            top: 0;
            cursor: pointer;
        }
        
        @media (max-width: 576px) {
            .card-body {
                padding: 2rem 1.5rem;
            }
            
            body {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="register-container">
            <div class="auth-card">
                <div class="card-body">
                    <div class="auth-header">
                        <h1 class="auth-title">Créer un compte</h1>
                        <p class="auth-subtitle">Gestion de flotte automobile</p>
                    </div>

                    <?php if(!empty($error_message)): ?>
                        <div class="alert alert-danger mb-4"><?= $error_message ?></div>
                    <?php endif; ?>
                    
                    <?php if(!empty($success_message)): ?>
                        <div class="alert alert-success mb-4"><?= $success_message ?></div>
                    <?php endif; ?>

                    <form method="post" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label required-field">Nom d'utilisateur</label>
                            <input type="text" class="form-control" name="username" required
                                   value="<?= isset($_POST['username']) ? htmlspecialchars($_POST['username']) : '' ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label required-field">Email</label>
                            <input type="email" class="form-control" name="email" required
                                   value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label required-field">Mot de passe</label>
                            <input type="password" class="form-control" name="password" required minlength="8">
                            <small class="text-muted">Minimum 8 caractères</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label required-field">Nom complet</label>
                            <input type="text" class="form-control" name="full_name" required
                                   value="<?= isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : '' ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label required-field">Rôle</label>
                            <select class="form-select" name="role" required>
                                <option value="">Sélectionner un rôle</option>
                                <option value="admin" <?= (isset($_POST['role']) && $_POST['role'] === 'admin') ? 'selected' : '' ?>>Admin</option>
                                <option value="user" <?= (isset($_POST['role']) && $_POST['role'] === 'user') ? 'selected' : '' ?>>Utilisateur</option>
                                <option value="moderator" <?= (isset($_POST['role']) && $_POST['role'] === 'moderator') ? 'selected' : '' ?>>Modérateur</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Fonction</label>
                            <select class="form-select" name="fonction">
                                <option value="">Sélectionner votre fonction</option>
                                <option value="Super utilisateur" <?= (isset($_POST['fonction']) && $_POST['fonction'] === 'Super utilisateur') ? 'selected' : '' ?>>Super utilisateur</option>
                                <option value="AREF" <?= (isset($_POST['fonction']) && $_POST['fonction'] === 'AREF') ? 'selected' : '' ?>>AREF</option>
                                <option value="Direction provinciale" <?= (isset($_POST['fonction']) && $_POST['fonction'] === 'Direction provinciale') ? 'selected' : '' ?>>Direction provinciale</option>
                                <option value="Sous-utilisateur" <?= (isset($_POST['fonction']) && $_POST['fonction'] === 'Sous-utilisateur') ? 'selected' : '' ?>>Sous-utilisateur</option>
                                <option value="Agent flotte(PPR)" <?= (isset($_POST['fonction']) && $_POST['fonction'] === 'Agent flotte(PPR)') ? 'selected' : '' ?>>Agent flotte (PPR)</option>
                            </select>
                            <small class="text-muted">Sélectionnez votre fonction dans l'organisation</small>
                        </div>

                        
                        <div class="mb-4">
                            <label class="form-label">Photo de profil</label>
                            <div class="file-upload">
                                <input type="file" class="form-control file-upload-input" name="profile_picture" accept="image/*">
                                <div class="input-group">
                                    <input type="text" class="form-control" placeholder="Choisir un fichier" disabled>
                                    <button class="btn btn-outline-secondary" type="button">Parcourir</button>
                                </div>
                            </div>
                            <small class="text-muted">Formats acceptés: JPG, PNG, GIF (max 2MB)</small>
                        </div>
                        
                        <button type="submit" class="btn btn-auth w-100 mb-3" name="ajouter">
                            Créer le compte
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-plus ms-2" viewBox="0 0 16 16">
                                <path d="M6 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm4 8c0 1-1 1-1 1H1s-1 0-1-1 1-4 6-4 6 3 6 4zm-1-.004c-.001-.246-.154-.986-.832-1.664C9.516 10.68 8.289 10 6 10c-2.29 0-3.516.68-4.168 1.332-.678.678-.83 1.418-.832 1.664h10z"/>
                                <path fill-rule="evenodd" d="M13.5 5a.5.5 0 0 1 .5.5V7h1.5a.5.5 0 0 1 0 1H14v1.5a.5.5 0 0 1-1 0V8h-1.5a.5.5 0 0 1 0-1H13V5.5a.5.5 0 0 1 .5-.5z"/>
                            </svg>
                        </button>
                    </form>
                    
                    <div class="divider">ou</div>
                    
                    <div class="auth-footer">
                        Déjà un compte ? <a href="index.php" class="auth-link">Se connecter</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Script pour afficher le nom du fichier sélectionné
        document.querySelector('.file-upload-input').addEventListener('change', function(e) {
            const fileName = e.target.files[0] ? e.target.files[0].name : 'Aucun fichier sélectionné';
            this.previousElementSibling.querySelector('input').value = fileName;
        });
        
        // Déclencher le click sur l'input file quand on clique sur le bouton Parcourir
        document.querySelector('.file-upload button').addEventListener('click', function() {
            this.parentElement.parentElement.querySelector('input[type="file"]').click();
        });
    </script>
</body>
</html>