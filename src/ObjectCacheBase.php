<?php

namespace JazzMan\WPObjectCache;

use JazzMan\ParameterBag\ParameterBag;
use Memcached;

/**
 * Class ObjectCacheBase.
 */
class ObjectCacheBase
{
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
     * List of global groups.
     *
     * @var array
     */
    public $global_groups = [];
    /**
     * @var int
     */
    protected $cache_hits = 0;
    /**
     * @var int
     */
    protected $cache_misses = 0;
    /**
     * Holds the Memcached object.
     *
     * @var Memcached
     */
    protected $memcached;
    /**
     * Result code that determines successful cache interaction.
     *
     * @var int
     */
    protected $success_code = Memcached::RES_SUCCESS;
    /**
     * @var Memcached[]
     */
    private $mc;
    /**
     * List of groups not saved to Memcached.
     *
     * @var ParameterBag
     */
    private $ignored_groups;
    /**
     * Holds the non-Memcached objects.
     *
     * @var ParameterBag
     */
    private $cache;
    /**
     * Salt to prefix all keys with.
     *
     * @var string
     */
    private $cache_key_salt = '';
    /**
     * Control character used to separate keys.
     *
     * @var string
     */
    private $cache_key_separator = ':';
    /**
     * @var float|int
     */
    private $thirty_days;
    /**
     * @var int
     */
    private $now;
    /**
     * @var bool
     */
    private $multisite;

    /**
     * Instantiate the Memcached class.
     *
     * Instantiates the Memcached class and returns adds the servers specified
     * in the $memcached_servers global array.
     *
     * @see    http://www.php.net/manual/en/memcached.construct.php
     *
     * @param string $persistent_id to create an instance that persists between requests, use persistent_id to specify
     *                              a unique ID for the instance
     */
    public function __construct($persistent_id = '')
    {
        $this->multisite = is_multisite();
        $this->thirty_days = DAY_IN_SECONDS * 30;
        $this->now = time();


        $this->setMemcached();
        $this->setCacheKeySalt();
        $this->setPrefixes();
        $this->setCacheGroups();

        $this->cache = new ParameterBag();
    }

    private function setCacheKeySalt()
    {
        if (\defined('WP_CACHE_KEY_SALT') && WP_CACHE_KEY_SALT) {
            $this->cache_key_salt = rtrim(WP_CACHE_KEY_SALT, $this->cache_key_separator);
        }
    }

    // Set values for handling expiration times

    private function setPrefixes()
    {
        global $blog_id;
        // Global prefix
        $this->global_prefix = $this->multisite || (\defined('CUSTOM_USER_TABLE') && \defined('CUSTOM_USER_META_TABLE')) ? '' : (int) $blog_id;

        // Blog prefix
        $this->blog_prefix = (int) $blog_id;
    }

    private function setCacheGroups()
    {
        if ($this->getServerStatus()) {
            $groups = [
                'users',
                'userlogins',
                'usermeta',
                'user_meta',
                'useremail',
                'userslugs',
                'site-transient',
                'site-options',
                'blog-lookup',
                'blog-details',
                'site-details',
                'rss',
                'global-posts',
                'blog-id-cache',
                'networks',
                'sites',
                'blog_meta',
            ];

            $groups_extendad = [
                'category',
                'posts',
                'comment',
                'default',
                'customize_changeset_post',
                'oembed_cache_post',
                'timeinfo',
                'calendar',
                // Posts
                'options',
                'posts',
                'post_meta',
                'query',
                'terms',
                'term_meta',
                'themes',
                'bookmark',
            ];

            $ignored_groups = [
                'counts',
                'plugins',
            ];

            $this->ignored_groups = new ParameterBag($ignored_groups);
            $this->global_groups = array_merge($groups_extendad, $groups);
        }
    }

    /**
     * Is Memcached available?
     *
     * @return bool
     *
     * @param mixed $group
     */
    protected function getServerStatus($group = 'default')
    {
        return (bool) $this->getMc($group)->getStats();
    }

