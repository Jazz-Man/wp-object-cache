<?php

use JazzMan\WPObjectCache\ObjectCache;
use JazzMan\WPObjectCache\OutputCache;

/**
 * Adds a value to cache.
 *
 * If the specified key already exists, the value is not stored and the function
 * returns false.
 *
 *
 * @see http://www.php.net/manual/en/memcached.add.php
 *
 * @param string $key        the key under which to store the value
 * @param mixed  $value      the value to store
 * @param string $group      the group value appended to the $key
 * @param int    $expiration the expiration time, defaults to 0
 *
 * @return bool returns TRUE on success or FALSE on failure
 */
function wp_cache_add($key, $value, $group = '', $expiration = 0)
{
    return wp_object_cache()->add($key, $value, $group, $expiration);
}


/**
 * Decrement a numeric item's value.
 *
 * Same as wp_cache_decrement. Original WordPress caching backends use wp_cache_decr. I
 * want both spellings to work.
 *
 *
 * @see http://www.php.net/manual/en/memcached.decrement.php
 *
 * @param string $key    the key under which to store the value
 * @param int    $offset the amount by which to decrement the item's value
 * @param string $group  the group value appended to the $key
 *
 * @return int|bool returns item's new value on success or FALSE on failure
 */
function wp_cache_decr($key, $offset = 1, $group = '')
{
    return wp_object_cache()->decr($key, $offset, $group);
}

/**
 * Remove the item from the cache.
 *
 * Remove an item from memcached with identified by $key after $time seconds. The
 * $time parameter allows an object to be queued for deletion without immediately
 * deleting. Between the time that it is queued and the time it's deleted, add,
 * replace, and get will fail, but set will succeed.
 *
 *
 * @see http://www.php.net/manual/en/memcached.delete.php
 *
 * @param string $key   the key under which to store the value
 * @param string $group the group value appended to the $key
 * @param int    $time  the amount of time the server will wait to delete the item in seconds
 *
 * @return bool returns TRUE on success or FALSE on failure
 */
function wp_cache_delete($key, $group = '', $time = 0)
{
    return wp_object_cache()->delete($key, $group, $time);
}

/**
 * Invalidate all items in the cache.
 *
 *
 * @see http://www.php.net/manual/en/memcached.flush.php
 *
 * @param int $delay number of seconds to wait before invalidating the items
 *
 * @return bool returns TRUE on success or FALSE on failure
 */
function wp_cache_flush($delay = 0)
{
    return wp_object_cache()->flush($delay);
}

/**
 * Retrieve object from cache.
 *
 * Gets an object from cache based on $key and $group. In order to fully support the $cache_cb and $cas_token
 * parameters, the runtime cache is ignored by this function if either of those values are set. If either of
 * those values are set, the request is made directly to the memcached server for proper handling of the
 * callback and/or token.
 *
 * Note that the $deprecated and $found args are only here for compatibility with the native wp_cache_get function.
 *
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
function wp_cache_get($key, $group, $force, $found)
{
    return wp_object_cache()->get($key, $group, $force, $found);
}


/**
 * Retrieve a cache key based on key & group.
 *
 *
 * @see http://www.php.net/manual/en/memcached.get.php
 *
 * @param string $key   the key under which a value is stored
 * @param string $group the group value appended to the $key
 *
 * @return string returns the cache key used for getting & setting
 */
function wp_cache_get_key($key, $group = '')
{
    return wp_object_cache()->buildKey($key, $group);
}


/**
 * Increment a numeric item's value.
 *
 * This is the same as wp_cache_increment, but kept for back compatibility. The original
 * WordPress caching backends use wp_cache_incr. I want both to work.
 *
 *
 * @see http://www.php.net/manual/en/memcached.increment.php
 *
 * @param string $key    the key under which to store the value
 * @param int    $offset the amount by which to increment the item's value
 * @param string $group  the group value appended to the $key
 *
 * @return int|bool returns item's new value on success or FALSE on failure
 */
function wp_cache_incr($key, $offset = 1, $group = '')
{
    return wp_object_cache()->incr($key, $offset, $group);
}


