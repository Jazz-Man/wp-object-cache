<?php

namespace JazzMan\WPObjectCache;

use Exception;
use Redis;

use function function_exists;

class RedisAdapter
{
    /**
     * Holds the non-Redis objects.
     *
     * @var array
     */
    public $cache = [];

    /**
     * Name of the used Redis client.
     *
     * @var bool
     */
    public $redis_client;

    /**
     * List of global groups.
     *
     * @var array
     */
    public $global_groups = [
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
     * @var array
     */
    public $ignored_groups = ['counts', 'plugins'];

    /**
     * Prefix used for global groups.
     *
     * @var string
     */
    public $global_prefix = '';

    /**
     * Prefix used for non-global groups.
     *
     * @var string
     */
    public $blog_prefix = '';

    /**
     * Track how many requests were found in cache.
     *
     * @var int
     */
    public $cache_hits = 0;

    /**
     * Track how may requests were not cached.
     *
     * @var int
     */
    public $cache_misses = 0;
    /**
     * @var Redis
     */
    private $redis;

    /**
     * The Redis server version.
     *
     * @var null|string
     */
    private $redis_version;

    /**
     * Track if Redis is available.
     *
     * @var bool
     */
    private $redis_connected = false;

    /**
     * Check to fail gracefully or throw an exception.
     *
     * @var bool
     */
    private $fail_gracefully = true;

    /**
     * Instantiate the Redis class.
     *
     * @param bool $fail_gracefully
     */
    public function __construct($fail_gracefully = true)
    {
        global $blog_id, $table_prefix;

        $this->fail_gracefully = $fail_gracefully;

        // Assign global and blog prefixes for use with keys
        if (function_exists('is_multisite')) {
            $this->global_prefix = (is_multisite() || (\defined('CUSTOM_USER_TABLE') && \defined('CUSTOM_USER_META_TABLE'))) ? '' : $table_prefix;
            $this->blog_prefix = (is_multisite() ? $blog_id : $table_prefix);
        }

        $parameters = [
            'scheme' => 'tcp',
            'host' => '127.0.0.1',
            'port' => 6379,
            'timeout' => 5,
            'read_timeout' => 5,
            'retry_interval' => null,
            'compression_level' => 5,
            'prefix' => DB_NAME,
        ];

        foreach (
            [
                'scheme',
                'host',
                'port',
                'path',
                'password',
                'database',
                'timeout',
                'read_timeout',
                'retry_interval',
                'prefix',
                'compression_level',
            ] as $setting
        ) {
            $constant = \sprintf('WP_REDIS_%s', \strtoupper($setting));

            if (\defined($constant)) {
                $parameters[$setting] = \constant($constant);
            }
        }

        if (\defined('WP_REDIS_IGNORED_GROUPS') && \is_array(WP_REDIS_IGNORED_GROUPS)) {
            $this->ignored_groups = WP_REDIS_IGNORED_GROUPS;
        }

        try {
            $phpredis_version = \phpversion('redis');

            $this->redis_client = \sprintf(
                'PhpRedis (v%s)',
                $phpredis_version
            );

            $this->redis = new Redis();

            $connection_args = [
                $parameters['host'],
                $parameters['port'],
                $parameters['timeout'],
                null,
                $parameters['retry_interval'],
            ];

            if (0 === \strcasecmp('tls', $parameters['scheme'])) {
                $connection_args[0] = \sprintf(
                    '%s://%s',
                    $parameters['scheme'],
                    \str_replace('tls://', '', $parameters['host'])
                );
            }

            if (0 === \strcasecmp('unix', $parameters['scheme'])) {
                $connection_args[0] = $parameters['path'];
                $connection_args[1] = null;
            }

            if (\version_compare($phpredis_version, '3.1.3', '>=')) {
                $connection_args[] = $parameters['read_timeout'];
            }

            \call_user_func_array(
                [$this->redis, 'connect'],
                $connection_args
            );

            if (isset($parameters['password'])) {
                $this->redis->auth($parameters['password']);
            }

            if (isset($parameters['database'])) {
                if (\ctype_digit($parameters['database'])) {
                    $parameters['database'] = (int) ($parameters['database']);
                }

                $this->redis->select($parameters['database']);
            }

            if (\defined('Redis::SERIALIZER_IGBINARY')) {
                $this->redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_IGBINARY);
            }

            if (\defined('Redis::COMPRESSION_LZF')) {
                $this->redis->setOption(Redis::OPT_COMPRESSION, Redis::COMPRESSION_LZF);

                $compression_level = \filter_var($parameters['compression_level'], FILTER_VALIDATE_INT, ['options' => [
                    'min_range' => 1,
                    'max_range' => 9,
                ]]);

                if ($compression_level) {
                    $this->redis->setOption(Redis::OPT_COMPRESSION_LEVEL, (int) $compression_level);
                }
            }

            if (!empty($parameters['prefix'])) {
                if (\defined('Redis::SCAN_PREFIX')) {
                    $this->redis->setOption(Redis::OPT_SCAN, Redis::SCAN_PREFIX);
                }
                $this->redis->setOption(Redis::OPT_PREFIX, "{$parameters['prefix']}_{$this->blog_prefix}:");
            }

            $this->redis->ping();

            $info = $this->redis->info();

            if (isset($info['redis_version'])) {
                $this->redis_version = $info['redis_version'];
            }

            $this->redis_connected = true;
        } catch (Exception $exception) {
            $this->handle_exception($exception);
        }
    }

