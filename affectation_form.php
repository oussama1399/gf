<?php
session_start();
error_log('Session utilisateur: ' . print_r($_SESSION['utilisateur'] ?? 'Aucune', true));
require_once 'include/database.php';
require_once 'mail_phpmailer.php';

if (!isset($_SESSION['utilisateur'])) {
    header("Location: connection.php");
    exit();
}

// Précharger les statuts
$idStatutActif = $pdo->query("SELECT id_statut FROM r_statuts WHERE libelle = 'actif'")->fetchColumn();
$idStatutInactif = $pdo->query("SELECT id_statut FROM r_statuts WHERE libelle = 'inactif'")->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'affecter') {
        $nd = $_POST['nd'] ?? '';
        $ppr = $_POST['ppr'] ?? '';
        $cd_etab = $_POST['etablissement'] ?? '';
        $id_fonction = $_POST['fonction'] ?? '';
        $etablissement = $cd_etab;
        $fonction = $id_fonction;

        if (empty($nd) || empty($ppr) || empty($cd_etab) || empty($id_fonction)) {
            $_SESSION['error'] = "Tous les champs sont obligatoires.";
        } else {
            $affectationExists = $pdo->prepare("SELECT COUNT(*) FROM affectation_flotte WHERE nd = ?");
            $affectationExists->execute([$nd]);
            $agentHasNd = $pdo->prepare("SELECT COUNT(*) FROM affectation_flotte WHERE ppr = ?");
            $agentHasNd->execute([$ppr]);

            if ($affectationExists->fetchColumn() > 0) {
                $_SESSION['error'] = "Ce numéro est déjà affecté à un agent.";
            } elseif ($agentHasNd->fetchColumn() > 0) {
                $_SESSION['error'] = "Cet agent a déjà un numéro affecté.";
            } else {
                $pdo->beginTransaction();
                try {
                    $pdo->prepare("UPDATE all_flotte SET id_statut = ?, CD_ETAB = ? WHERE nd = ?")
                        ->execute([$idStatutActif, $cd_etab, $nd]);

                    $pdo->prepare("INSERT INTO affectation_flotte (nd, ppr, date_affectation, id_fonction, id_statut) VALUES (?, ?, NOW(), ?, ?)")
                        ->execute([$nd, $ppr, $id_fonction, $idStatutActif]);

                    $stmt = $pdo->prepare("SELECT id_statut FROM all_flotte WHERE nd = ?");
                    $stmt->execute([$nd]);
                    $id_statut_nd = $stmt->fetchColumn();

                    $statut_nd = ($id_statut_nd == $idStatutActif) ? 1 : (($id_statut_nd == $idStatutInactif) ? 2 : 3);

                    $pdo->prepare("INSERT INTO historique_flotte (ppr, nom, prenom, lib_etab, lib_fonction, date_affectation, statut_nd, CD_PROV) 
                        SELECT a.ppr, a.nom, a.prenom, e.NOM_ETABL, f.libelle_fr, NOW(), ?, e.CD_ETAB
                        FROM tb_agents a 
                        JOIN z_etab e ON a.CD_ETAB = e.CD_ETAB 
                        JOIN r_fonction f ON a.id_fonction = f.id_fonction 
                        WHERE a.ppr = ?")
                        ->execute([$statut_nd, $ppr]);

                    $pdo->commit();

                    $stmt = $pdo->prepare("SELECT email, nom, prenom FROM tb_agents WHERE ppr = ?");
                    $stmt->execute([$ppr]);
                    $agent = $stmt->fetch();

                    if ($agent && !empty($agent['email'])) {
                        try {
                            $mailEnvoye = envoyerMailAffectation(
                                $agent['email'],
                                $agent['prenom'] . ' ' . $agent['nom'],
                                $nd,
                                $etablissement,
                                $fonction
                            );
                            if ($mailEnvoye) {
                                $_SESSION['success'] = "Affectation réussie ! L'email a bien été envoyé à l'agent.";
                            } else {
                                $_SESSION['success'] = "Affectation réussie ! Mais l'email n'a pas pu être envoyé à l'agent.";
                            }
                        } catch (Exception $e) {
                            error_log('Erreur envoi email: ' . $e->getMessage());
                            $_SESSION['success'] = "Affectation réussie ! Mais l'email n'a pas pu être envoyé à l'agent.";
                        }
                    } else {
                        $_SESSION['success'] = "Affectation réussie ! (Pas d'email renseigné pour l'agent)";
                    }

                    $_SESSION['success'] = "Affectation réussie !";
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    error_log('Erreur PDO: ' . $e->getMessage());
                    $_SESSION['error'] = "Erreur technique : " . $e->getMessage();
                }
            }
        }
    }


}

