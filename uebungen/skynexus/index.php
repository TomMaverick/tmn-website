<?php
session_start();

// --- DB Konfiguration prüfen und laden ---
if (!isset($_SESSION['db_config']) || empty($_SESSION['db_config']['host']) || !isset($_SESSION['db_config']['port'])) {
    // Wichtig: exit nach header() nicht vergessen!
    header('Location: config_form.php');
    exit;
}
$db_config = $_SESSION['db_config'];
$db_host = $db_config['host'];
$db_port = $db_config['port'];
$db_user = $db_config['user'];
$db_pass = $db_config['pass'];
$db_name = $db_config['name'];

// --- Authentifizierung ---
$is_authenticated = false;
if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
    $provided_username = $_SERVER['PHP_AUTH_USER'];
    $provided_password = $_SERVER['PHP_AUTH_PW'];
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn_auth = null; $stmt_auth = null;
    try {
        $conn_auth = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
        $conn_auth->set_charset('utf8mb4');
        $sql_auth = "SELECT password_hash FROM webusers WHERE username = ? LIMIT 1";
        $stmt_auth = $conn_auth->prepare($sql_auth);
        $stmt_auth->bind_param('s', $provided_username);
        $stmt_auth->execute();
        $result_auth = $stmt_auth->get_result();
        if ($user_row = $result_auth->fetch_assoc()) {
            if (password_verify($provided_password, $user_row['password_hash'])) {
                $is_authenticated = true;
            }
        }
    } catch (mysqli_sql_exception $e) {
        error_log("Fehler bei Authentifizierungs-DB-Abfrage (webusers): " . $e->getMessage());
        $is_authenticated = false;
        if (str_contains($e->getMessage(), 'mysqli::real_connect():') || str_contains($e->getMessage(), 'Access denied')) {
             unset($_SESSION['db_config']);
        }
    } finally {
        if ($stmt_auth instanceof mysqli_stmt) { $stmt_auth->close(); }
        if ($conn_auth instanceof mysqli) { $conn_auth->close(); }
    }
}
if (!$is_authenticated) {
    header('WWW-Authenticate: Basic realm="Flugbuchung (webusers Login)"');
    header('HTTP/1.0 401 Unauthorized');
    if (!isset($_SESSION['db_config'])) {
         echo 'Zugriff verweigert. Möglicherweise ist die Datenbankkonfiguration ungültig. <a href="config_form.php">Erneut konfigurieren</a>.';
    } else {
        echo 'Zugriff verweigert. Authentifizierung erforderlich.';
    }
    exit;
}

// --- Variablen initialisieren ---
$flights_data = [];
$departure_airports = [];
$arrival_airports = [];
$error_message = null;
$filter_departure_id = isset($_GET['departure_airport_id']) ? trim($_GET['departure_airport_id']) : '';
$filter_arrival_id = isset($_GET['arrival_airport_id']) ? trim($_GET['arrival_airport_id']) : '';
$filter_date = isset($_GET['date']) ? htmlspecialchars(trim($_GET['date'])) : '';

// --- Sortierparameter holen ---
$sortable_columns = ['flight_number', 'departure_city', 'arrival_city', 'departure_datetime', 'price_economy'];
$sort_column = 'departure_datetime';
$sort_dir = 'ASC';
if (isset($_GET['sort']) && in_array($_GET['sort'], $sortable_columns)) { $sort_column = $_GET['sort']; }
$sort_column_sql = match ($sort_column) {
    'departure_city' => 'dep_ap.city', 'arrival_city' => 'arr_ap.city',
    'departure_datetime' => 'CONCAT(f.departure_date, " ", f.departure_time)',
    default => 'f.' . $sort_column,
};
if (isset($_GET['dir']) && strtolower($_GET['dir']) === 'desc') { $sort_dir = 'DESC'; }

