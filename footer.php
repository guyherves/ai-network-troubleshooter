    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Live Clock
        function updateClock() {
            const now = new Date();
            const clockEl = document.getElementById('liveClock');
            if (clockEl) {
                clockEl.textContent = now.toLocaleTimeString();
            }
        }
        setInterval(updateClock, 1000);
        updateClock();

        // Theme Toggle
        const themeBtn = document.getElementById('themeToggle');
        if (themeBtn) {
            themeBtn.addEventListener('click', () => {
                const current = document.documentElement.getAttribute('data-theme');
                const newTheme = current === 'dark' ? 'light' : 'dark';
                document.documentElement.setAttribute('data-theme', newTheme);
                localStorage.setItem('noc_theme', newTheme);
                const icon = document.getElementById('themeIcon');
                if (icon) icon.className = newTheme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
            });

            // Set initial icon to match saved theme
            const initTheme = document.documentElement.getAttribute('data-theme') || 'dark';
            const initIcon = document.getElementById('themeIcon');
            if (initIcon) initIcon.className = initTheme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
        }
    </script>
</body>
</html>
