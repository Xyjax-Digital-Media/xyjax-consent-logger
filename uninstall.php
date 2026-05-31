<?php
/**
 * Uninstall cleanup for Xyjax Consent Logger.
 *
 * @package Xyjax_Consent_Logger
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

delete_option( 'xyjax_consent_logger_settings' );
$wpdb->query( 'DROP TABLE IF EXISTS ' . esc_sql( $wpdb->prefix . 'xyjax_consent_logs' ) );