    /**
     * Is Redis available?
     * @return bool
     */
    public function redis_status(): bool
    {
        return $this->redis_connected;
    }

    /**
     * Returns the Redis instance.
     *
     * @return mixed
     */
    public function redis_instance(): Redis
    {
        return $this->redis;
    }

    /**
     * Returns the Redis server version.
     * @return string|null
     */
    public function redis_version(): ?string
    {
        return $this->redis_version;
    }

    /**
     * Adds a value to cache.
     *
     * If the specified key already exists, the value is not stored and the function
     * returns false.
     *
     * @param string $key        the key under which to store the value
     * @param mixed  $value      the value to store
     * @param string $group      the group value appended to the $key
     * @param int    $expiration the expiration time, defaults to 0
     *
     * @return bool returns TRUE on success or FALSE on failure
     */
    public function add(string $key, $value, string $group = 'default', int $expiration = 0)
    {
        return $this->add_or_replace(true, $key, $value, $group, $expiration);
    }

    /**
     * Replace a value in the cache.
     *
     * If the specified key doesn't exist, the value is not stored and the function
     * returns false.
     *
     * @param string $key        the key under which to store the value
     * @param mixed  $value      the value to store
     * @param string $group      the group value appended to the $key
     * @param int    $expiration the expiration time, defaults to 0
     *
     * @return bool returns TRUE on success or FALSE on failure
     */
    public function replace(string $key, $value, string $group = 'default', int $expiration = 0)
    {
        return $this->add_or_replace(false, $key, $value, $group, $expiration);
    }

    /**
     * Remove the item from the cache.
     *
     * @param string $key   the key under which to store the value
     * @param string $group the group value appended to the $key
     *
     * @return bool returns TRUE on success or FALSE on failure
     */
    public function delete(string $key, string $group = 'default'): bool
    {
        $result = false;
        $derived_key = self::build_key($key, $group);

        if (isset($this->cache[$derived_key])) {
            unset($this->cache[$derived_key]);
            $result = true;
        }

        if ($this->redis_status() && !$this->is_ignored_group($group)) {
            try {
                $result = $this->redis->del($derived_key);
            } catch (Exception $exception) {
                $this->handle_exception($exception);

                $result = false;
            }
        }

        return $result;
    }

    /**
     * Invalidate all items in the cache. If `WP_REDIS_SELECTIVE_FLUSH` is `true`,
     * only keys prefixed with the `WP_CACHE_KEY_SALT` are flushed.
     *
     * @param float|int $delay number of seconds to wait before invalidating the items
     *
     * @return bool returns TRUE on success or FALSE on failure
     */
    public function flush(int $delay = 0): bool
    {
        $delay = \abs((int) $delay);

        if ($delay) {
            \sleep($delay);
        }

        $results = true;
        $this->cache = [];

        if ($this->redis_status()) {
            try {
                $results = $this->redis->flushdb();
            } catch (Exception $exception) {
                $this->handle_exception($exception);

                $results = false;
            }
        }

        return $results;
    }

