<?php
require_once 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';

// Bujumbura communes and zones
$communes = [
    'Bujumbura Mairie' => ['Rohero', 'Nyakabiga', 'Buyenzi', 'Bwiza', 'Kinama', 'Kamenge', 'Gihosha', 'Kanyosha', 'Mutanga Nord', 'Mutanga Sud'],
    'Bujumbura Rural' => ['Kabezi', 'Kanyosha', 'Mutambu', 'Mugongomanga', 'Mukike', 'Mubimbi', 'Isale']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $fullName = sanitizeInput($_POST['full_name'] ?? '');
    $commune = sanitizeInput($_POST['commune'] ?? '');
    $zone = sanitizeInput($_POST['zone'] ?? '');
    $address = sanitizeInput($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($phone) || empty($fullName) || empty($commune) || empty($zone) || empty($password)) {
        $error = 'Veuillez remplir tous les champs obligatoires / Uzuza byose bikenewe';
    } elseif (strlen($password) < 6) {
        $error = 'Le mot de passe doit contenir au moins 6 caractères / Ijambo ryibanga rigomba kuba rifite byibura inyuguti 6';
    } elseif ($password !== $confirmPassword) {
        $error = 'Les mots de passe ne correspondent pas / Amagambo yibanga ntabwo ahuje';
    } elseif (!preg_match('/^\+257[0-9]{8}$/', $phone)) {
        $error = 'Format de téléphone invalide. Utilisez +25779123456 / Uburyo bwa terefone sibunyangenge';
    } else {
        $db = new Database();
        $conn = $db->getConnection();
        
        // Check if phone number already exists
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE phone_number = ?");
        $stmt->bind_param("s", $phone);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $error = 'Ce numéro de téléphone est déjà utilisé / Iri nimero ya terefone rirakozwe';
        } else {
            // Check email if provided
            if (!empty($email)) {
                $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    $error = 'Cette adresse email est déjà utilisée / Iyi email irakozwe';
                }
            }
            
            if (empty($error)) {
                // Create new user
                $passwordHash = password_hash($password, HASH_ALGORITHM);
                $stmt = $conn->prepare("INSERT INTO users (phone_number, email, full_name, commune, zone, address, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssss", $phone, $email, $fullName, $commune, $zone, $address, $passwordHash);
                
                if ($stmt->execute()) {
                    $success = 'Compte créé avec succès! Vous pouvez maintenant vous connecter. / Konti yakoze neza! Mwashobora kwinjira.';
                    // Clear form data
                    $phone = $email = $fullName = $commune = $zone = $address = '';
                } else {
                    $error = 'Erreur lors de la création du compte / Habaye ikosa mu gukora konti';
                }
            }
        }
        
        $db->close();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - Ubuzima Hub</title>
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
            padding: 2rem 0;
        }
        
        .register-container {
            background: white;
            border-radius: 15px;
            padding: 3rem;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .logo h1 {
            color: #667eea;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .logo p {
            color: #666;
            font-size: 0.9rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }
        
        .required {
            color: #e74c3c;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .phone-input {
            position: relative;
        }
        
        .phone-prefix {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            font-weight: 500;
        }
        
        .phone-input input {
            padding-left: 4rem;
        }
        
        .register-btn {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease;
        }
        
        .register-btn:hover {
            transform: translateY(-2px);
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        
        .login-link {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e1e5e9;
        }
        
        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            .register-container {
                padding: 2rem;
                margin: 1rem;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="logo">
            <h1>🌍 Ubuzima Hub</h1>
            <p>Créez votre compte citoyen / Fungura konti yawe</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success-message">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-row">
                <div class="form-group">
                    <label for="full_name">
                        👤 Nom complet / Amazina yose <span class="required">*</span>
                    </label>
                    <input 
                        type="text" 
                        id="full_name" 
                        name="full_name" 
                        placeholder="Jean Baptiste Ndayishimiye" 
                        value="<?= htmlspecialchars($fullName ?? '') ?>"
                        required
                    >
                </div>
                
                <div class="form-group">
                    <label for="phone">
                        📱 Téléphone / Terefone <span class="required">*</span>
                    </label>
                    <div class="phone-input">
                        <span class="phone-prefix">+257</span>
                        <input 
                            type="tel" 
                            id="phone" 
                            name="phone" 
                            placeholder="79123456" 
                            value="<?= htmlspecialchars(str_replace('+257', '', $phone ?? '')) ?>"
                            pattern="[0-9]{8}"
                            required
                        >
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="email">
                    📧 Email (optionnel / ntikigomba)
                </label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    placeholder="jean@example.com" 
                    value="<?= htmlspecialchars($email ?? '') ?>"
                >
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="commune">
                        🏛️ Commune <span class="required">*</span>
                    </label>
                    <select id="commune" name="commune" required>
                        <option value="">Choisissez votre commune</option>
                        <?php foreach ($communes as $communeName => $zones): ?>
                            <option value="<?= $communeName ?>" <?= ($commune ?? '') === $communeName ? 'selected' : '' ?>>
                                <?= $communeName ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="zone">
                        📍 Zone <span class="required">*</span>
                    </label>
                    <select id="zone" name="zone" required>
                        <option value="">Choisissez d'abord la commune</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="address">
                    🏠 Adresse détaillée / Aho ubana
                </label>
                <textarea 
                    id="address" 
                    name="address" 
                    rows="2" 
                    placeholder="Avenue de la Paix, Quartier 1, près de l'école..."
                ><?= htmlspecialchars($address ?? '') ?></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="password">
                        🔒 Mot de passe / Ijambo ryibanga <span class="required">*</span>
                    </label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="••••••••"
                        minlength="6"
                        required
                    >
                    <small style="color: #666;">Minimum 6 caractères</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">
                        🔒 Confirmer le mot de passe <span class="required">*</span>
                    </label>
                    <input 
                        type="password" 
                        id="confirm_password" 
                        name="confirm_password" 
                        placeholder="••••••••"
                        required
                    >
                </div>
            </div>
            
            <button type="submit" class="register-btn">
                Créer mon compte / Fungura konti
            </button>
        </form>
        
        <div class="login-link">
            <p>Déjà un compte? / Usanzwe ufite konti?</p>
            <a href="login.php">Se connecter / Injira</a>
        </div>
    </div>
    
    <script>
        // Communes and zones data
        const communesData = <?= json_encode($communes) ?>;
        
        // Auto-format phone number
        const phoneInput = document.getElementById('phone');
        phoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 8) {
                value = value.slice(0, 8);
            }
            e.target.value = value;
        });
        
        // Handle commune selection
        const communeSelect = document.getElementById('commune');
        const zoneSelect = document.getElementById('zone');
        
        communeSelect.addEventListener('change', function() {
            const selectedCommune = this.value;
            zoneSelect.innerHTML = '<option value="">Choisissez votre zone</option>';
            
            if (selectedCommune && communesData[selectedCommune]) {
                communesData[selectedCommune].forEach(zone => {
                    const option = document.createElement('option');
                    option.value = zone;
                    option.textContent = zone;
                    zoneSelect.appendChild(option);
                });
            }
        });
        
        // Trigger commune change if there's a pre-selected value
        if (communeSelect.value) {
            communeSelect.dispatchEvent(new Event('change'));
            // Select the zone if it was previously selected
            const selectedZone = '<?= htmlspecialchars($zone ?? '') ?>';
            if (selectedZone) {
                setTimeout(() => {
                    zoneSelect.value = selectedZone;
                }, 100);
            }
        }
        
        // Password confirmation validation
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        
        function validatePasswords() {
            if (password.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('Les mots de passe ne correspondent pas');
            } else {
                confirmPassword.setCustomValidity('');
            }
        }
        
        password.addEventListener('change', validatePasswords);
        confirmPassword.addEventListener('keyup', validatePasswords);
        
        // Form submission - add +257 prefix
        document.querySelector('form').addEventListener('submit', function(e) {
            const phone = phoneInput.value;
            if (phone && !phone.startsWith('+257')) {
                phoneInput.value = '+257' + phone;
            }
        });
    </script>
</body>
</html>