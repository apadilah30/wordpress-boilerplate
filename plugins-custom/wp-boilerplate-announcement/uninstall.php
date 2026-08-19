<?php
/**
 * Uninstall handler — dijalankan saat plugin di-uninstall dari WP admin.
 * Bersihkan opsi database supaya tidak ada residu.
 */

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('wpba_settings');
