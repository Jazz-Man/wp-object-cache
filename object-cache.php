<?php

use JazzMan\WPObjectCache\DriverAdapter;
use JazzMan\WPObjectCache\OutputCache;

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
function wp_cache_decr($key, $offset = 1, string $group = 'default')
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
function wp_cache_incr($key, $offset = 1, string $group = 'default')
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
 * @param string $key
 * @param string $group
 *
 * @return bool
 */
function wp_cache_has(string $key, string $group = 'default'){
   return wp_object_cache()->has($key,$group);
}


/**
 * @param array  $keys
 * @param string $group
 *
 * @return array
 */
function wp_cache_sanitize_keys(array $keys, string $group = 'default'){
   return wp_object_cache()->sanitizeKeys($keys,$group);
}

/**
 * @param string $key
 * @param string $group
 *
 * @return string
 */
function wp_cache_sanitize_key(string $key, string $group = 'default'){
    return wp_object_cache()->sanitizeKey($key,$group);
}

/**
 * Sets up Object Cache Global and assigns it.
 */
function wp_cache_init()
{
    wp_object_cache_init();
}

/**
 * @return DriverAdapter
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
    $GLOBALS['wp_object_cache'] = new DriverAdapter();
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