// --- Datenbankverbindung herstellen & Daten holen ---
$conn = null; $stmt = null; $stmt_dep = null; $stmt_arr = null;
try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
    $conn->set_charset('utf8mb4');

    // 1. Eindeutige Abflughäfen holen
    $sql_dep_airports = "SELECT DISTINCT a.id, a.city, a.icao_code FROM airports a JOIN flights f ON a.id = f.departure_airport_id WHERE f.status = 'SCHEDULED' ORDER BY a.city ASC";
    $stmt_dep = $conn->prepare($sql_dep_airports); $stmt_dep->execute(); $result_dep = $stmt_dep->get_result();
    while ($row = $result_dep->fetch_assoc()) { $departure_airports[] = $row; } $stmt_dep->close();

    // 2. Eindeutige Ankunftsflughäfen holen
    $sql_arr_airports = "SELECT DISTINCT a.id, a.city, a.icao_code FROM airports a JOIN flights f ON a.id = f.arrival_airport_id WHERE f.status = 'SCHEDULED' ORDER BY a.city ASC";
    $stmt_arr = $conn->prepare($sql_arr_airports); $stmt_arr->execute(); $result_arr = $stmt_arr->get_result();
    while ($row = $result_arr->fetch_assoc()) { $arrival_airports[] = $row; } $stmt_arr->close();

    // 3. Flugdaten abfragen
    $sql_flights = "SELECT f.id AS flight_id, f.flight_number, f.departure_date, f.departure_time, f.flight_time_minutes, f.price_economy, f.price_business, f.price_first, f.status, dep_ap.id AS departure_airport_id, dep_ap.city AS departure_city, dep_ap.name AS departure_airport_name, dep_ap.icao_code AS departure_icao, arr_ap.id AS arrival_airport_id, arr_ap.city AS arrival_city, arr_ap.name AS arrival_airport_name, arr_ap.icao_code AS arrival_icao, act.model AS aircraft_model, m.name AS manufacturer_name FROM flights f JOIN airports dep_ap ON f.departure_airport_id = dep_ap.id JOIN airports arr_ap ON f.arrival_airport_id = arr_ap.id JOIN aircraft ac ON f.aircraft_id = ac.id JOIN aircraft_types act ON ac.type_id = act.id JOIN manufacturers m ON act.manufacturer_id = m.id WHERE f.status = 'SCHEDULED' ";
    $params = []; $types = "";
    if (!empty($filter_departure_id)) { $sql_flights .= " AND f.departure_airport_id = ?"; $types .= "i"; $params[] = $filter_departure_id; }
    if (!empty($filter_arrival_id)) { $sql_flights .= " AND f.arrival_airport_id = ?"; $types .= "i"; $params[] = $filter_arrival_id; }
    if (!empty($filter_date)) { $sql_flights .= " AND f.departure_date = ?"; $types .= "s"; $params[] = $filter_date; }
    $sql_flights .= " ORDER BY " . $sort_column_sql . " " . $sort_dir;

    $stmt = $conn->prepare($sql_flights);
    if (!empty($params)) { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $result_flights = $stmt->get_result();

    // Ergebnisse verarbeiten
    while ($flight = $result_flights->fetch_assoc()) {
        $flight['arrival_date'] = null; $flight['arrival_time'] = null; $flight['duration_formatted'] = 'N/A';
        if (!empty($flight['flight_time_minutes']) && $flight['flight_time_minutes'] > 0) {
             $hours = floor($flight['flight_time_minutes'] / 60); $minutes = $flight['flight_time_minutes'] % 60;
             $flight['duration_formatted'] = $hours . 'h ' . $minutes . 'min';
            try {
                $departure_string = $flight['departure_date'] . ' ' . $flight['departure_time'];
                $departure_dt = new DateTime($departure_string);
                $arrival_dt = clone $departure_dt; $arrival_dt->add(new DateInterval('PT' . $flight['flight_time_minutes'] . 'M'));
                $flight['arrival_date'] = $arrival_dt->format('Y-m-d'); $flight['arrival_time'] = $arrival_dt->format('H:i');
            } catch (Exception $e) { error_log("Fehler bei Ankunftszeitberechnung für Flug " . $flight['flight_id'] . ": " . $e->getMessage()); }
        }
        $flights_data[] = $flight;
    }

} catch (mysqli_sql_exception $e) {
    error_log("Datenbankfehler in index.php: " . $e->getMessage());
    $error_message = "Fehler beim Laden der Flugdaten.";
    if (str_contains($e->getMessage(), 'mysqli::real_connect():') || str_contains($e->getMessage(), 'Access denied')) {
         unset($_SESSION['db_config']); $error_message .= ' Möglicherweise ist die Datenbankkonfiguration ungültig.';
    }
} finally {
    // Ressourcen hier schließen, BEVOR HTML beginnt
    if ($stmt instanceof mysqli_stmt) { $stmt->close(); }
    // $stmt_dep und $stmt_arr sind schon geschlossen
    if ($conn instanceof mysqli) { $conn->close(); }
}

