<?php
/**
 * openARMS Footer Component
 * 
 * Standard HTML footer for all pages
 */
?>
        </div><!-- /.container -->
    </main><!-- /.main-content -->

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <p>&copy; <?= date('Y') ?> openARMS — Shelter Resource Management System</p>
                <p class="footer-sub">For authorized shelter staff only</p>
            </div>
        </div>
    </footer>

    <!-- JavaScript -->
    <script src="<?= ASSETS_URL ?>/js/app.js"></script>
    
    <?php if (isset($extraScripts)): ?>
        <?= $extraScripts ?>
    <?php endif; ?>
</body>
</html>
