<?php
require_once 'config.php';

startSession();

if (isLoggedIn()) {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Delete session from database
    if (isset($_SESSION['session_id'])) {
        $stmt = $conn->prepare("DELETE FROM user_sessions WHERE session_id = ?");
        $stmt->bind_param("s", $_SESSION['session_id']);
        $stmt->execute();
    }
    
    $db->close();
    
    // Clear session data
    session_destroy();
    
    setMessage('Vous avez été déconnecté avec succès / Wasohotse neza', 'success');
}

header('Location: login.php');
exit();
?>