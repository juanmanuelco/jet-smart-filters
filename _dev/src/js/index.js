import filtersInitializer from './filters-initializer';

// Includes
import elementorEditorMode from 'includes/elementor-editor-mode';
import eproCompat from 'includes/epro-compat';

"use strict";

//JetSmartFilters
window.JetSmartFilters = filtersInitializer;

// Init filters
$(document).ready(function () {
	window.JetSmartFilters.initializeFilters();
});

// if elementor
$(window).on('elementor/frontend/init', function () {
	// initialize elementor PRO widgets post rendered processing
	eproCompat.init();

	// edit mode filters init
	if (elementorFrontend.isEditMode())
		elementorEditorMode.initFilters();
});

// Reinit filters events
$(window)
	.on('jet-popup/render-content/ajax/success', function (evt, popup) {
		window.JetSmartFilters.initializeFiltersInContainer($('#jet-popup-' + popup.popup_id));
	})
	.on('jet-tabs/ajax-load-template/after', function (evt, props) {
		window.JetSmartFilters.initializeFiltersInContainer(props.contentHolder);
	})
	.on('jet-blocks/ajax-load-template/after', function (evt, props) {
		window.JetSmartFilters.initializeFiltersInContainer(props.contentHolder);
	});