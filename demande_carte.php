<?php
session_start();

// Vérification de l'authentification
if (!isset($_SESSION['utilisateur'])) {
    header("Location: connection.php");
    exit();
}

// Seul l'admin ou le modérateur peut accéder à cette page
if (!in_array($_SESSION['utilisateur']['role'], ['admin', 'moderator'])) {
    header("Location: flotte.php");
    exit();
}

require_once 'include/database.php';
require_once 'mail_phpmailer.php';

// Créer la table demandes_carte si elle n'existe pas
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS demandes_carte (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ppr VARCHAR(50) NOT NULL,
        motif TEXT NOT NULL,
        type_demande ENUM('changement', 'nouvelle', 'remplacement') DEFAULT 'changement',
        statut ENUM('en_attente', 'approuve', 'rejete') DEFAULT 'en_attente',
        commentaire_admin TEXT NULL,
        date_demande DATETIME DEFAULT CURRENT_TIMESTAMP,
        traite_le DATETIME NULL,
        traite_par INT NULL,
        FOREIGN KEY (ppr) REFERENCES tb_agents(ppr),
        FOREIGN KEY (traite_par) REFERENCES users(id)
    )");
    // Ajout de la colonne type_demande si elle n'existe pas
    $columns = $pdo->query("SHOW COLUMNS FROM demandes_carte LIKE 'type_demande'")->fetchAll();
    if (count($columns) === 0) {
        $pdo->exec("ALTER TABLE demandes_carte ADD COLUMN type_demande ENUM('changement', 'nouvelle', 'remplacement') DEFAULT 'changement' AFTER motif");
    }
} catch (PDOException $e) {
    // Table existe déjà ou erreur, on continue
}

// Traitement des actions
$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'valider':
                $result = validerDemande($pdo, $_POST['demande_id']);
                if ($result['success']) {
                    $success_message = $result['message'];
                } else {
                    $error_message = $result['message'];
                }
                break;
            case 'rejeter':
                $result = rejeterDemande($pdo, $_POST['demande_id'], $_POST['commentaire']);
                if ($result['success']) {
                    $success_message = $result['message'];
                } else {
                    $error_message = $result['message'];
                }
                break;
            // Suppression de la création de nouvelle demande sur cette page
        }
    }
}

