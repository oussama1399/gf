<?php
session_start();
require_once 'include/database.php';

// Vérification de l'authentification
if (!isset($_SESSION['utilisateur'])) {
    header("Location: connection.php");
    exit();
}

// Récupérer la liste des agents
try {
    $agents = $pdo->query("SELECT a.ppr, a.nom, a.prenom, e.NOM_ETABL FROM tb_agents a LEFT JOIN z_etab e ON a.CD_ETAB = e.CD_ETAB ORDER BY a.nom, a.prenom")->fetchAll();
} catch (PDOException $e) {
    $agents = [];
}

// Traitement du formulaire
$error_message = '';
$success_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'declarer_perte') {
    $ppr = trim($_POST['ppr'] ?? '');
    $motif = trim($_POST['motif'] ?? '');
    if (empty($ppr) || empty($motif)) {
        $error_message = 'Tous les champs sont obligatoires.';
    } else {
        try {
            // Vérifier que l'agent existe
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tb_agents WHERE ppr = ?");
            $stmt->execute([$ppr]);
            if ($stmt->fetchColumn() == 0) {
                $error_message = 'Agent introuvable avec ce PPR.';
            } else {
                // Enregistrer la demande de remplacement
                $stmt = $pdo->prepare("INSERT INTO demandes_carte (ppr, motif, type_demande, statut) VALUES (?, ?, ?, ?)");
                $stmt->execute([$ppr, $motif, 'remplacement', 'en_attente']);
                $success_message = 'Déclaration de perte enregistrée et demande de remplacement créée.';
            }
        } catch (PDOException $e) {
            $error_message = 'Erreur lors de la déclaration : ' . $e->getMessage();
        }
    }
}

// Récupérer les pertes déclarées
try {
    $pertes = $pdo->query("SELECT d.*, a.nom, a.prenom, e.NOM_ETABL FROM demandes_carte d JOIN tb_agents a ON d.ppr = a.ppr LEFT JOIN z_etab e ON a.CD_ETAB = e.CD_ETAB WHERE d.type_demande = 'remplacement' ORDER BY d.date_demande DESC")->fetchAll();
} catch (PDOException $e) {
    $pertes = [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Déclaration de perte et demande de remplacement</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body { background: #f3f4f6; }
        .card { border-radius: 1rem; box-shadow: 0 2px 8px rgba(59,130,246,0.06); }
        .badge-status { font-weight: 600; padding: 0.45rem 0.9rem; border-radius: 0.5rem; font-size: 0.85rem; }
        .badge-attente { background-color: #fef9c3; color: #fbbf24; }
        .badge-approuve { background-color: #dcfce7; color: #22c55e; }
        .badge-rejete { background-color: #fee2e2; color: #ef4444; }
    </style>
</head>
<body>
    <?php include 'include/nav.php'; ?>
    <div class="container py-4">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="bi bi-exclamation-octagon me-2"></i>Déclaration de perte et demande de remplacement</h4>
            </div>
            <div class="card-body">
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?= htmlspecialchars($error_message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?= htmlspecialchars($success_message) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <form method="POST" class="row g-3 align-items-end">
                    <input type="hidden" name="action" value="declarer_perte">
                    <div class="col-md-4">
                        <label class="form-label">Agent *</label>
                        <select name="ppr" class="form-select" required>
                            <option value="">Sélectionner un agent</option>
                            <?php foreach ($agents as $agent): ?>
                            <option value="<?= $agent['ppr'] ?>">
                                <?= htmlspecialchars($agent['nom']) ?> <?= htmlspecialchars($agent['prenom']) ?> (<?= $agent['ppr'] ?>) - <?= htmlspecialchars($agent['NOM_ETABL']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Motif de la perte *</label>
                        <input type="text" name="motif" class="form-control" required placeholder="Expliquer le motif de la perte...">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-danger w-100"><i class="bi bi-exclamation-octagon me-1"></i>Déclarer</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Pertes déclarées et demandes de remplacement</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Agent</th>
                                <th>PPR</th>
                                <th>Établissement</th>
                                <th>Motif</th>
                                <th>Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pertes)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-3 text-muted">Aucune perte déclarée</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($pertes as $perte): ?>
                            <tr>
                                <td><?= date('d/m/Y H:i', strtotime($perte['date_demande'])) ?></td>
                                <td><?= htmlspecialchars($perte['prenom'] . ' ' . $perte['nom']) ?></td>
                                <td><span class="ppr-text"><?= $perte['ppr'] ?></span></td>
                                <td><?= htmlspecialchars($perte['NOM_ETABL'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($perte['motif']) ?></td>
                                <td>
                                    <?php if ($perte['statut'] === 'en_attente'): ?>
                                        <span class="badge badge-status badge-attente">En attente</span>
                                    <?php elseif ($perte['statut'] === 'approuve'): ?>
                                        <span class="badge badge-status badge-approuve">Approuvée</span>
                                    <?php else: ?>
                                        <span class="badge badge-status badge-rejete">Rejetée</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
