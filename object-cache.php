<?php

declare( strict_types=1 );

/**
 * Plugin Name: WP Object Cache Drop-In
 * Plugin URI: https://github.com/Jazz-Man/wp-object-cache
 * Description: Redis, Memcached or Apcu backend for the WP Object Cache
 * Author: Vasyl Sokolyk.
 *
 * @return true
 */

namespace {
    use JazzMan\WPObjectCache\RedisAdapter;

    function wp_cache_close(): bool {
        return true;
    }

    function wp_cache_add( string $key, mixed $value, string $group = 'default', int $expiration = 0 ): bool|int|Redis|null {

        return wp_object_cache()->set( $key, $value, $group );
    }

    /**
     * @param array{string: mixed} $data
     */
    function wp_cache_add_multiple( array $data, string $group = 'default', int $expiration = 0 ): bool|Redis|null {
        return wp_object_cache()->set_multiple( $data, $group );
    }

    function wp_cache_decr( string $key, float|int $offset = 1, string $group = 'default' ): false|float|int|Redis|null {
        return wp_object_cache()->decrement( $key, $offset, $group );
    }

    function wp_cache_delete( string $key, string $group = 'default' ): bool|int|Redis|null {
        return wp_object_cache()->delete( $key, $group );
    }

    /**
     * @param string[] $keys
     */
    function wp_cache_delete_multiple( array $keys, string $group = 'default' ): bool|int|Redis|null {

        return wp_object_cache()->delete( $keys, $group );
    }

    function wp_cache_flush(): bool|Redis|null {
        return wp_object_cache()->flush();
    }

    /**
     * @param string[]|string $group
     */
    function wp_cache_flush_group( array|string $group ): false|int|Redis|null {

        return wp_object_cache()->flush_group( $group );
    }

    function wp_cache_get( string $key, string $group = 'default', bool $force = false, ?bool &$found = null ): mixed {
        return wp_object_cache()->get( $key, $group );
    }

    /**
     * @param string[] $keys
     */
    function wp_cache_get_multiple( array $keys, string $group = 'default', bool $force = false, ?bool &$found = null ): array|false|Redis|null {

        return wp_object_cache()->get_multiple( $keys, $group );
    }

    function wp_cache_incr( string $key, float|int $offset = 1, string $group = 'default' ): false|float|int|Redis|null {

        return wp_object_cache()->increment( $key, $offset, $group );
    }

    function wp_cache_replace( string $key, mixed $value = null, string $group = 'default', int $expiration = 0 ): bool|int|Redis|null {
        return wp_object_cache()->set( $key, $value, $group );
    }

    function wp_cache_set( string $key, mixed $value = null, string $group = 'default', int $expiration = 0 ): bool|int|Redis|null {

        return wp_object_cache()->set( $key, $value, $group );
    }

    function wp_cache_set_multiple( array $data, string $group = 'default', int $expire = 0 ): bool|Redis|null {
        return wp_object_cache()->set_multiple( $data, $group );
    }

    /**
     * Switch blog prefix, which changes the cache that is accessed.
     */
    function wp_cache_switch_to_blog( int|string $blogId ): bool {
        return wp_object_cache()->switch_to_blog( $blogId );
    }

    /**
     * Adds a group or set of groups to the list of non-persistent groups.
     *
     * @param string|string[] $groups a group or an array of groups to add
     */
    function wp_cache_add_global_groups( array|string $groups ): void {
        wp_object_cache()->add_global_groups( $groups );
    }

    /**
     * Adds a group or set of groups to the list of non-persistent groups.
     *
     * @param string|string[] $groups a group or an array of groups to add
     */
    function wp_cache_add_non_persistent_groups( array|string $groups ): void {
        wp_object_cache()->add_non_persistent_groups( $groups );
    }

    /**
     * Sets up Object Cache Global and assigns it.
     */
    function wp_cache_init(): void {
        global $wp_object_cache;

        if ( ! $wp_object_cache instanceof RedisAdapter ) {
            $wp_object_cache = new RedisAdapter();
        }
    }

    function wp_object_cache_get_stats(): void {
        wp_object_cache()->stats();
    }

    function wp_object_cache(): RedisAdapter {
        global $wp_object_cache;
        wp_cache_init();

        /** @var RedisAdapter $wp_object_cache */

        return $wp_object_cache;
    }

    function wp_object_cache_instance(): ?Redis {
        return wp_object_cache()->get_redis();
    }

    function wp_object_redis_status(): bool {
        return wp_object_cache()->redis_status();
    }

