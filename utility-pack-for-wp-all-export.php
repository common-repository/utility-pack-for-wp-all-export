<?php
/*
Plugin Name: Utility Pack for WP All Export
Description: A set of useful tools to enhance WP All Export.
Version: 1.0.2
Author: Coding Chicken
Author URI: https://codingchicken.com
*/

/**
 * Plugin root dir with using forward slashes.
 * @var string
 */
define('UTILITY_PACK_WPAE_ROOT_DIR', str_replace('\\', '/', dirname(__FILE__)));
/**
 * Plugin root url for referencing static content.
 * @var string
 */
define('UTILITY_PACK_WPAE_ROOT_URL', rtrim(plugin_dir_url(__FILE__), '/'));
/**
 * Plugin prefix for making names unique (be aware that this variable is used in conjunction with naming convention,
 * i.e. in order to change it one must not only modify this constant but also rename all constants, classes and functions which
 * names composed using this prefix)
 * @var string
 */
const UTILITY_PACK_WPAE_PREFIX = 'utility_pack_wpae_';

const UTILITY_PACK_WPAE_VERSION = '1.0.2';

// Require Composer autoloader.
require UTILITY_PACK_WPAE_ROOT_DIR . '/vendor/autoload.php';

final class UTILITY_PACK_WPAE_Plugin {
	/**
	 * Singletone instance
	 * @var UTILITY_PACK_WPAE_Plugin
	 */
	protected static $instance;

	/**
	 * Plugin root dir
	 * @var string
	 */
	const ROOT_DIR = UTILITY_PACK_WPAE_ROOT_DIR;
	/**
	 * Plugin root URL
	 * @var string
	 */
	const ROOT_URL = UTILITY_PACK_WPAE_ROOT_URL;
	/**
	 * Prefix used for names of shortcodes, action handlers, filter functions etc.
	 * @var string
	 */
	const PREFIX = UTILITY_PACK_WPAE_PREFIX;
	/**
	 * Plugin file path
	 * @var string
	 */
	const FILE = __FILE__;

	/**
	 * Return singleton instance
	 * @return UTILITY_PACK_WPAE_Plugin
	 */
	static public function getInstance(): UTILITY_PACK_WPAE_Plugin {
		if (self::$instance == NULL) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Common logic for requesting plugin info fields
	 */
	public function __call($method, $args) {
		if (preg_match('%^get(.+)%i', $method, $mtch)) {
			$info = get_plugin_data(self::FILE);
			if (isset($info[$mtch[1]])) {
				return $info[$mtch[1]];
			}
		}
		throw new Exception("Requested method " . get_class($this) . "::$method doesn't exist.");
	}

	/**
	 * Get path to plugin dir relative to WordPress root
	 * @param bool[optional] $noForwardSlash Whether path should be returned withot forwarding slash
	 * @return string
	 */
	public function getRelativePath($noForwardSlash = false) {
		$wp_root = str_replace('\\', '/', ABSPATH);
		return ($noForwardSlash ? '' : '/') . str_replace($wp_root, '', self::ROOT_DIR);
	}

	/**
	 * Check whether plugin is activated as network one
	 * @return bool
	 */
	public function isNetwork() {
		if ( !is_multisite() )
			return false;

		$plugins = get_site_option('active_sitewide_plugins');
		if (isset($plugins[plugin_basename(self::FILE)]))
			return true;

		return false;
	}

	/**
	 * Class constructor containing dispatching logic
	 * @param string $rootDir Plugin root dir
	 * @param string $pluginFilePath Plugin main file
	 */
	protected function __construct() {

		// Define WP CLI command.
		if (class_exists('WP_CLI')) {
			if(!class_exists('Utility_Pack_WPAE_Cli')){
				require self::ROOT_DIR . '/classes/cli.php';
			}
			WP_CLI::add_command( 'utility-all-export', 'Utility_Pack_WPAE_Cli' );
		}

		require_once self::ROOT_DIR . '/helpers/utility_pack_for_wp_all_export_get_export_id.php';

		register_activation_hook( self::FILE, array( $this, 'activation' ) );

		// register admin page pre-dispatcher
		add_action( 'admin_init', array( $this, 'adminInit' ) );
		add_action( 'init', array( $this, 'init' ) );


		// Register action handlers.
		if ( is_dir( self::ROOT_DIR . '/actions' ) ) {
			foreach ( Utility_Pack_WPAE_Helper::safe_glob( self::ROOT_DIR . '/actions/*.php', Utility_Pack_WPAE_Helper::GLOB_RECURSE | Utility_Pack_WPAE_Helper::GLOB_PATH ) as $filePath ) {
				require_once $filePath;
				$function = $actionName = basename( $filePath, '.php' );
				if ( preg_match( '%^(.+?)[_-](\d+)$%', $actionName, $m ) ) {
					$actionName = $m[1];
					$priority   = intval( $m[2] );
				} else {
					$priority = 10;
				}
				add_action( $actionName, self::PREFIX . str_replace( '-', '_', $function ), $priority, 99 ); // since we don't know at this point how many parameters each plugin expects, we make sure they will be provided with all of them (it's unlikely any developer will specify more than 99 parameters in a function)
			}
		}
		// Register filter handlers.
		if ( is_dir( self::ROOT_DIR . '/filters' ) ) {
			foreach ( Utility_Pack_WPAE_Helper::safe_glob( self::ROOT_DIR . '/filters/*.php', Utility_Pack_WPAE_Helper::GLOB_RECURSE | Utility_Pack_WPAE_Helper::GLOB_PATH ) as $filePath ) {
				require_once $filePath;
				$function = $actionName = basename( $filePath, '.php' );
				if ( preg_match( '%^(.+?)[_-](\d+)$%', $actionName, $m ) ) {
					$actionName = $m[1];
					$priority   = intval( $m[2] );
				} else {
					$priority = 10;
				}
				add_filter( $actionName, self::PREFIX . str_replace( '-', '_', $function ), $priority, 99 ); // since we don't know at this point how many parameters each plugin expects, we make sure they will be provided with all of them (it's unlikely any developer will specify more than 99 parameters in a function)


			}
		}

	}

	public function init(){
		$this->load_plugin_textdomain();
	}

	/**
	 * Load Localisation files.
	 *
	 * Note: the first-loaded translation file overrides any following ones if the same translation is present
	 *
	 * @access public
	 * @return void
	 */
	public function load_plugin_textdomain() {
		$locale = apply_filters( 'plugin_locale', get_locale(), 'utility_pack_for_wpae' );
		load_plugin_textdomain( 'utility_pack_for_wpae', false, dirname( plugin_basename( __FILE__ ) ) . "/i18n/languages" );
	}

	public function adminInit() {

	}

	public function replace_callback($matches){
		return strtoupper($matches[0]);
	}

	/**
	 * Plugin activation logic
	 */
	public function activation() {
		// Uncaught exception doesn't prevent plugin from being activated, therefore replace it with fatal error so it does.
		set_exception_handler(function($e){trigger_error($e->getMessage(), E_USER_ERROR);});
	}

}

UTILITY_PACK_WPAE_Plugin::getInstance();
