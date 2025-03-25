/**
 * Gravity Forms Phone Validator
 * 
 * Handles the initialization and validation of international phone fields
 */
(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        // This is just a helper file. The actual initialization is handled
        // by the PHP script that generates specific initialization for each form field
        
        // Hook into the Gravity Forms validation
        if (typeof gform !== 'undefined' && gform.hooks) {
            gform.addFilter('gform_is_value_valid', function(isValid, value, form, field) {
                // Only apply to phone fields with international format
                if (field.type === 'phone' && field.phoneFormat === 'international-selector') {
                    // Get the phone input instance
                    var phoneInputInstance = window['phoneInput_' + form.id + '_' + field.id];
                    
                    if (phoneInputInstance && value) {
                        // Check if the phone number is valid
                        return phoneInputInstance.isValidNumber();
                    }
                }
                
                return isValid;
            });
            
            // Add custom validation message for international phone fields
            gform.addFilter('gform_validation_message', function(message, form) {
                // Look for phone fields with errors
                for (var i = 0; i < form.fields.length; i++) {
                    var field = form.fields[i];
                    if (field.type === 'phone' && field.phoneFormat === 'international-selector' && !field.isValid) {
                        // Add custom error message
                        message += '<li>Please enter a valid international phone number with country code.</li>';
                        break;
                    }
                }
                
                return message;
            });
        }
    });
    
})(jQuery);