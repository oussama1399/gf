<?php
session_start();
require_once 'include/database.php';

// Vérification de l'authentification
if (!isset($_SESSION['utilisateur'])) {
    header("Location: connection.php");
    exit();
}

// Récupération de l'historique avec votre structure de table
$query = $pdo->query("
    SELECT 
        h.id_hf,
        h.ppr,
        h.nom,
        h.prenom,
        h.lib_etab,
        h.lib_fonction,
        h.date_affectation,
        h.date_session,
        h.statut_nd,
        h.CD_PROV
    FROM historique_flotte h
    ORDER BY COALESCE(h.date_session, h.date_affectation) DESC
");
$historique = $query->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique des affectations</title>
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
        
        .badge-warning {
            background-color: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: #4338ca;
            border-color: #4338ca;
        }
        
        .btn-success {
            background-color: var(--success-color);
            border-color: var(--success-color);
        }
        
        .btn-success:hover {
            background-color: #059669;
            border-color: #059669;
        }
        
        .ppr-text {
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 0.8rem;
            color: var(--text-muted);
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
                        <i class="bi bi-clock-history me-2"></i>Historique des Affectations
                    </h1>
                    <p class="text-muted mb-0">Consultez l'historique complet des affectations et désaffectations</p>
                </div>
                <div>
                    <button class="btn btn-success" onclick="exportToExcel()">
                        <i class="bi bi-file-earmark-excel me-2"></i>Exporter Excel
                    </button>
                </div>
            </div>
        </div>

        <!-- Tableau de l'historique -->
        <div class="data-card card">
            <div class="table-responsive">
                <table class="table table-hover" id="tableHistorique">
                    <thead>
                        <tr>
                            <th>Date Affectation</th>
                            <th>Date Désaffectation</th>
                            <th>Agent</th>
                            <th>Établissement</th>
                            <th>Fonction</th>
                            <th>Statut ND</th>
                        </tr>
                    </thead>
                    <tbody>
                            <?php foreach ($historique as $entry): ?>
                            <tr>
                                <td data-label="Date Affectation"><?= $entry['date_affectation'] ? date('d/m/Y', strtotime($entry['date_affectation'])) : 'N/A' ?></td>
                                <td data-label="Date Désaffectation"><?= $entry['date_session'] ? date('d/m/Y', strtotime($entry['date_session'])) : '-' ?></td>
                                <td data-label="Agent">
                                    <strong><?= htmlspecialchars($entry['nom']) ?> <?= htmlspecialchars($entry['prenom']) ?></strong>
                                    <div class="ppr-text">PPR: <?= htmlspecialchars($entry['ppr']) ?></div>
                                </td>
                                <td data-label="Établissement"><?= htmlspecialchars($entry['lib_etab']) ?></td>
                                <td data-label="Fonction"><?= htmlspecialchars($entry['lib_fonction']) ?></td>
                                <td data-label="Statut ND">
                                    <?php if ($entry['statut_nd']): ?>
                                    <span class="badge-status 
                                        <?= match(strtolower($entry['statut_nd'])) {
                                            'actif' => 'badge-active',
                                            'suspendu' => 'badge-warning',
                                            'inactif' => 'badge-inactive',
                                            default => 'badge-inactive'
                                        } ?>">
                                        <?= htmlspecialchars($entry['statut_nd']) ?>
                                    </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.sheetjs.com/xlsx-0.19.3/package/dist/xlsx.full.min.js"></script>
    <script>
    function exportToExcel() {
        const table = document.getElementById('tableHistorique');
        const wb = XLSX.utils.table_to_book(table);
        XLSX.writeFile(wb, 'historique_affectations.xlsx');
    }
    </script>
</body>
</html>