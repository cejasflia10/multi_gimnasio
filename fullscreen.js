// Activar pantalla completa al primer clic del usuario
document.addEventListener('DOMContentLoaded', function () {
    const body = document.body;

    function entrarPantallaCompleta() {
        if (!document.fullscreenElement && body.requestFullscreen) {
            body.requestFullscreen().catch(err => {
                console.warn("No se pudo activar pantalla completa:", err);
            });
        }
    }

    // Activar pantalla completa al hacer clic una vez
    body.addEventListener('click', entrarPantallaCompleta, { once: true });
});

// Bloquear clic derecho
document.addEventListener('contextmenu', function (e) {
    e.preventDefault();
});

// Bloquear combinaciones como F12, Ctrl+Shift+I, Ctrl+U
document.addEventListener('keydown', function (e) {
    if (
        e.key === "F12" ||
        (e.ctrlKey && e.shiftKey && (e.key === "I" || e.key === "J")) ||
        (e.ctrlKey && e.key === "U")
    ) {
        e.preventDefault();
    }
});
