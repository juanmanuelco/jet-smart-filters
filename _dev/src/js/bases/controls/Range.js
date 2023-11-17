import Filter from 'bases/Filter';

export default class RangeControl extends Filter {

	constructor($container, $filter, $sliderInputMin, $sliderInputMax, $sliderValuesMin, $sliderValuesMax, $sliderTrackRange, $rangeInputMin, $rangeInputMax, prefix, suffix) {
		super($filter, $container);

		this.$sliderInputMin = $sliderInputMin || this.$filter.find('.jet-range__slider__input--min');
		this.$sliderInputMax = $sliderInputMax || this.$filter.find('.jet-range__slider__input--max');
		this.$sliderValuesMin = $sliderValuesMin || this.$filter.find('.jet-range__values-min');
		this.$sliderValuesMax = $sliderValuesMax || this.$filter.find('.jet-range__values-max');
		this.$sliderTrackRange = $sliderTrackRange || this.$filter.find('.jet-range__slider__track__range');
		this.$rangeInputMin = $rangeInputMin || this.$filter.find('.jet-range__inputs__min');
		this.$rangeInputMax = $rangeInputMax || this.$filter.find('.jet-range__inputs__max');
		this.$rangeInputs = this.$rangeInputMin.add(this.$rangeInputMax);
		this.$inputs = this.$sliderInputMin.add(this.$sliderInputMax).add(this.$rangeInputMin).add(this.$rangeInputMax);
		this.minConstraint = parseFloat(this.$sliderInputMin.attr('min'));
		this.maxConstraint = parseFloat(this.$sliderInputMax.attr('max'));
		this.step = parseFloat(this.$sliderInputMax.attr('step'));
		this.minVal = parseFloat(this.$sliderInputMin.val());
		this.maxVal = parseFloat(this.$sliderInputMax.val());
		this.prefix = prefix || this.$filter.find('.jet-range__values-prefix').first().text() || false;
		this.suffix = suffix || this.$filter.find('.jet-range__values-suffix').first().text() || false;
		this.format = this.$filter.data('format') || {
			'thousands_sep': '',
			'decimal_sep': '',
			'decimal_num': 0,
		};
		this.format.thousands_sep = this.format.thousands_sep.replace(/&nbsp;/g, ' ');
		this.rangeInputsSeparators = this.$filter.data('inputs-separators');

		this.initSlider();
		this.processData();
		this.initEvent();
		this.valuesUpdated();
	}

	initSlider() {
		this.$filter.on('mousemove touchstart', this.findClosestRange.bind(this));

		this.$sliderInputMin.on('input', (event) => {
			this.minVal = parseFloat(this.$sliderInputMin.val());
			this.valuesUpdated('min');
		});
		this.$sliderInputMax.on('input', () => {
			this.maxVal = parseFloat(this.$sliderInputMax.val());
			this.valuesUpdated('max');
		});

		if (this.$rangeInputs.length)
			this.$rangeInputs.on('input keydown blur', (event) => {
				const elInput = event.target;
				const value = elInput.value;

				let inputType = '';
				if (elInput.hasAttribute('min-range')) inputType = 'min';
				if (elInput.hasAttribute('max-range')) inputType = 'max';

				if (!inputType)
					return;

				if (this.rangeInputsSeparators) {
					const oldValue = elInput.oldValue || '';
					const caretPosition = elInput.selectionEnd;

					if (value !== oldValue) {
						this.rangeInputUpdateValue(inputType, value);

						const formattedValue = elInput.value;
						const numericValue = elInput.numericValue;

						switch (inputType) {
							case 'min':
								this.minVal = this.inputNumberRangeValidation(numericValue);
								break;

							case 'max':
								this.maxVal = this.inputNumberRangeValidation(numericValue);
								break;
						}

						// set caret position
						if (formattedValue.length === elInput.selectionEnd) {
							let positionOffset = -1;

							if (formattedValue !== oldValue)
								positionOffset =
									(formattedValue.slice(0, caretPosition).split(this.format.thousands_sep).length - 1)
									-
									(oldValue.slice(0, caretPosition).split(this.format.thousands_sep).length - 1);

							if (formattedValue === oldValue)
								if ([this.format.thousands_sep, this.format.decimal_sep].includes(formattedValue.charAt(caretPosition)))
									positionOffset = 0;

							elInput.setSelectionRange(caretPosition + positionOffset, caretPosition + positionOffset);
						}
					}
				} else {
					switch (inputType) {
						case 'min':
							this.minVal = this.inputNumberRangeValidation(value);
							break;

						case 'max':
							this.maxVal = this.inputNumberRangeValidation(value);
							break;
					}
				}

				if (event.type === 'blur' || event.keyCode === 13)
					this.valuesUpdated(inputType);
			});
	}

	addFilterChangeEvent() {
		this.$inputs.on('change', () => {
			this.processData();
			this.wasСhanged();
		});
	}

	removeChangeEvent() {
		this.$filter.off();
		this.$inputs.off();
	}

	processData() {
		if (this.$rangeInputMin.length)
			this.rangeInputUpdateValue('min', this.minVal);
		if (this.$rangeInputMax.length)
			this.rangeInputUpdateValue('max', this.maxVal);

		// Prevent of adding slider defaults
		if (this.minVal == this.minConstraint && this.maxVal == this.maxConstraint) {
			this.dataValue = false;
			return;
		}

		this.dataValue = this.minVal + '_' + this.maxVal;
	}

