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
        $tax_type = $_POST['tax_type'];
        $taxpayer_name = $_POST['taxpayer_name'];
        $taxpayer_phone = $_POST['taxpayer_phone'];
        $taxpayer_email = $_POST['taxpayer_email'] ?? null;
        $taxpayer_nif = $_POST['taxpayer_nif'] ?? null;
        $property_address = $_POST['property_address'] ?? null;
        $amount = floatval($_POST['amount']);
        $payment_method = $_POST['payment_method'];
        $payment_reference = $_POST['payment_reference'] ?? null;
        
        // Generate unique transaction ID
        $transaction_id = 'TAX-' . date('Y') . '-' . str_pad(rand(1, 99999), 5, '0', STR_PAD_LEFT);
        
        // Insert payment record
        $sql = "INSERT INTO tax_payments (
            transaction_id, tax_type, taxpayer_name, taxpayer_phone, taxpayer_email, 
            taxpayer_nif, property_address, amount, payment_method, payment_reference,
            status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $transaction_id, $tax_type, $taxpayer_name, $taxpayer_phone, $taxpayer_email,
            $taxpayer_nif, $property_address, $amount, $payment_method, $payment_reference
        ]);
        
        $success_message = "Votre paiement a été enregistré! Numéro de transaction: " . $transaction_id;
        $payment_success = true;
        
    } catch(PDOException $e) {
        $error_message = "Erreur lors du paiement: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payer les Taxes - Ubuzima Hub</title>
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
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
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

        .payment-container {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 0;
            min-height: 600px;
        }

        .form-section {
            padding: 40px;
        }

        .summary-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
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

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
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
            border-color: #f5576c;
            background-color: white;
            box-shadow: 0 0 0 3px rgba(245, 87, 108, 0.1);
        }

        .tax-type-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 10px;
        }

        .tax-type-option {
            position: relative;
        }

        .tax-type-option input[type="radio"] {
            position: absolute;
            opacity: 0;
        }

        .tax-type-option label {
            display: block;
            padding: 20px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }

        .tax-type-option input[type="radio"]:checked + label {
            border-color: #f5576c;
            background-color: #f5576c;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(245, 87, 108, 0.3);
        }

        .tax-icon {
            font-size: 2rem;
            display: block;
            margin-bottom: 10px;
        }

        .payment-methods {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 15px;
            margin-top: 10px;
        }

        .payment-method {
            position: relative;
        }

        .payment-method input[type="radio"] {
            position: absolute;
            opacity: 0;
        }

        .payment-method label {
            display: block;
            padding: 15px 10px;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .payment-method input[type="radio"]:checked + label {
            border-color: #28a745;
            background-color: #28a745;
            color: white;
        }

        .amount-display {
            background: rgba(255,255,255,0.1);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 20px;
        }

        .amount-display h3 {
            font-size: 1.2rem;
            margin-bottom: 10px;
            opacity: 0.9;
        }

        .amount-value {
            font-size: 2.5rem;
            font-weight: bold;
            color: #fff;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }

        .summary-item:last-child {
            border-bottom: none;
            font-weight: bold;
            font-size: 1.1rem;
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
            width: 100%;
        }

        .btn-primary {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(245, 87, 108, 0.3);
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
            margin-bottom: 15px;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
        }

        .payment-success {
            text-align: center;
            padding: 40px;
        }

        .success-icon {
            font-size: 4rem;
            color: #28a745;
            margin-bottom: 20px;
        }

        .transaction-details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }

        .security-note {
            background: rgba(255,255,255,0.1);
            padding: 15px;
            border-radius: 8px;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        @media (max-width: 768px) {
            .container {
                margin: 10px;
            }
            
            .payment-container {
                grid-template-columns: 1fr;
            }
            
            .form-section,
            .summary-section {
                padding: 20px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .header h1 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💰 Payer les Taxes</h1>
            <p>Payez vos taxes facilement et en toute sécurité</p>
        </div>

        <?php if (isset($payment_success) && $payment_success): ?>
            <div class="payment-success">
                <div class="success-icon">✅</div>
                <h2>Paiement Enregistré!</h2>
                <div class="transaction-details">
                    <h3>Détails de la transaction:</h3>
                    <p><strong>Numéro:</strong> <?php echo htmlspecialchars($transaction_id); ?></p>
                    <p><strong>Montant:</strong> <?php echo number_format($amount, 0, ',', ' '); ?> BIF</p>
                    <p><strong>Type:</strong> <?php echo htmlspecialchars($tax_type); ?></p>
                    <p><strong>Statut:</strong> En attente de confirmation</p>
                </div>
                <p>Vous recevrez une confirmation par SMS/Email une fois le paiement validé.</p>
                <br>
                <a href="index.php" class="btn btn-primary">Retour à l'accueil</a>
            </div>
        <?php else: ?>
            <div class="payment-container">
                <div class="form-section">
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-error">
                            ❌ <?php echo htmlspecialchars($error_message); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="paymentForm">
                        <div class="form-group">
                            <label>Type de taxe *</label>
                            <div class="tax-type-grid">
                                <div class="tax-type-option">
                                    <input type="radio" name="tax_type" value="propriete" id="tax_propriete" required>
                                    <label for="tax_propriete">
                                        <span class="tax-icon">🏠</span>
                                        Taxe Foncière
                                    </label>
                                </div>
                                <div class="tax-type-option">
                                    <input type="radio" name="tax_type" value="commerce" id="tax_commerce">
                                    <label for="tax_commerce">
                                        <span class="tax-icon">🏪</span>
                                        Taxe Commerciale
                                    </label>
                                </div>
                                <div class="tax-type-option">
                                    <input type="radio" name="tax_type" value="vehicule" id="tax_vehicule">
                                    <label for="tax_vehicule">
                                        <span class="tax-icon">🚗</span>
                                        Taxe Véhicule
                                    </label>
                                </div>
                                <div class="tax-type-option">
                                    <input type="radio" name="tax_type" value="patente" id="tax_patente">
                                    <label for="tax_patente">
                                        <span class="tax-icon">📋</span>
                                        Patente
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="taxpayer_name">Nom complet *</label>
                                <input type="text" name="taxpayer_name" id="taxpayer_name" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label for="taxpayer_phone">Téléphone *</label>
                                <input type="tel" name="taxpayer_phone" id="taxpayer_phone" class="form-control" required placeholder="+257 XX XX XX XX">
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="taxpayer_email">Email</label>
                                <input type="email" name="taxpayer_email" id="taxpayer_email" class="form-control">
                            </div>
                            <div class="form-group">
                                <label for="taxpayer_nif">Numéro NIF</label>
                                <input type="text" name="taxpayer_nif" id="taxpayer_nif" class="form-control" placeholder="4001234567">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="property_address">Adresse de la propriété/entreprise</label>
                            <input type="text" name="property_address" id="property_address" class="form-control" placeholder="Avenue, Quartier, Commune">
                        </div>

                        <div class="form-group">
                            <label for="amount">Montant à payer (BIF) *</label>
                            <input type="number" name="amount" id="amount" class="form-control" required min="1000" step="100" placeholder="50000">
                        </div>

                        <div class="form-group">
                            <label>Méthode de paiement *</label>
                            <div class="payment-methods">
                                <div class="payment-method">
                                    <input type="radio" name="payment_method" value="ecocash" id="ecocash" required>
                                    <label for="ecocash">EcoCash</label>
                                </div>
                                <div class="payment-method">
                                    <input type="radio" name="payment_method" value="lumicash" id="lumicash">
                                    <label for="lumicash">LumiCash</label>
                                </div>
                                <div class="payment-method">
                                    <input type="radio" name="payment_method" value="bank" id="bank">
                                    <label for="bank">Virement</label>
                                </div>
                                <div class="payment-method">
                                    <input type="radio" name="payment_method" value="cash" id="cash">
                                    <label for="cash">Espèces</label>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="payment_reference">Référence de paiement</label>
                            <input type="text" name="payment_reference" id="payment_reference" class="form-control" placeholder="Numéro de transaction ou référence">
                        </div>
                    </form>
                </div>

                <div class="summary-section">
                    <div>
                        <h3>Résumé du Paiement</h3>
                        <div class="amount-display">
                            <h3>Montant Total</h3>
                            <div class="amount-value" id="displayAmount">0 BIF</div>
                        </div>

                        <div class="summary-details">
                            <div class="summary-item">
                                <span>Type de taxe:</span>
                                <span id="displayTaxType">-</span>
                            </div>
                            <div class="summary-item">
                                <span>Méthode:</span>
                                <span id="displayPaymentMethod">-</span>
                            </div>
                            <div class="summary-item">
                                <span>Frais de service:</span>
                                <span>Gratuit</span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <button type="submit" form="paymentForm" class="btn btn-primary">
                            Confirmer le Paiement
                        </button>
                        <a href="index.php" class="btn btn-secondary">Annuler</a>
                        
                        <div class="security-note">
                            🔒 <strong>Sécurisé</strong><br>
                            Vos informations sont protégées et cryptées. Ce paiement sera validé par les services fiscaux de Bujumbura.
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Update summary in real-time
        function updateSummary() {
            const amount = document.getElementById('amount').value || 0;
            const taxType = document.querySelector('input[name="tax_type"]:checked');
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked');

            document.getElementById('displayAmount').textContent = 
                new Intl.NumberFormat('fr-FR').format(amount) + ' BIF';

            document.getElementById('displayTaxType').textContent = 
                taxType ? taxType.nextElementSibling.textContent.trim() : '-';

            document.getElementById('displayPaymentMethod').textContent = 
                paymentMethod ? paymentMethod.nextElementSibling.textContent : '-';
        }

        // Add event listeners
        document.getElementById('amount').addEventListener('input', updateSummary);
        
        document.querySelectorAll('input[name="tax_type"]').forEach(radio => {
            radio.addEventListener('change', updateSummary);
        });
        
        document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
            radio.addEventListener('change', updateSummary);
        });

        // Form validation
        document.getElementById('paymentForm').addEventListener('submit', function(e) {
            const amount = parseFloat(document.getElementById('amount').value);
            if (amount < 1000) {
                e.preventDefault();
                alert('Le montant minimum est de 1,000 BIF');
                return;
            }
            
            if (!confirm('Confirmer le paiement de ' + new Intl.NumberFormat('fr-FR').format(amount) + ' BIF ?')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>