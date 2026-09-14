<?php
/**
 * Settings screen and dictionary upload.
 *
 * @package CrossStraitPhraseSwitch
 */

namespace CrossStraitPhraseSwitch;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * wp-admin settings for the converter.
 */
class Settings {
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
		add_action( 'admin_menu', array( $this, 'addPage' ) );
		add_action( 'admin_init', array( $this, 'registerSetting' ) );
		add_action( 'admin_post_csps_compile_dicts', array( $this, 'handleCompile' ) );
		add_action( 'admin_post_csps_upload_dict', array( $this, 'handleUpload' ) );
	}

	/**
	 * @return void
	 */
	public function addPage() {
		add_options_page(
			__( 'Cross-Strait Phrases', 'cross-strait-phrase-switch' ),
			__( 'Cross-Strait Phrases', 'cross-strait-phrase-switch' ),
			'manage_options',
			'cross-strait-phrase-switch',
			array( $this, 'renderPage' )
		);
	}

	/**
	 * @return void
	 */
	public function registerSetting() {
		register_setting(
			'cross_strait_phrase_switch',
			'cross_strait_phrase_switch',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(),
			)
		);
	}

	/**
	 * @param mixed $input Raw option.
	 * @return array<string, mixed>
	 */
	public function sanitize( $input ) {
		$data = is_array( $input ) ? $input : array();
		$defaultLocale = isset( $data['default_locale'] ) ? (string) $data['default_locale'] : 'auto';
		if ( ! in_array( $defaultLocale, array( 'auto', 'tw', 'cn' ), true ) ) {
			$defaultLocale = 'auto';
		}
		return array(
			'detect_accept_language' => ! empty( $data['detect_accept_language'] ),
			'floating_button'        => ! empty( $data['floating_button'] ),
			'integrate_puock'        => ! empty( $data['integrate_puock'] ),
			'default_locale'         => $defaultLocale,
		);
	}

	/**
	 * @return void
	 */
	public function handleCompile() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to compile dictionaries.', 'cross-strait-phrase-switch' ) );
		}
		check_admin_referer( 'csps_compile_dicts' );
		$result = Dictionaries::compile();
		$this->redirectAfterDictAction( $result );
	}

	/**
	 * @return void
	 */
	public function handleUpload() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to upload dictionaries.', 'cross-strait-phrase-switch' ) );
		}
		check_admin_referer( 'csps_upload_dict' );
		if ( empty( $_FILES['csps_dict_file'] ) || ! is_array( $_FILES['csps_dict_file'] ) ) {
			$this->redirectAfterDictAction( new \WP_Error( 'csps_no_file', __( 'No dictionary file was uploaded.', 'cross-strait-phrase-switch' ) ) );
			return;
		}
		$result = Dictionaries::storeUpload( $_FILES['csps_dict_file'] );
		if ( ! is_wp_error( $result ) ) {
			$result = Dictionaries::compile();
		}
		$this->redirectAfterDictAction( $result );
	}

	/**
	 * @return void
	 */
	public function renderPage() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage this plugin.', 'cross-strait-phrase-switch' ) );
		}
		$settings = $this->plugin->getSettings();
		$notice   = isset( $_GET['csps_notice'] ) ? sanitize_key( wp_unslash( $_GET['csps_notice'] ) ) : '';
		$files    = Dictionaries::listSourceFiles();
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Cross-Strait Phrase Switch', 'cross-strait-phrase-switch' ); ?></h1>
			<?php if ( $notice === 'compiled' ) : ?>
				<div class="notice notice-success"><p><?php echo esc_html__( 'Dictionaries compiled.', 'cross-strait-phrase-switch' ); ?></p></div>
			<?php elseif ( $notice === 'error' ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( isset( $_GET['csps_error'] ) ? sanitize_text_field( wp_unslash( $_GET['csps_error'] ) ) : __( 'Dictionary action failed.', 'cross-strait-phrase-switch' ) ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'cross_strait_phrase_switch' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php echo esc_html__( 'Default locale', 'cross-strait-phrase-switch' ); ?></th>
						<td>
							<select name="cross_strait_phrase_switch[default_locale]">
								<option value="auto" <?php selected( $settings['default_locale'], 'auto' ); ?>><?php echo esc_html__( 'Follow site language', 'cross-strait-phrase-switch' ); ?></option>
								<option value="cn" <?php selected( $settings['default_locale'], 'cn' ); ?>><?php echo esc_html__( 'Mainland phrases', 'cross-strait-phrase-switch' ); ?></option>
								<option value="tw" <?php selected( $settings['default_locale'], 'tw' ); ?>><?php echo esc_html__( 'Taiwan phrases', 'cross-strait-phrase-switch' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Browser language', 'cross-strait-phrase-switch' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="cross_strait_phrase_switch[detect_accept_language]" value="1" <?php checked( ! empty( $settings['detect_accept_language'] ) ); ?>>
								<?php echo esc_html__( 'Use Taiwan phrases when Accept-Language is zh-TW / zh-HK', 'cross-strait-phrase-switch' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Floating button', 'cross-strait-phrase-switch' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="cross_strait_phrase_switch[floating_button]" value="1" <?php checked( ! empty( $settings['floating_button'] ) ); ?>>
								<?php echo esc_html__( 'Show a floating toggle on the front end', 'cross-strait-phrase-switch' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Puock theme', 'cross-strait-phrase-switch' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="cross_strait_phrase_switch[integrate_puock]" value="1" <?php checked( ! empty( $settings['integrate_puock'] ) ); ?>>
								<?php echo esc_html__( 'Reuse Puock’s language icon when that theme is active', 'cross-strait-phrase-switch' ); ?>
							</label>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<h2><?php echo esc_html__( 'Extra dictionaries', 'cross-strait-phrase-switch' ); ?></h2>
			<p><?php echo esc_html__( 'JSON object maps, {toTaiwan,toMainland} pair lists, or 國教院-style NDJSON. Files stay in uploads and are not part of the plugin zip.', 'cross-strait-phrase-switch' ); ?></p>
			<ul>
				<?php if ( $files === array() ) : ?>
					<li><?php echo esc_html__( 'No extra dictionary files yet.', 'cross-strait-phrase-switch' ); ?></li>
				<?php else : ?>
					<?php foreach ( $files as $fileName ) : ?>
						<li><code><?php echo esc_html( $fileName ); ?></code></li>
					<?php endforeach; ?>
				<?php endif; ?>
			</ul>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<?php wp_nonce_field( 'csps_upload_dict' ); ?>
				<input type="hidden" name="action" value="csps_upload_dict">
				<input type="file" name="csps_dict_file" accept=".json,application/json" required>
				<?php submit_button( __( 'Upload and compile', 'cross-strait-phrase-switch' ), 'secondary', 'submit', false ); ?>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:1em;">
				<?php wp_nonce_field( 'csps_compile_dicts' ); ?>
				<input type="hidden" name="action" value="csps_compile_dicts">
				<?php submit_button( __( 'Recompile dictionaries', 'cross-strait-phrase-switch' ), 'secondary', 'submit', false ); ?>
			</form>
			<p>
				<?php echo esc_html__( 'Shortcode:', 'cross-strait-phrase-switch' ); ?>
				<code>[csps_toggle]</code>
			</p>
		</div>
		<?php
	}

	/**
	 * @param true|\WP_Error $result Compile/upload result.
	 * @return void
	 */
	private function redirectAfterDictAction( $result ) {
		$url = admin_url( 'options-general.php?page=cross-strait-phrase-switch' );
		if ( is_wp_error( $result ) ) {
			$url = add_query_arg(
				array(
					'csps_notice' => 'error',
					'csps_error'  => rawurlencode( $result->get_error_message() ),
				),
				$url
			);
		} else {
			$url = add_query_arg( 'csps_notice', 'compiled', $url );
		}
		wp_safe_redirect( $url );
		exit;
	}
}
