<?php

namespace JazzMan\WPObjectCache;

use Exception;
use Redis;

/**
 * Class RedisAdapter.
 */
class RedisAdapter
{
    /**
     * Holds the non-Redis objects.
     *
     * @var array
     */
    private $cache = [];

    /**
     * List of global groups.
     *
     * @var string[]
     */
    private $globalGroups = [
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
    private $ignoredGroups = ['counts', 'plugins'];

    /**
     * Prefix used for global groups.
     *
     * @var string
     */
    public $global_prefix = '';

    /**
     * Prefix used for non-global groups.
     *
     * @var int|string
     */
    private $blogPrefix = '';

    /**
     * Track how many requests were found in cache.
     *
     * @var int
     */
    private $cacheHits = 0;

    /**
     * Track how may requests were not cached.
     *
     * @var int
     */
    private $cacheMisses = 0;
    /**
     * @var Redis
     */
    private $redis;

    /**
     * Track if Redis is available.
     *
     * @var bool
     */
    private $redisConnected = false;

    /**
     * @var array<string,string|int|null>
     */
    private $redisParameters = [
        'scheme' => 'tcp',
        'host' => '127.0.0.1',
        'port' => 6379,
        'timeout' => 5,
        'read_timeout' => 5,
        'retry_interval' => null,
        'compression_level' => 5,
    ];

    public function __construct()
    {
        global $blog_id, $table_prefix;

        // Assign global and blog prefixes for use with keys
        if (function_exists('is_multisite')) {
            $is_multisite = is_multisite();
            $custom_user_table = defined('CUSTOM_USER_TABLE') && defined('CUSTOM_USER_META_TABLE');

            $this->global_prefix = $is_multisite || $custom_user_table ? '' : $table_prefix;

            $this->blogPrefix = $is_multisite ? $blog_id : $table_prefix;
        }

        if (defined('WP_REDIS_IGNORED_GROUPS') && \is_array(WP_REDIS_IGNORED_GROUPS)) {
            $this->ignoredGroups = WP_REDIS_IGNORED_GROUPS;
        }

        $this->initRedisParameters();

        $this->initRedis();
    }

    private function initRedisParameters(): void
    {
        if (defined('DB_NAME')) {
            $this->redisParameters['prefix'] = DB_NAME;
        }

        $settings = [
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
        ];

        foreach ($settings as $setting) {
            $constant = \sprintf('WP_REDIS_%s', \strtoupper($setting));

            if (defined($constant)) {
                $this->redisParameters[$setting] = \constant($constant);
            }
        }

        if (isset($this->redisParameters['database']) && self::validateInt($this->redisParameters['database'],1,16)) {
            $this->redisParameters['database'] = (int) $this->redisParameters['database'];
        }

        $this->redisParameters['version'] = (string) \phpversion('redis');
    }

    private function initRedis(): void
    {
        try {
            $this->redis = new Redis();

            $this->makeConnection();

            $this->setRedisOption();

            $this->redis->ping();

            $this->redisConnected = true;
        } catch (Exception $exception) {
            $this->handleException($exception);
        }
    }

    private function makeConnection(): void
    {
        $connectionArgs = [
            $this->redisParameters['host'],
            $this->redisParameters['port'],
            $this->redisParameters['timeout'],
            null,
            $this->redisParameters['retry_interval'],
        ];

        if (0 === \strcasecmp('tls', (string)$this->redisParameters['scheme'])) {
            $connectionArgs[0] = \sprintf(
                '%s://%s',
                $this->redisParameters['scheme'],
                \str_replace('tls://', '', (string)$this->redisParameters['host'])
            );
        }

        if (0 === \strcasecmp('unix', (string)$this->redisParameters['scheme'])) {
            $connectionArgs[0] = $this->redisParameters['path'];
            $connectionArgs[1] = null;
        }

        if (isset($this->redisParameters['read_timeout']) && version_compare((string)$this->redisParameters['version'], '3.1.3', '>=')) {
            $connectionArgs[] = $this->redisParameters['read_timeout'];
        }

        \call_user_func_array([$this->redis, 'connect'], $connectionArgs);
    }

    private function setRedisOption(): void
    {
        if (isset($this->redisParameters['password'])) {
            $this->redis->auth((string)$this->redisParameters['password']);
        }

        if (isset($this->redisParameters['database'])) {
            $this->redis->select((int)$this->redisParameters['database']);
        }

        if (defined('Redis::SERIALIZER_IGBINARY')) {
            $this->redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_IGBINARY);
        }

        if (defined('Redis::COMPRESSION_LZF')) {
            $this->redis->setOption(Redis::OPT_COMPRESSION, Redis::COMPRESSION_LZF);

            $level = self::validateInt($this->redisParameters['compression_level'], 1);

            if ($level) {
                $this->redis->setOption(Redis::OPT_COMPRESSION_LEVEL, $level);
            }
        }

        if (!empty($this->redisParameters['prefix'])) {
            if (defined('\Redis::SCAN_PREFIX')) {
                $this->redis->setOption(Redis::OPT_SCAN, Redis::SCAN_PREFIX);
            }
            $this->redis->setOption(Redis::OPT_PREFIX, "{$this->redisParameters['prefix']}:");
        }
    }

