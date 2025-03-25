gform.addFilter('gform_geolocation_autocomplete_options_pre_init', function (options, formId, fieldId, address) {
    if (address.dataset?.countryCode) {
        options.componentRestrictions = { country: address.dataset.countryCode };
    }
    return options;
});