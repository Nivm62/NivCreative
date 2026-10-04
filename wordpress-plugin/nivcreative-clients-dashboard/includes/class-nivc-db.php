<?php
defined( 'ABSPATH' ) || exit;

/** Schema management (dbDelta). Tables are never dropped by the plugin. */
final class NIVC_DB {

	public static function table( string $name ): string {
		global $wpdb;
		return $wpdb->prefix . 'nivc_' . $name;
	}

	public static function maybe_upgrade(): void {
		if ( get_option( 'nivc_db_version' ) !== NIVC_DB_VERSION ) {
			self::install();
		}
	}

	public static function install(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$clients = self::table( 'clients' );
		$pay     = self::table( 'payments' );
		$visits  = self::table( 'visits' );

		// landing_created_on = when the landing page was created (editable, may be historical).
		// created_at         = when this record was added to the dashboard (system-managed).
		dbDelta(
			"CREATE TABLE {$clients} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(190) NOT NULL,
  landing_name varchar(190) NOT NULL DEFAULT '',
  page_id bigint(20) unsigned NOT NULL DEFAULT 0,
  landing_url varchar(2083) NOT NULL DEFAULT '',
  path_key varchar(190) NOT NULL DEFAULT '',
  phone varchar(40) NOT NULL DEFAULT '',
  phone_intl varchar(20) NOT NULL DEFAULT '',
  landing_created_on date NOT NULL,
  expires_on date NOT NULL,
  notes text NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY path_key (path_key),
  KEY expires_on (expires_on),
  KEY page_id (page_id)
) {$charset};"
		);

		// One row per payment: kind = initial | renewal.
		dbDelta(
			"CREATE TABLE {$pay} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  client_id bigint(20) unsigned NOT NULL,
  kind varchar(20) NOT NULL DEFAULT 'initial',
  amount decimal(12,2) NOT NULL DEFAULT 0.00,
  paid_on date NOT NULL,
  period_start date NULL,
  period_end date NULL,
  note varchar(255) NOT NULL DEFAULT '',
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY client_id (client_id)
) {$charset};"
		);

		// Cookie-less aggregate: one row per page/day/anonymous daily visitor hash.
		dbDelta(
			"CREATE TABLE {$visits} (
  path_key varchar(190) NOT NULL,
  day date NOT NULL,
  visitor_hash char(32) NOT NULL,
  views int(10) unsigned NOT NULL DEFAULT 1,
  last_seen datetime NOT NULL,
  PRIMARY KEY  (path_key,day,visitor_hash),
  KEY day (day)
) {$charset};"
		);

		update_option( 'nivc_db_version', NIVC_DB_VERSION, false );
	}
}
