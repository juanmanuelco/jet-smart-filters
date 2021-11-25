// Main Class
import FilterGroup from './FilterGroup';

// Filters Сlasses
import filters from 'filters';

// Includes
import filtersUI from 'includes/filters-ui';
import eventBus from 'includes/event-bus';
import preloader from 'includes/preloader';
import {
	isNotEmpty
} from 'includes/utility';

const filtersInitializer = {
	filtersList: {
		CheckBoxes: 'jet-smart-filters-checkboxes',
		CheckRange: 'jet-smart-filters-check-range',
		Select: 'jet-smart-filters-select',
		SelectHierarchical: 'jet-smart-filters-hierarchy',
		Range: 'jet-smart-filters-range',
		DateRange: 'jet-smart-filters-date-range',
		DatePeriod: 'jet-smart-filters-date-period',
		Radio: 'jet-smart-filters-radio',
		Rating: 'jet-smart-filters-rating',
		Visual: 'jet-smart-filters-color-image',
		Alphabet: 'jet-smart-filters-alphabet',
		Search: 'jet-smart-filters-search',
		Sorting: 'jet-smart-filters-sorting',
		ButtonApply: 'jet-smart-filters-apply-button',
		ButtonRemove: 'jet-smart-filters-remove-filters',
		Pagination: 'jet-smart-filters-pagination',
		ActiveFilters: 'jet-smart-filters-active',
		ActiveTags: 'jet-smart-filters-active-tags'
	},
	filters,
	filterGroups: {},
	initializeFilters: init,
	initializeFiltersInContainer,
	findFilters,
	filtersUI,
	events: eventBus
}

const filtersList = filtersInitializer.filtersList,
	additionalFiltersExceptions = ['ActiveFilters', 'ActiveTags', 'ButtonRemove'];

let filterGroups = filtersInitializer.filterGroups;

function init() {
	const prevQueries = {};

	// before clearing
	for (const filterGroupKey in filterGroups) {
		const query = filterGroups[filterGroupKey].currentQuery;

		if (isNotEmpty(query))
			prevQueries[filterGroupKey] = query;
	}

	//clear previous filters
	eventBus.channels = {};
	filterGroups = filtersInitializer.filterGroups = {};

	// before initialization
	preloader.init();

	// initialization
	// search and group filters
	const $filters = findFilters();

	$filters.each(index => {
		const $filter = $filters.eq(index);

		let filterName = null,
			filter = null;

		for (const key in filtersList) {
			if ($filter.hasClass(filtersList[key]))
				filterName = key;
		}

		if (!filterName)
			return;

		// Main Provider
		filter = new filters[filterName]($filter);

		if (filter.isHierarchy) {
			filter.filters.forEach(hierarchyFilter => {
				pushFilterToGroup(hierarchyFilter);
			});
		} else {
			pushFilterToGroup(filter);
		}

		// Additional Filters
		const additionalFilters = $filter.data('additional-providers') || $filter.find('[data-additional-providers]').data('additional-providers');

		if (!additionalFilters || additionalFiltersExceptions.includes(filterName))
			return;

		additionalFilters.forEach(additionalFilter => {
			const additionalFilterData = additionalFilter.split('/', 2),
				additionalProvider = additionalFilterData[0],
				additionalQueryId = additionalFilterData[1] || filter.queryId;

			if (filter.isHierarchy) {
				filter.filters.forEach(hierarchyFilter => {
					pushFilterToGroup(createAdditionalFilter(additionalProvider, additionalQueryId, hierarchyFilter));
				});
			} else {
				pushFilterToGroup(createAdditionalFilter(additionalProvider, additionalQueryId, filter));
			}
		});
	})

	// group filter initialization
	for (const filterGroupKey in filterGroups) {
		if (filterGroups.hasOwnProperty(filterGroupKey)) {
			const splittedKeys = filterGroupKey.split('/');

			filterGroups[filterGroupKey] = new FilterGroup(splittedKeys[0], splittedKeys[1], filterGroups[filterGroupKey], prevQueries[filterGroupKey]);
		}
	}
}

function findFilters(container = $('html')) {
	return $('.' + Object.values(filtersList).join(', .'), container);
}

function createAdditionalFilter(additionalProvider, additionalQueryId, filter) {
	return {
		isAdditional: true,
		name: filter.name,
		provider: additionalProvider,
		queryId: additionalQueryId,
		filterId: filter.filterId,
		queryKey: filter.queryKey,
		data: filter.data,
		reset: function () {
			this.data = false;
		}
	};
}

function pushFilterToGroup(filter) {
	if (!filter || !filter.provider)
		return

	const provider = filter.provider,
		queryId = filter.queryId;

	if (!filterGroups[provider + '/' + queryId]) {
		filterGroups[provider + '/' + queryId] = [];
	}

	filterGroups[provider + '/' + queryId].push(filter);
}

function initializeFiltersInContainer(container) {
	const filters = findFilters(container);

	if (filters.length)
		init();
}

export default filtersInitializer;