<?php

use JazzMan\WPMemcached\WPObjectCache;

/**
 * Adds a value to cache.
 *
 * If the specified key already exists, the value is not stored and the function
 * returns false.
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
    /* @var WPObjectCache $wp_object_cache */
    global $wp_object_cache;

    return $wp_object_cache->add($key, $value, $group, $expiration);
}

/**
 * Adds a value to cache on a specific server.
 *
 * Using a server_key value, the object can be stored on a specified server as opposed
 * to a random server in the stack. Note that this method will add the key/value to the
 * _cache object as part of the runtime cache. It will add it to an array for the
 * specified server_key.
 *
 * @see http://www.php.net/manual/en/memcached.addbykey.php
 *
 * @param string $server_key the key identifying the server to store the value on
 * @param string $key        the key under which to store the value
 * @param mixed  $value      the value to store
 * @param string $group      the group value appended to the $key
 * @param int    $expiration the expiration time, defaults to 0
 *
 * @return bool returns TRUE on success or FALSE on failure
 */
function wp_cache_add_by_key($server_key, $key, $value, $group = '', $expiration = 0)
{
    /* @var WPObjectCache $wp_object_cache */
    global $wp_object_cache;

    return $wp_object_cache->addByKey($server_key, $key, $value, $group, $expiration);
}

/**
 * Add a single server to the list of Memcached servers.
 *
 * @see http://www.php.net/manual/en/memcached.addserver.php
 *
 * @param string $host   the hostname of the memcache server
 * @param int    $port   the port on which memcache is running
 * @param int    $weight the weight of the server relative to the total weight of all the servers in the pool
 *
 * @return bool returns TRUE on success or FALSE on failure
 */
function wp_cache_add_server($host, $port, $weight = 0)
{
    /* @var WPObjectCache $wp_object_cache */
    global $wp_object_cache;

    return $wp_object_cache->addServer($host, $port, $weight);
}

/**
 * Adds an array of servers to the pool.
 *
 * Each individual server in the array must include a domain and port, with an optional
 * weight value: $servers = array( array( '127.0.0.1', 11211, 0 ) );
 *
 * @see http://www.php.net/manual/en/memcached.addservers.php
 *
 * @param array $servers array of server to register
 *
 * @return bool true on success; false on failure
 */
function wp_cache_add_servers($servers)
{
    /* @var WPObjectCache $wp_object_cache */
    global $wp_object_cache;

    return $wp_object_cache->addServers($servers);
}

/**
 * Append data to an existing item.
 *
 * This method should throw an error if it is used with compressed data. This
 * is an expected behavior. Memcached casts the value to be appended to the initial value to the
 * type of the initial value. Be careful as this leads to unexpected behavior at times. Due to
 * how memcached treats types, the behavior has been mimicked in the internal cache to produce
 * similar results and improve consistency. It is recommend that appends only occur with data of
 * the same type.
 *
 * @see http://www.php.net/manual/en/memcached.append.php
 *
 * @param string $key   the key under which to store the value
 * @param mixed  $value Must be string as appending mixed values is not well-defined
 * @param string $group the group value appended to the $key
 *
 * @return bool returns TRUE on success or FALSE on failure
 */
function wp_cache_append($key, $value, $group = '')
{
    /* @var WPObjectCache $wp_object_cache */
    global $wp_object_cache;

    return $wp_object_cache->append($key, $value, $group);
}

/**
 * Append data to an existing item by server key.
 *
 * This method should throw an error if it is used with compressed data. This
 * is an expected behavior. Memcached casts the value to be appended to the initial value to the
 * type of the initial value. Be careful as this leads to unexpected behavior at times. Due to
 * how memcached treats types, the behavior has been mimicked in the internal cache to produce
 * similar results and improve consistency. It is recommend that appends only occur with data of
 * the same type.
 *
 * @see http://www.php.net/manual/en/memcached.appendbykey.php
 *
 * @param string $server_key the key identifying the server to store the value on
 * @param string $key        the key under which to store the value
 * @param mixed  $value      Must be string as appending mixed values is not well-defined
 * @param string $group      the group value appended to the $key
 *
 * @return bool returns TRUE on success or FALSE on failure
 */
function wp_cache_append_by_key($server_key, $key, $value, $group = '')
{
    /* @var WPObjectCache $wp_object_cache */
    global $wp_object_cache;

    return $wp_object_cache->appendByKey($server_key, $key, $value, $group);
}

