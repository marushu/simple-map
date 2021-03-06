<?php
/*
Plugin Name: Simple Map
Author: Takayuki Miyauchi
Plugin URI: https://github.com/miya0001/simple-map
Description: Insert google map convert from address.
Version: 2.13.0
Author URI: http://wpist.me/
Text Domain: simple-map
Domain Path: /languages
*/

$simplemap = new Simple_Map();

class Simple_Map {

	private $shortcode_tag  = 'map';
	private $class_name     = 'simplemap';
	private $width          = '100%';
	private $height         = '200px';
	private $zoom           = 16;
	private $breakpoint     = 480;
	private $max_breakpoint = 640;

	function __construct()
	{
		add_action( 'init', array( $this, 'init' ) );
	}

	public function init()
	{
		add_action( 'wp_head', array( $this, 'wp_head' ) );
		add_shortcode( $this->get_shortcode_tag(), array( $this, 'shortcode' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'settings_init' ) );
		add_action( 'admin_init', array( $this, 'load_textdomain' ) );
		$option = get_option( 'simple_map_settings' );
		$apikey = trim( $option['api_key_field'] );
		if( ! isset( $apikey ) || empty( $apikey ) ) {
			add_action( 'admin_notices', array( $this, 'admin_notice__error' ) );
		}

		wp_embed_register_handler(
			'google-map',
			'#( https://( www|maps ).google.[a-z]{2,3}\.?[a-z]{0,3}/maps( /ms )?\?.+ )#i',
			array( &$this, 'oembed_handler' )
		);
	}

	public function oembed_handler( $match )
	{
		return sprintf(
			'[%s url="%s"]',
			$this->get_shortcode_tag(),
			esc_url( $match[0] )
		);
	}

	public function wp_head()
	{
		echo "<style>.simplemap img{max-width:none !important;padding:0 !important;margin:0 !important;}.staticmap,.staticmap img{max-width:100% !important;height:auto !important;}.simplemap .simplemap-content{display:none;}</style>\n";
	}

	public function wp_enqueue_scripts()
	{

		$options = get_option( 'simple_map_settings' );
		$apikey  = ! empty( $options['api_key_field'] )
			? esc_attr( $options['api_key_field'] )
			: '';

		wp_register_script(
			'google-maps-api',
			'//maps.google.com/maps/api/js' . '?key=' . $apikey,
			false,
			null,
			true
		);

		wp_register_script(
			'simplemap',
			apply_filters(
				'simplemap_script',
				plugins_url( 'js/simple-map.min.js' , __FILE__ )
			),
			array( 'jquery', 'google-maps-api' ),
			filemtime( dirname( __FILE__ ).'/js/simple-map.min.js' ),
			true
		);

		wp_localize_script( 'simplemap', 'simplemap_att', array(
			'simplemap_nonce' => wp_create_nonce( 'simplemap-load-nonce' ),
			'ajaxurl'         => admin_url( 'admin-ajax.php', is_ssl() ? 'https' : 'http' ),
			'api_key'         => $apikey,
		));

		wp_enqueue_script( 'simplemap' );
	}

