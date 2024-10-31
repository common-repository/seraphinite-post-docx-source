<?php
/*
Plugin Name: Seraphinite Post .DOCX Source
Plugin URI: http://wordpress.org/plugins/seraphinite-post-docx-source
Description: Save your time by automatically converting from .DOCX to content with all WordPress post attributes.
Text Domain: seraphinite-post-docx-source
Domain Path: /languages
Version: 2.16.10
Author: Seraphinite Solutions
Author URI: https://www.s-sols.com
License: GPLv2 or later (if another license is not provided)
Requires PHP: 5.4
Requires at least: 4.5





 */




























if( defined( 'SERAPH_PDS_VER' ) )
	return;

define( 'SERAPH_PDS_VER', '2.16.10' );

include( __DIR__ . '/main.php' );

// #######################################################################

register_activation_hook( __FILE__, 'seraph_pds\\Plugin::OnActivate' );
register_deactivation_hook( __FILE__, 'seraph_pds\\Plugin::OnDeactivate' );
//register_uninstall_hook( __FILE__, 'seraph_pds\\Plugin::OnUninstall' );

// #######################################################################
// #######################################################################
