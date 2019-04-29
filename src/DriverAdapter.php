<?php

namespace JazzMan\WPObjectCache;

use Phpfastcache\Core\Item\ExtendedCacheItemInterface;
use Phpfastcache\Drivers\Redis\Driver as RedisDriver;
use Phpfastcache\Drivers\Memcached\Driver as MemcachedDriver;
use Phpfastcache\Drivers\Apcu\Driver as ApcuDriver;
use Phpfastcache\Drivers\Memstatic\Driver as MemstaticDriver;
use Psr\Cache\InvalidArgumentException;

/**
 * Class DriverAdapter.
 */
class DriverAdapter
{
    /**
     * @var bool
     */
    private $multisite;

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
     * @var string
     */
    private $multisite_prefix;
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
     * @var MemstaticDriver
     */
    private $memstatic_instance;

    /**
     * @var int
     */
    public $cache_hits = 0;

    /**
     * @var int
     */
    public $cache_misses = 0;

    /**
     * DriverAdapter constructor.
     *
     * @param null $config
     */
    public function __construct($config = null)
    {
        global $blog_id;

        $this->blog_prefix = (int) $blog_id;
        $this->multisite = is_multisite();

        $this->cache_instance     = app_object_cache()->getCacheInstance();
        $this->memstatic_instance = app_object_cache()->getMemstatic();

        $this->setCacheGroups();
        $this->setGlobalPrefix();
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

    private function setGlobalPrefix()
    {
        $has_cache_prefix = \defined('WP_CACHE_PREFIX') && !empty(WP_CACHE_PREFIX);

        $this->pool_prefix = $this->sanitizePrefix($_SERVER['HTTP_HOST'],false);

        $this->multisite_prefix = "{$this->pool_prefix}_blog_{$this->blog_prefix}";

        $this->global_prefix = $has_cache_prefix ? '' : $this->pool_prefix;

    }

    /**
     * @param string $prefix
     * @param bool   $add_global
     *
     * @return string
     */
    private function sanitizePrefix(string $prefix, bool $add_global = true)
    {
        if ($add_global && '' !== $this->global_prefix) {
            $prefix = "{$this->global_prefix}_{$prefix}";
        }

        $prefix = strtolower($prefix);
        $prefix = preg_replace('/[^a-z0-9_\-]/', '_', $prefix);

        return $prefix;
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

        try {
            if (\is_array($key)) {
                $keys = $this->sanitizeKeys($key, $group);

                $Items = $this->cache_instance->getItems($keys);

                $result = array_map(static function (ExtendedCacheItemInterface $item) {
                    if ($this->isValidCacheItem($item)) {
                        ++$this->cache_hits;
                        return $this->returnCacheItem($item);
                    }

                    ++$this->cache_misses;

                    return false;
                }, $Items);
            } else {
                $key = $this->sanitizeKey($key, $group);

                $cacheItem = $this->cache_instance->getItem($key);
                if ($this->isValidCacheItem($cacheItem)) {
                    ++$this->cache_hits;
                    $result = $this->returnCacheItem($cacheItem);
                }
            }

        } catch (\Exception $e) {
            error_log($e);
        }

        ++$this->cache_misses;

//        if ($result === false){
////            dump(compact('key','group'));
//            dump($this->ignored_groups);
//        }

        return $result;
    }

    /**
     * @param string $group
     *
     * @return \Phpfastcache\Core\Pool\ExtendedCacheItemPoolInterface|\Phpfastcache\Drivers\Memstatic\Driver
     */
    private function getDriver(string $group = 'default')
    {
        return $this->isIgnoredGroup($group) ? $this->memstatic_instance : $this->cache_instance;
    }

    /**
     * @param string|array $keys
     * @param string|array $group
     *
     * @return array
     */
    public function sanitizeKeys(array $keys, $group = 'default')
    {
        $derived_keys = [];

        // If strings sent, convert to arrays for proper handling
        if (!\is_array($group)) {
            $group = (array) $group;
        }

        $keys_count = \count($keys);
        $groups_count = \count($group);

        if ($keys_count === $groups_count) {
            for ($i = 0; $i < $keys_count; ++$i) {
                $derived_keys[] = $this->sanitizeKey($keys[$i], $group[$i]);
            }
        } elseif ($keys_count > $groups_count) {
            for ($i = 0; $i < $keys_count; ++$i) {
                if (isset($group[$i])) {
                    $derived_keys[] = $this->sanitizeKey($keys[$i], $group[$i]);
                } elseif (1 === $groups_count) {
                    $derived_keys[] = $this->sanitizeKey($keys[$i], $group[0]);
                } else {
                    $derived_keys[] = $this->sanitizeKey($keys[$i], 'default');
                }
            }
        }

        return $derived_keys;
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
     * @param \Phpfastcache\Core\Item\ExtendedCacheItemInterface $cacheItem
     *
     * @return mixed
     */
    private function returnCacheItem($cacheItem)
    {
        $value = maybe_unserialize($cacheItem->get());

        return \is_object($value) ? clone $value : $value;
    }

    /**
     * @param string $key
     * @param string $group
     *
     * @return string
     */
    public function sanitizeKey(string $key, string $group = 'default')
    {
        return $this->sanitizePrefix("{$group}_{$key}");
    }

    /**
     * @param string $group
     *
     * @return bool
     */
    private function isIgnoredGroup(string $group)
    {
        return \in_array($group, $this->ignored_groups);
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
    public function has(string $key, string $group = 'default')
    {
        $key = $this->sanitizeKey($key, $group);

        try {
            $memstatic_item = $this->memstatic_instance->getItem($key);

            if ($memstatic_item->isHit()) {
                return true;
            }

            $cache_item = $this->cache_instance->getItem($key);
            if ($cache_item->isHit() && !$cache_item->isExpired()) {
                return true;
            }
        } catch (\Exception $e) {
            error_log($e);
        }

        return false;
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

        $is_array = \is_array($key);

        try {
            if ($is_array) {
                foreach ($key as $_key => $_data) {
                    $cacheItem = $this->storeItem($_key, $_data, $group, $ttl, true);

                    $this->memstatic_instance->saveDeferred($cacheItem);
                    unset($cacheItem);
                }

                $this->memstatic_instance->commit();
            } else {
                $cacheItem = $this->storeItem($key, $data, $group, $ttl, true);

                $this->memstatic_instance->save($cacheItem);
            }

            if (!$this->isIgnoredGroup($group)) {
                if ($is_array) {
                    foreach ($key as $_key => $_data) {
                        $cacheItem = $this->storeItem($_key, $_data, $group, $ttl);

                        $this->cache_instance->saveDeferred($cacheItem);
                        unset($cacheItem);
                    }

                    $result = $this->cache_instance->commit();
                } else {
                    $cacheItem = $this->storeItem($key, $data, $group, $ttl);

                    $result = $this->cache_instance->save($cacheItem);
                }
            }
        } catch (\Exception $e) {
            error_log($e);

            return false;
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
     * @param string|array $key
     * @param mixed|null   $data
     * @param string       $group
     * @param int          $ttl
     * @param bool         $to_internal
     *
     * @return \Phpfastcache\Core\Item\ExtendedCacheItemInterface
     *
     * @throws \Phpfastcache\Exceptions\PhpfastcacheCoreException
     * @throws \Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException
     * @throws \Phpfastcache\Exceptions\PhpfastcacheLogicException
     */
    private function storeItem($key, $data = null, string $group = 'default', int $ttl = DAY_IN_SECONDS, bool $to_internal = false) {
        $cacheItemTags = [
            $this->sanitizePrefix($group),
            $this->multisite_prefix,
            $this->pool_prefix
        ];

        $key = $this->sanitizeKey($key, $group);
        $data = maybe_serialize($data);

        $driver = $to_internal ? $this->memstatic_instance : $this->cache_instance;

        $cacheItem = $driver->getItem($key);
        $cacheItem->set($data);

        $cacheItem->addTags($cacheItemTags);

        $cacheItem->expiresAfter($ttl);

        return $cacheItem;
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

        if (!$this->has($key)) {
            return $result;
        }

        try {
            if (\is_array($key)) {
                $keys = $this->sanitizeKeys($key, $group);

                $this->memstatic_instance->deleteItems($keys);

                $result = $this->cache_instance->deleteItems($keys);
            } else {
                $key = $this->sanitizeKey($key, $group);

                $this->memstatic_instance->deleteItem($key);

                $result = $this->cache_instance->deleteItem($key);
            }
        } catch (\Exception $e) {
            error_log($e);
        } catch (InvalidArgumentException $e) {
            error_log($e);
        }

        return $result;
    }

    /**
     * @param string|array $key
     * @param string       $group
     * @param int          $offset
     *
     * @return bool
     */
    public function decrement($key, string $group = 'default', $offset = 1)
    {
        return $this->decrementOrIncrement(true, $key, $group, $offset);
    }

    /**
     * @param bool         $decrement
     * @param string|array $key
     * @param string       $group
     * @param int          $offset
     *
     * @return bool|mixed
     */
    private function decrementOrIncrement(bool $decrement, $key, string $group = 'default', $offset = 1)
    {
        $result = false;

        if ($this->isSuspendCacheAddition()) {
            return $result;
        }

        $is_array = \is_array($key);

        try {
            if ($is_array) {
                foreach ($key as $_key => $_offset) {
                    $cacheItem = $this->storeDecrement($decrement, $_key, $group, $_offset, true);
                    if ($this->isValidCacheItem($cacheItem)) {
                        $this->memstatic_instance->saveDeferred($cacheItem);
                    }
                    unset($cacheItem);
                }

                $this->memstatic_instance->commit();
            } else {
                $cacheItem = $this->storeDecrement($decrement, $key, $group, $offset, true);

                if ($this->isValidCacheItem($cacheItem)) {
                    $this->memstatic_instance->save($cacheItem);
                }
            }

            if (!$this->isIgnoredGroup($group)) {
                if ($is_array) {
                    foreach ($key as $_key => $_offset) {
                        $cacheItem = $this->storeDecrement($decrement, $_key, $group, $_offset);
                        if ($this->isValidCacheItem($cacheItem)) {
                            $this->cache_instance->saveDeferred($cacheItem);
                        }
                        unset($cacheItem);
                    }

                    $result = $this->cache_instance->commit();
                } else {
                    $cacheItem = $this->storeDecrement($decrement, $key, $group, $offset);

                    if ($this->isValidCacheItem($cacheItem)) {
                        $result = $this->cache_instance->save($cacheItem);
                    }
                }
            }
        } catch (\Exception $e) {
            error_log($e);

            return false;
        }

        return $result;
    }

    /**
     * @param bool   $decrement
     * @param        $key
     * @param string $group
     * @param int    $offset
     * @param bool   $to_internal
     *
     * @return \Phpfastcache\Core\Item\ExtendedCacheItemInterface
     *
     * @throws \Phpfastcache\Exceptions\PhpfastcacheCoreException
     * @throws \Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException
     * @throws \Phpfastcache\Exceptions\PhpfastcacheLogicException
     */
    private function storeDecrement(bool $decrement, $key, string $group = 'default', $offset = 1, bool $to_internal = false) {
        $cacheItemTags = [
            $this->sanitizePrefix($group),
            $this->multisite_prefix,
        ];

        if (!empty($this->global_prefix)) {
            $cacheItemTags[] = $this->global_prefix;
        }

        $driver = $to_internal ? $this->memstatic_instance : $this->cache_instance;

        $key = $this->sanitizeKey($key, $group);

        $cacheItem = $driver->getItem($key);
        $cacheItem->addTags($cacheItemTags);

        if ($decrement) {
            $cacheItem->decrement($offset);
        } else {
            $cacheItem->increment($offset);
        }

        return $cacheItem;
    }

    /**
     * @param string|array $key
     * @param string       $group
     * @param int          $offset
     *
     * @return bool
     */
    public function increment($key, string $group = 'default', $offset = 1)
    {
        return $this->decrementOrIncrement(false, $key, $group, $offset);
    }

    /**
     * @param $delay
     *
     * @return bool
     */
    public function flush(int $delay = null)
    {
        $result = false;

        $this->memstatic_instance->clear();

        try {

            $tag = $this->multisite ? $this->multisite_prefix : $this->pool_prefix;

            $result = $this->cache_instance->deleteItemsByTag($tag);


        } catch (\Exception $e) {
            error_log($e);
        }

        return $result;
    }

    /**
     * @param int $blog_id
     */
    public function switchToBlog(int $blog_id)
    {
        global $table_prefix;

        $this->blog_prefix = ($this->multisite ? $blog_id : $table_prefix).'_';
    }
}