    /**
     * @param  mixed  $number
     * @param  int  $minRange
     * @param  int|null  $maxRange
     *
     * @return false|int
     */
    private static function validateInt($number, int $minRange = 0, int $maxRange = null)
    {
        $options = [
            'min_range' => $minRange,
        ];

        if (null !== $maxRange) {
            $options['max_range'] = $maxRange;
        }

        return \filter_var(
            $number,
            FILTER_VALIDATE_INT,
            [
                'options'=>$options,
            ]
        );
    }

    /**
     * Is Redis available?
     * @return bool
     */
    public function redisStatus(): bool
    {
        return $this->redisConnected;
    }

    /**
     * Returns the Redis instance.
     * @return \Redis
     */
    public function redisInstance(): Redis
    {
        return $this->redis;
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
    public function add(string $key, $value, string $group = 'default', int $expiration = 0): bool
    {
        return $this->addOrReplace(true, $key, $value, $group, $expiration);
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
    public function replace(string $key, $value, string $group = 'default', int $expiration = 0): bool
    {
        return $this->addOrReplace(false, $key, $value, $group, $expiration);
    }

    /**
     * Remove the item from the cache.
     *
     * @param string $key   the key under which to store the value
     * @param string $group the group value appended to the $key
     *
     * @return bool|int returns TRUE on success or FALSE on failure
     */
    public function delete(string $key, string $group = 'default')
    {
        $result = false;
        $key = $this->buildKey($key, $group);

        if (isset($this->cache[$key])) {
            unset($this->cache[$key]);
            $result = true;
        }

        if ($this->redisStatus() && !$this->isIgnoredGroup($group)) {
            try {
                $result = $this->redis->del($key);
            } catch (Exception $exception) {
                $this->handleException($exception);

                $result = false;
            }
        }

        return $result;
    }

    /**
     * Invalidate all items in the cache. If `WP_REDIS_SELECTIVE_FLUSH` is `true`,
     * only keys prefixed with the `WP_CACHE_KEY_SALT` are flushed.
     *
     * @param int $delay number of seconds to wait before invalidating the items
     *
     * @return bool returns TRUE on success or FALSE on failure
     */
    public function flush(int $delay = 0): bool
    {
        $delay = \abs($delay);

        if ($delay) {
            \sleep($delay);
        }

        $results = true;
        $this->cache = [];

        if ($this->redisStatus()) {
            try {
                $results = $this->redis->flushdb();
            } catch (Exception $exception) {
                $this->handleException($exception);

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
     * @param string    $key    the key under which to store the value
     * @param string    $group  the group value appended to the $key
     * @param bool      $force  Optional. Whether to force a refetch rather than relying on the local
     *                          cache. Default false.
     * @param null|bool &$found Optional. Whether the key was found in the cache. Disambiguates a return of
     *                          false, a storable value. Passed by reference. Default null.
     *
     * @return mixed cached object value
     */
    public function get(string $key, string $group = 'default', bool $force = false, &$found = null)
    {
        $key = $this->buildKey($key, $group);

        if (isset($this->cache[$key]) && !$force) {
            $found = true;
            ++$this->cacheHits;

            return $this->getFromInternalCache($key);
        }
        if ($this->isIgnoredGroup($group) || !$this->redisStatus()) {
            $found = false;
            ++$this->cacheMisses;

            return false;
        }

        try {
            $result = $this->redis->get($key);
        } catch (Exception $exception) {
            $this->handleException($exception);

            return false;
        }

        if (null === $result || false === (bool) $result) {
            $found = false;
            ++$this->cacheMisses;

            return false;
        }
        $found = true;
        ++$this->cacheHits;

        $this->addToInternalCache($key, $result);

        return $result;
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
    public function set(string $key, $value, string $group = 'default', int $expiration = 0): bool
    {
        $result = true;
        $key = $this->buildKey($key, $group);

        // save if group not excluded from redis and redis is up
        if (!$this->isIgnoredGroup($group) && $this->redisStatus()) {
            $expiration = self::validateInt($expiration);

            try {
                $result = $expiration ?
                    $this->redis->setex($key, $expiration, $value) :
                    $this->redis->set($key, $value);
            } catch (Exception $exception) {
                $this->handleException($exception);

                $result = false;
            }
        }

        // if the set was successful, or we didn't go to redis
        if ($result) {
            $this->addToInternalCache($key, $value);
        }

        return $result;
    }

    /**
     * Increment a Redis counter by the amount specified.
     *
     * @param  string  $key
     * @param  int  $offset
     * @param  string  $group
     *
     * @return bool|int
     */
    public function increment(string $key, int $offset = 1, string $group = 'default')
    {
        $key = $this->buildKey($key, $group);

        // If group is a non-Redis group, save to internal cache, not Redis
        if ($this->isIgnoredGroup($group) || !$this->redisStatus()) {
            $value = $this->getFromInternalCache($key);
            $value += $offset;
            $this->addToInternalCache($key, $value);

            return $value;
        }

        // Save to Redis
        try {
            $result = $this->redis->incrBy($key, $offset);

            $this->addToInternalCache($key, (int) $this->redis->get($key));
        } catch (Exception $exception) {
            $this->handleException($exception);

            return false;
        }

        return $result;
    }

    /**
     * Decrement a Redis counter by the amount specified.
     *
     * @param  string  $key
     * @param  int  $offset
     * @param  string  $group
     * @return bool|int
     */
    public function decrement(string $key, int $offset = 1, string $group = 'default')
    {
        $key = $this->buildKey($key, $group);

        // If group is a non-Redis group, save to internal cache, not Redis
        if ($this->isIgnoredGroup($group) || !$this->redisStatus()) {
            $value = $this->getFromInternalCache($key);
            $value -= $offset;
            $this->addToInternalCache($key, $value);

            return $value;
        }

        try {
            // Save to Redis
            $result = $this->redis->decrBy($key, $offset);

            $this->addToInternalCache($key, (int) $this->redis->get($key));
        } catch (Exception $exception) {
            $this->handleException($exception);

            return false;
        }

        return $result;
    }

    /**
     * Render data about current cache requests.
     */
    public function stats(): void
    {
        printf(
            '<p>
<strong>Redis Status:</strong> %s <br/>
<strong>Redis Client:</strong> PhpRedis (v%s) <br/>
<strong>Cache Hits:</strong> %s <br/>
<strong>Cache Misses:</strong> %s <br/>
</p>',
            esc_attr($this->redisStatus() ? 'Connected' : 'Not Connected'),
            esc_attr((string)$this->redisParameters['version']),
            esc_attr((string) $this->cacheHits),
            esc_attr((string) $this->cacheMisses)
        );
    }

    /**
     * Builds a key for the cached object using the prefix, group and key.
     *
     * @param  string  $key  the key under which to store the value
     * @param  string  $group  the group value appended to the $key
     * @return string
     */
    private function buildKey(string $key, string $group = 'default'): string
    {
        if (empty($group)) {
            $group = 'default';
        }

        return implode(':', array_filter([
            !empty($this->blogPrefix) ? $this->blogPrefix : false,
            $group,
            self::sanitizeKey($key),
        ]));
    }

    private static function sanitizeKey(string $key): string
    {
        return \ctype_alnum($key) && \mb_strlen($key, '8bit') <= 32 ? $key : \md5($key);
    }

    /**
     * Simple wrapper for saving object to the internal cache.
     *
     * @param string $key   key to save value under
     * @param mixed  $value object value
     */
    private function addToInternalCache(string $key, $value): void
    {
        if (\is_object($value)) {
            $value = clone $value;
        }

        $this->cache[$key] = $value;
    }

    /**
     * Get a value specifically from the internal, run-time cache, not Redis.
     *
     * @param string $key key value
     *
     * @return bool|mixed value on success; false on failure
     */
    private function getFromInternalCache(string $key)
    {
        if (!isset($this->cache[$key])) {
            return false;
        }

        if (\is_object($this->cache[$key])) {
            return clone $this->cache[$key];
        }

        return $this->cache[$key];
    }

    /**
     * In multisite, switch blog prefix when switching blogs.
     * @param  int  $blogId
     * @return bool
     */
    public function switchToBlog(int $blogId): bool
    {
        if (!function_exists('is_multisite') || !is_multisite()) {
            return false;
        }

        $this->blogPrefix = $blogId;

        return true;
    }

    /**
     * Sets the list of global groups.
     *
     * @param string|string[] $groups list of groups that are global
     */
    public function addGlobalGroups($groups): void
    {
        if ($this->redisStatus()) {
            $this->globalGroups = \array_unique(\array_merge($this->globalGroups, (array) $groups));
        } else {
            $this->ignoredGroups = \array_unique(\array_merge($this->ignoredGroups, (array) $groups));
        }
    }

    /**
     * Sets the list of groups not to be cached by Redis.
     *
     * @param string|string[] $groups list of groups that are to be ignored
     */
    public function addNonPersistentGroups($groups): void
    {
        $groups = (array) $groups;

        $this->ignoredGroups = \array_unique(\array_merge($this->ignoredGroups, $groups));
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
     * @return bool returns TRUE on success or FALSE on failure
     */
    private function addOrReplace(bool $add, string $key, $value, string $group = 'default', int $expiration = 0): bool
    {
        $suspended = function_exists('wp_suspend_cache_addition') && wp_suspend_cache_addition();

        if ($add && $suspended) {
            return false;
        }

        $result = true;
        $key = $this->buildKey($key, $group);

        // save if group not excluded and redis is up
        if (!$this->isIgnoredGroup($group) && $this->redisStatus()) {
            try {
                $exists = $this->redis->exists($key);

                if ($add === (bool) $exists) {
                    return false;
                }

                $expiration = self::validateInt($expiration);

                $result = $expiration ?
                    $this->redis->setex($key, $expiration, $value) :
                    $this->redis->set($key, $value);
            } catch (Exception $exception) {
                $this->handleException($exception);

                return false;
            }
        }

        $exists = isset($this->cache[$key]);

        if ($add === $exists) {
            return false;
        }

        if ($result) {
            $this->addToInternalCache($key, $value);
        }

        return $result;
    }

    /**
     * Checks if the given group is part the ignored group array.
     *
     * @param  string  $group  Name of the group to check
     * @return bool
     */
    private function isIgnoredGroup(string $group): bool
    {
        return \in_array($group, $this->ignoredGroups, true);
    }

    /**
     * Handle the redis failure gracefully or throw an exception.
     *
     * @param \Exception $exception exception thrown
     *
     * @internal
     */
    private function handleException(Exception $exception): void
    {
        $this->redisConnected = false;

        // When Redis is unavailable, fall back to the internal cache by forcing all groups to be "no redis" groups
        $this->ignoredGroups = \array_unique(\array_merge($this->ignoredGroups, $this->globalGroups));

        \error_log($exception);
    }
}
