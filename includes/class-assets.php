<?php
/**
 * Front-end scripts and styles.
 *
 * @package CrossStraitPhraseSwitch
 */

namespace CrossStraitPhraseSwitch;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers a small loader; OpenCC and dictionaries load on demand.
 */
class Assets {
	/**
	 * @var Plugin
	 */
	private $plugin;

	/**
	 * @param Plugin $plugin Plugin instance.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * @return void
	 */
	public function register() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 20 );
	}

	/**
	 * @return void
	 */
	public function enqueue() {
		if ( is_admin() ) {
			return;
		}

		$openCcPath  = CROSS_STRAIT_PHRASE_SWITCH_DIR . 'assets/js/opencc-full.min.js';
		$dictsPath   = CROSS_STRAIT_PHRASE_SWITCH_DIR . 'assets/js/bundled-phrases.js';
		$convertPath = CROSS_STRAIT_PHRASE_SWITCH_DIR . 'assets/js/convert.js';
		if ( ! is_readable( $openCcPath ) || ! is_readable( $dictsPath ) || ! is_readable( $convertPath ) ) {
			error_log( 'Cross-Strait Phrase Switch: converter assets are not readable.' );
			return;
		}

		$baseUrl = CROSS_STRAIT_PHRASE_SWITCH_URL . 'assets/js/';

		if ( $this->plugin->usesPuockIntegration() ) {
			wp_deregister_script( 'puock-opencc' );
			$scripts = wp_scripts();
			if ( isset( $scripts->registered['puock'] ) && is_array( $scripts->registered['puock']->deps ) ) {
				$scripts->registered['puock']->deps = array_values(
					array_diff( $scripts->registered['puock']->deps, array( 'puock-opencc' ) )
				);
			}
		}

		wp_enqueue_script(
			'csps-convert',
			$baseUrl . 'convert.js',
			array(),
			CROSS_STRAIT_PHRASE_SWITCH_VERSION,
			true
		);

		$userUrl  = Dictionaries::compiledUrl();
		$userPath = Dictionaries::compiledPath();
		if ( $userUrl === '' || ! is_readable( $userPath ) ) {
			$userUrl = '';
		}

		$settingsJson = wp_json_encode(
			array(
				'cookieName'    => CROSS_STRAIT_PHRASE_SWITCH_COOKIE,
				'cookiePath'    => defined( 'COOKIEPATH' ) && COOKIEPATH !== '' ? COOKIEPATH : '/',
				'initialLocale' => $this->plugin->getCurrentLocale(),
				'usePuock'      => $this->plugin->usesPuockIntegration(),
				'toggleLabel'   => __( '繁中/簡中 切換', 'cross-strait-phrase-switch' ),
				'openCcUrl'     => $baseUrl . 'opencc-full.min.js?ver=1.4.1',
				'bundledUrl'    => $baseUrl . 'bundled-phrases.js?ver=' . CROSS_STRAIT_PHRASE_SWITCH_VERSION,
				'userDictUrl'   => $userUrl === '' ? '' : $userUrl . '?ver=' . (string) filemtime( $userPath ),
			)
		);
		if ( ! is_string( $settingsJson ) || $settingsJson === '' ) {
			error_log( 'Cross-Strait Phrase Switch: failed to encode front-end settings.' );
			return;
		}
		wp_add_inline_script( 'csps-convert', 'window.CSPS_SETTINGS = ' . $settingsJson . ';', 'before' );

		wp_enqueue_style(
			'csps-toggle',
			CROSS_STRAIT_PHRASE_SWITCH_URL . 'assets/css/toggle.css',
			array(),
			CROSS_STRAIT_PHRASE_SWITCH_VERSION
		);
	}
}