/**
 * Replaces a value in cache.
 *
 * This method is similar to "add"; however, is does not successfully set a value if
 * the object's key is not already set in cache.
 *
 *
 * @see http://www.php.net/manual/en/memcached.replace.php
 *
 * @param string $key        the key under which to store the value
 * @param mixed  $value      the value to store
 * @param string $group      the group value appended to the $key
 * @param int    $expiration the expiration time, defaults to 0
 *
 * @return bool returns TRUE on success or FALSE on failure
 */
function wp_cache_replace($key, $value, $group = '', $expiration = 0)
{
    return wp_object_cache()->replace($key, $value, $group, $expiration);
}


/**
 * Sets a value in cache.
 *
 * The value is set whether or not this key already exists in memcached.
 *
 *
 * @see http://www.php.net/manual/en/memcached.set.php
 *
 * @param string $key        the key under which to store the value
 * @param mixed  $value      the value to store
 * @param string $group      the group value appended to the $key
 * @param int    $expiration the expiration time, defaults to 0
 *
 * @return bool returns TRUE on success or FALSE on failure
 */
function wp_cache_set($key, $value, $group = '', $expiration = 0)
{
    return wp_object_cache()->set($key, $value, $group, $expiration);
}

/**
 * Switch blog prefix, which changes the cache that is accessed.
 *
 *
 * @param int $blog_id blog to switch to
 */
function wp_cache_switch_to_blog($blog_id)
{
    wp_object_cache()->switchToBlog($blog_id);
}

/**
 * Adds a group or set of groups to the list of non-persistent groups.
 *
 *
 * @param string|array $groups a group or an array of groups to add
 */
function wp_cache_add_global_groups($groups)
{
    wp_object_cache()->addGlobalGroups($groups);
}

/**
 * Adds a group or set of groups to the list of non-persistent groups.
 *
 *
 * @param string|array $groups a group or an array of groups to add
 */
function wp_cache_add_non_persistent_groups($groups)
{
    wp_object_cache()->addNonPersistentGroups($groups);
}

/**
 * Sets up Object Cache Global and assigns it.
 */
function wp_cache_init()
{
    wp_object_cache_init();
}

/**
 * Returns the Object Cache Global.
 *
 * @return ObjectCache
 */
function wp_object_cache()
{
    if (!isset($GLOBALS['wp_object_cache'])) {
        wp_object_cache_init();
    }

    return $GLOBALS['wp_object_cache'];
}

/**
 * Sets up Output Cache Global and assigns it.
 */
function wp_object_cache_init()
{
    $GLOBALS['wp_object_cache'] = new ObjectCache();
}

/**
 * Sets up Output Cache Global and assigns it.
 */
function wp_output_cache_init()
{
    $GLOBALS['wp_output_cache'] = new OutputCache();
}

/**
 * Returns the Output Cache Global.
 *
 *
 * @return OutputCache
 */
function wp_output_cache()
{
    if (!isset($GLOBALS['wp_output_cache'])) {
        wp_output_cache_init();
    }

    return $GLOBALS['wp_output_cache'];
}

/**
 * Should we skip the output cache?
 *
 *
 * @return bool
 */
function wp_skip_output_cache()
{
    // Bail if caching not turned on
    if (!defined('WP_CACHE') || (true !== WP_CACHE)) {
        return true;
    }

    // Bail if no content directory
    if (!defined('WP_CONTENT_DIR')) {
        return true;
    }

    // Never cache interactive scripts or API endpoints.
    if (in_array(basename($_SERVER['SCRIPT_FILENAME']), [
        'wp-app.php',
        'wp-cron.php',
        'ms-files.php',
        'xmlrpc.php',
    ])) {
        return true;
    }

    // Never cache JavaScript generators
    if (strstr($_SERVER['SCRIPT_FILENAME'], 'wp-includes/js')) {
        return true;
    }

    // Never cache when POST data is present.
    if (!empty($GLOBALS['HTTP_RAW_POST_DATA']) || !empty($_POST)) {
        return true;
    }

    return false;
}

/**
 * Cancel Output-Cache.
 */
function wp_output_cache_cancel()
{
    wp_output_cache()->cancel = true;
}