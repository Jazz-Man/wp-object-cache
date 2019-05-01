<?php

/*
Plugin Name: WP Object Cache
Description: Redis, Memcached or Apcu backend for the WP Object Cache
Version: v1.0
Plugin URI: https://github.com/Jazz-Man/wp-object-cache
Author: Vasyl Sokolyk

*/

/**
 * Class WPObjectCache.
 */
class WPObjectCache
{

    /**
     * @var string
     */
    private $page;

    /**
     * @var string
     */
    private $page_slug = 'wp-object-cache';

    /**
     * @var string
     */
    private $base_options_page;

    /**
     * @var array
     */
    private $actions = ['enable-cache', 'disable-cache', 'flush-cache', 'update-dropin'];

    /**
     * @var string
     */
    private $capability;
    /**
     * @var string
     */
    private $root_dir;
    /**
     * @var string
     */
    private $dropin_file;
    /**
     * @var string
     */
    private $wp_dropin_file;

    public function __construct()
    {
        $this->root_dir = plugin_dir_path(__FILE__);

        register_activation_hook(__FILE__, 'wp_cache_flush');
        register_deactivation_hook(__FILE__, [$this, 'onDeactivation']);

        load_plugin_textdomain($this->page_slug, false, plugin_basename($this->root_dir) . '/languages');

        $is_multisite = is_multisite();

        $this->dropin_file    = "{$this->root_dir}include/object-cache.php";
        $this->wp_dropin_file = WP_CONTENT_DIR . '/object-cache.php';

        $this->capability = $is_multisite ? 'manage_network_options' : 'manage_options';

        $admin_menu = $is_multisite ? 'network_admin_menu' : 'admin_menu';

        $screen = "settings_page_{$this->page_slug}";

        $this->base_options_page = $is_multisite ? 'settings.php' : 'options-general.php';

        $this->page = "{$this->base_options_page}?page={$this->page_slug}";

        add_action($admin_menu, [$this, 'addAdminMenuPage']);

        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_styles']);
        add_action("load-{$screen}", [$this, 'doAdminActions']);
        add_action("load-{$screen}", [$this, 'addAdminPageNotices']);

        add_action('admin_notices', [$this, 'showAdminNotices']);

        add_filter(sprintf('%splugin_action_links_%s', $is_multisite ? 'network_admin_' : '',
            plugin_basename(__FILE__)), [$this, 'addPluginActionsLinks']);
    }

    public function addAdminMenuPage()
    {
        // add sub-page to "Settings"
        add_submenu_page($this->base_options_page, 'WP Object Cache', 'WP Object Cache', $this->capability,
            $this->page_slug, [$this, 'showAdminPage']);
    }

    public function showAdminNotices()
    {
        // only show admin notices to users with the right capability
        if ( ! current_user_can($this->capability)) {
            return;
        }

        if ($this->objectCacheDropinExists()) {
            $url = wp_nonce_url(network_admin_url(add_query_arg('action', 'update-dropin', $this->page)),
                'update-dropin');

            if ($this->validateObjectCacheDropin()) {
                $dropin = get_plugin_data($this->wp_dropin_file);
                $plugin = get_plugin_data($this->dropin_file);

                if (version_compare($dropin['Version'], $plugin['Version'], '<')) {
                    $message = sprintf(__('The object cache drop-in is outdated. Please <a href="%s">update it now</a>.',
                        $this->page_slug), $url);
                }
            } else {
                $message = sprintf(__('An unknown object cache drop-in was found. To use WP Object Cache , <a href="%s">please replace it now</a>.',
                    $this->page_slug), $url);
            }

            if (isset($message)) {
                printf('<div class="update-nag">%s</div>', $message);
            }
        } else {
            $enable_url = wp_nonce_url(network_admin_url(add_query_arg('action', 'enable-cache', $this->page)),
                'enable-cache');

            $message = sprintf(__('WP Object Cache is not used. To use WP Object Cache , <a href="%s">please enable it now</a>.',
                $this->page_slug), $enable_url);

            printf('<div class="update-nag">%s</div>', $message);
        }
    }

    /**
     * @param array $links
     *
     * @return array
     */
    public function addPluginActionsLinks($links)
    {
        $_actions = [
            sprintf('<a href="%s">Settings</a>', network_admin_url($this->page)),
        ];

        if ($this->getCacheStatus()) {
            $_actions[] = $this->getLink('flush-cache', 'Flush Cache');
        }
        if ( ! $this->objectCacheDropinExists()) {
            $_actions[] = $this->getLink('enable-cache', 'Enable Cache');
        }

        if ($this->validateObjectCacheDropin()) {
            $_actions[] = $this->getLink('disable-cache', 'Disable Cache');
        }

        return array_merge($_actions, $links);
    }

    public function showAdminPage()
    {
        $action = filter_input(INPUT_GET, 'action');
        $nonce  = filter_input(INPUT_GET, '_wpnonce');

        // request filesystem credentials?
        if ( ! empty($action) && ! empty($nonce)) {
            foreach ($this->actions as $name) {
                // verify nonce
                if ($action === $name && wp_verify_nonce($nonce, $action)) {
                    $url = wp_nonce_url(network_admin_url(add_query_arg('action', $action, $this->page)), $action);

                    if (false === $this->initFs($url)) {
                        return; // request filesystem credentials
                    }
                }
            }
        }

        // show admin page
        require_once $this->root_dir . '/admin-page.php';
    }

