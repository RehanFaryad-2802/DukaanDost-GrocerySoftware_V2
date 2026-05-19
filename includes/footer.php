</main>
</div>
</div>

<!-- Bootstrap JavaScript -->
<script src="assets/js/bootstrap.bundle.min.js"></script>

<!-- Custom JavaScript -->
<script>
    setTimeout(function () {
        let alerts = document.querySelectorAll('.alert');
        alerts.forEach(function (alert) {
            alert.style.display = 'none';
        });
    }, 3000);

    function formatCurrency(amount) {
        return '<?php echo $settings['currency_symbol']; ?>' + parseFloat(amount).toFixed(2);
    }

    function confirmDelete(message) {
        return confirm(message || 'Are you sure you want to delete this item?');
    }
</script>
</body>

</html>