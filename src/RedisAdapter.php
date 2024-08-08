<?php

declare( strict_types=1 );

namespace JazzMan\WPObjectCache;

use Exception;
use JetBrains\PhpStorm\ExpectedValues;
use Redis;
use Throwable;

/**
 * Class RedisAdapter.
 */
class RedisAdapter {

    /**
     * List of global groups.
     *
     * @var string[]
     */
    private array $global_groups = [
        'blog-details',
        'blog-id-cache',
        'blog-lookup',
        'global-posts',
        'networks',
        'rss',
        'sites',
        'site-details',
        'site-lookup',
        'site-options',
        'site-transient',
        'users',
        'useremail',
        'userlogins',
        'usermeta',
        'user_meta',
        'userslugs',
    ];

    /**
     * List of groups not saved to Redis.
     *
     * @var string[]
     */
    private array $ignored_groups = [ 'counts', 'plugins' ];

    /**
     * Prefix used for non-global groups.
     */
    private int|string $blog_prefix = '';

    private ?Redis $redis = null;

    /**
     * Track if Redis is available.
     */
    private bool $is_connected = false;

    private readonly bool $is_multisite;

    public function __construct() {
        /**
         * @var string|null     $table_prefix
         * @var int|string|null $blog_id
         */
        global $blog_id, $table_prefix;

        $this->is_multisite = \function_exists( 'is_multisite' ) && is_multisite();

        $this->blog_prefix = $this->is_multisite ? (int) $blog_id : (string) $table_prefix;

        $ignored_groups = \defined( 'WP_REDIS_IGNORED_GROUPS' ) ? \constant( 'WP_REDIS_IGNORED_GROUPS' ) : false;

        if ( \is_array( $ignored_groups ) ) {
            $this->ignored_groups = $ignored_groups;
        }

        $this->connect();
    }

    /**
     * Is Redis available?
     */
    public function redis_status(): bool {
        return $this->is_connected;
    }

    /**
     * Returns the Redis instance.
     */
    public function get_redis(): ?Redis {
        return $this->redis_status() ? $this->redis : null;
    }

    /**
     * Remove the item from the cache.
     *
     * @param string|string[] $keys  the key under which to store the value
     * @param string          $group the group value appended to the $key
     */
    public function delete( array|string $keys, string $group = 'default' ): bool|int|Redis|null {

        if ( ! $this->can_modify( $group ) ) {
            return false;
        }

        $_keys = array_map( static fn ( string $key ): string => self::sanitize_key( $key ), (array) $keys );

        try {
            $result = $this->redis?->hDel( $group, ...$_keys );
        } catch ( Exception $exception ) {
            $this->handle_exception( $exception );

            $result = false;
        }

        return $result;
    }

    /**
     * @param int $delay number of seconds to wait before invalidating the items
     */
    public function flush( int $delay = 0 ): bool|Redis|null {

        if ( ! $this->redis_status() ) {
            return false;
        }

        $delay = abs( $delay );

        if ( 0 !== $delay ) {
            sleep( $delay );
        }

        try {
            $results = $this->redis?->flushDB();
        } catch ( Exception $exception ) {
            $this->handle_exception( $exception );

            $results = false;
        }

        return $results;
    }

    /**
     * @param string|string[] $groups
     */
    public function flush_group( array|string $groups ): false|int|Redis|null {

        $list = array_filter( (array) $groups, fn ( $group ): bool => $this->can_modify( $group ) );

        if ( empty( $list ) ) {
            return false;
        }

        try {
            $result = $this->redis?->del( ...$list );
        } catch ( Exception $exception ) {
            $this->handle_exception( $exception );

            $result = false;
        }

        return $result;
    }

    /**
     * Retrieve object from cache.
     *
     * Gets an object from cache based on $key and $group.
     *
     * @param string $key   the key under which to store the value
     * @param string $group the group value appended to the $key
     */
    public function get( string $key, string $group = 'default' ): mixed {
        $key = self::sanitize_key( $key );

        try {
            $result = $this->redis?->hGet( $group, $key );
        } catch ( Exception $exception ) {
            $this->handle_exception( $exception );

            return false;
        }

        return $result;
    }

    /**
     * @param string[] $keys
     */
    public function get_multiple( array $keys, string $group = 'default' ): mixed {

        $keys = array_map( static fn ( $key ): string => self::sanitize_key( $key ), $keys );

        try {
            $result = $this->redis?->hMGet( $group, $keys );
        } catch ( Exception $exception ) {
            $this->handle_exception( $exception );

            return false;
        }

        return $result;
    }

