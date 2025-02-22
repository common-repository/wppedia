<?php

/**
 * WPPedia - The most advanced Glossary solution for WordPress!
 *
 * @wordpress-plugin
 *
 * Plugin Name:	WPPedia
 * Description:	The most advanced Glossary solution for WordPress!
 * Author:		Bastian Fießinger & WPPedia Glossary Team
 * AuthorURI:	https://github.com/bfiessinger/
 * Version: 	1.3.0
 * Text Domain:	wppedia
 */

// Make sure this file runs only from within WordPress.
defined( 'ABSPATH' ) or die();

/**
 * Core WPPedia functions
 */
require_once plugin_dir_path(__FILE__) . 'core/inc/core-functions.php';

// Core Classes
use WPPedia\Template;
use WPPedia\Rest_Controller;
use WPPedia\WP_Query_Setup;
use WPPedia\Admin;
use WPPedia\Notification_Center;
use WPPedia\Options;
use WPPedia\Post_Meta;
use WPPedia\Customizer;
use WPPedia\Post_Type;
use WPPedia\DB_Upgrade;

// Modules
use WPPedia\Modules\Cross_Link_Content;
use WPPedia\Modules\Tooltip;

// Compatibility
use WPPedia\Compatibilities\Compatibility_Collection;

class WPPedia {

	/**
	 * Static variable for instanciation
	 *
	 * @var WPPedia
	 */
	protected static $instance = null;

	/**
	 * Container that holds various class instances
	 *
	 * @var array
	 */
	private $container = [];

	/**
	 * Magic isset to bypass referencing plugin.
	 *
	 * @param  string $prop Property to check.
	 *
	 * @return bool
	 *
	 * @since 1.3.0
	 */
	public function __isset( $prop ) {
		return isset( $this->{$prop} ) || isset( $this->container[ $prop ] );
	}

	/**
	 * Magic getter method.
	 *
	 * @param  string $prop Property to get.
	 *
	 * @return mixed Property value or NULL if it does not exists.
	 *
	 * @since 1.3.0
	 */
	public function __get( $prop ) {
		if ( array_key_exists( $prop, $this->container ) ) {
			return $this->container[ $prop ];
		}

		if ( isset( $this->{$prop} ) ) {
			return $this->{$prop};
		}

		return null;
	}

	/**
	 * Magic setter method.
	 *
	 * @param mixed $prop  Property to set.
	 * @param mixed $value Value to set.
	 *
	 * @since 1.3.0
	 */
	public function __set( $prop, $value ) {
		if ( property_exists( $this, $prop ) ) {
			$this->$prop = $value;
			return;
		}

		$this->container[ $prop ] = $value;
	}

	/**
	 * Magic call method.
	 *
	 * @param  string $name      Method to call.
	 * @param  array  $arguments Arguments to pass when calling.
	 *
	 * @return mixed Return value of the callback.
	 *
	 * @since 1.3.0
	 */
	public function __call( $name, $arguments ) {
		return call_user_func_array( $name, $arguments );
	}

	/**
	 * Get current Instance
	 */
	public static function getInstance() {
		if ( null === self::$instance ) {
			self::$instance = new self;
			self::$instance->init();
		}
		return self::$instance;
	}

	protected function __clone() {}

	protected function __construct() {}

	/**
	 * Define Plugin Constants
	 *
	 * @since 1.2.0
	 */
	private function define_constants() {

		if (!function_exists('get_plugins')) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$pluginData = array_values(array_filter(get_plugins(), function ($plugins) {
			return ('WPPedia' === $plugins['Name']);
		}))[0];

		wppedia_maybe_define_constant('WPPediaPluginVersion', $pluginData['Version']);

		// Path Constants
		wppedia_maybe_define_constant('WPPediaPluginDir', plugin_dir_path(__FILE__));
		wppedia_maybe_define_constant('WPPediaPluginUrl', plugin_dir_url(__FILE__));
		wppedia_maybe_define_constant('WPPediaPluginBaseName', plugin_basename( __FILE__ ));

		// Env Constants
		wppedia_maybe_define_constant('WPPedia_TEMPLATE_DEBUG_MODE', false);

	}

