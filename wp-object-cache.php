<?php

declare( strict_types=1 );

/**
 * Plugin Name: WP Object Cache
 * Description: Redis, Memcached or Apcu backend for the WP Object Cache
 * Version: v2.0
 * Plugin URI: https://github.com/Jazz-Man/wp-object-cache
 * Author: Vasyl Sokolyk
 * Text Domain: wp-object-cache
 * Domain Path: /languages.
 */

/**
 * Class WPObjectCache.
 */
class WPObjectCache {

    private readonly string $page;

    private string $pageSlug = 'wp-object-cache';

    private readonly string $baseOptionsPage;

    /**
     * @var string[]
     */
    private array $actions = ['enable-cache', 'disable-cache', 'flush-cache', 'update-dropin'];

    private readonly string $capability;

    private readonly string $rootDir;

    private readonly string $dropinFile;

    private readonly string $wpDropinFile;

    public function __construct() {
        $this->rootDir = plugin_dir_path( __FILE__ );

        register_activation_hook( __FILE__, 'wp_cache_flush' );
        register_deactivation_hook( __FILE__, $this->onDeactivation( ... ) );

        $isMultisite = is_multisite();

        $this->dropinFile = "{$this->rootDir}include/object-cache.php";
        $this->wpDropinFile = WP_CONTENT_DIR.'/object-cache.php';

        $this->capability = $isMultisite ? 'manage_network_options' : 'manage_options';

        $adminMenu = $isMultisite ? 'network_admin_menu' : 'admin_menu';

        $screen = "settings_page_{$this->pageSlug}";

        $this->baseOptionsPage = $isMultisite ? 'settings.php' : 'options-general.php';

        $this->page = "{$this->baseOptionsPage}?page={$this->pageSlug}";

        add_action( $adminMenu, $this->addAdminMenuPage( ... ) );
        add_action( 'plugins_loaded', $this->loadPluginTextdomain( ... ) );

        add_action( "load-{$screen}", $this->doAdminActions( ... ) );
        add_action( "load-{$screen}", $this->addAdminPageNotices( ... ) );

        add_action( 'admin_notices', $this->showAdminNotices( ... ) );

        $filter = sprintf( '%splugin_action_links_%s', $isMultisite ? 'network_admin_' : '', plugin_basename( __FILE__ ) );
        add_filter( $filter, $this->addPluginActionsLinks( ... ) );
    }

    public function loadPluginTextdomain(): void {
        load_plugin_textdomain( 'wp-object-cache', false, dirname( plugin_basename( __FILE__ ) ).'/languages/' );
    }

    public function addAdminMenuPage(): void {
        // add sub-page to "Settings"
        add_submenu_page(
            $this->baseOptionsPage,
            __( 'WP Object Cache', 'wp-object-cache' ),
            __( 'WP Object Cache', 'wp-object-cache' ),
            $this->capability,
            $this->pageSlug,
            $this->showAdminPage( ... )
        );
    }

    public function showAdminNotices(): void {
        // only show admin notices to users with the right capability
        if ( ! current_user_can( $this->capability ) ) {
            return;
        }

        if ( $this->objectCacheDropinExists() ) {
            $url = $this->getNonceUrl( 'update-dropin' );

            if ( $this->validateObjectCacheDropin() ) {
                $dropin = get_plugin_data( $this->wpDropinFile );
                $plugin = get_plugin_data( $this->dropinFile );

                if ( version_compare( $dropin['Version'], $plugin['Version'], '<' ) ) {
                    $message = sprintf(
                        __(
                            '<strong>The object cache drop-in is outdated.</strong> Please <a href="%s">update it now</a>.',
                            'wp-object-cache'
                        ),
                        $url
                    );
                }
            } else {
                $message = sprintf(
                    __(
                        '<strong>An unknown object cache drop-in was found</strong>. To use WP Object Cache , <a href="%s">please replace it now</a>.',
                        'wp-object-cache'
                    ),
                    $url
                );
            }

            if ( isset( $message ) ) {
                $this->printNotice( $message );
            }
        } else {
            $enableUrl = $this->getNonceUrl( 'enable-cache' );

            $message = sprintf(
                __(
                    '<strong>WP Object Cache is not used.</strong> To use WP Object Cache , <a href="%s">please enable it now</a>.',
                    'wp-object-cache'
                ),
                $enableUrl
            );

            $this->printNotice( $message );
        }
    }