    private function setMemcached()
    {
        $memcached_servers = \function_exists('get_memcached_servers') ? get_memcached_servers() : null;

        $buckets = $memcached_servers ?? ['127.0.0.1:11211'];

        reset($buckets);

        if (\is_int(key($buckets))) {
            $buckets = ['default' => $buckets];
        }

        foreach ($buckets as $bucket => $servers) {
            $m = new Memcached('wpcache');
            $m->setOptions($this->getMemcachedOptions());

            $this->mc[$bucket] = $m;
            $instances = [];
            foreach ($servers as $server) {
                list($node, $port) = explode(':', $server);

                if (empty($port)) {
                    $port = ini_get('memcache.default_port');
                }
                $port = (int) $port;
                if (!$port) {
                    $port = $this->getMemcachedPort();
                }
                $instances[] = [$node, $port, 1];
            }
            $this->mc[$bucket]->addServers($instances);
        }
    }

    /**
     * @return array
     */
    private function getMemcachedOptions()
    {
        $options = [
            Memcached::OPT_NO_BLOCK => true,
            Memcached::OPT_COMPRESSION => true,
            Memcached::OPT_PREFIX_KEY => $this->getMemcachedPrefix(),
        ];

        return $options;
    }

    /**
     * @return mixed
     */
    private function getMemcachedPrefix()
    {
        if (\defined('WP_CACHE_PREFIX')) {
            return WP_CACHE_PREFIX;
        }

        $server_host = (string) $_SERVER['HTTP_HOST'];

        $prefix = str_replace(['.', ':', '-'], '', $server_host);

        return filter_var($prefix, FILTER_SANITIZE_URL) ?: '';
    }

    /**
     * @return int
     */
    private function getMemcachedPort()
    {
        return \defined('WP_CACHE_PORT') ? WP_CACHE_PORT : 11211;
    }

    /**
     * @param $group
     *
     * @return \Memcached
     */
    public function getMc($group = 'default')
    {
        return $this->mc[$group] ?? $this->mc['default'];
    }

    /**
     * Add global groups.
     *
     * @author  Ryan Boren   This function comes straight from the original WP Memcached Object cache
     *
     * @see     http://wordpress.org/extend/plugins/memcached/
     *
     * @param string|array $groups array of groups
     */
    public function setGlobalGroups($groups)
    {
        $groups = (array) $groups;

        $this->global_groups = array_merge($this->global_groups, $groups);
        $this->global_groups = array_unique(array_filter($this->global_groups));
    }

    /**
     * Add non-persistent groups.
     *
     * @author  Ryan Boren   This function comes straight from the original WP Memcached Object cache
     *
     * @see     http://wordpress.org/extend/plugins/memcached/
     *
     * @param array $groups array of groups
     */
    public function addNonPersistentGroups($groups)
    {
        $groups = (array) $groups;

        $ignored_groups = array_merge($this->ignored_groups->getArrayCopy(), $groups);
        $ignored_groups = array_unique(array_filter($ignored_groups));

        $this->ignored_groups->exchangeArray($ignored_groups);
    }

    /**
     * Get a value specifically from the internal, run-time cache, not Redis.
     *
     * @param int|string $key   key value
     * @param int|string $group group that the value belongs to
     *
     * @return bool|mixed value on success; false on failure
     */
    public function getFromInternalCache($key, $group)
    {
        $derived_key = $this->buildKey($key, $group);

        return $this->getCache($derived_key);
    }

