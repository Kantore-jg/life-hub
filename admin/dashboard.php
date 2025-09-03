<?php
session_start();
echo "<pre>Session Debug:</pre>";
echo "user_id: " . ($_SESSION['user_id'] ?? 'non défini') . "<br>";
echo "user_type: " . ($_SESSION['user_type'] ?? 'non défini') . "<br>";
echo "user_role: " . ($_SESSION['user_role'] ?? 'non défini') . "<br>";

// Vérification des droits d'administration
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] !== 'admin' && $_SESSION['user_type'] !== 'authority')) {
    header('Location: ../login.php?error=access_denied');
    exit();
}

// Database connection - UTILISE SEULEMENT config.php
require_once '../config.php';
$db = new Database();
$conn = $db->getConnection();

// Traitement des actions administratives
if ($_POST) {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_status':
                $report_id = (int)$_POST['report_id'];
                $new_status = $_POST['status'];
                $admin_comment = $_POST['admin_comment'] ?? '';
                
                // Mettre à jour le statut du rapport
                $update_query = "UPDATE issue_reports SET status = ?, updated_at = NOW() WHERE report_id = ?";
                $stmt = $conn->prepare($update_query);
                $stmt->bind_param("si", $new_status, $report_id);
                
                if ($stmt->execute()) {
                    // Ajouter un commentaire de mise à jour
                    if (!empty($admin_comment)) {
                        $update_comment = "UPDATE report_updates SET update_text = ?, updated_at = NOW() WHERE report_id = ? ORDER BY updated_at DESC LIMIT 1";
                        $stmt2 = $conn->prepare($update_comment);
                        $stmt2->bind_param("si", $admin_comment, $report_id);
                        
                        if (!$stmt2->execute()) {
                            // Si pas de mise à jour existante, créer une nouvelle
                            $insert_update = "INSERT INTO report_updates (report_id, update_text, updated_by, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())";
                            $stmt3 = $conn->prepare($insert_update);
                            $stmt3->bind_param("isi", $report_id, $admin_comment, $_SESSION['user_id']);
                            $stmt3->execute();
                        }
                    }
                    $_SESSION['success_message'] = "Statut du rapport mis à jour avec succès.";
                } else {
                    $_SESSION['error_message'] = "Erreur lors de la mise à jour du statut.";
                }
                break;
                
            case 'create_announcement':
                $title = $_POST['announcement_title'];
                $content = $_POST['announcement_content'];
                $priority = $_POST['priority'];
                $user_id = $_SESSION['user_id'];
                
                $insert_announcement = "INSERT INTO announcements (title, content, priority, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())";
                $stmt = $conn->prepare($insert_announcement);
                $stmt->bind_param("sssi", $title, $content, $priority, $user_id);
                
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = "Annonce créée avec succès.";
                } else {
                    $_SESSION['error_message'] = "Erreur lors de la création de l'annonce.";
                }
                break;
        }
    }
    
    // Redirection pour éviter la re-soumission
    header('Location: dashboard.php');
    exit();
}

// Récupérer les statistiques générales
$stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM issue_reports) as total_reports,
        (SELECT COUNT(*) FROM issue_reports WHERE status = 'pending') as pending_reports,
        (SELECT COUNT(*) FROM issue_reports WHERE status = 'in_progress') as in_progress_reports,
        (SELECT COUNT(*) FROM issue_reports WHERE status = 'resolved') as resolved_reports,
        (SELECT COUNT(*) FROM issue_reports WHERE DATE(created_at) = CURDATE()) as today_reports,
        (SELECT COUNT(*) FROM users WHERE role = 'user') as total_users,
        (SELECT COUNT(*) FROM announcements WHERE active = 1) as active_announcements
";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Récupérer les rapports récents nécessitant une attention
$recent_reports_query = "
    SELECT 
        ir.*,
        ic.category_name,
        ic.icon,
        u.full_name as user_name,
        u.phone_number,
        DATEDIFF(NOW(), ir.created_at) as days_ago
    FROM issue_reports ir
    LEFT JOIN issue_categories ic ON ir.category_id = ic.category_id
    LEFT JOIN users u ON ir.user_id = u.user_id
    WHERE ir.status IN ('pending', 'in_progress')
    ORDER BY 
        CASE ir.priority 
            WHEN 'urgent' THEN 1
            WHEN 'high' THEN 2
            WHEN 'medium' THEN 3
            WHEN 'low' THEN 4
        END,
        ir.created_at ASC
    LIMIT 10
