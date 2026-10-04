<?php
defined( 'ABSPATH' ) || exit;

/**
 * Data access + validation for clients and payments. Every query uses $wpdb->prepare().
 *
 * Payment definition (used everywhere in the UI): "amount paid" = SUM of ALL recorded payments
 * of a client (initial payment + every renewal). Totals/filters/sorting use the same sum.
 */
final class NIVC_Repository {

	const SORTS = array(
		'name'      => 'name',
		'created'   => 'landing_created_on',
		'expires'   => 'expires_on',
		'paid'      => 'paid',
		'views'     => 'views',
		'days_left' => 'days_left',
	);

	/* ---------------------------------------------------------------- queries */

	/** Inner SELECT with computed columns; wrapped by callers so filters can use aliases. */
	private static function base_sql( string $today, bool $tracking ): string {
		global $wpdb;
		$c = NIVC_DB::table( 'clients' );
		$p = NIVC_DB::table( 'payments' );
		$v = NIVC_DB::table( 'visits' );
		return $wpdb->prepare(
			"SELECT c.*, COALESCE(pp.paid, 0) AS paid, pp.payments_count,
				CASE WHEN %d = 1 AND c.path_key <> '' THEN COALESCE(vv.views, 0) END AS views,
				CASE WHEN %d = 1 AND c.path_key <> '' THEN COALESCE(vv.uniques, 0) END AS uniques,
				DATEDIFF(c.expires_on, %s) AS days_left
			FROM {$c} c
			LEFT JOIN (SELECT client_id, SUM(amount) AS paid, COUNT(*) AS payments_count FROM {$p} GROUP BY client_id) pp ON pp.client_id = c.id
			LEFT JOIN (SELECT path_key, SUM(views) AS views, COUNT(*) AS uniques FROM {$v} GROUP BY path_key) vv ON vv.path_key = c.path_key",
			$tracking ? 1 : 0,
			$tracking ? 1 : 0,
			$today
		); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/** Builds the outer WHERE from sanitized filters. */
	private static function where_sql( array $f ): string {
		global $wpdb;
		$w = array();

		if ( '' !== $f['search'] ) {
			$like  = '%' . $wpdb->esc_like( $f['search'] ) . '%';
			$parts = array(
				$wpdb->prepare( 'name LIKE %s', $like ),
				$wpdb->prepare( 'landing_name LIKE %s', $like ),
				$wpdb->prepare( 'landing_url LIKE %s', $like ),
				$wpdb->prepare( 'phone LIKE %s', $like ),
			);
			$digits = ltrim( preg_replace( '/\D+/', '', $f['search'] ), '0' );
			if ( strlen( $digits ) >= 3 ) {
				$parts[] = $wpdb->prepare( 'phone_intl LIKE %s', '%' . $wpdb->esc_like( $digits ) . '%' );
			}
			$w[] = '(' . implode( ' OR ', $parts ) . ')';
		}

		switch ( $f['status'] ) {
			case 'active':
				$w[] = $wpdb->prepare( 'days_left > %d', NIVC_Helpers::SOON_DAYS );
				break;
			case 'soon':
				$w[] = $wpdb->prepare( 'days_left BETWEEN 0 AND %d', NIVC_Helpers::SOON_DAYS );
				break;
			case 'expired':
				$w[] = 'days_left < 0';
				break;
		}

		$ranges = array(
			'created_from' => array( 'landing_created_on >= %s' ),
			'created_to'   => array( 'landing_created_on <= %s' ),
			'expires_from' => array( 'expires_on >= %s' ),
			'expires_to'   => array( 'expires_on <= %s' ),
			'paid_min'     => array( 'paid >= %f' ),
			'paid_max'     => array( 'paid <= %f' ),
			'views_min'    => array( 'views >= %d' ),
			'views_max'    => array( 'views <= %d' ),
		);
		foreach ( $ranges as $key => $tpl ) {
			if ( null !== $f[ $key ] ) {
				$w[] = $wpdb->prepare( $tpl[0], $f[ $key ] ); // phpcs:ignore WordPress.DB.PreparedSQL
			}
		}

		return $w ? ' WHERE ' . implode( ' AND ', $w ) : '';
	}

	public static function sanitize_filters( array $in ): array {
		$date = static function ( $v ) {
			return NIVC_Helpers::is_valid_date( $v ) ? $v : null;
		};
		$num = static function ( $v ) {
			return ( is_numeric( $v ) && $v >= 0 ) ? (float) $v : null;
		};
		$int = static function ( $v ) {
			return ( is_numeric( $v ) && $v >= 0 ) ? (int) $v : null;
		};
		$status = isset( $in['status'] ) ? (string) $in['status'] : '';
		$sort   = isset( $in['sort'] ) && isset( self::SORTS[ $in['sort'] ] ) ? $in['sort'] : 'expires';
		$per    = isset( $in['per_page'] ) ? (int) $in['per_page'] : 25;
		return array(
			'search'       => isset( $in['search'] ) ? trim( sanitize_text_field( wp_unslash( (string) $in['search'] ) ) ) : '',
			'status'       => in_array( $status, array( 'active', 'soon', 'expired' ), true ) ? $status : '',
			'created_from' => $date( $in['created_from'] ?? '' ),
			'created_to'   => $date( $in['created_to'] ?? '' ),
			'expires_from' => $date( $in['expires_from'] ?? '' ),
			'expires_to'   => $date( $in['expires_to'] ?? '' ),
			'paid_min'     => $num( $in['paid_min'] ?? '' ),
			'paid_max'     => $num( $in['paid_max'] ?? '' ),
			'views_min'    => $int( $in['views_min'] ?? '' ),
			'views_max'    => $int( $in['views_max'] ?? '' ),
			'sort'         => $sort,
			'order'        => ( isset( $in['order'] ) && 'desc' === strtolower( (string) $in['order'] ) ) ? 'DESC' : 'ASC',
			'page'         => max( 1, (int) ( $in['page'] ?? 1 ) ),
			'per_page'     => in_array( $per, array( 10, 25, 50, 100 ), true ) ? $per : 25,
		);
	}

	/** @return array{items:array,total:int,pages:int,page:int,summary:array,all_clients:int} */
	public static function query( array $in ): array {
		global $wpdb;
		$f        = self::sanitize_filters( $in );
		$today    = NIVC_Helpers::today();
		$tracking = NIVC_Settings::tracking_enabled();
		$base     = self::base_sql( $today, $tracking );
		$where    = self::where_sql( $f );

		$summary = $wpdb->get_row(
			"SELECT COUNT(*) AS total,
				COALESCE(SUM(days_left > " . (int) NIVC_Helpers::SOON_DAYS . "), 0) AS active,
				COALESCE(SUM(days_left BETWEEN 0 AND " . (int) NIVC_Helpers::SOON_DAYS . "), 0) AS soon,
				COALESCE(SUM(days_left < 0), 0) AS expired,
				COALESCE(SUM(paid), 0) AS paid,
				SUM(views) AS views, SUM(uniques) AS uniques
			FROM ({$base}) t{$where}",
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL
		$total   = (int) $summary['total'];
		$pages   = max( 1, (int) ceil( $total / $f['per_page'] ) );
		$page    = min( $f['page'], $pages );
		$offset  = ( $page - 1 ) * $f['per_page'];

		$col   = self::SORTS[ $f['sort'] ];
		$order = $f['order'];
		// Missing page-view data (NULL) always sorts last.
		$nulls = 'views' === $col ? 'views IS NULL, ' : '';
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM ({$base}) t{$where} ORDER BY {$nulls}{$col} {$order}, id DESC LIMIT %d OFFSET %d",
				$f['per_page'],
				$offset
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL

		$all = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . NIVC_DB::table( 'clients' ) ); // phpcs:ignore WordPress.DB.PreparedSQL

		return array(
			'items'       => array_map( array( __CLASS__, 'present' ), $rows ? $rows : array() ),
			'total'       => $total,
			'pages'       => $pages,
			'page'        => $page,
			'all_clients' => $all,
			'filtered'    => $total !== $all || self::has_filters( $f ),
			'summary'     => array(
				'total'   => $total,
				'active'  => (int) $summary['active'],
				'soon'    => (int) $summary['soon'],
				'expired' => (int) $summary['expired'],
				'paid'    => (float) $summary['paid'],
				'views'   => ( $tracking && null !== $summary['views'] ) ? (int) $summary['views'] : ( $tracking ? 0 : null ),
				'uniques' => ( $tracking && null !== $summary['uniques'] ) ? (int) $summary['uniques'] : ( $tracking ? 0 : null ),
			),
		);
	}

	private static function has_filters( array $f ): bool {
		foreach ( array( 'search', 'status', 'created_from', 'created_to', 'expires_from', 'expires_to', 'paid_min', 'paid_max', 'views_min', 'views_max' ) as $k ) {
			if ( '' !== $f[ $k ] && null !== $f[ $k ] ) {
				return true;
			}
		}
		return false;
	}

	public static function get( int $id ): ?array {
		global $wpdb;
		$base = self::base_sql( NIVC_Helpers::today(), NIVC_Settings::tracking_enabled() );
		$row  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM ({$base}) t WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		return $row ? self::present( $row ) : null;
	}

	/** Shapes a DB row for the API (no raw DB internals). */
	public static function present( array $r ): array {
		$today = NIVC_Helpers::today();
		$state = NIVC_Helpers::service_state( $r['expires_on'], $today );
		$page  = (int) $r['page_id'];
		$url   = $r['landing_url'];
		if ( $page ) {
			$live = get_permalink( $page );
			if ( $live ) {
				$url = $live;
			}
		}
		$safe_url = ( $url && preg_match( '#^https?://#i', $url ) ) ? esc_url_raw( $url ) : '';
		return array(
			'id'               => (int) $r['id'],
			'name'             => $r['name'],
			'landing_name'     => $r['landing_name'],
			'page_id'          => $page,
			'landing_source'   => $page ? 'page' : 'url',
			'landing_url'      => $safe_url,
			'phone'            => $r['phone'],
			'phone_intl'       => $r['phone_intl'],
			'whatsapp_url'     => NIVC_Helpers::whatsapp_url( $r['phone_intl'] ),
			'landing_created'  => $r['landing_created_on'],
			'expires_on'       => $r['expires_on'],
			'record_added_at'  => $r['created_at'],
			'notes'            => (string) $r['notes'],
			'paid'             => (float) $r['paid'],
			'payments_count'   => (int) $r['payments_count'],
			'views'            => null === $r['views'] ? null : (int) $r['views'],
			'uniques'          => null === $r['uniques'] ? null : (int) $r['uniques'],
			'measurable'       => '' !== $r['path_key'],
			'days_left'        => $state['days_left'],
			'overdue_days'     => $state['overdue_days'],
			'status'           => $state['status'],
			'progress'         => NIVC_Helpers::progress_percent( $r['expires_on'], $today ),
		);
	}

	public static function payments( int $client_id ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT id, kind, amount, paid_on, period_start, period_end, note FROM ' . NIVC_DB::table( 'payments' ) . ' WHERE client_id = %d ORDER BY paid_on DESC, id DESC', $client_id ), // phpcs:ignore WordPress.DB.PreparedSQL
			ARRAY_A
		);
		return array_map(
			static function ( $r ) {
				$r['id']     = (int) $r['id'];
				$r['amount'] = (float) $r['amount'];
				return $r;
			},
			$rows ? $rows : array()
		);
	}

