<?php

namespace JazzMan\WPObjectCache;

/**
 * Class ObjectCache.
 */
class ObjectCache extends ObjectCacheBase
{


    /**
     * Adds a value to cache.
     *
     * If the specified key already exists, the value is not stored and the function
     * returns false.
     *
     * @see    http://www.php.net/manual/en/memcached.add.php
     *
     * @param string     $key        the key under which to store the value
     * @param mixed      $value      the value to store
     * @param string     $group      the group value appended to the $key
     * @param int|string $expiration the expiration time, defaults to 0
     *
     * @return bool returns TRUE on success or FALSE on failure
     */
    public function add($key, $value, $group = 'default', $expiration = 0)
    {
        $result = false;

        $derived_key = $this->buildKey($key, $group);

        if ($this->checkPermissions($group)) {
            $expiration = $this->validateExpiration($expiration);

            $value = maybe_serialize($value);

            $result = $this->m->add($derived_key, $value, $expiration);
        }

        if ($result) {
            $this->addToInternalCache($derived_key, $value);
        }

        return $result;
    }

    /**
     * Replaces a value in cache.
     *
     * This method is similar to "add"; however, is does not successfully set a value if
     * the object's key is not already set in cache.
     *
     * @see    http://www.php.net/manual/en/memcached.replace.php
     *
     * @param string     $key        the key under which to store the value
     * @param mixed      $value      the value to store
     * @param string     $group      the group value appended to the $key
     * @param int|string $expiration the expiration time, defaults to 0
     *
     * @return bool returns TRUE on success or FALSE on failure
     */
    public function replace($key, $value, $group = 'default', $expiration = 0)
    {
        return $this->add_or_replace(false, $key, $value, $group, $expiration);
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
    private function add_or_replace($add, $key, $value, $group = 'default', $expiration = 0)
    {
        $cache_addition_suspended = \function_exists('wp_suspend_cache_addition')
            ? wp_suspend_cache_addition()
            : false;

        if ($add && $cache_addition_suspended) {
            return false;
        }

        $result = true;

        $derived_key = $this->buildKey($key, $group);

        // If group is a non-Memcached group, save to internal cache, not Memcached
        if ($this->checkPermissions($group)) {
            $_expiration = $this->validateExpiration($expiration);

            $_value = maybe_serialize($value);

            if ($add) {
                $result = $this->m->add($derived_key, $_value, $_expiration);
            } else {
                $result = $this->m->replace($derived_key, $_value, $_expiration);
            }

            if ($this->isResNotstored()) {
                $result = $this->set($key, $value, $group, $expiration);
            }
        }

        $exists = isset($this->cache[$derived_key]);

        if ($add === $exists) {
            return false;
        }

        if ($result) {
            $this->addToInternalCache($derived_key, $value);
        }

        return $result;
    }

    /**
     * Decrement a numeric item's value.
     *
     * Alias for $this->decrement. Other caching backends use this abbreviated form of the function. It *may* cause
     * breakage somewhere, so it is nice to have. This function will also allow the core unit tests to pass.
     *
     * @param string $key    the key under which to store the value
     * @param int    $offset the amount by which to decrement the item's value
     * @param string $group  the group value appended to the $key
     *
     * @return int|bool returns item's new value on success or FALSE on failure
     */
    public function decr($key, $offset = 1, $group = 'default')
    {
        $derived_key = $this->buildKey($key, $group);
        $offset = (int) $offset;

        // If group is a non-Memcached group, save to internal cache, not Memcached
        if ($this->checkPermissions($group)) {
            $value = $this->getFromInternalCache($derived_key, $group);
            $value -= $offset;
            $this->addToInternalCache($derived_key, $value);

            return $value;
        }

        $result = $this->m->decrement($derived_key, $offset);

        if ($this->isResSuccess()) {
            $this->addToInternalCache($derived_key, $result);
        }

        return $result;
    }

    /**
     * Remove the item from the cache.
     *
     * Remove an item from memcached with identified by $key after $time seconds. The
     * $time parameter allows an object to be queued for deletion without immediately
     * deleting. Between the time that it is queued and the time it's deleted, add,
     * replace, and get will fail, but set will succeed.
     *
     * @see http://www.php.net/manual/en/memcached.delete.php
     *
     * @param string $key   the key under which to store the value
     * @param string $group the group value appended to the $key
     * @param int    $time  the amount of time the server will wait to delete the item in seconds
     *
     * @return bool returns TRUE on success or FALSE on failure
     */
    public function delete($key, $group = 'default', $time = 0)
    {
        $result = false;

        $derived_key = $this->buildKey($key, $group);

        if ($this->checkPermissions($group)) {
            $result = $this->m->delete($derived_key, $time);
        }

        if (isset($this->cache[$derived_key]) && $this->isResSuccess()) {
            unset($this->cache[$derived_key]);
            $result = true;
        }

        return $result;
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
        $result = $this->m->flush($delay);

        // Only reset the runtime cache if memcached was properly flushed
        if ($this->isResSuccess()) {
            $this->cache = [];
        }

        return $result;
    }

    /**
     * Retrieve object from cache.
     *
     * Gets an object from cache based on $key and $group. In order to fully support the $cache_cb and $cas_token
     * parameters, the runtime cache is ignored by this function if either of those values are set. If either of
     * those values are set, the request is made directly to the memcached server for proper handling of the
     * callback and/or token. Note that the $cas_token variable cannot be directly passed to the function. The
     * variable need to be first defined with a non null value.
     *
     * If using the $cache_cb argument, the new value will always have an expiration of time of 0 (forever). This
     * is a limitation of the Memcached PECL extension.
     *
     * @see http://www.php.net/manual/en/memcached.get.php
     *
     * @param string    $key   the key under which to store the value
     * @param string    $group the group value appended to the $key
     * @param bool      $force whether or not to force a cache invalidation
     * @param bool|null $found variable passed by reference to determine if the value was found or not
     *
     * @return bool|mixed cached object value
     */
    public function get($key, $group = 'default', $force = false, &$found = null)
    {
        $derived_key = $this->buildKey($key, $group);

        if (isset($this->cache[$derived_key]) && !$force) {
            $found = true;
            ++$this->cache_hits;

            return \is_object($this->cache[$derived_key]) ? clone $this->cache[$derived_key] : $this->cache[$derived_key];
        }

        if (!$this->checkPermissions($group)) {
            $found = false;
            ++$this->cache_misses;

            return false;
        }

        $result = $this->m->get($derived_key);

        if ($this->isResNotfound()) {
            $found = false;
            ++$this->cache_misses;

            return false;
        }

        $found = true;

        ++$this->cache_hits;

        $value = maybe_unserialize($result);

        $this->addToInternalCache($derived_key, $value);

        $value = \is_object($value) ? clone $value : $value;

        return $value;
    }

    /**
     * Synonymous with $this->incr.
     *
     * Certain plugins expect an "incr" method on the $wp_object_cache object (e.g., Batcache). Since the original
     * version of this library matched names to the memcached methods, the "incr" method was missing. Adding this
     * method restores compatibility with plugins expecting an "incr" method.
     *
     * @param string $key    the key under which to store the value
     * @param int    $offset the amount by which to increment the item's value
     * @param string $group  the group value appended to the $key
     *
     * @return int|bool returns item's new value on success or FALSE on failure
     */
    public function incr($key, $offset = 1, $group = 'default')
    {
        $derived_key = $this->buildKey($key, $group);
        $offset = (int) $offset;

        // If group is a non-Memcached group, save to internal cache, not Memcached
        if ($this->checkPermissions($group)) {
            $value = $this->getFromInternalCache($derived_key, $group);
            $value += $offset;

            $this->addToInternalCache($derived_key, $value);

            return $value;
        }

        $result = $this->m->increment($derived_key, $offset);

        if ($this->isResSuccess()) {
            $this->addToInternalCache($derived_key, $result);
        }

        return $result;
    }

    /**
     * Sets a value in cache.
     *
     * The value is set whether or not this key already exists in memcached.
     *
     * @see http://www.php.net/manual/en/memcached.set.php
     *
     * @param string     $key        the key under which to store the value
     * @param mixed      $value      the value to store
     * @param string     $group      the group value appended to the $key
     * @param int|string $expiration the expiration time, defaults to 0
     *
     * @return bool returns TRUE on success or FALSE on failure
     */
    public function set($key, $value, $group = 'default', $expiration = 0)
    {
        $result = true;

        $derived_key = $this->buildKey($key, $group);

        // If group is a non-Memcached group, save to runtime cache, not Memcached

        if ($this->checkPermissions($group)) {
            $expiration = $this->validateExpiration($expiration);

            $value = maybe_serialize($value);

            $result = $this->m->set($derived_key, $value, $expiration);
        }

        // if the set was successful, or we didn't go to redis
        if ($result) {
            $this->addToInternalCache($derived_key, $value);
        }

        return $result;
    }

    /**
     * @param string $group
     *
     * @return bool
     */
    private function checkPermissions($group)
    {
        return !$this->isIgnoredGroup($group) && $this->getServerStatus();
    }

}