    /**
     * Sets a value in cache.
     *
     * The value is set whether or not this key already exists in Redis.
     */
    public function set( string $key, mixed $value, string $group = 'default' ): bool|int|Redis|null {

        if ( ! $this->can_modify( $group ) ) {
            return false;
        }

        $key = self::sanitize_key( $key );

        try {

            $result = $this->redis?->hSet( $group, $key, $value );
        } catch ( Exception $exception ) {
            $this->handle_exception( $exception );

            $result = false;
        }

        return $result;
    }

    /**
     * @param array{string: mixed} $data
     */
    public function set_multiple( array $data, string $group = 'default' ): bool|Redis|null {

        if ( ! $this->can_modify( $group ) ) {
            return false;
        }

        foreach ( $data as $key => $value ) {
            $key = self::sanitize_key( $key );
            $data[ $key ] = $value;
        }

        try {

            $result = $this->redis?->hMSet( $group, $data );
        } catch ( Exception $exception ) {
            $this->handle_exception( $exception );

            $result = false;
        }

        return $result;
    }

    /**
     * Increment a Redis counter by the amount specified.
     */
    public function increment( string $key, float|int $offset = 1, string $group = 'default' ): false|float|int|Redis|null {

        if ( ! $this->can_modify( $group ) ) {
            return false;
        }

        $key = self::sanitize_key( $key );

        $is_float = \is_float( $offset );

        try {
            $result = $is_float ? $this->redis?->hIncrByFloat( $group, $key, (float) $offset ) : $this->redis?->hIncrBy( $group, $key, (int) $offset );

        } catch ( Exception $exception ) {
            $this->handle_exception( $exception );

            return false;
        }

        return $result;
    }

    /**
     * Decrement a Redis counter by the amount specified.
     */
    public function decrement( string $key, float|int $offset = 1, string $group = 'default' ): false|float|int|Redis|null {

        $offset = -1 * abs( $offset );

        return $this->increment( $key, $offset, $group );
    }

    /**
     * Render data about current cache requests.
     */
    public function stats(): void {

        if ( ! $this->redis_status() ) {
            echo '<p><strong>Redis Status:</strong> Not Connected</p>';

            return;

        }

        try {

            /** @var array{keyspace_hits: int|float, keyspace_misses: int|float}|null $info */
            $info = $this->redis?->info( 'STATS' );

            if ( empty( $info ) ) {
                return;
            }

            $hits = $info['keyspace_hits'];
            $misses = $info['keyspace_misses'];
            $ratio = $hits / ( $hits + $misses );

            printf(
                '<p>
<strong>Redis Status:</strong> Connected <br/>
<strong>Cache Hits:</strong> %s <br/>
<strong>Cache misses:</strong> %s <br/>
<strong>Hit/Miss Ratio:</strong> %s <br/>
</p>',
                esc_attr( (string) $hits ),
                esc_attr( (string) $misses ),
                esc_attr( (string) $ratio )
            );
        } catch ( Exception ) {
            return;
        }
    }

    /**
     * In multisite, switch blog prefix when switching blogs.
     */
    public function switch_to_blog( int|string $blogId ): bool {
        if ( ! $this->is_multisite ) {
            return false;
        }

        $this->blog_prefix = $blogId;

        return true;
    }

    /**
     * Sets the list of global groups.
     *
     * @param string|string[] $groups list of groups that are global
     */
    public function add_global_groups( array|string $groups ): void {
        if ( $this->redis_status() ) {
            $this->global_groups = array_unique( array_merge( $this->global_groups, (array) $groups ) );
        } else {
            $this->ignored_groups = array_unique( array_merge( $this->ignored_groups, (array) $groups ) );
        }
    }

    /**
     * Sets the list of groups not to be cached by Redis.
     *
     * @param string|string[] $groups list of groups that are to be ignored
     */
    public function add_non_persistent_groups( array|string $groups ): void {
        $groups = (array) $groups;

        $this->ignored_groups = array_unique( array_merge( $this->ignored_groups, $groups ) );
    }

    private function connect(): void {
        try {
            $this->redis = new Redis();

            $params = $this->get_connection_params();

            $this->redis->pconnect(
                host: $params['host'],
                port: $params['port'],
                timeout: $params['timeout'],
                persistent_id: $params['persistent_id'],
                retry_interval: $params['retry_interval'],
                read_timeout: $params['read_timeout']
            );

            $this->redis->setOption( Redis::OPT_SERIALIZER, $params['serializer'] );

            $this->redis->setOption( Redis::OPT_COMPRESSION, $params['compression'] );

            if ( Redis::COMPRESSION_NONE !== $params['compression'] && ! empty( $params['compression_level'] ) ) {
                $this->redis->setOption( Redis::OPT_COMPRESSION_LEVEL, $params['compression_level'] );
            }

            $this->redis->setOption( Redis::OPT_SCAN, Redis::SCAN_PREFIX );

            $this->redis->setOption( Redis::OPT_PREFIX, "{$params['prefix']}:" );

            $this->is_connected = $this->redis->isConnected();
        } catch ( Exception $exception ) {
            $this->handle_exception( $exception );
        }
    }

