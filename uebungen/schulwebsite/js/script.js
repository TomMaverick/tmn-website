// Dark Mode und Uhrzeit
document.addEventListener("DOMContentLoaded", () => {
    const darkModeButton = document.getElementById("darkModeButton");// Button for Darkmode
    const body = document.body;
    const dateTimeElement = document.getElementById("dateTime"); // Element for date and time

    // Check if darkMode is saved in localStorage
    if (localStorage.getItem("darkMode") === "enabled") {
        body.classList.add("darkMode");
        body.classList.remove("lightMode");
    } else {
        body.classList.add("lightMode");
        body.classList.remove("darkMode");
    }
    // Dark-Light Mode toggle functionality
    darkModeButton.addEventListener("click", () => {
        if (body.classList.contains("lightMode")) {
            body.classList.replace("lightMode", "darkMode");
            localStorage.setItem("darkMode", "enabled");  // Save dark mode setting
        } else {
            body.classList.replace("darkMode", "lightMode");
            localStorage.setItem("darkMode", "disabled");// Save dark mode setting
        }
    });

    // Function to update date and time
    function updateDateTime() {
        const now = new Date();
        const options = { weekday: 'short', year: 'numeric', month: 'long', day: 'numeric' };
        const date = now.toLocaleDateString('de-DE', options); // Format date
        const time = now.toLocaleTimeString('de-DE'); // Format time

        // Combine the date and time
        dateTimeElement.textContent = `${date} - ${time}`;
    }
    // Update time every second
    setInterval(updateDateTime, 1000);
    // Function to start the time
    updateDateTime();
});
