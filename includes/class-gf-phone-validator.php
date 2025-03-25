<?php
/**
 * Phone validator class for Gravity Forms
 */
class GF_Phone_Validator {
    /**
     * Initialize the validator
     */
    public function __construct() {
        // Add UK phone format to Gravity Forms
        add_filter('gform_phone_formats', array($this, 'add_phone_formats'));
        
        // Add scripts and styles for the international phone field
        add_action('gform_enqueue_scripts', array($this, 'enqueue_intl_phone_scripts'), 99, 0);
        
        // Add initialization for international phone fields
        add_action('gform_register_init_scripts', array($this, 'register_intl_phone_init'));
        
        // Filter the phone field to add country code and flag selector
        add_filter('gform_field_content', array($this, 'modify_phone_field'), 10, 5);
        
        // Add server-side validation for international phone fields
        add_filter('gform_field_validation', array($this, 'validate_phone_field'), 10, 4);
    }

    /**
     * Add phone formats to the Gravity Forms phone formats
     * 
     * @param array $phone_formats The existing phone formats
     * @return array The modified phone formats
     */
    public function add_phone_formats($phone_formats) {
        // Add UK format
        $phone_formats['uk'] = array(
            'label'       => 'UK',
            'mask'        => false,
            'regex'       => '/^(((\+44\s?\d{4}|\(?0\d{4}\)?)\s?\d{3}\s?\d{3})|((\+44\s?\d{3}|\(?0\d{3}\)?)\s?\d{3}\s?\d{4})|((\+44\s?\d{2}|\(?0\d{2}\)?)\s?\d{4}\s?\d{4}))(\s?\#(\d{4}|\d{3}))?$/',
            'instruction' => false,
        );
        
        // Add universal international format
        $phone_formats['international-selector'] = array(
            'label'       => 'International (with country code)',
            'mask'        => false,
            'regex'       => '/^\+[1-9]\d{1,14}$/', // E.164 format
            'instruction' => 'Please enter a valid international phone number with country code',
        );
    
        return $phone_formats;
    }
    
    /**
     * Enqueue scripts and styles for international phone field
     */
    public function enqueue_intl_phone_scripts() {
        $plugin_url = plugin_dir_url(dirname(__FILE__));
        
        // Enqueue intl-tel-input library
        wp_enqueue_style('intl-tel-input', 'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/css/intlTelInput.css', array('gforms_browsers_css', 'gravity_forms_orbital_theme'), null);
        wp_enqueue_script('intl-tel-input', 'https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/intlTelInput.min.js', array('jquery'), null, true);
        
        // Enqueue our custom script
        wp_enqueue_script('gf-phone-validator', $plugin_url . 'assets/js/phone-validator.js', array('jquery', 'intl-tel-input'), null, true);
        wp_enqueue_style('gf-phone-validator', $plugin_url . 'assets/css/phone-validator.css', array('intl-tel-input'), null);
    }
    
    /**
     * Register initialization for international phone fields
     * 
     * @param array $form The form object
     */
    public function register_intl_phone_init($form) {
        // Get all phone fields in the form
        $phone_fields = array();
        $default_country_code = 'gb'; // Default country code if no address field is found
        $preferred_countries = array('gb'); // Default preferred countries

        foreach ($form['fields'] as $field) {
            if ($field->type === 'phone' && !empty($field->phoneFormat) && $field->phoneFormat === 'international-selector') {
                $phone_fields[] = $field->id;
            }
            // Check for address field with default country
            if ($field instanceof GF_Field_Address) {
                $type = empty($field->addressType) ? $field->get_default_address_type($field->formId) : $field->addressType;
                $type_config = rgar($field->get_address_types($field->formId), $type);
                if (!empty($type_config)) {
                    $country_code = $field->get_country_code(rgar($type_config, 'country', $field->defaultCountry));
                    if (!empty($country_code)) {
                        $default_country_code = strtolower($country_code);
                        $preferred_countries = array($default_country_code);
                    }
                }
            }
        }

        if (empty($phone_fields)) {
            return;
        }

        $script = 'jQuery(document).ready(function($) {';

        foreach ($phone_fields as $field_id) {
            $script .= '
                var phoneInput_' . $field_id . ' = window.intlTelInput(document.querySelector("#input_' . $form['id'] . '_' . $field_id . '"), {
                    utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/utils.js",
                    initialCountry: "' . $default_country_code . '",
                    preferredCountries: ["' . implode('","', $preferred_countries) . '"],
                    separateDialCode: true,
                    formatOnDisplay: true,
                    hiddenInput: "full_phone"
                });
                
                // Store the initialized instance for later use
                window["phoneInput_' . $form['id'] . '_' . $field_id . '"] = phoneInput_' . $field_id . ';
                
                // Add validation for this field
                $("#input_' . $form['id'] . '_' . $field_id . '").on("blur", function() {
                    var isValid = phoneInput_' . $field_id . '.isValidNumber();
                    if (isValid) {
                        $(this).removeClass("phone-error").addClass("phone-valid");
                        // Store the full international number in the field
                        $(this).val(phoneInput_' . $field_id . '.getNumber());
                    } else {
                        $(this).removeClass("phone-valid").addClass("phone-error");
                    }
                });
                
                // Before form submission, ensure we have the full number
                $("#gform_' . $form['id'] . '").on("submit", function() {
                    var fullPhone = phoneInput_' . $field_id . '.getNumber();
                    $("#input_' . $form['id'] . '_' . $field_id . '").val(fullPhone);
                });
            ';
        }

        $script .= '});';

        GFFormDisplay::add_init_script($form['id'], 'intl_phone_fields', GFFormDisplay::ON_PAGE_RENDER, $script);
    }
    
    /**
     * Modify phone field to add custom attributes for international phone input
     * 
     * @param string $field_content The field content to be filtered
     * @param object $field The field object
     * @param string $value The field value
     * @param int $entry_id The entry ID
     * @param int $form_id The form ID
     * @return string The modified field content
     */
    public function modify_phone_field($field_content, $field, $value, $entry_id, $form_id) {
        if ($field->type === 'phone' && !empty($field->phoneFormat) && $field->phoneFormat === 'international-selector') {
            // Add custom class to identify international phone fields
            $field_content = str_replace('class="', 'class="gf-intl-phone ', $field_content);
        }
        
        return $field_content;
    }

    /**
     * Validate phone field server-side
     * 
     * @param array $result The validation result
     * @param mixed $value The field value
     * @param array $form The form object
     * @param object $field The field object
     * @return array The modified validation result
     */
    public function validate_phone_field($result, $value, $form, $field) {
        // Only validate international format phone fields
        if ($field->type === 'phone' && !empty($field->phoneFormat) && $field->phoneFormat === 'international-selector') {
            // Basic server-side validation for E.164 format
            if (!empty($value) && !preg_match('/^\+[1-9]\d{1,14}$/', $value)) {
                $result['is_valid'] = false;
                $result['message'] = 'Please enter a valid international phone number with country code.';
            }
        }
        
        return $result;
    }
}