";
$recent_reports = $conn->query($recent_reports_query);

// Récupérer les statistiques par catégorie
$category_stats_query = "
    SELECT 
        ic.category_name,
        ic.icon,
        COUNT(ir.report_id) as count,
        SUM(CASE WHEN ir.status = 'resolved' THEN 1 ELSE 0 END) as resolved_count
    FROM issue_categories ic
    LEFT JOIN issue_reports ir ON ic.category_id = ir.category_id
    GROUP BY ic.category_id, ic.category_name, ic.icon
    ORDER BY count DESC
";
$category_stats = $conn->query($category_stats_query);

// Récupérer les annonces récentes
$announcements_query = "
    SELECT a.*, u.full_name as author_name 
    FROM announcements a 
    LEFT JOIN users u ON a.created_by = u.user_id 
    ORDER BY a.created_at DESC 
    LIMIT 5
";
$announcements = $conn->query($announcements_query);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Ubuzima Hub</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --admin-primary: #2c3e50;
            --admin-secondary: #3498db;
            --admin-success: #27ae60;
            --admin-warning: #f39c12;
            --admin-danger: #e74c3c;
            --admin-light: #ecf0f1;
            --admin-dark: #2c3e50;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--admin-light);
        }

        .sidebar {
            background: linear-gradient(180deg, var(--admin-primary) 0%, var(--admin-dark) 100%);
            min-height: 100vh;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }

        .sidebar .nav-link {
            color: #bdc3c7;
            padding: 0.75rem 1rem;
            margin: 0.25rem 0;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: var(--admin-secondary);
            color: white;
            transform: translateX(5px);
        }

        .main-content {
            background: white;
            min-height: 100vh;
            padding: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-left: 4px solid var(--admin-secondary);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--admin-secondary);
        }

        .report-card {
            background: white;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .report-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .priority-urgent { border-left: 4px solid var(--admin-danger); }
        .priority-high { border-left: 4px solid #e67e22; }
        .priority-medium { border-left: 4px solid var(--admin-warning); }
        .priority-low { border-left: 4px solid var(--admin-success); }

        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-in_progress { background: #d1ecf1; color: #0c5460; }
        .status-resolved { background: #d4edda; color: #155724; }
        .status-closed { background: #f8d7da; color: #721c24; }

        .header-admin {
            background: linear-gradient(135deg, var(--admin-primary) 0%, var(--admin-secondary) 100%);
            color: white;
            padding: 1rem 0;
            margin-bottom: 2rem;
            border-radius: 10px;
        }

        .quick-actions {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .btn-admin {
            background: var(--admin-secondary);
            color: white;
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .btn-admin:hover {
            background: var(--admin-primary);
            color: white;
            transform: translateY(-1px);
        }

        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .announcement-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0 sidebar">
                <div class="p-3">
                    <h4 class="text-white mb-4">
                        <i class="fas fa-shield-alt"></i> Admin Panel
                    </h4>
                    <nav class="nav flex-column">
                        <a class="nav-link active" href="#dashboard">
                            <i class="fas fa-tachometer-alt"></i> Tableau de bord
                        </a>
                        <a class="nav-link" href="#reports">
                            <i class="fas fa-clipboard-list"></i> Rapports
                        </a>
                        <a class="nav-link" href="#announcements">
                            <i class="fas fa-bullhorn"></i> Annonces
                        </a>
                        <a class="nav-link" href="#users">
                            <i class="fas fa-users"></i> Utilisateurs
                        </a>
                        <a class="nav-link" href="#analytics">
                            <i class="fas fa-chart-bar"></i> Analytics
                        </a>
                        <a class="nav-link" href="#settings">
                            <i class="fas fa-cog"></i> Paramètres
                        </a>
                        <hr class="my-3">
                        <a class="nav-link" href="../index.php">
                            <i class="fas fa-arrow-left"></i> Site public
                        </a>
                        <a class="nav-link" href="../logout.php">
                            <i class="fas fa-sign-out-alt"></i> Déconnexion
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 main-content">
                <!-- Messages de feedback -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success_message']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error_message']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error_message']); ?>
                <?php endif; ?>

                <!-- Header -->
                <div class="header-admin">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h2><i class="fas fa-tachometer-alt"></i> Tableau de Bord Administrateur</h2>
                            <p class="mb-0">Gestion des rapports et annonces - Ubuzima Hub</p>
                        </div>
                        <div class="col-md-4 text-end">
                            <span class="badge bg-light text-dark">
                                <i class="fas fa-user"></i> <?php echo $_SESSION['user_name'] ?? 'Administrateur'; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Statistiques -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="stat-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="stat-number"><?php echo $stats['total_reports']; ?></div>
                                    <div class="text-muted">Total Rapports</div>
                                </div>
                                <i class="fas fa-clipboard-list fa-2x text-primary"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stat-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="stat-number text-warning"><?php echo $stats['pending_reports']; ?></div>
                                    <div class="text-muted">En Attente</div>
                                </div>
                                <i class="fas fa-clock fa-2x text-warning"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stat-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="stat-number text-info"><?php echo $stats['in_progress_reports']; ?></div>
                                    <div class="text-muted">En Cours</div>
                                </div>
                                <i class="fas fa-cog fa-2x text-info"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stat-card">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="stat-number text-success"><?php echo $stats['resolved_reports']; ?></div>
                                    <div class="text-muted">Résolus</div>
                                </div>
                                <i class="fas fa-check-circle fa-2x text-success"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions rapides -->
                <div class="quick-actions">
                    <h5><i class="fas fa-bolt"></i> Actions Rapides</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <button class="btn btn-admin me-2 mb-2" data-bs-toggle="modal" data-bs-target="#newAnnouncementModal">
                                <i class="fas fa-plus"></i> Nouvelle Annonce
                            </button>
                            <button class="btn btn-outline-primary me-2 mb-2">
                                <i class="fas fa-download"></i> Exporter Rapports
                            </button>
                        </div>
                        <div class="col-md-6 text-end">
                            <div class="badge bg-success me-2">
                                <i class="fas fa-calendar"></i> Aujourd'hui: <?php echo $stats['today_reports']; ?> rapports
                            </div>
                            <div class="badge bg-info">
                                <i class="fas fa-users"></i> <?php echo $stats['total_users']; ?> utilisateurs
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Rapports nécessitant attention -->
                    <div class="col-md-8">
                        <div class="chart-container">
                            <h5><i class="fas fa-exclamation-triangle"></i> Rapports Nécessitant une Attention</h5>
                            
                            <?php if ($recent_reports->num_rows > 0): ?>
                                <?php while ($report = $recent_reports->fetch_assoc()): ?>
                                    <div class="report-card priority-<?php echo $report['priority'] ?? 'medium'; ?>">
                                        <div class="row align-items-center">
                                            <div class="col-md-1">
                                                <i class="fas fa-<?php echo $report['icon'] ?? 'exclamation-triangle'; ?> fa-lg text-primary"></i>
                                            </div>
                                            <div class="col-md-6">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($report['title']); ?></h6>
                                                <small class="text-muted">
                                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($report['user_name']); ?> |
                                                    <i class="fas fa-tag"></i> <?php echo htmlspecialchars($report['category_name']); ?> |
                                                    <i class="fas fa-calendar"></i> Il y a <?php echo $report['days_ago']; ?> jour(s)
                                                </small>
                                            </div>
                                            <div class="col-md-2 text-center">
                                                <span class="status-badge status-<?php echo $report['status']; ?>">
                                                    <?php 
                                                    $status_labels = [
                                                        'pending' => 'En attente',
                                                        'in_progress' => 'En cours',
                                                        'resolved' => 'Résolu',
                                                        'closed' => 'Fermé'
                                                    ];
                                                    echo $status_labels[$report['status']] ?? 'Inconnu';
                                                    ?>
                                                </span>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-primary dropdown-toggle" 
                                                            type="button" data-bs-toggle="dropdown">
                                                        Actions
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li><a class="dropdown-item" href="#" 
                                                               onclick="updateStatus(<?php echo $report['report_id']; ?>, 'in_progress')">
                                                            <i class="fas fa-play"></i> Marquer en cours
                                                        </a></li>
                                                        <li><a class="dropdown-item" href="#" 
                                                               onclick="updateStatus(<?php echo $report['report_id']; ?>, 'resolved')">
                                                            <i class="fas fa-check"></i> Marquer résolu
                                                        </a></li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li><a class="dropdown-item" href="report_details.php?id=<?php echo $report['report_id']; ?>">
                                                            <i class="fas fa-eye"></i> Voir détails
                                                        </a></li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-check-circle fa-3x mb-3"></i>
                                    <p>Aucun rapport nécessitant une attention particulière.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Statistiques par catégorie et annonces -->
                    <div class="col-md-4">
                        <!-- Catégories -->
                        <div class="chart-container mb-3">
                            <h6><i class="fas fa-chart-pie"></i> Rapports par Catégorie</h6>
                            <?php if ($category_stats->num_rows > 0): ?>
                                <?php while ($cat = $category_stats->fetch_assoc()): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div>
                                            <i class="fas fa-<?php echo $cat['icon'] ?? 'circle'; ?>"></i>
                                            <?php echo htmlspecialchars($cat['category_name']); ?>
                                        </div>
                                        <div>
                                            <span class="badge bg-primary"><?php echo $cat['count']; ?></span>
                                            <span class="badge bg-success"><?php echo $cat['resolved_count']; ?></span>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Annonces récentes -->
                        <div class="chart-container">
                            <h6><i class="fas fa-bullhorn"></i> Annonces Récentes</h6>
                            <?php if ($announcements->num_rows > 0): ?>
                                <?php while ($ann = $announcements->fetch_assoc()): ?>
                                    <div class="announcement-card">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($ann['title']); ?></h6>
                                        <p class="mb-1 small"><?php echo htmlspecialchars(substr($ann['content'], 0, 100)); ?>...</p>
                                        <small>
                                            <i class="fas fa-user"></i> <?php echo htmlspecialchars($ann['author_name']); ?> |
                                            <i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($ann['created_at'])); ?>
                                        </small>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p class="text-muted small">Aucune annonce récente.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Nouvelle Annonce -->
    <div class="modal fade" id="newAnnouncementModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-plus"></i> Nouvelle Annonce</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_announcement">
                        
                        <div class="mb-3">
                            <label for="announcement_title" class="form-label">Titre</label>
                            <input type="text" class="form-control" name="announcement_title" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="announcement_content" class="form-label">Contenu</label>
                            <textarea class="form-control" name="announcement_content" rows="4" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="priority" class="form-label">Priorité</label>
                            <select class="form-select" name="priority">
                                <option value="normal">Normal</option>
                                <option value="high">Élevée</option>
                                <option value="urgent">Urgente</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-admin">Créer l'annonce</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateStatus(reportId, newStatus) {
            const comment = prompt("Commentaire administratif (optionnel) :");
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'update_status';
            
            const reportIdInput = document.createElement('input');
            reportIdInput.type = 'hidden';
            reportIdInput.name = 'report_id';
            reportIdInput.value = reportId;
            
            const statusInput = document.createElement('input');
            statusInput.type = 'hidden';
            statusInput.name = 'status';
            statusInput.value = newStatus;
            
            const commentInput = document.createElement('input');
            commentInput.type = 'hidden';
            commentInput.name = 'admin_comment';
            commentInput.value = comment || '';
            
            form.appendChild(actionInput);
            form.appendChild(reportIdInput);
            form.appendChild(statusInput);
            form.appendChild(commentInput);
            
            document.body.appendChild(form);
            form.submit();
        }

        // Auto-refresh toutes les 2 minutes
        setInterval(() => {
            location.reload();
        }, 120000);
    </script>
</body>
</html>