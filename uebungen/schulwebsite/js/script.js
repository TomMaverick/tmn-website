"use strict";   // Aktiviert strict-mode. Dient dazu sicheren und sauberen Code zu schreiben

// Dark Mode
document.addEventListener("DOMContentLoaded", () => {
    const darkModeButton = document.getElementById("darkModeButton");   // Button für DarkMode
    const body = document.body;
    const dateTimeElement = document.getElementById("dateTime");        // Element für Datum und Zeit

    // Prüfe ob Einstellungen im localStorage vorhanden sind
    if (localStorage.getItem("darkMode") === "enabled") {
        body.classList.add("darkMode");
        body.classList.remove("lightMode");
    } else {
        body.classList.add("lightMode");
        body.classList.remove("darkMode");
    }


    // DarkMode toggle
    darkModeButton.addEventListener("click", () => {
        if (body.classList.contains("lightMode")) {
            body.classList.replace("lightMode", "darkMode");
            localStorage.setItem("darkMode", "enabled");    // Speichert DM Einstellung
        } else {
            body.classList.replace("darkMode", "lightMode");
            localStorage.setItem("darkMode", "disabled");   // Speichert DM Einstellung
        }
    }
    );


    // Datums und Zeitanzeige
    function updateDateTime() {
        const now = new Date();
        const options = { weekday: 'short', year: 'numeric', month: 'long', day: 'numeric' };
        const date = now.toLocaleDateString('de-DE', options);  // Datumsformat
        const time = now.toLocaleTimeString('de-DE');           // Zeitformat

        // Kombiniere Datum und Zeit
        dateTimeElement.textContent = `${date} - ${time}`;
    }
    // Update Intervall
    setInterval(updateDateTime, 1000);
    updateDateTime();
}
);
