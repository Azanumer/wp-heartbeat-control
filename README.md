# Heartbeat Control

Take control of the WordPress Heartbeat API. By default WordPress pings `admin-ajax.php` every 15 seconds on every admin page, every open post editor tab, and even the login page — pure overhead on busy sites.

## What it does

| Setting | Default | What it changes |
|---|---|---|
| **Frontend** | Disabled | Deregisters the Heartbeat script entirely outside wp-admin |
| **Dashboard interval** | 60 sec | `heartbeat_settings['interval']` for the wp-admin screens |
| **Post editor interval** | 60 sec | Separate interval for `post.php` / `post-new.php` |
| **Force interval** | On | Also overrides WP's `minimalInterval` so the chosen value always wins |

## Filters

```php
// Code-level override (functions.php or must-use plugin):
add_filter( 'whc_intervals', function( $intervals ) {
    return array( 'dashboard' => 120, 'editor' => 120 );
} );
```

## Install

Upload to `wp-content/plugins/`, activate, go to **Settings → Heartbeat Control**.

Uninstalling drops the `whc_settings` option (see `uninstall.php`).

## License

MIT
