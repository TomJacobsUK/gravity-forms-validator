<?php
/**
 * Class for validating address fields in Gravity Forms
 * 
 * Requires the Gravity Forms Geolocation plugin for geolocation suggestions.
 */
class GF_Address_Validator {
    // Array of country code => postcode regex patterns
    private $postcode_patterns = array(
        'US' => '/^\d{5}(-\d{4})?$/', // US ZIP codes: 12345 or 12345-6789
        'GB' => '/^([Gg][Ii][Rr] 0[Aa]{2})|((([A-Za-z][0-9]{1,2})|(([A-Za-z][A-Ha-hJ-Yj-y][0-9]{1,2})|(([A-Za-z][0-9][A-Za-z])|([A-Za-z][A-Ha-hJ-Yj-y][0-9]?[A-Za-z])))) [0-9][A-Za-z]{2})$/i', // UK postcodes including GIR 0AA
        'CA' => '/^[ABCEGHJ-NPRSTVXY]\d[ABCEGHJ-NPRSTV-Z][ -]?\d[ABCEGHJ-NPRSTV-Z]\d$/i', // Canadian postcodes
        'AU' => '/^\d{4}$/', // Australian postcodes
        'FI' => '/^\d{5}$/', // Finnish vvvvvvvvvvvvpostcodes: 5 digits
        'HU' => '/^\d{4}$/', // Hungarian postcodes: 4 digits
    );

    public function __construct() {
        // Add hooks for address validation
        add_filter('gform_field_container', array($this, 'add_geolocation_suggestions'), 11, 2);
        add_action('gform_enqueue_scripts', array($this, 'enqueue_scripts'), 10, 2);
        
        // Add the validation filter
        add_filter('gform_field_validation', array($this, 'validate_postcode'), 10, 4);
    }

    public function enqueue_scripts($form, $is_ajax) {
        if ( ! $this->form_has_address_field($form) ) {
            return;
        }
        wp_enqueue_script('gf-validator-script', plugin_dir_url(__FILE__) . '../assets/js/gf-validator-address.js', array('jquery'), '1.0.0', true);
    }

    public function add_geolocation_suggestions($field_container, $field) {
        if ( ! $field instanceof GF_Field_Address ) {
            return $field_container;
        }

        $type        = empty( $field->addressType ) ? $field->get_default_address_type( $field->formId ) : $field->addressType;
        $type_config = rgar( $field->get_address_types( $field->formId ), $type );
        if ( empty( $type_config ) ) {
            return $field_container;
        }

        $hide_country = ! empty( $type_config['country'] ) || $field->hideCountry || $field->get_input_property( 6, 'isHidden' );
        if ( ! $hide_country ) {
            return $field_container;
        }

        $code = $field->get_country_code( rgar( $type_config, 'country', $field->defaultCountry ) );
        if ( empty( $code ) ) {
            return $field_container;
        }

        return str_replace( 'data-js', sprintf( "data-country-code='%s' data-js", esc_attr( $code ) ), $field_container );
    }
    
    public function form_has_address_field($form) {
        foreach ($form['fields'] as $field) {
            if ($field instanceof GF_Field_Address) {
                return true;
            }
        }
        return false;
    }

    /**
     * Validate postcode based on the country selected in the address field
     *
     * @param array $result The validation result
     * @param mixed $value The field value
     * @param array $form The form object
     * @param GF_Field $field The field object
     * @return array Modified validation result
     */
    public function validate_postcode($result, $value, $form, $field) {
        // Only validate Address fields
        if (!($field instanceof GF_Field_Address)) {
            return $result;
        }
        
        // Get the postcode value
        $postcode = rgpost('input_' . str_replace('.', '_', $field->id . '.5'));
        
        // If postcode is empty, only validate if the field is required
        if (empty($postcode)) {
            // If the field is not required, skip validation
            if (!$field->isRequired) {
                return $result;
            }
        }

        $country_name = rgpost('input_' . str_replace('.', '_', $field->id . '.6'));
        // Get country code
        $country_code = $field->get_country_code( $country_name );
        
        // Validate postcode if we have a pattern for this country
        if ($country_code && isset($this->postcode_patterns[$country_code])) {
            $pattern = $this->postcode_patterns[$country_code];
            
            if (!preg_match($pattern, $postcode)) {
                $result['is_valid'] = false;
                $result['message'] = sprintf(__('Please enter a valid postcode/zip for the %s.', 'gravity-forms-validator'), $country_name);
            }
        }

        if (empty($postcode)) {
            $result['is_valid'] = false;
            $result['message'] = __('Postcode cannot be empty.', 'gravity-forms-validator');
        }

        return $result;
    }
}