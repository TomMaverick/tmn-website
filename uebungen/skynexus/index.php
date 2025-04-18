<?php
session_start(); // Session MUSS GANZ am Anfang gestartet werden

// --- Prüfen, ob DB-Konfiguration in der Session vorhanden ist ---
if (!isset($_SESSION['db_config']) || empty($_SESSION['db_config']['host'])) {
    // Keine Konfiguration gefunden, zum Konfigurationsformular umleiten
    header('Location: config_form.php');
    exit;
}

// --- Konfiguration aus der Session laden ---
$db_config = $_SESSION['db_config'];
$db_host = $db_config['host'];
$db_user = $db_config['user'];
$db_pass = $db_config['pass'];
$db_name = $db_config['name'];

// --- Zugangsdaten für den Seitenschutz (HTTP Basic Auth) ---
// Diese könnten auch noch aus einer Konfigurationsdatei oder DB kommen,
// aber für jetzt lassen wir sie hier (sind ja nicht die DB-Daten).
// WICHTIG: Wähle einen sicheren Benutzernamen und ein starkes Passwort!
$auth_user = 'passagier';
$auth_pass = 'sicheresPasswort123!'; // ÄNDERN!

// --- Passwortschutz (HTTP Basic Auth) für Passagiere ---
if (!isset($_SERVER['PHP_AUTH_USER']) ||
    !isset($_SERVER['PHP_AUTH_PW']) ||
    $_SERVER['PHP_AUTH_USER'] != $auth_user ||
    $_SERVER['PHP_AUTH_PW'] != $auth_pass)
{
    header('WWW-Authenticate: Basic realm="Flugbuchung"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Zugriff verweigert. Authentifizierung erforderlich.';
    exit;
}

// --- Variablen für die View initialisieren ---
$flights_data = [];
$error_message = null;
$filter_departure_city = isset($_GET['departure_city']) ? htmlspecialchars(trim($_GET['departure_city'])) : '';
$filter_arrival_city = isset($_GET['arrival_city']) ? htmlspecialchars(trim($_GET['arrival_city'])) : '';
$filter_date = isset($_GET['date']) ? htmlspecialchars(trim($_GET['date'])) : '';

// --- Datenbankverbindung und Datenabfrage (jetzt mit Daten aus Session) ---
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = null;
$stmt = null;

try {
    // Verwende die aus der Session geladenen Zugangsdaten
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    $conn->set_charset('utf8mb4');

    // SQL-Abfrage (unverändert)
    $sql = "SELECT
                f.id AS flight_id, f.flight_number, f.departure_date, f.departure_time,
                f.price_economy, f.status,
                dep_ap.city AS departure_city, dep_ap.name AS departure_airport_name, dep_ap.icao_code AS departure_icao,
                arr_ap.city AS arrival_city, arr_ap.name AS arrival_airport_name, arr_ap.icao_code AS arrival_icao
            FROM flights f
            JOIN airports dep_ap ON f.departure_airport_id = dep_ap.id
            JOIN airports arr_ap ON f.arrival_airport_id = arr_ap.id
            WHERE f.status IN ('SCHEDULED', 'BOARDING') ";

    $params = [];
    $types = "";

    if (!empty($filter_departure_city)) {
        $sql .= " AND dep_ap.city LIKE ?";
        $types .= "s";
        $params[] = "%" . $filter_departure_city . "%";
    }
    if (!empty($filter_arrival_city)) {
        $sql .= " AND arr_ap.city LIKE ?";
        $types .= "s";
        $params[] = "%" . $filter_arrival_city . "%";
    }
    if (!empty($filter_date)) {
        $sql .= " AND f.departure_date = ?";
        $types .= "s";
        $params[] = $filter_date;
    }
    $sql .= " ORDER BY f.departure_date ASC, f.departure_time ASC";

    // Prepared Statement (unverändert)
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $flights_data[] = $row;
    }

} catch (mysqli_sql_exception $e) {
    error_log("Datenbankfehler in index.php: " . $e->getMessage());
    // Optional: Detailliertere Fehlermeldung, wenn Konfig falsch sein könnte
    if (str_contains($e->getMessage(), 'Access denied for user')) {
         $error_message = "Datenbankzugriff verweigert. Möglicherweise sind die gespeicherten Konfigurationsdaten nicht mehr gültig.";
         // Optional: Session-Daten löschen, um erneute Eingabe zu erzwingen
         // unset($_SESSION['db_config']);
    } else {
        $error_message = "Fehler beim Laden der Flugdaten.";
    }
} finally {
    if ($stmt instanceof mysqli_stmt) {
       $stmt->close();
    }
    if ($conn instanceof mysqli) {
       $conn->close();
    }
}

// --- View-Datei laden (unverändert) ---
include 'flights_view.php';

?>