    /**
     * Builds a key for the cached object using the blog_id, key, and group values.
     *
     * @author  Ryan Boren   This function is inspired by the original WP Memcached Object cache.
     *
     * @see     http://wordpress.org/extend/plugins/memcached/
     *
     * @param string $key   the key under which to store the value
     * @param string $group the group value appended to the $key
     *
     * @return string
     */
    public function buildKey($key, $group = 'default')
    {
        // Setup empty keys array
        $keys = [];

        // Force default group if none is passed
        if (empty($group)) {
            $group = 'default';
        }

        // Prefix with key salt if set
        if (!empty($this->cache_key_salt)) {
            $keys['salt'] = $this->cache_key_salt;
        }

        // Set prefix
        $keys['prefix'] = \in_array($group, $this->global_groups, true) ? $this->global_prefix : $this->blog_prefix;

        // Set group & key
        $keys['group'] = $group;
        $keys['key'] = $key;

        /**
         * Filter the cache keys array.
         *
         * @since 5.0.0
         *
         * @param array  $keys  All cache key parts
         * @param string $key   The current cache key
         * @param string $group The current cache group
         */
        $keys = (array) apply_filters('wp_cache_key_parts', array_filter($keys), $key, $group);

        // Assemble the cache key
        $cache_key = implode($this->cache_key_separator, $keys);

        // Prevent double separators
        $cache_key = str_replace("{$this->cache_key_separator}{$this->cache_key_separator}", $this->cache_key_separator,
            $cache_key);

        // Remove all whitespace
        return preg_replace('/\s+/', '', $cache_key);
    }

    /**
     * @param string $key
     * @param bool   $default
     *
     * @return mixed|null
     */
    protected function getCache($key, $default = false)
    {
        $data = $this->cache->get($key, $default);

        return \is_object($data) ? clone $data : $data;
    }

    /**
     * @param string $key
     * @param mixed  $value
     */
    protected function setCache($key, $value)
    {
        $this->cache->offsetSet($key, $value);
    }

    /**
     * Switch blog prefix, which changes the cache that is accessed.
     *
     * @param int $blog_id blog to switch to
     */
    public function switchToBlog($blog_id)
    {
        global $table_prefix;
        $blog_id = (int) $blog_id;
        $this->blog_prefix = (is_multisite() ? $blog_id : $table_prefix).':';
    }

    /**
     * Invalidate all items in the cache.
     *
     * @see http://www.php.net/manual/en/memcached.flush.php
     *
     * @param int $delay number of seconds to wait before invalidating the items
     *
     * @return bool returns TRUE on success or FALSE on failure
     */
    public function flush($delay = 0)
    {
        if ($this->multisite) {
            return true;
        }

        $result = false;

        foreach ($this->mc as $group) {
            $result = $group->flush($delay);
        }

        return $result;
    }

    /**
     * @param string $group
     *
     * @return bool
     */
    protected function success($group)
    {
        return $this->success_code === $this->getMc($group)->getResultCode();
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    protected function deleteCache($key)
    {
        $result = false;

        if ($this->hasCache($key)) {
            $this->cache->offsetUnset($key);

            $result = true;
        }

        return $result;
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    protected function hasCache($key)
    {
        return $this->cache->offsetExists($key);
    }

    /**
     * Wrapper to validate the cache keys expiration value.
     *
     * @param mixed $expiration Incomming expiration value (whatever it is)
     *
     * @return int|mixed
     */
    protected function validateExpiration($expiration)
    {
        $expiration = ($expiration > $this->thirty_days) && ($expiration <= $this->now) ? $expiration + $this->now : 0;

        return $expiration;
    }

    /**
     * @return bool
     */
    protected function isSuspendCacheAddition()
    {
        return \function_exists('wp_suspend_cache_addition') && wp_suspend_cache_addition();
    }

    /**
     * @param string $group
     *
     * @return bool
     */
    protected function isIgnoredGroup($group)
    {
        return $this->ignored_groups->offsetExists($group);
    }

    /**
     * @param string $group
     *
     * @return bool
     */
    protected function isResNotfound($group)
    {
        return Memcached::RES_NOTFOUND === $this->getMc($group)->getResultCode();
    }

    /**
     * @param string $group
     *
     * @return bool
     */
    protected function isResNotstored($group)
    {
        return Memcached::RES_NOTSTORED === $this->getMc($group)->getResultCode();
    }

    /**
     * @return string
     */
    private function getMemcachedHost()
    {
        return \defined('WP_CACHE_HOST') ? WP_CACHE_HOST : '127.0.0.1';
    }
}
