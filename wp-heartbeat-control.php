<?php
/**
 * Plugin Name: Heartbeat Control
 * Plugin URI:  https://github.com/Azanumer/wp-heartbeat-control
 * Description: Take control of the WordPress Heartbeat API — disable it on the frontend, slow it down in the dashboard and post editor. Fewer AJAX polls = faster site, lower server load.
 * Version:     1.0.0
 * Author:      Azan Umer
 * License:     MIT
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WHC_VERSION', '1.0.0' );
define( 'WHC_OPTION', 'whc_settings' );

/**
 * Default settings. Intervals are in seconds.
 */
function whc_defaults() {
	return array(
		'frontend'  => 'disable', // disable | allow
		'dashboard' => 60,        // default WP is 15s
		'editor'    => 60,        // default WP is 15s
		'override'  => true,      // deregister WP's 15/30/60 'minimalInterval' script values
	);
}

function whc_get_settings() {
	$saved = get_option( WHC_OPTION, array() );
	return wp_parse_args( $saved, whc_defaults() );
}

/**
 * Throttle the heartbeat intervals. WP only offers '5'/'15'/'30'/'60' (sec)
 * to the JS via heartbeat-settings — we pick the closest supported value
 * at or above the user's choice.
 */
function whc_heartbeat_settings( $settings ) {
	$opts = whc_get_settings();

	// Kill it on the frontend entirely.
	if ( $opts['frontend'] === 'disable' && ! is_admin() ) {
		wp_deregister_script( 'heartbeat' );
		return $settings;
	}

	if ( ! is_admin() ) {
		// Frontend allowed: keep default WP behavior.
		return $settings;
	}

	// Allow code-level overrides: add_filter( 'whc_intervals', ... ).
	$intervals = apply_filters( 'whc_intervals', array(
		'dashboard' => $opts['dashboard'],
		'editor'    => $opts['editor'],
	) );

	$seconds = $intervals['dashboard'];
	if ( isset( $GLOBALS['pagenow'] ) && in_array( $GLOBALS['pagenow'], array( 'post.php', 'post-new.php' ), true ) ) {
		$seconds = $intervals['editor'];
	}

	$seconds = (int) $seconds;
	$supported = array( 5, 15, 30, 60, 120 );
	foreach ( $supported as $s ) {
		if ( $seconds <= $s ) {
			$seconds = $s;
			break;
		}
	}

	$settings['interval'] = $seconds;
	if ( $opts['override'] ) {
		// WP's 'minimalInterval' dropdown: force the same interval everywhere.
		$settings['minimalInterval'] = $seconds;
	}
	return $settings;
}
add_filter( 'heartbeat_settings', 'whc_heartbeat_settings' );

/**
 * Also limit heartbeat on the login page (it polls there too).
 */
function whc_limit_login_poll() {
	$opts = whc_get_settings();
	if ( $opts['frontend'] === 'disable' ) {
		wp_deregister_script( 'heartbeat' );
	}
}
add_action( 'login_enqueue_scripts', 'whc_limit_login_poll', 20 );

/* ---------------- Settings page ---------------- */

function whc_register_settings() {
	register_setting( 'whc_group', WHC_OPTION, 'whc_sanitize' );
	add_settings_section( 'whc_main', 'Heartbeat behavior', '__return_false', 'whc-page' );
}
add_action( 'admin_init', 'whc_register_settings' );

function whc_sanitize( $input ) {
	$clean = whc_defaults();
	if ( isset( $input['frontend'] ) ) {
		$clean['frontend'] = ( $input['frontend'] === 'disable' ) ? 'disable' : 'allow';
	}
	foreach ( array( 'dashboard', 'editor' ) as $key ) {
		if ( isset( $input[ $key ] ) ) {
			$clean[ $key ] = min( 300, max( 15, (int) $input[ $key ] ) );
		}
	}
	$clean['override'] = ! empty( $input['override'] );
	return $clean;
}

function whc_menu() {
	add_options_page( 'Heartbeat Control', 'Heartbeat Control', 'manage_options', 'whc-page', 'whc_page' );
}
add_action( 'admin_menu', 'whc_menu' );

function whc_page() {
	$opts = whc_get_settings();
	?>
	<div class="wrap">
		<h1>Heartbeat Control</h1>
		<p>WordPress pings <code>admin-ajax.php</code> every 15 seconds via the Heartbeat API. On busy sites that is a lot of wasted PHP requests.</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'whc_group' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row">Frontend</th>
					<td>
						<select name="<?php echo esc_attr( WHC_OPTION ); ?>[frontend]">
							<option value="disable" <?php selected( $opts['frontend'], 'disable' ); ?>>Disable (recommended)</option>
							<option value="allow" <?php selected( $opts['frontend'], 'allow' ); ?>>Allow (default WP behavior)</option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="whc-dashboard">Dashboard interval (sec)</label></th>
					<td><input id="whc-dashboard" type="number" min="15" max="300" step="5" name="<?php echo esc_attr( WHC_OPTION ); ?>[dashboard]" value="<?php echo (int) $opts['dashboard']; ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="whc-editor">Post editor interval (sec)</label></th>
					<td><input id="whc-editor" type="number" min="15" max="300" step="5" name="<?php echo esc_attr( WHC_OPTION ); ?>[editor]" value="<?php echo (int) $opts['editor']; ?>"></td>
				</tr>
				<tr>
					<th scope="row">Force interval</th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( WHC_OPTION ); ?>[override]" value="1" <?php checked( $opts['override'] ); ?>> Override WP's minimalInterval so the chosen interval always wins</label></td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/* Example code-level override (functions.php or a must-use plugin):
 * add_filter( 'whc_intervals', function( $intervals ) {
 *     return array( 'dashboard' => 120, 'editor' => 120 );
 * } );
 */

/* ---------------- Uninstall ---------------- */
// See uninstall.php — drops the whc_settings option.
