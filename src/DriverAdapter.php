<?php

namespace JazzMan\WPObjectCache;

use Phpfastcache\Drivers\Redis\Driver as RedisDriver;
use Phpfastcache\Drivers\Memcached\Driver as MemcachedDriver;
use Phpfastcache\Drivers\Apcu\Driver as ApcuDriver;
use Psr\Cache\InvalidArgumentException;

/**
 * Class DriverAdapter.
 */
class DriverAdapter
{
    /**
     * @var int
     */
    public $cache_hits = 0;
    /**
     * @var int
     */
    public $cache_misses = 0;
    /**
     * @var array
     */
    private $global_groups;
    /**
     * @var string
     */
    private $global_prefix = '';
    /**
     * @var string
     */
    private $pool_prefix;
    /**
     * @var array
     */
    private $ignored_groups;
    /**
     * @var int
     */
    private $blog_prefix;
    /**
     * @var RedisDriver|MemcachedDriver|ApcuDriver
     */
    private $cache_instance;
    /**
     * @var array
     */
    private $cache = [];
    /**
     * @var bool
     */
    private $has_cache_prefix;

    /**
     * DriverAdapter constructor.
     */
    public function __construct()
    {
        global $blog_id;

        $this->blog_prefix = (int) $blog_id;

        $this->cache_instance = Driver::getInstance()->getCacheInstance();

        $this->has_cache_prefix = \defined('WP_CACHE_PREFIX') && !empty(WP_CACHE_PREFIX);

        $this->setCacheGroups();
        $this->setPoolPrefix($_SERVER['HTTP_HOST']);
    }

    private function setCacheGroups()
    {
        $this->global_groups = [
            'users',
            'userlogins',
            'usermeta',
            'user_meta',
            'useremail',
            'userslugs',
            'site-transient',
            'transient',
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
            'category',
            'posts',
            'comment',
            'default',
            'customize_changeset_post',
            'oembed_cache_post',
            'timeinfo',
            'calendar',
            'last_changed',
            // Posts
            'options',
            'posts',
            'post_meta',
            'query',
            'terms',
            'term_meta',
            'bookmark',
        ];

        $this->ignored_groups = [
            'counts',
            'plugins',
        ];
    }

    /**
     * @return string
     */
    private function getPoolPrefix()
    {
        return $this->pool_prefix;
    }

    /**
     * @param string $pool_prefix
     */
    private function setPoolPrefix(string $pool_prefix)
    {
        $this->pool_prefix = $this->sanitizePrefix($pool_prefix, false);
    }

    /**
     * @return bool|string
     */
    private function getGlobalPrefix()
    {
        return $this->has_cache_prefix ? false : $this->getPoolPrefix();
    }

    /**
     * @param string|array $groups
     */
    public function setIgnoredGroups($groups)
    {
        $groups = (array) $groups;

        $ignored_groups = array_merge($this->ignored_groups, $groups);
        $ignored_groups = array_unique(array_filter($ignored_groups));

        $this->ignored_groups = $ignored_groups;
    }

    /**
     * @param string|array $groups
     */
    public function setGlobalGroups($groups)
    {
        $groups = (array) $groups;
        $this->global_groups = array_merge($this->global_groups, $groups);
        $this->global_groups = array_unique(array_filter($this->global_groups));
    }

    /**
     * @param string|array $key
     * @param string       $group
     * @param bool         $force
     * @param null         $found
     *
     * @return mixed
     */
    public function get($key, $group = 'default', $force = false, &$found = null)
    {
        $result = false;

        $key = $this->sanitizeKey($key, $group);

        if (!$force && !empty($this->cache[$key])) {
            $found = true;
            ++$this->cache_hits;

            return $this->returnCacheItem($this->cache[$key]);
        }

        if (\in_array($group, $this->ignored_groups)) {
            $found = false;
            ++$this->cache_misses;

            return false;
        }

        try {
            $cacheItem = $this->cache_instance->getItem($key);

            if (!$this->isValidCacheItem($cacheItem)) {
                $found = false;
                ++$this->cache_misses;

                return false;
            }

            $found = true;
            ++$this->cache_hits;

            $result = $cacheItem->get();

            $this->addToInternalCache($key, $result);

            return $this->returnCacheItem($result);
        } catch (\Exception $e) {
            error_log($e);
        }

        ++$this->cache_misses;

        return $result;
    }

