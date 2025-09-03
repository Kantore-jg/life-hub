<?php
session_start();

// Database connection
$servername = "localhost";
$username = "root";  // Change if different
$password = "";      // Change if you have a password
$dbname = "ubuzima_hub";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch transportation data
$transport_query = "SELECT * FROM transportation WHERE is_active = 1 ORDER BY type, name";
$transport_result = $conn->query($transport_query);

// Group transportation by type
$buses = [];
$taxis = [];
$service_stations = [];

while ($row = $transport_result->fetch_assoc()) {
    switch (strtolower($row['type'])) {
        case 'bus':
            $buses[] = $row;
            break;
        case 'taxi':
            $taxis[] = $row;
            break;
        case 'service_station':
        case 'gas_station':
            $service_stations[] = $row;
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transport Information - Ubuzima Hub</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
            --accent-color: #f093fb;
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

        .transport-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s ease;
        }

        .transport-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .section-header {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            text-align: center;
        }

        .transport-icon {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .route-info {
            background: var(--bg-light);
            padding: 0.5rem;
            border-radius: 5px;
            margin: 0.5rem 0;
        }

        .contact-info {
            color: var(--text-light);
            font-size: 0.9rem;
        }

        .fare-badge {
            background: var(--accent-color);
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.9rem;
        }

        .status-badge {
            background: #48bb78;
            color: white;
            padding: 0.2rem 0.5rem;
            border-radius: 10px;
            font-size: 0.8rem;
        }

        .search-box {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .nav-tabs {
            border: none;
            margin-bottom: 2rem;
        }

        .nav-tabs .nav-link {
            border: none;
            background: white;
            color: var(--text-dark);
            border-radius: 10px;
            margin-right: 0.5rem;
            padding: 0.8rem 1.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .nav-tabs .nav-link.active {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
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
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="fas fa-bus"></i> Transport à Bujumbura</h1>
                    <p class="mb-0">Informations sur les bus, taxis et stations-service à Bujumbura</p>
                </div>
                <a href="index.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Search Box -->
        <div class="search-box">
            <div class="row">
                <div class="col-md-6">
                    <input type="text" class="form-control" id="searchInput" placeholder="Rechercher un transport...">
                </div>
                <div class="col-md-3">
                    <select class="form-control" id="typeFilter">
                        <option value="">Tous les types</option>
                        <option value="bus">Bus</option>
                        <option value="taxi">Taxi</option>
                        <option value="service_station">Station-service</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary w-100" onclick="filterTransport()">
                        <i class="fas fa-search"></i> Rechercher
                    </button>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs" id="transportTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="buses-tab" data-bs-toggle="tab" data-bs-target="#buses" type="button" role="tab">
                    <i class="fas fa-bus"></i> Bus (<?php echo count($buses); ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="taxis-tab" data-bs-toggle="tab" data-bs-target="#taxis" type="button" role="tab">
                    <i class="fas fa-taxi"></i> Taxis (<?php echo count($taxis); ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="stations-tab" data-bs-toggle="tab" data-bs-target="#stations" type="button" role="tab">
                    <i class="fas fa-gas-pump"></i> Stations (<?php echo count($service_stations); ?>)
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="transportTabContent">
            <!-- Buses Tab -->
            <div class="tab-pane fade show active" id="buses" role="tabpanel">
                <div class="section-header">
                    <h3><i class="fas fa-bus"></i> Services de Bus</h3>
                </div>
                
                <?php if (empty($buses)): ?>
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle"></i> Aucun service de bus disponible pour le moment.
                    </div>
                <?php else: ?>
                    <?php foreach ($buses as $bus): ?>
                        <div class="transport-card" data-type="bus" data-name="<?php echo strtolower($bus['name']); ?>">
                            <div class="row">
                                <div class="col-md-1 text-center">
                                    <i class="fas fa-bus transport-icon"></i>
                                </div>
                                <div class="col-md-8">
                                    <h5 class="mb-2"><?php echo htmlspecialchars($bus['name']); ?></h5>
                                    
                                    <?php if ($bus['route_from'] && $bus['route_to']): ?>
                                        <div class="route-info">
                                            <strong>Itinéraire:</strong> 
                                            <?php echo htmlspecialchars($bus['route_from']); ?> 
                                            <i class="fas fa-arrow-right mx-2"></i>
                                            <?php echo htmlspecialchars($bus['route_to']); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($bus['location_address']): ?>
                                        <p class="contact-info mb-1">
                                            <i class="fas fa-map-marker-alt"></i> 
                                            <?php echo htmlspecialchars($bus['location_address']); ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if ($bus['contact_phone']): ?>
                                        <p class="contact-info mb-1">
                                            <i class="fas fa-phone"></i> 
                                            <?php echo htmlspecialchars($bus['contact_phone']); ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if ($bus['operating_hours']): ?>
                                        <p class="contact-info mb-0">
                                            <i class="fas fa-clock"></i> 
                                            <?php echo htmlspecialchars($bus['operating_hours']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-3 text-end">
                                    <?php if ($bus['fare']): ?>
                                        <div class="fare-badge mb-2"><?php echo htmlspecialchars($bus['fare']); ?> FBu</div>
                                    <?php endif; ?>
                                    <div class="status-badge">
                                        <i class="fas fa-check-circle"></i> Actif
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Taxis Tab -->
            <div class="tab-pane fade" id="taxis" role="tabpanel">
                <div class="section-header">
                    <h3><i class="fas fa-taxi"></i> Services de Taxi</h3>
                </div>
                
                <?php if (empty($taxis)): ?>
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle"></i> Aucun service de taxi disponible pour le moment.
                    </div>
                <?php else: ?>
                    <?php foreach ($taxis as $taxi): ?>
                        <div class="transport-card" data-type="taxi" data-name="<?php echo strtolower($taxi['name']); ?>">
                            <div class="row">
                                <div class="col-md-1 text-center">
                                    <i class="fas fa-taxi transport-icon"></i>
                                </div>
                                <div class="col-md-8">
                                    <h5 class="mb-2"><?php echo htmlspecialchars($taxi['name']); ?></h5>
                                    
                                    <?php if ($taxi['location_address']): ?>
                                        <p class="contact-info mb-1">
                                            <i class="fas fa-map-marker-alt"></i> 
                                            <?php echo htmlspecialchars($taxi['location_address']); ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if ($taxi['contact_phone']): ?>
                                        <p class="contact-info mb-1">
                                            <i class="fas fa-phone"></i> 
                                            <?php echo htmlspecialchars($taxi['contact_phone']); ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if ($taxi['operating_hours']): ?>
                                        <p class="contact-info mb-0">
                                            <i class="fas fa-clock"></i> 
                                            <?php echo htmlspecialchars($taxi['operating_hours']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-3 text-end">
                                    <?php if ($taxi['fare']): ?>
                                        <div class="fare-badge mb-2"><?php echo htmlspecialchars($taxi['fare']); ?> FBu/km</div>
                                    <?php endif; ?>
                                    <div class="status-badge">
                                        <i class="fas fa-check-circle"></i> Disponible
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Service Stations Tab -->
            <div class="tab-pane fade" id="stations" role="tabpanel">
                <div class="section-header">
                    <h3><i class="fas fa-gas-pump"></i> Stations-service</h3>
                </div>
                
                <?php if (empty($service_stations)): ?>
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle"></i> Aucune station-service disponible pour le moment.
                    </div>
                <?php else: ?>
                    <?php foreach ($service_stations as $station): ?>
                        <div class="transport-card" data-type="service_station" data-name="<?php echo strtolower($station['name']); ?>">
                            <div class="row">
                                <div class="col-md-1 text-center">
                                    <i class="fas fa-gas-pump transport-icon"></i>
                                </div>
                                <div class="col-md-8">
                                    <h5 class="mb-2"><?php echo htmlspecialchars($station['name']); ?></h5>
                                    
                                    <?php if ($station['location_address']): ?>
                                        <p class="contact-info mb-1">
                                            <i class="fas fa-map-marker-alt"></i> 
                                            <?php echo htmlspecialchars($station['location_address']); ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if ($station['contact_phone']): ?>
                                        <p class="contact-info mb-1">
                                            <i class="fas fa-phone"></i> 
                                            <?php echo htmlspecialchars($station['contact_phone']); ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if ($station['operating_hours']): ?>
                                        <p class="contact-info mb-1">
                                            <i class="fas fa-clock"></i> 
                                            <?php echo htmlspecialchars($station['operating_hours']); ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if ($station['fuel_types']): ?>
                                        <p class="contact-info mb-0">
                                            <i class="fas fa-oil-can"></i> 
                                            <strong>Carburants:</strong> <?php echo htmlspecialchars($station['fuel_types']); ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-3 text-end">
                                    <div class="status-badge">
                                        <i class="fas fa-check-circle"></i> Ouvert
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        function filterTransport() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const typeFilter = document.getElementById('typeFilter').value;
            const cards = document.querySelectorAll('.transport-card');
            
            cards.forEach(card => {
                const name = card.getAttribute('data-name');
                const type = card.getAttribute('data-type');
                
                const matchesSearch = name.includes(searchTerm) || searchTerm === '';
                const matchesType = type === typeFilter || typeFilter === '';
                
                if (matchesSearch && matchesType) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        // Real-time search
        document.getElementById('searchInput').addEventListener('input', filterTransport);
        document.getElementById('typeFilter').addEventListener('change', filterTransport);
    </script>
</body>
</html>