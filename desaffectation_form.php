<?php
session_start();
require_once 'include/database.php';

if (!isset($_SESSION['utilisateur'])) {
    header("Location: connection.php");
    exit();
}

// Récupérer les ND affectés
$ndAffectes = $pdo->query("SELECT af.nd, a.nom, a.prenom, e.NOM_ETABL FROM affectation_flotte af JOIN tb_agents a ON af.ppr = a.ppr JOIN z_etab e ON a.CD_ETAB = e.CD_ETAB ORDER BY af.nd")->fetchAll(PDO::FETCH_ASSOC);

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nd = $_POST['nd'] ?? '';
    if ($nd) {
        // Récupérer les infos avant suppression
    $infoStmt = $pdo->prepare('SELECT af.nd, a.ppr, a.nom, a.prenom, e.NOM_ETABL, f.libelle_fr as lib_fonction, af.id_statut, a.CD_ETAB, af.date_affectation FROM affectation_flotte af JOIN tb_agents a ON af.ppr = a.ppr JOIN z_etab e ON a.CD_ETAB = e.CD_ETAB JOIN r_fonction f ON af.id_fonction = f.id_fonction WHERE af.nd = ?');
    $infoStmt->execute([$nd]);
    $info = $infoStmt->fetch(PDO::FETCH_ASSOC);
        // Supprimer l'affectation
        $stmt = $pdo->prepare('DELETE FROM affectation_flotte WHERE nd = ?');
        if ($stmt->execute([$nd])) {
            // Ajouter à l'historique
            if ($info) {
                $histStmt = $pdo->prepare('INSERT INTO historique_flotte (ppr, nom, prenom, lib_etab, lib_fonction, date_affectation, date_session, statut_nd, CD_PROV, type_action) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)');
                $histStmt->execute([
                    $info['ppr'],
                    $info['nom'],
                    $info['prenom'],
                    $info['NOM_ETABL'],
                    $info['lib_fonction'],
                    $info['date_affectation'],
                    $info['id_statut'],
                    $info['CD_ETAB'],
                    'désaffectation'
                ]);
            }
            $success = "Désaffectation réussie pour le numéro $nd.";
            // Recharger la liste après suppression
            header("Refresh:2");
        } else {
            $error = "Erreur lors de la désaffectation.";
        }
    } else {
        $error = "Veuillez sélectionner un numéro à désaffecter.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Désaffectation d'un ND | Gestion Flotte</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-light: #6366f1;
            --primary-dark: #4338ca;
            --danger: #ef4444;
            --danger-dark: #dc2626;
        }
        
        body { 
            background: #f8fafc; 
            font-family: 'Inter', sans-serif; 
            color: #1e293b;
        }
        
        .card { 
            border: none;
            border-radius: 12px; 
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.03); 
            transition: transform 0.2s ease;
        }
        
        .card:hover {
            transform: translateY(-2px);
        }
        
        .page-header {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-left: 4px solid var(--primary);
        }
        
        .form-label { 
            font-weight: 500;
            color: #475569;
            margin-bottom: 0.5rem;
        }
        
        .form-select, .form-control {
            padding: 0.75rem 1rem;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        
        .form-select:focus, .form-control:focus {
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }
        
        .btn-primary { 
            background: var(--primary); 
            border: none;
            padding: 0.625rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            letter-spacing: 0.025em;
        }
        
        .btn-primary:hover { 
            background: var(--primary-dark); 
            transform: translateY(-1px);
        }
        
        .btn-danger {
            background: var(--danger);
            border: none;
        }
        
        .btn-danger:hover {
            background: var(--danger-dark);
        }
        
        .alert {
            border-radius: 8px;
            padding: 1rem;
        }
        
        .table-responsive {
            border-radius: 12px;
            overflow: hidden;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table thead th {
            background: #f1f5f9;
            color: #475569;
            font-weight: 600;
            padding: 1rem;
            border-bottom-width: 1px;
        }
        
        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-color: #f1f5f9;
        }
        
        .badge {
            font-weight: 500;
            padding: 0.35em 0.65em;
            border-radius: 50rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 2rem;
            color: #64748b;
        }
        
        .empty-state-icon {
            font-size: 3rem;
            color: #cbd5e1;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
<?php include 'include/nav.php'; ?>
<div class="container py-5">
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h4 fw-bold mb-2"><i class="bi bi-phone text-primary me-2"></i>Désaffectation de numéro</h1>
                <p class="text-muted mb-0">Retirez l'affectation d'un numéro à un agent</p>
            </div>
            <div>
                <a href="affectation_nd.php" class="btn btn-outline-primary">
                    <i class="bi bi-arrow-left me-1"></i> Retour
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card mb-4">
                <div class="card-body p-4">
                    <?php if ($error): ?>
                        <div class="alert alert-danger d-flex align-items-center mb-4">
                            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                            <div><?= htmlspecialchars($error) ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if ($success): ?>
                        <div class="alert alert-success d-flex align-items-center mb-4">
                            <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                            <div><?= htmlspecialchars($success) ?></div>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" autocomplete="off">
                        <div class="mb-4">
                            <label for="nd" class="form-label">Sélectionnez un numéro à désaffecter</label>
                            <select class="form-select" name="nd" id="nd" required>
                                <option value="">Choisir un numéro...</option>
                                <?php foreach ($ndAffectes as $row): ?>
                                    <option value="<?= htmlspecialchars($row['nd']) ?>">
                                        <?= htmlspecialchars($row['nd']) ?> - <?= htmlspecialchars($row['prenom'] . ' ' . $row['nom']) ?> (<?= htmlspecialchars($row['NOM_ETAB']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-danger px-4">
                                <i class="bi bi-x-circle me-2"></i> Désaffecter le numéro
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body p-4">
                    <h2 class="h5 fw-bold mb-4"><i class="bi bi-list-check me-2"></i>Liste des numéros affectés</h2>
                    
                    <?php if (empty($ndAffectes)): ?>
                        <div class="empty-state py-5">
                            <div class="empty-state-icon">
                                <i class="bi bi-check2-circle"></i>
                            </div>
                            <h3 class="h5 mb-2">Aucun numéro affecté</h3>
                            <p class="text-muted">Tous les numéros sont disponibles</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Numéro</th>
                                        <th>Agent</th>
                                        <th>Établissement</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ndAffectes as $row): ?>
                                        <tr>
                                            <td class="fw-semibold"><?= htmlspecialchars($row['nd']) ?></td>
                                            <td><?= htmlspecialchars($row['prenom'] . ' ' . $row['nom']) ?></td>
                                            <td><?= htmlspecialchars($row['NOM_ETABL']) ?></td>
                                            <td>
                                                <form method="post" style="display: inline;">
                                                    <input type="hidden" name="nd" value="<?= htmlspecialchars($row['nd']) ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="bi bi-x-lg"></i> Désaffecter
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Confirmation avant désaffectation
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', (e) => {
            const nd = form.querySelector('[name="nd"]').value;
            if (!confirm(`Êtes-vous sûr de vouloir désaffecter le numéro ${nd} ?`)) {
                e.preventDefault();
            }
        });
    });
</script>
</body>
</html>