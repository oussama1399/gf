<?php
session_start();
require_once 'include/database.php';

// Vérification de l'authentification et des permissions
if (!isset($_SESSION['utilisateur']) || !in_array($_SESSION['utilisateur']['role'], ['admin', 'moderator'])) {
    header("Location: connection.php");
    exit();
}

// Traitement ajout établissement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Ajout établissement
    if ($_POST['action'] === 'ajouter') {
        try {
            $pdo->beginTransaction();
            $nom = trim($_POST['nom_etabl'] ?? '');
            $ville = trim($_POST['la_ville'] ?? '');
            $type = trim($_POST['typeEtab'] ?? '');
            if (empty($nom) || empty($ville) || empty($type)) {
                throw new Exception("Tous les champs sont obligatoires");
            }
            $code = generateEtabCode($nom, $ville);
            $stmt = $pdo->prepare("INSERT INTO z_etab (CD_ETAB, NOM_ETABL, LA_VILLE, typeEtab, Actif, DateModification) VALUES (?, ?, ?, ?, 1, NOW())");
            $stmt->execute([$code, $nom, $ville, $type]);
            $pdo->commit();
            $_SESSION['success'] = "Établissement ajouté avec succès";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Erreur : " . $e->getMessage();
        }
        header("Location: etablissement_liste.php");
        exit();
    }
    // Modification établissement
    if ($_POST['action'] === 'modifier' && isset($_POST['code_etab'])) {
        try {
            $pdo->beginTransaction();
            $code = $_POST['code_etab'];
            $nom = trim($_POST['nom_etabl_modif'] ?? '');
            $ville = trim($_POST['la_ville_modif'] ?? '');
            $type = trim($_POST['typeEtab_modif'] ?? '');
            if (empty($nom) || empty($ville) || empty($type)) {
                throw new Exception("Tous les champs sont obligatoires");
            }
            $stmt = $pdo->prepare("UPDATE z_etab SET NOM_ETABL = ?, LA_VILLE = ?, typeEtab = ?, DateModification = NOW() WHERE CD_ETAB = ?");
            $stmt->execute([$nom, $ville, $type, $code]);
            $pdo->commit();
            $_SESSION['success'] = "Établissement modifié avec succès";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Erreur : " . $e->getMessage();
        }
        header("Location: etablissement_liste.php");
        exit();
    }
    // Toggle statut via AJAX
    if ($_POST['action'] === 'toggle_status' && isset($_POST['id'], $_POST['status'])) {
        $id = $_POST['id'];
        $status = $_POST['status'] == '1' ? 1 : 0;
        $stmt = $pdo->prepare("UPDATE z_etab SET Actif = ? WHERE CD_ETAB = ?");
        $success = $stmt->execute([$status, $id]);
        header('Content-Type: application/json');
        echo json_encode(['success' => $success]);
        exit();
    }
}

// Récupérer les établissements avec pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Requête avec pagination et comptage
$total = $pdo->query("SELECT COUNT(*) FROM z_etab")->fetchColumn();
$etablissements = $pdo->prepare("SELECT * FROM z_etab ORDER BY NOM_ETABL LIMIT :limit OFFSET :offset");
$etablissements->bindValue(':limit', $perPage, PDO::PARAM_INT);
$etablissements->bindValue(':offset', $offset, PDO::PARAM_INT);
$etablissements->execute();
$etablissements = $etablissements->fetchAll();

