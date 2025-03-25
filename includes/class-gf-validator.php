<?php
/**
 * Main validator class that loads individual validators
 */
class GF_Validator {
    /**
     * List of validator instances
     */
    private $validators = array();

    /**
     * Initialize the validator
     */
    public function __construct() {
        // Load all validators
        $this->load_validators();
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_update']);
        add_filter('plugins_api', [$this, 'plugins_api_handler'], 10, 3);
    }

    /**
     * Load all validators
     */
    private function load_validators() {
        // Load address validator
        $this->load_address_validator();
        
        // Add other validators here as needed
        $this->load_phone_validator();
    }

    /**
     * Load the address validator
     */
    private function load_address_validator() {
        require_once plugin_dir_path(__FILE__) . 'class-gf-address-validator.php';
        $this->validators['address'] = new GF_Address_Validator();
    }

    /**
     * Load the phone validator
     */
    private function load_phone_validator() {
        require_once plugin_dir_path(__FILE__) . 'class-gf-phone-validator.php';
        $this->validators['phone'] = new GF_Phone_Validator();
    }

    public function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $remote_version = $this->get_remote_version();
        $plugin_data = get_plugin_data(__FILE__);
        $plugin_version = $plugin_data['Version'];

        if (version_compare($plugin_version, $remote_version, '<')) {
            $plugin_slug = plugin_basename(__FILE__);
            $transient->response[$plugin_slug] = (object) [
                'slug' => $plugin_slug,
                'new_version' => $remote_version,
                'url' => 'https://github.com/TomJacobsUK/gravity-forms-validator',
                'package' => 'https://github.com/TomJacobsUK/gravity-forms-validator/archive/refs/heads/main.zip',
            ];
        }

        return $transient;
    }

    public function plugins_api_handler($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if ($args->slug !== plugin_basename(__FILE__)) {
            return $result;
        }

        $remote_info = $this->get_remote_info();

        return (object) [
            'name' => 'Gravity Forms UTM Tracking',
            'slug' => plugin_basename(__FILE__),
            'version' => $remote_info->version,
            'author' => 'Tom Jacobs',
            'author_profile' => 'https://tomjacobs.co.uk',
            'homepage' => 'https://github.com/TomJacobsUK/gravity-forms-validator',
            'short_description' => 'Automatically captures and stores UTM parameters in Gravity Forms submissions.',
            'sections' => [
                'description' => $remote_info->description,
                'changelog' => $remote_info->changelog,
            ],
            'download_link' => 'https://github.com/TomJacobsUK/gravity-forms-validator/archive/refs/heads/main.zip',
        ];
    }

    private function get_remote_version() {
        $remote_info = $this->get_remote_info();
        return $remote_info->version;
    }

    private function get_remote_info() {
        $response = wp_remote_get('https://raw.githubusercontent.com/TomJacobsUK/gravity-forms-validator/main/info.json');
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return (object) [
                'version' => '1.0.0',
                'description' => '',
                'changelog' => '',
            ];
        }

        return json_decode(wp_remote_retrieve_body($response));
    }
}
