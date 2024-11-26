document.querySelector('.toggle-button').addEventListener('click', function() {
    document.body.classList.toggle('light-mode');

    document.querySelector('.toggle-button').innerHTML = document.body.classList.contains('light-mode')
        ? '<i class="fas fa-moon"></i>'  // Moon icon for light mode
        : '<i class="fas fa-sun"></i>';   // Sun icon for dark mode
});
