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
     * Holds the Memcached object.
     *
     * @var Memcached
     */
    public $m;
    /**
     * List of global groups.
     *
     * @var array
     */
    public $global_groups = [
        // Users
        'users',
        'userlogins',
        'usermeta',
        'user_meta',
        'useremail',
        'userslugs',

        // Networks & Sites
        'site-transient',
        'site-options',
        'blog-lookup',
        'blog-details',
        'blog-id-cache',
        'site-details',
        'networks',
        'sites',

        // Posts
        'rss',
        'global-posts',
        'options',
        'posts',
        'post_meta',
        'query',
        'terms',
        'term_meta',
        'themes',
    ];

    /**
     * List of groups not saved to Memcached.
     *
     * @var ParameterBag
     */
    private $ignored_groups;
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
     * @var int
     */
    protected $cache_hits = 0;
    /**
     * @var int
     */
    protected $cache_misses = 0;
    /**
     * Holds the non-Memcached objects.
     *
     * @var ParameterBag
     */
    private $cache;
    /**
     * @var float|int
     */
    private $thirty_days;
    /**
     * @var int
     */
    private $now;

    /**
     * Instantiate the Memcached class.
     *
     * Instantiates the Memcached class and returns adds the servers specified
     * in the $memcached_servers global array.
     *
     * @see    http://www.php.net/manual/en/memcached.construct.php
     *
     * @param string $persistent_id to create an instance that persists between requests, use persistent_id to specify a unique ID for the instance
     */
    public function __construct($persistent_id = '')
    {
        global $blog_id, $table_prefix;

        $this->m = new Memcached(!empty($persistent_id) ? $persistent_id : '');

        $this->cache = new ParameterBag();

        $this->ignored_groups = new ParameterBag(['comment', 'counts']);

        $this->m->addServer((string) $this->getMemcachedHost(), (int) $this->getMemcachedPort());

        $this->m->setOptions($this->getMemcachedOptions());

        // Assign global and blog prefixes for use with keys
        if (\function_exists('is_multisite')) {
            $this->global_prefix = (is_multisite() || (\defined('CUSTOM_USER_TABLE') && \defined('CUSTOM_USER_META_TABLE'))) ? '' : $table_prefix;
            $this->blog_prefix = (is_multisite() ? $blog_id : $table_prefix).':';
        }

        // Setup cacheable values for handling expiration times
        $this->thirty_days = MONTH_IN_SECONDS;
        $this->now = time();
    }

    /**
     * @return string
     */
    private function getMemcachedHost()
    {
        return \defined('WP_CACHE_HOST') ? WP_CACHE_HOST : '127.0.0.1';
    }

    /**
     * @return int
     */
    private function getMemcachedPort()
    {
        return \defined('WP_CACHE_PORT') ? WP_CACHE_PORT : 11211;
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
     * @param string $key
     *
     * @return bool
     */
    protected function hasCache($key)
    {
        return $this->cache->offsetExists($key);
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

    protected function flushCache()
    {
        $this->cache->exchangeArray([]);
    }

    /**
     * Add global groups.
     *
     * @author  Ryan Boren   This function comes straight from the original WP Memcached Object cache
     *
     * @see    http://wordpress.org/extend/plugins/memcached/
     *
     * @param array $groups array of groups
     */
    public function addGlobalGroups($groups)
    {
        if ($this->getServerStatus()) {
            $groups = (array) $groups;

            $this->global_groups = array_merge($this->global_groups, $groups);
            $this->global_groups = array_unique(array_filter($this->global_groups));
        }
    }

    /**
     * Is Memcached available?
     *
     * @return bool
     */
    protected function getServerStatus()
    {
        return (bool) $this->m->getStats();
    }

    /**
     * Add non-persistent groups.
     *
     * @author  Ryan Boren   This function comes straight from the original WP Memcached Object cache
     *
     * @see    http://wordpress.org/extend/plugins/memcached/
     *
     * @param array $groups array of groups
     */
    public function addNonPersistentGroups($groups)
    {
        if ($this->getServerStatus()) {
            $groups = (array) $groups;

            $ignored_groups = array_merge($this->ignored_groups->getArrayCopy(), $groups);
            $ignored_groups = array_unique(array_filter($ignored_groups));

            $this->ignored_groups->exchangeArray($ignored_groups);
        }
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
     * @see    http://wordpress.org/extend/plugins/memcached/
     *
     * @param string $key   the key under which to store the value
     * @param string $group the group value appended to the $key
     *
     * @return string
     */
    public function buildKey($key, $group = 'default')
    {
        $prefix = \in_array($group, $this->global_groups) ? $this->global_prefix : $this->blog_prefix;

        return "{{$prefix}}:{$group}:{$key}";
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
     * Wrapper to validate the cache keys expiration value.
     *
     * @param mixed $expiration Incomming expiration value (whatever it is)
     *
     * @return int|mixed
     */
    protected function validateExpiration($expiration)
    {
        $expiration = \is_int($expiration) || ctype_digit($expiration) ? (int) $expiration : 0;

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
     * @return bool
     */
    protected function isResSuccess()
    {
        return Memcached::RES_SUCCESS === $this->m->getResultCode();
    }

    /**
     * @return bool
     */
    protected function isResNotfound()
    {
        return Memcached::RES_NOTFOUND === $this->m->getResultCode();
    }

    /**
     * @return bool
     */
    protected function isResNotstored()
    {
        return Memcached::RES_NOTSTORED === $this->m->getResultCode();
    }
}
