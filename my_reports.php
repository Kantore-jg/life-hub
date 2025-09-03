<?php
session_start();

// Vérification de session renforcée
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "ubuzima_hub";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get user ID from session
$user_id = $_SESSION['user_id'];

// Pagination setup
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Filter setup
$status_filter = isset($_GET['status']) && in_array($_GET['status'], ['pending', 'in_progress', 'resolved', 'closed']) 
    ? $_GET['status'] : '';
$search_filter = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build WHERE conditions
$where_conditions = ["ir.user_id = ?"];
$params = [$user_id];
$param_types = "i";

if ($status_filter) {
    $where_conditions[] = "ir.status = ?";
    $params[] = $status_filter;
    $param_types .= "s";
}

if ($search_filter) {
    $where_conditions[] = "(ir.title LIKE ? OR ir.description LIKE ? OR ic.category_name LIKE ?)";
    $search_term = "%$search_filter%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
    $param_types .= "sss";
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Get total count for pagination
$count_query = "
    SELECT COUNT(*) as total
    FROM issue_reports ir
    LEFT JOIN issue_categories ic ON ir.category_id = ic.category_id
    $where_clause
";

$count_stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $count_stmt->bind_param($param_types, ...$params);
}
$count_stmt->execute();
$total_reports = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_reports / $per_page);

// Main reports query with pagination
$reports_query = "
    SELECT 
        ir.report_id as id,
        ir.user_id,
        ir.category_id,
        ir.title,
        ir.description,
        ir.location_address as location,
        ir.status,
        ir.priority,
        ir.created_at,
        ir.updated_at,
        CASE WHEN ir.image_path IS NOT NULL THEN 1 ELSE 0 END as has_image,
        COALESCE(ic.category_name, 'Non catégorisé') as category_name,
        COALESCE(ic.icon, 'exclamation-triangle') as category_icon,
        'issue_report' as source_table
    FROM issue_reports ir
    LEFT JOIN issue_categories ic ON ir.category_id = ic.category_id
    $where_clause
    ORDER BY ir.created_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($reports_query);
$params[] = $per_page;
$params[] = $offset;
$param_types .= "ii";
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$reports_result = $stmt->get_result();

