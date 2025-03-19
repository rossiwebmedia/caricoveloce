// Funzione principale
document.addEventListener('DOMContentLoaded', function() {
    // Toggle della sidebar su dispositivi mobili
    const sidebarCollapse = document.getElementById('sidebarCollapse');
    if (sidebarCollapse) {
        sidebarCollapse.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('content').classList.toggle('active');
        });
    }
    
    // Verifica se siamo su un dispositivo mobile e nascondi la sidebar
    if (window.innerWidth <= 768) {
        document.getElementById('sidebar').classList.add('active');
        document.getElementById('content').classList.add('active');
    }
    
    // Inizializza i tooltip di Bootstrap
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Nascondi automaticamente gli alert dopo 5 secondi
    setTimeout(function() {
        var alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
        alerts.forEach(function(alert) {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
});