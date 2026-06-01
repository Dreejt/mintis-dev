<?php
/**
 * Plugin Name: Mintis Debug Symlink
 * Description: Maakt automatisch een symlink aan van de PHP error_log naar
 *              <bedrock-root>/app/debug.log. Alleen actief in de
 *              development-omgeving (WP_ENV=development) of op het lokale
 *              ontwikkelmachine (Herd). Als dit niet werkt, plaats de code
 *              dan in bedrock/config/application.php.
 * Author:      Mintis
 * Author URI:  https://mintis.nl
 * License:     MIT
 *
 * @see https://herd.laravel.com/
 */

if (!defined('ABSPATH')) {
    exit;
}

// Harde stop: nooit uitvoeren buiten development, ook niet als de plugin
// per ongeluk toch geïnstalleerd is via `composer install` zonder --no-dev.
if (getenv('WP_ENV') !== 'development') {
    return;
}

add_action('init', function () {

    $target = ini_get('error_log');
    $link   = dirname(ABSPATH) . '/app/debug.log';

    // Fallback: Herd's standaard FPM-logpad als ini_get leeg is
    // (Herd zet error_log in php-fpm pool config, niet in php.ini)
    if (empty($target) || !file_exists($target)) {
        $herdLog = getenv('HOME') . '/Library/Application Support/Herd/Log/php-fpm.log';
        if (file_exists($herdLog)) {
            $target = $herdLog;
        }
    }

    // Geen geldig target gevonden → niets doen
    if (empty($target) || !file_exists($target)) {
        return;
    }

    // Verwijder circulaire of kapotte symlink
    if (is_link($link)) {
        $resolved = @readlink($link);
        if ($resolved === false || !file_exists($resolved) || realpath($link) === $link) {
            unlink($link);
        }
    }

    // Verwijder kapotte reguliere file als er al een bestaat op het pad
    if (!is_link($link) && file_exists($link)) {
        unlink($link);
    }

    // Maak de symlink aan als die nog niet bestaat
    if (!file_exists($link) && !is_link($link)) {
        symlink($target, $link);
    }
});
