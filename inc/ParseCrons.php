<?php
namespace ParseInc;

class ParseCrons {
	public static function init() {
		add_filter( 'cron_schedules', [ self::class, 'add_schedules' ] );

		$periods = self::get_periods();
		foreach ( $periods as $key => $period ) {
			add_action( $key . '_parse', [ self::class, 'handle_cron' ] );
			if( ! wp_next_scheduled( $key . '_parse' ) ) {
				wp_schedule_event( time(), $key, $key . '_parse' );
			}
		}
	}

	public static function add_schedules( $schedules ) {
		$schedules['pe_hourly'] = array(
			'interval' => HOUR_IN_SECONDS,
			'display'  => __( 'Once Hourly' ),
		);
		$schedules['pe_twicedaily'] = array(
			'interval' => DAY_IN_SECONDS / 2,
			'display'  => __( 'Twice Daily' ),
		);
		$schedules['pe_daily'] = array(
			'interval' => DAY_IN_SECONDS,
			'display'  => __( 'Once Daily' ),
		);

		return $schedules;
	}

	public static function get_periods() {
		$periods = wp_get_schedules();
		foreach ( $periods as $key => $val ) {
			if ( 'pe_' !== substr( $key, 0, 3 ) ) {
				unset( $periods[ $key ] );
			}
		}

		return $periods;
	}

	public static function handle_cron() {
		$period = str_replace( '_parse', '', current_action() );
		$args = [
			'post_type' => ParseCpt::POST_TYPE,
			'fields' => 'ids',
			'posts_per_page' => -1,
			'meta_query' => [
				'relation' => 'OR',
				[
					'key' => 'pe_period',
					'value' => $period,
				],
			]
		];
		if ( 'pe_hourly' === $period ) {
			$args['meta_query'][] = [
				'key' => 'pe_period',
				'compare' => 'NOT EXISTS'
			];
		}
		$ids = get_posts( $args );

		foreach ( $ids as $id ) {
			self::run_cron( $id );
		}
	}

	public static function run_cron( $id ) {
		$status = get_post_status( $id );
		$settings = ParseCpt::get_settings( $id );
		if ( 'publish' !== $status ) {
			return;
		}
		$templates = ParseCpt::get_templates( $id );
		$args = array_merge( $settings, $templates, [ 'id' => $id ] );
		$original_parse_result = \ParseEverything::run_parse( $args );

		if ( ! is_wp_error( $original_parse_result ) ) {
			$parse_result = preg_replace( '/[^[:print:]]/', '', $original_parse_result );
			$data = [
				'time' => time(),
				'result' => $parse_result,
			];
			$old_value = get_post_meta( $id, 'parse_result', true );
			update_post_meta( $id, 'parse_result', $data );
			$new_value = get_post_meta( $id, 'parse_result', true );
			if ( ( empty( $old_value['result'] ) || $old_value['result'] !== $new_value['result'] )
					&& ! empty( $settings['type'] ) ) {
				ParseSenders::send( $settings, $original_parse_result );
			}
		}
	}

	public static function activation() {
		self::remove_crons();
	}

	private static function remove_crons() {
		wp_clear_scheduled_hook( 'pe_hourly_parse' );
	}

	public static function deactivation() {
		self::remove_crons();
	}

}

register_activation_hook( __FILE__, [ 'ParseInc\ParseCpt', 'activation' ] );
register_deactivation_hook( __FILE__, [ 'ParseInc\ParseCpt', 'deactivation' ] );
