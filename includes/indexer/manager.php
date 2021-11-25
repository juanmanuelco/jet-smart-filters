<?php
/**
 * Class description
 *
 * @package   package_name
 * @author    Cherry Team
 * @license   GPL-2.0+
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die();
}

// If class `Jet_Smart_Filters` doesn't exists yet.
if ( ! class_exists( 'Jet_Smart_Filters_Indexer_Manager' ) ) {

	/**
	 * Sets up and initializes the plugin.
	 */
	class Jet_Smart_Filters_Indexer_Manager {

		public $data               = null;
		public $controls           = null;
		public $table_name         = null;
		public $indexed_post_types = array();

		/**
		 * Sets up needed actions/filters for the plugin to initialize.
		 *
		 * @since  1.0.0
		 * @access public
		 * @return void
		 */
		public function __construct() {

			if ( filter_var( jet_smart_filters()->settings->get( 'use_indexed_filters' ), FILTER_VALIDATE_BOOLEAN ) ) {
				require jet_smart_filters()->plugin_path( 'includes/db.php' );
				require jet_smart_filters()->plugin_path( 'includes/indexer/data.php' );
				require jet_smart_filters()->plugin_path( 'includes/indexer/controls.php' );

				$this->data       = new Jet_Smart_Filters_Indexer_Data();
				$this->controls   = new Jet_Smart_Filters_Indexer_Controls();
				$this->table_name = Jet_Smart_Filters_DB::get_table_full_name( 'indexer' );

				foreach ( jet_smart_filters()->settings->get( 'avaliable_post_types' ) as $post_type => $enabled ) {
					if ( filter_var( $enabled, FILTER_VALIDATE_BOOLEAN ) ) {
						array_push( $this->indexed_post_types, $post_type );
					}
				}

				add_action( 'restrict_manage_posts', array( $this, 'add_index_filters_button' ), 10, 2 );
				add_action( 'wp_ajax_jet_smart_filters_admin_indexer', array( $this, 'indexing_filters' ) );

				if ( filter_var( jet_smart_filters()->settings->get( 'use_auto_indexing' ), FILTER_VALIDATE_BOOLEAN ) ) {
					add_action( 'wp_after_insert_post', array( $this, 'post_updated' ), 10, 2 );
					add_action( 'user_register', array( $this, 'user_updated' ), 10 );
					add_action( 'profile_update', array( $this, 'user_updated' ), 10 );
				}
			}

		}

		public function add_filter( $args ) {

			if ( is_null( $this->data ) ) {
				return;
			}

			$provider_key = $args['content_provider'] . '/' . ( ! empty( $args['query_id'] ) ? $args['query_id'] : 'default' );
			$filter_id    = $args['filter_id'];

			$this->data->add_indexing_data_from_filter( $provider_key, $filter_id );

		}

		/**
		 * Reindex filters data
		 */
		public function indexing_filters() {

			Jet_Smart_Filters_DB::clear_table( 'indexer' );

			global $wpdb;
			$indexed_data = array();
			$filters_data = $this->filters_data();

			if ( ! empty( $filters_data['tax_query'] ) ) {
				$taxanomies_parent_terms = $this->get_taxanomies_parent_terms( $filters_data['tax_query'] );
				$taxanomies_data         = $this->get_taxanomies_data(
					$filters_data['tax_query'],
					array_map( function ( $term ) { return $term['term_id']; }, $taxanomies_parent_terms )
				);

				foreach ( $taxanomies_parent_terms as $term ) {
					$parent_term_posts = $this->get_taxanomies_parent_term_posts( $term['taxonomy'], $term['term_id'] );

					foreach ( $parent_term_posts as $post_id ) {
						array_push( $taxanomies_data, array(
							'item_id'   => $post_id,
							'item_key' => $term['taxonomy'],
							'item_value'  => $term['term_id']
						) );
					}
				}

				foreach ( $taxanomies_data as $tax_row ) {
					$tax_row['type']       = 'post';
					$tax_row['item_query'] = 'tax_query';
					$indexed_data[] = $tax_row;
				}
			}

			if ( ! empty( $filters_data['meta_query'] ) ) {
				$post_meta_data = $this->get_post_meta_data( $filters_data['meta_query'] );
				$user_meta_data = $this->get_user_meta_data( $filters_data['meta_query'] );

				$meta_data = array_merge( $post_meta_data, $user_meta_data );

				foreach ( $meta_data as $meta_row ) {
					$meta_row['item_query'] = 'meta_query';

					if ( is_serialized( $meta_row['item_value'] ) ) {
						$unserialized_data = @unserialize( $meta_row['item_value'] );
						foreach ( $unserialized_data as $key => $value ) {
							if ( $value === 'false' ) {
								continue;
							}

							if ( $value === 'true' ) {
								$meta_row['item_value'] = $key;
							} else {
								$meta_row['item_value'] = $value;
							}

							$indexed_data[] = $meta_row;
						}
					} else {
						$indexed_data[] = $meta_row;
					}
				}

			}

			foreach ( array_chunk( $indexed_data, 1000 ) as $data ) {
				$sql_insert = "INSERT INTO $this->table_name (type, item_id, item_query, item_key, item_value) VALUES ";
				foreach ( $data as $item ) {
					$sql_insert .= "('" . $item['type'] . "','" . $item['item_id'] . "','" . $item['item_query'] . "','" . $item['item_key'] . "','" . $item['item_value'] . "'),";
				}
				$sql_insert = rtrim($sql_insert, ',');

				$wpdb->query( $sql_insert );
			}

		}

		public function post_updated( $post_ID, $post ) {

			if ( $post->post_status === 'auto-draft' ) {
				return;
			}

			if ( in_array( $post->post_type, $this->indexed_post_types ) ) {

				$this->remove_single_data( $post_ID, 'post' );

				if ( $post->post_status !== 'publish' ) {
					return;
				}

				$this->add_single_data( $post_ID, 'post' );

			} else if ( $post->post_type === 'jet-smart-filters' ) {

				$this->indexing_filters();

			}

		}

		public function user_updated( $user_ID ) {

			if ( ! in_array( 'users', $this->indexed_post_types ) ) {
				return;
			}

			$this->remove_single_data( $user_ID, 'user' );
			$this->add_single_data( $user_ID, 'user' );

		}

		/**
		 * Add single data to indexing table
		 * @param Number $user_ID
		 * 
		 * @return void
		 */
		public function add_single_data( $id, $type = 'post' ) {

			global $wpdb;
			$new_rows     = array();
			$filters_data = $this->filters_data();

			if ( ! empty( $filters_data['tax_query'] ) && $type === 'post' ) {
				foreach ( wp_get_post_terms( $id, $filters_data['tax_query'] ) as $term ) {
					array_push( $new_rows, array(
						'type'       => 'post',
						'item_id'    => $id,
						'item_query' => 'tax_query',
						'item_key'   => $term->taxonomy,
						'item_value' => $term->term_id
					) );
				}
			}

			$item_meta_data = array();
			if ( $type === 'post' ) {
				$item_meta_data = get_post_meta( $id );
			}
			if ( $type === 'user' ) {
				$item_meta_data = get_user_meta( $id );
			}

			if ( ! empty( $item_meta_data ) ) {
				foreach ( $filters_data['meta_query'] as $meta_type => $filter_meta ) {
					if ( empty( $filter_meta ) ) {
						continue;
					}

					foreach ( $filter_meta as $meta_key => $meta_value ) {
						if ( empty( $item_meta_data[$meta_key][0] ) ) {
							continue;
						}

						$item_meta = $item_meta_data[$meta_key][0];

						if ( in_array( $meta_type, ['serialized', 'normal'] ) ) {
							foreach ( $meta_value as $value ) {
								if ( $meta_type === 'serialized' && ! preg_match( '/[\'"]?;s:4:"true"|:[\'"]?' . $value . '[\'"]?;[^s]/', $item_meta ) ) {
									continue;
								}

								if ( $meta_type === 'normal' && $item_meta != $value ) {
									continue;
								}

								array_push( $new_rows, array(
									'type'       => $type,
									'item_id'    => $id,
									'item_query' => 'meta_query',
									'item_key'   => $meta_key,
									'item_value' => $value
								) );
							}
						}

						if ( $meta_type === 'range' ) {
							if ( $meta_value['min'] <= $item_meta && $meta_value['max'] >= $item_meta ) {
								array_push( $new_rows, array(
									'type'       => $type,
									'item_id'    => $id,
									'item_query' => 'meta_query',
									'item_key'   => $meta_key,
									'item_value' => $item_meta
								) );
							}
						}
					}
				}
			}

			foreach ( array_unique( $new_rows, SORT_REGULAR ) as $new_row ) {
				$wpdb->insert( $this->table_name, $new_row );
			}

		}
		
		/**
		 * Remove single data from indexing table
		 * @param Number $user_ID
		 * 
		 * @return void
		 */
		public function remove_single_data( $id, $type = 'post' ) {

			global $wpdb;

			$wpdb->delete( $this->table_name, array(
				'type'    => $type,
				'item_id' => $id
			) );

		}

		/**
		 * Get filters taxanomies data
		 * @param Array $taxanomies
		 * 
		 * @return Array
		 */
		function get_taxanomies_data( $taxanomies, $excluded_terms = false ) {

			global $wpdb;
			$result = [];

			$excluded_terms_and = '';
			if ( $excluded_terms ) {
				$excluded_terms_and = "AND $wpdb->term_taxonomy.term_id NOT IN ('" . implode( "','", $excluded_terms ) . "')";
			}
			
			$sql = "
			SELECT $wpdb->posts.ID as item_id, $wpdb->term_taxonomy.taxonomy as item_key, $wpdb->term_taxonomy.term_id as item_value
				FROM $wpdb->posts, $wpdb->term_relationships, $wpdb->term_taxonomy
				WHERE $wpdb->posts.post_type IN ('" . implode( "','", $this->indexed_post_types ) . "')
				AND $wpdb->posts.ID = $wpdb->term_relationships.object_id
				AND $wpdb->term_relationships.term_taxonomy_id = $wpdb->term_taxonomy.term_taxonomy_id
				$excluded_terms_and
				AND $wpdb->term_taxonomy.taxonomy IN ('" . implode( "','", $taxanomies ) . "')
				ORDER BY $wpdb->posts.ID ASC";
			$result = $wpdb->get_results( $sql, ARRAY_A );

			return $result;

		}

		public function get_taxanomies_parent_terms( $taxanomies ) {

			global $wpdb;
			$sql = "
			SELECT MAX(taxonomy) as taxonomy, parent as term_id
				FROM $wpdb->posts, $wpdb->term_relationships, $wpdb->term_taxonomy
				WHERE $wpdb->posts.post_type IN ('" . implode( "','", $this->indexed_post_types ) . "')
				AND $wpdb->posts.ID = $wpdb->term_relationships.object_id
				AND $wpdb->term_relationships.term_taxonomy_id = $wpdb->term_taxonomy.term_taxonomy_id
				AND $wpdb->term_taxonomy.parent > 0
				AND $wpdb->term_taxonomy.taxonomy IN ('" . implode( "','", $taxanomies ) . "')
				GROUP BY parent
				ORDER BY parent ASC";
			$result = $wpdb->get_results( $sql, ARRAY_A );

			return $result;

		}

		public function get_taxanomies_parent_term_posts( $tax, $term_id ) {

			$term_posts_count_args = array(
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'tax_query' => array(
					array(
						'taxonomy' => $tax,
						'terms'    => $term_id,
					),
				),
			);

			$query = new WP_Query( $term_posts_count_args );

			return $query->posts;

		}

		/**
		 * Get filters meta data
		 * @param Array $metadata
		 * 
		 * @return Array
		 */
		function get_post_meta_data( $metadata ) {

			$result = [];

			$metadata_sql_and = $this->get_sql_and_from_metadata( $metadata );

			if ( ! $metadata_sql_and ) {
				return $result;
			}

			global $wpdb;
			$sql = "
			SELECT post_id as item_id, meta_key as item_key, meta_value as item_value
				FROM $wpdb->posts, $wpdb->postmeta
				WHERE $wpdb->posts.post_type IN ('" . implode( "','", $this->indexed_post_types ) . "')
				AND $wpdb->posts.ID = $wpdb->postmeta.post_id
				AND meta_value != '' AND meta_value IS NOT NULL
				AND ( $metadata_sql_and )
				ORDER BY post_id ASC";
			$result = $wpdb->get_results( $sql, ARRAY_A );

			for ( $i = 0; $i < count( $result ); $i++ ) {
				$result[$i]['type'] = 'post';
			}

			return $result;

		}

		/**
		 * Get filters user meta data
		 * @param Array $metadata
		 * 
		 * @return Array
		 */
		function get_user_meta_data( $metadata ) {

			$result = array();

			if ( ! in_array( 'users', $this->indexed_post_types ) ) {
				return $result;
			}

			$metadata_sql_and = $this->get_sql_and_from_metadata( $metadata );

			if ( ! $metadata_sql_and ) {
				return $result;
			}

			global $wpdb;
			$sql = "
			SELECT user_id as item_id, meta_key as item_key, meta_value as item_value
				FROM $wpdb->usermeta
				WHERE meta_value != '' AND meta_value IS NOT NULL
				AND ( $metadata_sql_and )
				ORDER BY user_id ASC";
			$result = $wpdb->get_results( $sql, ARRAY_A );

			for ( $i = 0; $i < count( $result ); $i++ ) {
				$result[$i]['type'] = 'user';
			}

			return $result;

		}

		/**
		 * Get AND MySQL query from meta data
		 * @param Array $metadata
		 * 
		 * @return String
		 */
		function get_sql_and_from_metadata( $metadata ) {

			$metadata_sql_and = '';
			foreach ( ['normal', 'serialized', 'range'] as $data_type ) {
				if ( empty( $metadata[$data_type] ) ) {
					continue;
				}

				foreach ( $metadata[$data_type] as $query_var => $data ) {
					$meta_keys = explode( ',', $query_var );

					foreach ($meta_keys as $meta_key) {
						$meta_key = trim( $meta_key );

						if ( $metadata_sql_and ) {
							$metadata_sql_and .= ' OR ';
						}
	
						if ( 'serialized' === $data_type ) {
							$metadata_sql_and .= "( meta_key = '$meta_key' AND meta_value REGEXP '[\'\"]?;s:4:\"true\"|\:[\'\"]?(" . implode( '|', $data ) . ")[\'\"]?;[^s]' )";
						}
	
						if ( 'normal' === $data_type ) {
							$metadata_sql_and .= "( meta_key = '$meta_key' AND meta_value IN ('" . implode( "','", $data ) . "') )";
						}
	
						if ( 'range' === $data_type ) {
							$metadata_sql_and .= "( meta_key = '$meta_key' AND meta_value >= " . $data['min'] . " AND meta_value <= " . $data['max'] . " )";
						}
					}
				}
			}

			return $metadata_sql_and;

		}

		/**
		 * Create table
		 * 
		 * @return Array
		 */
		public function filters_data() {

			$filters_data = [
				'tax_query'  => [],
				'meta_query' => [
					'serialized' => [],
					'normal'     => []
				],
			];

			$meta_keys = array(
				'_filter_type',
				'_data_source',
				'_source_taxonomy',
				'_source_manual_input',
				'_source_manual_input_range',
				'_source_color_image_input',
				'_ih_source_map',
				'_is_custom_checkbox',
				'_is_hierarchical',
				'_query_var',
				'_source_custom_field',
				'_source_get_from_field_data',
				'_custom_field_source_plugin'
			);

			global $wpdb;
			$sql = "
			SELECT $wpdb->posts.ID, $wpdb->posts.post_title, GROUP_CONCAT($wpdb->postmeta.meta_key SEPARATOR '<-nv->') as meta_key, GROUP_CONCAT($wpdb->postmeta.meta_value SEPARATOR '<-nv->') as meta_value
				FROM $wpdb->posts, $wpdb->postmeta
				WHERE $wpdb->posts.ID = $wpdb->postmeta.post_ID
				AND $wpdb->postmeta.meta_key IN ('" . implode( "','", $meta_keys ) . "')
				AND $wpdb->posts.post_type='jet-smart-filters'
				AND $wpdb->posts.post_status = 'publish'
				GROUP BY $wpdb->posts.ID
				ORDER BY $wpdb->posts.ID ASC";
			$result = $wpdb->get_results( $sql, ARRAY_A );

			// parse result
			foreach ( $result as $row ) {
				$filter_id = $row['ID'];
				$data = array_combine( explode( '<-nv->', $row['meta_key'] ), explode( '<-nv->', $row['meta_value'] ) );

				if ( ! in_array( $data['_filter_type'], array( 'select', 'checkboxes', 'check-range', 'radio', 'color-image' ) ) ) {
					continue;
				}

				if ( $data['_filter_type'] === 'check-range' ) {
					$query_var = $data['_query_var'];
					$data_type = 'range';

					if ( ! $query_var ) {
						continue;
					}

					foreach ( unserialize( $data['_source_manual_input_range'] ) as $meta_data ) {
						$min = ! empty( $meta_data['min'] ) ? intval( $meta_data['min'] ) : 0;
						$max = ! empty( $meta_data['max'] ) || $meta_data['max'] === '0' ? intval( $meta_data['max'] ) : 100;

						if ( ! isset( $filters_data['meta_query'][$data_type][$query_var] ) ) {
							$filters_data['meta_query'][$data_type][$query_var] = array();
						}

						if ( ! isset( $filters_data['meta_query'][$data_type][$query_var]['min'] ) || $filters_data['meta_query'][$data_type][$query_var]['min'] > $min ) {
							$filters_data['meta_query'][$data_type][$query_var]['min'] = $min;
						}

						if ( ! isset( $filters_data['meta_query'][$data_type][$query_var]['max'] ) || $filters_data['meta_query'][$data_type][$query_var]['max'] < $max ) {
							$filters_data['meta_query'][$data_type][$query_var]['max'] = $max;
						}
					}

					continue;
				}

				if ( $data['_filter_type'] === 'select' && filter_var( $data['_is_hierarchical'], FILTER_VALIDATE_BOOLEAN ) ) {
					foreach ( unserialize( $data['_ih_source_map'] ) as $hierarchical_data ) {
						array_push( $filters_data['tax_query'], $hierarchical_data['tax'] );
					}
				}

				switch ( $data['_data_source'] ) {
					case 'taxonomies':
						array_push( $filters_data['tax_query'], $data['_source_taxonomy'] );

						break;

					case 'manual_input':
						$query_var = $data['_query_var'];

						if ( ! $query_var ) {
							break;
						}

						$is_serialized_data = filter_var( $data['_is_custom_checkbox'], FILTER_VALIDATE_BOOLEAN );
						$data_type          = $is_serialized_data ? 'serialized' : 'normal';

						if ( ! isset( $filters_data['meta_query'][$data_type][$query_var] ) ) {
							$filters_data['meta_query'][$data_type][$query_var] = array();
						}

						
						if ( $data['_filter_type'] === 'color-image' ) {
							$input_data = $data['_source_color_image_input'];
						} else {
							$input_data = $data['_source_manual_input'];
						}

						if ( ! $input_data ) {
							break;
						}

						foreach ( unserialize( $input_data ) as $input_item ) {
							array_push( $filters_data['meta_query'][$data_type][$query_var], $input_item['value'] );
						}

						break;
					
					case 'custom_fields':
						$query_var           = $data['_query_var'];
						$get_from_field_data = isset( $data['_source_get_from_field_data'] ) ? filter_var( $data['_source_get_from_field_data'], FILTER_VALIDATE_BOOLEAN ) : false;

						if ( ! $query_var || ! $get_from_field_data ) {
							break;
						}

						$is_serialized_data   = filter_var( $data['_is_custom_checkbox'], FILTER_VALIDATE_BOOLEAN );
						$data_type            = $is_serialized_data ? 'serialized' : 'normal';
						$custom_field         =  $data['_source_custom_field'];
						$custom_field_source  =  $data['_custom_field_source_plugin'];
						$custom_field_options = jet_smart_filters()->data->get_choices_from_field_data( array(
							'field_key' => $custom_field,
							'source'    => $custom_field_source,
						) );

						if ( ! isset( $filters_data['meta_query'][$data_type][$query_var] ) ) {
							$filters_data['meta_query'][$data_type][$query_var] = array();
						}

						foreach ( $custom_field_options as $meta_key => $meta_data ) {
							array_push( $filters_data['meta_query'][$data_type][$query_var], $meta_key );
						}

						break;

					default:
						$query_var   = $data['_query_var'];
						$custom_args = apply_filters( 'jet-smart-filters/indexer/custom-args', array(), $filter_id );
						$options     = ! empty( $custom_args['options'] ) ? $custom_args['options'] : false;

						if ( ! $query_var || ! $options ) {
							break;
						}

						$is_serialized_data = filter_var( $data['_is_custom_checkbox'], FILTER_VALIDATE_BOOLEAN );
						$data_type          = $is_serialized_data ? 'serialized' : 'normal';
						$query_type         = isset( $custom_args['query_type'] ) ? $custom_args['query_type'] : 'meta_query';

						if ( ! isset( $filters_data[$query_type][$data_type][$query_var] ) ) {
							$filters_data[$query_type][$data_type][$query_var] = array();
						}

						$filters_data[$query_type][$data_type][$query_var] = array_merge(
							$filters_data[$query_type][$data_type][$query_var],
							$options
						);

						break;
				}
			}

			// remove duplicates
			$filters_data['tax_query'] = array_unique( $filters_data['tax_query'] );
			foreach ( ['normal', 'serialized'] as $data_type ) {
				foreach ( $filters_data['meta_query'][$data_type] as $query_var => $meta_data ) {
					$filters_data['meta_query'][$data_type][$query_var] = array_unique( $meta_data );
				}
			}

			return $filters_data;

		}

		/**
		 * Add index filter button in manage post panel
		 *
		 * @param $post_type
		 * @param $which
		 */
		public function add_index_filters_button( $post_type, $which ) {

			if ( 'jet-smart-filters' !== $post_type ) {
				return;
			}

			printf( '<button type="button" id="jet-smart-filters-indexer-button" data-default-text="%1$s" data-loading-text="%2$s">%1$s</button>',
				esc_html__( 'Index Filters', 'jet-smart-filters' ),
				esc_html__( 'Indexing...', 'jet-smart-filters' )
			);

		}

	}
}