// Traitement de la demande : affecter un ND libre et passer la demande en approuvée
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'traiter_demande') {
    $demandeId = $_POST['demande_id'] ?? null;
    $ndLibre = $_POST['nd_libre'] ?? null;
    if ($demandeId && $ndLibre) {
        try {
            // Récupérer le PPR de la demande
            $stmt = $pdo->prepare("SELECT ppr FROM demandes_carte WHERE id = ?");
            $stmt->execute([$demandeId]);
            $ppr = $stmt->fetchColumn();
            if ($ppr) {
                // Récupérer la fonction de l'agent
                $stmt = $pdo->prepare("SELECT id_fonction FROM tb_agents WHERE ppr = ?");
                $stmt->execute([$ppr]);
                $id_fonction = $stmt->fetchColumn();
                // Récupérer l'id du statut 'actif'
                $id_statut = $pdo->query("SELECT id_statut FROM r_statuts WHERE libelle = 'actif'")->fetchColumn();
                // Affecter le ND libre à l'agent
                $stmt = $pdo->prepare("INSERT INTO affectation_flotte (nd, ppr, date_affectation, id_fonction, id_statut) VALUES (?, ?, NOW(), ?, ?)");
                $stmt->execute([$ndLibre, $ppr, $id_fonction, $id_statut]);

                // Ajouter à l'historique
                $stmt = $pdo->prepare("
                    INSERT INTO historique_flotte (ppr, nom, prenom, lib_etab, lib_fonction, date_affectation, statut_nd, CD_PROV)
                    SELECT a.ppr, a.nom, a.prenom, e.NOM_ETABL, f.libelle_fr, NOW(), ?, e.CD_ETAB
                    FROM tb_agents a
                    JOIN z_etab e ON a.CD_ETAB = e.CD_ETAB
                    JOIN r_fonction f ON a.id_fonction = f.id_fonction
                    WHERE a.ppr = ?
                ");
                $stmt->execute([1, $ppr]); // 1 = statut actif (ou adapter selon logique)
                // Mettre la demande en approuvée
                $stmt = $pdo->prepare("UPDATE demandes_carte SET statut = 'approuve', traite_le = NOW(), traite_par = ? WHERE id = ?");
                $stmt->execute([$_SESSION['utilisateur']['id'], $demandeId]);
                $success_message = "ND $ndLibre affecté à l'agent et demande approuvée.";
            }
        } catch (PDOException $e) {
            $error_message = 'Erreur lors du traitement : ' . $e->getMessage();
        }
    }
}

// Fonction pour créer une nouvelle demande
function creerNouvelleDemande($pdo, $data) {
    try {
        $ppr = trim($data['ppr']);
        $motif = trim($data['motif']);
        $type_demande = $data['type_demande'] ?? 'changement';
        
        if (empty($ppr) || empty($motif)) {
            return ['success' => false, 'message' => 'Tous les champs sont obligatoires'];
        }
        
        // Vérifier que l'agent existe
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM tb_agents WHERE ppr = ?");
        $stmt->execute([$ppr]);
        if ($stmt->fetchColumn() == 0) {
            return ['success' => false, 'message' => 'Agent introuvable avec ce PPR'];
        }
        
        // Vérifier s'il n'y a pas déjà une demande en attente
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM demandes_carte WHERE ppr = ? AND statut = 'en_attente'");
        $stmt->execute([$ppr]);
        if ($stmt->fetchColumn() > 0) {
            return ['success' => false, 'message' => 'Une demande est déjà en attente pour cet agent'];
        }
        
        $stmt = $pdo->prepare("INSERT INTO demandes_carte (ppr, motif, type_demande) VALUES (?, ?, ?)");
        $stmt->execute([$ppr, $motif, $type_demande]);
        
        return ['success' => true, 'message' => 'Demande créée avec succès'];
        
    } catch (PDOException $e) {
        return ['success' => false, 'message' => 'Erreur lors de la création : ' . $e->getMessage()];
    }
}

// Fonction pour valider une demande
function validerDemande($pdo, $demandeId) {
    try {
        // 1. Marquer la demande comme approuvée
        $stmt = $pdo->prepare("UPDATE demandes_carte SET statut = 'approuve', traite_le = NOW(), traite_par = ? WHERE id = ?");
        $stmt->execute([$_SESSION['utilisateur']['id'], $demandeId]);
        
        // 2. Récupérer les infos de la demande
        $stmt = $pdo->prepare("
            SELECT d.*, a.nom, a.prenom, a.email 
            FROM demandes_carte d 
            JOIN tb_agents a ON d.ppr = a.ppr 
            WHERE d.id = ?
        ");
        $stmt->execute([$demandeId]);
        $demande = $stmt->fetch();
        
        // 3. Envoyer une notification par email si disponible
        if ($demande && !empty($demande['email'])) {
            try {
                envoyerMailDemande($demande['email'], $demande['prenom'] . ' ' . $demande['nom'], 'approuvée');
            } catch (Exception $e) {
                // Email échoué mais demande validée
            }
        }
        
        return ['success' => true, 'message' => "La demande a été validée avec succès."];
        
    } catch (PDOException $e) {
        return ['success' => false, 'message' => "Erreur lors de la validation : " . $e->getMessage()];
    }
}

// Fonction pour rejeter une demande
function rejeterDemande($pdo, $demandeId, $commentaire) {
    try {
        $stmt = $pdo->prepare("UPDATE demandes_carte SET statut = 'rejete', commentaire_admin = ?, traite_le = NOW(), traite_par = ? WHERE id = ?");
        $stmt->execute([$commentaire, $_SESSION['utilisateur']['id'], $demandeId]);
        
        // Récupérer les infos pour notification
        $stmt = $pdo->prepare("
            SELECT d.*, a.nom, a.prenom, a.email 
            FROM demandes_carte d 
            JOIN tb_agents a ON d.ppr = a.ppr 
            WHERE d.id = ?
        ");
        $stmt->execute([$demandeId]);
        $demande = $stmt->fetch();
        
        // Envoyer notification
        if ($demande && !empty($demande['email'])) {
            try {
                envoyerMailDemande($demande['email'], $demande['prenom'] . ' ' . $demande['nom'], 'rejetée', $commentaire);
            } catch (Exception $e) {
                // Email échoué mais demande rejetée
            }
        }
        
        return ['success' => true, 'message' => "La demande a été rejetée."];
        
    } catch (PDOException $e) {
        return ['success' => false, 'message' => "Erreur lors du rejet : " . $e->getMessage()];
    }
}

// Fonction pour envoyer un email de notification
function envoyerMailDemande($email, $nom, $statut, $commentaire = '') {
    $sujet = "Demande de carte - " . ucfirst($statut);
    $message = "
    <h3>Demande de carte {$statut}</h3>
    <p>Bonjour {$nom},</p>
    <p>Votre demande de carte a été <strong>{$statut}</strong>.</p>
    ";
    
    if (!empty($commentaire)) {
        $message .= "<p><strong>Commentaire :</strong> " . htmlspecialchars($commentaire) . "</p>";
    }
    
    $message .= "
    <p>Pour plus d'informations, contactez votre administrateur.</p>
    <p>Cordialement,<br>L'équipe de gestion de flotte</p>
    ";
    
    return envoyerMail($email, $sujet, $message);
}

// Récupérer les demandes en attente
try {
    $demandesEnAttente = $pdo->query("
        SELECT d.*, a.nom, a.prenom, a.ppr, af.nd, e.NOM_ETABL
        FROM demandes_carte d
        JOIN tb_agents a ON d.ppr = a.ppr
        LEFT JOIN affectation_flotte af ON a.ppr = af.ppr
        LEFT JOIN z_etab e ON a.CD_ETAB = e.CD_ETAB
        WHERE d.statut = 'en_attente'
        ORDER BY d.date_demande ASC
    ")->fetchAll();
} catch (PDOException $e) {
    $demandesEnAttente = [];
    $error_message = "Erreur lors du chargement des demandes : " . $e->getMessage();
}

// Récupérer les demandes traitées (uniquement celles traitées par un utilisateur)
try {
    $demandesTraitees = $pdo->query("
        SELECT d.*, a.nom, a.prenom, u.username as traite_par_nom 
        FROM demandes_carte d
        JOIN tb_agents a ON d.ppr = a.ppr
        LEFT JOIN users u ON d.traite_par = u.id
        WHERE d.statut != 'en_attente' AND d.traite_par IS NOT NULL
        ORDER BY d.traite_le DESC
        LIMIT 20
    ")->fetchAll();
} catch (PDOException $e) {
    $demandesTraitees = [];
}

// Récupérer la liste des agents pour le formulaire
try {
    $agents = $pdo->query("
        SELECT a.ppr, a.nom, a.prenom, e.NOM_ETABL
        FROM tb_agents a
        LEFT JOIN z_etab e ON a.CD_ETAB = e.CD_ETAB
        ORDER BY a.nom, a.prenom
    ")->fetchAll();
} catch (PDOException $e) {
    $agents = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Demandes de Carte</title>
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
        .data-card {
            background-color: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            border: none;
            overflow: hidden;
            margin-bottom: 1.5rem;
        }
        .table {
    margin-bottom: 0;
    border-collapse: separate;
    border-spacing: 0 8px; /* Espace vertical entre les lignes */
    }

    .table thead th {
        background-color: var(--primary-lighter);
        color: var(--primary-color);
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.9rem;
        letter-spacing: 0.5px;
        padding: 1rem; /* Plus de padding */
        position: sticky;
        top: 0;
        z-index: 2;
    }

    .table tbody tr {
        background-color: white;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .table tbody tr:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 10px rgba(0,0,0,0.08);
        background-color: var(--primary-lighter);
    }

    .table tbody td {
        padding: 1rem 1.2rem; /* Plus d'espace dans les cellules */
        vertical-align: middle;
        border-top: none !important; /* Enlève les traits verticaux internes */
    }

    .table-striped tbody tr:nth-of-type(odd) td {
        background-color: #f8fafc;
    }

        .badge-status {
            font-weight: 600;
            padding: 0.4rem 0.7rem;
            border-radius: 0.25rem;
            font-size: 0.8rem;
        }
        .badge-attente {
            background-color: rgba(245, 158, 11, 0.15);
            color: var(--warning-color);
        }
        .badge-approuve {
            background-color: rgba(16, 185, 129, 0.15);
            color: var(--success-color);
        }
        .badge-rejete {
            background-color: rgba(239, 68, 68, 0.15);
            color: var(--danger-color);
        }
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        .btn-primary:hover {
            background-color: #4f46e5;
            border-color: #4f46e5;
        }
        .btn-success {
            background-color: var(--success-color);
            border-color: var(--success-color);
        }
        .btn-success:hover {
            background-color: #059669;
            border-color: #059669;
        }
        .btn-danger {
            background-color: var(--danger-color);
            border-color: var(--danger-color);
        }
        .btn-danger:hover {
            background-color: #dc2626;
            border-color: #dc2626;
        }
        .btn-icon {
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            border-radius: 50%;
        }
        .ppr-text {
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 0.95rem;
            color: var(--text-muted);
        }
        .table-responsive {
            border-radius: 0.5rem;
            overflow-x: auto;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        @media (max-width: 992px) {
            .table thead {
                display: none;
            }
            .table tbody tr {
                display: block;
                margin-bottom: 1rem;
                border: 1px solid var(--border-color);
                border-radius: 0.5rem;
                box-shadow: 0 1px 2px rgba(0,0,0,0.03);
            }
            .table tbody td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0.85rem;
                border-top: none;
                border-bottom: 1px solid var(--border-color);
                background: #fff;
            }
            .table tbody td::before {
                content: attr(data-label);
                font-weight: 600;
                color: var(--text-muted);
                margin-right: 1rem;
            }
            .table tbody td:last-child {
                border-bottom: none;
            }
            .table tbody tr:last-child {
                margin-bottom: 0;
            }
        }
    </style>
</head>
<body>
    <?php include 'include/nav.php'; ?>

    <div class="container my-4">
        <!-- Messages d'alerte -->
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

        <!-- En-tête de page -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h4 mb-1 fw-semibold">
                        <i class="bi bi-credit-card me-2"></i>Gestion des Demandes de Carte
                    </h1>
                    <p class="text-muted mb-0">Gérez les demandes de carte des agents</p>
                </div>
                <!-- Bouton Nouvelle Demande supprimé -->
            </div>
        </div>

        <!-- Modal pour nouvelle demande -->
        <div class="modal fade" id="nouvelleDemande" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Créer une nouvelle demande</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <!-- Formulaire de création de nouvelle demande supprimé -->
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                            <button type="submit" class="btn btn-primary">Créer la demande</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Demandes en attente -->
        <div class="data-card card">
            <div class="card-header" style="background-color: var(--primary-light); color: white;">
                <h5 class="mb-0"><i class="bi bi-hourglass-split me-2"></i>Demandes en Attente</h5>
            </div>
            <div class="card-body">
                <?php if (empty($demandesEnAttente)): ?>
                    <div class="alert alert-info">Aucune demande en attente.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Agent</th>
                                    <th>PPR</th>
                                    <th>Établissement</th>
                                    <th>Carte actuelle</th>
                                    <th>Type</th>
                                    <th>Motif</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($demandesEnAttente as $demande): ?>
                                <tr>
                                    <td data-label="Date"><?= date('d/m/Y H:i', strtotime($demande['date_demande'])) ?></td>
                                    <td data-label="Agent"><?= htmlspecialchars($demande['prenom'] . ' ' . $demande['nom']) ?></td>
                                    <td data-label="PPR"><span class="ppr-text"><?= $demande['ppr'] ?></span></td>
                                    <td data-label="Établissement"><?= htmlspecialchars($demande['NOM_ETABL'] ?? 'N/A') ?></td>
                                    <td data-label="Carte actuelle"><?= $demande['nd'] ?? '<span class="text-muted">Aucune</span>' ?></td>
                                    <td data-label="Type">
                                        <span class="badge badge-status" style="background-color: var(--primary-lighter); color: var(--primary-color);">
                                            <?= ucfirst($demande['type_demande']) ?>
                                        </span>
                                    </td>
                                    <td data-label="Motif">
                                        <button type="button" class="btn btn-sm btn-outline-info btn-icon" 
                                                data-bs-toggle="tooltip" 
                                                title="<?= htmlspecialchars($demande['motif']) ?>">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </td>
                                    <td data-label="Actions">
                                        <div class="d-flex justify-content-center gap-2 flex-wrap">
                                            <button class="btn btn-sm btn-primary d-flex align-items-center" type="button" onclick="showInlineForm('traiter', <?= $demande['id'] ?>)">
                                                <i class="bi bi-arrow-right-circle me-1"></i> Traiter
                                            </button>
                                            <button class="btn btn-sm btn-danger d-flex align-items-center" type="button" onclick="showInlineForm('rejeter', <?= $demande['id'] ?>)">
                                                <i class="bi bi-x-circle me-1"></i> Rejeter
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <!-- Formulaire inline sous la ligne -->
                                <tr id="inline-form-row-<?= $demande['id'] ?>" class="d-none">
                                    <td colspan="8" class="p-0">
                                        <div id="inline-form-content-<?= $demande['id'] ?>" style="background:#f8fafc; border:2px solid #6366f1; border-radius:0.5rem; margin:0.5rem 0; padding:1.2rem; max-width:600px; margin-left:auto; margin-right:auto;">
                                            <!-- Formulaires générés par JS -->
                                        </div>
                                    </td>
                                </tr>
    <script>
    // Cache tous les formulaires inline
    function hideAllInlineForms() {
        document.querySelectorAll('tr[id^="inline-form-row-"]').forEach(row => row.classList.add('d-none'));
    }
    // Affiche le formulaire demandé sous la ligne
    function showInlineForm(type, id) {
        hideAllInlineForms();
        const row = document.getElementById('inline-form-row-' + id);
        const content = document.getElementById('inline-form-content-' + id);
        if (!row || !content) return;
        let html = '';
        if (type === 'traiter') {
            html = `
                <form method="POST" class="mb-0">
                    <input type="hidden" name="demande_id" value="${id}">
                    <input type="hidden" name="action" value="traiter_demande">
                    <div class="mb-2">
                        <label class="form-label">ND libre à affecter *</label>
                        <select name="nd_libre" class="form-select form-select-sm" required>
                            <option value="">Sélectionner un ND libre</option>
                            ${window['ndsLibres'+id] ? window['ndsLibres'+id].map(nd => `<option value="${nd}">${nd}</option>`).join('') : ''}
                        </select>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm">Affecter</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="hideAllInlineForms()">Annuler</button>
                    </div>
                </form>
            `;
        } else if (type === 'rejeter') {
            html = `
                <form method="POST" class="mb-0">
                    <input type="hidden" name="demande_id" value="${id}">
                    <input type="hidden" name="action" value="rejeter">
                    <div class="mb-2">
                        <label class="form-label">Motif du rejet</label>
                        <textarea name="commentaire" class="form-control form-control-sm" required></textarea>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-danger btn-sm">Confirmer le rejet</button>
                        <button type="button" class="btn btn-secondary btn-sm" onclick="hideAllInlineForms()">Annuler</button>
                    </div>
                </form>
            `;
        }
        content.innerHTML = html;
        row.classList.remove('d-none');
    }
    // Préparer les ND libres pour chaque demande (pour JS)
    <?php foreach ($demandesEnAttente as $demande):
        $ndsLibres = $pdo->query("SELECT nd FROM all_flotte WHERE nd NOT IN (SELECT nd FROM affectation_flotte)")->fetchAll(PDO::FETCH_COLUMN);
        $ndsLibresJson = json_encode(array_map('htmlspecialchars', $ndsLibres));
    ?>
    window['ndsLibres<?= $demande['id'] ?>'] = <?= $ndsLibresJson ?>;
    <?php endforeach; ?>
    </script>

                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Historique des demandes -->
        <div class="data-card card">
            <div class="card-header" style="background-color: var(--primary-light); color: white;">
                <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Historique des Demandes</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Date demande</th>
                                <th>Agent</th>
                                <th>PPR</th>
                                <th>Type</th>
                                <th>Statut</th>
                                <th>Traité par</th>
                                <th>Date traitement</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($demandesTraitees)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-3">
                                    <i class="bi bi-inbox text-muted fs-1"></i>
                                    <p class="text-muted mt-2">Aucune demande traitée</p>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($demandesTraitees as $demande): ?>
                            <tr>
                                <td data-label="Date demande"><?= date('d/m/Y H:i', strtotime($demande['date_demande'])) ?></td>
                                <td data-label="Agent"><?= htmlspecialchars($demande['prenom'] . ' ' . $demande['nom']) ?></td>
                                <td data-label="PPR"><span class="ppr-text"><?= $demande['ppr'] ?></span></td>
                                <td data-label="Type">
                                    <span class="badge badge-status" style="background-color: rgba(107, 114, 128, 0.1); color: var(--text-muted);">
                                        <?= ucfirst($demande['type_demande']) ?>
                                    </span>
                                </td>
                                <td data-label="Statut">
                                    <span class="badge badge-status <?= $demande['statut'] === 'approuve' ? 'badge-approuve' : 'badge-rejete' ?>">
                                        <?= $demande['statut'] === 'approuve' ? 'Approuvée' : 'Rejetée' ?>
                                    </span>
                                </td>
                                <td data-label="Traité par"><?= htmlspecialchars($demande['traite_par_nom'] ?? 'Non traité') ?></td>
                                <td data-label="Date traitement"><?= $demande['traite_le'] ? date('d/m/Y H:i', strtotime($demande['traite_le'])) : 'N/A' ?></td>
                                <td data-label="Actions">
                                    <?php if (!empty($demande['motif'])): ?>
                                    <button type="button" class="btn btn-sm btn-outline-info btn-icon me-1" 
                                            data-bs-toggle="tooltip" 
                                            title="Motif: <?= htmlspecialchars($demande['motif']) ?>">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <?php endif; ?>
                                    <?php if (!empty($demande['commentaire_admin'])): ?>
                                    <button type="button" class="btn btn-sm btn-outline-secondary btn-icon" 
                                            data-bs-toggle="tooltip" 
                                            title="Commentaire admin: <?= htmlspecialchars($demande['commentaire_admin']) ?>">
                                        <i class="bi bi-chat-text"></i>
                                    </button>
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
    <script>
        // Activer les tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);
        });
    </script>
</body>
</html>