<?php

/*
Plugin Name: WP Object Cache
Description: Redis, Memcached or Apcu backend for the WP Object Cache
Version: v2.0
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
    private $pageSlug = 'wp-object-cache';

    /**
     * @var string
     */
    private $baseOptionsPage;

    /**
     * @var string[]
     */
    private $actions;

    /**
     * @var string
     */
    private $capability;
    /**
     * @var string
     */
    private $rootDir;
    /**
     * @var string
     */
    private $dropinFile;
    /**
     * @var string
     */
    private $wpDropinFile;

    public function __construct()
    {
        $this->rootDir = plugin_dir_path(__FILE__);

        register_activation_hook(__FILE__, 'wp_cache_flush');
        register_deactivation_hook(__FILE__, [$this, 'onDeactivation']);

        $isMultisite = is_multisite();

        $this->actions = ['enable-cache', 'disable-cache', 'flush-cache', 'update-dropin'];

        $this->dropinFile = "{$this->rootDir}include/object-cache.php";
        $this->wpDropinFile = WP_CONTENT_DIR.'/object-cache.php';

        $this->capability = $isMultisite ? 'manage_network_options' : 'manage_options';

        $adminMenu = $isMultisite ? 'network_admin_menu' : 'admin_menu';

        $screen = "settings_page_$this->pageSlug";

        $this->baseOptionsPage = $isMultisite ? 'settings.php' : 'options-general.php';

        $this->page = "$this->baseOptionsPage?page=$this->pageSlug";

        add_action($adminMenu, [$this, 'addAdminMenuPage']);

        add_action("load-$screen", [$this, 'doAdminActions']);
        add_action("load-$screen", [$this, 'addAdminPageNotices']);

        add_action('admin_notices', [$this, 'showAdminNotices']);

        $filter = sprintf('%splugin_action_links_%s', $isMultisite ? 'network_admin_' : '', plugin_basename(__FILE__));
        add_filter($filter, [$this, 'addPluginActionsLinks']);
    }

    public function addAdminMenuPage(): void
    {
        // add sub-page to "Settings"
        add_submenu_page(
            $this->baseOptionsPage,
            'WP Object Cache',
            'WP Object Cache',
            $this->capability,
            $this->pageSlug,
            [$this, 'showAdminPage']
        );
    }

    public function showAdminNotices(): void
    {
        // only show admin notices to users with the right capability
        if (!current_user_can($this->capability)) {
            return;
        }

        if ($this->objectCacheDropinExists()) {
            $url = wp_nonce_url(
                network_admin_url(add_query_arg('action', 'update-dropin', $this->page)),
                'update-dropin'
            );

            if ($this->validateObjectCacheDropin()) {
                $dropin = get_plugin_data($this->wpDropinFile);
                $plugin = get_plugin_data($this->dropinFile);

                if (version_compare($dropin['Version'], $plugin['Version'], '<')) {
                    $message = sprintf(
                        __(
                            '<strong>The object cache drop-in is outdated.</strong> Please <a href="%s">update it now</a>.',
                            $this->pageSlug
                        ),
                        $url
                    );
                }
            } else {
                $message = sprintf(
                    __('<strong>An unknown object cache drop-in was found</strong>. To use WP Object Cache , <a href="%s">please replace it now</a>.', $this->pageSlug),
                    $url
                );
            }

            if (isset($message)) {
                $this->printNotice($message);
            }
        } else {
            $enableUrl = wp_nonce_url(
                network_admin_url(add_query_arg('action', 'enable-cache', $this->page)),
                'enable-cache'
            );

            $message = sprintf(
                __(
                    '<strong>WP Object Cache is not used.</strong> To use WP Object Cache , <a href="%s">please enable it now</a>.',
                    $this->pageSlug
                ),
                $enableUrl
            );

            $this->printNotice($message);
        }
    }

    /**
     * @param string[] $actions
     *
     * @return string[]
     */
    public function addPluginActionsLinks(array $actions): array
    {
        $links = [
            sprintf(
                '<a href="%s">%s</a>',
                esc_url(network_admin_url($this->page)),
                esc_attr__('Settings', $this->pageSlug)
            ),
        ];

        if ($this->isRedisEnabled()) {
            $links[] = $this->getLink('flush-cache', 'Flush Cache');
        }
        if (!$this->objectCacheDropinExists()) {
            $links[] = $this->getLink('enable-cache', 'Enable Cache');
        }

        if ($this->validateObjectCacheDropin()) {
            $links[] = $this->getLink('disable-cache', 'Disable Cache');
        }

        return array_merge($links, $actions);
    }

    public function showAdminPage(): void
    {
        $action = filter_input(INPUT_GET, 'action');
        $nonce = filter_input(INPUT_GET, '_wpnonce');

        // request filesystem credentials?
        if (!empty($action) && !empty($nonce)) {
            foreach ($this->actions as $name) {
                // verify nonce
                if ($action === $name && wp_verify_nonce($nonce, $action)) {
                    $url = wp_nonce_url(network_admin_url(add_query_arg('action', $action, $this->page)), $action);

                    if (false === $this->initFilesystem($url)) {
                        return; // request filesystem credentials
                    }
                }
            }
        }

        if (!$this->validateObjectCacheDropin()) {
            return;
        }

        $redis = wp_object_cache_instance();

        $content = sprintf(
            '<p class="submit">%s<br/>%s<br/>%s</p>',
            $this->isRedisEnabled() ?
                $this->buildAdminLink(
                    'Flush Cache',
                    wp_nonce_url(network_admin_url(add_query_arg('action', 'flush-cache', $this->page)), 'flush-cache')
                ) :
                '',
            !$this->objectCacheDropinExists() ?
                $this->buildAdminLink(
                    'Enable Object Cache',
                    wp_nonce_url(
                        network_admin_url(add_query_arg('action', 'enable-cache', $this->page)),
                        'enable-cache'
                    )
                ) :
                '',
            $this->validateObjectCacheDropin() ?
                $this->buildAdminLink(
                    'Disable Object Cache',
                    wp_nonce_url(
                        network_admin_url(add_query_arg('action', 'disable-cache', $this->page)),
                        'disable-cache'
                    )
                ) : ''
        );

        $content .= $this->buildFormTable('Redis COMANDSTATS', $redis->info('COMANDSTATS'));
        $content .= $this->buildFormTable('Redis KEYSPACE', $redis->info('KEYSPACE'));
        $content .= $this->buildFormTable('Redis CLASTER', $redis->info('CLASTER'));
        $content .= $this->buildFormTable('Redis CPU', $redis->info('CPU'));
        $content .= $this->buildFormTable('Redis REPLICATION', $redis->info('REPLICATION'));
        $content .= $this->buildFormTable('Redis PERSISTENCE', $redis->info('PERSISTENCE'));
        $content .= $this->buildFormTable('Redis MEMORY', $redis->info('MEMORY'));
        $content .= $this->buildFormTable('Redis CLIENTS', $redis->info('CLIENTS'));
        $content .= $this->buildFormTable('Redis SERVER', $redis->info('SERVER'));
        $content .= $this->buildFormTable('Redis STATS', $redis->info('STATS'));

        $this->buildAdminWrapper($content);
    }

    private function printNotice(string $message): void
    {
        printf('<div class="update-nag notice notice-warning">%s</div>', $message);
    }

    private function buildAdminLink(string $label, string $link): string
    {
        return sprintf(
            '<a href="%s" class="button button-primary button-large">%s</a>',
            $link,
            esc_html($label)
        );
    }

    private function buildAdminWrapper(string $content): void
    {
        printf(
            '<div class="wrap"><h1>%s</h1><div class="section-overview">%s</div></div>',
            esc_html__('WP Object Cache', $this->pageSlug),
            $content
        );
    }


    private function buildFormTable(string $label, array $info): string
    {
        $columnts = '';

        foreach ($info as $key => $item) {
            $name = app_get_human_friendly($key);

            $columnts .= sprintf(
                '<tr><th>%s</th><td><code>%s</code></td></tr>',
                esc_attr($name),
                esc_attr($item)
            );
        }

        return sprintf(
            '<div class="section-overview"><h2 class="title">%s</h2><table class="fixed striped widefat wp-list-table"><tbody>%s</tbody></table><div',
            esc_attr($label),
            $columnts
        );
    }

    /**
     * @param  string  $url
     * @param  bool  $silent
     *
     * @return bool
     */
    private function initFilesystem(string $url, bool $silent = false): bool
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

        if (!WP_Filesystem($credentials)) {
            request_filesystem_credentials($url);

            if ($silent) {
                ob_end_clean();
            }

            return false;
        }

        return true;
    }

    private function objectCacheDropinExists(): bool
    {
        return is_readable($this->wpDropinFile);
    }

    private function validateObjectCacheDropin(): bool
    {
        if (!$this->objectCacheDropinExists()) {
            return false;
        }

        $dropin = get_plugin_data($this->wpDropinFile);

        $plugin = get_plugin_data($this->dropinFile);

        return $dropin['PluginURI'] === $plugin['PluginURI'];
    }

    private function getLink(string $action = 'flush-cache', string $label = 'Flush Cache'): string
    {
        $action = esc_attr($action);

        return sprintf(
            '<a href="%s">%s</a>',
            wp_nonce_url(
                network_admin_url(add_query_arg('action', $action, $this->page)),
                $action
            ),
            esc_html__(sanitize_text_field($label), $this->pageSlug)
        );
    }

    private function isRedisEnabled(): bool
    {
        if ($this->validateObjectCacheDropin()) {
            return wp_object_redis_status();
        }

        return false;
    }

    public function doAdminActions()
    {
        // @var \WP_Filesystem_Direct $wp_filesystem

        global $wp_filesystem;

        $action = filter_input(INPUT_GET, 'action');
        $nonce = filter_input(INPUT_GET, '_wpnonce');

        if (!empty($action) && !empty($nonce)) {
            // verify nonce
            foreach ($this->actions as $name) {
                if ($action === $name && !wp_verify_nonce($nonce, $action)) {
                    return;
                }
            }

            if (in_array($action, $this->actions)) {
                $url = wp_nonce_url(network_admin_url(add_query_arg('action', $action, $this->page)), $action);

                if ('flush-cache' === $action) {
                    $message = wp_cache_flush() ? 'cache-flushed' : 'flush-cache-failed';
                }

                if ($this->initFilesystem($url, true)) {
                    switch ($action) {
                        case 'enable-cache':
                            $result = $wp_filesystem->copy($this->dropinFile, $this->wpDropinFile, true);
                            $message = $result ? 'cache-enabled' : 'enable-cache-failed';

                            break;

                        case 'disable-cache':
                            $result = $wp_filesystem->delete($this->wpDropinFile);
                            $message = $result ? 'cache-disabled' : 'disable-cache-failed';

                            break;

                        case 'update-dropin':
                            $result = $wp_filesystem->copy($this->dropinFile, $this->wpDropinFile, true);
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
            add_settings_error('', $this->pageSlug, __('This plugin requires PHP 5.4 or greater.', $this->pageSlug));
        }

        $message_code = filter_input(INPUT_GET, 'message');

        $message = false;

        $error = false;

        // show action success/failure messages
        if (!empty($message_code)) {
            switch ($message_code) {
                case 'cache-enabled':
                    $message = __('Object cache enabled.', $this->pageSlug);

                    break;

                case 'enable-cache-failed':
                    $error = __('Object cache could not be enabled.', $this->pageSlug);

                    break;

                case 'cache-disabled':
                    $message = __('Object cache disabled.', $this->pageSlug);

                    break;

                case 'disable-cache-failed':
                    $error = __('Object cache could not be disabled.', $this->pageSlug);

                    break;

                case 'cache-flushed':
                    $message = __('Object cache flushed.', $this->pageSlug);

                    break;

                case 'flush-cache-failed':
                    $error = __('Object cache could not be flushed.', $this->pageSlug);

                    break;

                case 'dropin-updated':
                    $message = __('Updated object cache drop-in and enabled Redis object cache.', $this->pageSlug);

                    break;

                case 'update-dropin-failed':
                    $error = __('Object cache drop-in could not be updated.', $this->pageSlug);

                    break;
            }

            add_settings_error('', $this->pageSlug, $message ?? $error, isset($message) ? 'updated' : 'error');
        }
    }

    public function onDeactivation(string $plugin)
    {
        // @var \WP_Filesystem_Direct $wp_filesystem

        global $wp_filesystem;

        if ($plugin === plugin_basename(__FILE__)) {
            wp_cache_flush();

            if ($this->validateObjectCacheDropin() && $this->initFilesystem('', true)) {
                $wp_filesystem->delete($this->wpDropinFile);
            }
        }
    }
}

new WPObjectCache();
