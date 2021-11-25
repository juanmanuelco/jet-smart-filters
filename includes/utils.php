<?php
/**
 * Utils class
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'Jet_Smart_Filters_Utils' ) ) {

	/**
	 * Define Jet_Smart_Filters_Utils class
	 */
	class Jet_Smart_Filters_Utils {

		/**
		 * Returns template content as string
		 *
		 * @return string
		 */
		public function get_template_html( $template ) {

			ob_start();
			include jet_smart_filters()->get_template( $template );
			$html = ob_get_clean();

			return preg_replace('~>\\s+<~m', '><', $html);

		}

		/**
		 * Returns parsed template
		 *
		 * @return string
		 */
		public function template_parse( $template ) {

			$html_template = $this->get_template_html( $template );

			preg_match_all( '/<%(.+?)%>/', $html_template, $matches, PREG_SET_ORDER );
			foreach ( $matches as $item ) {
				$prefix = ! preg_match( '/(if|for|else|{|})/', $item[0] ) ? 'echo ' : '';
				$html_template = str_replace( $item[0], '<?php ' . $prefix . trim( $item[1] ) . ' ?>', $html_template );
			}

			return $html_template;

		}

		/**
		 * Returns image size array in slug => name format
		 *
		 * @return  array
		 */
		public function get_image_sizes() {

			global $_wp_additional_image_sizes;

			$sizes  = get_intermediate_image_sizes();
			$result = array();

			foreach ( $sizes as $size ) {
				if ( in_array( $size, array( 'thumbnail', 'medium', 'medium_large', 'large' ) ) ) {
					$result[ $size ] = ucwords( trim( str_replace( array( '-', '_' ), array( ' ', ' ' ), $size ) ) );
				} else {
					$result[ $size ] = sprintf(
						'%1$s (%2$sx%3$s)',
						ucwords( trim( str_replace( array( '-', '_' ), array( ' ', ' ' ), $size ) ) ),
						$_wp_additional_image_sizes[ $size ]['width'],
						$_wp_additional_image_sizes[ $size ]['height']
					);
				}
			}

			return array_merge( array( 'full' => esc_html__( 'Full', 'jet-woo-builder' ), ), $result );
		}

		/**
		 * Returns additional providers
		 *
		 * @return string
		 */
		public function get_additional_providers( $settings ) {

			if ( empty( $settings['additional_providers_enabled'] ) ) {
				return '';
			}
		
			if ( ! empty( $settings['additional_providers_list'] ) ) {
				$additional_providers = $settings['additional_providers_list'];
			} else if ( ! empty( $settings['additional_providers'] ) ) {
				// backward compatibility
				$additional_providers = array_map( function ( $additional_provider ) {
					return array( 'additional_provider' => $additional_provider );
				}, $settings['additional_providers'] );
			} else {
				return '';
			}
		
			$output_data      = [];
			$default_query_id = ! empty( $settings['query_id'] ) ? $settings['query_id'] : 'default';
		
			foreach ( $additional_providers as $additional_provider ) {
				$provider = ! empty( $additional_provider['additional_provider'] ) ? $additional_provider['additional_provider'] : false;
				$query_id = ! empty( $additional_provider['additional_query_id'] ) ? $additional_provider['additional_query_id'] : $default_query_id;
		
				if ( $provider ) {
					$output_data[] = $provider . ( $query_id ? '/' . $query_id : '' );
				}
			}
		
			return $output_data ? htmlspecialchars( json_encode( $output_data ) ) : '';
		
		}

		/**
		 * Merge Query Args
		 *
		 * @return Array
		 */
		public function merge_query_args( $current_query_args, $new_query_args ) {

			foreach ( $new_query_args as $key => $value ) {
				if ( in_array( $key, array( 'tax_query', 'meta_query' ) ) && ! empty( $current_query_args[$key] ) ) {
					$value = array_merge( $current_query_args[$key], $value );
				}

				$current_query_args[$key] = $value;
			}

			return $current_query_args;

		}

		/**
		 * Insert in array after key
		 *
		 * @return Array
		 */
		public function array_insert_after( $source = array(), $after = null, $insert = array() ) {

			$index  = array_search( $after, array_keys( $source ) );

			if ( false === $index ) {
				return $source + $insert;
			}

			$offset = $index + 1;

			return array_slice( $source, 0, $offset, true ) + $insert + array_slice( $source, $offset, null, true );

		}

	}

}
