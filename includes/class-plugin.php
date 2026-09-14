<?php
/**
 * Plugin bootstrap.
 *
 * @package CrossStraitPhraseSwitch
 */

namespace CrossStraitPhraseSwitch;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coordinates modules and default options.
 */
class Plugin {
	/**
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * @var array<string, mixed>
	 */
	private $settings = array();

	/**
	 * @return Plugin
	 */
	public static function instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Create upload directories and compile any extra dictionaries.
	 *
	 * @return void
	 */
	public static function activate() {
		$plugin = self::instance();
		$plugin->ensureSettings();
		if ( get_option( 'cross_strait_phrase_switch', null ) === null ) {
			add_option( 'cross_strait_phrase_switch', $plugin->getSettings() );
		}
		Dictionaries::ensureUploadDir();
		Dictionaries::maybeMigrateLegacyDicts();
		$compiled = Dictionaries::compile();
		if ( $compiled !== true && is_wp_error( $compiled ) ) {
			error_log( 'Cross-Strait Phrase Switch: activation compile failed: ' . $compiled->get_error_message() );
		}
	}

	/**
	 * @return void
	 */
	public function boot() {
		load_plugin_textdomain( 'cross-strait-phrase-switch', false, dirname( plugin_basename( CROSS_STRAIT_PHRASE_SWITCH_FILE ) ) . '/languages' );
		$this->ensureSettings();

		$assets     = new Assets( $this );
		$toggle     = new Toggle( $this );
		$settings   = new Settings( $this );
		$assets->register();
		$toggle->register();
		$settings->register();

		add_action( 'send_headers', array( $this, 'maybeSetDefaultCookie' ), 1 );
		add_filter( 'option_puock_options', array( $this, 'enablePuockToggle' ) );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getSettings() {
		return $this->settings;
	}

	/**
	 * @param string $key Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function getSetting( $key, $default = null ) {
		if ( array_key_exists( $key, $this->settings ) ) {
			return $this->settings[ $key ];
		}
		return $default;
	}

	/**
	 * @return bool
	 */
	public function usesPuockIntegration() {
		if ( ! $this->getSetting( 'integrate_puock', true ) ) {
			return false;
		}
		return get_template() === 'puock' || get_stylesheet() === 'puock';
	}

	/**
	 * @return string tw|cn
	 */
	public function getCurrentLocale() {
		if ( isset( $_COOKIE[ CROSS_STRAIT_PHRASE_SWITCH_COOKIE ] ) ) {
			$value = sanitize_text_field( wp_unslash( $_COOKIE[ CROSS_STRAIT_PHRASE_SWITCH_COOKIE ] ) );
			if ( $value === 'tw' || $value === 'cn' ) {
				return $value;
			}
		}
		if ( $this->usesPuockIntegration() && isset( $_COOKIE['pk_lang'] ) ) {
			$puock = sanitize_text_field( wp_unslash( $_COOKIE['pk_lang'] ) );
			return $puock === 'tc' ? 'tw' : 'cn';
		}
		return $this->getDefaultLocale();
	}

	/**
	 * @return string tw|cn
	 */
	public function getDefaultLocale() {
		$configured = $this->getSetting( 'default_locale', 'auto' );
		if ( $configured === 'tw' || $configured === 'cn' ) {
			return $configured;
		}
		$wpLocale = get_locale();
		if ( is_string( $wpLocale ) && preg_match( '/^(zh_TW|zh_HK|zh_MO)/', $wpLocale ) ) {
			return 'tw';
		}
		return 'cn';
	}

	/**
	 * Enable Puock's header language icon when integration is on.
	 *
	 * @param mixed $config Theme option array.
	 * @return mixed
	 */
	public function enablePuockToggle( $config ) {
		if ( ! $this->usesPuockIntegration() || ! is_array( $config ) ) {
			return $config;
		}
		$config['cn_sc_tc_toggle'] = true;
		return $config;
	}

	/**
	 * Set a Taiwan-phrase cookie from Accept-Language when none exists.
	 *
	 * @return void
	 */
	public function maybeSetDefaultCookie() {
		if ( is_admin() || headers_sent() ) {
			return;
		}
		if ( ! $this->getSetting( 'detect_accept_language', true ) ) {
			return;
		}
		if ( isset( $_COOKIE[ CROSS_STRAIT_PHRASE_SWITCH_COOKIE ] ) ) {
			return;
		}
		$acceptLanguage = isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ? (string) $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '';
		if ( $acceptLanguage === '' || ! preg_match( '/zh[-_](TW|HK|MO|Hant)/i', $acceptLanguage ) ) {
			return;
		}
		$this->setLocaleCookie( 'tw' );
	}

	/**
	 * @param string $locale tw|cn
	 * @return void
	 */
	public function setLocaleCookie( $locale ) {
		if ( $locale !== 'tw' && $locale !== 'cn' ) {
			error_log( 'Cross-Strait Phrase Switch: refused to set unknown locale cookie.' );
			return;
		}
		$cookiePath = defined( 'COOKIEPATH' ) && COOKIEPATH !== '' ? COOKIEPATH : '/';
		$options    = array(
			'expires'  => time() + YEAR_IN_SECONDS,
			'path'     => $cookiePath,
			'secure'   => is_ssl(),
			'httponly' => false,
			'samesite' => 'Lax',
		);
		$set = setcookie( CROSS_STRAIT_PHRASE_SWITCH_COOKIE, $locale, $options );
		if ( $set !== true ) {
			error_log( 'Cross-Strait Phrase Switch: failed to set locale cookie.' );
			return;
		}
		$_COOKIE[ CROSS_STRAIT_PHRASE_SWITCH_COOKIE ] = $locale;
		if ( $this->usesPuockIntegration() ) {
			$puockValue = $locale === 'tw' ? 'tc' : 'sc';
			$setPuock   = setcookie( 'pk_lang', $puockValue, $options );
			if ( $setPuock !== true ) {
				error_log( 'Cross-Strait Phrase Switch: failed to set Puock pk_lang cookie.' );
				return;
			}
			$_COOKIE['pk_lang'] = $puockValue;
		}
	}

	/**
	 * @return void
	 */
	private function ensureSettings() {
		$stored = get_option( 'cross_strait_phrase_switch', array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		$defaults = array(
			'detect_accept_language' => true,
			'floating_button'        => get_template() !== 'puock',
			'integrate_puock'        => true,
			'default_locale'         => 'auto',
		);
		$this->settings = array_merge( $defaults, $stored );
	}
}
