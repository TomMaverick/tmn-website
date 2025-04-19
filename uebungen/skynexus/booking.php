<?php
session_start();

// --- DB Konfiguration prüfen und laden ---
if (!isset($_SESSION['db_config']) || empty($_SESSION['db_config']['host']) || !isset($_SESSION['db_config']['port'])) {
    die("Datenbank nicht konfiguriert. Bitte gehen Sie zur <a href='index.php'>Startseite</a>.");
}
$db_config = $_SESSION['db_config'];
$db_host = $db_config['host'];
$db_port = $db_config['port'];
$db_user = $db_config['user'];
$db_pass = $db_config['pass'];
$db_name = $db_config['name'];

// --- Variablen initialisieren ---
$flight_id = null;
$flight = null;
$db_nationalities_data = [];
$error_message = null; // Für POST-Fehler
$page_error_message = null; // Für Fehler beim Laden
$success_message = null;
$form_data = $_POST;
$passport_suffix_value = '';

// --- Flug-ID aus GET holen ---
if (isset($_GET['flight_id']) && is_numeric($_GET['flight_id'])) {
    $flight_id = (int)$_GET['flight_id'];
} else {
    $page_error_message = "Keine gültige Flug-ID übergeben.";
}

// --- Datenbankverbindung herstellen ---
$conn = null; $stmt_flight = null; $stmt_capacity = null; $stmt_count = null; $stmt_insert = null; $stmt_passport_check = null; $stmt_double_booking_check = null;
// Nur verbinden, wenn ID gültig war und kein initialer Fehler vorliegt
if ($flight_id && !$page_error_message) {
    try {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $conn = new mysqli($db_host, $db_user, $db_pass, $db_name, $db_port);
        $conn->set_charset('utf8mb4');

        // --- Nationalitäten UND Ländercodes aus DB laden ---
        $db_nationalities_data = [];
        $sql_nationalities = "SELECT nationality, code_2 FROM countries ORDER BY CASE WHEN code_2 = 'DE' THEN 1 WHEN code_2 = 'AT' THEN 2 WHEN code_2 = 'CH' THEN 3 WHEN code_2 = 'US' THEN 4 WHEN code_2 = 'GB' THEN 5 WHEN code_2 = 'FR' THEN 6 ELSE 7 END ASC, nationality ASC";
        $result_nationalities = $conn->query($sql_nationalities);
        while ($row = $result_nationalities->fetch_assoc()) { $db_nationalities_data[] = ['nationality' => $row['nationality'], 'code_2' => $row['code_2']]; }
        $result_nationalities->free();

        // --- Flugdaten für die ausgewählte ID holen ---
        $sql_flight = "SELECT f.id AS flight_id, f.flight_number, f.aircraft_id, f.departure_date, f.departure_time, f.flight_time_minutes, f.price_economy, f.price_business, f.price_first, dep_ap.city AS departure_city, dep_ap.name AS departure_airport_name, dep_ap.icao_code AS departure_icao, arr_ap.city AS arrival_city, arr_ap.name AS arrival_airport_name, arr_ap.icao_code AS arrival_icao, act.model AS aircraft_model, m.name AS manufacturer_name FROM flights f JOIN airports dep_ap ON f.departure_airport_id = dep_ap.id JOIN airports arr_ap ON f.arrival_airport_id = arr_ap.id JOIN aircraft ac ON f.aircraft_id = ac.id JOIN aircraft_types act ON ac.type_id = act.id JOIN manufacturers m ON act.manufacturer_id = m.id WHERE f.id = ? AND f.status = 'SCHEDULED' LIMIT 1";
        $stmt_flight = $conn->prepare($sql_flight); // $stmt_flight wird hier definiert
        $stmt_flight->bind_param('i', $flight_id);
        $stmt_flight->execute();
        $result_flight = $stmt_flight->get_result();
        $flight = $result_flight->fetch_assoc();
        // KEIN close() hier für $stmt_flight!

        if (!$flight) { $page_error_message = "Ausgewählter Flug nicht gefunden oder nicht mehr buchbar."; $flight_id = null;
        } else {
             $flight['arrival_date'] = null; $flight['arrival_time'] = null; $flight['duration_formatted'] = 'N/A';
             if (!empty($flight['flight_time_minutes']) && $flight['flight_time_minutes'] > 0) {
                 $hours = floor($flight['flight_time_minutes'] / 60); $minutes = $flight['flight_time_minutes'] % 60; $flight['duration_formatted'] = $hours . 'h ' . $minutes . 'min';
                try { $departure_string = $flight['departure_date'] . ' ' . $flight['departure_time']; $departure_dt = new DateTime($departure_string); $arrival_dt = clone $departure_dt; $arrival_dt->add(new DateInterval('PT' . $flight['flight_time_minutes'] . 'M')); $flight['arrival_date'] = $arrival_dt->format('Y-m-d'); $flight['arrival_time'] = $arrival_dt->format('H:i'); } catch (Exception $e) { /* ignore */ }
             }
        }

        // --- Formularverarbeitung ---
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $flight_id && $flight) {
             $seat_class = trim($_POST['seat_class'] ?? ''); $last_name = trim($_POST['last_name'] ?? ''); $first_name = trim($_POST['first_name'] ?? ''); $gender = trim($_POST['gender'] ?? ''); $dob = trim($_POST['dob'] ?? ''); $nationality = trim($_POST['nationality'] ?? ''); $passport_suffix = trim($_POST['passport_suffix'] ?? ''); $passport_suffix = strtoupper($passport_suffix);
            $errors = [];
            if (empty($seat_class) || !in_array($seat_class, ['Economy', 'Business', 'First'])) $errors[] = "Bitte wählen Sie eine Buchungsklasse."; if (empty($last_name)) $errors[] = "Nachname ist erforderlich."; if (empty($first_name)) $errors[] = "Vorname ist erforderlich."; if (empty($gender)) $errors[] = "Geschlecht ist erforderlich."; if (empty($dob)) { $errors[] = "Geburtsdatum ist erforderlich."; } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) { $errors[] = "Geburtsdatum muss im Format JJJJ-MM-DD sein."; }
            $valid_nationality_found = false; $expected_country_code = null; foreach ($db_nationalities_data as $nat_data) { if ($nat_data['nationality'] === $nationality) { $valid_nationality_found = true; $expected_country_code = $nat_data['code_2']; break; } } if (empty($nationality)) { $errors[] = "Nationalität ist erforderlich."; } elseif (!$valid_nationality_found) { $errors[] = "Ungültige Nationalität ausgewählt."; $expected_country_code = null; }
            $form_data['passport_suffix'] = $passport_suffix;

            // Doppelbuchungs-Check (Zeile ~96 ist hier irgendwo)
            if (!empty($first_name) && !empty($last_name) && !empty($dob) && empty($errors)) {
                 $sql_double_booking = "SELECT COUNT(*) AS count FROM passengers WHERE flight_id = ? AND first_name = ? AND last_name = ? AND date_of_birth = ?";
                 $stmt_double_booking_check = $conn->prepare($sql_double_booking);
                 $stmt_double_booking_check->bind_param('isss', $flight_id, $first_name, $last_name, $dob);
                 $stmt_double_booking_check->execute();
                 $result_double = $stmt_double_booking_check->get_result();
                 $double_row = $result_double->fetch_assoc();
                 $stmt_double_booking_check->close(); // Schließen nach Gebrauch ist ok
                 if ($double_row['count'] > 0) { $errors[] = "Dieser Passagier (Vorname, Nachname, Geburtsdatum) ist bereits für diesen Flug gebucht."; }
            }

            // Passnummer Validierung
            $passport = '';
            if (!empty($passport_suffix) && empty($errors)) {
                if (!preg_match('/^[A-Z0-9]{7}$/', $passport_suffix)) { $errors[] = "Die 7 Zeichen der Passnummer nach dem Ländercode dürfen nur Großbuchstaben und Zahlen enthalten."; } elseif (!$expected_country_code) { $errors[] = "Ländercode für Passnummer konnte nicht ermittelt werden.";
                } else {
                    $passport = $expected_country_code . $passport_suffix;
                    $sql_passport_check = "SELECT first_name, last_name, date_of_birth, nationality FROM passengers WHERE passport_number = ? LIMIT 1";
                    $stmt_passport_check = $conn->prepare($sql_passport_check);
                    $stmt_passport_check->bind_param('s', $passport);
                    $stmt_passport_check->execute();
                    $result_passport = $stmt_passport_check->get_result();
                    $existing_passenger = $result_passport->fetch_assoc();
                    $stmt_passport_check->close(); // Schließen nach Gebrauch ist ok
                    if ($existing_passenger) { if ( $existing_passenger['first_name'] !== $first_name || $existing_passenger['last_name'] !== $last_name || $existing_passenger['date_of_birth'] !== $dob || $existing_passenger['nationality'] !== $nationality ) { $errors[] = "Diese Passnummer ist bereits registriert, aber die eingegebenen persönlichen Daten stimmen nicht überein."; } }
                }
            }

            if (empty($errors)) {
                // Verfügbarkeitsprüfung
                $is_available = false; $pax_capacity = 0; $current_passengers = 0;
                $sql_capacity = "SELECT act.pax_capacity FROM aircraft ac JOIN aircraft_types act ON ac.type_id = act.id WHERE ac.id = ?";
                $stmt_capacity = $conn->prepare($sql_capacity); $stmt_capacity->bind_param('i', $flight['aircraft_id']); $stmt_capacity->execute(); $result_capacity = $stmt_capacity->get_result(); if ($capacity_row = $result_capacity->fetch_assoc()) { $pax_capacity = (int)$capacity_row['pax_capacity']; }
                $sql_count = "SELECT COUNT(*) AS current_passengers FROM passengers WHERE flight_id = ?";
                $stmt_count = $conn->prepare($sql_count); $stmt_count->bind_param('i', $flight_id); $stmt_count->execute(); $result_count = $stmt_count->get_result(); if ($count_row = $result_count->fetch_assoc()) { $current_passengers = (int)$count_row['current_passengers']; }
                if ($pax_capacity > 0 && $current_passengers < $pax_capacity) { $is_available = true; }

                if ($is_available) {
                    // Passagier einfügen
                    try { $sql_insert = "INSERT INTO passengers (last_name, first_name, gender, date_of_birth, nationality, passport_number, flight_id, seat_class) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"; $stmt_insert = $conn->prepare($sql_insert); $stmt_insert->bind_param('ssssssis', $last_name, $first_name, $gender, $dob, $nationality, $passport, $flight_id, $seat_class); if ($stmt_insert->execute()) { $success_message = "Buchung erfolgreich! Passagier wurde hinzugefügt."; $form_data = []; } else { throw new mysqli_sql_exception("Fehler beim Speichern des Passagiers."); } $stmt_insert->close(); } catch (mysqli_sql_exception $e) { error_log("Fehler beim Einfügen des Passagiers: " . $e->getMessage()); $error_message = "Ein Fehler ist beim Speichern der Buchung aufgetreten. Details: " . htmlspecialchars($e->getMessage()); }
                } else { $error_message = "Buchung nicht möglich: Der Flug ist bereits ausgebucht (Kapazität: $pax_capacity, Gebucht: $current_passengers)."; }
            } else { $error_message = "Bitte korrigieren Sie die folgenden Fehler:<br>" . implode("<br>", $errors); } // $error_message wird hier bei Validierungsfehlern gesetzt
        } // Ende POST-Verarbeitung

    } catch (mysqli_sql_exception $e) {
        error_log("Datenbankfehler auf booking.php: " . $e->getMessage());
        $page_error_message = "Ein Datenbankfehler ist aufgetreten. Details: " . htmlspecialchars($e->getMessage()); // Fehler in page_error_message speichern
        if (str_contains($e->getMessage(), 'mysqli::real_connect():') || str_contains($e->getMessage(), 'Access denied')) {
             unset($_SESSION['db_config']); $page_error_message .= ' Möglicherweise ist die Datenbankkonfiguration ungültig. <a href="index.php">Zurück zur Startseite</a>.';
        }
    } finally {
        // Statements und Verbindung schließen
        if ($stmt_flight instanceof mysqli_stmt) { $stmt_flight->close(); } // Wird hier sicher geschlossen
        if ($stmt_capacity instanceof mysqli_stmt) { $stmt_capacity->close(); }
        if ($stmt_count instanceof mysqli_stmt) { $stmt_count->close(); }
        // Die nächsten beiden wurden schon oben geschlossen, falls der Code ausgeführt wurde
        // if ($stmt_passport_check instanceof mysqli_stmt) { $stmt_passport_check->close(); }
        // if ($stmt_double_booking_check instanceof mysqli_stmt) { $stmt_double_booking_check->close(); }
        if ($conn instanceof mysqli) { $conn->close(); }
    }
} // Ende if($flight_id && !$page_error_message)

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flug buchen</title>
    <link rel="stylesheet" href="css/skynexus-styles.css">
    <link rel="stylesheet" href="css/passport-styles.css">
