<?php
// includes/footer.php
?>
</main>
<footer class="text-center mt-5 mb-3">
    <p>&copy; <?php echo date('Y'); ?> ProspekApp. All Rights Reserved.</p>
</footer>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script>
// Inisialisasi semua tabel dengan class .sortable-table
$(document).ready(function() {
    $('.sortable-table').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.13.7/i18n/id.json"
        }
    });
});
</script>
</body>
</html>