<?php

session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Vérification de l'authentification
if (!isset($_SESSION['utilisateur'])) {
    header("Location: connection.php");
    exit();
}

require_once 'include/database.php';

// Gestion de l'ajout de flotte
$ajout_message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nd'], $_POST['id_statut'], $_POST['id_operateur'], $_POST['id_type_abonnement'], $_POST['CD_PROV'], $_POST['CD_ETAB'])) {
    $nd = trim($_POST['nd']);
    $id_statut = intval($_POST['id_statut']);
    $id_operateur = intval($_POST['id_operateur']);
    $id_type_abonnement = intval($_POST['id_type_abonnement']);
    $CD_PROV = intval($_POST['CD_PROV']);
    $CD_ETAB = trim($_POST['CD_ETAB']);
    if ($nd && $id_statut && $id_operateur && $id_type_abonnement && $CD_PROV && $CD_ETAB) {
        try {
            $stmt = $pdo->prepare("INSERT INTO all_flotte (nd, id_statut, id_operateur, id_type_abonnement, CD_PROV, CD_ETAB) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$nd, $id_statut, $id_operateur, $id_type_abonnement, $CD_PROV, $CD_ETAB])) {
                $ajout_message = '<div class="alert alert-success">Flotte ajoutée avec succès !</div>';
            } else {
                $ajout_message = '<div class="alert alert-danger">Erreur lors de l\'ajout de la flotte.</div>';
            }
        } catch (PDOException $e) {
            $ajout_message = '<div class="alert alert-danger">Erreur SQL : ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    } else {
        $ajout_message = '<div class="alert alert-warning">Veuillez remplir tous les champs du formulaire.</div>';
    }
}

