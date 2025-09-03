<?php
require_once 'config.php';

// Gestion des langues
if (isset($_GET['lang'])) {
    setLanguage($_GET['lang']);
    header('Location: household_management.php');
    exit();
}

requireLogin();

$currentUser = getCurrentUser();
$db = new Database();
$conn = $db->getConnection();

// Traitement des formulaires
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_member':
                $stmt = $conn->prepare("INSERT INTO household_members (user_id, full_name, relationship, id_card_number, date_of_birth, gender, phone_number) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("issssss", 
                    $currentUser['user_id'],
                    $_POST['full_name'],
                    $_POST['relationship'],
                    $_POST['id_card_number'],
                    $_POST['date_of_birth'],
                    $_POST['gender'],
                    $_POST['phone_number']
                );
                
                if ($stmt->execute()) {
                    setMessage("Membre de famille ajouté avec succès", "success");
                } else {
                    setMessage("Erreur lors de l'ajout du membre", "error");
                }
                break;
                
            case 'add_guest':
                $stmt = $conn->prepare("INSERT INTO household_guests (user_id, host_member_id, full_name, id_card_number, phone_number, origin_address, arrival_date, planned_departure_date, purpose_of_visit) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("iisssssss",
                    $currentUser['user_id'],
                    $_POST['host_member_id'],
                    $_POST['guest_name'],
                    $_POST['guest_id_card'],
                    $_POST['guest_phone'],
                    $_POST['origin_address'],
                    $_POST['arrival_date'],
                    $_POST['departure_date'],
                    $_POST['purpose']
                );
                
                if ($stmt->execute()) {
                    // Créer notification pour l'autorité locale
                    $guest_id = $conn->insert_id;
                    
                    // Trouver l'autorité responsable de la zone
                    $auth_stmt = $conn->prepare("SELECT user_id FROM users WHERE authority_level IN ('chef_quartier', 'chef_zone') AND authority_area = ? AND user_type = 'authority'");
                    $auth_stmt->bind_param("s", $currentUser['zone']);
                    $auth_stmt->execute();
                    $authority = $auth_stmt->get_result()->fetch_assoc();
                    
                    if ($authority) {
                        $notif_stmt = $conn->prepare("INSERT INTO household_notifications (user_id, guest_id, notification_type, title, message, recipient_authority_id) VALUES (?, ?, 'new_guest', ?, ?, ?)");
                        $title = "Nouvel invité déclaré";
                        $message = "Un nouveau invité a été déclaré dans le ménage de " . $currentUser['full_name'] . " (" . $currentUser['zone'] . ")";
                        $notif_stmt->bind_param("iissi", $currentUser['user_id'], $guest_id, $title, $message, $authority['user_id']);
                        $notif_stmt->execute();
                    }
                    
                    setMessage("Invité déclaré avec succès. Le chef de quartier a été notifié.", "success");
                } else {
                    setMessage("Erreur lors de la déclaration de l'invité", "error");
                }
                break;

            case 'delete_member':
                $stmt = $conn->prepare("UPDATE household_members SET status = 'inactive' WHERE member_id = ? AND user_id = ?");
                $stmt->bind_param("ii", $_POST['member_id'], $currentUser['user_id']);
                
                if ($stmt->execute()) {
                    setMessage("Membre supprimé avec succès", "success");
                } else {
                    setMessage("Erreur lors de la suppression", "error");
                }
                break;

            case 'update_guest_departure':
                $stmt = $conn->prepare("UPDATE household_guests SET actual_departure_date = CURDATE(), status = 'departed' WHERE guest_id = ? AND user_id = ?");
                $stmt->bind_param("ii", $_POST['guest_id'], $currentUser['user_id']);
                
                if ($stmt->execute()) {
                    setMessage("Départ de l'invité enregistré", "success");
                } else {
                    setMessage("Erreur lors de l'enregistrement du départ", "error");
                }
                break;
        }
        
        header('Location: household_management.php');
        exit();
    }
}

