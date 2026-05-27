<?php
/**
 * Footer template.
 */
?>
    <footer class="site-footer">
        <div class="container footer-grid">
            <div>
                <a class="logo footer-logo" href="<?php echo esc_url(home_url('/')); ?>" rel="home">
                    <span class="logo-mark" aria-hidden="true">T</span>
                    <span><?php bloginfo('name'); ?></span>
                </a>
                <p>חופשות באירופה, טיסות וביטוח נסיעות עם בדיקה מסודרת לפני שמזמינים.</p>
            </div>
            <div>
                <h2>יעדים</h2>
                <a href="<?php echo esc_url(home_url('/budapest-vacation/')); ?>">חופשה בבודפשט</a>
                <a href="<?php echo esc_url(home_url('/prague-vacation/')); ?>">חופשה בפראג</a>
                <a href="<?php echo esc_url(home_url('/vienna-vacation/')); ?>">חופשה בוינה</a>
                <a href="<?php echo esc_url(home_url('/budapest-prague-vienna-trip/')); ?>">בודפשט פראג ווינה</a>
            </div>
            <div>
                <h2>שירותים</h2>
                <a href="<?php echo esc_url(home_url('/cheap-flights-europe/')); ?>">טיסות זולות לאירופה</a>
                <a href="<?php echo esc_url(home_url('/travel-insurance-europe/')); ?>">ביטוח נסיעות לאירופה</a>
                <a href="<?php echo esc_url(home_url('/#inquiry')); ?>">בדיקת חופשה</a>
            </div>
            <div>
                <h2>לפני הזמנה</h2>
                <p>מחירים, זמינות, כבודה, תנאי שינוי וביטוח חייבים להיבדק מול אתר ההזמנה או חברת השירות לפני רכישה.</p>
            </div>
        </div>
        <div class="container footer-bottom">
            <p>&copy; <?php echo esc_html(gmdate('Y')); ?> <?php bloginfo('name'); ?>. כל הזכויות שמורות.</p>
        </div>
    </footer>
</div>
<?php wp_footer(); ?>
</body>
</html>
