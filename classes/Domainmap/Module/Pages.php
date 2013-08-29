<?php

// +----------------------------------------------------------------------+
// | Copyright Incsub (http://incsub.com/)                                |
// | Based on an original by Donncha (http://ocaoimh.ie/)                 |
// +----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify |
// | it under the terms of the GNU General Public License, version 2, as  |
// | published by the Free Software Foundation.                           |
// |                                                                      |
// | This program is distributed in the hope that it will be useful,      |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of       |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the        |
// | GNU General Public License for more details.                         |
// |                                                                      |
// | You should have received a copy of the GNU General Public License    |
// | along with this program; if not, write to the Free Software          |
// | Foundation, Inc., 51 Franklin St, Fifth Floor, Boston,               |
// | MA 02110-1301 USA                                                    |
// +----------------------------------------------------------------------+

/**
 * The module responsible for admin pages.
 *
 * @category Domainmap
 * @package Module
 *
 * @since 4.0.0
 */
class Domainmap_Module_Pages extends Domainmap_Module {

	const NAME = __CLASS__;

	/**
	 * Admin page handle.
	 *
	 * @since 4.0.0
	 *
	 * @access private
	 * @var string
	 */
	private $_admin_page;

	/**
	 * Constructor.
	 *
	 * @since 4.0.0
	 *
	 * @access public
	 * @param Domainmap_Plugin $plugin The instance of Domainmap_Plugin class.
	 */
	public function __construct( Domainmap_Plugin $plugin ) {
		parent::__construct( $plugin );

		$this->_add_action( 'admin_menu', 'add_site_options_page' );
		$this->_add_action( 'network_admin_menu', 'add_network_options_page' );
		$this->_add_action( 'admin_enqueue_scripts', 'enqueue_scripts' );
	}

	/**
	 * Registers site options page in admin menu.
	 *
	 * @since 4.0.0
	 * @action admin_menu
	 *
	 * @access public
	 */
	public function add_site_options_page() {
		if ( $this->_plugin->is_site_permitted() ) {
			$title = __( 'Domain Mapping', 'domainmap' );
			$this->_admin_page = add_management_page( $title, $title, 'manage_options', 'domainmapping', array( $this, 'render_site_options_page' ) );
		}
	}

	/**
	 * Renders network options page.
	 *
	 * @since 4.0.0
	 * @callback add_management_page()
	 *
	 * @access public
	 * @global domain_map $dm_map The instance of domaim_map class.
	 */
	public function render_site_options_page() {
		global $dm_map;

		$page = new Domainmap_Render_Page_Site( $this->_plugin->get_options() );

		$page->origin = $this->_wpdb->get_row( sprintf(
			"SELECT * FROM %s WHERE blog_id = %d",
			$this->_wpdb->blogs,
			$this->_wpdb->blogid
		) );

		$page->domains = (array)$this->_wpdb->get_col( sprintf(
			"SELECT domain FROM %s WHERE blog_id = %d ORDER BY id ASC",
			$dm_map->dmtable,
			$this->_wpdb->blogid
		) );

		$page->render();
	}

	/**
	 * Registers network options page in admin menu.
	 *
	 * @since 4.0.0
	 * @action network_admin_menu
	 *
	 * @access public
	 */
	public function add_network_options_page() {
		$title = __( 'Domain Mapping', 'domainmap' );
		$this->_admin_page = add_submenu_page( 'settings.php', $title, $title, 'manage_network_options', 'domainmapping_options', array( $this, 'render_network_options_page' ) );
	}

	/**
	 * Renders network options page.
	 *
	 * @since 4.0.0
	 * @callback add_submenu_page()
	 *
	 * @access public
	 */
	public function render_network_options_page() {
		// if request method is post, then save options
		if ( $_SERVER['REQUEST_METHOD'] == 'POST' ) {
			check_admin_referer( 'update-dmoptions' );

			// Update the domain mapping settings
			$options = $this->_plugin->get_options();

			$ips = array();
			foreach ( explode( ',', filter_input( INPUT_POST, 'map_ipaddress' ) ) as $ip ) {
				$ip = filter_var( trim( $ip ), FILTER_VALIDATE_IP );
				if ( $ip ) {
					$ips[] = $ip;
				}
			}

			$supporters = array();
			if ( isset( $_POST['map_supporteronly'] ) ) {
				$supporters = array_filter( array_map( 'intval', (array)$_POST['map_supporteronly'] ) );
			}

			$options['map_ipaddress'] = implode( ', ', array_unique( $ips ) );
			$options['map_supporteronly'] = $supporters;
			$options['map_admindomain'] = $_POST['map_admindomain'];
			$options['map_logindomain'] = $_POST['map_logindomain'];
			$options['map_reseller'] = $_POST['map_reseller'];

			update_site_option( 'domain_mapping', $options );

			// if noheader argument is passed, then redirect back to options page
			if ( filter_input( INPUT_GET, 'noheader', FILTER_VALIDATE_BOOLEAN ) ) {
				wp_safe_redirect( add_query_arg( array( 'noheader' => false, 'msg' => 1 ) ) );
				exit;
			}
		}

		// render page
		$page = new Domainmap_Render_Page_Network( $this->_plugin->get_options() );
		$page->resellers = $this->_plugin->get_resellers();
		$page->render();
	}

	/**
	 * Enqueues appropriate scripts and styles for specific admin pages.
	 *
	 * @since 3.3
	 * @action admin_enqueue_scripts
	 * @uses plugins_url() To generate base URL of assets files.
	 * @uses wp_enqueue_script() To enqueue javascript files.
	 * @uses wp_enqueue_style() To enqueue CSS files.
	 *
	 * @access public
	 * @global WP_Styles $wp_styles The styles queue class object.
	 * @param string $page The page handle.
	 */
	public function enqueue_scripts( $page ) {
		global $wp_styles;

		// if we are not at the site admin page, then exit
		if ( $page != $this->_admin_page ) {
			return;
		}

		$baseurl = plugins_url( '/', DOMAINMAP_BASEFILE );

		// enqueue scripts
		wp_enqueue_script( 'domainmapping-admin', $baseurl . 'js/admin.js', array( 'jquery' ), Domainmap_Plugin::VERSION, true );
		wp_localize_script( 'domainmapping-admin', 'domainmapping', array(
			'message' => array(
				'unmap' => __( 'You are about to unmap selected domain. Do you really want to proceed?', 'domainmap' ),
				'empty' => __( 'Please, enter not empty domain name.', 'domainmap' ),
			),
		) );

		// enqueue styles
		wp_enqueue_style( 'font-awesome', $baseurl . 'css/font-awesome.min.css', array(), '3.2.1' );
		wp_enqueue_style( 'font-awesome-ie', $baseurl . 'css/font-awesome-ie7.min.css', array( 'font-awesome' ), '3.2.1' );
		wp_enqueue_style( 'google-font-lato', 'https://fonts.googleapis.com/css?family=Lato:300,400,700,400italic', array(), Domainmap_Plugin::VERSION );
		wp_enqueue_style( 'domainmapping-admin', $baseurl . 'css/admin.css', array( 'google-font-lato' ), Domainmap_Plugin::VERSION );

		$wp_styles->registered['font-awesome-ie']->add_data( 'conditional', 'IE 7' );
	}

}