	public function shortcode( $p, $content = null )
	{
		add_action( 'wp_footer', array( &$this, 'wp_enqueue_scripts' ) );

		if ( isset( $p['width'] ) && preg_match( '/^[0-9]+(%|px)$/', $p['width'] ) ) {
			$w = $p['width'];
		} else {
			$w = apply_filters( 'simplemap_default_width', $this->width );
		}
		if ( isset( $p['height'] ) && preg_match( '/^[0-9]+(%|px)$/', $p['height'] ) ) {
			$h = $p['height'];
		} else {
			$h = apply_filters( 'simplemap_default_height', $this->height );
		}
		if ( isset( $p['zoom'] ) && intval( $p['zoom'] ) ) {
			$zoom = $p['zoom'];
		} else {
			$zoom = apply_filters( 'simplemap_default_zoom', $this->zoom );
		}
		if ( isset( $p['breakpoint'] ) && intval( $p['breakpoint'] ) ) {
			if ( intval( $p['breakpoint'] ) > $this->max_breakpoint ) {
				$breakpoint = $this->max_breakpoint;
			} else {
				$breakpoint = intval( $p['breakpoint'] );
			}
		} else {
			$breakpoint = apply_filters(
				'simplemap_default_breakpoint',
				$this->breakpoint
			);
		}
		if ( ! empty( $p['map_type_control'] ) ) {
			$map_type_control = 'true';
		} else {
			$map_type_control = 'false';
		}
		if ( ! empty( $p['map_type_id'] ) ) {
			$map_type_id = $p['map_type_id'];
		} else {
			$map_type_id = 'ROADMAP';
		}
		if ( $content ) {
			$content = do_shortcode( $content );
		}
		if ( isset( $p['infowindow'] ) && $p['infowindow'] ) {
			$infowindow = $p['infowindow'];
		} else {
			$infowindow = apply_filters( 'simplemap_default_infowindow', 'close' );
		}

		$addr = '';
		$lat  = '';
		$lng  = '';

		if ( isset( $p['url'] ) && $p['url'] ) {
			$iframe = '<iframe width="%s" height="%s" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src="%s"></iframe>';

			return sprintf(
				$iframe,
				$w,
				$h,
				esc_url( $p['url'].'&output=embed' )
			);
		} elseif ( isset( $p['lat'] ) && preg_match( '/^\-?[0-9\.]+$/', $p['lat'] )
					&& isset( $p['lng'] ) && preg_match( '/^\-?[0-9\.]+$/', $p['lng'] ) ){
			$lat = $p['lat'];
			$lng = $p['lng'];
		} elseif ( isset( $p['addr'] ) && $p['addr'] ) {
			if ( $content ) {
				$addr = $p['addr'];
			} else {
				$content = $p['addr'];
			}
		} elseif ( ! $content ) {
			return;
		}
		return sprintf(
			'<div class="%1$s"><div class="%1$s-content" data-breakpoint="%2$s" data-lat="%3$s" data-lng="%4$s" data-zoom="%5$s" data-addr="%6$s" data-infowindow="%7$s" data-map-type-control="%8$s" data-map-type-id="%9$s" style="width:%10$s;height:%11$s;">%12$s</div></div>',
			esc_attr( apply_filters( 'simplemap_class_name', $this->class_name ) ),
			esc_attr( $breakpoint ),
			esc_attr( $lat ),
			esc_attr( $lng ),
			esc_attr( $zoom ),
			esc_attr( $addr ),
			esc_attr( $infowindow ),
			esc_attr( $map_type_control ),
			esc_attr( $map_type_id ),
			esc_attr( $w ),
			esc_attr( $h ),
			esc_html( trim( $content ) )
		);
	}

	private function get_shortcode_tag()
	{
		return apply_filters( 'simplemap_shortcode_tag', $this->shortcode_tag );
	}


	/**
	 * Load textdomain
	 */
	public function load_textdomain()

	{
		load_plugin_textdomain(
			'simple-map',
			false,
			plugin_basename( dirname( __FILE__ ) ) . '/languages'
		);

	}

	/*
	 * Sanitize api_key_field.
	 */
	public function data_sanitize( $input )
	{

		$new_input = array();

		/**
		 * API Key.
		 */
		$api_key = isset( $input['api_key_field'] ) ? $input['api_key_field'] : '';

		if( ! empty( $api_key ) ) {

			if( strlen( $api_key ) === mb_strlen( $api_key ) ) {

				$new_input[ 'api_key_field' ] = esc_attr( $api_key );

			} else {

				add_settings_error(
					'simple_map_settings',
					'api_key_field',
					esc_html__( 'Check your API key.', 'simple-map' ),
					'error'
				);
				$new_input[ 'api_key_field' ] = '';

			}

		} else {

			add_settings_error(
				'simple_map_settings',
				'api_key_field',
				esc_html__( 'Check your API key.', 'simple-map' ),
				'error'
			);

			$new_input['api_key_field'] = '';

		}

		return $new_input;

	}


