<!-- Qui termina il contenuto specifico della pagina -->
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script src="assets/js/main.js"></script>
    
    <?php if (isset($additionalScripts)): ?>
        <?php echo $additionalScripts; ?>
    <?php endif; ?>

    <script>
        $(document).ready(function () {
            // Toggle sidebar
            $('#sidebarCollapse').on('click', function () {
                $('#sidebar').toggleClass('active');
            });
        });
    </script>
</body>
</html>