/**
 * Performs a "check and set" to store data.
 *
 * The set will be successful only if the no other request has updated the value since it was fetched by
 * this request.
 *
 * @see http://www.php.net/manual/en/memcached.cas.php
 *
 * @param float  $cas_token  Unique value associated with the existing item. Generated by memcached.
 * @param string $key        the key under which to store the value
 * @param mixed  $value      the value to store
 * @param string $group      the group value appended to the $key
 * @param int    $expiration the expiration time, defaults to 0
 *
 * @return bool returns TRUE on success or FALSE on failure
 */
function wp_cache_cas($cas_token, $key, $value, $group = '', $expiration = 0)
{
    /* @var WPObjectCache $wp_object_cache */
    global $wp_object_cache;

    return $wp_object_cache->cas($cas_token, $key, $value, $group, $expiration);
}

/**
 * Performs a "check and set" to store data with a server key.
 *
 * The set will be successful only if the no other request has updated the value since it was fetched by
 * this request.
 *
 * @see http://www.php.net/manual/en/memcached.casbykey.php
 *
 * @param string $server_key the key identifying the server to store the value on
 * @param float  $cas_token  Unique value associated with the existing item. Generated by memcached.
 * @param string $key        the key under which to store the value
 * @param mixed  $value      the value to store
 * @param string $group      the group value appended to the $key
 * @param int    $expiration the expiration time, defaults to 0
 *
 * @return bool returns TRUE on success or FALSE on failure
 */
function wp_cache_cas_by_key($cas_token, $server_key, $key, $value, $group = '', $expiration = 0)
{
    /* @var WPObjectCache $wp_object_cache */
    global $wp_object_cache;

    return $wp_object_cache->casByKey($cas_token, $server_key, $key, $value, $group, $expiration);
}

/**
 * Closes the cache.
 *
 * This function has ceased to do anything since WordPress 2.5. The
 * functionality was removed along with the rest of the persistent cache. This
 * does not mean that plugins can't implement this function when they need to
 * make sure that the cache is cleaned up after WordPress no longer needs it.
 *
 * @since 2.0.0
 *
 * @return bool Always returns True
 */
function wp_cache_close()
{
    return true;
}

/**
 * Decrement a numeric item's value.
 *
 * @see http://www.php.net/manual/en/memcached.decrement.php
 *
 * @param string $key    the key under which to store the value
 * @param int    $offset the amount by which to decrement the item's value
 * @param string $group  the group value appended to the $key
 *
 * @return int|bool returns item's new value on success or FALSE on failure
 */
function wp_cache_decrement($key, $offset = 1, $group = '')
{
    /* @var WPObjectCache $wp_object_cache */
    global $wp_object_cache;

    return $wp_object_cache->decrement($key, $offset, $group);
}

/**
 * Decrement a numeric item's value.
 *
 * Same as wp_cache_decrement. Original WordPress caching backends use wp_cache_decr. I
 * want both spellings to work.
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
    return wp_cache_decrement($key, $offset, $group);
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
function wp_cache_delete($key, $group = '', $time = 0)
{
    /* @var WPObjectCache $wp_object_cache */
    global $wp_object_cache;

    return $wp_object_cache->delete($key, $group, $time);
}

/**
 * Remove the item from the cache by server key.
 *
 * Remove an item from memcached with identified by $key after $time seconds. The
 * $time parameter allows an object to be queued for deletion without immediately
 * deleting. Between the time that it is queued and the time it's deleted, add,
 * replace, and get will fail, but set will succeed.
 *
 * @see http://www.php.net/manual/en/memcached.deletebykey.php
 *
 * @param string $server_key the key identifying the server to store the value on
 * @param string $key        the key under which to store the value
 * @param string $group      the group value appended to the $key
 * @param int    $time       the amount of time the server will wait to delete the item in seconds
 *
 * @return bool returns TRUE on success or FALSE on failure
 */
function wp_cache_delete_by_key($server_key, $key, $group = '', $time = 0)
{
    /* @var WPObjectCache $wp_object_cache */
    global $wp_object_cache;

    return $wp_object_cache->deleteByKey($server_key, $key, $group, $time);
}

/**
 * Fetch the next result.
 *
 * @see http://www.php.net/manual/en/memcached.fetch.php
 *
 * @return array|bool returns the next result or FALSE otherwise
 */
function wp_cache_fetch()
{
    /* @var WPObjectCache $wp_object_cache */
    global $wp_object_cache;

    return $wp_object_cache->fetch();
}

