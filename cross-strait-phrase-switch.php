<?php
/**
 * Plugin Name: Cross-Strait Phrase Switch
 * Description: Converts between Taiwan and Mainland Chinese vocabulary, not only Traditional/Simplified glyphs.
 * Version: 1.0.1
 * Requires at least: 6.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * Author: Roy
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: cross-strait-phrase-switch
 *
 * @package CrossStraitPhraseSwitch
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CROSS_STRAIT_PHRASE_SWITCH_VERSION', '1.0.1' );
define( 'CROSS_STRAIT_PHRASE_SWITCH_FILE', __FILE__ );
define( 'CROSS_STRAIT_PHRASE_SWITCH_DIR', plugin_dir_path( __FILE__ ) );
define( 'CROSS_STRAIT_PHRASE_SWITCH_URL', plugin_dir_url( __FILE__ ) );
define( 'CROSS_STRAIT_PHRASE_SWITCH_COOKIE', 'csps_locale' );

require_once CROSS_STRAIT_PHRASE_SWITCH_DIR . 'includes/class-dictionaries.php';
require_once CROSS_STRAIT_PHRASE_SWITCH_DIR . 'includes/class-assets.php';
require_once CROSS_STRAIT_PHRASE_SWITCH_DIR . 'includes/class-toggle.php';
require_once CROSS_STRAIT_PHRASE_SWITCH_DIR . 'includes/class-settings.php';
require_once CROSS_STRAIT_PHRASE_SWITCH_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'CrossStraitPhraseSwitch\\Plugin', 'activate' ) );

add_action(
	'plugins_loaded',
	static function () {
		CrossStraitPhraseSwitch\Plugin::instance()->boot();
	}
);