    /**
     * @param string[] $actions
     *
     * @return string[]
     *
     * @psalm-return array{0: string,...}
     */
    public function addPluginActionsLinks( array $actions ): array {
        $links = [
            sprintf(
                '<a href="%s">%s</a>',
                esc_url( network_admin_url( $this->page ) ),
                esc_attr( $this->getLinkLabel( 'settings' ) )
            ),
        ];

        if ( $this->isRedisEnabled() ) {
            $links[] = $this->getLink();
        }

        if ( ! $this->objectCacheDropinExists() ) {
            $links[] = $this->getLink( 'enable-cache' );
        }

        if ( $this->validateObjectCacheDropin() ) {
            $links[] = $this->getLink( 'disable-cache' );
        }

        return array_merge( $links, $actions );
    }

    public function showAdminPage(): void {
        $request = $this->verifyNonceFromRequest();

        // request filesystem credentials?
        if ( ! empty( $request ) ) {
            $url = $this->getNonceUrl( $request['action'] );

            if ( false === $this->initFilesystem( $url ) ) {
                return; // request filesystem credentials
            }
        }

        if ( ! $this->validateObjectCacheDropin() ) {
            return;
        }

        if ( ! wp_object_redis_status() ) {
            return;
        }

        $redis = wp_object_cache_instance();

        $adminLinkAttr = [
            'class' => 'button button-primary button-large',
        ];

        $content = sprintf(
            '<p class="submit">%s<br/>%s<br/>%s</p>',
            $this->isRedisEnabled() ? $this->getLink( 'flush-cache', $adminLinkAttr ) : '',
            $this->objectCacheDropinExists() ? '' : $this->getLink( 'enable-cache', $adminLinkAttr ),
            $this->getLink( 'disable-cache', $adminLinkAttr )
        );

        $infoCommands = [
            'COMANDSTATS',
            'KEYSPACE',
            'CLASTER',
            'CPU',
            'REPLICATION',
            'PERSISTENCE',
            'MEMORY',
            'CLIENTS',
            'SERVER',
            'STATS',
        ];

        foreach ( $infoCommands as $infoCommand ) {
            $content .= $this->buildFormTable( "Redis {$infoCommand}", $redis->info( $infoCommand ) );
        }

        $this->buildAdminWrapper( $content );
    }

    public function doAdminActions(): void {

        /** @var WP_Filesystem_Direct $wp_filesystem */
        global $wp_filesystem;

        $request = $this->verifyNonceFromRequest();

        if ( ! empty( $request ) ) {
            $url = $this->getNonceUrl( $request['action'] );

            if ( 'flush-cache' === $request['action'] ) {
                $message = wp_cache_flush() ? 'cache-flushed' : 'flush-cache-failed';
            }

            if ( $this->initFilesystem( $url, true ) ) {
                switch ( $request['action'] ) {
                    case 'enable-cache':
                        $result = $wp_filesystem->copy( $this->dropinFile, $this->wpDropinFile, true );
                        $message = $result ? 'cache-enabled' : 'enable-cache-failed';

                        break;

                    case 'disable-cache':
                        $result = $wp_filesystem->delete( $this->wpDropinFile );
                        $message = $result ? 'cache-disabled' : 'disable-cache-failed';

                        break;

                    case 'update-dropin':
                        $result = $wp_filesystem->copy( $this->dropinFile, $this->wpDropinFile, true );
                        $message = $result ? 'dropin-updated' : 'update-dropin-failed';

                        break;
                }
            }

            // redirect if status `$message` was set
            if ( isset( $message ) ) {
                wp_safe_redirect( network_admin_url( add_query_arg( 'message', $message, $this->page ) ) );

                exit( 0 );
            }
        }
    }

    public function addAdminPageNotices(): void {
        $message_code = filter_input( INPUT_GET, 'message' );

        if ( empty( $message_code ) ) {
            return;
        }

        $message = false;

        $error = false;

        // show action success/failure messages
        switch ( $message_code ) {
            case 'cache-enabled':
                $message = __( 'Object cache enabled.', 'wp-object-cache' );

                break;

            case 'enable-cache-failed':
                $error = __( 'Object cache could not be enabled.', 'wp-object-cache' );

                break;

            case 'cache-disabled':
                $message = __( 'Object cache disabled.', 'wp-object-cache' );

                break;

            case 'disable-cache-failed':
                $error = __( 'Object cache could not be disabled.', 'wp-object-cache' );

                break;

            case 'cache-flushed':
                $message = __( 'Object cache flushed.', 'wp-object-cache' );

                break;

            case 'flush-cache-failed':
                $error = __( 'Object cache could not be flushed.', 'wp-object-cache' );

                break;

            case 'dropin-updated':
                $message = __( 'Updated object cache drop-in and enabled Redis object cache.', 'wp-object-cache' );

                break;

            case 'update-dropin-failed':
                $error = __( 'Object cache drop-in could not be updated.', 'wp-object-cache' );

                break;
        }

        add_settings_error( '', $this->pageSlug, $message ?? $error, isset( $message ) ? 'updated' : 'error' );
    }

