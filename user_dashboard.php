<?php
session_start();

require_once 'include/database.php';

// Vérification de l'authentification
if (!isset($_SESSION['utilisateur'])) {
    header("Location: connection.php");
    exit();
}

// Récupération fiable du PPR
$ppr = $_SESSION['utilisateur']['ppr'] ?? null;
if (empty($ppr)) {
    // 1. Essayer par email (champ unique)
    if (!empty($_SESSION['utilisateur']['email'])) {
        $stmt = $pdo->prepare("SELECT ppr FROM tb_agents WHERE email = ? LIMIT 1");
        $stmt->execute([$_SESSION['utilisateur']['email']]);
        $row = $stmt->fetch();
        if ($row && !empty($row['ppr'])) {
            $_SESSION['utilisateur']['ppr'] = $row['ppr'];
            $ppr = $row['ppr'];
        }
    }
    // 2. Sinon, essayer par nom/prénom (en majuscules pour éviter la casse)
    if (empty($ppr) && !empty($_SESSION['utilisateur']['nom']) && !empty($_SESSION['utilisateur']['prenom'])) {
        $stmt = $pdo->prepare("SELECT ppr FROM tb_agents WHERE UPPER(nom) = UPPER(?) AND UPPER(prenom) = UPPER(?) LIMIT 1");
        $stmt->execute([$_SESSION['utilisateur']['nom'], $_SESSION['utilisateur']['prenom']]);
        $row = $stmt->fetch();
        if ($row && !empty($row['ppr'])) {
            $_SESSION['utilisateur']['ppr'] = $row['ppr'];
            $ppr = $row['ppr'];
        }
    }
    // 3. Si toujours rien, message d'erreur explicite
    if (empty($ppr)) {
        echo '<div class="alert alert-danger">Impossible de retrouver votre PPR. Merci de contacter l\'administrateur.</div>';
    }
}
$username = $_SESSION['utilisateur']['username'] ?? '';

// Récupérer les infos personnelles de l'utilisateur
$stmt = $pdo->prepare("SELECT a.ppr, a.nom, a.prenom, e.NOM_ETABL, af.nd, s.libelle as etat_ligne FROM tb_agents a LEFT JOIN z_etab e ON a.CD_ETAB = e.CD_ETAB LEFT JOIN affectation_flotte af ON a.ppr = af.ppr AND af.id_statut IN (SELECT id_statut FROM r_statuts WHERE libelle IN ('actif','suspendu','resilie','perdu')) LEFT JOIN r_statuts s ON af.id_statut = s.id_statut WHERE a.ppr = ? ORDER BY af.date_affectation DESC LIMIT 1");
$stmt->execute([$ppr]);
$info = $stmt->fetch();

// Historique des lignes
if ($ppr) {
    $stmt = $pdo->prepare("SELECT af.nd, s.libelle as etat, af.date_affectation FROM affectation_flotte af LEFT JOIN r_statuts s ON af.id_statut = s.id_statut WHERE af.ppr = ? ORDER BY af.date_affectation DESC");
    $stmt->execute([$ppr]);
    $historique = $stmt->fetchAll();
} else {
    $historique = [];
}

// Historique des demandes
if ($ppr) {
    $stmt = $pdo->prepare("SELECT id, type_demande, statut, date_demande, traite_le FROM demandes_carte WHERE ppr = ? ORDER BY date_demande DESC");
    $stmt->execute([$ppr]);
    $demandes = $stmt->fetchAll();
} else {
    $demandes = [];
}

// Coordonnées support
$support = [
    'AREF' => ['email' => 'support.aref@example.com', 'tel' => '0520-000-000'],
    'Direction provinciale' => ['email' => 'support.direction@example.com', 'tel' => '0520-111-111']
];

$error_message = $error_message ?? '';
$success_message = '';

