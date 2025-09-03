<?php
// One-time setup script to create test users
require_once 'config.php';

// Create admin user with proper password hash
$db = new Database();
$conn = $db->getConnection();

// Password: admin123
$adminPasswordHash = password_hash('admin123', PASSWORD_DEFAULT);

// Update or insert admin user
$stmt = $conn->prepare("INSERT INTO users (phone_number, email, full_name, user_type, commune, zone, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE password_hash = ?");
$phone = '+25779000000';
$email = 'admin@ubuzimahub.bi';
$fullName = 'System Administrator';
$userType = 'admin';
$commune = 'Bujumbura Mairie';
$zone = 'Rohero';

$stmt->bind_param("ssssssss", $phone, $email, $fullName, $userType, $commune, $zone, $adminPasswordHash, $adminPasswordHash);

if ($stmt->execute()) {
    echo "✅ Admin user created/updated successfully!<br>";
    echo "Phone: +25779000000<br>";
    echo "Password: admin123<br><br>";
} else {
    echo "❌ Error creating admin user: " . $conn->error . "<br>";
}

// Create a test citizen user
$citizenPasswordHash = password_hash('citizen123', PASSWORD_DEFAULT);
$stmt2 = $conn->prepare("INSERT INTO users (phone_number, email, full_name, user_type, commune, zone, address, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE password_hash = ?");

$citizenPhone = '+25779111111';
$citizenEmail = 'citizen@test.bi';
$citizenName = 'Jean Baptiste Ndayishimiye';
$citizenType = 'citizen';
$citizenCommune = 'Bujumbura Mairie';
$citizenZone = 'Nyakabiga';
$citizenAddress = 'Avenue de la Paix, Quartier 2';

$stmt2->bind_param("sssssssss", $citizenPhone, $citizenEmail, $citizenName, $citizenType, $citizenCommune, $citizenZone, $citizenAddress, $citizenPasswordHash, $citizenPasswordHash);

if ($stmt2->execute()) {
    echo "✅ Test citizen user created/updated successfully!<br>";
    echo "Phone: +25779111111<br>";
    echo "Password: citizen123<br><br>";
} else {
    echo "❌ Error creating citizen user: " . $conn->error . "<br>";
}

// Create a test authority user
$authorityPasswordHash = password_hash('authority123', PASSWORD_DEFAULT);
$stmt3 = $conn->prepare("INSERT INTO users (phone_number, email, full_name, user_type, commune, zone, password_hash) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE password_hash = ?");

$authorityPhone = '+25779222222';
$authorityEmail = 'authority@ubuzimahub.bi';
$authorityName = 'Marie Claire Uwimana';
$authorityType = 'authority';
$authorityCommune = 'Bujumbura Mairie';
$authorityZone = 'Rohero';

$stmt3->bind_param("ssssssss", $authorityPhone, $authorityEmail, $authorityName, $authorityType, $authorityCommune, $authorityZone, $authorityPasswordHash, $authorityPasswordHash);

if ($stmt3->execute()) {
    echo "✅ Test authority user created/updated successfully!<br>";
    echo "Phone: +25779222222<br>";
    echo "Password: authority123<br><br>";
} else {
    echo "❌ Error creating authority user: " . $conn->error . "<br>";
}

// Insert some sample transportation data
$transport_data = [
    ['bus', 'Bus Bujumbura-Gitega', 'Bujumbura', 'Gitega', 'Gare Routière Centrale', -3.3761, 29.3594, '+25779333333', '06:00-18:00', 3000],
    ['taxi', 'Taxi Nyakabiga-Centre', 'Nyakabiga', 'Centre-ville', 'Nyakabiga Market', -3.3833, 29.3667, '+25779444444', '24/7', 500],
    ['gas_station', 'Station Total Rohero', '', '', 'Avenue du Commerce, Rohero', -3.3700, 29.3600, '+25779555555', '06:00-22:00', 0]
];

foreach ($transport_data as $transport) {
    $stmt4 = $conn->prepare("INSERT IGNORE INTO transportation (type, name, route_from, route_to, location_address, latitude, longitude, contact_phone, operating_hours, fare) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt4->bind_param("sssssddssd", ...$transport);
    
    if ($stmt4->execute()) {
        echo "✅ Transportation data inserted: {$transport[1]}<br>";
    } else {
        echo "❌ Error inserting {$transport[1]}: " . $conn->error . "<br>";
    }
}

echo "<br>🎉 Setup completed! You can now:<br>";
echo "1. <a href='login.php'>Login with any of the test accounts</a><br>";
echo "2. <a href='register.php'>Register a new account</a><br>";
echo "3. Test the application functionality<br><br>";

echo "<strong>⚠️ IMPORTANT:</strong> Delete this setup.php file before going live!<br>";

$db->close();
?>