$numeros = $pdo->prepare("
    SELECT f.nd
    FROM all_flotte f
    LEFT JOIN affectation_flotte af ON f.nd = af.nd
    WHERE f.id_statut = ? OR (f.id_statut = ? AND af.ppr IS NULL)
");
$numeros->execute([$idStatutInactif, $idStatutActif]);
$numeros = $numeros->fetchAll();

// Récupérer les agents qui n'ont pas encore d'affectation
$agents = $pdo->query("
    SELECT a.ppr, a.nom, a.prenom 
    FROM tb_agents a 
    LEFT JOIN affectation_flotte af ON a.ppr = af.ppr 
    WHERE af.ppr IS NULL
    ORDER BY a.nom, a.prenom
")->fetchAll();

$etablissements = $pdo->query("SELECT CD_ETAB, NOM_ETABL FROM z_etab WHERE actif = 1 ORDER BY NOM_ETABL")->fetchAll();
$fonctions = $pdo->query("SELECT id_fonction, libelle_fr FROM r_fonction ORDER BY libelle_fr")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Affectation de numéro</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
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
            position: relative;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }
        
        .required-field::after {
            content: " *";
            color: var(--danger-color);
            font-weight: bold;
        }
        
        .form-control, .form-select {
            border: 1px solid var(--border-color);
            border-radius: 0.375rem;
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
            outline: none;
        }
        
        .form-control:hover, .form-select:hover {
            border-color: var(--primary-light);
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
            z-index: 5;
            pointer-events: none;
        }
        
        .input-with-icon {
            padding-left: 2.5rem;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            border-radius: 0.375rem;
            transition: all 0.2s ease;
        }
        
        .btn-primary:hover {
            background-color: #4f46e5;
            border-color: #4f46e5;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
        }
        
        .btn-secondary {
            background-color: white;
            border: 1px solid var(--border-color);
            color: var(--text-color);
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            border-radius: 0.375rem;
        }
        
        .btn-secondary:hover {
            background-color: var(--light-color);
            border-color: var(--text-muted);
        }
        
        .alert {
            border: none;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .alert-danger {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
            border-left: 4px solid var(--danger-color);
        }
        
        .alert-success {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
        }
        
        .form-footer {
            background-color: var(--light-color);
            padding: 1.5rem 2rem;
            margin: 0 -2rem -2rem -2rem;
            border-radius: 0 0 0.5rem 0.5rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-footer {
                flex-direction: column-reverse;
            }
            
            .form-footer .btn {
                width: 100%;
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
                        <i class="bi bi-sim me-2"></i>Affectation de Numéro
                    </h1>
                    <p class="text-muted mb-0">Affecter un numéro de flotte à un agent</p>
                </div>
                <div>
                    <a href="flotte.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-2"></i>Retour à la flotte
                    </a>
                </div>
            </div>
        </div>

        <!-- Formulaire -->
        <div class="form-container">
            <form method="post">
                <input type="hidden" name="action" value="affecter">
                
                <!-- Section Numéro et Agent -->
                <div class="form-section">
                    <h3 class="form-section-title">
                        <i class="bi bi-phone"></i>
                        Numéro et Agent
                    </h3>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label required-field">Numéro à affecter</label>
                            <div class="input-group">
                                <i class="bi bi-hash input-icon"></i>
                                <input list="ndList" name="nd" class="form-control input-with-icon" 
                                       placeholder="Sélectionner un numéro..." required>
                                <datalist id="ndList">
                                    <?php foreach ($numeros as $num): ?>
                                        <option value="<?= htmlspecialchars($num['nd']) ?>">
                                    <?php endforeach; ?>
                                </datalist>
                            </div>
                            <?php if (empty($numeros)): ?>
                                <small class="text-muted">Aucun numéro disponible pour affectation</small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label required-field">Agent (PPR)</label>
                            <div class="input-group">
                                <i class="bi bi-person input-icon"></i>
                                <select name="ppr" class="form-select input-with-icon" required>
                                    <option value="">Sélectionner un agent...</option>
                                    <?php foreach ($agents as $agent): ?>
                                        <option value="<?= $agent['ppr'] ?>">
                                            <?= htmlspecialchars($agent['ppr']) ?> - <?= htmlspecialchars($agent['nom'] . ' ' . $agent['prenom']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php if (empty($agents)): ?>
                                <small class="text-muted">Aucun agent disponible pour affectation</small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Section Affectation -->
                <div class="form-section">
                    <h3 class="form-section-title">
                        <i class="bi bi-building"></i>
                        Détails de l'Affectation
                    </h3>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label required-field">Établissement</label>
                            <div class="input-group">
                                <i class="bi bi-geo-alt input-icon"></i>
                                <select name="etablissement" class="form-select input-with-icon" required>
                                    <option value="">Sélectionner un établissement...</option>
                                    <?php foreach ($etablissements as $etab): ?>
                                        <option value="<?= $etab['CD_ETAB'] ?>">
                                            <?= htmlspecialchars($etab['NOM_ETABL']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label required-field">Fonction</label>
                            <div class="input-group">
                                <i class="bi bi-briefcase input-icon"></i>
                                <select name="fonction" class="form-select input-with-icon" required>
                                    <option value="">Sélectionner une fonction...</option>
                                    <?php foreach ($fonctions as $fonction): ?>
                                        <option value="<?= $fonction['id_fonction'] ?>">
                                            <?= htmlspecialchars($fonction['libelle_fr']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Footer du formulaire -->
                <div class="form-footer">
                    <button type="button" class="btn btn-secondary" onclick="window.history.back()">
                        <i class="bi bi-x-circle me-2"></i>Annuler
                    </button>
                    <?php if (!empty($numeros) && !empty($agents)): ?>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle me-2"></i>Valider l'Affectation
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn btn-primary" disabled>
                            <i class="bi bi-check-circle me-2"></i>Aucune affectation possible
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        });

        // Form validation and confirmation
        document.querySelectorAll('form').forEach(form => {
            if (form.querySelector('input[name="action"][value="affecter"]')) {
                form.addEventListener('submit', function(e) {
                    // Get form data for confirmation
                    const nd = document.querySelector('[name="nd"]').value;
                    const pprSelect = document.querySelector('[name="ppr"]');
                    const pprOption = pprSelect.options[pprSelect.selectedIndex];
                    const pprText = pprOption ? pprOption.textContent : '';
                    
                    if (!nd || !pprText) {
                        alert('Veuillez remplir tous les champs obligatoires.');
                        e.preventDefault();
                        return;
                    }
                    
                    if (!confirm(`Confirmez-vous l'affectation du numéro ${nd} à l'agent :\n${pprText} ?`)) {
                        e.preventDefault();
                    }
                });
            }
        });

        // Add some visual feedback on form interactions
        document.querySelectorAll('.form-control, .form-select').forEach(function(element) {
            element.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
            });
            
            element.addEventListener('blur', function() {
                this.parentElement.classList.remove('focused');
            });
        });
    </script>
</body>
</html>
