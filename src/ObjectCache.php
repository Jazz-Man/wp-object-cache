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

        if ($this->isSuspendCacheAddition()) {
            return $result;
        }

        $derived_key = $this->buildKey($key, $group);

        if ($this->isIgnoredGroup($group)) {
            if ($this->hasCache($derived_key)) {
                return $result;
            }

            $this->setCache($derived_key, $value);

            return true;
        }

        if ($this->getServerStatus($group)) {
            $expiration = $this->validateExpiration($expiration);

            $value = maybe_serialize($value);

            $result = $this->getMc($group)->add($derived_key, $value, $expiration);

            if ($this->success($group)) {
                $this->setCache($derived_key, $value);
            }

            return $result;
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
        $result = false;

        $derived_key = $this->buildKey($key, $group);

        if ($this->isIgnoredGroup($group)) {
            if ($this->hasCache($derived_key)) {
                $this->setCache($derived_key, $value);

                return true;
            }

            return $result;
        }

        if ($this->getServerStatus($group)) {
            $expiration = $this->validateExpiration($expiration);

            $value = maybe_serialize($value);

            $result = $this->getMc($group)->replace($derived_key, $value, $expiration);

            if ($this->success($group)) {
                $this->setCache($derived_key, $value);
            }

            return $result;
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
        $result = false;
        $derived_key = $this->buildKey($key, $group);

        // If group is a non-Memcached group, save to internal cache, not Memcached
        if ($this->isIgnoredGroup($group)) {
            $value = $this->getCache($derived_key);
            if ($value && $value >= 0) {
                if (is_numeric($value)) {
                    $value -= (int) $offset;
                } else {
                    $value = 0;
                }

                if ($value < 0) {
                    $value = 0;
                }

                $this->setCache($derived_key, $value);

                return $value;
            }

            return $result;
        }

        if ($this->getServerStatus($group)) {
            $result = $this->getMc($group)->decrement($derived_key, $offset);

            if ($this->success($group)) {
                $this->setCache($derived_key, $result);
            }

            return $result;
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

        if ($this->isIgnoredGroup($group)) {
            return $this->deleteCache($derived_key);
        }

        if ($this->getServerStatus($group)) {
            $result = $this->getMc($group)->delete($derived_key, $time);

            if ($this->success($group)) {
                $result = $this->deleteCache($derived_key);
            }

            return $result;
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
        $result = false;
        $derived_key = $this->buildKey($key, $group);

        if ($this->isIgnoredGroup($group) && $this->hasCache($derived_key) && !$force) {
            $found = true;
            ++$this->cache_hits;

            return $this->getCache($derived_key);
        }

        if ($this->getServerStatus($group)) {
            $result = $this->getMc($group)->get($derived_key);

            if ($this->isResNotfound($group)) {
                $found = false;
                ++$this->cache_misses;

                return false;
            }

            $found = true;

            ++$this->cache_hits;

            $value = maybe_unserialize($result);

            $this->setCache($derived_key, $value);

            return \is_object($value) ? clone $value : $value;
        }

        return $result;
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
        $result = false;
        $derived_key = $this->buildKey($key, $group);

        if ($this->isIgnoredGroup($group)) {
            $value = $this->getCache($derived_key);

            if ($value && $value >= 0) {
                if (is_numeric($value)) {
                    $value += (int) $offset;
                } else {
                    $value = 0;
                }

                if ($value < 0) {
                    $value = 0;
                }

                $this->setCache($derived_key, $value);

                return $value;
            }

            return false;
        }

        if ($this->getServerStatus($group)) {
            $result = $this->getMc($group)->increment($derived_key, (int) $offset);

            if ($this->success($group)) {
                $this->setCache($derived_key, $result);
            }

            return $result;
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
        $result = false;

        $derived_key = $this->buildKey($key, $group);

        if ($this->isIgnoredGroup($group)) {
            $this->setCache($derived_key, $value);

            return true;
        }

        if ($this->getServerStatus($group)) {
            $expiration = $this->validateExpiration($expiration);
            $value = maybe_serialize($value);

            $result = $this->getMc($group)->set($derived_key, $value, $expiration);

            if ($this->success($group)) {
                $this->setCache($derived_key, $result);
            }

            return $result;
        }

        return $result;
    }
}