// --- Ab hier beginnt der HTML-Teil (früher flights_view.php) ---

// Hilfsfunktion zum Erstellen von Sortier-Links
function get_sort_link($column_name, $current_sort_column, $current_sort_dir, $base_params) {
    $link_dir = 'asc';
    if ($column_name === $current_sort_column) { $link_dir = ($current_sort_dir === 'asc') ? 'desc' : 'asc'; }
    $params = $base_params; $params['sort'] = $column_name; $params['dir'] = $link_dir;
    return 'index.php?' . http_build_query($params);
}
// Funktion zum Abrufen des Indikators für den Link-Text
function get_sort_indicator($column_name, $current_sort_column, $current_sort_dir) {
     if ($column_name === $current_sort_column) { return ($current_sort_dir === 'asc') ? ' ▲' : ' ▼'; } return '';
}
// Basisparameter für Links extrahieren (Filter ohne Sortierung)
$base_link_params = $_GET;
unset($base_link_params['sort']);
unset($base_link_params['dir']);

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flugbuchung - Verfügbare Flüge</title>
    <link rel="stylesheet" href="css/skynexus-styles.css">
    <style>
        /* Minimale Inline-Stile, besser in Haupt-CSS */
         .price-details span { display: inline-block; min-width: 6em; font-weight: bold; color: var(--text-2); }
         .flight-id { font-size: 0.8em; color: var(--text-2); display: block; margin-top: 0.2em; }
    </style>