    /**
     * @param      $url
     * @param bool $silent
     *
     * @return bool
     */
    public function initFs($url, $silent = false)
    {
        if ($silent) {
            ob_start();
        }

        if (false === ($credentials = request_filesystem_credentials($url))) {
            if ($silent) {
                ob_end_clean();
            }

            return false;
        }

        if ( ! WP_Filesystem($credentials)) {
            request_filesystem_credentials($url);

            if ($silent) {
                ob_end_clean();
            }

            return false;
        }

        return true;
    }

    /**
     * @return bool
     */
    private function objectCacheDropinExists()
    {
        return file_exists($this->wp_dropin_file);
    }

    /**
     * @return bool
     */
    public function validateObjectCacheDropin()
    {
        if ( ! $this->objectCacheDropinExists()) {
            return false;
        }

        $dropin = get_plugin_data($this->wp_dropin_file);

        $plugin = get_plugin_data($this->dropin_file);

        return $dropin['PluginURI'] === $plugin['PluginURI'];
    }

    /**
     * @param string $action
     * @param string $link_text
     *
     * @return string
     */
    public function getLink($action = 'flush-cache', $link_text = 'Flush Cache')
    {
        $action       = esc_attr($action);
        $link_text    = esc_html__(sanitize_text_field($link_text), $this->page_slug);

        $link_url = wp_nonce_url(network_admin_url(add_query_arg('action', $action, $this->page)), $action);

        return "<a href='{$link_url}'>{$link_text}</a>";
    }

    /**
     * @return bool|\Phpfastcache\Entities\DriverStatistic
     */
    public function getCacheStatus()
    {
        if ($this->validateObjectCacheDropin()) {
            return wp_object_cache_get_stats();
        }

        return false;
    }

    public function doAdminActions()
    {
        /* @var \WP_Filesystem_Direct $wp_filesystem */

        global $wp_filesystem;

        $action = filter_input(INPUT_GET, 'action');
        $nonce  = filter_input(INPUT_GET, '_wpnonce');

        if ( ! empty($action) && ! empty($nonce)) {
            // verify nonce
            foreach ($this->actions as $name) {
                if ($action === $name && ! wp_verify_nonce($nonce, $action)) {
                    return;
                }
            }

            if (in_array($action, $this->actions)) {
                $url = wp_nonce_url(network_admin_url(add_query_arg('action', $action, $this->page)), $action);

                if ('flush-cache' === $action) {
                    $message = wp_cache_flush() ? 'cache-flushed' : 'flush-cache-failed';
                }

                if ($this->initFs($url, true)) {
                    switch ($action) {
                        case 'enable-cache':

                            $result  = $wp_filesystem->copy($this->dropin_file, $this->wp_dropin_file, true);
                            $message = $result ? 'cache-enabled' : 'enable-cache-failed';
                            break;
                        case 'disable-cache':
                            $result  = $wp_filesystem->delete($this->wp_dropin_file);
                            $message = $result ? 'cache-disabled' : 'disable-cache-failed';
                            break;

                        case 'update-dropin':
                            $result  = $wp_filesystem->copy($this->dropin_file, $this->wp_dropin_file, true);
                            $message = $result ? 'dropin-updated' : 'update-dropin-failed';
                            break;
                    }
                }

                // redirect if status `$message` was set
                if (isset($message)) {
                    wp_safe_redirect(network_admin_url(add_query_arg('message', $message, $this->page)));
                    exit(0);
                }
            }
        }
    }

    public function addAdminPageNotices()
    {
        // show PHP version warning
        if (PHP_VERSION_ID < 50400) {
            add_settings_error('', $this->page_slug, __('This plugin requires PHP 5.4 or greater.', $this->page_slug));
        }

        $message_code = filter_input(INPUT_GET, 'message');

        // show action success/failure messages
        if ( ! empty($message_code)) {

            switch ($message_code) {
                case 'cache-enabled':
                    $message = __('Object cache enabled.', $this->page_slug);
                    break;
                case 'enable-cache-failed':
                    $error = __('Object cache could not be enabled.', $this->page_slug);
                    break;
                case 'cache-disabled':
                    $message = __('Object cache disabled.', $this->page_slug);
                    break;
                case 'disable-cache-failed':
                    $error = __('Object cache could not be disabled.', $this->page_slug);
                    break;
                case 'cache-flushed':
                    $message = __('Object cache flushed.', $this->page_slug);
                    break;
                case 'flush-cache-failed':
                    $error = __('Object cache could not be flushed.', $this->page_slug);
                    break;
                case 'dropin-updated':
                    $message = __('Updated object cache drop-in and enabled Redis object cache.', $this->page_slug);
                    break;
                case 'update-dropin-failed':
                    $error = __('Object cache drop-in could not be updated.', $this->page_slug);
                    break;
            }

            add_settings_error('', $this->page_slug, isset($message) ? $message : $error,
                isset($message) ? 'updated' : 'error');
        }
    }

    /**
     * @param string $plugin
     */
    public function onDeactivation($plugin)
    {
        /* @var \WP_Filesystem_Direct $wp_filesystem */

        global $wp_filesystem;

        if ($plugin === plugin_basename(__FILE__)) {
            wp_cache_flush();

            if ($this->validateObjectCacheDropin() && $this->initFs('', true)) {
                $wp_filesystem->delete($this->wp_dropin_file);
            }
        }
    }
}

new WPObjectCache();
