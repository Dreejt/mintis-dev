<?php
/**
 * Setup WP-CLI configuration from .env file
 * Runs automatically after: composer install
 * Manual run: composer run-script setup-wp-cli
 * Force overwrite of a custom wp-cli.local.yml: php setup-wp-cli.php --force
 *
 * The SSH aliases (server user + IP) go to wp-cli.local.yml, which WP-CLI reads
 * before wp-cli.yml and which inherits it. wp-cli.yml stays tracked for the
 * server deploy (path: web/wp) and is only created when missing, never
 * rewritten: a `composer install` can no longer leave server hosts in a file
 * that ends up in git.
 */

$force = in_array('--force', $argv);

if (!file_exists('.env')) {
    echo "⚠️  .env file not found. Skipping wp-cli.local.yml generation.\n";
    if (file_exists('.env.example.local')) {
        echo "💡 Copy .env.example.local to .env and run 'composer run-script setup-wp-cli' when ready.\n";
    } else {
        echo "💡 Copy .env.example to .env and run 'composer run-script setup-wp-cli' when ready.\n";
    }
    exit(0); // Exit gracefully, don't break install
}

$env = [];
$lines = file('.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

foreach ($lines as $line) {
    if (strpos($line, '=') !== false && !str_starts_with(trim($line), '#')) {
        [$key, $value] = explode('=', $line, 2);
        $env[trim($key)] = trim($value, '"\'');
    }
}

// Shared base config: only created when missing (fresh project), never rewritten.
if (!file_exists('wp-cli.yml')) {
    file_put_contents('wp-cli.yml', <<<YML
# Tracked in git: the server deploy needs 'path' to find WordPress.
# SSH aliases live in wp-cli.local.yml (gitignored, generated from .env).

path: web/wp
server:
  docroot: web

@development:
  path: web/wp

YML);
    echo "✅ wp-cli.yml created (base config, no server hosts)\n";
}

// Without a complete server block an alias would point at "user@" with an empty
// host; skip instead of writing a broken alias.
$required = ['SERVER_USER', 'SERVER_IP', 'SERVER_BASE_PATH', 'PROD_DOMAIN', 'STAGING_DOMAIN'];
$missing = array_filter($required, fn ($var) => empty($env[$var]));
if ($missing) {
    echo "⚠️  Skipping wp-cli.local.yml: empty or missing in .env: " . implode(', ', $missing) . "\n";
    echo "💡 Fill them in and run 'composer run-script setup-wp-cli' for @staging/@production.\n";
    exit(0);
}

$localFile = 'wp-cli.local.yml';
if (file_exists($localFile) && !str_contains(file_get_contents($localFile), 'Auto-generated from .env variables')) {
    if ($force) {
        echo "⚠️  Forcing overwrite of custom $localFile...\n";
    } else {
        echo "⚠️  $localFile already exists and appears to be custom.\n";
        echo "❓ Do you want to overwrite it? [y/N]: ";
        $handle = fopen("php://stdin", "r");
        $answer = fgets($handle);
        fclose($handle);
        if (trim(strtolower((string) $answer)) !== 'y') {
            echo "❌ Aborted. Your existing $localFile was not modified.\n";
            echo "💡 Or force overwrite with: php setup-wp-cli.php --force\n";
            exit(0);
        }
    }
}

file_put_contents($localFile, <<<YML
# Auto-generated from .env variables — per developer, never commit this file.
# Regenerate with: composer run-script setup-wp-cli

_:
  inherit: wp-cli.yml

@staging:
  ssh: {$env['SERVER_USER']}@{$env['SERVER_IP']}
  path: {$env['SERVER_BASE_PATH']}/{$env['STAGING_DOMAIN']}/web/wp

@production:
  ssh: {$env['SERVER_USER']}@{$env['SERVER_IP']}
  path: {$env['SERVER_BASE_PATH']}/{$env['PROD_DOMAIN']}/web/wp

YML);
echo "✅ $localFile generated from .env variables\n";

// Projects older than this change may not ignore the local file yet; exclude it
// locally rather than editing the tracked .gitignore.
exec('git check-ignore -q ' . escapeshellarg($localFile) . ' 2>/dev/null', $out, $ignored);
if ($ignored !== 0) {
    $exclude = trim((string) shell_exec('git rev-parse --git-path info/exclude 2>/dev/null'));
    if ($exclude !== '') {
        @mkdir(dirname($exclude), 0777, true);
        file_put_contents($exclude, "\n# mintis-dev: per-developer WP-CLI aliases\n$localFile\n", FILE_APPEND);
        echo "🔒 $localFile added to $exclude (add it to .gitignore as well)\n";
    }
}
