<?php

declare( strict_types=1 );

use JazzMan\WPObjectCache\RedisAdapter;

/**
 * Plugin Name: WP Object Cache Drop-In
 * Plugin URI: https://github.com/Jazz-Man/wp-object-cache
 * Description: Redis, Memcached or Apcu backend for the WP Object Cache
 * Version: v2.1.2
 * Author: Vasyl Sokolyk.
 */
function wp_cache_close(): bool {
    return true;
}

function wp_cache_add( string $key, mixed $value, string $group = 'default', int $expiration = 0 ): bool|int|Redis|null {

    return wp_object_cache()->set( $key, $value, $group );
}

function wp_cache_add_multiple( array $data, string $group = 'default', int $expiration = 0 ): bool|Redis|null {
    return wp_object_cache()->set_multiple( $data, $group );
}

function wp_cache_decr( string $key, float|int $offset = 1, string $group = 'default' ): false|float|int|Redis|null {
    return wp_object_cache()->decrement( $key, $offset, $group );
}

function wp_cache_delete( string $key, string $group = 'default' ): bool|int|Redis|null {
    return wp_object_cache()->delete( $key, $group );
}

/**
 * @param string[] $keys
 */
function wp_cache_delete_multiple( array $keys, string $group = 'default' ): bool|int|Redis|null {

    return wp_object_cache()->delete( $keys, $group );
}

function wp_cache_flush(): bool|Redis|null {
    return wp_object_cache()->flush();
}

/**
 * @param string[]|string $group
 */
function wp_cache_flush_group( array|string $group ): false|int|Redis|null {

    return wp_object_cache()->flush_group( $group );
}

function wp_cache_get( string $key, string $group = 'default', bool $force = false, ?bool &$found = null ): mixed {
    return wp_object_cache()->get( $key, $group );
}

/**
 * @param string[] $keys
 */
function wp_cache_get_multiple( array $keys, string $group = 'default', bool $force = false, ?bool &$found = null ): mixed {

    return wp_object_cache()->get_multiple( $keys, $group );
}

function wp_cache_incr( string $key, float|int $offset = 1, string $group = 'default' ): false|float|int|Redis|null {

    return wp_object_cache()->increment( $key, $offset, $group );
}

function wp_cache_replace( string $key, mixed $value = null, string $group = 'default', int $expiration = 0 ): bool|int|Redis|null {
    return wp_object_cache()->set( $key, $value, $group );
}

function wp_cache_set( string $key, mixed $value = null, string $group = 'default', int $expiration = 0 ): bool|int|Redis|null {

    return wp_object_cache()->set( $key, $value, $group );
}

function wp_cache_set_multiple( array $data, string $group = 'default', int $expire = 0 ): bool|Redis|null {
    return wp_object_cache()->set_multiple( $data, $group );
}

/**
 * Switch blog prefix, which changes the cache that is accessed.
 */
function wp_cache_switch_to_blog( int|string $blogId ): bool {
    return wp_object_cache()->switch_to_blog( $blogId );
}

/**
 * Adds a group or set of groups to the list of non-persistent groups.
 *
 * @param string|string[] $groups a group or an array of groups to add
 */
function wp_cache_add_global_groups( array|string $groups ): void {
    wp_object_cache()->add_global_groups( $groups );
}

/**
 * Adds a group or set of groups to the list of non-persistent groups.
 *
 * @param string|string[] $groups a group or an array of groups to add
 */
function wp_cache_add_non_persistent_groups( array|string $groups ): void {
    wp_object_cache()->add_non_persistent_groups( $groups );
}

/**
 * Sets up Object Cache Global and assigns it.
 */
function wp_cache_init(): void {
    global $wp_object_cache;

    if ( ! $wp_object_cache instanceof RedisAdapter ) {
        $wp_object_cache = new RedisAdapter();
    }
}

function wp_object_cache_get_stats(): void {
    wp_object_cache()->stats();
}

function wp_object_cache(): RedisAdapter {
    global $wp_object_cache;
    wp_cache_init();

    /** @var RedisAdapter $wp_object_cache */

    return $wp_object_cache;
}

function wp_object_cache_instance(): ?Redis {
    return wp_object_cache()->get_redis();
}

function wp_object_redis_status(): bool {
    return wp_object_cache()->redis_status();
}

function wp_cache_supports( string $feature ): bool {
    return match ( $feature ) {
        'add_multiple',
        'set_multiple',
        'get_multiple',
        'delete_multiple',
        //        'flush_runtime',
        'flush_group' => true,
        default => false,
    };
}
