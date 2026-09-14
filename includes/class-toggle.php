<?php
/**
 * Toggle markup: shortcode, widget, floating button.
 *
 * @package CrossStraitPhraseSwitch
 */

namespace CrossStraitPhraseSwitch;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the language switch control.
 */
class Toggle {
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
		add_shortcode( 'csps_toggle', array( $this, 'renderShortcode' ) );
		add_action( 'widgets_init', array( $this, 'registerWidget' ) );
		add_action( 'wp_footer', array( $this, 'renderFloatingButton' ) );
	}

	/**
	 * @return void
	 */
	public function registerWidget() {
		register_widget( ToggleWidget::class );
	}

	/**
	 * @param array<string, string> $atts Shortcode attributes.
	 * @return string
	 */
	public function renderShortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'label' => __( '繁中/簡中 切換', 'cross-strait-phrase-switch' ),
			),
			is_array( $atts ) ? $atts : array(),
			'csps_toggle'
		);
		return self::buttonHtml( $atts['label'], 'csps-toggle-inline' );
	}

	/**
	 * @return void
	 */
	public function renderFloatingButton() {
		if ( is_admin() || ! $this->plugin->getSetting( 'floating_button', false ) ) {
			return;
		}
		echo self::buttonHtml( __( '繁中/簡中 切換', 'cross-strait-phrase-switch' ), 'csps-toggle-float' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * @param string $label Visible label.
	 * @param string $extraClass Extra CSS class.
	 * @return string
	 */
	public static function buttonHtml( $label, $extraClass = '' ) {
		$classes = trim( 'csps-toggle ' . $extraClass );
		if ( Plugin::instance()->usesPuockIntegration() ) {
			$classes .= ' sc-tc-toggle';
		}
		return sprintf(
			'<button type="button" class="%1$s" title="%2$s">%3$s</button>',
			esc_attr( $classes ),
			esc_attr( $label ),
			esc_html( $label )
		);
	}
}

/**
 * Sidebar widget wrapper.
 */
class ToggleWidget extends \WP_Widget {
	public function __construct() {
		parent::__construct(
			'csps_toggle',
			__( 'Cross-Strait Phrase Switch', 'cross-strait-phrase-switch' ),
			array(
				'description' => __( 'Toggle Taiwan / Mainland Chinese vocabulary.', 'cross-strait-phrase-switch' ),
			)
		);
	}

	/**
	 * @param array<string, mixed> $args Widget args.
	 * @param array<string, mixed> $instance Instance.
	 * @return void
	 */
	public function widget( $args, $instance ) {
		$title = isset( $instance['title'] ) ? $instance['title'] : '';
		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( is_string( $title ) && $title !== '' ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo Toggle::buttonHtml( __( '繁中/簡中 切換', 'cross-strait-phrase-switch' ), 'csps-toggle-widget' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * @param array<string, mixed> $instance Instance.
	 * @return void
	 */
	public function form( $instance ) {
		$title = isset( $instance['title'] ) ? (string) $instance['title'] : '';
		printf(
			'<p><label for="%1$s">%2$s</label><input class="widefat" id="%1$s" name="%3$s" type="text" value="%4$s"></p>',
			esc_attr( $this->get_field_id( 'title' ) ),
			esc_html__( 'Title', 'cross-strait-phrase-switch' ),
			esc_attr( $this->get_field_name( 'title' ) ),
			esc_attr( $title )
		);
	}

	/**
	 * @param array<string, mixed> $newInstance New values.
	 * @param array<string, mixed> $oldInstance Old values.
	 * @return array<string, mixed>
	 */
	public function update( $newInstance, $oldInstance ) {
		$instance          = is_array( $oldInstance ) ? $oldInstance : array();
		$instance['title'] = isset( $newInstance['title'] ) ? sanitize_text_field( $newInstance['title'] ) : '';
		return $instance;
	}
}