	public function setup() {
		load_plugin_textdomain( 'wppedia', false, dirname( WPPediaPluginBaseName ) . '/languages' );
	}

	private function init() {

		// psr4 Autoloader
		$loader = require "vendor/autoload.php";
		$loader->addPsr4('WPPedia\\', __DIR__);

		$this->define_constants();

		add_action( 'after_setup_theme', [ $this, 'setup' ] );

		$this->container['version'] = WPPediaPluginVersion;
		$this->container['template_debug_mode'] = WPPedia_TEMPLATE_DEBUG_MODE;
		$this->container['plugin_dir'] = WPPediaPluginDir;
		$this->container['plugin_url'] = WPPediaPluginUrl;
		$this->container['notifications'] = new Notification_Center( 'wppedia_notifications' );

		/**
		 * Instantiate Template Utils
		 */
		$template = new Template();
		$template->_init();

		$this->container['template'] = $template;

		/**
		 * Theme and Plugin compatibility
		 */
		(new Compatibility_Collection())->_init();

		/**
		 * Instantiate REST API Controller Class
		 */
		(new Rest_Controller())->_init();

		/**
		 * Instantiate Query Controller
		 */
		(new WP_Query_Setup())->_init();

		/**
		 * Instatiate Admin View
		 * Used to edit post or edit views in wp_admin
		 */
		(new Admin())->_init();

		/**
		 * Options
		 * Setup options and settings pages
		 */
		$options = new Options();
		$options->_init();

		$this->container['options'] = $options;

		/**
		 * Post meta
		 * Setup custom postmeta for WPPedia articles
		 */
		(new Post_Meta())->_init();

		/**
		 * Setup Customizer Controls
		 */
		(new Customizer())->_init();

		/**
		 * Instantiate Post Type
		 * Generates the WPPedia Post type and related taxonomies
		 */
		(new Post_Type())->_init();

		/**
		 * Instantiate Crosslink Module
		 */
		(new Cross_Link_Content(
			!!options::get_option('crosslinks', 'active'),
			!!options::get_option('crosslinks', 'prefer_single_words')
		))->_init();

		/**
		 * Tooltips
		 */
		(new Tooltip())->_init();

		/**
		 * Upgrade the WPPedia Database if needed
		 */
		(new DB_Upgrade())->_init();
	}

	/**
	 * Get default path for templates in themes.
	 * By default the template path is yourtheme/wppedia
	 *
	 * If you want to override the default behaviour in your theme use
	 * the filter "wppedia_template_path" and return your preferred folder name
	 * in the callback.
	 *
	 * @since 1.1.3
	 */
	public function template_path() {
		return trailingslashit(apply_filters( 'wppedia_template_path', 'wppedia' ));
	}

	/**
	 * Get default plugin path
	 *
	 * @since 1.2.0
	 */
	public function plugin_path() {
		return (defined('WPPediaPluginDir')) ? WPPediaPluginDir : plugin_dir_path(__FILE__);
	}

}

WPPedia::getInstance();

/**
 * Template Hooks
 */
require_once WPPediaPluginDir . 'template-hooks/hooks.php';

/**
 * Enqueue Assets
 */
require_once WPPediaPluginDir . 'core/inc/assets.php';

/**
 * Shortcodes
 */
require_once WPPediaPluginDir . 'core/inc/shortcodes.php';

/**
 * The code that runs during plugin activation.
 */
require_once WPPediaPluginDir . 'core/inc/class-activation.php';
register_activation_hook( __FILE__, [ 'WPPedia\\activation', 'activate' ] );

/**
 * Flush rewrite rules if the previously added flag exists,
 * and then remove the flag.
 */
add_action('init', function() {

	if ( get_option( 'wppedia_flush_rewrite_rules_flag' ) ) {
		flush_rewrite_rules();
		delete_option( 'wppedia_flush_rewrite_rules_flag' );
	}

}, 20);

/**
 * The code that runs during plugin deactivation.
 */
require_once WPPediaPluginDir . 'core/inc/class-deactivation.php';
register_deactivation_hook( __FILE__, [ 'WPPedia\\deactivation', 'deactivate' ] );
