<?php

declare(strict_types=1);

/**
 * Plugin Name: WP Object Cache Drop-In
 * Plugin URI: https://github.com/Jazz-Man/wp-object-cache
 * Description: Redis, Memcached or Apcu backend for the WP Object Cache
 * Version: v2.1.2
 * Author: Vasyl Sokolyk
 */

use JazzMan\WPObjectCache\RedisAdapter;

function wp_cache_close(): bool {
    return true;
}

function wp_cache_add(string $key, mixed $value, string $group = 'default', int $expiration = 0): bool {
    return wp_object_cache()->add($key, $value, $group, $expiration);
}

/**
 * @return bool|int
 */
function wp_cache_decr(string $key, int|float $offset = 1, string $group = 'default'): bool|int|\Redis {
    return wp_object_cache()->decrement($key, $offset, $group);
}

function wp_cache_delete(string $key, string $group = 'default'): bool|int {
    return wp_object_cache()->delete($key, $group);
}

function wp_cache_flush(): bool {
    return wp_object_cache()->flush();
}

/**
 * @return bool|mixed
 */
function wp_cache_get(string $key, string $group = 'default', bool $force = false, bool &$found = null) {
    return wp_object_cache()->get($key, $group, $force, $found);
}


function wp_cache_incr(string $key, int|float $offset = 1, string $group = 'default'): bool|int|Redis {
    return wp_object_cache()->increment($key, $offset, $group);
}

function wp_cache_replace(string $key, mixed $value = null, string $group = 'default', int $expiration = 0): bool {
    return wp_object_cache()->replace($key, $value, $group, $expiration);
}

function wp_cache_set(string $key, mixed $value = null, string $group = 'default', int $expiration = 0): bool {
    return wp_object_cache()->set($key, $value, $group, $expiration);
}

/**
 * Switch blog prefix, which changes the cache that is accessed.
 *
 */
function wp_cache_switch_to_blog(int|string $blogId): bool {
    return wp_object_cache()->switchToBlog( $blogId);
}

/**
 * Adds a group or set of groups to the list of non-persistent groups.
 *
 * @param string|string[] $groups a group or an array of groups to add
 */
function wp_cache_add_global_groups( string|array $groups): void {
    wp_object_cache()->addGlobalGroups($groups);
}

/**
 * Adds a group or set of groups to the list of non-persistent groups.
 *
 * @param string|string[] $groups a group or an array of groups to add
 */
function wp_cache_add_non_persistent_groups(string|array $groups): void {
    wp_object_cache()->addNonPersistentGroups($groups);
}

/**
 * Sets up Object Cache Global and assigns it.
 */
function wp_cache_init(): void {
    global $wp_object_cache;

    if (!$wp_object_cache instanceof RedisAdapter) {
        $wp_object_cache = new RedisAdapter();
    }
}

function wp_object_cache_get_stats(): void {
    wp_object_cache()->stats();
}

function wp_object_cache(): RedisAdapter {
    global $wp_object_cache;

	/** @var RedisAdapter $wp_object_cache */

    return $wp_object_cache;
}

function wp_object_cache_instance(): Redis {
    return wp_object_cache()->redisInstance();
}

function wp_object_redis_status(): bool {
    return wp_object_cache()->redisStatus();
}