	/* ------------------------------------------------------------- validation */

	/**
	 * Validates + sanitizes client input.
	 *
	 * @return array|WP_Error Clean data, or WP_Error (422) with data['errors'] = field => Hebrew message.
	 */
	public static function validate_client( array $in ) {
		$e = array();
		$d = array();

		$d['name'] = sanitize_text_field( (string) ( $in['name'] ?? '' ) );
		if ( '' === $d['name'] ) {
			$e['name'] = 'נא להזין שם לקוח או עסק.';
		} elseif ( mb_strlen( $d['name'] ) > 190 ) {
			$e['name'] = 'השם ארוך מדי (עד 190 תווים).';
		}

		$d['landing_name'] = sanitize_text_field( (string) ( $in['landing_name'] ?? '' ) );
		if ( '' === $d['landing_name'] ) {
			$e['landing_name'] = 'נא להזין שם דף נחיתה.';
		} elseif ( mb_strlen( $d['landing_name'] ) > 190 ) {
			$e['landing_name'] = 'שם דף הנחיתה ארוך מדי (עד 190 תווים).';
		}

		$source        = ( $in['landing_source'] ?? 'url' ) === 'page' ? 'page' : 'url';
		$d['page_id']  = 0;
		$d['landing_url'] = '';
		$d['path_key'] = '';
		if ( 'page' === $source ) {
			$pid  = (int) ( $in['page_id'] ?? 0 );
			$post = $pid ? get_post( $pid ) : null;
			if ( ! $post || 'page' !== $post->post_type || 'trash' === $post->post_status ) {
				$e['page_id'] = 'נא לבחור עמוד קיים מהרשימה.';
			} else {
				$d['page_id']     = $pid;
				$d['landing_url'] = (string) get_permalink( $pid );
			}
		} else {
			$url = trim( (string) ( $in['landing_url'] ?? '' ) );
			if ( '' === $url ) {
				$e['landing_url'] = 'נא להזין כתובת דף נחיתה.';
			} else {
				$clean = esc_url_raw( $url, array( 'http', 'https' ) );
				if ( '' === $clean || ! wp_http_validate_url( $clean ) || ! preg_match( '#^https?://#i', $clean ) ) {
					$e['landing_url'] = 'כתובת האתר אינה תקינה (יש להתחיל ב-https://).';
				} else {
					$d['landing_url'] = $clean;
				}
			}
		}
		if ( '' !== $d['landing_url'] ) {
			$d['path_key'] = NIVC_Helpers::path_key_from_url( $d['landing_url'], home_url() );
		}

		$phone = NIVC_Helpers::normalize_phone( (string) ( $in['phone'] ?? '' ) );
		if ( '' === trim( (string) ( $in['phone'] ?? '' ) ) ) {
			$e['phone'] = 'נא להזין מספר טלפון.';
		} elseif ( ! $phone ) {
			$e['phone'] = 'מספר הטלפון אינו תקין. לדוגמה: 050-1234567.';
		} else {
			$d['phone']      = $phone['display'];
			$d['phone_intl'] = $phone['intl'];
		}

		$amount = $in['amount'] ?? '';
		if ( '' === $amount || null === $amount || ! is_numeric( $amount ) ) {
			$e['amount'] = 'נא להזין סכום תקין בשקלים.';
		} elseif ( (float) $amount < 0 || (float) $amount > 99999999 ) {
			$e['amount'] = 'הסכום חייב להיות בין 0 ל-99,999,999.';
		} else {
			$d['amount'] = round( (float) $amount, 2 );
		}

		$created = (string) ( $in['landing_created'] ?? '' );
		if ( ! NIVC_Helpers::is_valid_date( $created ) ) {
			$e['landing_created'] = 'נא לבחור תאריך יצירה תקין.';
		} else {
			$d['landing_created'] = $created;
		}

		$expires = (string) ( $in['expires_on'] ?? '' );
		if ( '' === $expires && isset( $d['landing_created'] ) ) {
			$expires = NIVC_Helpers::add_years( $d['landing_created'], 1 );
		}
		if ( ! NIVC_Helpers::is_valid_date( $expires ) ) {
			$e['expires_on'] = 'נא לבחור תאריך תפוגה תקין.';
		} elseif ( isset( $d['landing_created'] ) && $expires <= $d['landing_created'] ) {
			$e['expires_on'] = 'תאריך התפוגה חייב להיות אחרי תאריך היצירה.';
		} else {
			$d['expires_on'] = $expires;
		}

		$d['notes'] = sanitize_textarea_field( (string) ( $in['notes'] ?? '' ) );
		if ( mb_strlen( $d['notes'] ) > 5000 ) {
			$e['notes'] = 'ההערות ארוכות מדי (עד 5,000 תווים).';
		}

		if ( $e ) {
			return new WP_Error( 'nivc_invalid', 'יש לתקן את השדות המסומנים.', array( 'status' => 422, 'errors' => $e ) );
		}
		return $d;
	}

