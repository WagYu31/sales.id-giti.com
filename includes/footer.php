<?php
// includes/footer.php
?>
    </main><!-- end content-area -->

    <footer style="text-align:center;padding:20px 32px;border-top:1px solid #E2E8F0;">
        <p style="margin:0;font-size:12px;font-weight:500;color:#94A3B8;font-family:'Inter',sans-serif;">
            &copy; <?php echo date('Y'); ?> Loewix Sales. All Rights Reserved.
        </p>
    </footer>
</div><!-- end main-wrapper -->

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    if ($('.sortable-table').length) {
        $('.sortable-table').DataTable({
            "order": [],
            "deferRender": true,
            "pageLength": 25,
            "language": {
                "sProcessing":   "Sedang memproses...",
                "sLengthMenu":   "Tampilkan _MENU_ entri",
                "sZeroRecords":  "Tidak ditemukan data yang sesuai",
                "sInfo":         "Menampilkan _START_ sampai _END_ dari _TOTAL_ entri",
                "sInfoEmpty":    "Menampilkan 0 sampai 0 dari 0 entri",
                "sInfoFiltered": "(disaring dari _MAX_ entri keseluruhan)",
                "sSearch":       "Cari:",
                "oPaginate": {
                    "sFirst":    "Pertama",
                    "sPrevious": "Sebelumnya",
                    "sNext":     "Selanjutnya",
                    "sLast":     "Terakhir"
                }
            }
        });
    }
});
</script>
</body>
</html>