/**
 * Fetch all remaining results from the last request.
 *
 * @see http://www.php.net/manual/en/memcached.fetchall.php
 *
 * @return array|bool returns the results or FALSE on failure
 */
function wp_cache_fetch_all()
{
    /* @var WPObjectCache $wp_object_cache */
    global $wp_object_cache;

    return $wp_object_cache->fetchAll();
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
function wp_cache_flush($delay = 0)
{
    /* @var WPObjectCache $wp_object_cache */
    global $wp_object_cache;

    return $wp_object_cache->flush($delay);
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
 * @see http://www.php.net/manual/en/memcached.get.php
 *
 * @param string      $key       the key under which to store the value
 * @param string      $group     the group value appended to the $key
 * @param bool        $force     whether or not to force a cache invalidation
 * @param bool|null   $found     variable passed by reference to determine if the value was found or not
 * @param string|null $cache_cb  read-through caching callback
 * @param float|null  $cas_token the variable to store the CAS token in
 *
 * @return bool|mixed cached object value
 */
function wp_cache_get($key, $group = '', $force = false, &$found = null, $cache_cb = null, &$cas_token = null)
{
    /* @var WPObjectCache $wp_object_cache */
    global $wp_object_cache;

    if (func_num_args() > 4) {
        return $wp_object_cache->get($key, $group, $force, $found, '', false, $cache_cb, $cas_token);
    }

    return $wp_object_cache->get($key, $group, $force, $found);
}

/**
 * Retrieve object from cache from specified server.
 *
 * Gets an object from cache based on $key, $group and $server_key. In order to fully support the $cache_cb and
 * $cas_token parameters, the runtime cache is ignored by this function if either of those values are set. If either of
 * those values are set, the request is made directly to the memcached server for proper handling of the callback
 * and/or token.
 *
 * @see http://www.php.net/manual/en/memcached.getbykey.php
 *
 * @param string      $server_key the key identifying the server to store the value on
 * @param string      $key        the key under which to store the value
 * @param string      $group      the group value appended to the $key
 * @param bool        $force      whether or not to force a cache invalidation
 * @param bool|null   $found      variable passed by reference to determine if the value was found or not
 * @param string|null $cache_cb   read-through caching callback
 * @param float|null  $cas_token  the variable to store the CAS token in
 *
 * @return bool|mixed cached object value
 */
function wp_cache_get_by_key($server_key, $key, $group = '', $force = false, &$found = null, $cache_cb = null, &$cas_token = null) {
    /* @var WPObjectCache $wp_object_cache */
    global $wp_object_cache;

    if (func_num_args() > 5) {
        return $wp_object_cache->getByKey($server_key, $key, $group, $force, $found, $cache_cb, $cas_token);
    }

    return $wp_object_cache->getByKey($server_key, $key, $group, $force, $found);
}

/**
 * Request multiple keys without blocking.
 *
 * @see http://www.php.net/manual/en/memcached.getdelayed.php
 *
 * @param string|array $keys     array or string of key(s) to request
 * @param string|array $groups   Array or string of group(s) for the key(s). See buildKeys for more on how these are
 *                               handled.
 * @param bool         $with_cas whether to request CAS token values also
 * @param null         $value_cb the result callback or NULL
 *
 * @return bool returns TRUE on success or FALSE on failure
 */
function wp_cache_get_delayed($keys, $groups = '', $with_cas = false, $value_cb = null)
{
    /* @var WPObjectCache $wp_object_cache */
    global $wp_object_cache;

    return $wp_object_cache->getDelayed($keys, $groups, $with_cas, $value_cb);
}

/**
 * Request multiple keys without blocking from a specified server.
 *
 * @see http://www.php.net/manual/en/memcached.getdelayed.php
 *
 * @param string       $server_key the key identifying the server to store the value on
 * @param string|array $keys       array or string of key(s) to request
 * @param string|array $groups     Array or string of group(s) for the key(s). See buildKeys for more on how these are
 *                                 handled.
 * @param bool         $with_cas   whether to request CAS token values also
 * @param null         $value_cb   the result callback or NULL
 *
 * @return bool returns TRUE on success or FALSE on failure
 */
function wp_cache_get_delayed_by_key($server_key, $keys, $groups = '', $with_cas = false, $value_cb = null)
{
    /* @var WPObjectCache $wp_object_cache */
    global $wp_object_cache;

    return $wp_object_cache->getDelayedByKey($server_key, $keys, $groups, $with_cas, $value_cb);
}

/**
 * Gets multiple values from memcached in one request.
 *
 * See the buildKeys method definition to understand the $keys/$groups parameters.
 *
 * @see http://www.php.net/manual/en/memcached.getmulti.php
 *
 * @param array        $keys       array of keys to retrieve
 * @param string|array $groups     If string, used for all keys. If arrays, corresponds with the $keys array.
 * @param array|null   $cas_tokens the variable to store the CAS tokens for the found items
 * @param int          $flags      the flags for the get operation
 *
 * @return bool|array returns the array of found items or FALSE on failure
 */
function wp_cache_get_multi($keys, $groups = '', &$cas_tokens = null, $flags = null)
{
    /* @var WPObjectCache $wp_object_cache */
    global $wp_object_cache;

    if (func_num_args() > 2) {
        return $wp_object_cache->getMulti($keys, $groups, '', $cas_tokens, $flags);
    }

    return $wp_object_cache->getMulti($keys, $groups);
}

/**
 * Gets multiple values from memcached in one request by specified server key.
 *
 * See the buildKeys method definition to understand the $keys/$groups parameters.
 *
 * @see http://www.php.net/manual/en/memcached.getmultibykey.php
 *
 * @param string       $server_key the key identifying the server to store the value on
 * @param array        $keys       array of keys to retrieve
 * @param string|array $groups     If string, used for all keys. If arrays, corresponds with the $keys array.
 * @param array|null   $cas_tokens the variable to store the CAS tokens for the found items
 * @param int          $flags      the flags for the get operation
 *
 * @return bool|array returns the array of found items or FALSE on failure
 */
function wp_cache_get_multi_by_key($server_key, $keys, $groups = '', &$cas_tokens = null, $flags = null)
{
    /* @var WPObjectCache $wp_object_cache */
    global $wp_object_cache;

    if (func_num_args() > 3) {
        return $wp_object_cache->getMultiByKey($server_key, $keys, $groups, $cas_tokens, $flags);
    }

    return $wp_object_cache->getMultiByKey($server_key, $keys, $groups);
}

/**
 * Retrieve a Memcached option value.
 *
 * @see http://www.php.net/manual/en/memcached.getoption.php
 *
 * @param int $option one of the Memcached::OPT_* constants
 *
 * @return mixed returns the value of the requested option, or FALSE on error
 */
function wp_cache_get_option($option)
{
    /* @var WPObjectCache $wp_object_cache */
    global $wp_object_cache;

    return $wp_object_cache->getOption($option);
}

/**
 * Return the result code of the last option.
 *
 * @see http://www.php.net/manual/en/memcached.getresultcode.php
 *
 * @return int result code of the last Memcached operation
 */
function wp_cache_get_result_code()
{
    /* @var WPObjectCache $wp_object_cache */
    global $wp_object_cache;

    return $wp_object_cache->getResultCode();
}

/**
 * Return the message describing the result of the last operation.
 *
 * @see http://www.php.net/manual/en/memcached.getresultmessage.php
 *
 * @return string message describing the result of the last Memcached operation
 */
function wp_cache_get_result_message()
{
    /* @var WPObjectCache $wp_object_cache */
    global $wp_object_cache;

    return $wp_object_cache->getResultMessage();
}

/**
 * Get server information by key.
 *
 * @see http://www.php.net/manual/en/memcached.getserverbykey.php
 *
 * @param string $server_key the key identifying the server to store the value on
 *
 * @return array array with host, post, and weight on success, FALSE on failure
 */
function wp_cache_get_server_by_key($server_key)
{
    /* @var WPObjectCache $wp_object_cache */
    global $wp_object_cache;

    return $wp_object_cache->getServerByKey($server_key);
}

/**
 * Get the list of servers in the pool.
 *
 * @see http://www.php.net/manual/en/memcached.getserverlist.php
 *
 * @return array the list of all servers in the server pool
 */
function wp_cache_get_server_list()
{
    /* @var WPObjectCache $wp_object_cache */
    global $wp_object_cache;

    return $wp_object_cache->getServerList();
}

/**
 * Get server pool statistics.
 *
 * @see http://www.php.net/manual/en/memcached.getstats.php
 *
 * @return array array of server statistics, one entry per server
 */
function wp_cache_get_stats()
{
    /* @var WPObjectCache $wp_object_cache */
    global $wp_object_cache;

    return $wp_object_cache->getStats();
}

/**
 * Get server pool memcached version information.
 *
 * @see http://www.php.net/manual/en/memcached.getversion.php
 *
 * @return array array of server versions, one entry per server
 */
function wp_cache_get_version()
{
    /* @var WPObjectCache $wp_object_cache */
    global $wp_object_cache;

    return $wp_object_cache->getVersion();
}

/**
 * Increment a numeric item's value.
 *
 * @see http://www.php.net/manual/en/memcached.increment.php
 *
 * @param string $key    the key under which to store the value
 * @param int    $offset the amount by which to increment the item's value
 * @param string $group  the group value appended to the $key
 *
 * @return int|bool returns item's new value on success or FALSE on failure
 */
function wp_cache_increment($key, $offset = 1, $group = '')
{
    /* @var WPObjectCache $wp_object_cache */
    global $wp_object_cache;

    return $wp_object_cache->increment($key, $offset, $group);
}

/**
 * Increment a numeric item's value.
 *
 * This is the same as wp_cache_increment, but kept for back compatibility. The original
 * WordPress caching backends use wp_cache_incr. I want both to work.
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
    return wp_cache_increment($key, $offset, $group);
}

/**
 * Prepend data to an existing item.
 *
 * This method should throw an error if it is used with compressed data. This is an expected behavior.
 * Memcached casts the value to be prepended to the initial value to the type of the initial value. Be
 * careful as this leads to unexpected behavior at times. For instance, prepending (float) 45.23 to
 * (int) 23 will result in 45, because the value is first combined (45.2323) then cast to "integer"
 * (the original value), which will be (int) 45. Due to how memcached treats types, the behavior has been
 * mimicked in the internal cache to produce similar results and improve consistency. It is recommend
 * that prepends only occur with data of the same type.
 *
 * @see http://www.php.net/manual/en/memcached.prepend.php
 *
 * @param string $key   the key under which to store the value
 * @param string $value must be string as prepending mixed values is not well-defined
 * @param string $group the group value prepended to the $key
 *
 * @return bool returns TRUE on success or FALSE on failure
 */
function wp_cache_prepend($key, $value, $group = '')
{
    /* @var WPObjectCache $wp_object_cache */
    global $wp_object_cache;

    return $wp_object_cache->prepend($key, $value, $group);
}

/**
 * Append data to an existing item by server key.
 *
 * This method should throw an error if it is used with compressed data. This is an expected behavior.
 * Memcached casts the value to be prepended to the initial value to the type of the initial value. Be
 * careful as this leads to unexpected behavior at times. For instance, prepending (float) 45.23 to
 * (int) 23 will result in 45, because the value is first combined (45.2323) then cast to "integer"
 * (the original value), which will be (int) 45. Due to how memcached treats types, the behavior has been
 * mimicked in the internal cache to produce similar results and improve consistency. It is recommend
 * that prepends only occur with data of the same type.
 *
 * @see http://www.php.net/manual/en/memcached.prependbykey.php
 *
 * @param string $server_key the key identifying the server to store the value on
 * @param string $key        the key under which to store the value
 * @param string $value      must be string as prepending mixed values is not well-defined
 * @param string $group      the group value prepended to the $key
 *
 * @return bool returns TRUE on success or FALSE on failure
 */
function wp_cache_prepend_by_key($server_key, $key, $value, $group = '')
{
    /* @var WPObjectCache $wp_object_cache */
    global $wp_object_cache;

    return $wp_object_cache->prependByKey($server_key, $key, $value, $group);
}

/**
 * Replaces a value in cache.
 *
 * This method is similar to "add"; however, is does not successfully set a value if
 * the object's key is not already set in cache.
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
    /* @var WPObjectCache $wp_object_cache */
    global $wp_object_cache;

    return $wp_object_cache->replace($key, $value, $group, $expiration);
}

/**
 * Replaces a value in cache on a specific server.
 *
 * This method is similar to "addByKey"; however, is does not successfully set a value if
 * the object's key is not already set in cache.
 *
 * @see http://www.php.net/manual/en/memcached.addbykey.php
 *
 * @param string $server_key the key identifying the server to store the value on
 * @param string $key        the key under which to store the value
 * @param mixed  $value      the value to store
 * @param string $group      the group value appended to the $key
 * @param int    $expiration the expiration time, defaults to 0
 *
 * @return bool returns TRUE on success or FALSE on failure
 */
function wp_cache_replace_by_key($server_key, $key, $value, $group = '', $expiration = 0)
{
    /* @var WPObjectCache $wp_object_cache */
    global $wp_object_cache;

    return $wp_object_cache->replaceByKey($server_key, $key, $value, $group, $expiration);
}

/**
 * Sets a value in cache.
 *
 * The value is set whether or not this key already exists in memcached.
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
    /* @var WPObjectCache $wp_object_cache */
    global $wp_object_cache;

    return $wp_object_cache->set($key, $value, $group, $expiration);
}

