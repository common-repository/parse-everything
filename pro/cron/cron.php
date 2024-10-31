<?php

if ( ! class_exists( 'ParseEverythingCronPro' ) ) {
	class ParseEverythingCronPro {

		public static function init() {
			add_filter( 'cron_schedules', [ self::class, 'add_schedules' ] ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval
		}

		public static function add_schedules( $schedules ) {
			$schedules['pe_minutely'] = array(
				'interval' => MINUTE_IN_SECONDS,
				'display' => __( 'Once Minutely' )
			);
			$schedules['pe_5mins'] = array(
				'interval' => 5 * MINUTE_IN_SECONDS,
				'display' => __( 'Every 5 mins' )
			);
			$schedules['pe_10mins'] = array(
				'interval' => 10 * MINUTE_IN_SECONDS,
				'display' => __( 'Every 10 mins' )
			);
			$schedules['pe_30mins'] = array(
				'interval' => 30 * MINUTE_IN_SECONDS,
				'display' => __( 'Every 30 mins' )
			);
			$schedules['pe_weekly'] = array(
				'interval' => WEEK_IN_SECONDS,
				'display' => __( 'Once Weekly' )
			);

			return $schedules;
		}
	}

	ParseEverythingCronPro::init();
}