    /**
     * @param string $key
     * @param string $group
     *
     * @return string
     */
    private function sanitizeKey(string $key, string $group = 'default')
    {
        return $this->sanitizePrefix("blog_{$this->blog_prefix}_{$group}_{$key}");
    }

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    private function returnCacheItem($value)
    {
        return \is_object($value) ? clone $value : $value;
    }

    /**
     * @param \Phpfastcache\Core\Item\ExtendedCacheItemInterface $cacheItem
     *
     * @return bool
     */
    private function isValidCacheItem($cacheItem)
    {
        return !$cacheItem->isExpired() && !$cacheItem->isNull();
    }

    /**
     * @param string $key
     * @param mixed  $value
     */
    private function addToInternalCache(string $key, $value)
    {
        $this->cache[$key] = $value;
    }

    /**
     * @param string $prefix
     * @param bool   $add_global
     *
     * @return string
     */
    private function sanitizePrefix(string $prefix, bool $add_global = true)
    {
        if ($add_global && $global_prefix = $this->getGlobalPrefix()) {
            $prefix = "{$global_prefix}_{$prefix}";
        }

        $prefix = strtolower($prefix);
        $prefix = preg_replace('/[^a-z0-9_\-]/', '_', $prefix);

        return $prefix;
    }

    /**
     * @param string|array           $key
     * @param mixed|null             $data
     * @param string                 $group
     * @param int|\DateInterval|null $ttl
     *
     * @return bool
     */
    public function add($key, $data = null, string $group = 'default', $ttl = null)
    {
        if ($this->has($key, $group)) {
            return false;
        }

        return $this->set($key, $data, $group, $ttl);
    }

    /**
     * @param string $key
     * @param string $group
     *
     * @return bool
     */
    private function has(string $key, string $group = 'default')
    {
        $result = false;

        $key = $this->sanitizeKey($key, $group);

        try {
            $result = $this->cache_instance->hasItem($key);
        } catch (\Exception $e) {
            error_log($e);
        } catch (InvalidArgumentException $e) {
            error_log($e);
        }

        return $result;
    }

    /**
     * @param string|array           $key
     * @param mixed|null             $data
     * @param string                 $group
     * @param int|\DateInterval|null $ttl
     *
     * @return bool
     */
    public function set($key, $data = null, string $group = 'default', int $ttl = DAY_IN_SECONDS)
    {
        $result = false;

        if ($this->isSuspendCacheAddition()) {
            return $result;
        }

        if (!\in_array($group, $this->ignored_groups)) {
            $key = $this->sanitizeKey($key, $group);

            try {
                $cacheItemTags = [
                    $this->sanitizePrefix($group),
                    $this->getMultisitePrefix(),
                    $this->getPoolPrefix(),
                ];

                if (($global_prefix = $this->getGlobalPrefix()) && $global_prefix !== $this->getPoolPrefix()) {
                    $cacheItemTags[] = $global_prefix;
                }

                $cacheItem = $this->cache_instance->getItem($key);
                $cacheItem->set($data);

                $cacheItem->addTags($cacheItemTags);

                $cacheItem->expiresAfter($ttl);

                $result = $this->cache_instance->save($cacheItem);

                if ($result) {
                    $this->addToInternalCache($key, $cacheItem->get());
                }
            } catch (\Exception $e) {
                error_log($e);
            }
        }

        return $result;
    }

    /**
     * @return bool
     */
    private function isSuspendCacheAddition()
    {
        return \function_exists('wp_suspend_cache_addition') && wp_suspend_cache_addition();
    }

    /**
     * @return bool
     */
    private function isMultisite()
    {
        return \function_exists('is_multisite') && is_multisite();
    }

    /**
     * @return string
     */
    private function getMultisitePrefix()
    {
        return "{$this->getPoolPrefix()}_blog_{$this->blog_prefix}";
    }

    /**
     * @param string|array           $key
     * @param mixed|null             $data
     * @param string                 $group
     * @param int|\DateInterval|null $ttl
     *
     * @return bool
     */
    public function replace($key, $data = null, string $group = 'default', $ttl = null)
    {
        if ($this->has($key, $group)) {
            return $this->set($key, $data, $group, $ttl);
        }

        return false;
    }

