<?php
session_start();
require_once 'include/database.php';

// Vérification de l'authentification et du rôle modérateur
if (!isset($_SESSION['utilisateur']) ){
    header("Location: connection.php");
    exit();
}

// Définition de la province (Tiznit)
define('PROVINCE_TIZNIT', 581);
$user = $_SESSION['utilisateur'];

// Récupération des données
try {
    // 1️⃣ Statistiques des ND
    $stmt = $pdo->query("SELECT COUNT(*) as total_nd FROM all_flotte");
    
    $total_nd = $stmt->fetch()['total_nd'];

    // ND par statut
    $stmt = $pdo->query("
        SELECT s.libelle, COUNT(*) as count 
        FROM all_flotte af
        JOIN r_statuts s ON af.id_statut = s.id_statut 
        GROUP BY s.libelle
    ");
    $nd_par_statut = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // 2️⃣ Affectations récentes (5 dernières)
    $stmt = $pdo->query("
        SELECT af.nd, a.nom, a.prenom, e.NOM_ETABL, f.libelle_fr as fonction , af.date_affectation
        FROM affectation_flotte af
        JOIN tb_agents a ON af.ppr = a.ppr
        JOIN z_etab e ON a.CD_ETAB = e.CD_ETAB
        JOIN r_fonction f ON af.id_fonction = f.id_fonction
        ORDER BY af.date_affectation DESC
        LIMIT 5
    ");
    $recentes_affectations = $stmt->fetchAll();

    // 4️⃣ Établissements avec ND actifs
    $stmt = $pdo->prepare("
        SELECT e.CD_ETAB, e.NOM_ETABL, COUNT(af.id_aff) as nb_nd_actifs
        FROM z_etab e
        LEFT JOIN tb_agents a ON e.CD_ETAB = a.CD_ETAB
        LEFT JOIN affectation_flotte af ON a.ppr = af.ppr AND af.id_statut = 1
        GROUP BY e.CD_ETAB
        ORDER BY e.NOM_ETABL
        LIMIT 5
    ");
    $stmt->execute();
    $etablissements = $stmt->fetchAll();

    // Statistiques pour le graphique par type d'abonnement
    $statsAbonnement = $pdo->query("SELECT ta.libelle, COUNT(*) as total FROM all_flotte f JOIN r_type_abonnement ta ON f.id_type_abonnement = ta.id_type_abonnement GROUP BY ta.libelle ORDER BY total DESC")->fetchAll(PDO::FETCH_ASSOC);
    $labelsAbonnement = array_map(fn($row) => $row['libelle'], $statsAbonnement);
    $dataAbonnement = array_map(fn($row) => $row['total'], $statsAbonnement);

} catch (PDOException $e) {
    $error_message = "Erreur lors de la récupération des données: " . $e->getMessage();
    $total_nd = 0;
    $nd_par_statut = ['actif' => 0, 'inactif' => 0, 'suspendu' => 0];
    $recentes_affectations = [];
    $etablissements = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Modérateur - Gestion Flotte Tiznit</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary: #5c6bc0;
            --primary-light: #7986cb;
            --secondary: #66bb6a;
            --info: #42a5f5;
            --warning: #ffa726;
            --danger: #ef5350;
            --dark: #37474f;
            --light: #f5f5f5;
            --gray: #90a4ae;
            --card-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            --card-hover: 0 8px 20px rgba(0, 0, 0, 0.12);
        }

        body {
            background-color: #fafafa;
            font-family: 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
        }

        .dashboard-header {
            background: linear-gradient(135deg, #5c6bc0 0%, #3949ab 100%);
            color: white;
            padding: 2.5rem 0;
            margin-bottom: 2.5rem;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .stat-card {
            border: none;
            border-radius: 10px;
            transition: all 0.3s ease;
            box-shadow: var(--card-shadow);
            background: white;
            border-top: 4px solid transparent;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--card-hover);
        }

        .stat-card-primary { border-top-color: var(--primary); }
        .stat-card-success { border-top-color: var(--secondary); }
        .stat-card-warning { border-top-color: var(--warning); }
        .stat-card-danger { border-top-color: var(--danger); }

        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            height: 300px;
        }

        .activity-item {
            background: #f3f4f6;
            border-radius: 0.75rem;
            margin-bottom: 0.7rem;
            padding: 1rem;
            transition: background 0.2s;
        }

        @media (max-width: 768px) {
            .dashboard-header { padding: 1.75rem 0; }
            .stat-card { margin-bottom: 1.25rem; }
        }
    </style>
</head>
<body>
    <?php include 'include/nav.php'; ?>

    <div class="dashboard-header">
        <div class="container">
            <h1 class="display-4 fw-bold">Tableau de bord</h1>
            <p class="lead">Gestion de la flotte téléphonique - Direction Provinciale de Tiznit</p>
        </div>
    </div>

    <div class="container mb-5">
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <?= htmlspecialchars($error_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistiques principales -->
        <div class="row g-4 mb-4">
            <div class="col-md-6 col-lg-3">
                <div class="stat-card stat-card-primary h-100 p-4 text-center">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary rounded-circle mb-3 mx-auto" style="width:54px;height:54px;display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-phone fs-4"></i>
                    </div>
                    <h5 class="card-title">ND Disponibles</h5>
                    <p class="card-text text-muted">Numéros sans agent</p>
                    <div class="display-4 fw-bold">
                        <?php
                            $stmt = $pdo->query("SELECT COUNT(*) as dispo FROM all_flotte af LEFT JOIN affectation_flotte a ON af.nd = a.nd WHERE a.nd IS NULL");
                            echo number_format($stmt->fetch()['dispo']);
                        ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-3">
                <div class="stat-card stat-card-success h-100 p-4 text-center">
                    <div class="stat-icon bg-success bg-opacity-10 text-success rounded-circle mb-3 mx-auto" style="width:54px;height:54px;display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-check-circle fs-4"></i>
                    </div>
                    <h5 class="card-title">ND Actifs</h5>
                    <p class="card-text text-muted">Numéros avec agent</p>
                    <div class="display-4 fw-bold">
                        <?php
                            $stmt = $pdo->query("SELECT COUNT(DISTINCT nd) as actifs FROM affectation_flotte");
                            echo number_format($stmt->fetch()['actifs']);
                        ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-3">
                <div class="stat-card stat-card-warning h-100 p-4 text-center">
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning rounded-circle mb-3 mx-auto" style="width:54px;height:54px;display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-exclamation-triangle fs-4"></i>
                    </div>
                    <h5 class="card-title">ND Suspendus</h5>
                    <p class="card-text text-muted">Numéros inactifs temporaires</p>
                    <div class="display-4 fw-bold"><?= number_format($nd_par_statut['suspendu'] ?? 0) ?></div>
                </div>
            </div>
            
            <div class="col-md-6 col-lg-3">
                <div class="stat-card stat-card-danger h-100 p-4 text-center">
                    <div class="stat-icon bg-danger bg-opacity-10 text-danger rounded-circle mb-3 mx-auto" style="width:54px;height:54px;display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-building fs-4"></i>
                    </div>
                    <h5 class="card-title">Établissements</h5>
                    <p class="card-text text-muted">Total établissements</p>
                    <div class="display-4 fw-bold">
                        <?php
                            $stmt = $pdo->query("SELECT COUNT(*) as total_etab FROM z_etab");
                            echo number_format($stmt->fetch()['total_etab']);
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions Rapides -->
        <h5 class="section-title">Actions Rapides</h5>
        <div class="row g-4">
            <div class="col-md-6 col-lg-3">
                <div class="stat-card stat-card-primary h-100 p-4 text-center">
                          <div class="stat-icon bg-primary bg-opacity-10 text-primary rounded-circle mb-3 mx-auto" style="width:54px;height:54px;display:flex;align-items:center;justify-content:center;">
                              <i class="bi bi-people-fill fs-4 "></i>
                          </div>
                    <h5 class="card-title">Affectations</h5>
                    <p class="card-text">Gérer les affectations des ND</p>
                    <a href="affectation_form.php" class="btn btn-access btn-primary">ACCÉDER</a>
                </div>
            </div>

           
           
          

            <div class="col-md-6 col-lg-3">
                <div class="stat-card stat-card-warning h-100 p-4 text-center">
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning rounded-circle mb-3 mx-auto" style="width:54px;height:54px;display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-x-circle fs-4"></i>
                    </div>
                    <h5 class="card-title">Désaffectation</h5>
                    <p class="card-text">Retirer l'affectation d'un ND</p>
                    <a href="desaffectation_form.php" class="btn btn-warning btn-sm px-3" style="font-size:1rem;min-width:80px;height:38px;display:inline-flex;align-items:center;justify-content:center;">
                         ACCÉDER
                    </a> 
                </div>
            </div>
                        <div class="col-md-6 col-lg-3">
                            <div class="stat-card stat-card-danger h-100 p-4 text-center">
                                <div class="stat-icon bg-danger bg-opacity-10 text-danger rounded-circle mb-3 mx-auto" style="width:54px;height:54px;display:flex;align-items:center;justify-content:center;">
                                        <i class="bi bi-exclamation-octagon fs-4"></i>
                                </div>
                                <h5 class="card-title">Déclarer Perte</h5>
                                <p class="card-text">Déclarer la perte d'une carte SIM<br>
                                <a href="perte.php" class="btn btn-danger btn-sm px-3" style="font-size:1rem;min-width:80px;height:38px;display:inline-flex;align-items:center;justify-content:center;">
                                         ACCÉDER
                                </a>
                            </div>
                     </div>
        </div>

            

        <!-- Dernières activités -->
        <div class="row mt-5">
            <div class="col-lg-6 mb-4">
                <div class="stat-card h-100">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center border-0">
                        <h5 class="mb-0"><i class="bi bi-activity me-2"></i>Dernières Affectations</h5>
                        <a href="historique.php" class="btn btn-sm btn-outline-primary">Voir tout</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentes_affectations)): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                                <p class="mt-2">Aucune affectation récente</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recentes_affectations as $affectation): ?>
                                <div class="activity-item d-flex align-items-center">
                                    <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center" style="width:40px;height:40px;">
                                        <i class="bi bi-check-circle"></i>
                                    </div>
                                    <div class="ms-3 flex-grow-1">
                                        <h6 class="mb-1"><?= htmlspecialchars($affectation['prenom'] . ' ' . htmlspecialchars($affectation['nom'])) ?></h6>
                                        <p class="mb-1 small text-muted">
                                            <?= htmlspecialchars($affectation['NOM_ETABL']) ?> - <?= htmlspecialchars($affectation['fonction']) ?>
                                        </p>
                                        <div class="d-flex justify-content-between small">
                                            <span class="text-primary">
                                                <i class="bi bi-phone me-1"></i>
                                                <?= htmlspecialchars($affectation['nd']) ?>
                                            </span>
                                            <span class="text-success">
                                                <i class="bi bi-clock me-1"></i>
                                                <?= date('d/m/Y', strtotime($affectation['date_affectation'])) ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-6 mb-4">
                <div class="stat-card h-100">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0"><i class="bi bi-pie-chart me-2"></i>Répartition par type d'abonnement</h5>
                    </div>
                    <div class="card-body d-flex justify-content-center align-items-center" style="min-height:320px;">
                        <canvas id="abonnementChart" width="320" height="320" style="max-width:320px;max-height:320px;"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var ctx = document.getElementById('abonnementChart').getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode($labelsAbonnement) ?>,
                    datasets: [{
                        data: <?= json_encode($dataAbonnement) ?>,
                        backgroundColor: [
                            '#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#818cf8', '#e0e7ff', '#6366f1', '#f472b6'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'bottom' },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = Math.round((value / total) * 100);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        });
        </script>

        <!-- Établissements -->
        <div class="stat-card mb-4">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0"><i class="bi bi-building me-2"></i>Établissements de Tiznit</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Nom</th>
                                <th>Code</th>
                                <th class="text-center">ND Actifs</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($etablissements)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">
                                        Aucun établissement trouvé
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($etablissements as $etablissement): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($etablissement['NOM_ETABL']) ?></td>
                                        <td><?= htmlspecialchars($etablissement['CD_ETAB']) ?></td>
                                        <td class="text-center">
                                            <span class="badge bg-primary rounded-pill"><?= $etablissement['nb_nd_actifs'] ?></span>
                                        </td>
                                        <td>
                                            <a href="modifier_etablissement.php?id=<?= $etablissement['CD_ETAB'] ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-pencil"></i> Modifier
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-end mt-3">
                    <a href="etablissement_liste.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Voir tout
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>