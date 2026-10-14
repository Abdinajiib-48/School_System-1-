<?php
include_once __DIR__ . '/../auth.php';
internal_file_only(__FILE__);   // included by other pages, never opened directly
// Closes the divs opened in sidebar.php
?>
</div></div>

<script>
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    if (menuToggle) {
        menuToggle.addEventListener('click', function () { sidebar.classList.toggle('active'); });
    }
    document.addEventListener('click', function (event) {
        if (window.innerWidth <= 768) {
            if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
                sidebar.classList.remove('active');
            }
        }
    });
</script>
</body>
</html>
