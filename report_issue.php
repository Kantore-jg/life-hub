<?php
// Database configuration
$host = 'localhost';
$dbname = 'ubuzima_hub';
$username = 'root';
$password = '';

// Create database connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Erreur de connexion: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $problem_type = $_POST['problem_type'];
        $description = $_POST['description'];
        $location = $_POST['location'];
        $citizen_name = $_POST['citizen_name'];
        $citizen_phone = $_POST['citizen_phone'];
        $citizen_email = $_POST['citizen_email'] ?? null;
        $priority = $_POST['priority'] ?? 'medium';
        
        // Handle image upload
        $image_path = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
            $upload_dir = 'uploads/reports/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array(strtolower($file_extension), $allowed_extensions)) {
                $filename = uniqid() . '.' . $file_extension;
                $image_path = $upload_dir . $filename;
                move_uploaded_file($_FILES['image']['tmp_name'], $image_path);
            }
        }
        
        // Generate unique report ID
        $report_id = 'RPT-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Insert into database
        $sql = "INSERT INTO reports (report_id, problem_type, description, location, citizen_name, citizen_phone, citizen_email, priority, image_path, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $report_id, $problem_type, $description, $location, 
            $citizen_name, $citizen_phone, $citizen_email, $priority, $image_path
        ]);
        
        $success_message = "Votre signalement a été enregistré avec succès! Numéro de référence: " . $report_id;
        
    } catch(PDOException $e) {
        $error_message = "Erreur lors de l'enregistrement: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signaler un Problème - Ubuzima Hub</title>
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
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .form-container {
            padding: 40px;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 1rem;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: #fafbfc;
        }

        .form-control:focus {
            outline: none;
            border-color: #4facfe;
            background-color: white;
            box-shadow: 0 0 0 3px rgba(79, 172, 254, 0.1);
        }

        select.form-control {
            cursor: pointer;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 120px;
        }

        .priority-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 10px;
        }

        .priority-option {
            position: relative;
        }

        .priority-option input[type="radio"] {
            position: absolute;
            opacity: 0;
        }

        .priority-option label {
            display: block;
            padding: 12px 20px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .priority-option input[type="radio"]:checked + label {
            border-color: #4facfe;
            background-color: #4facfe;
            color: white;
        }

        .priority-low label { border-color: #28a745; }
        .priority-low input[type="radio"]:checked + label { background-color: #28a745; }

        .priority-medium label { border-color: #ffc107; }
        .priority-medium input[type="radio"]:checked + label { background-color: #ffc107; color: #333; }

        .priority-high label { border-color: #dc3545; }
        .priority-high input[type="radio"]:checked + label { background-color: #dc3545; }

        .file-upload {
            position: relative;
            display: inline-block;
            cursor: pointer;
            width: 100%;
        }

        .file-upload input[type="file"] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .file-upload-label {
            display: block;
            padding: 12px 15px;
            border: 2px dashed #4facfe;
            border-radius: 8px;
            text-align: center;
            background-color: #f8f9fa;
            color: #4facfe;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .file-upload:hover .file-upload-label {
            background-color: #4facfe;
            color: white;
        }

        .btn {
            display: inline-block;
            padding: 15px 30px;
            font-size: 1.1rem;
            font-weight: 600;
            text-decoration: none;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(79, 172, 254, 0.3);
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
            margin-right: 15px;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
        }

        .form-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e1e5e9;
        }

        @media (max-width: 768px) {
            .container {
                margin: 10px;
            }
            
            .form-container {
                padding: 20px;
            }
            
            .header {
                padding: 20px;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .form-footer {
                flex-direction: column;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚨 Signaler un Problème</h1>
            <p>Aidez-nous à améliorer votre commune en signalant les problèmes</p>
        </div>

        <div class="form-container">
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    ✅ <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-error">
                    ❌ <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="problem_type">Type de problème *</label>
                    <select name="problem_type" id="problem_type" class="form-control" required>
                        <option value="">Sélectionnez le type de problème</option>
                        <option value="eau">💧 Problème d'eau</option>
                        <option value="electricite">⚡ Problème d'électricité</option>
                        <option value="routes">🛣️ Routes en mauvais état</option>
                        <option value="ordures">🗑️ Gestion des ordures</option>
                        <option value="transport">🚌 Transport public</option>
                        <option value="securite">🚔 Sécurité</option>
                        <option value="autre">🔧 Autre</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="description">Description détaillée *</label>
                    <textarea name="description" id="description" class="form-control" required 
                              placeholder="Décrivez le problème en détail..."></textarea>
                </div>

                <div class="form-group">
                    <label for="location">Localisation *</label>
                    <input type="text" name="location" id="location" class="form-control" required
                           placeholder="Ex: Avenue de la Révolution, Quartier Rohero">
                </div>

                <div class="form-group">
                    <label>Niveau de priorité</label>
                    <div class="priority-group">
                        <div class="priority-option priority-low">
                            <input type="radio" name="priority" value="low" id="priority_low">
                            <label for="priority_low">🟢 Faible</label>
                        </div>
                        <div class="priority-option priority-medium">
                            <input type="radio" name="priority" value="medium" id="priority_medium" checked>
                            <label for="priority_medium">🟡 Moyen</label>
                        </div>
                        <div class="priority-option priority-high">
                            <input type="radio" name="priority" value="high" id="priority_high">
                            <label for="priority_high">🔴 Urgent</label>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Photo (optionnelle)</label>
                    <div class="file-upload">
                        <input type="file" name="image" accept="image/*">
                        <span class="file-upload-label">
                            📷 Cliquez pour ajouter une photo
                        </span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="citizen_name">Votre nom *</label>
                    <input type="text" name="citizen_name" id="citizen_name" class="form-control" required
                           placeholder="Nom complet">
                </div>

                <div class="form-group">
                    <label for="citizen_phone">Numéro de téléphone *</label>
                    <input type="tel" name="citizen_phone" id="citizen_phone" class="form-control" required
                           placeholder="+257 XX XX XX XX">
                </div>

                <div class="form-group">
                    <label for="citizen_email">Email (optionnel)</label>
                    <input type="email" name="citizen_email" id="citizen_email" class="form-control"
                           placeholder="votre.email@exemple.com">
                </div>

                <div class="form-footer">
                    <a href="index.php" class="btn btn-secondary">← Retour à l'accueil</a>
                    <button type="submit" class="btn btn-primary">Soumettre le signalement</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // File upload preview
        document.querySelector('input[type="file"]').addEventListener('change', function(e) {
            const label = document.querySelector('.file-upload-label');
            if (e.target.files.length > 0) {
                label.textContent = '📷 ' + e.target.files[0].name;
                label.style.color = '#28a745';
            }
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const requiredFields = ['problem_type', 'description', 'location', 'citizen_name', 'citizen_phone'];
            let isValid = true;

            requiredFields.forEach(field => {
                const input = document.querySelector(`[name="${field}"]`);
                if (!input.value.trim()) {
                    input.style.borderColor = '#dc3545';
                    isValid = false;
                } else {
                    input.style.borderColor = '#e1e5e9';
                }
            });

            if (!isValid) {
                e.preventDefault();
                alert('Veuillez remplir tous les champs obligatoires.');
            }
        });
    </script>
</body>
</html>