    /**
     * @param string|array $key
     * @param string       $group
     *
     * @return bool
     */
    public function delete($key, string $group = 'default')
    {
        $result = false;
        if ($this->isSuspendCacheAddition()) {
            return $result;
        }

        $key = $this->sanitizeKey($key, $group);

        $result = $this->deleteFromInternalCache($key);

        if (!\in_array($group, $this->ignored_groups)) {
            try {
                $result = $this->cache_instance->deleteItem($key);
            } catch (\Exception $e) {
                error_log($e);
            } catch (InvalidArgumentException $e) {
                error_log($e);
            }
        }

        return $result;
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    private function deleteFromInternalCache(string $key)
    {
        if (!empty($this->cache[$key])) {
            unset($this->cache[$key]);

            return true;
        }

        return false;
    }

    /**
     * @param string|array $key
     * @param int          $offset
     * @param string       $group
     *
     * @return bool
     */
    public function decrement($key, int $offset = 1, string $group = 'default')
    {
        $result = false;

        if ($this->isSuspendCacheAddition()) {
            return $result;
        }

        $key = $this->sanitizeKey($key, $group);

        if (\in_array($group, $this->ignored_groups)) {
            $value = $this->getFromInternalCache($key);
            $value -= $offset;

            $this->addToInternalCache($key, $value);

            return $value;
        }

        $cacheItemTags = [
            $this->sanitizePrefix($group),
            $this->getMultisitePrefix(),
            $this->getPoolPrefix(),
        ];

        if (($global_prefix = $this->getGlobalPrefix()) && $global_prefix !== $this->getPoolPrefix()) {
            $cacheItemTags[] = $global_prefix;
        }

        try {
            $cacheItem = $this->cache_instance->getItem($key);
            $cacheItem->decrement($offset);
            $cacheItem->addTags($cacheItemTags);

            $result = $this->cache_instance->save($cacheItem);

            if ($result) {
                $this->addToInternalCache($key, $cacheItem->get());
            }
        } catch (\Exception $e) {
            error_log($e);
        }

        return $result;
    }

    /**
     * @param string $key
     *
     * @return bool|mixed
     */
    private function getFromInternalCache(string $key)
    {
        return $this->cache[$key] ?? false;
    }

    /**
     * @param string|array $key
     * @param int          $offset
     * @param string       $group
     *
     * @return bool
     */
    public function increment($key, int $offset = 1, string $group = 'default')
    {
        $result = false;

        if ($this->isSuspendCacheAddition()) {
            return $result;
        }

        $key = $this->sanitizeKey($key, $group);

        if (\in_array($group, $this->ignored_groups)) {
            $value = $this->getFromInternalCache($key);
            $value += $offset;

            $this->addToInternalCache($key, $value);

            return $value;
        }

        $cacheItemTags = [
            $this->sanitizePrefix($group),
            $this->getMultisitePrefix(),
            $this->getPoolPrefix(),
        ];

        if (($global_prefix = $this->getGlobalPrefix()) && $global_prefix !== $this->getPoolPrefix()) {
            $cacheItemTags[] = $global_prefix;
        }

        try {
            $cacheItem = $this->cache_instance->getItem($key);
            $cacheItem->increment($offset);
            $cacheItem->addTags($cacheItemTags);

            $result = $this->cache_instance->save($cacheItem);

            if ($result) {
                $this->addToInternalCache($key, $cacheItem->get());
            }
        } catch (\Exception $e) {
            error_log($e);
        }

        return $result;
    }

    /**
     * @return bool
     */
    public function flush()
    {
        $result = false;

        $this->cache = [];

        try {
            $tag = $this->isMultisite() ? $this->getMultisitePrefix() : $this->getPoolPrefix();

            $result = $this->cache_instance->deleteItemsByTag($tag);
        } catch (\Exception $e) {
            error_log($e);
        }

        return $result;
    }

    /**
     * @param int $blog_id
     *
     * @return bool
     */
    public function switchToBlog(int $blog_id)
    {
        if (!$this->isMultisite()) {
            return false;
        }

        $this->blog_prefix = $blog_id;

        return true;
    }

    /**
     * @return \Phpfastcache\Entities\DriverStatistic
     */
    public function getStats()
    {
        return $this->cache_instance->getStats();
    }
}
