</main><!-- /.page-wrapper -->

<!-- ═══════════════ FOOTER ═══════════════ -->
<footer class="site-footer">
    <div class="footer-inner">
        <span class="footer-brand" style="display: inline-flex; align-items: center; gap: 8px;">
            <img src="<?php echo $basePath; ?>/assets/images/logo-trophy.png" alt="AthleteHub Logo" style="width: 32px; height: 32px; object-fit: contain;">
            Athlete<strong>Hub</strong>
        </span>
        <span class="footer-copy">&copy; 2026 AthleteHub. All rights reserved.</span>
    </div>
</footer>

<!-- JavaScript -->
<script src="<?php echo $basePath; ?>/assets/js/main.js?t=<?php echo time(); ?>"></script>
<?php if (!empty($pageJS)): ?>
    <?php foreach ((array)$pageJS as $js): ?>
        <script src="<?php echo $basePath; ?>/assets/js/<?php echo $js; ?>.js?t=<?php echo time(); ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
