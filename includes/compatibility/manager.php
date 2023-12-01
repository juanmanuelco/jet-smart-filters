<?php
/**
 * Compatibility manager
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'Jet_Smart_Filters_Compatibility_Manager' ) ) {
	/**
	 * Define Jet_Engine_Compatibility class
	 */
	class Jet_Smart_Filters_Compatibility_Manager {
		/**
		 * Constructor for the class
		 */
		function __construct() {

			// WPML compatibility
			if ( defined( 'WPML_ST_VERSION' ) ) {
				require jet_smart_filters()->plugin_path( 'includes/compatibility/wpml/wpml-compatibility.php' );
				new Jet_Smart_Filters_Compatibility_WPML();
			}

			// WooCommerce product setup
			add_action( 'jet-smart-filters/referrer/request', array( $this, 'setup_wc_product' ) );

			// datepicker texts
			add_filter( 'jet-smart-filters/filters/localized-data',  array( $this, 'datepicker_texts' ) );

			// for CCT
			add_filter( 'jet-smart-filters/post-type/options-data-sources', array( $this, 'cct_data_sources' ) );
			add_filter( 'jet-smart-filters/post-type/meta-fields-settings', array( $this, 'cct_register_controls' ) );
		}

		public function setup_wc_product() {
			
			global $wp;

			if ( ! function_exists( 'wc_setup_product_data' ) ) {
				return;
			}

			if ( empty( $wp->query_vars['post_type'] ) || 'product' !== $wp->query_vars['post_type'] ) {
				return;
			}

			if ( empty( $wp->query_vars['product'] ) ) {
				return;
			}

			$posts = get_posts( [
				'post_type' => 'product',
				'name' => $wp->query_vars['product'],
				'posts_per_page' => 1
			] );

			if ( empty( $posts ) ) {
				return;
			}

			global $post;
			$post = $posts[0];

			wc_setup_product_data( $post );

		}

		public function datepicker_texts( $args ) {

			$args['datePickerData'] = array(
				'closeText'       => esc_html__( 'Done', 'jet-smart-filters' ),
				'prevText'        => esc_html__( 'Prev', 'jet-smart-filters' ),
				'nextText'        => esc_html__( 'Next', 'jet-smart-filters' ),
				'currentText'     => esc_html__( 'Today', 'jet-smart-filters' ),
				'monthNames'      => array(
					esc_html__( 'January', 'jet-smart-filters' ),
					esc_html__( 'February', 'jet-smart-filters' ),
					esc_html__( 'March', 'jet-smart-filters' ),
					esc_html__( 'April', 'jet-smart-filters' ),
					esc_html__( 'May', 'jet-smart-filters' ),
					esc_html__( 'June', 'jet-smart-filters' ),
					esc_html__( 'July', 'jet-smart-filters' ),
					esc_html__( 'August', 'jet-smart-filters' ),
					esc_html__( 'September', 'jet-smart-filters' ),
					esc_html__( 'October', 'jet-smart-filters' ),
					esc_html__( 'November', 'jet-smart-filters' ),
					esc_html__( 'December', 'jet-smart-filters' ),
				),
				'monthNamesShort' => array(
					esc_html__( 'Jan', 'jet-smart-filters' ),
					esc_html__( 'Feb', 'jet-smart-filters' ),
					esc_html__( 'Mar', 'jet-smart-filters' ),
					esc_html__( 'Apr', 'jet-smart-filters' ),
					esc_html__( 'May', 'jet-smart-filters' ),
					esc_html__( 'Jun', 'jet-smart-filters' ),
					esc_html__( 'Jul', 'jet-smart-filters' ),
					esc_html__( 'Aug', 'jet-smart-filters' ),
					esc_html__( 'Sep', 'jet-smart-filters' ),
					esc_html__( 'Oct', 'jet-smart-filters' ),
					esc_html__( 'Nov', 'jet-smart-filters' ),
					esc_html__( 'Dec', 'jet-smart-filters' ),
				),
				'dayNames'        => array(
					esc_html__( 'Sunday', 'jet-smart-filters' ),
					esc_html__( 'Monday', 'jet-smart-filters' ),
					esc_html__( 'Tuesday', 'jet-smart-filters' ),
					esc_html__( 'Wednesday', 'jet-smart-filters' ),
					esc_html__( 'Thursday', 'jet-smart-filters' ),
					esc_html__( 'Friday', 'jet-smart-filters' ),
					esc_html__( 'Saturday', 'jet-smart-filters' )
				),
				'dayNamesShort'   => array(
					esc_html__( 'Sun', 'jet-smart-filters' ),
					esc_html__( 'Mon', 'jet-smart-filters' ),
					esc_html__( 'Tue', 'jet-smart-filters' ),
					esc_html__( 'Wed', 'jet-smart-filters' ),
					esc_html__( 'Thu', 'jet-smart-filters' ),
					esc_html__( 'Fri', 'jet-smart-filters' ),
					esc_html__( 'Sat', 'jet-smart-filters' )
				),
				'dayNamesMin'     => array(
					esc_html__( 'Su', 'jet-smart-filters' ),
					esc_html__( 'Mo', 'jet-smart-filters' ),
					esc_html__( 'Tu', 'jet-smart-filters' ),
					esc_html__( 'We', 'jet-smart-filters' ),
					esc_html__( 'Th', 'jet-smart-filters' ),
					esc_html__( 'Fr', 'jet-smart-filters' ),
					esc_html__( 'Sa', 'jet-smart-filters' ),
				),
				'weekHeader'      => esc_html__( 'Wk', 'jet-smart-filters' ),
			);

			return $args;
		}

		public function cct_data_sources( $data_sources ) {

			if ( function_exists( 'jet_engine' ) && jet_engine()->modules->is_module_active( 'custom-content-types' ) ) {
				$data_sources['cct'] = __( 'JetEngine Custom Content Types', 'jet-smart-filters' );
			}

			return $data_sources;
		}

		public function cct_register_controls( $fields ) {

			if ( function_exists( 'jet_engine' ) && jet_engine()->modules->is_module_active( 'custom-content-types' ) ) {
				$fields = jet_smart_filters()->utils->array_insert_after( $fields, '_data_source', array(
					'_cct_notice' => array(
						'title'      => __( 'Coming soon', 'jet-smart-filters' ),
						'type'       => 'html',
						'fullwidth'  => true,
						'html'       => __( 'Support for the Visual filter will be added with future updates', 'jet-smart-filters' ),
						'conditions' => array(
							'_filter_type' => 'color-image',
							'_data_source' => 'cct',
						),
					),
				) );

				if ( jet_smart_filters()->is_classic_admin ) {
					$fields['_cct_notice']['type']        = 'text';
					$fields['_cct_notice']['input_type']  = 'hidden';
					$fields['_cct_notice']['description'] = $fields['_cct_notice']['html'];
					unset( $fields['_cct_notice']['html'] );
				}
			}

			$fields = jet_smart_filters()->utils->add_control_condition( $fields, '_color_image_type', '_cct_notice!', 'is_visible' );
			$fields = jet_smart_filters()->utils->add_control_condition( $fields, '_color_image_behavior', '_cct_notice!', 'is_visible' );
			$fields = jet_smart_filters()->utils->add_control_condition( $fields, '_source_color_image_input', '_cct_notice!', 'is_visible' );
			$fields = jet_smart_filters()->utils->add_control_condition( $fields, '_query_var', '_cct_notice!', 'is_visible' );

			return $fields;
		}
	}
}
