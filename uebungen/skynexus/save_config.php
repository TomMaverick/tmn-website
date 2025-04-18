<?php
session_start(); // Session starten, um Daten zu speichern

// Daten aus dem Formular holen
$db_host = trim($_POST['db_host'] ?? '');
$db_name = trim($_POST['db_name'] ?? '');
$db_user = trim($_POST['db_user'] ?? '');
$db_pass = $_POST['db_pass'] ?? ''; // Passwort nicht trimmen

// Einfache Validierung (nur prüfen, ob Felder nicht leer sind)
if (empty($db_host) || empty($db_name) || empty($db_user)) {
    $_SESSION['config_error'] = "Bitte alle erforderlichen Felder ausfüllen.";
    header('Location: config_form.php');
    exit;
}

// Versuche, eine Verbindung herzustellen, um die Daten zu validieren
mysqli_report(MYSQLI_REPORT_OFF); // Standard-Fehlerberichterstattung deaktivieren
$conn_test = @new mysqli($db_host, $db_user, $db_pass, $db_name); // @ unterdrückt direkte Fehlerausgabe

if ($conn_test->connect_error) {
    // Fehler beim Verbindungsaufbau
    $_SESSION['config_error'] = "Verbindung zur Datenbank fehlgeschlagen. Bitte überprüfen Sie die Zugangsdaten. Fehler: " . $conn_test->connect_error;
    header('Location: config_form.php');
    exit;
}

// Verbindung erfolgreich - Zugangsdaten in der Session speichern
$_SESSION['db_config'] = [
    'host' => $db_host,
    'name' => $db_name,
    'user' => $db_user,
    'pass' => $db_pass // Passwort speichern
];

// Testverbindung wieder schließen
$conn_test->close();

// Erfolgreich, Weiterleitung zur Hauptseite
unset($_SESSION['config_error']); // Alte Fehlermeldungen löschen
header('Location: index.php');
exit;

?>
