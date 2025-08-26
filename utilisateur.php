<?php
session_start();
require_once 'include/database.php';
require_once 'resetfunc.php';

// Vérifier si l'utilisateur est connecté et est admin
if (!isset($_SESSION['utilisateur']) || $_SESSION['utilisateur']['role'] !== 'admin') {
    header("Location: connection.php");
    exit();
}

$error_message = '';
$success_message = '';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_user') {
        // Ajouter un utilisateur
        $username = htmlspecialchars(trim($_POST['username']));
        $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
        $password = trim($_POST['password']);
        $full_name = htmlspecialchars(trim($_POST['full_name']));
        $role = htmlspecialchars(trim($_POST['role']));
        $created_at = date('Y-m-d H:i:s');
        
        // Validation
        if (empty($username) || empty($email) || empty($password) || empty($full_name) || empty($role)) {
            $error_message = "Tous les champs sont obligatoires";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Format d'email invalide";
        } elseif (strlen($password) < 8) {
            $error_message = "Le mot de passe doit contenir au moins 8 caractères";
        } else {
            // Utiliser la fonction externe pour créer l'utilisateur
            $result = reset_or_create_user_password($pdo, $username, $email, $password, $full_name, $role, null, $created_at);
            
            if ($result['success']) {
                $success_message = "Utilisateur créé avec succès";
            } else {
                $error_message = $result['message'];
            }
        }
    }
    
    elseif ($action === 'edit_user') {
        // Modifier un utilisateur
        $user_id = (int)$_POST['user_id'];
        $username = htmlspecialchars(trim($_POST['username']));
        $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
        $full_name = htmlspecialchars(trim($_POST['full_name']));
        $role = htmlspecialchars(trim($_POST['role']));
        $new_password = trim($_POST['new_password']);
        
        if (empty($username) || empty($email) || empty($full_name) || empty($role)) {
            $error_message = "Tous les champs sont obligatoires";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Format d'email invalide";
        } else {
            try {
                // Vérifier si username ou email existe déjà pour un autre utilisateur
                $check = $pdo->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
                $check->execute([$username, $email, $user_id]);
                
                if ($check->rowCount() > 0) {
                    $error_message = "Ce nom d'utilisateur ou email existe déjà";
                } else {
                    // Mise à jour avec ou sans mot de passe
                    if (!empty($new_password)) {
                        if (strlen($new_password) < 8) {
                            $error_message = "Le nouveau mot de passe doit contenir au moins 8 caractères";
                        } else {
                            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                            $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, full_name = ?, role = ?, password_hash = ? WHERE id = ?");
                            $stmt->execute([$username, $email, $full_name, $role, $password_hash, $user_id]);
                            $success_message = "Utilisateur modifié avec succès (mot de passe mis à jour)";
                        }
                    } else {
                        $stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, full_name = ?, role = ? WHERE id = ?");
                        $stmt->execute([$username, $email, $full_name, $role, $user_id]);
                        $success_message = "Utilisateur modifié avec succès";
                    }
                }
            } catch (PDOException $e) {
                $error_message = "Erreur lors de la modification: " . $e->getMessage();
            }
        }
    }
    
    elseif ($action === 'delete_user') {
        // Supprimer un utilisateur
        $user_id = (int)$_POST['user_id'];
        
        // Empêcher la suppression de son propre compte
        if ($user_id == $_SESSION['utilisateur']['id']) {
            $error_message = "Vous ne pouvez pas supprimer votre propre compte";
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $success_message = "Utilisateur supprimé avec succès";
            } catch (PDOException $e) {
                $error_message = "Erreur lors de la suppression: " . $e->getMessage();
            }
        }
    }
}

