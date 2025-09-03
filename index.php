<?php
require_once 'config.php';

// GESTION DES LANGUES DÉPLACÉE AU DÉBUT
if (isset($_GET['lang'])) {
    setLanguage($_GET['lang']);
    // Redirige vers la même page sans paramètres pour éviter les conflits
    $redirect_url = strtok($_SERVER['REQUEST_URI'], '?');
    header('Location: ' . $redirect_url);
    exit();
}

requireLogin();

$currentUser = getCurrentUser();
$userType = $currentUser['user_type'];
?>

<!DOCTYPE html>
<html lang="<?= getLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ubuzima Hub - Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .nav {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 2rem;
        }
        
        .logo {
            font-size: 1.5rem;
            font-weight: bold;
            color: #667eea;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .lang-switch {
            display: flex;
            gap: 0.5rem;
        }
        
        .lang-btn {
            padding: 0.25rem 0.5rem;
            border: 1px solid #ddd;
            background: white;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            color: #333;
        }
        
        .lang-btn.active {
            background: #667eea;
            color: white;
        }
        
        .main-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .welcome-card {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .dashboard-card {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            cursor: pointer;
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
        }
        
        .card-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            text-align: center;
        }
        
        .card-title {
            font-size: 1.25rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            text-align: center;
        }
        
        .card-description {
            color: #666;
            text-align: center;
            line-height: 1.5;
        }
        
        .stats-section {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .stat-item {
            text-align: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
        }
        
        .stat-label {
            color: #666;
            margin-top: 0.5rem;
        }
        
        .logout-btn {
            background: #dc3545;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
        }
        
        .message {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        
        .message.success { background: #d4edda; color: #155724; }
        .message.error { background: #f8d7da; color: #721c24; }
        .message.info { background: #d1ecf1; color: #0c5460; }
    </style>
</head>
<body>
    <header class="header">
        <nav class="nav">
            <div class="logo">🌍 Ubuzima Hub</div>
            <div class="user-info">
                <div class="lang-switch">
                    <a href="?lang=fr" class="lang-btn <?= getLanguage() === 'fr' ? 'active' : '' ?>">FR</a>
                    <a href="?lang=rn" class="lang-btn <?= getLanguage() === 'rn' ? 'active' : '' ?>">RN</a>
                </div>
                <span>👋 <?= htmlspecialchars($currentUser['full_name']) ?></span>
                <a href="logout.php" class="logout-btn"><?= t('logout') ?></a>
            </div>
        </nav>
    </header>

    <main class="main-container">
        <?php 
        $message = getMessage();
        if ($message): 
        ?>
            <div class="message <?= $message['type'] ?>">
                <?= htmlspecialchars($message['text']) ?>
            </div>
        <?php endif; ?>

        <div class="welcome-card">
            <h1><?= getLanguage() === 'fr' ? 'Bienvenue sur Ubuzima Hub' : 'Murakaza neza kuri Ubuzima Hub' ?></h1>
            <p><?= getLanguage() === 'fr' ? 'Votre plateforme pour améliorer les services publics à Bujumbura' : 'Urubuga rwawe rwo kuzamura serivise za Leta i Bujumbura' ?></p>
            <p><strong><?= getLanguage() === 'fr' ? 'Votre localisation:' : 'Aho ubana:' ?></strong> <?= htmlspecialchars($currentUser['commune']) ?>, <?= htmlspecialchars($currentUser['zone']) ?></p>
        </div>

        <div class="dashboard-grid">
            <div class="dashboard-card" onclick="location.href='report_issue.php'">
                <div class="card-icon">🚨</div>
                <div class="card-title"><?= t('report_issue') ?></div>
                <div class="card-description">
                    <?= getLanguage() === 'fr' ? 'Signalez les problèmes d\'eau, électricité, routes...' : 'Menya ibibazo by\'amazi, amashanyarazi, imihanda...' ?>
                </div>
            </div>

            <div class="dashboard-card" onclick="location.href='tax_payment.php'">
                <div class="card-icon">💰</div>
                <div class="card-title"><?= t('pay_taxes') ?></div>
                <div class="card-description">
                    <?= getLanguage() === 'fr' ? 'Payez vos taxes facilement en ligne' : 'Ishyura imisoro yawe byoroshye kuri interineti' ?>
                </div>
            </div>

            <div class="dashboard-card" onclick="location.href='transportation.php'">
                <div class="card-icon">🚌</div>
                <div class="card-title"><?= t('transport_info') ?></div>
                <div class="card-description">
                    <?= getLanguage() === 'fr' ? 'Informations sur les bus, taxis et stations-service' : 'Amakuru ku modoka, takisi n\'aho guhindurira peteroli' ?>
                </div>
            </div>

            <div class="dashboard-card" onclick="location.href='my_reports.php'">
                <div class="card-icon">📋</div>
                <div class="card-title"><?= getLanguage() === 'fr' ? 'Mes rapports' : 'Raporo zanjye' ?></div>
                <div class="card-description">
                    <?= getLanguage() === 'fr' ? 'Suivez l\'état de vos signalements' : 'Kurikirana raporo zawe' ?>
                </div>
            </div>
            
            <div class="dashboard-card" onclick="location.href='household_management.php'">
                <div class="card-icon">🏡</div>
                <div class="card-title"><?= getLanguage() === 'fr' ? 'My house' : 'iwacu' ?></div>
                <div class="card-description">
                    <?= getLanguage() === 'fr' ? 'Suivez l\'état de vos menage' : 'Kurikirana raporo zaho mubaye' ?>
                </div>
            </div>

            <?php if ($userType === 'authority' || $userType === 'admin'): ?>
            <div class="dashboard-card" onclick="location.href='admin/dashboard.php'">
                <div class="card-icon">⚙️</div>
                <div class="card-title"><?= getLanguage() === 'fr' ? 'Administration' : 'Ubuyobozi' ?></div>
                <div class="card-description">
                    <?= getLanguage() === 'fr' ? 'Gérer les rapports et annonces' : 'Gucunga raporo n\'amatangazo' ?>
                </div>
                
            </div>
            <?php endif; ?>
        </div>

        <?php
        // Get basic stats for the user's area
        $db = new Database();
        $conn = $db->getConnection();
        
        // Count reports in user's commune
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total_reports,
                SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_reports,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_reports
            FROM issue_reports ir 
            JOIN users u ON ir.user_id = u.user_id 
            WHERE u.commune = ?
        ");
        $stmt->bind_param("s", $currentUser['commune']);
        $stmt->execute();
        $stats = $stmt->get_result()->fetch_assoc();
        
        $db->close();
        ?>

        <div class="stats-section">
            <h2><?= getLanguage() === 'fr' ? 'Statistiques de votre commune' : 'Imibare y\'akarere kanyu' ?></h2>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number"><?= $stats['total_reports'] ?></div>
                    <div class="stat-label"><?= getLanguage() === 'fr' ? 'Total rapports' : 'Raporo zose' ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?= $stats['resolved_reports'] ?></div>
                    <div class="stat-label"><?= getLanguage() === 'fr' ? 'Résolu' : 'Byakemutse' ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?= $stats['pending_reports'] ?></div>
                    <div class="stat-label"><?= getLanguage() === 'fr' ? 'En attente' : 'Bitegerejwe' ?></div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?= $stats['resolved_reports'] > 0 ? round(($stats['resolved_reports'] / $stats['total_reports']) * 100) : 0 ?>%</div>
                    <div class="stat-label"><?= getLanguage() === 'fr' ? 'Taux de résolution' : 'Igipimo cyo gukemura' ?></div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Handle language switching - VERSION CORRIGÉE
        const langLinks = document.querySelectorAll('.lang-btn');
        langLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const lang = this.getAttribute('href').split('=')[1];
                const currentUrl = window.location.pathname;
                window.location.href = currentUrl + '?lang=' + lang;
            });
        });
    </script>
</body>
</html>