    /**
     * Retrieve object from cache.
     *
     * Gets an object from cache based on $key and $group.
     *
     * @param string $key    the key under which to store the value
     * @param string $group  the group value appended to the $key
     * @param bool   $force  Optional. Whether to force a refetch rather than relying on the local
     *                       cache. Default false.
     * @param bool   &$found Optional. Whether the key was found in the cache. Disambiguates a return of
     *                       false, a storable value. Passed by reference. Default null.
     *
     * @return mixed cached object value
     */
    public function get(string $key, $group = 'default', bool $force = false, bool &$found = null)
    {
        $derived_key = self::build_key($key, $group);

        if (isset($this->cache[$derived_key]) && !$force) {
            $found = true;
            ++$this->cache_hits;

            return $this->get_from_internal_cache($derived_key, $group);
        }
        if ($this->is_ignored_group($group) || !$this->redis_status()) {
            $found = false;
            ++$this->cache_misses;

            return false;
        }

        try {
            $result = $this->redis->get($derived_key);
        } catch (Exception $exception) {
            $this->handle_exception($exception);

            return false;
        }

        if (null === $result || false === $result) {
            $found = false;
            ++$this->cache_misses;

            return false;
        }
        $found = true;
        ++$this->cache_hits;

        $this->add_to_internal_cache($derived_key, $result);

        return $result;
    }

    /**
     * Retrieve multiple values from cache.
     *
     * Gets multiple values from cache, including across multiple groups
     *
     * Usage: array( 'group0' => array( 'key0', 'key1', 'key2', ), 'group1' => array( 'key0' ) )
     *
     * Mirrors the Memcached Object Cache plugin's argument and return-value formats
     *
     * @param  array  $groups Array of groups and keys to retrieve
     *
     * @return array|false Array of cached values, keys in the format $group:$key. Non-existent keys null.
     */
    public function get_multi(array $groups)
    {
        if (empty($groups) || !\is_array($groups)) {
            return false;
        }

        // Retrieve requested caches and reformat results to mimic Memcached Object Cache's output
        $cache = [];

        foreach ($groups as $group => $keys) {
            if ($this->is_ignored_group($group) || !$this->redis_status()) {
                foreach ($keys as $key) {
                    $cache[self::build_key($key, $group)] = $this->get($key, $group);
                }
            } else {
                // Reformat arguments as expected by Redis
                $derived_keys = [];

                foreach ($keys as $key) {
                    $derived_keys[] = self::build_key($key, $group);
                }

                // Retrieve from cache in a single request
                try {
                    $group_cache = $this->redis->mget($derived_keys);
                } catch (Exception $exception) {
                    $this->handle_exception($exception);
                    $group_cache = \array_fill(0, \count($derived_keys) - 1, false);
                }

                // Build an array of values looked up, keyed by the derived cache key
                $group_cache = \array_combine($derived_keys, $group_cache);

                // Redis returns null for values not found in cache, but expected return value is false in this instance
                $group_cache = \array_map([$this, 'filter_redis_get_multi'], $group_cache);

                $cache[] = $group_cache;
            }
        }

        $cache = \array_merge(...$cache);

        // Add to the internal cache the found values from Redis
        foreach ($cache as $key => $value) {
            if ($value) {
                ++$this->cache_hits;
                $this->add_to_internal_cache($key, $value);
            } else {
                ++$this->cache_misses;
            }
        }

        return $cache;
    }

    /**
     * Sets a value in cache.
     *
     * The value is set whether or not this key already exists in Redis.
     *
     * @param string $key        the key under which to store the value
     * @param mixed  $value      the value to store
     * @param string $group      the group value appended to the $key
     * @param int    $expiration the expiration time, defaults to 0
     *
     * @return bool returns TRUE on success or FALSE on failure
     */
    public function set(string $key, $value, string $group = 'default', $expiration = 0)
    {
        $result = true;
        $derived_key = self::build_key($key, $group);

        // save if group not excluded from redis and redis is up
        if (!$this->is_ignored_group($group) && $this->redis_status()) {
            $expiration = $this->validate_expiration($expiration);

            try {
                if ($expiration) {
                    $result = $this->redis->setex($derived_key, $expiration, $value);
                } else {
                    $result = $this->redis->set($derived_key, $value);
                }
            } catch (Exception $exception) {
                $this->handle_exception($exception);

                $result = false;
            }
        }

        // if the set was successful, or we didn't go to redis
        if ($result) {
            $this->add_to_internal_cache($derived_key, $value);
        }

        return $result;
    }