// Déclaration perte/dysfonctionnement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['declarer'])) {
    $type = $_POST['type'] ?? '';
    $commentaire = $_POST['commentaire'] ?? '';
    $justificatif = '';
    if (!empty($_FILES['justificatif']['name'])) {
        $target_dir = 'uploads/';
        $target_file = $target_dir . time() . '_' . basename($_FILES['justificatif']['name']);
        if (move_uploaded_file($_FILES['justificatif']['tmp_name'], $target_file)) {
            $justificatif = $target_file;
        }
    }
    $stmt = $pdo->prepare("INSERT INTO demandes_carte (ppr, type_demande, statut, date_demande, motif) VALUES (?, ?, 'en_attente', NOW(), ?)");
    if ($stmt->execute([$ppr, $type, $commentaire])) {
        $success_message = 'Votre déclaration a été soumise.';
    } else {
        $error_message = 'Erreur lors de la déclaration.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Tableau de Bord</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            background: #fff;
            font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
        }
        .dashboard-section {
            background: linear-gradient(120deg, #fff 60%, #f0f4ff 100%);
            border-radius: 1.2rem;
            box-shadow: 0 8px 32px rgba(79,70,229,0.13), 0 1.5px 8px rgba(0,0,0,0.04);
            margin-bottom: 2.5rem;
            padding: 2.5rem 2rem 2rem 2rem;
            transition: box-shadow 0.3s, transform 0.3s;
            position: relative;
            overflow: hidden;
        }
        .dashboard-section:hover {
            box-shadow: 0 16px 48px rgba(79,70,229,0.18), 0 2px 12px rgba(0,0,0,0.07);
            transform: translateY(-4px) scale(1.01);
        }
        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #7c3aed;
            margin-bottom: 1.3rem;
            letter-spacing: -0.5px;
            text-shadow: 0 2px 8px #e0e7ff;
            background: linear-gradient(90deg, #7c3aed 30%, #38bdf8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .table thead {
            background: linear-gradient(90deg, #6366f1 60%, #38bdf8 100%);
            color: #fff;
            animation: fadein 1.2s;
        }
        @keyframes fadein {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .table-striped tbody tr:nth-of-type(odd) {
            background: #f0f9ff;
        }
        .table-striped tbody tr:hover {
            background: #e0e7ff;
            transition: background 0.3s;
        }
        .badge {
            font-size: 1em;
            border-radius: 16px;
            letter-spacing: 0.5px;
            padding: 0.6em 1.2em;
            box-shadow: 0 2px 8px rgba(124,58,237,0.08);
            background: linear-gradient(90deg, #7c3aed 60%, #38bdf8 100%);
            color: #fff;
            animation: popbadge 0.7s;
        }
        @keyframes popbadge {
            0% { transform: scale(0.7); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }
        .badge-success { background: linear-gradient(90deg, #22c55e 60%, #38bdf8 100%) !important; }
        .badge-danger { background: linear-gradient(90deg, #ef4444 60%, #f59e42 100%) !important; }
        .badge-warning { background: linear-gradient(90deg, #f59e42 60%, #fbbf24 100%) !important; color: #fff !important; }
        .badge-secondary { background: linear-gradient(90deg, #64748b 60%, #818cf8 100%) !important; }
        .badge-dark { background: linear-gradient(90deg, #334155 60%, #818cf8 100%) !important; }
        .btn-danger, .btn-primary {
            border-radius: 12px;
            font-weight: 700;
            padding: 0.8em 2em;
            font-size: 1.1em;
            box-shadow: 0 4px 16px rgba(79,70,229,0.13);
            transition: background 0.2s, box-shadow 0.2s, transform 0.2s;
            background: linear-gradient(90deg, #7c3aed 60%, #38bdf8 100%);
            color: #fff;
            border: none;
        }
        .btn-danger:hover, .btn-primary:hover {
            background: linear-gradient(90deg, #38bdf8 60%, #7c3aed 100%);
            box-shadow: 0 8px 24px rgba(79,70,229,0.18);
            transform: scale(1.04);
        }
        .list-group-item {
            border: none;
            background: transparent;
            font-size: 1.08em;
            padding-left: 0;
        }
        .support-card {
            background: linear-gradient(120deg, #f0f9ff 0%, #e0e7ff 100%);
            border-radius: 1.2rem;
            padding: 1.7rem;
            box-shadow: 0 2px 12px rgba(79,70,229,0.10);
            animation: fadein 1.2s;
        }
        .form-select, .form-control {
            border-radius: 10px;
            font-size: 1.08em;
            transition: border 0.2s, box-shadow 0.2s;
        }
        .form-select:focus, .form-control:focus {
            border-color: #7c3aed;
            box-shadow: 0 0 0 3px #a5b4fc55;
        }
        .form-label {
            font-weight: 700;
            color: #7c3aed;
            letter-spacing: 0.2px;
        }
        .dashboard-section label, .dashboard-section strong {
            color: #334155;
        }
        .dashboard-section ul.list-group {
            background: none;
        }
        .dashboard-section .list-group-item {
            background: none;
        }
        @media (max-width: 768px) {
            .dashboard-section { padding: 1.2rem 0.7rem; }
        }
    </style>
</head>
<body>
<!-- Navbar personnalisé pour ce dashboard uniquement -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-4">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold text-primary" href="flotte.php"><i class="bi bi-phone"></i> Gestion Flotte</a>
        <div class="d-flex align-items-center ms-auto">
            <span class="nav-link fw-semibold"><i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['utilisateur']['username'] ?? '') ?></span>
            <a class="btn btn-outline-danger ms-2" href="deconnection.php"><i class="bi bi-box-arrow-right"></i> </a>
        </div>
    </div>
</nav>
<div class="container my-5">
    <h2 class="mb-4" style="font-size:2rem;font-weight:700;color:#222;"><i class="bi bi-person-circle me-2"></i>Mon Tableau de Bord</h2>
    <?php if ($error_message): ?>
        <div class="alert alert-danger"> <?= htmlspecialchars($error_message) ?> </div>
    <?php endif; ?>
    <?php if ($success_message): ?>
        <div class="alert alert-success"> <?= htmlspecialchars($success_message) ?> </div>
    <?php endif; ?>

    <!-- ===================== 1. INFOS PERSONNELLES ===================== -->
    <div class="dashboard-section">
        <div class="section-title"><i class="bi bi-info-circle me-2"></i>Mes informations</div>
        <div class="row mb-3">
            <div class="col-md-6">
                <ul class="list-group">
                    <li class="list-group-item"><strong>PPR :</strong> <?= htmlspecialchars($info['ppr'] ?? '') ?></li>
                    <li class="list-group-item"><strong>Nom :</strong> <?= htmlspecialchars($info['nom'] ?? '') ?></li>
                    <li class="list-group-item"><strong>Prénom :</strong> <?= htmlspecialchars($info['prenom'] ?? '') ?></li>
                    <li class="list-group-item"><strong>Établissement :</strong> <?= htmlspecialchars($info['NOM_ETABL'] ?? '') ?></li>
                </ul>
            </div>
            <div class="col-md-6">
                <ul class="list-group">
                    <li class="list-group-item"><strong>ND attribué :</strong> <?= htmlspecialchars($info['nd'] ?? 'Aucun') ?></li>
                    <li class="list-group-item"><strong>État de la ligne :</strong> <span class="badge bg-<?= ($info['etat_ligne'] === 'actif' ? 'success' : ($info['etat_ligne'] === 'suspendu' ? 'warning' : ($info['etat_ligne'] === 'resilie' ? 'danger' : 'secondary'))) ?>"> <?= htmlspecialchars($info['etat_ligne'] ?? 'N/A') ?> </span></li>
                </ul>
            </div>
        </div>
    </div>

    <!-- ===================== 2. HISTORIQUE DES LIGNES ===================== -->
    <div class="dashboard-section">
        <div class="section-title"><i class="bi bi-clock-history me-2"></i>Historique de mes lignes</div>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead><tr><th>ND</th><th>État</th><th>Date d'affectation</th></tr></thead>
                <tbody>
                <?php foreach ($historique as $h): ?>
                    <tr>
                        <td><?= htmlspecialchars($h['nd']) ?></td>
                        <td><span class="badge bg-<?= ($h['etat'] === 'actif' ? 'success' : ($h['etat'] === 'suspendu' ? 'warning' : ($h['etat'] === 'resilie' ? 'danger' : ($h['etat'] === 'perdu' ? 'dark' : 'secondary')))) ?>"><?= htmlspecialchars($h['etat']) ?></span></td>
                        <td><?= $h['date_affectation'] ? date('d/m/Y', strtotime($h['date_affectation'])) : '' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ===================== 3. DÉCLARATION PERTE/DYSFONCTIONNEMENT ===================== -->
    <div class="dashboard-section">
        <div class="section-title"><i class="bi bi-exclamation-diamond me-2"></i>Déclarer une perte ou un dysfonctionnement</div>
        <form method="post" enctype="multipart/form-data" class="row g-3">
            <div class="col-md-4">
                <select name="type" class="form-select" required>
                    <option value="">Type de déclaration</option>
                    <option value="perte">Perte</option>
                    <option value="dysfonctionnement">Dysfonctionnement</option>
                </select>
            </div>
            <div class="col-md-5">
                <input type="text" name="commentaire" class="form-control" placeholder="Commentaire ou motif (optionnel)">
            </div>
            <div class="col-md-3">
                <input type="file" name="justificatif" class="form-control">
            </div>
            <div class="col-12 text-end">
                <button type="submit" name="declarer" class="btn btn-danger"><i class="bi bi-send me-1"></i>Déclarer</button>
            </div>
        </form>
    </div>

    <!-- ===================== 4. SUIVI DES DEMANDES ===================== -->
    <div class="dashboard-section">
        <div class="section-title"><i class="bi bi-list-check me-2"></i>Suivi de mes demandes</div>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead><tr><th>Type</th><th>Statut</th><th>Date de demande</th><th>Date de traitement</th><th>Motif de rejet</th></tr></thead>
                <tbody>
                <?php foreach ($demandes as $d): ?>
                    <tr>
                        <td><?= ucfirst(htmlspecialchars($d['type_demande'])) ?></td>
                        <td><span class="badge bg-<?= ($d['statut'] === 'approuve' ? 'success' : ($d['statut'] === 'rejete' ? 'danger' : 'warning')) ?>"><?= ucfirst(htmlspecialchars($d['statut'])) ?></span></td>
                        <td><?= $d['date_demande'] ? date('d/m/Y', strtotime($d['date_demande'])) : '' ?></td>
                        <td><?= $d['traite_le'] ? date('d/m/Y', strtotime($d['traite_le'])) : '-' ?></td>
                        <td>-</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ===================== 5. ASSISTANCE & SUPPORT ===================== -->
    <div class="dashboard-section support-card">
        <div class="section-title"><i class="bi bi-headset me-2"></i>Assistance & Contact</div>
        <div class="row">
            <?php foreach ($support as $label => $contact): ?>
            <div class="col-md-6 mb-2">
                <div class="p-3 bg-white rounded shadow-sm h-100">
                    <strong><?= htmlspecialchars($label) ?></strong><br>
                    <i class="bi bi-envelope me-1"></i> <?= htmlspecialchars($contact['email']) ?><br>
                    <i class="bi bi-telephone me-1"></i> <?= htmlspecialchars($contact['tel']) ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