</head>
<body>
    <header>
         <a href="index.php" class="header-brand-link">
             <img src="img/logo.png" alt="SkyNexus Logo" class="header-logo">
             <img src="img/schriftzug.png" alt="SkyNexus Schriftzug" class="header-wordmark">
        </a>
    </header>

    <main>
        <p><a href="index.php">&laquo; Zurück zur Flugsuche</a></p>

        <h2 class="main-title">Flugdetails & Buchung</h2>

        <?php if ($page_error_message): // Zeige Seiten-Ladefehler zuerst an ?>
            <p class="error-message"><?php echo $page_error_message; ?></p>
            <p><a href="index.php">Zurück zur Flugübersicht</a></p>
        <?php elseif ($flight): // Wenn kein Ladefehler und Flug gefunden ?>
            <div class="flight-summary">
                <h3>Ihr ausgewählter Flug</h3>
                <div class="summary-grid">
                    <strong class="grid-label">Flugnummer:</strong> <span class="grid-value"><?php echo htmlspecialchars($flight['flight_number']); ?> (ID: <?php echo $flight['flight_id']; ?>)</span>
                    <strong class="grid-label">Flugzeug:</strong> <span class="grid-value"><?php echo htmlspecialchars(trim(($flight['manufacturer_name'] ?? '') . ' ' . ($flight['aircraft_model'] ?? 'N/A'))); ?></span>
                    <strong class="grid-label">Von:</strong> <span class="grid-value"><?php echo htmlspecialchars($flight['departure_city'] . ' (' . $flight['departure_icao'] . ')'); ?></span>
                    <strong class="grid-label">Nach:</strong> <span class="grid-value"><?php echo htmlspecialchars($flight['arrival_city'] . ' (' . $flight['arrival_icao'] . ')'); ?></span>
                    <strong class="grid-label">Abflug:</strong> <span class="grid-value"><?php echo htmlspecialchars(date("d.m.Y", strtotime($flight['departure_date']))) . ' ' . htmlspecialchars(substr($flight['departure_time'], 0, 5)); ?> Uhr</span>
                    <strong class="grid-label">Ankunft:</strong> <span class="grid-value"><?php if ($flight['arrival_date'] && $flight['arrival_time']){ echo htmlspecialchars(date("d.m.Y", strtotime($flight['arrival_date']))) . ' ' . htmlspecialchars($flight['arrival_time']) . ' Uhr'; } else { echo 'N/A'; } ?></span>
                    <strong class="grid-label prices-label">Preise:</strong> <div class="grid-value price-details"><span class="price-label">Economy:</span> <?php echo number_format($flight['price_economy'], 2, ',', '.'); ?> €<br><span class="price-label">Business:</span> <?php echo number_format($flight['price_business'], 2, ',', '.'); ?> €<br><span class="price-label">First Class:</span> <?php echo number_format($flight['price_first'], 2, ',', '.'); ?> €</div>
                    <strong class="grid-label">Dauer:</strong> <span class="grid-value"><?php echo htmlspecialchars($flight['duration_formatted']); ?></span>
                </div>
            </div>

            <?php if (!$success_message): ?>
                <form action="booking.php?flight_id=<?php echo $flight_id; ?>" method="POST" class="booking-form">
                    <h3 class="main-category">Passagierdaten eingeben</h3>

                    <div class="radio-group"> <label>Buchungsklasse:</label><br> <label class="radio-option"><input type="radio" name="seat_class" value="Economy" <?php echo (($form_data['seat_class'] ?? '') === 'Economy') ? 'checked' : ''; ?> required> Economy</label> <label class="radio-option"><input type="radio" name="seat_class" value="Business" <?php echo (($form_data['seat_class'] ?? '') === 'Business') ? 'checked' : ''; ?>> Business</label> <label class="radio-option"><input type="radio" name="seat_class" value="First" <?php echo (($form_data['seat_class'] ?? '') === 'First') ? 'checked' : ''; ?>> First Class</label> </div>
                    <label for="last_name">Nachname:</label> <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($form_data['last_name'] ?? ''); ?>" required>
                    <label for="first_name">Vorname:</label> <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($form_data['first_name'] ?? ''); ?>" required>
                    <label for="gender">Geschlecht:</label> <select id="gender" name="gender" required> <option value="">-- Bitte wählen --</option> <option value="MALE" <?php echo (($form_data['gender'] ?? '') === 'MALE') ? 'selected' : ''; ?>>Männlich</option> <option value="FEMALE" <?php echo (($form_data['gender'] ?? '') === 'FEMALE') ? 'selected' : ''; ?>>Weiblich</option> <option value="OTHER" <?php echo (($form_data['gender'] ?? '') === 'OTHER') ? 'selected' : ''; ?>>Divers</option> </select>
                    <label for="dob">Geburtsdatum:</label> <input type="date" id="dob" name="dob" value="<?php echo htmlspecialchars($form_data['dob'] ?? ''); ?>" required>
                    <label for="nationality">Nationalität:</label> <select id="nationality" name="nationality" required> <option value="">-- Bitte wählen --</option> <?php foreach ($db_nationalities_data as $nat_data): ?> <option value="<?php echo htmlspecialchars($nat_data['nationality']); ?>" data-code="<?php echo htmlspecialchars($nat_data['code_2']); ?>" <?php echo (($form_data['nationality'] ?? '') === $nat_data['nationality']) ? 'selected' : ''; ?>> <?php echo htmlspecialchars($nat_data['nationality']); ?> </option> <?php endforeach; ?> </select>
                    <label for="passport_suffix">Passnummer: (7 Großbuchstaben/Zahlen nach dem Ländercode)</label> <div class="passport-wrapper"> <span id="passport_prefix_display" class="passport-prefix">--</span> <input type="text" id="passport_suffix" name="passport_suffix" value="<?php echo htmlspecialchars($form_data['passport_suffix'] ?? ''); ?>" pattern="[A-Z0-9]{7}" maxlength="7" title="7 Großbuchstaben/Zahlen nach dem Ländercode." placeholder="-------" disabled> </div>

					<?php if ($error_message): ?>
                        <p class="error-message" style="margin-top: 1.5em;"><?php echo $error_message; ?></p>
                    <?php endif; ?>
                    <button type="submit">Flug jetzt buchen</button>
                </form>
            <?php endif; // Ende if (!$success_message) ?>

             <?php if ($success_message): ?>
                 <p class="success-message"><?php echo htmlspecialchars($success_message); ?></p>
                 <p><a href="index.php">Zurück zur Flugübersicht</a></p>
             <?php endif; ?>

        <?php else: // Fallback für $page_error_message ?>
             <p class="error-message"><?php echo $page_error_message ?? 'Ein unerwarteter Fehler ist aufgetreten.'; ?></p>
             <p><a href="index.php">Zurück zur Flugübersicht</a></p>
        <?php endif; // Ende if ($flight) ?>

    </main>

    <footer>
        <p>&copy; <?php echo date("Y"); ?> SkyNexus Airline Management</p>
    </footer>

    <script>
        // JavaScript für Passnummer-Interaktion (unverändert)
        const nationalitySelect = document.getElementById('nationality');
        const passportPrefixSpan = document.getElementById('passport_prefix_display');
        const passportSuffixInput = document.getElementById('passport_suffix');
        let currentCountryCode = '';
        if (nationalitySelect && passportPrefixSpan && passportSuffixInput) {
            function updatePassportField() {
                 const selectedOption = nationalitySelect.options[nationalitySelect.selectedIndex];
                 currentCountryCode = selectedOption.dataset.code || '';
                 if (nationalitySelect.value === '') {
                     passportSuffixInput.disabled = true; passportSuffixInput.value = '';
                     passportPrefixSpan.textContent = '--'; passportPrefixSpan.style.opacity = '0.5';
                 } else {
                     passportSuffixInput.disabled = false; passportPrefixSpan.textContent = currentCountryCode;
                     passportPrefixSpan.style.opacity = '1';
                     if (!passportSuffixInput.value) {
                         passportSuffixInput.placeholder = '-------'; setTimeout(() => { passportSuffixInput.focus(); }, 0);
                     } else { setTimeout(() => { passportSuffixInput.focus(); passportSuffixInput.setSelectionRange(passportSuffixInput.value.length, passportSuffixInput.value.length); }, 0); }
                 }
            }
            nationalitySelect.addEventListener('change', updatePassportField);
            passportSuffixInput.addEventListener('input', function(e) {
                let originalValue = this.value; let upperValue = originalValue.toUpperCase();
                let cursorPosition = this.selectionStart; let cleanedValue = upperValue.replace(/[^A-Z0-9]/g, '');
                if (cleanedValue.length > 7) { cleanedValue = cleanedValue.substring(0, 7); }
                if (this.value !== cleanedValue) { this.value = cleanedValue; this.setSelectionRange(cursorPosition, cursorPosition); }
            });
            updatePassportField();
             if (passportSuffixInput.value) { passportSuffixInput.value = passportSuffixInput.value.toUpperCase(); }
        }
    </script>

</body>
</html>