// Fonction pour générer un code établissement
function generateEtabCode($nom, $ville) {
    $prefix = substr(strtoupper($ville), 0, 3);
    $suffix = substr(strtoupper(preg_replace('/[^A-Z]/', '', $nom)), 0, 3);
    return $prefix . $suffix . rand(100, 999);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des établissements</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4/bootstrap-4.css">
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
        
        .search-box {
            position: relative;
        }
        
        .search-box input {
            padding-left: 2.5rem;
            border-radius: 0.375rem;
            border: 1px solid var(--border-color);
        }
        
        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }
        
        .code-cell {
            font-weight: 600;
            color: var(--primary-color);
            font-family: 'Consolas', 'Monaco', monospace;
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

    <div class="container my-4">
        <!-- En-tête de page -->
        <div class="page-header">
             <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h4 mb-1 fw-semibold">
                        <i class="bi bi-building me-2"></i>Gestion des Etablissements
                    </h1>
                    <p class="text-muted mb-0">Consultez et gérez les établissements </p>
                </div>
                <div>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEtabModal">
                        <i class="bi bi-plus-circle me-2"></i>Nouvel Établissement
                    </button>
                </div>
            </div>
        </div>
        <!-- Filtres et recherche -->
        <div class="filter-card card">
            <div class="card-body">
                <div class="row align-items-end">
                    <div class="col-md-4">
                        <label for="searchInput" class="form-label fw-semibold">
                            <i class="bi bi-search me-1"></i>Recherche
                        </label>
                        <div class="search-box">
                            <i class="bi bi-search"></i>
                            <input type="text" id="searchInput" class="form-control" placeholder="Rechercher un établissement...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label for="filterType" class="form-label fw-semibold">
                            <i class="bi bi-tag me-1"></i>Type
                        </label>
                        <select class="form-select" id="filterType">
                            <option value="">Tous les types</option>
                            <option value="PUBLIC">Public</option>
                            <option value="PRIVE">Privé</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="filterStatus" class="form-label fw-semibold">
                            <i class="bi bi-circle-fill me-1"></i>Statut
                        </label>
                        <select class="form-select" id="filterStatus">
                            <option value="">Tous les statuts</option>
                            <option value="1">Actif</option>
                            <option value="0">Inactif</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <div class="d-flex gap-2">
                            <button type="button" id="filterBtn" class="btn btn-primary">
                                <i class="bi bi-funnel me-1"></i>Filtrer
                            </button>
                            <button type="button" id="resetBtn" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-clockwise"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Messages d'alerte -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= $_SESSION['success'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= $_SESSION['error'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Tableau des établissements -->
        <div class="data-card card">
            <div class="table-responsive">
                <table class="table table-hover" id="etablissementsTable">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Nom</th>
                            <th>Ville</th>
                            <th>Type</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($etablissements as $etab): ?>
                            <tr>
                                <td data-label="Code"><span class="code-cell"><?= htmlspecialchars($etab['CD_ETAB']) ?></span></td>
                                <td data-label="Nom"><?= htmlspecialchars($etab['NOM_ETABL']) ?></td>
                                <td data-label="Ville"><?= htmlspecialchars($etab['LA_VILLE'] ?? 'N/A') ?></td>
                                <td data-label="Type"><?= htmlspecialchars($etab['typeEtab'] ?? 'N/A') ?></td>
                                <td data-label="Statut">
                                    <span class="badge-status <?= $etab['Actif'] ? 'badge-active' : 'badge-inactive' ?>">
                                        <?= $etab['Actif'] ? 'Actif' : 'Inactif' ?>
                                    </span>
                                </td>
                                <td data-label="Actions">
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-sm btn-outline-primary btn-icon" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editEtabModal"
                                                data-code="<?= htmlspecialchars($etab['CD_ETAB']) ?>"
                                                data-nom="<?= htmlspecialchars($etab['NOM_ETABL']) ?>"
                                                data-ville="<?= htmlspecialchars($etab['LA_VILLE'] ?? '') ?>"
                                                data-type="<?= htmlspecialchars($etab['typeEtab'] ?? '') ?>"
                                                title="Modifier">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-secondary btn-icon toggle-status" 
                                                data-id="<?= htmlspecialchars($etab['CD_ETAB']) ?>"
                                                data-status="<?= $etab['Actif'] ?>"
                                                data-bs-toggle="tooltip"
                                                title="<?= $etab['Actif'] ? 'Désactiver' : 'Activer' ?>">
                                            <i class="bi bi-power"></i>
                                        </button>
                                        <a href="supprimer_etablissement.php?id=<?= urlencode($etab['CD_ETAB']) ?>" 
                                           class="btn btn-sm btn-outline-danger btn-icon" 
                                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet établissement ?');"
                                           data-bs-toggle="tooltip"
                                           title="Supprimer">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total > $perPage): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page - 1 ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= ceil($total / $perPage); $i++): ?>
                        <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($page < ceil($total / $perPage)): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $page + 1 ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

    <!-- Modal d'ajout -->
    <div class="modal fade" id="addEtabModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="action" value="ajouter">
                    <div class="modal-header">
                        <h5 class="modal-title">Ajouter un établissement</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nom de l'établissement</label>
                            <input type="text" name="nom_etabl" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Ville</label>
                            <input type="text" name="la_ville" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Type</label>
                            <select name="typeEtab" class="form-select" required>
                                <option value="">Sélectionner un type</option>
                                <option value="PUBLIC">Public</option>
                                <option value="PRIVE">Privé</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de modification -->
    <div class="modal fade" id="editEtabModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <input type="hidden" name="action" value="modifier">
                    <input type="hidden" name="code_etab" id="editCodeEtab">
                    <div class="modal-header">
                        <h5 class="modal-title">Modifier l'établissement</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Nom de l'établissement</label>
                            <input type="text" name="nom_etabl_modif" id="editNomEtab" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Ville</label>
                            <input type="text" name="la_ville_modif" id="editVilleEtab" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Type</label>
                            <select name="typeEtab_modif" id="editTypeEtab" class="form-select" required>
                                <option value="">Sélectionner un type</option>
                                <option value="PUBLIC">Public</option>
                                <option value="PRIVE">Privé</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Fonction de filtrage
            function filterTable() {
                var searchInput = document.getElementById('searchInput').value.toLowerCase();
                var type = document.getElementById('filterType').value;
                var status = document.getElementById('filterStatus').value;
                var rows = document.querySelectorAll('#etablissementsTable tbody tr');
                
                rows.forEach(function(row) {
                    var rowText = row.textContent.toLowerCase();
                    var rowType = row.cells[3].textContent;
                    var rowStatus = row.cells[4].querySelector('.badge-status').textContent.trim();
                    
                    var searchMatch = !searchInput || rowText.includes(searchInput);
                    var typeMatch = !type || rowType.includes(type);
                    var statusMatch = !status || 
                        (status === '1' && rowStatus === 'Actif') || 
                        (status === '0' && rowStatus === 'Inactif');
                    
                    row.style.display = (searchMatch && typeMatch && statusMatch) ? '' : 'none';
                });
            }

            // Événements de filtrage
            document.getElementById('searchInput').addEventListener('keyup', filterTable);
            document.getElementById('filterType').addEventListener('change', filterTable);
            document.getElementById('filterStatus').addEventListener('change', filterTable);
            document.getElementById('filterBtn').addEventListener('click', filterTable);

            // Réinitialisation des filtres
            document.getElementById('resetBtn').addEventListener('click', function() {
                document.getElementById('searchInput').value = '';
                document.getElementById('filterType').value = '';
                document.getElementById('filterStatus').value = '';
                
                var rows = document.querySelectorAll('#etablissementsTable tbody tr');
                rows.forEach(function(row) {
                    row.style.display = '';
                });
            });

            // Toggle statut
            document.querySelectorAll('.toggle-status').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const currentStatus = this.getAttribute('data-status');
                    const newStatus = currentStatus === '1' ? '0' : '1';
                    
                    Swal.fire({
                        title: 'Confirmation',
                        text: `Voulez-vous vraiment ${newStatus === '1' ? 'activer' : 'désactiver'} cet établissement ?`,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Confirmer',
                        cancelButtonText: 'Annuler'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            fetch('', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/x-www-form-urlencoded',
                                },
                                body: `action=toggle_status&id=${id}&status=${newStatus}`
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    location.reload();
                                } else {
                                    Swal.fire('Erreur', 'Une erreur est survenue', 'error');
                                }
                            });
                        }
                    });
                });
            });

            // Pré-remplir le modal de modification
            var editModal = document.getElementById('editEtabModal');
            editModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                var code = button.getAttribute('data-code');
                var nom = button.getAttribute('data-nom');
                var ville = button.getAttribute('data-ville');
                var type = button.getAttribute('data-type');
                document.getElementById('editCodeEtab').value = code;
                document.getElementById('editNomEtab').value = nom;
                document.getElementById('editVilleEtab').value = ville;
                document.getElementById('editTypeEtab').value = type;
            });
        });
    </script>
</body>
</html>