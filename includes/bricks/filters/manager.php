<?php

namespace Jet_Smart_Filters\Bricks_Views\Filters;

define( 'BRICKS_QUERY_LOOP_PROVIDER_ID', 'bricks-query-loop' );
define( 'BRICKS_QUERY_LOOP_PROVIDER_NAME', 'Bricks query loop' );

class Manager {

	public function __construct() {
		/**
		 * Register custom provider
		 */

		add_action( 'init', [ $this, 'add_control_to_elements' ], 40 );
		add_action( 'jet-smart-filters/providers/register', [ $this, 'register_provider_for_filters' ] );
		add_filter( 'jet-smart-filters/filters/localized-data', [ $this, 'add_script' ] );
		add_filter( 'jet-engine/query-builder/filters/allowed-providers', [ $this, 'add_provider_to_query_builder' ] );

	}

	public function register_provider_for_filters( $providers_manager ) {

		$providers_manager->register_provider(
			'\Jet_Smart_Filters\Bricks_Views\Filters\Provider', // Custom provider class name
			jet_smart_filters()->plugin_path( 'includes/bricks/filters/provider.php' ) // Path to file where this class defined
		);
	}

	public function add_control_to_elements() {

		// Only container, block and div element have query controls
		$elements = [ 'container', 'block', 'div' ];

		foreach ( $elements as $name ) {
			add_filter( "bricks/elements/{$name}/controls", [ $this, 'add_jet_smart_filters_controls' ], 40 );
		}

	}

	public function add_jet_smart_filters_controls( $controls ) {

		$jet_smart_filters_control['jsfb_is_filterable'] = [
			'tab'         => 'content',
			'label'       => esc_html__( 'Is filterable', 'jet-smart-filters' ),
			'type'        => 'checkbox',
			'required'    => [
				[ 'hasLoop', '=', true ],
			],
			'rerender'    => true,
			'description' => esc_html__( 'Please check this option if you will use with JetSmartFilters.', 'jet-smart-filters' ),
		];

		$jet_smart_filters_control['jsfb_query_id'] = [
			'tab'            => 'content',
			'label'          => esc_html__( 'Query ID for filters', 'jet-smart-filters' ),
			'type'           => 'text',
			'placeholder'    => esc_html__( 'Please enter query id.', 'jet-smart-filters' ),
			'hasDynamicData' => false,
			'required'       => [
				[ 'hasLoop', '=', true ],
				[ 'jsfb_is_filterable', '=', true ]
			],
			'rerender'       => true,
		];

		// Below 2 lines is just some php array functions to force my new control located after the query control
		$query_key_index = absint( array_search( 'query', array_keys( $controls ) ) );
		$new_controls    = array_slice( $controls, 0, $query_key_index + 1, true ) + $jet_smart_filters_control + array_slice( $controls, $query_key_index + 1, null, true );

		return $new_controls;
	}

	public function add_provider_to_query_builder( $providers ) {
		$providers[] = BRICKS_QUERY_LOOP_PROVIDER_ID;

		return $providers;
	}

	public function add_script( $data ) {

		wp_add_inline_script( 'jet-smart-filters', '

				const filtersStack = {};

				document.addEventListener( "jet-smart-filters/inited", () => {
					window.JetSmartFilters.events.subscribe( "ajaxFilters/start-loading", ( provider, queryID ) => {
						if ( "bricks-query-loop" === provider && filtersStack[ queryID ] ) {
							delete filtersStack[ queryID ];
						}
					} );
				} );

				jQuery( document ).on( "jet-filter-data-updated", ".jsfb-filterable", ( event, response, filter ) => {

					if ( event.target.classList.contains( "jsfb-query--" + response.query_id ) ) {
						if ( ! filtersStack[ response.query_id ] ) {
							
							filtersStack[ response.query_id ] = true;
							
							var newContent = response.rendered_content;
							var replaced = false;

							if ( ! response.loadMore ) {
								jQuery( ".jsfb-filterable.jsfb-query--" + response.query_id ).replaceWith( () => {

									if ( replaced ) {
										newContent = "";
									} else {
										replaced = true;
									}

									return newContent;

								} );
							}
							
							const {id: styleElementId, style: styleElement} = response.styles;
							const previousStyleElement = document.getElementById(styleElementId);
							if (previousStyleElement) {
							  document.body.removeChild(previousStyleElement);
							}
							document.body.insertAdjacentHTML("beforeend", styleElement);
							filter.$provider.last().after( newContent );
							filter.$provider = jQuery( ".jsfb-filterable.jsfb-query--" + response.query_id );
							window.JetPlugins && window.JetPlugins.init( filter.$provider.closest( "*" ) );
							
							// Re-init Bricks scripts after filtering
							const bricksScripts = {
								".bricks-lightbox": bricksPhotoswipe,
								".brxe-accordion, .brxe-accordion-nested": bricksAccordion,
								".brxe-animated-typing": bricksAnimatedTyping,
								".brxe-audio": bricksAudio,
								".brxe-countdown": bricksCountdown,
								".brxe-counter": bricksCounter,
								".brxe-video": bricksVideo,
								".bricks-lazy-hidden": bricksLazyLoad,
								".brx-animated": bricksAnimation,
								".brxe-pie-chart": bricksPieChart,
								".brxe-progress-bar .bar span": bricksProgressBar,
								".brxe-form": bricksForm,
								".brx-query-trail": bricksInitQueryLoopInstances,
								"[data-interactions]": bricksInteractions,
								".brxe-alert svg": bricksAlertDismiss,
								".brxe-tabs, .brxe-tabs-nested": bricksTabs,
								".bricks-video-overlay, .bricks-video-overlay-icon, .bricks-video-preview-image": bricksVideoOverlayClickDetector,
								".bricks-background-video-wrapper": bricksBackgroundVideoInit,
								".brxe-toggle": bricksToggle,
								".brxe-offcanvas": bricksOffcanvas,
							}
														
							const contentWrapper = filter.$provider[0].parentNode; 
							
							for (key in bricksScripts) {
								const widget = contentWrapper.querySelector(key);
								
								if (widget && typeof bricksScripts[key] === "function") {
//							        console.log("Викликаємо функцію за ключем:", key);
							        bricksScripts[key](); // Викликаємо функцію
							    }
							}
							
							const interactions = document.querySelectorAll("[data-interactions]");
							
							if (interactions.length) {
								interactions.forEach(el => {
									const interactionAttrs = JSON.parse(el.dataset.interactions);
									const {loadMoreQuery} = interactionAttrs[0];
										
									if (response.element_id === loadMoreQuery) {
										const {max_num_pages: maxPages, page} = response.pagination;
									
										if (page >= maxPages) {
											el.style.display = "none";
										} else {
											el.style.display = "";
										}
									}
								});	
							}
							
							bricksInitQueryLoopInstances();

						}
					}
				} );
				
				/*window.JetSmartFilters.events.subscribe( "ajaxFilters/updated", ( provider, queryId ) => {

					if ( "bricks-query-loop" !== provider ) {
						return;
					}
		
					let filterGroup = window.JetSmartFilters.filterGroups[ provider + "/" + queryId ];
					
					console.log(filterGroup);
		
					if ( ! filterGroup || ! filterGroup.$provider ) {
						return;
					}
		
				} );*/

			' );

		return $data;

	}
}