/**
 * Sets a value in cache.
 *
 * The value is set whether or not this key already exists in memcached.
 *
 * @see http://www.php.net/manual/en/memcached.set.php
 *
 * @param string $server_key the key identifying the server to store the value on
 * @param string $key        the key under which to store the value
 * @param mixed  $value      the value to store
 * @param string $group      the group value appended to the $key
 * @param int    $expiration the expiration time, defaults to 0
 *
 * @return bool returns TRUE on success or FALSE on failure
 */
function wp_cache_set_by_key($server_key, $key, $value, $group = '', $expiration = 0)
{
    /* @var WPObjectCache $wp_object_cache */
    global $wp_object_cache;

    return $wp_object_cache->setByKey($server_key, $key, $value, $group, $expiration);
}

/**
 * Set multiple values to cache at once.
 *
 * By sending an array of $items to this function, all values are saved at once to
 * memcached, reducing the need for multiple requests to memcached. The $items array
 * keys and values are what are stored to memcached. The keys in the $items array
 * are merged with the $groups array/string value via buildKeys to determine the
 * final key for the object.
 *
 * @param array        $items      an array of key/value pairs to store on the server
 * @param string|array $groups     group(s) to merge with key(s) in $items
 * @param int          $expiration the expiration time, defaults to 0
 *
 * @return bool returns TRUE on success or FALSE on failure
 */
