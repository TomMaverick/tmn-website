<?php
session_start(); // Session starten, um Daten zu speichern

// Daten aus dem Formular holen
$db_host = trim($_POST['db_host'] ?? '');
$db_port = trim($_POST['db_port'] ?? ''); // Port holen
$db_name = trim($_POST['db_name'] ?? '');
$db_user = trim($_POST['db_user'] ?? '');
$db_pass = $_POST['db_pass'] ?? ''; // Passwort nicht trimmen

// Einfache Validierung
if (empty($db_host) || empty($db_name) || empty($db_user) || empty($db_port)) { // Port hinzugefügt
    $_SESSION['config_error'] = "Bitte alle erforderlichen Felder ausfüllen.";
    header('Location: config_form.php');
    exit;
}

// Prüfen ob Port numerisch ist
if (!is_numeric($db_port) || (int)$db_port <= 0) {
     $_SESSION['config_error'] = "Der Port muss eine positive Zahl sein.";
     header('Location: config_form.php');
     exit;
}
$db_port_int = (int)$db_port; // Port in Integer umwandeln

// Versuche, eine Verbindung herzustellen, um die Daten zu validieren
mysqli_report(MYSQLI_REPORT_OFF); // Standard-Fehlerberichterstattung deaktivieren
// @ unterdrückt direkte Fehlerausgabe, Port wird als letzter Parameter übergeben
$conn_test = @new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port_int);

if ($conn_test->connect_error) {
    // Fehler beim Verbindungsaufbau
    // Detailliertere Fehlermeldung inkl. Port
    $_SESSION['config_error'] = "Verbindung zur Datenbank fehlgeschlagen (Host: " . htmlspecialchars($db_host) . ":" . $db_port_int . "). Bitte überprüfen Sie die Zugangsdaten und den Port. Fehler: " . $conn_test->connect_error;
    // Alte Konfig in Session löschen, damit sie neu eingegeben werden muss
    unset($_SESSION['db_config']);
    header('Location: config_form.php');
    exit;
}

// Verbindung erfolgreich - Zugangsdaten in der Session speichern
$_SESSION['db_config'] = [
    'host' => $db_host,
    'port' => $db_port_int, // Port hinzugefügt
    'name' => $db_name,
    'user' => $db_user,
    'pass' => $db_pass
];

// Testverbindung wieder schließen
$conn_test->close();

// Erfolgreich, Weiterleitung zur Hauptseite
unset($_SESSION['config_error']); // Alte Fehlermeldungen löschen
header('Location: index.php');
exit;

?>
