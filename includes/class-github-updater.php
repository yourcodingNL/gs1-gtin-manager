<?php
/**
 * GitHub Updater Class
 * 
 * Handles automatic updates from GitHub releases
 * 
 * @package GS1_GTIN_Manager
 * @author YoCo - Sebastiaan Kalkman
 */

if (!defined('ABSPATH')) {
    exit;
}

class GS1_GTIN_GitHub_Updater {
    
    private $file;
    private $plugin;
    private $basename;
    private $active;
    private $username;
    private $repository;
    private $authorize_token;
    private $github_response;
    
    public function __construct($file, $username, $repository, $authorize_token = '') {
        $this->file = $file;
        $this->plugin = get_plugin_data($this->file);
        $this->basename = plugin_basename($this->file);
        $this->active = is_plugin_active($this->basename);
        $this->username = $username;
        $this->repository = $repository;
        $this->authorize_token = $authorize_token;
        
        add_filter('pre_set_site_transient_update_plugins', [$this, 'modify_transient'], 10, 1);
        add_filter('plugins_api', [$this, 'plugin_popup'], 10, 3);
        add_filter('upgrader_post_install', [$this, 'after_install'], 10, 3);
        
        // Clear cache on update
        add_action('upgrader_process_complete', [$this, 'purge_cache'], 10, 2);
    }
    
    /**
     * Get GitHub release info
     */
    private function get_repository_info() {
        if (!is_null($this->github_response)) {
            return;
        }
        
        $request_uri = sprintf('https://api.github.com/repos/%s/%s/releases/latest', 
            $this->username, 
            $this->repository
        );
        
        $args = [
            'timeout' => 15,
            'headers' => [
                'Accept' => 'application/vnd.github.v3+json'
            ]
        ];
        
        if ($this->authorize_token) {
            $args['headers']['Authorization'] = "token {$this->authorize_token}";
        }
        
        $response = wp_remote_get($request_uri, $args);
        
        if (is_wp_error($response)) {
            GS1_GTIN_Logger::log('GitHub API Error: ' . $response->get_error_message(), 'error');
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            GS1_GTIN_Logger::log("GitHub API returned code {$response_code}", 'error');
            return false;
        }
        
        $this->github_response = json_decode(wp_remote_retrieve_body($response));
    }
    
    /**
     * Modify the plugin update transient
     */
    public function modify_transient($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        $this->get_repository_info();
        
        if (!$this->github_response || !isset($this->github_response->tag_name)) {
            return $transient;
        }
        
        $remote_version = ltrim($this->github_response->tag_name, 'v');
        
        if (version_compare($this->plugin['Version'], $remote_version, '<')) {
            $plugin = [
                'slug' => dirname($this->basename),
                'plugin' => $this->basename,
                'new_version' => $remote_version,
                'url' => $this->plugin['PluginURI'],
                'package' => $this->github_response->zipball_url,
                'tested' => $this->plugin['Requires at least'],
            ];
            
            $transient->response[$this->basename] = (object) $plugin;
        }
        
        return $transient;
    }
    
    /**
     * Show plugin information popup
     */
    public function plugin_popup($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }
        
        if (!empty($args->slug) && $args->slug === dirname($this->basename)) {
            $this->get_repository_info();
            
            if (!$this->github_response) {
                return $result;
            }
            
            $plugin = [
                'name' => $this->plugin['Name'],
                'slug' => dirname($this->basename),
                'version' => ltrim($this->github_response->tag_name, 'v'),
                'author' => $this->plugin['Author'],
                'author_profile' => $this->plugin['AuthorURI'],
                'homepage' => $this->plugin['PluginURI'],
                'requires' => $this->plugin['Requires at least'],
                'tested' => $this->plugin['WC tested up to'],
                'downloaded' => 0,
                'last_updated' => $this->github_response->published_at,
                'sections' => [
                    'description' => $this->plugin['Description'],
                    'changelog' => $this->github_response->body ?? 'Zie GitHub voor changelog'
                ],
                'download_link' => $this->github_response->zipball_url,
            ];
            
            return (object) $plugin;
        }
        
        return $result;
    }
    
    /**
     * Handle post-install to ensure correct directory structure
     */
    public function after_install($response, $hook_extra, $result) {
        global $wp_filesystem;
        
        $install_directory = plugin_dir_path($this->file);
        $wp_filesystem->move($result['destination'], $install_directory);
        $result['destination'] = $install_directory;
        
        if ($this->active) {
            activate_plugin($this->basename);
        }
        
        return $result;
    }
    
    /**
     * Purge cache after update
     */
    public function purge_cache($upgrader, $options) {
        if ($options['action'] === 'update' && $options['type'] === 'plugin') {
            delete_transient('gs1_gtin_github_update');
        }
    }
}