// Récupérer la liste des utilisateurs
try {
    $stmt = $pdo->query("SELECT id, username, email, full_name, role, created_at FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Erreur lors du chargement des utilisateurs: " . $e->getMessage();
    $users = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des utilisateurs</title>
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
        
        .badge-role {
            font-weight: 500;
            padding: 0.35rem 0.6rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
        }
        
        .badge-admin {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }
        
        .badge-moderator {
            background-color: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
        }
        
        .badge-user {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
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
                        <i class="bi bi-people me-2"></i>Gestion des Utilisateurs
                    </h1>
                    <p class="text-muted mb-0">Gérez les comptes utilisateurs du système</p>
                </div>
                <div>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="bi bi-person-plus me-2"></i>Ajouter un Utilisateur
                    </button>
                </div>
            </div>
        </div>

        <!-- Liste des utilisateurs -->
        <div class="data-card card">
            <div class="card-header" style="background-color: var(--primary-light); color: white;">
                <h5 class="mb-0"><i class="bi bi-list-ul me-2"></i>Liste des Utilisateurs</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nom d'utilisateur</th>
                                <th>Email</th>
                                <th>Nom complet</th>
                                <th>Rôle</th>
                                <th>Date de création</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-3">
                                    <i class="bi bi-inbox text-muted fs-1"></i>
                                    <p class="text-muted mt-2">Aucun utilisateur trouvé</p>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td data-label="ID"><?= $user['id'] ?></td>
                                <td data-label="Nom d'utilisateur"><?= htmlspecialchars($user['username']) ?></td>
                                <td data-label="Email"><?= htmlspecialchars($user['email']) ?></td>
                                <td data-label="Nom complet"><?= htmlspecialchars($user['full_name']) ?></td>
                                <td data-label="Rôle">
                                    <span class="badge badge-role <?= 'badge-' . $user['role'] ?>">
                                        <?= ucfirst($user['role']) ?>
                                    </span>
                                </td>
                                <td data-label="Date de création"><?= date('d/m/Y H:i', strtotime($user['created_at'])) ?></td>
                                <td data-label="Actions">
                                    <button type="button" class="btn btn-sm btn-outline-primary me-1" 
                                            onclick="editUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>', '<?= htmlspecialchars($user['email']) ?>', '<?= htmlspecialchars($user['full_name']) ?>', '<?= $user['role'] ?>')">
                                        <i class="bi bi-pencil"></i> Modifier
                                    </button>
                                    <?php if ($user['id'] != $_SESSION['utilisateur']['id']): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                            onclick="deleteUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')">
                                        <i class="bi bi-trash"></i> Supprimer
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

    <!-- Modal pour ajouter un utilisateur -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ajouter un utilisateur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_user">
                        <div class="mb-3">
                            <label for="username" class="form-label">Nom d'utilisateur *</label>
                            <input type="text" class="form-control" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Mot de passe *</label>
                            <input type="password" class="form-control" name="password" required minlength="8">
                        </div>
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Nom complet *</label>
                            <input type="text" class="form-control" name="full_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="role" class="form-label">Rôle *</label>
                            <select class="form-select" name="role" required>
                                <option value="">Sélectionner un rôle</option>
                                <option value="admin">Admin</option>
                                <option value="moderator">Modérateur</option>
                                <option value="user">Utilisateur</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Créer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal pour modifier un utilisateur -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Modifier un utilisateur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_user">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        <div class="mb-3">
                            <label for="edit_username" class="form-label">Nom d'utilisateur *</label>
                            <input type="text" class="form-control" name="username" id="edit_username" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_email" class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" id="edit_email" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_full_name" class="form-label">Nom complet *</label>
                            <input type="text" class="form-control" name="full_name" id="edit_full_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_role" class="form-label">Rôle *</label>
                            <select class="form-select" name="role" id="edit_role" required>
                                <option value="admin">Admin</option>
                                <option value="moderator">Modérateur</option>
                                <option value="user">Utilisateur</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">Nouveau mot de passe (optionnel)</label>
                            <input type="password" class="form-control" name="new_password" minlength="8">
                            <small class="form-text text-muted">Laissez vide pour conserver le mot de passe actuel</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Modifier</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de confirmation de suppression -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmer la suppression</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir supprimer l'utilisateur <strong id="delete_username"></strong> ?</p>
                    <p class="text-danger">Cette action est irréversible.</p>
                </div>
                <div class="modal-footer">
                    <form method="post">
                        <input type="hidden" name="action" value="delete_user">
                        <input type="hidden" name="user_id" id="delete_user_id">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-danger">Supprimer</button>
                    </form>
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

        function editUser(id, username, email, fullName, role) {
            document.getElementById('edit_user_id').value = id;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_full_name').value = fullName;
            document.getElementById('edit_role').value = role;
            
            var editModal = new bootstrap.Modal(document.getElementById('editUserModal'));
            editModal.show();
        }

        function deleteUser(id, username) {
            document.getElementById('delete_user_id').value = id;
            document.getElementById('delete_username').textContent = username;
            
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteUserModal'));
            deleteModal.show();
        }
    </script>
</body>
</html>