// Récupérer les membres du ménage
$stmt = $conn->prepare("SELECT * FROM household_members WHERE user_id = ? AND status = 'active' ORDER BY created_at");
$stmt->bind_param("i", $currentUser['user_id']);
$stmt->execute();
$members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Récupérer les invités
$stmt = $conn->prepare("
    SELECT g.*, m.full_name as host_name 
    FROM household_guests g 
    JOIN household_members m ON g.host_member_id = m.member_id 
    WHERE g.user_id = ? 
    ORDER BY g.created_at DESC
");
$stmt->bind_param("i", $currentUser['user_id']);
$stmt->execute();
$guests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Récupérer les notifications non lues
$stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM announcements WHERE user_id = ? AND is_read = FALSE");
$stmt->bind_param("i", $currentUser['user_id']);
$stmt->execute();
$unread_notifications = $stmt->get_result()->fetch_assoc()['unread_count'];

$db->close();
?>

<!DOCTYPE html>
<html lang="<?= getLanguage() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carnet de Ménage - Ubuzima Hub</title>
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
            text-decoration: none;
        }
        
        .nav-links {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        
        .nav-links a {
            color: #333;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            transition: background-color 0.3s;
            position: relative;
        }
        
        .nav-links a:hover {
            background-color: #f0f0f0;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 2rem;
        }
        
        .page-title {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: center;
        }

        .info-banner {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            text-align: center;
        }

        .info-banner h3 {
            margin-bottom: 0.5rem;
        }
        
        .section {
            background: white;
            border-radius: 10px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .section h2 {
            margin-bottom: 1.5rem;
            color: #667eea;
            border-bottom: 2px solid #667eea;
            padding-bottom: 0.5rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: bold;
            color: #555;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .form-group textarea {
            height: 80px;
            resize: vertical;
        }
        
        .btn {
            background: #667eea;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            transition: background-color 0.3s;
        }
        
        .btn:hover {
            background: #5a6fd8;
        }
        
        .btn-success {
            background: #28a745;
        }
        
        .btn-success:hover {
            background: #218838;
        }

        .btn-danger {
            background: #dc3545;
            font-size: 0.8rem;
            padding: 0.4rem 0.8rem;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-sm {
            font-size: 0.8rem;
            padding: 0.4rem 0.8rem;
        }
        
        .members-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .member-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 1rem;
            background: #f8f9fa;
            position: relative;
        }
        
        .member-card h4 {
            color: #667eea;
            margin-bottom: 0.5rem;
        }
        
        .member-info {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 1rem;
        }

        .member-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .guests-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            font-size: 0.9rem;
        }
        
        .guests-table th,
        .guests-table td {
            border: 1px solid #ddd;
            padding: 0.75rem;
            text-align: left;
        }
        
        .guests-table th {
            background: #f8f9fa;
            font-weight: bold;
        }
        
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        
        .status-pending { background: #fff3cd; color: #856404; }
        .status-approved { background: #d4edda; color: #155724; }
        .status-rejected { background: #f8d7da; color: #721c24; }
        .status-departed { background: #d1ecf1; color: #0c5460; }
        
        .message {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        
        .message.success { background: #d4edda; color: #155724; }
        .message.error { background: #f8d7da; color: #721c24; }
        
        .tab-container {
            margin-bottom: 2rem;
        }
        
        .tab-buttons {
            display: flex;
            border-bottom: 1px solid #ddd;
            margin-bottom: 2rem;
        }
        
        .tab-button {
            background: none;
            border: none;
            padding: 1rem 1.5rem;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.3s;
            font-size: 1rem;
        }
        
        .tab-button.active {
            border-bottom-color: #667eea;
            color: #667eea;
            font-weight: bold;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .members-grid {
                grid-template-columns: 1fr;
            }
            
            .guests-table {
                font-size: 0.8rem;
            }
            
            .nav-links {
                flex-direction: column;
                gap: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <nav class="nav">
            <a href="index.php" class="logo">🏠 Ubuzima Hub</a>
            <div class="nav-links">
                <a href="index.php">Accueil</a>
                <a href="household_management.php">Carnet de Ménage</a>
                <a href="official_communications.php">Communiqués
                    <?php if ($unread_notifications > 0): ?>
                        <span class="notification-badge"><?= $unread_notifications ?></span>
                    <?php endif; ?>
                </a>
                <a href="logout.php">Déconnexion</a>
            </div>
        </nav>
    </header>

    <div class="container">
        <?php 
        $message = getMessage();
        if ($message): 
        ?>
            <div class="message <?= $message['type'] ?>">
                <?= htmlspecialchars($message['text']) ?>
            </div>
        <?php endif; ?>

        <div class="page-title">
            <h1>📋 <?= getLanguage() === 'fr' ? 'Carnet de Ménage' : 'Igitabo cy\'umuryango' ?></h1>
            <p><?= getLanguage() === 'fr' ? 'Gérez votre famille et vos invités en toute transparence' : 'Cungira umuryango wawe n\'abashyitsi bawe mu buryo bunyuze' ?></p>
        </div>

        <div class="info-banner">
            <h3>🛡️ Pourquoi utiliser le Carnet de Ménage ?</h3>
            <p>Évitez les amendes et la corruption lors des contrôles policiers. Déclarez vos invités facilement et les autorités seront automatiquement notifiées !</p>
        </div>

        <div class="stats-cards">
            <div class="stat-card">
                <div class="stat-number"><?= count($members) ?></div>
                <div class="stat-label">Membres Famille</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= count(array_filter($guests, function($g) { return $g['status'] === 'pending'; })) ?></div>
                <div class="stat-label">Invités en Attente</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= count(array_filter($guests, function($g) { return $g['status'] === 'approved'; })) ?></div>
                <div class="stat-label">Invités Approuvés</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $unread_notifications ?></div>
                <div class="stat-label">Notifications</div>
            </div>
        </div>

        <div class="tab-container">
            <div class="tab-buttons">
                <button class="tab-button active" onclick="showTab('members')">
                    👨‍👩‍👧‍👦 <?= getLanguage() === 'fr' ? 'Membres de la Famille' : 'Abagize Umuryango' ?>
                </button>
                <button class="tab-button" onclick="showTab('guests')">
                    🏠 <?= getLanguage() === 'fr' ? 'Invités' : 'Abashyitsi' ?>
                </button>
            </div>

            <!-- Onglet Membres de la Famille -->
            <div id="members" class="tab-content active">
                <div class="section">
                    <h2><?= getLanguage() === 'fr' ? 'Ajouter un Membre de la Famille' : 'Kongeramo Umunyamuryango' ?></h2>
                    
                    <form method="POST">
                        <input type="hidden" name="action" value="add_member">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label><?= getLanguage() === 'fr' ? 'Nom complet' : 'Amazina yose' ?></label>
                                <input type="text" name="full_name" required>
                            </div>
                            
                            <div class="form-group">
                                <label><?= getLanguage() === 'fr' ? 'Relation familiale' : 'Ubusabane' ?></label>
                                <select name="relationship" required>
                                    <option value="chef_famille"><?= getLanguage() === 'fr' ? 'Chef de famille' : 'Umutware w\'umuryango' ?></option>
                                    <option value="epoux"><?= getLanguage() === 'fr' ? 'Époux/Épouse' : 'Umugore/Umugabo' ?></option>
                                    <option value="enfant"><?= getLanguage() === 'fr' ? 'Enfant' : 'Umwana' ?></option>
                                    <option value="parent"><?= getLanguage() === 'fr' ? 'Parent' : 'Umubyeyi' ?></option>
                                    <option value="autre"><?= getLanguage() === 'fr' ? 'Autre' : 'Indi' ?></option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label><?= getLanguage() === 'fr' ? 'Numéro de carte d\'identité' : 'Nimero y\'indangamuntu' ?></label>
                                <input type="text" name="id_card_number">
                            </div>
                            
                            <div class="form-group">
                                <label><?= getLanguage() === 'fr' ? 'Date de naissance' : 'Itariki y\'amavuko' ?></label>
                                <input type="date" name="date_of_birth">
                            </div>
                            
                            <div class="form-group">
                                <label><?= getLanguage() === 'fr' ? 'Genre' : 'Igitsina' ?></label>
                                <select name="gender" required>
                                    <option value="M"><?= getLanguage() === 'fr' ? 'Masculin' : 'Gabo' ?></option>
                                    <option value="F"><?= getLanguage() === 'fr' ? 'Féminin' : 'Gore' ?></option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label><?= getLanguage() === 'fr' ? 'Téléphone' : 'Terefone' ?></label>
                                <input type="tel" name="phone_number">
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-success">
                            <?= getLanguage() === 'fr' ? 'Ajouter le Membre' : 'Kongeramo Umunyamuryango' ?>
                        </button>
                    </form>
                </div>

                <div class="section">
                    <h2><?= getLanguage() === 'fr' ? 'Membres Enregistrés' : 'Abagize Umuryango Biyandikishije' ?></h2>
                    
                    <?php if (empty($members)): ?>
                        <p><?= getLanguage() === 'fr' ? 'Aucun membre enregistré pour le moment.' : 'Nta munyamuryango wiyandikishije ubu.' ?></p>
                    <?php else: ?>
                        <div class="members-grid">
                            <?php foreach ($members as $member): ?>
                                <div class="member-card">
                                    <h4><?= htmlspecialchars($member['full_name']) ?></h4>
                                    <div class="member-info">
                                        <p><strong><?= getLanguage() === 'fr' ? 'Relation:' : 'Ubusabane:' ?></strong> <?= htmlspecialchars($member['relationship']) ?></p>
                                        <?php if ($member['id_card_number']): ?>
                                            <p><strong><?= getLanguage() === 'fr' ? 'CIN:' : 'Indangamuntu:' ?></strong> <?= htmlspecialchars($member['id_card_number']) ?></p>
                                        <?php endif; ?>
                                        <?php if ($member['phone_number']): ?>
                                            <p><strong><?= getLanguage() === 'fr' ? 'Tél:' : 'Terefone:' ?></strong> <?= htmlspecialchars($member['phone_number']) ?></p>
                                        <?php endif; ?>
                                        <p><strong><?= getLanguage() === 'fr' ? 'Genre:' : 'Igitsina:' ?></strong> <?= $member['gender'] === 'M' ? (getLanguage() === 'fr' ? 'Masculin' : 'Gabo') : (getLanguage() === 'fr' ? 'Féminin' : 'Gore') ?></p>
                                        <p><strong><?= getLanguage() === 'fr' ? 'Ajouté le:' : 'Yiyandikishije ku wa:' ?></strong> <?= date('d/m/Y', strtotime($member['created_at'])) ?></p>
                                    </div>
                                    <div class="member-actions">
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce membre ?')">
                                            <input type="hidden" name="action" value="delete_member">
                                            <input type="hidden" name="member_id" value="<?= $member['member_id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-sm">Supprimer</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Onglet Invités -->
            <div id="guests" class="tab-content">
                <div class="section">
                    <h2><?= getLanguage() === 'fr' ? 'Déclarer un Invité' : 'Kwemeza Umushyitsi' ?></h2>
                    
                    <?php if (empty($members)): ?>
                        <div class="message error">
                            <?= getLanguage() === 'fr' ? 'Vous devez d\'abord enregistrer au moins un membre de famille avant de déclarer des invités.' : 'Mbere ugomba kwiyandikisha nibura umwe mu bagize umuryango mbere yo kwemeza abashyitsi.' ?>
                        </div>
                    <?php else: ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="add_guest">
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label><?= getLanguage() === 'fr' ? 'Hébergé par' : 'Wakiriye' ?></label>
                                    <select name="host_member_id" required>
                                        <?php foreach ($members as $member): ?>
                                            <option value="<?= $member['member_id'] ?>"><?= htmlspecialchars($member['full_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label><?= getLanguage() === 'fr' ? 'Nom de l\'invité' : 'Amazina y\'umushyitsi' ?></label>
                                    <input type="text" name="guest_name" required>
                                </div>
                                
                                <div class="form-group">
                                    <label><?= getLanguage() === 'fr' ? 'Carte d\'identité' : 'Indangamuntu' ?></label>
                                    <input type="text" name="guest_id_card">
                                </div>
                                
                                <div class="form-group">
                                    <label><?= getLanguage() === 'fr' ? 'Téléphone' : 'Terefone' ?></label>
                                    <input type="tel" name="guest_phone">
                                </div>
                                
                                <div class="form-group">
                                    <label><?= getLanguage() === 'fr' ? 'Date d\'arrivée' : 'Itariki y\'ukugera' ?></label>
                                    <input type="date" name="arrival_date" value="<?= date('Y-m-d') ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label><?= getLanguage() === 'fr' ? 'Date de départ prévue' : 'Itariki y\'ugusubira' ?></label>
                                    <input type="date" name="departure_date" value="<?= date('Y-m-d', strtotime('+3 days')) ?>" required>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label><?= getLanguage() === 'fr' ? 'Adresse d\'origine' : 'Aho ava' ?></label>
                                <textarea name="origin_address" placeholder="Commune, Zone, Colline..."></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label><?= getLanguage() === 'fr' ? 'Motif de la visite' : 'Impamvu y\'urushylo' ?></label>
                                <textarea name="purpose" placeholder="Motif de la visite..." required></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-success">
                                <?= getLanguage() === 'fr' ? 'Déclarer l\'Invité' : 'Kwemeza Umushyitsi' ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="section">
                    <h2><?= getLanguage() === 'fr' ? '....' : '...' ?></div>
                </div>