function wp_cache_set_multi($items, $groups = '', $expiration = 0)
{
    /* @var WPObjectCache $wp_object_cache */
    global $wp_object_cache;

    return $wp_object_cache->setMulti($items, $groups, $expiration);
}

/**
 * Set multiple values to cache at once on specified server.
 *
 * By sending an array of $items to this function, all values are saved at once to
 * memcached, reducing the need for multiple requests to memcached. The $items array
 * keys and values are what are stored to memcached. The keys in the $items array
 * are merged with the $groups array/string value via buildKeys to determine the
 * final key for the object.
 *
 * @param string       $server_key the key identifying the server to store the value on
 * @param array        $items      an array of key/value pairs to store on the server
 * @param string|array $groups     group(s) to merge with key(s) in $items
 * @param int          $expiration the expiration time, defaults to 0
 *
 * @return bool returns TRUE on success or FALSE on failure
 */
function wp_cache_set_multi_by_key($server_key, $items, $groups = 'default', $expiration = 0)
{
    /* @var WPObjectCache $wp_object_cache */
    global $wp_object_cache;

    return $wp_object_cache->setMultiByKey($server_key, $items, $groups, $expiration);
}

/**
 * Set a Memcached option.
 *
 * @see http://www.php.net/manual/en/memcached.setoption.php
 *
 * @param int   $option option name
 * @param mixed $value  option value
 *
 * @return bool returns TRUE on success or FALSE on failure
 */
