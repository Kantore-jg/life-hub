<?php
require_once 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($phone) || empty($password)) {
        $error = 'Veuillez remplir tous les champs / Uzuza byose';
    } else {
        $db = new Database();
        $conn = $db->getConnection();
        
        // Check user credentials
        $stmt = $conn->prepare("SELECT user_id, password_hash, full_name, user_type FROM users WHERE phone_number = ?");
        $stmt->bind_param("s", $phone);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($user = $result->fetch_assoc()) {
            if (password_verify($password, $user['password_hash'])) {
                // Login successful
                startSession();
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['session_id'] = generateSessionId();
                
                // Store session in database
                $stmt2 = $conn->prepare("INSERT INTO user_sessions (session_id, user_id, ip_address, user_agent, expires_at) VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))");
                $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                $sessionId = $_SESSION['session_id'];
                $userId = $user['user_id'];
                $sessionLifetime = SESSION_LIFETIME;
                $stmt2->bind_param("sissi", $sessionId, $userId, $ip, $userAgent, $sessionLifetime);
                $stmt2->execute();
                
                setMessage('Connexion réussie! / Mwiriwe neza!', 'success');
                header('Location: index.php');
                exit();
            } else {
                $error = 'Mot de passe incorrect / Ijambo ryibanga sibyo';
            }
        } else {
            $error = 'Numéro de téléphone non trouvé / Nimero ya terefone ntiboneka';
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
    <title>Connexion - Ubuzima Hub</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        
        .login-container {
            background: white;
            border-radius: 15px;
            padding: 3rem;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 450px;
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
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .login-btn {
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
        
        .login-btn:hover {
            transform: translateY(-2px);
        }
        
        .login-btn:active {
            transform: translateY(0);
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        
        .register-link {
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e1e5e9;
        }
        
        .register-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .register-link a:hover {
            text-decoration: underline;
        }
        
        .demo-credentials {
            background: #e7f3ff;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }
        
        .demo-credentials h4 {
            color: #0066cc;
            margin-bottom: 0.5rem;
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
        
        @media (max-width: 768px) {
            .login-container {
                padding: 2rem;
                margin: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>🌍 Ubuzima Hub</h1>
            <p>Plateforme citoyenne pour Bujumbura</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error-message">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <div class="demo-credentials">
            <h4>🔧 Compte de test / Test Account:</h4>
            <p><strong>Téléphone:</strong> +25779000000</p>
            <p><strong>Mot de passe:</strong> admin123</p>
        </div>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="phone">
                    📱 Numéro de téléphone / Nimero ya terefone
                </label>
                <div class="phone-input">
                    <span class="phone-prefix">+257</span>
                    <input 
                        type="tel" 
                        id="phone" 
                        name="phone" 
                        placeholder="79123456" 
                        value="<?= htmlspecialchars($phone ?? '') ?>"
                        pattern="[0-9]{8}"
                        required
                    >
                </div>
                <small style="color: #666; font-size: 0.85rem;">Format: +25779123456</small>
            </div>
            
            <div class="form-group">
                <label for="password">
                    🔒 Mot de passe / Ijambo ryibanga
                </label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    placeholder="••••••••"
                    required
                >
            </div>
            
            <button type="submit" class="login-btn">
                Se connecter / Injira
            </button>
        </form>
        
        <div class="register-link">
            <p>Pas de compte? / Ntufite konti?</p>
            <a href="register.php">Créer un compte / Fungura konti</a>
        </div>
    </div>
    
    <script>
        // Auto-format phone number
        const phoneInput = document.getElementById('phone');
        phoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, ''); // Remove non-digits
            if (value.length > 8) {
                value = value.slice(0, 8);
            }
            e.target.value = value;
        });
        
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