<?php
session_start();
require_once 'include/database.php';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ppr = $_POST['ppr'];
    $nom = $_POST['nom'];
    $prenom = $_POST['prenom'];
    $id_fonction = $_POST['id_fonction'];
    $cd_etab = $_POST['cd_etab'];
    $cin = $_POST['cin'];
    $email = $_POST['email'];

    // Vérifier si le PPR existe déjà
    $exists = $pdo->prepare("SELECT COUNT(*) FROM tb_agents WHERE ppr = ?");
    $exists->execute([$ppr]);
    if ($exists->fetchColumn() > 0) {
        $_SESSION['error'] = "Ce PPR existe déjà.";
    } else {
        $stmt = $pdo->prepare("INSERT INTO tb_agents (ppr, nom, prenom, id_fonction, CD_ETAB, cin, email) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$ppr, $nom, $prenom, $id_fonction, $cd_etab, $cin, $email])) {
            $_SESSION['success'] = "Agent ajouté avec succès.";
        } else {
            $_SESSION['error'] = "Erreur lors de l'ajout.";
        }
    }
}

// Récupérer les fonctions et établissements pour les selects
$fonctions = $pdo->query("SELECT id_fonction, libelle_fr FROM r_fonction")->fetchAll();
$etablissements = $pdo->query("SELECT CD_ETAB, NOM_ETABL FROM z_etab WHERE Actif = 1")->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un agent</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #6366f1;
            --primary-light: #818cf8;
            --primary-lighter: #e0e7ff;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --dark-color: #1f2937;
            --light-color: #f9fafb;
            --border-color: #e5e7eb;
            --text-color: #374151;
            --text-muted: #6b7280;
        }
        
        body {
            background-color: var(--light-color);
            color: var(--text-color);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }
        
        .navbar {
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .page-header {
            background-color: white;
            padding: 1.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
        }
        
        .form-container {
            background: white;
            border-radius: 0.5rem;
            padding: 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
        }
        
        .form-section {
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .form-section-title {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
            display: block;
            color: var(--text-color);
        }
        
        .required-field::after {
            content: " *";
            color: var(--danger-color);
        }
        
        .form-control, .form-select {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            width: 100%;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
            outline: none;
        }
        
        .input-group {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            pointer-events: none;
        }
        
        .input-with-icon {
            padding-left: 2.5rem;
        }
        
        .form-footer {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
            margin-top: 1.5rem;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: #4f46e5;
            border-color: #4f46e5;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background-color: white;
            border: 1px solid var(--border-color);
            color: var(--text-color);
        }
        
        .btn-secondary:hover {
            background-color: var(--light-color);
            border-color: var(--text-muted);
        }
        
        .alert {
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid transparent;
        }
        
        .alert-danger {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
            border-left-color: var(--danger-color);
        }
        
        .alert-success {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            border-left-color: var(--success-color);
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-footer {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <?php include 'include/nav.php'; ?>

    <div class="container my-4">
        <!-- Messages d'alerte -->
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <?= htmlspecialchars($_SESSION['error']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle me-2"></i>
                <?= htmlspecialchars($_SESSION['success']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <!-- En-tête de page -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h4 mb-1 fw-semibold">
                        <i class="bi bi-person-plus me-2"></i>Ajouter un Agent
                    </h1>
                    <p class="text-muted mb-0">Créer un nouveau profil d'agent dans le système</p>
                </div>
                <div>
                    <a href="flotte.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-2"></i>Retour à la liste
                    </a>
                </div>
            </div>
        </div>

        <!-- Formulaire -->
        <div class="form-container">
            <form method="post">
                <!-- Section Informations personnelles -->
                <div class="form-section">
                    <h3 class="form-section-title">
                        <i class="bi bi-person"></i>
                        Informations Personnelles
                    </h3>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label required-field">PPR</label>
                            <div class="input-group">
                                <i class="bi bi-hash input-icon"></i>
                                <input type="text" name="ppr" class="form-control input-with-icon" 
                                       placeholder="Exemple: 12345678" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label required-field">Nom</label>
                            <div class="input-group">
                                <i class="bi bi-person input-icon"></i>
                                <input type="text" name="nom" class="form-control input-with-icon" 
                                       placeholder="Nom de famille" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label required-field">Prénom</label>
                            <div class="input-group">
                                <i class="bi bi-person input-icon"></i>
                                <input type="text" name="prenom" class="form-control input-with-icon" 
                                       placeholder="Prénom" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label required-field">CIN</label>
                            <div class="input-group">
                                <i class="bi bi-credit-card input-icon"></i>
                                <input type="text" name="cin" class="form-control input-with-icon" 
                                       placeholder="Carte d'identité nationale" required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section Contact -->
                <div class="form-section">
                    <h3 class="form-section-title">
                        <i class="bi bi-envelope"></i>
                        Informations de Contact
                    </h3>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label required-field">Email</label>
                            <div class="input-group">
                                <i class="bi bi-envelope input-icon"></i>
                                <input type="email" name="email" class="form-control input-with-icon" 
                                       placeholder="exemple@domaine.com" required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section Affectation -->
                <div class="form-section">
                    <h3 class="form-section-title">
                        <i class="bi bi-building"></i>
                        Affectation Professionnelle
                    </h3>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label required-field">Fonction</label>
                            <div class="input-group">
                                <i class="bi bi-briefcase input-icon"></i>
                                <select name="id_fonction" class="form-select input-with-icon" required>
                                    <option value="">Sélectionner une fonction...</option>
                                    <?php foreach ($fonctions as $fonction): ?>
                                        <option value="<?= $fonction['id_fonction'] ?>">
                                            <?= htmlspecialchars($fonction['libelle_fr']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label required-field">Établissement</label>
                            <div class="input-group">
                                <i class="bi bi-geo-alt input-icon"></i>
                                <select name="cd_etab" class="form-select input-with-icon" required>
                                    <option value="">Sélectionner un établissement...</option>
                                    <?php foreach ($etablissements as $etab): ?>
                                        <option value="<?= $etab['CD_ETAB'] ?>">
                                            <?= htmlspecialchars($etab['NOM_ETABL']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer du formulaire -->
                <div class="form-footer">
                    <a href="flotte.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Annuler
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Créer l'agent
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelector('form').addEventListener('submit', function(e) {
            if (!confirm("Confirmez-vous la création de cet agent ?")) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>