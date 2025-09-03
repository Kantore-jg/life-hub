<?php
// Database configuration for Ubuzima Hub
define('DB_HOST', 'localhost');
define('DB_NAME', 'ubuzima_hub');
define('DB_USER', 'root');
define('DB_PASS', ''); // Usually empty for localhost XAMPP/WAMP

// Application configuration
define('BASE_URL', 'http://localhost/ubuzima-hub/');
define('UPLOAD_PATH', 'uploads/');

// Security settings
define('SESSION_LIFETIME', 7200); // 2 hours in seconds
define('HASH_ALGORITHM', PASSWORD_DEFAULT);

// Database connection class
class Database {
    private $connection;
    
    public function __construct() {
        try {
            $this->connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            if ($this->connection->connect_error) {
                throw new Exception("Connection failed: " . $this->connection->connect_error);
            }
            
            // Set charset to UTF-8 for proper Kirundi/French support
            $this->connection->set_charset("utf8mb4");
            
        } catch (Exception $e) {
            die("Database connection error: " . $e->getMessage());
        }
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function close() {
        if ($this->connection) {
            $this->connection->close();
        }
    }
}

// Session management functions
function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function isLoggedIn() {
    startSession();
    return isset($_SESSION['user_id']) && isset($_SESSION['session_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("SELECT user_id, phone_number, email, full_name, user_type, commune, zone FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    
    $db->close();
    return $user;
}

function sanitizeInput($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

function generateSessionId() {
    return bin2hex(random_bytes(32));
}

// Language support
function getLanguage() {
    startSession();
    return $_SESSION['language'] ?? 'fr'; // Default to French
}

function setLanguage($lang) {
    startSession();
    $_SESSION['language'] = in_array($lang, ['fr', 'rn']) ? $lang : 'fr';
}

// Simple translation function
function t($key, $lang = null) {
    if (!$lang) $lang = getLanguage();
    
    $translations = [
        'fr' => [
            'water' => 'Eau',
            'electricity' => 'Électricité',
            'waste' => 'Déchets',
            'roads' => 'Routes',
            'security' => 'Sécurité',
            'healthcare' => 'Santé',
            'education' => 'Éducation',
            'report_issue' => 'Signaler un problème',
            'pay_taxes' => 'Payer les taxes',
            'transport_info' => 'Info transport',
            'login' => 'Se connecter',
            'register' => 'S\'inscrire',
            'logout' => 'Déconnexion'
        ],
        'rn' => [
            'water' => 'Amazi',
            'electricity' => 'Amashanyarazi',
            'waste' => 'Imyanda',
            'roads' => 'Inzira',
            'security' => 'Umutekano',
            'healthcare' => 'Ubuvuzi',
            'education' => 'Uburezi',
            'report_issue' => 'Menya ikibazo',
            'pay_taxes' => 'Kwishyura imisoro',
            'transport_info' => 'Amakuru y\'ubwikorezi',
            'login' => 'Injira',
            'register' => 'Iyandikishe',
            'logout' => 'Gusohoka'
        ]
    ];
    
    return $translations[$lang][$key] ?? $key;
}

// Error and success message handling
function setMessage($message, $type = 'info') {
    startSession();
    $_SESSION['message'] = ['text' => $message, 'type' => $type];
}

function getMessage() {
    startSession();
    if (isset($_SESSION['message'])) {
        $message = $_SESSION['message'];
        unset($_SESSION['message']);
        return $message;
    }
    return null;
}
?>