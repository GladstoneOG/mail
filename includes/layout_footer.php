
            </div><!-- /.content-area -->
        </main>
    </div><!-- /.app-container -->

    <div class="toast-container" id="toast-container"></div>

    <script>
        var APP_CONFIG = {
            csrfToken: '<?php echo get_csrf_token(); ?>',
            userId: <?php echo auth_user_id(); ?>,
            pollInterval: <?php echo NOTIFICATION_POLL_INTERVAL; ?>,
            baseUrl: 'index.php',
            currentPage: '<?php echo isset($page) ? $page : ""; ?>'
        };
    </script>
    <script src="assets/js/app.js"></script>
</body>
</html>
