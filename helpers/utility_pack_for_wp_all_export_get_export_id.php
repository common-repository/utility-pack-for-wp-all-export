<?php

if ( ! function_exists( 'utility_pack_for_wp_all_export_get_export_id' ) ) {
	function utility_pack_for_wp_all_export_get_export_id() {
		global $argv;
		$export_id = 'new';

		if ( ! empty( $argv ) ) {
			$export_id_arr = array_filter( $argv, function( $a ) {
				return ( is_numeric( $a ) ) ? true : false;
			});

			if ( ! empty( $export_id_arr ) ) {
				$export_id = reset( $export_id_arr );
			}
		}

		if ( $export_id == 'new' ) {
			if ( isset( $_GET['export_id'] ) ) {
				$export_id = $_GET['export_id'];
			} elseif ( isset( $_GET['id'] ) ) {
				$export_id = $_GET['id'];
			}
		}

		return $export_id;
	}
}