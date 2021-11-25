<?php
/**
 * Provider base class
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'Jet_Smart_Filters_Filter_Base' ) ) {

	/**
	 * Define Jet_Smart_Filters_Filter_Base class
	 */
	abstract class Jet_Smart_Filters_Filter_Base {

		/**
		 * Get filter name
		 *
		 * @return string
		 */
		abstract public function get_name();

		/**
		 * Get filter ID
		 *
		 * @return string
		 */
		abstract public function get_id();

		/**
		 * Get filter JS files
		 *
		 * @return string
		 */
		abstract public function get_scripts();

		/**
		 * Return arguments
		 * @return [type] [description]
		 */
		public function get_args() {
			return array();
		}

		/**
		 * Get filtered provider content
		 *
		 * @return string
		 */
		public function get_template( $args = array() ) {

			if ( isset( $args['dropdown_enabled'] ) && $args['dropdown_enabled'] ) {
				return jet_smart_filters()->get_template( 'common/filter-items-dropdown.php' );
			} else {
				return jet_smart_filters()->get_template( 'filters/' . $this->get_id() . '.php' );
			}

		}

		/**
		 * Get filter widget file
		 *
		 * @return string
		 */
		public function widget() {
			return jet_smart_filters()->plugin_path( 'includes/widgets/' . $this->get_id() . '.php' );
		}

	}

}
