<script>
(function (window, document, $) {
    'use strict';

    const HrUi = window.HrUi = window.HrUi || {};
    HrUi.flatpickrs = HrUi.flatpickrs || {};

    function resolveSelects(target) {
        if (!target) {
            return $('select');
        }

        if (typeof target === 'string') {
            return $(target);
        }

        const $target = $(target);
        return $target.is('select') ? $target : $target.find('select');
    }

    HrUi.initSelect2 = function (target, options) {
        if (!$ || typeof $.fn.select2 === 'undefined') {
            return $();
        }

        const $selects = resolveSelects(target).filter(':not([data-no-select2])');

        $selects.each(function () {
            const element = this;
            const $element = $(element);

            if ($element.hasClass('select2-hidden-accessible')) {
                return;
            }

            const $modal = $element.closest('.modal');
            const emptyOption = Array.from(element.options || []).find(function (option) {
                return option.value === '';
            });

            const config = {
                theme: 'progga-theme',
                width: '100%',
                placeholder: element.dataset.placeholder || (emptyOption ? emptyOption.text : 'Select an option'),
                allowClear: element.dataset.allowClear !== 'false',
                closeOnSelect: !element.multiple
            };

            if (element.dataset.search === 'false') {
                config.minimumResultsForSearch = Infinity;
            }

            if ($modal.length) {
                config.dropdownParent = $modal;
            }

            $element.select2(Object.assign(config, options || {}));
        });

        return $selects;
    };

    HrUi.destroySelect2 = function (target) {
        if (!$ || typeof $.fn.select2 === 'undefined') {
            return;
        }

        resolveSelects(target).each(function () {
            const $element = $(this);
            if ($element.hasClass('select2-hidden-accessible')) {
                $element.select2('destroy');
            }
        });
    };

    HrUi.selectValue = function (id) {
        const element = document.getElementById(id);
        if (!element) {
            return '';
        }

        return $(element).val() ?? '';
    };

    HrUi.setSelectValue = function (id, value) {
        const element = document.getElementById(id);
        if (!element) {
            return;
        }

        let normalized;
        if (element.multiple) {
            normalized = Array.isArray(value)
                ? value.map(String)
                : (value === null || value === undefined || value === '' ? [] : [String(value)]);
        } else {
            normalized = value === null || value === undefined ? '' : String(value);
        }

        $(element).val(normalized).trigger('change');
    };

    HrUi.resetSelect = function (id) {
        const element = document.getElementById(id);
        if (!element) {
            return;
        }

        const defaultValues = Array.from(element.options || [])
            .filter(function (option) { return option.defaultSelected; })
            .map(function (option) { return option.value; });

        let value;
        if (element.multiple) {
            value = defaultValues;
        } else if (defaultValues.length) {
            value = defaultValues[0];
        } else {
            const emptyOption = Array.from(element.options || []).find(function (option) {
                return option.value === '';
            });
            value = emptyOption ? '' : (element.options[0] ? element.options[0].value : '');
        }

        HrUi.setSelectValue(id, value);
    };

    HrUi.initFlatpickr = function (elementOrSelector, options) {
        if (typeof window.flatpickr === 'undefined') {
            return null;
        }

        const element = typeof elementOrSelector === 'string'
            ? document.querySelector(elementOrSelector)
            : elementOrSelector;

        if (!element) {
            return null;
        }

        if (element._flatpickr) {
            return element._flatpickr;
        }

        const instance = flatpickr(element, Object.assign({
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'd-m-Y',
            allowInput: true,
            disableMobile: true
        }, options || {}));

        if (element.id) {
            HrUi.flatpickrs[element.id] = instance;
        }

        return instance;
    };

    HrUi.showValidationErrors = function (errors, selectorPrefix) {
        const prefix = selectorPrefix || '[data-error="';
        document.querySelectorAll('.hr-validation-error').forEach(function (element) {
            element.textContent = '';
        });

        Object.keys(errors || {}).forEach(function (key) {
            const field = key.replace(/\./g, '_');
            const target = document.querySelector(prefix + field + '"]');
            if (target) {
                target.textContent = Array.isArray(errors[key]) ? errors[key][0] : errors[key];
            }
        });
    };

    $(document).on('shown.bs.modal', '.modal', function () {
        HrUi.initSelect2(this);
    });
})(window, document, window.jQuery);
</script>