// Initialisation des variables
$search = $_GET['search'] ?? '';
$statut_id = $_GET['statut'] ?? '';
$operateur_id = $_GET['operateur'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 15;
$total = 0;
$numeros = [];
$error = null;
$statuts = [];
$operateurs = [];

try {
    // Requête principale
    $query = "SELECT 
                f.nd, 
                s.libelle AS statut, 
                o.libelle AS operateur,
                ta.libelle AS type_abonnement,
                d.libelle_fr AS direction,
                e.NOM_ETABL AS etablissement,
                a.ppr, 
                a.nom, 
                a.prenom
              FROM all_flotte f
              JOIN r_statuts s ON f.id_statut = s.id_statut
              JOIN r_operateurs o ON f.id_operateur = o.id_operateur
              JOIN r_type_abonnement ta ON f.id_type_abonnement = ta.id_type_abonnement
              JOIN z_direction d ON f.CD_PROV = d.CD_PROV
              JOIN z_etab e ON CONVERT(f.CD_ETAB USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(e.CD_ETAB USING utf8mb4) COLLATE utf8mb4_unicode_ci
              LEFT JOIN affectation_flotte af ON f.nd = af.nd
              LEFT JOIN tb_agents a ON af.ppr = a.ppr
              WHERE 1=1";

    $params = [];
    
    // Filtres
    if (!empty($search)) {
        $query .= " AND (f.nd LIKE :search OR a.nom LIKE :search OR a.prenom LIKE :search OR e.NOM_ETABL LIKE :search)";
        $params[':search'] = "%$search%";
    }
    if (!empty($statut_id)) {
        $query .= " AND f.id_statut = :statut_id";
        $params[':statut_id'] = $statut_id;
    }
    if (!empty($operateur_id)) {
        $query .= " AND f.id_operateur = :operateur_id";
        $params[':operateur_id'] = $operateur_id;
    }

    // Comptage total
    $countQuery = "SELECT COUNT(*) FROM ($query) AS total";
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($params);
    $total = $stmt->fetchColumn();

    // Requête paginée
    $query .= " ORDER BY f.nd ASC LIMIT :offset, :perPage";
    $params[':offset'] = ($page - 1) * $perPage;
    $params[':perPage'] = $perPage;

    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue($key, $value, $paramType);
    }
    $stmt->execute();
    $numeros = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Récupération des filtres disponibles
    $statuts = $pdo->query("SELECT id_statut AS id, libelle FROM r_statuts ORDER BY libelle")->fetchAll();
    $operateurs = $pdo->query("SELECT id_operateur AS id, libelle AS nom FROM r_operateurs ORDER BY libelle")->fetchAll();

} catch (PDOException $e) {
    $error = "Erreur SQL: " . $e->getMessage();
    error_log("Erreur SQL dans flotte.php: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion de la flotte téléphonique</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #4f46e5;
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
        
        .filter-card {
            background-color: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
            border: none;
        }
        
        .filter-card .card-body {
            padding: 1.25rem;
        }
        
        .data-card {
            background-color: white;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            border: none;
            overflow: hidden;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table thead th {
            background-color: var(--primary-lighter);
            color: var(--primary-color);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            border-bottom-width: 1px;
            padding: 0.75rem 1rem;
        }
        
        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-top: 1px solid var(--border-color);
        }
        
        .table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .table tbody tr:hover {
            background-color: var(--light-color);
        }
        
        .badge-status {
            font-weight: 500;
            padding: 0.35rem 0.6rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
        }
        
        .badge-active {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }
        
        .badge-inactive {
            background-color: rgba(107, 114, 128, 0.1);
            color: var(--text-muted);
        }
        
        .badge-suspended {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
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
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: #4338ca;
            border-color: #4338ca;
        }
        
        .btn-outline-primary {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-outline-primary:hover {
            background-color: var(--primary-lighter);
            color: var(--primary-color);
        }
        
        .pagination .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .pagination .page-link {
            color: var(--primary-color);
            border: 1px solid var(--border-color);
            margin: 0 0.25rem;
            border-radius: 0.375rem;
        }
        
        .pagination .page-link:hover {
            background-color: var(--primary-lighter);
        }
        
        .empty-state {
            padding: 3rem 1rem;
            text-align: center;
        }
        
        .empty-state-icon {
            font-size: 3rem;
            color: var(--text-muted);
            margin-bottom: 1rem;
        }
        
        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background-color: var(--primary-lighter);
            color: var(--primary-color);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .form-control, .form-select {
            border: 1px solid var(--border-color);
            padding: 0.5rem 0.75rem;
            border-radius: 0.375rem;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
        @media (max-width: 768px) {
            .table-responsive {
                border-radius: 0.5rem;
            }
            
            .table thead {
                display: none;
            }
            
            .table tbody tr {
                display: block;
                margin-bottom: 1rem;
                border: 1px solid var(--border-color);
                border-radius: 0.5rem;
            }
            
            .table tbody td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0.75rem;
                border-top: none;
                border-bottom: 1px solid var(--border-color);
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

    <div class="container py-4">
        <!-- En-tête de page -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h4 mb-1 fw-semibold">
                        <i class="bi bi-phone me-2"></i>Gestion de la flotte téléphonique
                    </h1>
                    <p class="text-muted mb-0">Consultez et gérez les numéros de la flotte</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#ajoutFlotteModal">
                        <i class="bi bi-plus-circle me-1"></i> Ajouter une flotte
                    </button>
                    <?php if ($_SESSION['utilisateur']['role'] === 'admin'): ?>
                        <a href="affectation_form.php" class="btn btn-primary">
                            <i class="bi bi-person-plus me-1"></i> Nouvelle affectation
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

                <!-- Message d'ajout flotte -->
                <?php if ($ajout_message) echo $ajout_message; ?>

                <!-- Modal ajout flotte -->
                <div class="modal fade" id="ajoutFlotteModal" tabindex="-1" aria-labelledby="ajoutFlotteModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form action="" method="post">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="ajoutFlotteModalLabel">Ajouter une flotte</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label for="nd" class="form-label">Numéro ND</label>
                                        <input type="text" class="form-control" name="nd" id="nd" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="id_statut" class="form-label">Statut</label>
                                        <select class="form-select" name="id_statut" id="id_statut" required>
                                            <?php foreach ($statuts as $statut): ?>
                                                <option value="<?= $statut['id'] ?>"><?= htmlspecialchars($statut['libelle']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="id_operateur" class="form-label">Opérateur</label>
                                        <select class="form-select" name="id_operateur" id="id_operateur" required>
                                            <?php foreach ($operateurs as $operateur): ?>
                                                <option value="<?= $operateur['id'] ?>"><?= htmlspecialchars($operateur['nom']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="id_type_abonnement" class="form-label">Type abonnement</label>
                                        <input type="number" class="form-control" name="id_type_abonnement" id="id_type_abonnement" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="CD_PROV" class="form-label">Direction</label>
                                        <input type="number" class="form-control" name="CD_PROV" id="CD_PROV" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="CD_ETAB" class="form-label">Établissement</label>
                                        <input type="text" class="form-control" name="CD_ETAB" id="CD_ETAB" required>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                    <button type="submit" class="btn btn-success">Ajouter</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

        <!-- Modal modification flotte -->
        <div class="modal fade" id="modifierFlotteModal" tabindex="-1" aria-labelledby="modifierFlotteModalLabel" aria-hidden="true">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg border-0 rounded-4">
              <form id="modifierFlotteForm" action="modifier_flotte.php" method="post" autocomplete="off">
                <div class="modal-header bg-success text-white rounded-top-4">
                  <h5 class="modal-title" id="modifierFlotteModalLabel"><i class="bi bi-pencil-square me-2"></i>Modifier la flotte</h5>
                  <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                  <input type="hidden" name="nd" id="modif_nd">
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label for="modif_id_statut" class="form-label fw-semibold">Statut</label>
                      <select class="form-select form-select-lg" name="id_statut" id="modif_id_statut" required>
                        <?php foreach ($statuts as $statut): ?>
                          <option value="<?= $statut['id'] ?>"><?= htmlspecialchars($statut['libelle']) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-6">
                      <label for="modif_id_operateur" class="form-label fw-semibold">Opérateur</label>
                      <select class="form-select form-select-lg" name="id_operateur" id="modif_id_operateur" required>
                        <?php foreach ($operateurs as $operateur): ?>
                          <option value="<?= $operateur['id'] ?>"><?= htmlspecialchars($operateur['nom']) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-6">
                      <label for="modif_id_type_abonnement" class="form-label fw-semibold">Type abonnement</label>
                      <input type="number" class="form-control form-control-lg" name="id_type_abonnement" id="modif_id_type_abonnement" required>
                    </div>
                    <div class="col-md-6">
                      <label for="modif_CD_PROV" class="form-label fw-semibold">Direction</label>
                      <input type="number" class="form-control form-control-lg" name="CD_PROV" id="modif_CD_PROV" required>
                    </div>
                    <div class="col-12">
                      <label for="modif_CD_ETAB" class="form-label fw-semibold">Établissement</label>
                      <input type="text" class="form-control form-control-lg" name="CD_ETAB" id="modif_CD_ETAB" required>
                    </div>
                  </div>
                </div>
                <div class="modal-footer bg-light rounded-bottom-4">
                  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                  <button type="submit" class="btn btn-success">Enregistrer</button>
                </div>
              </form>
            </div>
          </div>
        </div>
        <script>
        var modifierModal = document.getElementById('modifierFlotteModal');
        modifierModal.addEventListener('show.bs.modal', function (event) {
          var button = event.relatedTarget;
          var nd = button.getAttribute('data-nd');
          document.getElementById('modif_nd').value = nd;
          fetch('get_flotte.php?nd=' + encodeURIComponent(nd))
            .then(response => response.json())
            .then(flotte => {
              document.getElementById('modif_id_statut').value = flotte.id_statut;
              document.getElementById('modif_id_operateur').value = flotte.id_operateur;
              document.getElementById('modif_id_type_abonnement').value = flotte.id_type_abonnement;
              document.getElementById('modif_CD_PROV').value = flotte.CD_PROV;
              document.getElementById('modif_CD_ETAB').value = flotte.CD_ETAB;
            });
        });
        </script>

        <!-- Filtres -->
        <div class="filter-card">
            <div class="card-body">
                <form method="get" class="row g-3">
                    <div class="col-md-4">
                        <label for="searchInput" class="form-label fw-semibold">
                            <i class="bi bi-search me-1"></i>Recherche
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" id="search" name="search" class="form-control" 
                                   placeholder="Numéro, nom, établissement..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                         <label for="filterStatus" class="form-label fw-semibold">
                            <i class="bi bi-circle-fill me-1"></i>Statut
                        </label>
                        <select id="statut" name="statut" class="form-select">
                            <option value="">Tous les statuts</option>
                            <?php foreach ($statuts as $statut): ?>
                                <option value="<?= $statut['id'] ?>" <?= $statut_id == $statut['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($statut['libelle']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="operateur" class="form-label fw-semibold">
                            <i class="bi bi-broadcast me-1"></i>Opérateur
                        </label>
                        <select id="operateur" name="operateur" class="form-select">
                            <option value="">Tous les opérateurs</option>
                            <?php foreach ($operateurs as $operateur): ?>
                               <option value="<?= $operateur['id'] ?>" <?= $operateur_id == $operateur['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($operateur['nom']) ?>
                               </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-funnel me-1"></i> Filtrer
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Message d'erreur -->
        <?php if ($error): ?>
            <div class="alert alert-danger mb-4"><?= $error ?></div>
        <?php endif; ?>

        <!-- Tableau des résultats -->
        <div class="data-card">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Numéro</th>
                            <th>Statut</th>
                            <th>Opérateur</th>
                            <th>Abonnement</th>
                            <th>Direction</th>
                            <th>Établissement</th>
                            <th>Affecté à</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($numeros)): ?>
                            <tr>
                                <td colspan="8" class="empty-state">
                                    <div class="empty-state-icon">
                                        <i class="bi bi-search"></i>
                                    </div>
                                    <h5 class="mb-2">Aucun numéro trouvé</h5>
                                    <p class="text-muted mb-3">Aucun résultat ne correspond à vos critères de recherche</p>
                                    <?php if (!empty($search) || !empty($statut_id) || !empty($operateur_id)): ?>
                                        <a href="flotte.php" class="btn btn-outline-primary">
                                            Réinitialiser les filtres
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($numeros as $numero): ?>
                                <tr>
                                    <td data-label="Numéro">
                                        <span class="fw-semibold"><?= htmlspecialchars($numero['nd']) ?></span>
                                    </td>
                                    <td data-label="Statut">
                                        <?php
                                        $badgeClass = match(strtolower($numero['statut'])) {
                                            'actif' => 'badge-status badge-active',
                                            'inactif' => 'badge-status badge-inactive',
                                            'suspendu' => 'badge-status badge-suspended',
                                            default => 'badge-status'
                                        };
                                        ?>
                                        <span class="<?= $badgeClass ?>">
                                            <?= htmlspecialchars($numero['statut']) ?>
                                        </span>
                                    </td>
                                    <td data-label="Opérateur"><?= htmlspecialchars($numero['operateur']) ?></td>
                                    <td data-label="Abonnement"><?= htmlspecialchars($numero['type_abonnement']) ?></td>
                                    <td data-label="Direction"><?= htmlspecialchars($numero['direction']) ?></td>
                                    <td data-label="Établissement"><?= htmlspecialchars($numero['etablissement'] ?? 'Non affecté') ?></td>
                                    <td data-label="Affecté à">
                                        <?php if (!empty($numero['ppr'])): ?>
                                            <div class="d-flex align-items-center">
                                                <span class="user-avatar">
                                                    <?= substr($numero['prenom'], 0, 1) . substr($numero['nom'], 0, 1) ?>
                                                </span>
                                                <div>
                                                    <div><?= htmlspecialchars($numero['prenom'] . ' ' . $numero['nom']) ?></div>
                                                    <small class="text-muted">PPR: <?= $numero['ppr'] ?></small>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">Non affecté</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Actions">
                                        <div class="d-flex gap-2">
                                            <a href="#" class="btn btn-sm btn-outline-success btn-icon" title="Modifier la flotte" data-bs-toggle="modal" data-bs-target="#modifierFlotteModal" data-nd="<?= htmlspecialchars($numero['nd']) ?>">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                            <a href="historique.php?nd=<?= urlencode($numero['nd']) ?>" class="btn btn-sm btn-outline-primary btn-icon" title="Historique" data-bs-toggle="tooltip">
                                                <i class="bi bi-clock-history"></i>
                                            </a>
                                            <?php if ($_SESSION['utilisateur']['role'] === 'admin'): ?>
                                                <a href="affectation_form.php?nd=<?= urlencode($numero['nd']) ?>" class="btn btn-sm btn-outline-primary btn-icon" title="Affecter" data-bs-toggle="tooltip">
                                                    <i class="bi bi-person-plus"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total > $perPage): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <!-- Précédent -->
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                    <?php endif; ?>

                    <!-- Première page -->
                    <?php if ($page > 3): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">1</a>
                        </li>
                        <?php if ($page > 4): ?>
                            <li class="page-item disabled">
                                <span class="page-link">...</span>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- Pages autour de la page courante -->
                    <?php 
                    $start = max(1, $page - 2);
                    $end = min(ceil($total / $perPage), $page + 2);
                    
                    for ($i = $start; $i <= $end; $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                <?= $i ?>
                            </a>
                        </li>
                    <?php endfor; ?>

                    <!-- Dernière page -->
                    <?php if ($page < ceil($total / $perPage) - 2): ?>
                        <?php if ($page < ceil($total / $perPage) - 3): ?>
                            <li class="page-item disabled">
                                <span class="page-link">...</span>
                            </li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => ceil($total / $perPage)])) ?>">
                                <?= ceil($total / $perPage) ?>
                            </a>
                        </li>
                    <?php endif; ?>

                    <!-- Suivant -->
                    <?php if ($page < ceil($total / $perPage)): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Activation des tooltips Bootstrap
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Adaptation pour les écrans mobiles
            if (window.innerWidth < 768) {
                document.querySelectorAll('tbody td').forEach(function(td) {
                    const label = td.getAttribute('data-label') || td.querySelector('th')?.textContent || '';
                    td.setAttribute('data-label', label);
                });
            }
        });
        
        // Remplissage des données dans le modal de modification
        document.addEventListener('show.bs.modal', function (event) {
          var button = event.relatedTarget; // Bouton qui a déclenché le modal
          var nd = button.getAttribute('data-nd'); // Récupération du ND
          
          // Remplissage des champs du modal avec les données correspondantes
          var modalNd = document.getElementById('modalNd');
          var modalIdStatut = document.getElementById('modalIdStatut');
          var modalIdOperateur = document.getElementById('modalIdOperateur');
          var modalIdTypeAbonnement = document.getElementById('modalIdTypeAbonnement');
          var modalCD_PROV = document.getElementById('modalCD_PROV');
          var modalCD_ETAB = document.getElementById('modalCD_ETAB');
          
          modalNd.value = nd;
          
          // Récupération des données via AJAX
          fetch('get_flotte.php?nd=' + encodeURIComponent(nd))
            .then(response => response.json())
            .then(data => {
              if (data) {
                modalIdStatut.value = data.id_statut;
                modalIdOperateur.value = data.id_operateur;
                modalIdTypeAbonnement.value = data.id_type_abonnement;
                modalCD_PROV.value = data.CD_PROV;
                modalCD_ETAB.value = data.CD_ETAB;
              }
            })
            .catch(error => console.error('Erreur:', error));
        });
    </script>
</body>
</html>