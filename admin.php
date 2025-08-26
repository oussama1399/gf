<?php
session_start();

// Vérification de l'authentification et du rôle admin
if (!isset($_SESSION['utilisateur']) || $_SESSION['utilisateur']['role'] !== 'admin') {
    header("Location: connection.php");
    exit();
}

require_once 'include/database.php';
require_once 'auth.php';

error_log("Fonction de l'utilisateur: " . ($_SESSION['utilisateur']['fonction'] ?? 'Non définie'));

// Requêtes des statistiques intégrées
$nbND = $pdo->query("SELECT COUNT(*) FROM all_flotte")->fetchColumn();
$nbUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$nbEtab = $pdo->query("SELECT COUNT(*) FROM z_etab")->fetchColumn();
$nbSuspendus = $pdo->query("
    SELECT COUNT(nd)
    FROM all_flotte
    WHERE id_statut = (
        SELECT id_statut FROM r_statuts WHERE libelle = 'suspendu' LIMIT 1
    )
")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin</title>
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
            color: var(--dark);
            font-family: 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
        }

        .dashboard-header {
            background: linear-gradient(135deg, #5c6bc0 0%, #3949ab 100%);
            color: white;
            padding: 2.5rem 0;
            margin-bottom: 2.5rem;
            border-radius: 0;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .dashboard-title {
            font-weight: 600;
            letter-spacing: -0.25px;
            margin-bottom: 0.75rem;
            font-size: 2rem;
        }

        .dashboard-subtitle {
            font-weight: 400;
            opacity: 0.9;
            font-size: 1.1rem;
        }

        .stat-card {
            border: none;
            border-radius: 10px;
            overflow: hidden;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            box-shadow: var(--card-shadow);
            background: white;
            border-top: 4px solid transparent;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--card-hover);
        }

        .stat-card-primary {
            border-top-color: var(--primary);
        }

        .stat-card-success {
            border-top-color: var(--secondary);
        }

        .stat-card-info {
            border-top-color: var(--info);
        }

        .stat-card-warning {
            border-top-color: var(--warning);
        }

        .stat-card-danger {
            border-top-color: var(--danger);
        }

        .stat-icon {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            padding: 0.75rem;
            border-radius: 12px;
            width: 54px;
            height: 54px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: auto;
            margin-right: auto;
        }

        .stat-icon-primary {
            background-color: rgba(92, 107, 192, 0.1);
            color: var(--primary);
        }

        .stat-icon-success {
            background-color: rgba(102, 187, 106, 0.1);
            color: var(--secondary);
        }

        .stat-icon-info {
            background-color: rgba(66, 165, 245, 0.1);
            color: var(--info);
        }

        .stat-icon-warning {
            background-color: rgba(255, 167, 38, 0.1);
            color: var(--warning);
        }

        .stat-icon-danger {
            background-color: rgba(239, 83, 80, 0.1);
            color: var(--danger);
        }

        .card-title {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-size: 1.1rem;
        }

        .card-text {
            color: var(--gray);
            font-size: 0.85rem;
            margin-bottom: 1.25rem;
            line-height: 1.5;
        }

        .btn-access {
            border-radius: 6px;
            padding: 0.5rem 1.25rem;
            font-weight: 500;
            transition: all 0.2s;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            border: 1px solid rgba(0, 0, 0, 0.05);
            height: 300px; /* Hauteur fixe pour le conteneur du graphique */
        }

        .chart-title {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 1.5rem;
            font-size: 1.1rem;
        }

        .section-title {
            font-weight: 600;
            color: var(--dark);
            margin: 2rem 0 1.5rem;
            font-size: 1.25rem;
            position: relative;
            padding-bottom: 0.5rem;
        }

        .section-title:after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 50px;
            height: 3px;
            background: var(--primary);
            border-radius: 3px;
        }

        @media (max-width: 768px) {
            .dashboard-header {
                padding: 1.75rem 0;
            }
            
            .dashboard-title {
                font-size: 1.75rem;
            }
            
            .stat-card {
                margin-bottom: 1.25rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'include/nav.php'; ?>

    <div class="dashboard-header">
        <div class="container">
            <h1 class="dashboard-title">Tableau de bord Administrateur</h1>
            <p class="dashboard-subtitle">Gestion complète de votre flotte téléphonique</p>
        </div>
    </div>

    <div class="container mb-5">
        <div class="chart-container">
            <h5 class="chart-title">Statistiques globales</h5>
            <canvas id="statsChart"></canvas>
        </div>

        <h5 class="section-title">Gestion Principale</h5>
        <div class="row g-4">
            <div class="col-md-6 col-lg-3">
                <div class="stat-card stat-card-primary h-100 p-4 text-center">
                    <div class="stat-icon stat-icon-primary">
                       <i class="bi bi-people-fill fs-2 text-primary mb-3"></i>
                    </div>
                    <h5 class="card-title">Affectations</h5>
                    <p class="card-text">Gérer les affectations des utilisateurs</p>
                    <a href="affectation_form.php" class="btn btn-access btn-primary">Accéder</a>
                </div>
            </div>

           <div class="col-md-6 col-lg-3">
              <div class="stat-card stat-card-info h-100 p-4 text-center">
                <div class="stat-icon stat-icon-info">
                    <i class="bi bi-person-fill fs-2 text-info mb-3"></i>
                </div>
                <h5 class="card-title">Agents</h5>
                <p class="card-text" style="margin-bottom: 2.5rem;">Ajouter et modifier des agents</p>
                <a href="ajouter_agent.php" class="btn btn-access btn-info">Accéder</a>
              </div>
           </div>
           
          
            <div class="col-md-6 col-lg-3">
                    <div class="stat-card stat-card-success h-100 p-4 text-center">
                        <div class="stat-icon stat-icon-success">
                            <i class="bi bi-people"></i>
                        </div>
                         <h5 class="card-title">Utilisateurs</h5>
                        <p class="card-text">Gestion des comptes utilisateurs et permissions</p>
                        <a href="utilisateur.php" class="btn btn-access btn-success">Accéder</a>
                    </div>
            </div>
          

            <div class="col-md-6 col-lg-3">
                <div class="stat-card stat-card-warning h-100 p-4 text-center">
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning rounded-circle mb-3 mx-auto" style="width:54px;height:54px;display:flex;align-items:center;justify-content:center;">
                        <i class="bi bi-x-circle fs-4"></i>
                    </div>
                    <h5 class="card-title">Désaffectation</h5>
                    <p class="card-text">Retirer l'affectation d'un ND à un agent</p>
                    <a href="desaffectation_form.php" class="btn btn-warning btn-sm px-3" style="font-size:1rem;min-width:80px;height:38px;display:inline-flex;align-items:center;justify-content:center;">
                         ACCÉDER
                    </a> 
                </div>
            </div>
        </div>

        <h5 class="section-title">Modules complémentaires</h5>
        <div class="row g-4">
            <div class="col-md-6">
                <div class="stat-card stat-card-danger h-100 p-4 text-center">
                    <div class="stat-icon stat-icon-danger">
                        <i class="bi bi-credit-card"></i>
                    </div>
                    <h5 class="card-title">Demandes Carte</h5>
                    <p class="card-text">Gestion des demandes de cartes SIM</p>
                    <a href="demande_carte.php" class="btn btn-access btn-danger">Accéder</a>
                </div>
            </div>

            
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const ctx = document.getElementById('statsChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['ND actifs', 'Utilisateurs', 'Etablissements', 'ND suspendus'],
                datasets: [{
                    label: 'Statistiques',
                    data: [<?= $nbND ?>, <?= $nbUsers ?>, <?= $nbEtab ?>, <?= $nbSuspendus ?>],
                    backgroundColor: [
                        'rgba(92, 107, 192, 0.7)',
                        'rgba(102, 187, 106, 0.7)',
                        'rgba(66, 165, 245, 0.7)',
                        'rgba(239, 83, 80, 0.7)'
                    ],
                    borderColor: [
                        'rgba(92, 107, 192, 1)',
                        'rgba(102, 187, 106, 1)',
                        'rgba(66, 165, 245, 1)',
                        'rgba(239, 83, 80, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>