<?php

/*
Plugin Name: WP Object Cache Drop-In
Plugin URI: https://github.com/Jazz-Man/wp-object-cache
Description: Redis, Memcached or Apcu backend for the WP Object Cache
Version: v2.1.1
Author: Vasyl Sokolyk
*/

use JazzMan\WPObjectCache\RedisAdapter;

function wp_cache_close(): bool
{
    return true;
}

/**
 * @param  string  $key
 * @param  mixed  $value
 * @param  string  $group
 * @param  int  $expiration
 *
 * @return bool
 */
function wp_cache_add(string $key, $value, string $group = 'default', int $expiration = 0): bool
{
    return wp_object_cache()->add($key, $value, $group, $expiration);
}

/**
 * @param  string  $key
 * @param  int  $offset
 * @param  string  $group
 * @return bool|int
 */
function wp_cache_decr(string $key, int $offset = 1, string $group = 'default')
{
    return wp_object_cache()->decrement($key, $offset, $group);
}

/**
 * @param  string  $key
 * @param  string  $group
 * @return bool|int
 */
function wp_cache_delete(string $key, string $group = 'default')
{
    return wp_object_cache()->delete($key, $group);
}

function wp_cache_flush(): bool
{
    return wp_object_cache()->flush();
}

/**
 * @param  string  $key
 * @param  string  $group
 * @param  bool  $force
 * @param  bool|null  $found
 * @return bool|mixed
 */
function wp_cache_get(string $key, string $group = 'default', bool $force = false, bool &$found = null)
{
    return wp_object_cache()->get($key, $group, $force, $found);
}

/**
 * @param  string  $key
 * @param  int  $offset
 * @param  string  $group
 * @return bool|int
 */
function wp_cache_incr(string $key, int $offset = 1, string $group = 'default')
{
    return wp_object_cache()->increment($key, $offset, $group);
}

/**
 * @param  string  $key
 * @param  mixed  $value
 * @param  string  $group
 * @param  int  $expiration
 * @return bool
 */
function wp_cache_replace(string $key, $value = null, string $group = 'default', int $expiration = 0): bool
{
    return wp_object_cache()->replace($key, $value, $group, $expiration);
}

/**
 * @param  string  $key
 * @param  mixed  $value
 * @param  string  $group
 * @param  int  $expiration
 * @return bool
 */
function wp_cache_set(string $key, $value = null, string $group = 'default', int $expiration = 0): bool
{
    return wp_object_cache()->set($key, $value, $group, $expiration);
}

/**
 * Switch blog prefix, which changes the cache that is accessed.
 * @param  int  $blogId
 * @return bool
 */
function wp_cache_switch_to_blog(int $blogId): bool
{
    return wp_object_cache()->switchToBlog($blogId);
}

/**
 * Adds a group or set of groups to the list of non-persistent groups.
 *
 * @param string|string[] $groups a group or an array of groups to add
 */
function wp_cache_add_global_groups($groups): void
{
    wp_object_cache()->addGlobalGroups($groups);
}

/**
 * Adds a group or set of groups to the list of non-persistent groups.
 *
 * @param string|string[] $groups a group or an array of groups to add
 */
function wp_cache_add_non_persistent_groups($groups): void
{
    wp_object_cache()->addNonPersistentGroups($groups);
}

/**
 * Sets up Object Cache Global and assigns it.
 */
function wp_cache_init(): void
{
    global $wp_object_cache;

    if (!($wp_object_cache instanceof RedisAdapter)) {
        $wp_object_cache = new RedisAdapter();
    }
}

function wp_object_cache_get_stats(): void
{
    wp_object_cache()->stats();
}

/**
 * @return \JazzMan\WPObjectCache\RedisAdapter
 */
function wp_object_cache(): RedisAdapter
{
    global $wp_object_cache;

    return $wp_object_cache;
}

/**
 * @return \Redis
 */
function wp_object_cache_instance(): Redis
{
    return wp_object_cache()->redisInstance();
}

/**
 * @return bool
 */
function wp_object_redis_status(): bool
{
    return wp_object_cache()->redisStatus();
}
