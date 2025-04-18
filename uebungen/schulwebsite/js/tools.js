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
            // Berechne die verbleibenden Minuten im aktuellen Stunde
            const minuten = Math.floor((differenz % (1000 * 60 * 60)) / (1000 * 60));
            // Berechne die verbleibenden Sekunden im aktuellen Minute
            const sekunden = Math.floor((differenz % (1000 * 60)) / 1000);

            if (tage >= 1) {
                countdownElement.textContent = `${tage} Tage\n${stunden} Std. ${minuten} Min. ${sekunden} Sek.`;
            } else if (stunden >= 1) {
                countdownElement.textContent = `${stunden} Std. ${minuten} Min. ${sekunden} Sek.`;
            } else if (minuten >= 1) {
                countdownElement.textContent = `${minuten} Min. ${sekunden} Sek.`;
            } else if (sekunden >= 1) {
                countdownElement.textContent = `${sekunden} Sek.`;
            } else {
                countdownElement.textContent = `Die Prüfung hat begonnen!\nViel Erfolg!`;
            }
        }
        updateCountdown();      // Initiales Update
        setInterval(updateCountdown, 1000);      // Update jede Sekunde
    }
    // Direkt den Countdown starten
    startCountdown();


    // Schaltjahr
    function schaltjahr() {
        let jahr = parseFloat(prompt("Welches Jahr wollen sie prüfen? Keine Eingabe zum beenden.", ""));

        if (isNaN(jahr)) {
            alert("Bitte geben Sie ein gültiges Jahr ein!");
        } else if (jahr % 400 === 0) {
            alert(`${jahr} ist ein Schaltjahr!`)
        } else if (jahr % 100 === 0) {
            alert(`${jahr} ist KEIN Schaltjahr!`)
        } else if (jahr % 4 === 0) {
            alert(`${jahr} ist ein Schaltjahr!`)
        } else {
            alert(`${jahr} ist KEIN Schaltjahr!`)
        }
    }
    // Event-Listener, der die Funktion beim Klick auf den Button ausführt
    document.getElementById('schaltjahrBerechnen').addEventListener('click', schaltjahr);
});