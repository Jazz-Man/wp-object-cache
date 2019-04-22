<?php

namespace JazzMan\WPObjectCache;

use Phpfastcache\Core\Item\ExtendedCacheItemInterface;
use Psr\Cache\InvalidArgumentException;

/**
 * Class DriverAdapter.
 */
class DriverAdapter
{
    /**
     * @var \JazzMan\WPObjectCache\Driver
     */
    private $driver;

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
     * @var array
     */
    private $ignored_groups;
    /**
     * @var int
     */
    private $blog_prefix;

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

        $this->multisite = is_multisite();
        $this->setCacheGroups();
        $this->setGlobalPrefix();
        $this->driver = app_object_cache();
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
        $driver_prefix = $this->driver->getCachePrefix() ?: home_url();

        $this->global_prefix = $this->sanitizePrefix($driver_prefix);
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
        } catch (\Exception $e) {
            dump($e);
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
        return $this->isIgnoredGroup($group) ? $this->driver->getMemstatic() : $this->driver->getCacheInstance();
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
        $prefix = \in_array($group, $this->global_groups) ? $this->global_prefix : $this->blog_prefix;

        return $this->sanitizePrefix("{$prefix}_{$group}_{$key}");
    }

    /**
     * @param string $prefix
     *
     * @return string
     */
    private function sanitizePrefix(string $prefix)
    {
        $prefix = strtolower($prefix);
        $prefix = preg_replace('/[^a-z0-9_\-]/', '', $prefix);

        return $prefix;
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
        return $this->storeItem($key, $data, $group, $ttl);
    }

    /**
     * @param string|array           $key
     * @param mixed|null             $data
     * @param string                 $group
     * @param int|\DateInterval|null $ttl
     *
     * @return bool
     */
    private function storeItem($key, $data = null, string $group = 'default', $ttl = null)
    {
        $result = false;

        if ($this->isSuspendCacheAddition()) {
            return $result;
        }

        $driver = $this->getDriver($group);

        try {
            if (\is_array($key)) {
                foreach ($key as $item => $value) {
                    $_key = $this->sanitizeKey($item, $group);
                    $value = maybe_serialize($value);

                    $cacheItem = $driver->getItem($_key);
                    $cacheItem->set($value);
                    $cacheItem->addTags([
                        $group,
                        $this->global_prefix,
                    ]);

                    $this->setCacheItemExpires($cacheItem, $ttl);

                    $driver->saveDeferred($cacheItem);
                    unset($cacheItem);
                }

                $result = $driver->commit();
            } else {
                $key = $this->sanitizeKey($key, $group);
                $data = maybe_serialize($data);

                $cacheItem = $driver->getItem($key);
                $cacheItem->set($data);
                $cacheItem->addTags([
                    $group,
                    $this->global_prefix,
                ]);

                $this->setCacheItemExpires($cacheItem, $ttl);

                $result = $driver->save($cacheItem);
            }
        } catch (\Exception $e) {
            dump($e);
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
     * @param ExtendedCacheItemInterface $cacheItem
     * @param int|\DateInterval|null     $ttl
     */
    private function setCacheItemExpires(&$cacheItem, $ttl = null)
    {
        if (\is_int($ttl)) {
            if ($ttl <= 0) {
                $cacheItem->expiresAt(new \DateTime('@0'));
            } elseif ($ttl instanceof \DateInterval) {
                $cacheItem->expiresAfter($ttl);
            }
        }
    }

    /**
     * @param string|array           $key
     * @param mixed|null             $data
     * @param string                 $group
     * @param int|\DateInterval|null $ttl
     *
     * @return bool
     */
    public function set($key, $data = null, string $group = 'default', $ttl = null)
    {
        return $this->storeItem($key, $data, $group, $ttl);
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
        return $this->storeItem($key, $data, $group, $ttl);
    }

    /**
     * @param string $key
     * @param mixed  $value
     * @param string $group
     *
     * @return bool
     */
    public function append(string $key, $value, string $group = 'default')
    {
        return $this->appendOrPrepend(true, $key, $value, $group);
    }

    /**
     * @param bool   $append
     * @param string $key
     * @param        $value
     * @param string $group
     *
     * @return bool
     */
    private function appendOrPrepend(bool $append, string $key, $value, string $group = 'default')
    {
        $result = false;

        if ($this->isSuspendCacheAddition()) {
            return $result;
        }

        $driver = $this->getDriver($group);

        $key = $this->sanitizeKey($key, $group);
        $value = maybe_serialize($value);

        try {
            $cacheItem = $driver->getItem($key);
            $cacheItem->addTags([
                $group,
                $this->global_prefix,
            ]);

            if ($append) {
                $cacheItem->append($value);
            } else {
                $cacheItem->prepend($value);
            }

            $result = $driver->save($cacheItem);
        } catch (\Exception $e) {
            dump($e);
        }

        return $result;
    }

    /**
     * @param string $key
     * @param mixed  $value
     * @param string $group
     *
     * @return bool
     */
    public function prepend(string $key, $value, string $group = 'default')
    {
        return $this->appendOrPrepend(false, $key, $value, $group);
    }

    /**
     * @param string|array $key
     * @param string       $group
     * @param int          $time
     *
     * @return bool
     */
    public function delete($key, string $group = 'default', $time = 0)
    {
        $result = false;
        if ($this->isSuspendCacheAddition()) {
            return $result;
        }

        $driver = $this->getDriver($group);

        try {
            if (\is_array($key)) {
                $keys = $this->sanitizeKeys($key, $group);
                $result = $driver->deleteItems($keys);
            } else {
                $key = $this->sanitizeKey($key, $group);

                $result = $driver->deleteItem($key);
            }
        } catch (InvalidArgumentException $e) {
            dump($e);
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

        $driver = $this->getDriver($group);

        try {
            if (\is_array($key)) {
                foreach ($key as $item => $value) {
                    $_key = $this->sanitizeKey($item, $group);

                    $cacheItem = $driver->getItem($_key);
                    if ($this->isValidCacheItem($cacheItem)) {

                        $cacheItem->addTags([
                            $group,
                            $this->global_prefix,
                        ]);

                        if ($decrement) {
                            $cacheItem->decrement($offset);
                        } else {
                            $cacheItem->increment($offset);
                        }
                        $driver->saveDeferred($cacheItem);
                    }
                    unset($cacheItem);
                }

                $result = $driver->commit();
            } else {
                $key = $this->sanitizeKey($key, $group);

                $cacheItem = $driver->getItem($key);

                if ($this->isValidCacheItem($cacheItem)) {

                    $cacheItem->addTags([
                        $group,
                        $this->global_prefix,
                    ]);

                    if ($decrement) {
                        $cacheItem->decrement($offset);
                    } else {
                        $cacheItem->increment($offset);
                    }

                    $result = $driver->save($cacheItem);
                }
            }
        } catch (\Exception $e) {
            dump($e);
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
    public function increment($key, string $group = 'default', $offset = 1)
    {
        return $this->decrementOrIncrement(false, $key, $group, $offset);
    }

    /**
     * @param string $key
     * @param string $group
     *
     * @return bool
     */
    public function has(string $key, string $group = 'default')
    {
        $result = false;
        $key = $this->sanitizeKey($key, $group);
        $driver = $this->getDriver($group);
        try {
            $cacheItem = $driver->getItem($key);
            $result = $cacheItem->isHit() && !$cacheItem->isExpired();
        } catch (\Exception $e) {
            dump($e);
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
