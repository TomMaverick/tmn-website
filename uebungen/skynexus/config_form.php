<?php
session_start(); // Session starten, um ggf. Fehlermeldungen anzuzeigen

$error = $_SESSION['config_error'] ?? null; // Fehlermeldung aus der Session holen
unset($_SESSION['config_error']); // Fehlermeldung aus der Session entfernen

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datenbank Konfiguration</title>
    <link rel="stylesheet" href="css/styles.css">
    <style>
        /* Einfache Stile für das Formular */
        body { max-width: 600px; margin: 2em auto; padding: 1em; }
        .config-form { border: 1px solid var(--table-border, #ccc); padding: 1.5em; border-radius: 5px; background-color: var(--header-bg-color, #f9f9f9); }
        .config-form label { display: block; margin-bottom: 0.5em; font-weight: bold; color: var(--text-1); }
        .config-form input[type="text"],
        .config-form input[type="password"] {
            width: 100%;
            padding: 0.5em;
            margin-bottom: 1em;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .config-form button {
            background-color: var(--category-bg);
            color: var(--category-text);
            padding: 0.7em 1.5em;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
        }
        .error-message {
            color: red;
            background-color: #ffebee;
            border: 1px solid red;
            padding: 0.8em;
            margin-bottom: 1em;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <h1 class="header-title">Datenbank Konfiguration</h1>
    <p class="text-light">Bitte geben Sie die Zugangsdaten für die MariaDB-Datenbank ein.</p>
    <p class="text-light">Diese Daten werden nur temporär für Ihre Sitzung gespeichert.</p>

    <?php if ($error): ?>
        <p class="error-message"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form action="save_config.php" method="POST" class="config-form">
        <label for="db_host">Datenbank Host:</label>
        <input type="text" id="db_host" name="db_host" value="eucje.h.filess.io" required>

        <label for="db_name">Datenbank Name:</label>
        <input type="text" id="db_name" name="db_name" value="SkyNexus_fifthpond" required>

        <label for="db_user">Datenbank Benutzer:</label>
        <input type="text" id="db_user" name="db_user" required>

        <label for="db_pass">Datenbank Passwort:</label>
        <input type="password" id="db_pass" name="db_pass">

        <button type="submit">Konfiguration speichern & Testen</button>
    </form>

</body>
</html>
