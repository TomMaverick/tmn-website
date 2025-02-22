"use strict";   // Aktiviert strict-mode. Dient dazu sicheren und sauberen Code zu schreiben

document.addEventListener("DOMContentLoaded", () => {

    // Spritrechner mit Eingabefeldern
    function berechneSpritverbrauch() {
            // Werte aus den Eingabefeldern werden in float umgewandelt
            let gefahreneKm2 = parseFloat(document.getElementById('gefahreneKm2').value);
            let verbrauchterSprit2 = parseFloat(document.getElementById('verbrauchterSprit2').value);

            // Prüfe die Eingaben auf gültigkeit
            if (isNaN(gefahreneKm2) || gefahreneKm2 <= 0 || isNaN(verbrauchterSprit2) || verbrauchterSprit2 <= 0) {
                document.getElementById('spritrechnerErgebnis').textContent = "Bitte geben Sie eine gültige Zahl ein!";
                return;     // Wenn Eingabe ungültig, beende Funktion
            }

            // Berechnung des Durchschnittsverbrauchs auf 100 km
            let durchschnitt2 = (verbrauchterSprit2 / gefahreneKm2) * 100;

            // Formatiert die Ausgabe mit zwei Kommastellen
            let text2 = `\nDurchschnittsverbrauch: ${durchschnitt2.toFixed(2)} Liter / 100km`;
            document.getElementById('spritrechnerErgebnis').textContent = text2;
        }
        // Event-Listener, der die Funktion beim Klick auf den Button ausführt
        document.getElementById('SpritrechnerBerechnen').addEventListener('click', berechneSpritverbrauch);


        // Countdown Abschlussprüfung
    function startCountdown() {
        const pruefungsDatum = new Date('2025-05-05T09:00:00');     // Prüfungsdatum: 5.5.2025, 9 Uhr
        const countdownElement = document.getElementById('countdownAbschlusspruefung');

        function updateCountdown() {
            const jetzt = new Date();
            const differenz = pruefungsDatum - jetzt;

            // Berechne die verbleibenden Tage (ganze Tage)
            const tage = Math.floor(differenz / (1000 * 60 * 60 * 24));
            // Berechne die verbleibenden Stunden des aktuellen Tages
            const stunden = Math.floor((differenz % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));

            if(tage >= 0) {
                countdownElement.textContent = `${tage} Tage und ${stunden} Stunden`;
            } else if (stunden >= 0) {
                countdownElement.textContent = `${stunden} Stunden`;
            } else if (stunden <= 0) {
                countdownElement.textContent = `Die Prüfung hat begonnen!`;
            } else {
                countdownElement.textContent = `Fehler in der Berechnung`;
            }
        }

        updateCountdown();      // Initiales Update
        setInterval(updateCountdown, 60000);      // Update jede Minute
    }

    // Direkt den Countdown starten
    startCountdown();
});