function wp_cache_set_option($option, $value)
{
    /* @var WPObjectCache $wp_object_cache */
    global $wp_object_cache;

    return $wp_object_cache->setOption($option, $value);
}

/**
 * Switch blog prefix, which changes the cache that is accessed.
 *
 * @param int $blog_id blog to switch to
 */
function wp_cache_switch_to_blog($blog_id)
{
    /* @var WPObjectCache $wp_object_cache */
    global $wp_object_cache;

    return $wp_object_cache->switch_to_blog($blog_id);
}

/**
 * Sets up Object Cache Global and assigns it.
 */
function wp_cache_init()
{
    /* @var WPObjectCache $wp_object_cache */
    global $wp_object_cache;
    $wp_object_cache = new WPObjectCache();
}

/**
 * Adds a group or set of groups to the list of non-persistent groups.
 *
 * @param string|array $groups a group or an array of groups to add
 */
function wp_cache_add_global_groups($groups)
{
    /* @var WPObjectCache $wp_object_cache */
    global $wp_object_cache;
    $wp_object_cache->add_global_groups($groups);
}

/**
 * Adds a group or set of groups to the list of non-Memcached groups.
 *
 * @param string|array $groups a group or an array of groups to add
 */
function wp_cache_add_non_persistent_groups($groups)
{
    /* @var WPObjectCache $wp_object_cache */
    global $wp_object_cache;
    $wp_object_cache->add_non_persistent_groups($groups);
}