// Get updates for each report
function getReportUpdates($conn, $report_id) {
    $updates_query = "SELECT COUNT(*) as count, MAX(update_text) as latest_update FROM report_updates WHERE report_id = ?";
    $stmt = $conn->prepare($updates_query);
    $stmt->bind_param("i", $report_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Get status statistics
$stats_query = "
    SELECT status, COUNT(*) as count 
    FROM issue_reports 
    WHERE user_id = ? 
    GROUP BY status
";
$stmt_stats = $conn->prepare($stats_query);
$stmt_stats->bind_param("i", $user_id);
$stmt_stats->execute();
$stats_result = $stmt_stats->get_result();

$stats = ['pending' => 0, 'in_progress' => 0, 'resolved' => 0, 'closed' => 0];
while ($row = $stats_result->fetch_assoc()) {
    if (isset($stats[$row['status']])) {
        $stats[$row['status']] = $row['count'];
    }
}

// Generate pagination links
function generatePaginationUrl($page, $status_filter, $search_filter) {
    $params = ['page' => $page];
    if ($status_filter) $params['status'] = $status_filter;
    if ($search_filter) $params['search'] = $search_filter;
    return 'my_reports.php?' . http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Rapports - Ubuzima Hub</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --accent-color: #f093fb;
            --success-color: #48bb78;
            --warning-color: #ed8936;
            --danger-color: #f56565;
            --text-dark: #2d3748;
            --text-light: #718096;
            --bg-light: #f7fafc;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--bg-light) 0%, #e2e8f0 100%);
            min-height: 100vh;
        }

        .header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-top: 4px solid var(--primary-color);
            transition: transform 0.3s ease;
        }

        .stats-card:hover {
            transform: translateY(-3px);
        }

        .stats-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
        }

        .report-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s ease;
        }

        .report-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-pending {
            background: #fef2e0;
            color: var(--warning-color);
            border: 1px solid var(--warning-color);
        }

        .status-in_progress {
            background: #e6fffa;
            color: #319795;
            border: 1px solid #319795;
        }

        .status-resolved {
            background: #f0fff4;
            color: var(--success-color);
            border: 1px solid var(--success-color);
        }

        .status-closed {
            background: #f7fafc;
            color: var(--text-light);
            border: 1px solid var(--text-light);
        }

        .category-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--bg-light);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--primary-color);
        }

        .report-meta {
            color: var(--text-light);
            font-size: 0.9rem;
        }

        .back-btn {
            background: white;
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
            padding: 0.5rem 1rem;
            border-radius: 5px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: var(--primary-color);
            color: white;
        }

        .updates-badge {
            background: var(--accent-color);
            color: white;
            padding: 0.3rem 0.6rem;
            border-radius: 15px;
            font-size: 0.8rem;
        }

        .no-reports {
            text-align: center;
            padding: 3rem;
            color: var(--text-light);
        }

        .report-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn-action {
            padding: 0.4rem 0.8rem;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }

        .btn-details {
            background: var(--primary-color);
            color: white;
        }

        .btn-details:hover {
            background: var(--secondary-color);
            color: white;
        }

        .priority-high {
            border-left-color: var(--danger-color) !important;
        }

        .priority-medium {
            border-left-color: var(--warning-color) !important;
        }

        .priority-low {
            border-left-color: var(--success-color) !important;
        }

        .pagination-info {
            color: var(--text-light);
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .filters-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .alert-custom {
            border-radius: 10px;
            border: none;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="fas fa-clipboard-list"></i> Mes Rapports</h1>
                    <p class="mb-0">Suivez l'état de vos signalements</p>
                </div>
                <a href="index.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Messages de feedback -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-custom alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-custom alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error_message']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $stats['pending']; ?></div>
                    <div class="text-muted">En attente</div>
                    <i class="fas fa-clock text-warning mt-2"></i>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $stats['in_progress']; ?></div>
                    <div class="text-muted">En cours</div>
                    <i class="fas fa-cog text-info mt-2"></i>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $stats['resolved']; ?></div>
                    <div class="text-muted">Résolus</div>
                    <i class="fas fa-check-circle text-success mt-2"></i>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="stats-card">
                    <div class="stats-number"><?php echo $stats['closed']; ?></div>
                    <div class="text-muted">Fermés</div>
                    <i class="fas fa-archive text-secondary mt-2"></i>
                </div>
            </div>
        </div>

        <!-- Filter Options -->
        <div class="filters-card">
            <form method="GET" action="my_reports.php" class="row g-3">
                <div class="col-md-4">
                    <label for="status" class="form-label">Filtrer par statut</label>
                    <select class="form-select" name="status" id="status">
                        <option value="">Tous les statuts</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>En attente</option>
                        <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>En cours</option>
                        <option value="resolved" <?php echo $status_filter === 'resolved' ? 'selected' : ''; ?>>Résolus</option>
                        <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Fermés</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="search" class="form-label">Rechercher</label>
                    <input type="text" class="form-control" name="search" id="search" 
                           placeholder="Rechercher dans titre, description ou catégorie..." 
                           value="<?php echo htmlspecialchars($search_filter); ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Filtrer
                    </button>
                </div>
            </form>
            
            <?php if ($status_filter || $search_filter): ?>
                <div class="mt-3">
                    <a href="my_reports.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-times"></i> Effacer les filtres
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination Info -->
        <div class="pagination-info">
            <i class="fas fa-info-circle"></i>
            Affichage <?php echo ($offset + 1); ?> à <?php echo min($offset + $per_page, $total_reports); ?> 
            sur <?php echo $total_reports; ?> rapport(s)
        </div>

        <!-- Reports List -->
        <div class="reports-container">
            <?php if ($reports_result->num_rows > 0): ?>
                <?php while ($report = $reports_result->fetch_assoc()): 
                    // Get updates for this report
                    $updates_data = getReportUpdates($conn, $report['id']);
                    $updates_count = $updates_data['count'];
                    $latest_update = $updates_data['latest_update'];
                ?>
                    <div class="report-card priority-<?php echo strtolower($report['priority'] ?? 'medium'); ?>">
                        
                        <div class="row align-items-center">
                            <div class="col-md-1">
                                <div class="category-icon">
                                    <i class="fas fa-<?php echo $report['category_icon'] ?? 'exclamation-triangle'; ?>"></i>
                                </div>
                            </div>
                            
                            <div class="col-md-7">
                                <h5 class="mb-2"><?php echo htmlspecialchars($report['title']); ?></h5>
                                <p class="text-muted mb-2"><?php echo htmlspecialchars(substr($report['description'], 0, 150)) . (strlen($report['description']) > 150 ? '...' : ''); ?></p>
                                
                                <div class="report-meta">
                                    <span><i class="fas fa-tag"></i> <?php echo htmlspecialchars($report['category_name'] ?? 'Non catégorisé'); ?></span>
                                    <span class="ms-3"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($report['location'] ?? 'Non spécifié'); ?></span>
                                    <span class="ms-3"><i class="fas fa-calendar"></i> <?php echo date('d/m/Y H:i', strtotime($report['created_at'])); ?></span>
                                </div>
                                
                                <?php if ($latest_update): ?>
                                    <div class="mt-2">
                                        <small class="text-info">
                                            <i class="fas fa-comment"></i> 
                                            <strong>Dernière mise à jour:</strong> 
                                            <?php echo htmlspecialchars(substr($latest_update, 0, 100)); ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-2 text-center">
                                <div class="status-badge status-<?php echo $report['status']; ?>">
                                    <?php 
                                    $status_labels = [
                                        'pending' => 'En attente',
                                        'in_progress' => 'En cours',
                                        'resolved' => 'Résolu',
                                        'closed' => 'Fermé'
                                    ];
                                    echo $status_labels[$report['status']] ?? 'Inconnu';
                                    ?>
                                </div>
                                
                                <?php if ($updates_count > 0): ?>
                                    <div class="updates-badge mt-2">
                                        <i class="fas fa-comments"></i> <?php echo $updates_count; ?> mise(s) à jour
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-2">
                                <div class="report-actions">
                                    <a href="report_details.php?id=<?php echo $report['id']; ?>&source=<?php echo $report['source_table']; ?>" class="btn-action btn-details">
                                        <i class="fas fa-eye"></i> Détails
                                    </a>
                                </div>
                                
                                <?php if ($report['priority']): ?>
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            Priorité: <strong><?php echo ucfirst($report['priority']); ?></strong>
                                        </small>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($report['has_image']): ?>
                                    <div class="mt-1">
                                        <small class="text-success">
                                            <i class="fas fa-image"></i> Avec image
                                        </small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="no-reports">
                    <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                    <h3>Aucun rapport trouvé</h3>
                    <p>
                        <?php if ($status_filter || $search_filter): ?>
                            Aucun rapport ne correspond à vos critères de recherche.
                        <?php else: ?>
                            Vous n'avez pas encore soumis de signalement.
                        <?php endif; ?>
                    </p>
                    <a href="report_issue.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Signaler un problème
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav aria-label="Navigation des pages" class="mt-4">
                <ul class="pagination justify-content-center">
                    <!-- Previous Page -->
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?php echo generatePaginationUrl($page - 1, $status_filter, $search_filter); ?>">
                                <i class="fas fa-chevron-left"></i> Précédent
                            </a>
                        </li>
                    <?php endif; ?>

                    <!-- Page Numbers -->
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    if ($start_page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?php echo generatePaginationUrl(1, $status_filter, $search_filter); ?>">1</a>
                        </li>
                        <?php if ($start_page > 2): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="<?php echo generatePaginationUrl($i, $status_filter, $search_filter); ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($end_page < $total_pages): ?>
                        <?php if ($end_page < $total_pages - 1): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="<?php echo generatePaginationUrl($total_pages, $status_filter, $search_filter); ?>"><?php echo $total_pages; ?></a>
                        </li>
                    <?php endif; ?>

                    <!-- Next Page -->
                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?php echo generatePaginationUrl($page + 1, $status_filter, $search_filter); ?>">
                                Suivant <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh every 60 seconds for status updates
        let autoRefresh = setInterval(() => {
            // Check if there are any pending reports
            const pendingCount = <?php echo $stats['pending']; ?>;
            const inProgressCount = <?php echo $stats['in_progress']; ?>;
            
            if (pendingCount > 0 || inProgressCount > 0) {
                // Only refresh if there might be updates
                // In production, you might want to use AJAX to check for updates
                // location.reload();
            }
        }, 60000);

        // Clear auto-refresh when user is actively interacting
        document.addEventListener('click', () => {
            clearInterval(autoRefresh);
            // Restart after 5 minutes of inactivity
            setTimeout(() => {
                autoRefresh = setInterval(() => {
                    const pendingCount = <?php echo $stats['pending']; ?>;
                    const inProgressCount = <?php echo $stats['in_progress']; ?>;
                    if (pendingCount > 0 || inProgressCount > 0) {
                        // location.reload();
                    }
                }, 60000);
            }, 300000);
        });

        // Smooth scroll to top when changing pages
        if (window.location.search.includes('page=')) {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    </script>
</body>
</html>