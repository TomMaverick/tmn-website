<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flugbuchung - Verfügbare Flüge</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>

    <header>
        <div>
            <h1 class="header-title">SkyNexus Flugbuchung</h1>
            <p class="header-subtitle">Finden und buchen Sie Ihren nächsten Flug</p>
        </div>
    </header>

    <main>
        <h2 class="main-title">Flüge suchen & buchen</h2>

        <form action="index.php" method="GET" class="filter-form">
             <label for="departure_city">Abflugort (Stadt):</label>
             <input type="text" id="departure_city" name="departure_city" value="<?php echo $filter_departure_city; ?>">

             <label for="arrival_city">Zielort (Stadt):</label>
             <input type="text" id="arrival_city" name="arrival_city" value="<?php echo $filter_arrival_city; ?>">

             <label for="date">Datum:</label>
             <input type="date" id="date" name="date" value="<?php echo $filter_date; ?>">

             <button type="submit">Flüge suchen</button>
             <a href="index.php" style="margin-left: 1em; color: var(--link-text-color);">Filter zurücksetzen</a>
        </form>

        <h3 class="main-category">Verfügbare Flüge</h3>

        <?php // Anzeige der Fehlermeldung, falls vorhanden ?>
        <?php if (!empty($error_message)): ?>
            <p class="no-flights" style="color: red;"><?php echo htmlspecialchars($error_message); ?></p>
        <?php // Anzeige der Flüge, falls keine Fehler und Daten vorhanden ?>
        <?php elseif (!empty($flights_data)): ?>
            <table class="flight-table">
                <thead>
                    <tr>
                        <th>Flugnr.</th>
                        <th>Von</th>
                        <th>Nach</th>
                        <th>Datum</th>
                        <th>Abflugzeit</th>
                        <th>Preis (Eco)</th>
                        <th>Status</th>
                        <th>Aktion</th>
                    </tr>
                </thead>
                <tbody>
                    <?php // Schleife durch das $flights_data Array ?>
                    <?php foreach ($flights_data as $flight): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($flight['flight_number']); ?></td>
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
                            <td><?php echo htmlspecialchars(date("d.m.Y", strtotime($flight['departure_date']))); ?></td>
                            <td><?php echo htmlspecialchars(substr($flight['departure_time'], 0, 5)); ?></td>
                            <td><?php echo htmlspecialchars(number_format($flight['price_economy'], 2, ',', '.')); ?> €</td>
                            <td><?php echo htmlspecialchars($flight['status']); ?></td>
                            <td>
                                <a href="booking.php?flight_id=<?php echo $flight['flight_id']; ?>" class="book-button">Buchen</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php // Meldung, wenn keine Flüge gefunden wurden (und kein Fehler vorlag) ?>
        <?php else: ?>
            <p class="no-flights">Keine Flüge für die gewählten Kriterien gefunden.</p>
        <?php endif; ?>

    </main>

    <footer>
        <p>&copy; <?php echo date("Y"); ?> SkyNexus Airline Management</p>
    </footer>

</body>
</html>
