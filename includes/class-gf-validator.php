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
     * GitHub repository information
     */
    private $github_repo = [
        'owner' => 'TomJacobsUK',
        'repo' => 'gravity-forms-validator',
        'api_url' => 'https://api.github.com/repos/TomJacobsUK/gravity-forms-validator',
    ];
    
    /**
     * Current plugin data
     */
    private $plugin_data;
    
    /**
     * Plugin basename
     */
    private $plugin_basename;

    /**
     * Initialize the validator
     */
    public function __construct() {
        // Set plugin info
        $this->plugin_basename = plugin_basename(dirname(dirname(__FILE__)) . '/gravity-forms-validator.php');
        $this->plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $this->plugin_basename);
        
        // Load all validators
        $this->load_validators();
        
        // Setup update mechanism
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_update']);
        add_filter('plugins_api', [$this, 'plugins_api_handler'], 10, 3);
        
        // Filter for downloaded updates to ensure vendor files are included
        add_filter('upgrader_post_install', [$this, 'post_install_process'], 10, 3);
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

    /**
     * Check for plugin updates by comparing versions with GitHub releases
     * 
     * @param object $transient WordPress update transient
     * @return object Modified update transient
     */
    public function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        // Get latest release information from GitHub
        $release_info = $this->get_latest_github_release();
        if (!$release_info) {
            return $transient;
        }

        $current_version = $this->plugin_data['Version'];
        $latest_version = ltrim($release_info->tag_name, 'v'); // Remove 'v' prefix if present
        
        // Compare versions
        if (version_compare($current_version, $latest_version, '<')) {
            $download_url = $this->get_release_asset_url($release_info) ?: $release_info->zipball_url;
            
            $transient->response[$this->plugin_basename] = (object) [
                'slug' => dirname($this->plugin_basename),
                'plugin' => $this->plugin_basename,
                'new_version' => $latest_version,
                'url' => $this->github_repo['api_url'],
                'package' => $download_url,
                'icons' => [],
                'banners' => [],
                'banners_rtl' => [],
                'tested' => '',
                'requires_php' => '',
                'compatibility' => new stdClass(),
            ];
        }

        return $transient;
    }

    /**
     * Get information for the WordPress plugin info popup
     * 
     * @param mixed $result
     * @param string $action
     * @param object $args
     * @return object Plugin information
     */
    public function plugins_api_handler($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (!isset($args->slug) || $args->slug !== dirname($this->plugin_basename)) {
            return $result;
        }

        $release_info = $this->get_latest_github_release();
        if (!$release_info) {
            return $result;
        }

        $latest_version = ltrim($release_info->tag_name, 'v'); // Remove 'v' prefix if present
        $download_url = $this->get_release_asset_url($release_info) ?: $release_info->zipball_url;

        return (object) [
            'name' => $this->plugin_data['Name'],
            'slug' => dirname($this->plugin_basename),
            'version' => $latest_version,
            'author' => $this->plugin_data['Author'],
            'author_profile' => $this->plugin_data['AuthorURI'],
            'homepage' => $this->plugin_data['PluginURI'],
            'requires' => '',
            'requires_php' => '7.1',
            'downloaded' => 0,
            'last_updated' => $release_info->published_at,
            'sections' => [
                'description' => $this->plugin_data['Description'],
                'changelog' => $this->format_github_markdown($release_info->body),
                'installation' => $this->get_installation_instructions(),
            ],
            'download_link' => $download_url,
        ];
    }

    /**
     * After plugin update, run composer install if needed
     * 
     * @param bool $response Installation response
     * @param array $hook_extra Extra arguments passed to hooked filters
     * @param array $result Installation result data
     * @return array Modified result
     */
    public function post_install_process($response, $hook_extra, $result) {
        // Check if this is our plugin
        if (isset($hook_extra['plugin']) && $hook_extra['plugin'] === $this->plugin_basename) {
            global $wp_filesystem;
            
            // Get plugin directory
            $plugin_dir = WP_PLUGIN_DIR . '/' . dirname($this->plugin_basename);
            
            // Check if composer.json exists but vendor directory is missing
            if (file_exists($plugin_dir . '/composer.json') && !is_dir($plugin_dir . '/vendor')) {
                // Add notice for user to run composer
                set_transient('gf_validator_composer_notice', true, 60 * 60 * 24); // 24 hour notice
                
                // Hook into admin_notices to display a notice
                add_action('admin_notices', function() {
                    if (get_transient('gf_validator_composer_notice')) {
                        ?>
                        <div class="notice notice-warning is-dismissible">
                            <p><?php _e('Gravity Forms Validator has been updated, but requires Composer dependencies. Please run "composer install" in the plugin directory.', 'gravity-forms-validator'); ?></p>
                        </div>
                        <?php
                        delete_transient('gf_validator_composer_notice');
                    }
                });
            }
        }
        
        return $result;
    }
    
    /**
     * Get the latest release information from GitHub
     * 
     * @return object|false Release information or false on failure
     */
    private function get_latest_github_release() {
        $transient_key = 'gf_validator_github_release';
        $cached_release = get_transient($transient_key);
        
        if ($cached_release !== false) {
            return $cached_release;
        }
        
        $url = $this->github_repo['api_url'] . '/releases/latest';
        $response = wp_remote_get($url, [
            'headers' => [
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url'),
            ],
        ]);
        
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }
        
        $release_info = json_decode(wp_remote_retrieve_body($response));
        
        // Cache for 6 hours
        set_transient($transient_key, $release_info, 6 * HOUR_IN_SECONDS);
        
        return $release_info;
    }
    
    /**
     * Get the URL for a release asset (if one exists)
     * 
     * @param object $release_info Release information from GitHub
     * @return string|false Asset URL or false if not found
     */
    private function get_release_asset_url($release_info) {
        if (empty($release_info->assets)) {
            return false;
        }
        
        // Look for a specific asset (preferably a built zip with vendor included)
        foreach ($release_info->assets as $asset) {
            if (strpos($asset->name, '-with-dependencies') !== false && strpos($asset->name, '.zip') !== false) {
                return $asset->browser_download_url;
            }
        }
        
        // If no specific asset found, return the first zip
        foreach ($release_info->assets as $asset) {
            if (strpos($asset->name, '.zip') !== false) {
                return $asset->browser_download_url;
            }
        }
        
        return false;
    }
    
    /**
     * Format GitHub markdown for WordPress
     * 
     * @param string $markdown GitHub markdown
     * @return string Formatted content
     */
    private function format_github_markdown($markdown) {
        // Simple conversion - for a more comprehensive solution, consider using a Markdown parser
        $content = nl2br(esc_html($markdown));
        
        // Convert GitHub issue/PR references
        $content = preg_replace('/#(\d+)/', '<a href="' . $this->github_repo['api_url'] . '/issues/$1" target="_blank">#$1</a>', $content);
        
        return $content;
    }
    
    /**
     * Get installation instructions
     * 
     * @return string Installation instructions
     */
    private function get_installation_instructions() {
        return '
        <h4>Standard Installation</h4>
        <ol>
            <li>Download the plugin ZIP file from GitHub releases</li>
            <li>Upload the ZIP file through WordPress plugin uploader</li>
            <li>Activate the plugin</li>
        </ol>
        
        <h4>Manual Installation with Composer</h4>
        <ol>
            <li>Clone the repository to your <code>wp-content/plugins</code> directory</li>
            <li>Navigate to the plugin directory and run <code>composer install</code></li>
            <li>Activate the plugin through the WordPress admin panel</li>
        </ol>
        ';
    }
}