	setData(newData) {
		this.reset();

		if (!newData)
			return;

		const data = newData.split('_');

		if (data[0]) {
			this.minVal = parseFloat(data[0]);
			this.$sliderInputMin.val(this.minVal);
		}
		if (data[1]) {
			this.maxVal = parseFloat(data[1]);
			this.$sliderInputMax.val(this.maxVal);
		}

		this.valuesUpdated();
		this.processData();
	}

	reset() {
		this.dataValue = false;
		this.minVal = this.minConstraint;
		this.maxVal = this.maxConstraint;
		this.$sliderInputMin.val(this.minVal);
		this.$sliderInputMax.val(this.maxVal);

		this.valuesUpdated();
		this.processData();
	}

	findClosestRange(event) {
		const bounds = event.target.getBoundingClientRect(),
			clientX = event.clientX || event.touches[0].clientX,
			x = clientX - bounds.left,
			width = parseFloat(this.$sliderInputMax.width()),
			minValue = parseFloat(this.$sliderInputMin.val()),
			maxValue = parseFloat(this.$sliderInputMax.val());

		const averageValue = (maxValue + minValue) / 2,
			hoverValue = this.isRTL
				? (this.minConstraint - this.maxConstraint) * (x / width) + this.maxConstraint
				: (this.maxConstraint - this.minConstraint) * (x / width) + this.minConstraint;

		if (hoverValue > averageValue) {
			this.swapInput('max');
		} else {
			this.swapInput('min');
		}
	}

	swapInput(inputType) {
		switch (inputType) {
			case 'min':
				this.$sliderInputMin.css('z-index', 21);
				this.$sliderInputMax.css('z-index', 20);

				break;

			case 'max':
				this.$sliderInputMin.css('z-index', 20);
				this.$sliderInputMax.css('z-index', 21);

				break;
		}
	}

	valuesUpdated(inputType = false) {
		switch (inputType) {
			case 'min':
				if (this.minVal > this.maxVal - this.step)
					this.minVal = this.maxVal - this.step;

				this.$sliderInputMin.val(this.minVal);
				this.rangeInputUpdateValue('min', this.minVal);

				break;

			case 'max':
				if (this.maxVal < this.minVal + this.step)
					this.maxVal = this.minVal + this.step;

				this.$sliderInputMax.val(this.maxVal);
				this.rangeInputUpdateValue('max', this.maxVal);

				break;
		}

		if (this.$sliderValuesMin.length)
			this.$sliderValuesMin.html(this.getFormattedData(this.minVal));
		if (this.$sliderValuesMax.length)
			this.$sliderValuesMax.html(this.getFormattedData(this.maxVal));

		const low = 100 * ((this.minVal - this.minConstraint) / (this.maxConstraint - this.minConstraint)),
			high = 100 * ((this.maxVal - this.minConstraint) / (this.maxConstraint - this.minConstraint));

		this.$sliderTrackRange.css({
			'--low': low + '%',
			'--high': high + '%'
		});
	}

	inputNumberRangeValidation(val) {
		if (val < this.minConstraint)
			return this.minConstraint;

		if (val > this.maxConstraint)
			return this.maxConstraint;

		return val;
	}

	getFormattedData(data) {
		var re = '\\d(?=(\\d{' + (3 || 3) + '})+' + (this.format.decimal_num > 0 ? '\\D' : '$') + ')',
			num = data.toFixed(Math.max(0, ~~this.format.decimal_num));

		return (this.format.decimal_sep ? num.replace('.', this.format.decimal_sep) : num).replace(new RegExp(re, 'g'), '$&' + (this.format.thousands_sep || ''));
	}

	restoreFormattedData(data) {
		if (typeof data === 'number')
			return data;

		var restoreData = data
			.replace(new RegExp('\\' + this.format.thousands_sep, 'g'), '')
			.replace(this.format.decimal_sep, '.');

		return parseFloat(this.removeNonNumeric(restoreData));
	}

	removeNonNumeric(str) {
		return str.replace(/[^\d.-]/g, '');
	}

	rangeInputUpdateValue(inputType, newValue) {
		if (!this.$rangeInputs.length)
			return;

		let elInput;
		switch (inputType) {
			case 'min':
				elInput = this.$rangeInputMin[0];
				break;

			case 'max':
				elInput = this.$rangeInputMax[0];
				break;

			default:
				return;
		}

		if (this.rangeInputsSeparators) {
			const restoreValue = this.restoreFormattedData(newValue);
			const formattedValue = this.getFormattedData(restoreValue);

			if (!isNaN(restoreValue)) {
				elInput.value = formattedValue;
				elInput.numericValue = restoreValue;
			} else {
				elInput.value = '';

				switch (inputType) {
					case 'min':
						elInput.numericValue = this.minConstraint;
						break;

					case 'max':
						elInput.numericValue = this.maxConstraint;
						break;
				}
			}

			elInput.oldValue = elInput.value;
		} else {
			elInput.value = newValue;
		}
	}

	get activeValue() {
		if (typeof this.dataValue === 'string') {
			const data = this.dataValue.split('_');
			let value = '';

			if (data[0]) {
				if (this.prefix)
					value += this.prefix;

				value += this.getFormattedData(parseFloat(data[0]));

				if (this.suffix)
					value += this.suffix;

				if (data[1])
					value += ' — ';
			}

			if (data[1]) {
				if (this.prefix)
					value += this.prefix;

				value += this.getFormattedData(parseFloat(data[1]));

				if (this.suffix)
					value += this.suffix;
			}

			return value;
		} else {
			return this.dataValue;
		}
	}
}