	public function admin_notice__error()
	{

		$class = 'notice notice-error';
		$link  = sprintf(
			'<a href="%1$s">%2$s</a>',
			admin_url( 'options-general.php?page=simple_map' ),
			__( 'Settings page', 'simple-map' )
		);
		$message = sprintf(
			__( 'Simple Map, you need an API key. Please move to the %1$s.', 'simple-map' ),
			$link
		);
		printf( '<div class="%1$s"><p>%2$s</p></div>', 'notice notice-warning is-dismissible', $message );

	}

	/**
	 * Add admin menu
	 */
	public function admin_menu()
	{

		add_options_page(
			'Simple Map',
			'Simple Map',
			'manage_options',
			'simple_map',
			array( $this, 'simple_map_options_page' )
		);

	}

	/**
	 * Register settings.
	 */
	public function settings_init()
	{

		register_setting(
			'simplemappage',
			'simple_map_settings',
			array( $this, 'data_sanitize' )
		);

		add_settings_section(
			'simple_map_settings_section',
			esc_html__( 'Simple Map settings', 'simple-map' ),
			array( $this, 'simple_map_settings_section_callback' ),
			'simplemappage'
		);

		add_settings_field(
			'api_key_field',
			__( '<p>Set API Key</p>', 'simple_map' ),
			array( $this, 'api_key_field_render' ),
			'simplemappage',
			'simple_map_settings_section'
		);

	}

	/**
	 * Add description of Post Notifier.
	 */
	public function simple_map_settings_section_callback()
	{

		echo esc_html__( 'Set your Google Maps API key.', 'simple-map' );

	}

	/**
	 * Output text field.
	 */
	public function api_key_field_render()
	{

		$options = get_option( 'simple_map_settings' );
		$apikey  = isset( $options['api_key_field'] ) ? $options['api_key_field'] : '';

		?>

		<input type="text" name="simple_map_settings[api_key_field]" value="<?php echo esc_html( $apikey ); ?>" size="30">

		<?php

	}

	/**
	 * Output Post Notifier page form.
	 */
	public function simple_map_options_page()
	{

		?>
		<form action='options.php' method='post'>

		<?php
			settings_fields( 'simplemappage' );
			do_settings_sections( 'simplemappage' );

			submit_button();

			/*
			 * API key obtaining method.
			 */
			$maps_api_for_web_link = sprintf(
				'%1$s<a href="https://developers.google.com/maps/web/">%2$s</a>',
				esc_html__( 'Go to ', 'simple-map' ),
				esc_html__( 'Google Maps APIs for Web page.' )
			);

			$get_key_text    = esc_html__( 'Click "GET A KEY" button', 'simple-map' );
			$continue_text   = esc_html__( 'Click "CONTINUE" button', 'simple-map' );
			$set_domain_text = esc_html__( 'Add your domain.', 'simple-map' );
			$copy_api_key    = esc_html__( 'API key will pop up. Copy and paste the key.', 'simple-map' );

			$html  = '';
			$html .= '<h2>' . esc_html__( 'How to get API key?', 'simplemap' ) . '</h2>';
			$html .= '<ol>';
			$html .= '<li>' . $maps_api_for_web_link . '</li>';
			$html .= '<li>' . $get_key_text . '<p><img style="width: 80%;" src="' . plugin_dir_url( __FILE__ ) . 'images/001_click_get_a_key_button.png"></p></li>';
			$html .= '<li>' . $continue_text . '<p><img style="width: 80%;" src="' . plugin_dir_url( __FILE__ ) . 'images/002_click_continue_button.png"></p></li>';
			$html .= '<li>' . $continue_text . '<p><img style="width: 80%;" src="' . plugin_dir_url( __FILE__ ) . 'images/004_make_project_click_continue_button.png"></p></li>';
			$html .= '<li>' . $set_domain_text . '<p><img style="width: 80%;" src="' . plugin_dir_url( __FILE__ ) . 'images/005_add_your_domain_and_click_creating.png"></p></li>';
			$html .= '<li>' . $copy_api_key . '<p><img style="width: 80%;" src="' . plugin_dir_url( __FILE__ ) . 'images/006_copy_your_api_key.png"></p></li>';
			$html .= '</ol>';


			echo $html;

		?>

		</form>
		<?php

	}

} // end class


// EOF