    /**
     * @return array{host: string, port: int, timeout: int, persistent_id: ?string, prefix: string, retry_interval: int, read_timeout: int, serializer: int, compression: int, compression_level: ?int}
     */
    private function get_connection_params(): array {

        /**
         * @param string   $constant_name
         * @param string[] $list
         *
         * @return array{string,int}
         */
        $get_valid_redis_list = static function (
            #[ExpectedValues( values: [
                'SERIALIZER',
                'COMPRESSION',
            ] )]
            string $constant_name,
            array $list
        ): array {

            $data = [];

            /** @var string[] $list */
            foreach ( $list as $item ) {
                $constant = sprintf( Redis::class.'::%s_%s', $constant_name, strtoupper( $item ) );

                if ( \defined( $constant ) ) {
                    $value = \constant( $constant );

                    if ( \is_int( $value ) ) {
                        $data[ $item ] = $value;
                    }
                }

            }

            return $data;
        };

        $serializer = \defined( 'Redis::SERIALIZER_MSGPACK' ) ? Redis::SERIALIZER_MSGPACK : Redis::SERIALIZER_PHP;
        $compression = \defined( 'Redis::COMPRESSION_LZF' ) ? Redis::COMPRESSION_LZF : Redis::COMPRESSION_NONE;

        /** @var array{host: string, port: int, timeout: int, persistent_id: ?string, prefix: string, retry_interval: int, read_timeout: int, serializer: int, compression: int, compression_level: ?int} $params */
        $params = [
            'host' => '127.0.0.1',
            'port' => 6379,
            'timeout' => 5,
            'persistent_id' => DB_NAME,
            'prefix' => implode( ':', [
                DB_NAME,
                $this->blog_prefix,
            ] ),
            'retry_interval' => 5,
            'read_timeout' => 5,
            'serializer' => $serializer,
            'compression' => $compression,
            'compression_level' => Redis::COMPRESSION_LZF === $compression ? 3 : null,
        ];

        /** @var array{string: int} $compressions */
        $compressions = $get_valid_redis_list(
            'COMPRESSION',
            [
                'none',
                'lzf',
                'zstd',
                'lz4',
            ]
        );

        /** @var array{string: int} $serializers */
        $serializers = $get_valid_redis_list(
            'SERIALIZER',
            [
                'none',
                'php',
                'igbinary',
                'msgpack',
                'json',
            ]
        );

        foreach ( array_keys( $params ) as $setting ) {

            $constant = sprintf( 'WP_REDIS_%s', strtoupper( $setting ) );

            if ( \defined( $constant ) ) {

                /** @var string|int $constant_val */
                $constant_val = \constant( $constant );

                switch ( $setting ) {
                    case 'serializer':
                        if ( ! empty( $serializers[ (string) $constant_val ] ) ) {
                            $constant_val = $serializers[ $constant_val ];
                        }

                        break;

                    case 'compression':
                        if ( ! empty( $compressions[ (string) $constant_val ] ) ) {
                            $constant_val = $compressions[ $constant_val ];
                        }

                        break;

                    case 'prefix':

                        $constant_val = rtrim( (string) $constant_val, ':' );

                        break;
                }

                $params[ $setting ] = $constant_val;
            }
        }

        return $params;
    }

    private static function sanitize_key( string $key ): string {

        return (string) preg_replace( '/[^a-z0-9_\-:]/', '', strtolower( $key ) );
    }

    /**
     * Checks if the given group is part the ignored group array.
     *
     * @param string $group Name of the group to check
     */
    private function is_ignored_group( string $group ): bool {
        return \in_array( $group, $this->ignored_groups, true );
    }

    /**
     * Handle the redis failure gracefully or throw an exception.
     *
     * @param Exception $throwable exception thrown
     *
     * @internal
     */
    private function handle_exception( Throwable $throwable ): void {
        $this->is_connected = false;

        // When Redis is unavailable, fall back to the internal cache by forcing all groups to be "no redis" groups
        $this->ignored_groups = array_unique( array_merge( $this->ignored_groups, $this->global_groups ) );

        error_log( $throwable );
    }

    private function can_modify( string $group = 'default' ): bool {
        if ( $this->is_ignored_group( $group ) ) {
            return false;
        }

        return $this->redis_status();
    }
}