    public function onDeactivation( string $plugin ): void {

        /** @var WP_Filesystem_Direct $wp_filesystem */
        global $wp_filesystem;

        if ( plugin_basename( __FILE__ ) === $plugin ) {
            wp_cache_flush();

            if ( $this->validateObjectCacheDropin() && $this->initFilesystem( '', true ) ) {
                $wp_filesystem->delete( $this->wpDropinFile );
            }
        }
    }

    private function objectCacheDropinExists(): bool {
        return is_readable( $this->wpDropinFile );
    }

    private function getNonceUrl( string $action ): string {
        return wp_nonce_url(
            network_admin_url( add_query_arg( 'action', $action, $this->page ) ),
            $action
        );
    }

    private function validateObjectCacheDropin(): bool {
        if ( ! $this->objectCacheDropinExists() ) {
            return false;
        }

        $dropin = get_plugin_data( $this->wpDropinFile );

        $plugin = get_plugin_data( $this->dropinFile );

        return $dropin['PluginURI'] === $plugin['PluginURI'];
    }

    private function printNotice( string $message ): void {
        printf( '<div class="update-nag notice notice-warning">%s</div>', $message );
    }

    private function getLinkLabel( string $action = 'enable-cache' ): string {

        return match ( $action ) {
            default => __( 'Enable Object Cache', 'wp-object-cache' ),
            'disable-cache' => __( 'Disable Object Cache', 'wp-object-cache' ),
            'flush-cache' => __( 'Flush Cache', 'wp-object-cache' ),
            'settings' => __( 'Settings', 'wp-object-cache' ),
        };
    }

    private function isRedisEnabled(): bool {
        if ( $this->validateObjectCacheDropin() ) {
            return wp_object_redis_status();
        }

        return false;
    }

    private function getLink( string $action = 'flush-cache', array $linkAttr = [] ): string {
        return sprintf(
            '<a href="%s" %s>%s</a>',
            $this->getNonceUrl( $action ),
            app_add_attr_to_el( $linkAttr ),
            esc_html( $this->getLinkLabel( $action ) )
        );
    }

    /**
     * @return false|string[]
     *
     * @psalm-return array{action: string, _wpnonce: string}|false
     */
    private function verifyNonceFromRequest(): array|bool {
        $actionsList = $this->actions;

        /** @var array{action:string, _wpnonce: string}|false $request */
        $request = filter_input_array(
            INPUT_GET,
            [
                'action' => [
                    'filter' => FILTER_CALLBACK,
                    'options' => static fn ( string $action ): false|string => in_array( $action, $actionsList, true ) ? $action : false,
                ],
                '_wpnonce' => [
                    'filter' => FILTER_DEFAULT,
                    'flags' => FILTER_REQUIRE_SCALAR,
                ],
            ],
            false
        );

        if ( empty( $request ) ) {
            return false;
        }

        return wp_verify_nonce( $request['_wpnonce'], $request['action'] ) ? $request : false;
    }

    private function initFilesystem( string $url, bool $silent = false ): bool {
        if ( $silent ) {
            ob_start();
        }

        $credentials = request_filesystem_credentials( $url );

        if ( false === $credentials ) {
            if ( $silent ) {
                ob_end_clean();
            }

            return false;
        }

        if ( ! WP_Filesystem( $credentials ) ) {
            request_filesystem_credentials( $url );

            if ( $silent ) {
                ob_end_clean();
            }

            return false;
        }

        return true;
    }

    /**
     * @param array{string,string} $info
     */
    private function buildFormTable( string $label, array $info ): string {
        $columnts = '';

        foreach ( $info as $key => $item ) {
            $name = app_get_human_friendly( $key );

            $columnts .= sprintf(
                '<tr><th>%s</th><td><code>%s</code></td></tr>',
                esc_attr( $name ),
                esc_attr( $item )
            );
        }

        return sprintf(
            '<div class="section-overview"><h2 class="title">%s</h2><table class="fixed striped widefat wp-list-table"><tbody>%s</tbody></table><div',
            esc_attr( $label ),
            $columnts
        );
    }

    private function buildAdminWrapper( string $content ): void {
        printf(
            '<div class="wrap"><h1>%s</h1><div class="section-overview">%s</div></div>',
            __( 'WP Object Cache', 'wp-object-cache' ),
            $content
        );
    }
}

new WPObjectCache();