    function wp_cache_supports( string $feature ): bool {
        return match ( $feature ) {
            'add_multiple',
            'set_multiple',
            'get_multiple',
            'delete_multiple',
            //        'flush_runtime',
            'flush_group' => true,
            default => false,
        };
    }
}

namespace JazzMan\WPObjectCache {

    use JetBrains\PhpStorm\ExpectedValues;
    use Redis;
    use RedisException;

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
            } catch ( RedisException $redisException ) {
                $this->handle_exception( $redisException );

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
            } catch ( RedisException $redisException ) {
                $this->handle_exception( $redisException );

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
            } catch ( RedisException $redisException ) {
                $this->handle_exception( $redisException );

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
            } catch ( RedisException $redisException ) {
                $this->handle_exception( $redisException );

                return false;
            }

            return $result;
        }

        /**
         * @param string[] $keys
         */
        public function get_multiple( array $keys, string $group = 'default' ): array|false|Redis|null {

            $keys = array_map( static fn ( $key ): string => self::sanitize_key( $key ), $keys );

            try {
                $result = $this->redis?->hMGet( $group, $keys );
            } catch ( RedisException $redisException ) {
                $this->handle_exception( $redisException );

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

            if ( $this->is_suspend_cache() ) {
                return false;
            }

            if ( ! $this->can_modify( $group ) ) {
                return false;
            }

            $key = self::sanitize_key( $key );

            try {

                $result = $this->redis?->hSet( $group, $key, $value );
            } catch ( RedisException $redisException ) {
                $this->handle_exception( $redisException );

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
            } catch ( RedisException $redisException ) {
                $this->handle_exception( $redisException );

                $result = false;
            }

            return $result;
        }

        public function increment( string $key, float|int $offset = 1, string $group = 'default' ): false|float|int|Redis|null {

            if ( ! $this->can_modify( $group ) ) {
                return false;
            }

            $key = self::sanitize_key( $key );

            $is_float = \is_float( $offset );

            try {
                $result = $is_float ? $this->redis?->hIncrByFloat( $group, $key, (float) $offset ) : $this->redis?->hIncrBy( $group, $key, (int) $offset );

            } catch ( RedisException $redisException ) {
                $this->handle_exception( $redisException );

                return false;
            }

            return $result;
        }

        public function decrement( string $key, float|int $offset = 1, string $group = 'default' ): false|float|int|Redis|null {

            $offset = -1 * abs( $offset );

            return $this->increment( $key, $offset, $group );
        }

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

        public function switch_to_blog( int|string $blogId ): bool {
            if ( ! $this->is_multisite ) {
                return false;
            }

            $this->blog_prefix = $blogId;

            return true;
        }

        /**
         * @param string|string[] $groups list of groups that are global
         */
        public function add_global_groups( array|string $groups ): void {
            $groups = (array) $groups;

            if ( $this->redis_status() ) {

                $this->global_groups = array_unique( [ ...$this->global_groups, ...$groups ] );

                return;
            }

            $this->add_non_persistent_groups( $groups );
        }

        /**
         * Sets the list of groups not to be cached by Redis.
         *
         * @param string|string[] $groups list of groups that are to be ignored
         */
        public function add_non_persistent_groups( array|string $groups ): void {
            $groups = (array) $groups;

            $this->ignored_groups = array_unique( [ ...$this->ignored_groups, ...$groups ] );
        }

        private function is_suspend_cache(): bool {
            return \function_exists( 'wp_suspend_cache_addition' ) && wp_suspend_cache_addition();
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
            } catch ( RedisException $redisException ) {
                $this->handle_exception( $redisException );
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
            $get_valid_redis_list = /**
             * @return int[]
             *
             * @psalm-return array<string, int>
             */
                static function (
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

            $serializer = \defined( 'Redis::SERIALIZER_IGBINARY' ) ? Redis::SERIALIZER_IGBINARY : Redis::SERIALIZER_PHP;
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
         * @param RedisException $redisException exception thrown
         *
         * @internal
         */
        private function handle_exception( RedisException $redisException ): void {
            $this->is_connected = false;

            // When Redis is unavailable, fall back to the internal cache by forcing all groups to be "no redis" groups
            $this->ignored_groups = array_unique( array_merge( $this->ignored_groups, $this->global_groups ) );

            error_log( $redisException );
        }

        private function can_modify( string $group = 'default' ): bool {
            if ( $this->is_ignored_group( $group ) ) {
                return false;
            }

            return $this->redis_status();
        }
    }
}