</head>
<body>

    <header>
        <a href="index.php" class="header-brand-link">
             <img src="img/logo.png" alt="SkyNexus Logo" class="header-logo">
             <img src="img/schriftzug.png" alt="SkyNexus Schriftzug" class="header-wordmark">
        </a>
    </header>

    <main>
        <h2 class="main-title">Flüge suchen & buchen</h2>

        <form action="index.php" method="GET" class="filter-form" id="filter-form">
             <label for="departure_airport_id">Abflugort:</label>
             <select id="departure_airport_id" name="departure_airport_id">
                 <option value="">-- Alle Orte --</option>
                 <?php foreach ($departure_airports as $airport): ?>
                     <option value="<?php echo htmlspecialchars($airport['id']); ?>" <?php echo ($filter_departure_id == $airport['id']) ? 'selected' : ''; ?>>
                         <?php echo htmlspecialchars($airport['city'] . ' (' . $airport['icao_code'] . ')'); ?>
                     </option>
                 <?php endforeach; ?>
             </select>

             <label for="arrival_airport_id">Zielort:</label>
             <select id="arrival_airport_id" name="arrival_airport_id">
                 <option value="">-- Alle Orte --</option>
                  <?php foreach ($arrival_airports as $airport): ?>
                     <option value="<?php echo htmlspecialchars($airport['id']); ?>" <?php echo ($filter_arrival_id == $airport['id']) ? 'selected' : ''; ?>>
                         <?php echo htmlspecialchars($airport['city'] . ' (' . $airport['icao_code'] . ')'); ?>
                     </option>
                 <?php endforeach; ?>
             </select>

             <label for="date">Datum:</label>
             <input type="date" id="date" name="date" value="<?php echo $filter_date; ?>">

             <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort_column); ?>">
             <input type="hidden" name="dir" value="<?php echo htmlspecialchars($sort_dir); ?>">

             <button type="submit">Flüge suchen</button>
             <a href="index.php" class="reset-link">Filter zurücksetzen</a>
        </form>

        <div id="loading-indicator">Lade Flüge...</div>

        <h3 class="main-category">Verfügbare Flüge</h3>

        <?php if (!empty($error_message)): ?>
            <p class="no-flights" style="color: red;"><?php echo htmlspecialchars($error_message); ?></p>
        <?php elseif (!empty($flights_data)): ?>
            <div id="flight-results">
                <table class="flight-table">
                    <thead>
                        <tr>
                            <th><a href="<?php echo get_sort_link('flight_number', $sort_column, $sort_dir, $base_link_params); ?>">Flugnr.<?php echo get_sort_indicator('flight_number', $sort_column, $sort_dir); ?></a></th>
                            <th><a href="<?php echo get_sort_link('departure_city', $sort_column, $sort_dir, $base_link_params); ?>">Von<?php echo get_sort_indicator('departure_city', $sort_column, $sort_dir); ?></a></th>
                            <th><a href="<?php echo get_sort_link('arrival_city', $sort_column, $sort_dir, $base_link_params); ?>">Nach<?php echo get_sort_indicator('arrival_city', $sort_column, $sort_dir); ?></a></th>
                            <th>Flugzeug</th>
                            <th><a href="<?php echo get_sort_link('departure_datetime', $sort_column, $sort_dir, $base_link_params); ?>">Abflug<?php echo get_sort_indicator('departure_datetime', $sort_column, $sort_dir); ?></a></th>
                            <th>Ankunft</th>
                            <th>Flugdauer</th>
                            <th><a href="<?php echo get_sort_link('price_economy', $sort_column, $sort_dir, $base_link_params); ?>">Preis<?php echo get_sort_indicator('price_economy', $sort_column, $sort_dir); ?></a></th>
                            <th>Aktion</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($flights_data as $flight): ?>
                            <tr>
                                <td>
                                    <?php echo htmlspecialchars($flight['flight_number']); ?>
                                    <span class="flight-id">(ID: <?php echo $flight['flight_id']; ?>)</span>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($flight['departure_city']); ?>
                                    (<?php echo htmlspecialchars($flight['departure_icao']); ?>)<br>
                                    <small><?php echo htmlspecialchars($flight['departure_airport_name']); ?></small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($flight['arrival_city']); ?>
                                    (<?php echo htmlspecialchars($flight['arrival_icao']); ?>)<br>
                                    <small><?php echo htmlspecialchars($flight['arrival_airport_name']); ?></small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars(trim(($flight['manufacturer_name'] ?? '') . ' ' . ($flight['aircraft_model'] ?? 'N/A'))); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars(date("d.m.Y", strtotime($flight['departure_date']))); ?><br>
                                    <?php echo htmlspecialchars(substr($flight['departure_time'], 0, 5)); ?> Uhr
                                </td>
                                <td>
                                    <?php if ($flight['arrival_date'] && $flight['arrival_time']): ?>
                                        <?php echo htmlspecialchars(date("d.m.Y", strtotime($flight['arrival_date']))); ?><br>
                                        <?php echo htmlspecialchars($flight['arrival_time']); ?> Uhr
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($flight['duration_formatted']); ?></td>
                                <td>
                                    <div class="price-details">
                                        <span>Economy:</span> <?php echo htmlspecialchars(number_format($flight['price_economy'], 2, ',', '.')); ?> €<br>
                                        <span>Business:</span> <?php echo htmlspecialchars(number_format($flight['price_business'], 2, ',', '.')); ?> €<br>
                                        <span>First:</span> <?php echo htmlspecialchars(number_format($flight['price_first'], 2, ',', '.')); ?> €
                                    </div>
                                </td>
                                <td>
                                    <a href="booking.php?flight_id=<?php echo $flight['flight_id']; ?>" class="book-button">Buchen</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="no-flights">Keine geplanten Flüge für die gewählten Kriterien gefunden.</p>
        <?php endif; ?>

    </main>

    <footer>
        <p>&copy; <?php echo date("Y"); ?> SkyNexus Airline Management</p>
    </footer>

    <script>
        // Ladeindikator Script
        const filterForm = document.getElementById('filter-form');
        const loadingIndicator = document.getElementById('loading-indicator');
        const flightResults = document.getElementById('flight-results');
        if (filterForm && loadingIndicator) {
            filterForm.addEventListener('submit', function() {
                loadingIndicator.style.display = 'block';
                 if (flightResults) { flightResults.style.opacity = '0.5'; }
            });
        }
        window.addEventListener('load', function() {
             if (loadingIndicator) { loadingIndicator.style.display = 'none'; }
             if (flightResults) { flightResults.style.opacity = '1'; }
        });
    </script>

</body>
</html>
