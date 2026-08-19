<footer class="site-footer">
    <?php
    if (has_nav_menu('footer')) {
        wp_nav_menu(['theme_location' => 'footer', 'container' => 'nav']);
    }
    ?>
    <p>&copy; <?php echo esc_html((string) date('Y')); ?> <?php bloginfo('name'); ?></p>
</footer>
<?php wp_footer(); ?>
</body>
</html>