	/* ----------------------------------------------------------------- writes */

	public static function create( array $d ) {
		global $wpdb;
		$now = NIVC_Helpers::now_mysql();
		$ok  = $wpdb->insert(
			NIVC_DB::table( 'clients' ),
			array(
				'name'               => $d['name'],
				'landing_name'       => $d['landing_name'],
				'page_id'            => $d['page_id'],
				'landing_url'        => $d['landing_url'],
				'path_key'           => $d['path_key'],
				'phone'              => $d['phone'],
				'phone_intl'         => $d['phone_intl'],
				'landing_created_on' => $d['landing_created'],
				'expires_on'         => $d['expires_on'],
				'notes'              => $d['notes'],
				'created_at'         => $now,
				'updated_at'         => $now,
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		if ( ! $ok ) {
			return new WP_Error( 'nivc_db', 'שמירת הלקוח נכשלה. נסו שוב.', array( 'status' => 500 ) );
		}
		$id = (int) $wpdb->insert_id;
		self::insert_payment( $id, 'initial', $d['amount'], $d['landing_created'], $d['landing_created'], $d['expires_on'], 'תשלום ראשוני' );
		NIVC_Tracker::flush_cache();
		return $id;
	}

	public static function update( int $id, array $d ) {
		global $wpdb;
		$ok = $wpdb->update(
			NIVC_DB::table( 'clients' ),
			array(
				'name'               => $d['name'],
				'landing_name'       => $d['landing_name'],
				'page_id'            => $d['page_id'],
				'landing_url'        => $d['landing_url'],
				'path_key'           => $d['path_key'],
				'phone'              => $d['phone'],
				'phone_intl'         => $d['phone_intl'],
				'landing_created_on' => $d['landing_created'],
				'expires_on'         => $d['expires_on'],
				'notes'              => $d['notes'],
				'updated_at'         => NIVC_Helpers::now_mysql(),
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);
		if ( false === $ok ) {
			return new WP_Error( 'nivc_db', 'עדכון הלקוח נכשל. נסו שוב.', array( 'status' => 500 ) );
		}
		// The form amount edits the INITIAL payment only; renewals stay untouched.
		$pt      = NIVC_DB::table( 'payments' );
		$initial = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$pt} WHERE client_id = %d AND kind = 'initial' ORDER BY id ASC LIMIT 1", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		if ( $initial ) {
			$wpdb->update( $pt, array( 'amount' => $d['amount'] ), array( 'id' => $initial ), array( '%f' ), array( '%d' ) );
		} else {
			self::insert_payment( $id, 'initial', $d['amount'], $d['landing_created'], $d['landing_created'], $d['expires_on'], 'תשלום ראשוני' );
		}
		NIVC_Tracker::flush_cache();
		return true;
	}

	public static function delete( int $id ): bool {
		global $wpdb;
		// Only dashboard records are removed; the WordPress page itself is never touched.
		$wpdb->delete( NIVC_DB::table( 'payments' ), array( 'client_id' => $id ), array( '%d' ) );
		$n = $wpdb->delete( NIVC_DB::table( 'clients' ), array( 'id' => $id ), array( '%d' ) );
		NIVC_Tracker::flush_cache();
		return (bool) $n;
	}

	/** @return array|WP_Error */
	public static function renew( int $id, array $in ) {
		global $wpdb;
		$e     = array();
		$start = (string) ( $in['start_date'] ?? '' );
		if ( ! NIVC_Helpers::is_valid_date( $start ) ) {
			$e['start_date'] = 'נא לבחור תאריך התחלה תקין.';
		}
		$amount = $in['amount'] ?? '';
		if ( '' === $amount || ! is_numeric( $amount ) || (float) $amount < 0 || (float) $amount > 99999999 ) {
			$e['amount'] = 'נא להזין סכום תקין בשקלים.';
		}
		$note = sanitize_text_field( (string) ( $in['note'] ?? '' ) );
		if ( mb_strlen( $note ) > 255 ) {
			$e['note'] = 'ההערה ארוכה מדי (עד 255 תווים).';
		}
		if ( $e ) {
			return new WP_Error( 'nivc_invalid', 'יש לתקן את השדות המסומנים.', array( 'status' => 422, 'errors' => $e ) );
		}
		$new_end = NIVC_Helpers::add_years( $start, 1 );
		$ok      = self::insert_payment( $id, 'renewal', round( (float) $amount, 2 ), NIVC_Helpers::today(), $start, $new_end, $note ? $note : 'חידוש לשנה' );
		if ( ! $ok ) {
			return new WP_Error( 'nivc_db', 'רישום החידוש נכשל. נסו שוב.', array( 'status' => 500 ) );
		}
		$upd = $wpdb->update(
			NIVC_DB::table( 'clients' ),
			array( 'expires_on' => $new_end, 'updated_at' => NIVC_Helpers::now_mysql() ),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
		if ( false === $upd ) {
			$wpdb->delete( NIVC_DB::table( 'payments' ), array( 'id' => $ok ), array( '%d' ) );
			return new WP_Error( 'nivc_db', 'רישום החידוש נכשל. נסו שוב.', array( 'status' => 500 ) );
		}
		return array( 'expires_on' => $new_end );
	}

	private static function insert_payment( int $client_id, string $kind, float $amount, string $paid_on, string $start, string $end, string $note ) {
		global $wpdb;
		$ok = $wpdb->insert(
			NIVC_DB::table( 'payments' ),
			array(
				'client_id'    => $client_id,
				'kind'         => $kind,
				'amount'       => $amount,
				'paid_on'      => $paid_on,
				'period_start' => $start,
				'period_end'   => $end,
				'note'         => $note,
				'created_at'   => NIVC_Helpers::now_mysql(),
			),
			array( '%d', '%s', '%f', '%s', '%s', '%s', '%s', '%s' )
		);
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/** Keeps stored URL/path in sync when a linked WordPress page is edited or its slug changes. */
	public static function sync_page( int $page_id ): void {
		global $wpdb;
		$url = get_permalink( $page_id );
		if ( ! $url ) {
			return;
		}
		$wpdb->update(
			NIVC_DB::table( 'clients' ),
			array( 'landing_url' => $url, 'path_key' => NIVC_Helpers::path_key_from_url( $url, home_url() ) ),
			array( 'page_id' => $page_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
		NIVC_Tracker::flush_cache();
	}

	/** WordPress pages available for selection. */
	public static function pages(): array {
		$posts = get_posts(
			array(
				'post_type'        => 'page',
				'post_status'      => array( 'publish', 'draft', 'private', 'pending', 'future' ),
				'posts_per_page'   => 1000,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'no_found_rows'    => true,
				'suppress_filters' => true,
			)
		);
		$out   = array();
		foreach ( $posts as $p ) {
			$out[] = array(
				'id'     => (int) $p->ID,
				'title'  => '' !== $p->post_title ? wp_strip_all_tags( $p->post_title ) : '(ללא כותרת) #' . $p->ID,
				'url'    => get_permalink( $p ),
				'status' => $p->post_status,
			);
		}
		return $out;
	}
}
