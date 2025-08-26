<?php
session_start();

require_once 'include/database.php';

$error = '';

if (isset($_POST['connection'])) {
    // Journalisation de la tentative de connexion
    error_log("Tentative de connexion depuis l'IP: " . $_SERVER['REMOTE_ADDR']);
    
    $identifiant = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($identifiant) || empty($password)) {
        $error = "Veuillez remplir tous les champs.";
        error_log("Champs manquants pour la connexion - IP: " . $_SERVER['REMOTE_ADDR']);
    } else {
        try {
            // Journalisation avant la requête
            error_log("Tentative de connexion pour l'identifiant: " . $identifiant);

            $stmt = $pdo->prepare("SELECT id, username, email, password_hash, role FROM users WHERE username = ? OR email = ? LIMIT 1");
            $stmt->execute([$identifiant, $identifiant]);
            $user = $stmt->fetch();

            if ($user) {
                error_log("Utilisateur trouvé: " . $user['username'] . " (ID: " . $user['id'] . ")");
                require_once 'include/password_utils.php';
                if (verify_password($password, $user['password_hash'])) {
                    error_log("Connexion réussie pour l'utilisateur: " . $user['username'] . " (ID: " . $user['id'] . ")");
                    session_regenerate_id(true);
                    // Recherche de l'agent correspondant dans tb_agents (par email)
                    $agent = null;
                    if (!empty($user['email'])) {
                        $stmt_agent = $pdo->prepare("SELECT ppr, nom, prenom, email FROM tb_agents WHERE email = ? LIMIT 1");
                        $stmt_agent->execute([$user['email']]);
                        $agent = $stmt_agent->fetch();
                    }
                    // Si pas trouvé par email, essayer par nom/prenom (si username = nom/prenom)
                    if (!$agent && !empty($user['username'])) {
                        // On suppose username = NOM PRENOM ou NOM_PRENOM
                        $parts = preg_split('/[ _]/', $user['username']);
                        if (count($parts) >= 2) {
                            $nom = strtoupper($parts[0]);
                            $prenom = strtoupper($parts[1]);
                            $stmt_agent = $pdo->prepare("SELECT ppr, nom, prenom, email FROM tb_agents WHERE UPPER(nom) = ? AND UPPER(prenom) = ? LIMIT 1");
                            $stmt_agent->execute([$nom, $prenom]);
                            $agent = $stmt_agent->fetch();
                        }
                    }
                    $_SESSION['utilisateur'] = [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'role' => $user['role'],
                        'email' => $user['email']
                    ];
                    if ($agent) {
                        $_SESSION['utilisateur']['ppr'] = $agent['ppr'];
                        $_SESSION['utilisateur']['nom'] = $agent['nom'];
                        $_SESSION['utilisateur']['prenom'] = $agent['prenom'];
                        $_SESSION['utilisateur']['email'] = $agent['email'];
                    }
                    error_log("Redirection selon le rôle pour l'utilisateur: " . $user['username']);
                    $role = strtolower($_SESSION['utilisateur']['role']);
                    $fonction = strtoupper(trim($_SESSION['utilisateur']['fonction'] ?? ''));
                    switch ($_SESSION['utilisateur']['role']) {
                        case 'admin':
                            header("Location: admin.php");
                        break;
                        case 'user':
                            header("Location: user_dashboard.php");
                        break;
                        case 'moderator':
                            header("Location: moderateur.php");
                        break;
                        default:
                            header("Location: index.php");
                        }
                    exit()
                } else {
                    $error = "Mot de passe incorrect.";
                    error_log("Échec de connexion: mot de passe incorrect pour l'identifiant: " . $identifiant);
                }
            } else {
                $error = "Utilisateur non trouvé.";
                error_log("Échec de connexion: utilisateur non trouvé: " . $identifiant);
            }
        } catch (PDOException $e) {
            $error = "Erreur système. Veuillez réessayer plus tard.";
            error_log("ERREUR PDO lors de la connexion: " . $e->getMessage() . " - Identifiant: " . $identifiant);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - Gestion Flotte</title>
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
        
        .login-container {
            max-width: 440px;
            margin: 0 auto;
            width: 100%;
        }
        
        .auth-card {
            border: none;
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(79, 70, 229, 0.08);
            overflow: hidden;
            background: white;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .auth-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(79, 70, 229, 0.12);
        }
        
        .card-body {
            padding: 2.75rem;
        }
        
        .auth-header {
            text-align: center;
            margin-bottom: 2.5rem;
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
        
        .form-control {
            border-radius: 10px;
            padding: 0.875rem 1.25rem;
            border: 1px solid var(--border-color);
            font-size: 0.95rem;
            transition: all 0.25s ease;
        }
        
        .form-control:focus {
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
        
        .divider::before {
            margin-right: 1rem;
        }
        
        .divider::after {
            margin-left: 1rem;
        }
        
        .alert-danger {
            border-radius: 10px;
            padding: 0.875rem 1.25rem;
            background-color: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #b91c1c;
            font-size: 0.9rem;
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
        <div class="login-container">
            <div class="auth-card">
                <div class="card-body">
                    <div class="auth-header">
                        <h1 class="auth-title">Accédez à votre compte</h1>
                        <p class="auth-subtitle">Gestion de flotte automobile</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger mb-4"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="post">
                        <div class="mb-4">
                            <label for="username" class="form-label">Identifiant</label>
                            <input type="text" class="form-control" id="username" name="username" placeholder="Email ou nom d'utilisateur" required autofocus>
                        </div>
                        
                        <div class="mb-4">
                            <label for="password" class="form-label">Mot de passe</label>
                            <input type="password" class="form-control" id="password" name="password" placeholder="••••••••" required>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="remember">
                                <label class="form-check-label" for="remember" style="font-size: 0.9rem;">Se souvenir de moi</label>
                            </div>
                            <a href="forgot_password.php" class="auth-link" style="font-size: 0.9rem;">Mot de passe oublié ?</a>
                        </div>
                        
                        <button type="submit" class="btn btn-auth w-100 mb-3" name="connection">
                            Se connecter
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-right ms-2" viewBox="0 0 16 16">
                                <path fill-rule="evenodd" d="M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8z"/>
                            </svg>
                        </button>
                    </form>
                    
                    <div class="divider">ou continuer avec</div>
                    
                    <div class="auth-footer">
                        Pas encore de compte ? <a href="index.php" class="auth-link">S'inscrire</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>