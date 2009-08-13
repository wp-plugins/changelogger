<?php
/**
 * The main plugin file
 *
 * @package WordPress_Plugins
 * @subpackage Changelogger
 */
 
/*
Plugin Name: Changelogger
Version: 1.1.2
Plugin URI: http://www.schloebe.de/wordpress/changelogger-plugin/
Description: <strong>WordPress 2.7+ only.</strong> For many many people a changelog is a very important thing; it is all about justifying to your users why they should upgrade to the latest version of a plugin. Changelogger shows the latest changelog right on the plugin listing page, whenever there's a plugin ready to be updated.
Author: Oliver Schl&ouml;be
Author URI: http://www.schloebe.de/


Copyright 2009 Oliver Schlöbe (email : scripts@schloebe.de)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


/**
 * Define the plugin version
 */
define("CLOS_VERSION", "1.1.2");

/**
 * Define the global var CLOSISWP27, returning bool if at least WP 2.7 is running
 */
define('CLOSISWP27', version_compare($GLOBALS['wp_version'], '2.6.999', '>'));


/** 
* The Changelogger class
*
* @package WordPress_Plugins
* @subpackage Changelogger
* @since 1.0
* @author scripts@schloebe.de
*/
class Changelogger {

	/**
 	* The Changelogger class constructor
 	* initializing required stuff for the plugin
 	* 
	* PHP 4 Compatible Constructor
 	*
 	* @since 1.0
 	* @author scripts@schloebe.de
 	*/
	function Changelogger() {
		$this->__construct();
	}
	
	
	/**
 	* The Changelogger class constructor
 	* initializing required stuff for the plugin
 	* 
	* PHP 5 Constructor
 	*
 	* @since 1.0
 	* @author scripts@schloebe.de
 	*/		
	function __construct(){
		$this->textdomain_loaded = false;
		
		if ( !CLOSISWP27 ) {
			add_action('admin_notices', array(&$this, 'wpVersion27Failed'));
			return;
		}
		
		if ( is_admin() ) {
			add_action('admin_init', array(&$this, 'load_textdomain'));
			add_action('admin_init', array(&$this, 'init'));
			add_action('after_plugin_row', array(&$this, 'display_info_row'), 50, 2);
		}
	}
	
	
	/**
 	* Initialize and load the plugin stuff
 	*
 	* @since 1.0
 	* @uses $pagenow
 	* @author scripts@schloebe.de
 	*/
	function init() {
		global $pagenow;
		if ( !function_exists("add_action") ) return;
		
		if( $pagenow == 'plugins.php' ) {
			add_action('admin_head', array(&$this, 'css_admin_header'));
		}
	}
	
	
	/**
 	* Add a plugin row to display changelog via WP plugin API
 	*
 	* @since 1.0
 	* @param string $file
 	* @param array $plugin_data
 	* @author scripts@schloebe.de
 	*/
	function display_info_row( $file, $plugin_data ) {	
		$current = version_compare( $GLOBALS['wp_version'], '2.7.999', '>' ) ? get_transient( 'update_plugins' ) : get_option( 'update_plugins' );
		if (!isset($current->response[$file])) return false;
		$output = '';
		
		$r = $current->response[ $file ];
		include_once( ABSPATH . 'wp-admin/includes/plugin-install.php' );
		$columns = version_compare( $GLOBALS['wp_version'], '2.7.999', '>' ) ? 3 : 5;
		
		$cache_key = 'changelogger_plugin_changelog_' . $r->slug;
		$output = wp_cache_get($cache_key, 'changelogger');
		
		if (false === $output) {
			$api = plugins_api('plugin_information', array('slug' => $r->slug, 'fields' => array('tested' => false, 'requires' => false, 'rating' => false, 'downloaded' => false, 'downloadlink' => false, 'last_updated' => false, 'homepage' => false, 'tags' => false, 'sections' => true) ));
			if ( !is_wp_error( $api ) && current_user_can('update_plugins') ) {
				$is_active = is_plugin_active( $file );
				$class = $is_active ? 'active' : 'inactive';
				$class_tr = version_compare( $GLOBALS['wp_version'], '2.7.999', '>' ) ? ' class="plugin-update-tr second ' . $class . '"' : '';
				if( isset($api->sections['changelog']) ) {
					$changelog = $api->sections['changelog'];
					$section_exists = preg_match_all('/(<h4>|<p><strong>)(.*)(<\/h4>|<\/strong><\/p>)[\n|\r]{0,}<ul>(.*)<\/ul>[\w|\W]{0,}/isU', $changelog, $changelog_result);
					if( $section_exists ) {
						$output .= '<tr' . $class_tr . '><td class="plugin-update CLOS-plugin-update" colspan="' . $columns . '"><div class="update-message CLOS-message">';
						$output .= sprintf(__('What has changed in version %1$s', 'changelogger'), trim( $changelog_result[0][0] ));
						$output .= ' ' . sprintf(__('If you are interested, check out the plugin\'s <a href="http://plugins.trac.wordpress.org/log/%s/trunk" target="_blank">Revision Log</a>!', 'changelogger'), $r->slug) . '</div></td></tr>';
					} else {
						$output .= '<tr' . $class_tr . '><td class="plugin-update CLOS-plugin-update" colspan="' . $columns . '"><div class="update-message CLOS-message">';
						$output .= '<span style="color:#A36300;">' . sprintf(__('There is a changelog section for this plugin, but it is not readable, propably because it <strong>does not match the <a href="http://wordpress.org/extend/plugins/about/readme.txt" target="_blank">readme.txt standards</a></strong>!', 'changelogger')) . ' ' . sprintf(__('If you are interested, check out the plugin\'s <a href="http://plugins.trac.wordpress.org/log/%s/trunk" target="_blank">Revision Log</a>!', 'changelogger'), $r->slug) . '</span>';
						$output .= '</div></td></tr>';
					}
				} else {
					#print_r($api);
					$output .= '<tr' . $class_tr . '><td class="plugin-update CLOS-plugin-update" colspan="' . $columns . '"><div class="update-message CLOS-message">';
					$output .= '<span style="color:#A36300;">' . sprintf(__('There is <strong>no changelog section provided for this plugin</strong>. Please encourage the plugin author to add a changelog section to the plugin\'s readme! Contact %s! [<a href="http://westi.wordpress.com/2009/06/20/changelogs-changelogs-changelogs/" target="_blank">More</a>]', 'changelogger'), $api->author) . ' ' . sprintf(__('If you are interested, check out the plugin\'s <a href="http://plugins.trac.wordpress.org/log/%s/trunk" target="_blank">Revision Log</a>!', 'changelogger'), $r->slug) . '</span>';
					$output .= '</div></td></tr>';
				}
			} else {
				$output .= '<tr class="plugin-update-tr"><td colspan="' . $columns . '"><div class="update-message CLOS-message">';
				$output .= sprintf(__('<strong>ERROR</strong>: %s', 'changelogger'), $api->get_error_message());
				$output .= '</div></td></tr>';
			}
			wp_cache_set($cache_key, $output, 'changelogger', 86400);
		}
		echo $output;
	}