    /**
     * Increment a Redis counter by the amount specified.
     *
     * @param string $group
     *
     * @return bool|int
     */
    public function increment(string $key, int $offset = 1, $group = 'default')
    {
        $derived_key = self::build_key($key, $group);
        $offset = (int) $offset;

        // If group is a non-Redis group, save to internal cache, not Redis
        if ($this->is_ignored_group($group) || !$this->redis_status()) {
            $value = $this->get_from_internal_cache($derived_key, $group);
            $value += $offset;
            $this->add_to_internal_cache($derived_key, $value);

            return $value;
        }

        // Save to Redis
        try {
            $result = $this->redis->incrBy($derived_key, $offset);

            $this->add_to_internal_cache($derived_key, (int) $this->redis->get($derived_key));
        } catch (Exception $exception) {
            $this->handle_exception($exception);

            return false;
        }

        return $result;
    }

    /**
     * Alias of `increment()`.
     *
     * @param int $offset
     *
     * @return bool
     */
    public function incr(string $key, $offset = 1, string $group = 'default')
    {
        return $this->increment($key, $offset, $group);
    }

    /**
     * Decrement a Redis counter by the amount specified.
     *
     * @param int $offset
     *
     * @return bool|int
     */
    public function decrement(string $key, $offset = 1, string $group = 'default')
    {
        $derived_key = self::build_key($key, $group);
        $offset = (int) $offset;

        // If group is a non-Redis group, save to internal cache, not Redis
        if ($this->is_ignored_group($group) || !$this->redis_status()) {
            $value = $this->get_from_internal_cache($derived_key, $group);
            $value -= $offset;
            $this->add_to_internal_cache($derived_key, $value);

            return $value;
        }

        try {
            // Save to Redis
            $result = $this->redis->decrBy($derived_key, $offset);

            $this->add_to_internal_cache($derived_key, (int) $this->redis->get($derived_key));
        } catch (Exception $exception) {
            $this->handle_exception($exception);

            return false;
        }

        return $result;
    }

    /**
     * Render data about current cache requests.
     *
     * @return string
     */
    public function stats()
    {
        ?>

        <p>
            <strong>Redis Status:</strong> <?php echo $this->redis_status() ? 'Connected' : 'Not Connected'; ?><br/>
            <strong>Redis Client:</strong> <?php echo $this->redis_client; ?><br/>
            <strong>Cache Hits:</strong> <?php
            echo $this->cache_hits; ?><br/>
            <strong>Cache Misses:</strong> <?php
            echo $this->cache_misses; ?>
        </p>

        <ul>
        <?php
        foreach ($this->cache as $group => $cache) { ?>
            <li><?php
                \printf(
            '%s - %sk',
            \strip_tags($group),
            \number_format(\strlen(\serialize($cache)) / 1024, 2)
        ); ?></li>
            <?php
        } ?>
        </ul><?php
    }

    /**
     * Builds a key for the cached object using the prefix, group and key.
     *
     * @param string $key   the key under which to store the value
     * @param string $group the group value appended to the $key
     */
    public static function build_key(string $key, string $group = 'default'): string
    {
        if (empty($group)) {
            $group = 'default';
        }

        $key = self::sanitize_key($key);

        return $group.':'.$key;
    }

    public static function sanitize_key(string $key): string
    {
        return \ctype_alnum($key) && \mb_strlen($key, '8bit') <= 32 ? $key : \md5($key);
    }

    /**
     * Simple wrapper for saving object to the internal cache.
     *
     * @param  string  $derived_key key to save value under
     * @param mixed  $value       object value
     */
    public function add_to_internal_cache(string $derived_key, $value)
    {
        if (\is_object($value)) {
            $value = clone $value;
        }

        $this->cache[$derived_key] = $value;
    }

    /**
     * Get a value specifically from the internal, run-time cache, not Redis.
     *
     * @param int|string $derived_key key value
     * @param int|string $group       group that the value belongs to
     *
     * @return bool|mixed value on success; false on failure
     */
    public function get_from_internal_cache($derived_key, $group)
    {
        if (!isset($this->cache[$derived_key])) {
            return false;
        }

        if (\is_object($this->cache[$derived_key])) {
            return clone $this->cache[$derived_key];
        }

        return $this->cache[$derived_key];
    }

