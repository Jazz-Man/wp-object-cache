<?php

use JazzMan\WPObjectCache\DriverAdapter;

/**
 * @return bool
 */
function wp_cache_close()
{
    return true;
}

/**
 * @param string|array           $key
 * @param                        $value
 * @param string                 $group
 * @param int|\DateInterval|null $expiration
 *
 * @return bool
 */
function wp_cache_add($key, $value, string $group = 'default', $expiration = 0)
{
    return wp_object_cache()->add($key, $value, $group, $expiration);
}

/**
 * @param string|array $key
 * @param int          $offset
 * @param string       $group
 *
 * @return bool
 */
function wp_cache_decr($key, int $offset = 1, string $group = 'default')
{
    return wp_object_cache()->decrement($key, $offset, $group);
}

/**
 * @param string|array $key
 * @param string       $group
 *
 * @return bool
 */
function wp_cache_delete($key, string $group = 'default')
{
    return wp_object_cache()->delete($key, $group);
}

/**
 * @param int $delay
 *
 * @return bool
 */
function wp_cache_flush($delay = 0)
{
    return wp_object_cache()->flush($delay);
}

/**
 * @param string|array $key
 * @param string       $group
 * @param bool         $force
 * @param bool|null    $found
 *
 * @return mixed
 */
function wp_cache_get($key, string $group = 'default', $force = false, &$found = null)
{
    return wp_object_cache()->get($key, $group, $force, $found);
}

/**
 * @param string|array $key
 * @param int          $offset
 * @param string       $group
 *
 * @return bool
 */
function wp_cache_incr($key, int $offset = 1, string $group = 'default')
{
    return wp_object_cache()->increment($key, $offset, $group);
}

/**
 * @param string|array           $key
 * @param mixed|null             $value
 * @param string                 $group
 * @param int|\DateInterval|null $expiration
 *
 * @return bool
 */
function wp_cache_replace($key, $value = null, string $group = 'default', $expiration = 0)
{
    return wp_object_cache()->replace($key, $value, $group, $expiration);
}

/**
 * @param string|array           $key
 * @param mixed|null             $value
 * @param string                 $group
 * @param int|\DateInterval|null $expiration
 *
 * @return bool
 */
function wp_cache_set($key, $value = null, string $group = 'default', $expiration = 0)
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
    wp_object_cache()->setGlobalGroups($groups);
}

/**
 * Adds a group or set of groups to the list of non-persistent groups.
 *
 *
 * @param string|array $groups a group or an array of groups to add
 */
function wp_cache_add_non_persistent_groups($groups)
{
    wp_object_cache()->setIgnoredGroups($groups);
}

/**
 * Sets up Object Cache Global and assigns it.
 */
function wp_cache_init()
{
    global $wp_object_cache;

    if (! ($wp_object_cache instanceof DriverAdapter)) {
        $wp_object_cache = new DriverAdapter;
    }
}


/**
 * @return DriverAdapter
 */
function wp_object_cache()
{
    global $wp_object_cache;

    return $wp_object_cache;
}