	/**
	 * Writes the css stuff into plugin page header needed for the plugin to look good
	 *
	 * @since 1.0
	 * @author scripts@schloebe.de
	 */
	function css_admin_header() {
		echo '
<style type="text/css">
td.CLOS-plugin-update {
	text-align: left;
	border-top-width: 0px;
}

.plugin-update-tr div.CLOS-message {
	font-weight: normal;
}

div.CLOS-message h4 {
	margin: 0;
	display: inline;
}

div.CLOS-message ul {
	list-style-type: square;
	margin-left: 20px;
}
</style>' . "\n";
	}
	
	
	/**
 	* Initialize and load the plugin textdomain
 	*
 	* @since 1.0
 	* @author scripts@schloebe.de
 	*/
	function load_textdomain() {
		if($this->textdomain_loaded) return;
		load_plugin_textdomain('changelogger', false, dirname(plugin_basename(__FILE__)) . '/languages');
		$this->textdomain_loaded = true;
	}
	
	
	/**
 	* Checks for the version of WordPress,
 	* and adds a message to inform the user
 	* if required WP version is less than 2.7
 	*
 	* @since 1.0
 	* @author scripts@schloebe.de
 	*/
	function wpVersion27Failed() {
		echo "<div id='wpversionfailedmessage' class='error fade'><p>" . __('Changelogger requires at least WordPress 2.7!', 'changelogger') . "</p></div>";
	}
	
}

if ( class_exists('Changelogger') ) {
	$Changelogger = new Changelogger();
}
?>