    /**
     * In multisite, switch blog prefix when switching blogs.
     *
     * @param int $_blog_id
     *
     * @return bool
     */
    public function switch_to_blog($_blog_id): bool
    {
        if ( ! function_exists('is_multisite') || !is_multisite()) {
            return false;
        }

        $this->blog_prefix = $_blog_id;

        return true;
    }

    /**
     * Sets the list of global groups.
     *
     * @param  array  $groups list of groups that are global
     */
    public function add_global_groups(array $groups)
    {
        $groups = (array) $groups;

        if ($this->redis_status()) {
            $this->global_groups = \array_unique(\array_merge($this->global_groups, $groups));
        } else {
            $this->ignored_groups = \array_unique(\array_merge($this->ignored_groups, $groups));
        }
    }

    /**
     * Sets the list of groups not to be cached by Redis.
     *
     * @param array|string $groups list of groups that are to be ignored
     */
    public function add_non_persistent_groups($groups)
    {
        $groups = (array) $groups;

        $this->ignored_groups = \array_unique(\array_merge($this->ignored_groups, $groups));
    }

    /**
     * Add or replace a value in the cache.
     *
     * Add does not set the value if the key exists; replace does not replace if the value doesn't exist.
     *
     * @param bool   $add        True if should only add if value doesn't exist, false to only add when value already exists
     * @param string $key        the key under which to store the value
     * @param mixed  $value      the value to store
     * @param string $group      the group value appended to the $key
     * @param int    $expiration the expiration time, defaults to 0
     *
     * @throws \Exception
     *
     * @return bool returns TRUE on success or FALSE on failure
     */
    protected function add_or_replace(bool $add, string $key, $value, string $group = 'default', $expiration = 0): bool
    {
        $cache_addition_suspended = function_exists('wp_suspend_cache_addition') && wp_suspend_cache_addition();

        if ($add && $cache_addition_suspended) {
            return false;
        }

        $result = true;
        $derived_key = self::build_key($key, $group);

        // save if group not excluded and redis is up
        if (!$this->is_ignored_group($group) && $this->redis_status()) {
            try {
                $exists = $this->redis->exists($derived_key);

                if ($add === $exists) {
                    return false;
                }

                $expiration = $this->validate_expiration($expiration);

                if ($expiration) {
                    $result = $this->redis->setex($derived_key, $expiration, $value);
                } else {
                    $result = $this->redis->set($derived_key, $value);
                }
            } catch (Exception $exception) {
                $this->handle_exception($exception);

                return false;
            }
        }

        $exists = isset($this->cache[$derived_key]);

        if ($add === $exists) {
            return false;
        }

        if ($result) {
            $this->add_to_internal_cache($derived_key, $value);
        }

        return $result;
    }

    /**
     * Checks if the given group is part the ignored group array.
     *
     * @param  string  $group  Name of the group to check
     *
     * @return bool
     */
    protected function is_ignored_group(string $group): bool
    {
        return \in_array($group, $this->ignored_groups, true);
    }

    /**
     * Convert data types when using Redis MGET.
     *
     * When requesting multiple keys, those not found in cache are assigned the value null upon return.
     * Expected value in this case is false, so we convert
     *
     * @param bool|string $value Value to possibly convert
     *
     * @return string Converted value
     */
    protected function filter_redis_get_multi($value)
    {
        if (null === $value) {
            $value = false;
        }

        return $value;
    }

    /**
     * Wrapper to validate the cache keys expiration value.
     *
     * @param mixed $expiration Incoming expiration value (whatever it is)
     *
     * @return int
     */
    protected function validate_expiration($expiration)
    {
        $expiration = \is_int($expiration) || \ctype_digit($expiration) ? (int) $expiration : 0;

        if (\defined('WP_REDIS_MAXTTL')) {
            $max = (int) WP_REDIS_MAXTTL;

            if (0 === $expiration || $expiration > $max) {
                $expiration = $max;
            }
        }

        return $expiration;
    }

    /**
     * Handle the redis failure gracefully or throw an exception.
     *
     * @param \Exception $exception exception thrown
     *
     * @throws \Exception
     *
     * @internal
     */
    protected function handle_exception(Exception $exception)
    {
        $this->redis_connected = false;

        // When Redis is unavailable, fall back to the internal cache by forcing all groups to be "no redis" groups
        $this->ignored_groups = \array_unique(\array_merge($this->ignored_groups, $this->global_groups));

        if (!$this->fail_gracefully) {
            throw $exception;
        }

        \error_log($exception);
    }
}
