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
     * @var \JazzMan\WPObjectCache\Driver
     */
    private $cache_instance;

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
    private $global_prefix;

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
    private $_cache_instance;

    /**
     * @var MemstaticDriver
     */
    private $memstatic_instance;

    /**
     * DriverAdapter constructor.
     *
     * @param null $config
     */
    public function __construct($config = null)
    {
        global $blog_id;

        // Blog prefix
        $this->blog_prefix = (int) $blog_id;
        $this->multisite_prefix = "blog_{$this->blog_prefix}";

        $this->cache_instance = app_object_cache();
        $this->_cache_instance = app_object_cache()->getCacheInstance();
        $this->memstatic_instance = app_object_cache()->getMemstatic();

        $this->multisite = is_multisite();
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
            'themes',
            'bookmark',
        ];

        $this->ignored_groups = [
            'counts',
            'plugins',
        ];
    }

    private function setGlobalPrefix()
    {
        $driver_prefix = Driver::hasRedis() && $this->cache_instance->getCachePrefix() ? '' : $_SERVER['HTTP_HOST'];

        $this->global_prefix = $this->sanitizePrefix($driver_prefix, false);
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

        $driver = $this->getDriver($group);

        try {
            if (\is_array($key)) {
                $keys = $this->sanitizeKeys($key, $group);

                $result = array_map(static function (ExtendedCacheItemInterface $item) {
                    if ($this->isValidCacheItem($item)) {
                        return $this->returnCacheItem($item);
                    }

                    return false;
                }, $driver->getItems($keys));
            } else {
                $key = $this->sanitizeKey($key, $group);

                $cacheItem = $driver->getItem($key);
                if ($this->isValidCacheItem($cacheItem)) {
                    $result = $this->returnCacheItem($cacheItem);
                }
            }

            $found = true;
        } catch (\Exception $e) {
            $found = false;
            error_log($e);
        }

        return $result;
    }

    /**
     * @param string $group
     *
     * @return \Phpfastcache\Core\Pool\ExtendedCacheItemPoolInterface|\Phpfastcache\Drivers\Memstatic\Driver
     */
    private function getDriver(string $group = 'default')
    {
        return $this->isIgnoredGroup($group) ? $this->memstatic_instance : $this->_cache_instance;
    }

    /**
     * @param string|array $keys
     * @param string|array $groups
     *
     * @return array
     */
    private function sanitizeKeys(array $keys, $groups = 'default')
    {
        $derived_keys = [];

        // If strings sent, convert to arrays for proper handling
        if (!\is_array($groups)) {
            $groups = (array) $groups;
        }

        $keys_count = \count($keys);
        $groups_count = \count($groups);

        if ($keys_count === $groups_count) {
            for ($i = 0; $i < $keys_count; ++$i) {
                $derived_keys[] = $this->sanitizeKey($keys[$i], $groups[$i]);
            }
        } elseif ($keys_count > $groups_count) {
            for ($i = 0; $i < $keys_count; ++$i) {
                if (isset($groups[$i])) {
                    $derived_keys[] = $this->sanitizeKey($keys[$i], $groups[$i]);
                } elseif (1 === $groups_count) {
                    $derived_keys[] = $this->sanitizeKey($keys[$i], $groups[0]);
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
    private function sanitizeKey(string $key, string $group)
    {
        return $this->sanitizePrefix("{$this->blog_prefix}_{$group}_{$key}");
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
    private function has(string $key, string $group = 'default')
    {
        $key = $this->sanitizeKey($key, $group);

        try {
            $memstatic_item = $this->memstatic_instance->getItem($key);

            if ($memstatic_item->isHit()) {
                return true;
            }

            $cache_item = $this->_cache_instance->getItem($key);
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

                        $this->_cache_instance->saveDeferred($cacheItem);
                        unset($cacheItem);
                    }

                    $result = $this->_cache_instance->commit();
                } else {
                    $cacheItem = $this->storeItem($key, $data, $group, $ttl);

                    $result = $this->_cache_instance->save($cacheItem);
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
    private function storeItem(
        $key,
        $data = null,
        string $group = 'default',
        int $ttl = DAY_IN_SECONDS,
        bool $to_internal = false
    ) {
        $cacheItemTags = [
            $this->sanitizePrefix($group),
            $this->multisite_prefix,
        ];

        if (!empty($this->global_prefix)) {
            $cacheItemTags[] = $this->global_prefix;
        }
        $key = $this->sanitizeKey($key, $group);
        $data = maybe_serialize($data);

        $driver = $to_internal ? $this->memstatic_instance : $this->_cache_instance;

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

                $result = $this->_cache_instance->deleteItems($keys);
            } else {
                $key = $this->sanitizeKey($key, $group);

                $this->memstatic_instance->deleteItem($key);

                $result = $this->_cache_instance->deleteItem($key);
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
                            $this->_cache_instance->saveDeferred($cacheItem);
                        }
                        unset($cacheItem);
                    }

                    $result = $this->_cache_instance->commit();
                } else {
                    $cacheItem = $this->storeDecrement($decrement, $key, $group, $offset);

                    if ($this->isValidCacheItem($cacheItem)) {
                        $result = $this->_cache_instance->save($cacheItem);
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

        $driver = $to_internal ? $this->memstatic_instance : $this->_cache_instance;

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
            $result = $this->_cache_instance->clear();

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
