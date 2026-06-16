
            </div><!-- /.content-body -->
        </main>
    </div><!-- /.app-layout -->

    <div id="toast-container" class="toast-container"></div>

    <script src="../assets/js/app.js?v=<?= filemtime(__DIR__ . '/../assets/js/app.js') ?>"></script>
    <?php if (isset($extraJS)): ?>
        <script><?= $extraJS ?></script>
    <?php endif; ?>
</body>
</html>
