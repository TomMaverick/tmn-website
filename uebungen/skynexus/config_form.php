<?php
session_start(); // Session starten, um ggf. Fehlermeldungen anzuzeigen

$error = $_SESSION['config_error'] ?? null; // Fehlermeldung aus der Session holen
// Konfigurationsdaten aus der Session holen, um sie ggf. vorauszufüllen
$db_config = $_SESSION['db_config'] ?? [];
unset($_SESSION['config_error']); // Fehlermeldung aus der Session entfernen

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datenbank Konfiguration</title>
    <link rel="stylesheet" href="css/skynexus-styles.css">
</head>
<body class="config-page"> {/* Hinzufügen einer Klasse zum Body für spezifisches Styling */}

    <h1 class="header-title">Datenbank Konfiguration</h1>
    <p class="text-light">Bitte geben Sie die Zugangsdaten für die MariaDB-Datenbank ein.</p>
    <p class="text-light">Diese Daten werden nur temporär für Ihre Sitzung gespeichert.</p>

    <?php if ($error): ?>
        <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    {/* Container-Div für das Formular hinzugefügt */}
    <div class="config-form-container">
        <form action="save_config.php" method="POST" class="config-form">
            <label for="db_host">Datenbank Host:</label>
            {/* Wert aus Session voraussfüllen, falls vorhanden */}
            <input type="text" id="db_host" name="db_host" value="<?php echo htmlspecialchars($db_config['host'] ?? 'eucje.h.filess.io'); ?>" required>

            <label for="db_port">Datenbank Port:</label> {/* NEUES FELD */}
            {/* Wert aus Session voraussfüllen, Standard 3306 */}
            <input type="number" id="db_port" name="db_port" value="<?php echo htmlspecialchars($db_config['port'] ?? '3306'); ?>" required>

            <label for="db_name">Datenbank Name:</label>
            {/* Wert aus Session voraussfüllen, falls vorhanden */}
            <input type="text" id="db_name" name="db_name" value="<?php echo htmlspecialchars($db_config['name'] ?? 'SkyNexus_fifthpond'); ?>" required>

            <label for="db_user">Datenbank Benutzer:</label>
             {/* Wert aus Session voraussfüllen, falls vorhanden */}
            <input type="text" id="db_user" name="db_user" value="<?php echo htmlspecialchars($db_config['user'] ?? ''); ?>" required>

            <label for="db_pass">Datenbank Passwort:</label>
            {/* Passwort wird aus Sicherheitsgründen nicht vorausgefüllt */}
            <input type="password" id="db_pass" name="db_pass">

            <button type="submit">Konfiguration speichern & Testen</button>
        </form>
    </div>

</body>
</html>
