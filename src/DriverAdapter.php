<?php

namespace JazzMan\WPObjectCache;

use Phpfastcache\CacheManager;
use Phpfastcache\Core\Item\ExtendedCacheItemInterface;
use Phpfastcache\Core\Pool\ExtendedCacheItemPoolInterface;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Psr\Cache\InvalidArgumentException;

/**
 * Class DriverAdapter.
 */
class DriverAdapter
{
    /**
     * @var ExtendedCacheItemPoolInterface
     */
    protected $internalCacheInstance;

    /**
     * DriverAdapter constructor.
     *
     * @param      $driver
     * @param null $config
     *
     * @throws \Phpfastcache\Exceptions\PhpfastcacheDriverCheckException
     * @throws \Phpfastcache\Exceptions\PhpfastcacheDriverException
     * @throws \Phpfastcache\Exceptions\PhpfastcacheDriverNotFoundException
     * @throws \Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException
     * @throws \Phpfastcache\Exceptions\PhpfastcacheInvalidConfigurationException
     * @throws \Phpfastcache\Exceptions\PhpfastcacheLogicException
     */
    public function __construct($driver, $config = null)
    {
        if ($driver instanceof ExtendedCacheItemPoolInterface) {
            if (null !== $config) {
                throw new PhpfastcacheLogicException("You can't pass a config parameter along with an non-string '\$driver' parameter.");
            }
            $this->internalCacheInstance = $driver;
        } else {
            $this->internalCacheInstance = CacheManager::getInstance($driver, $config);
        }
    }

    /**
     * @param string $key
     * @param string $group
     * @param null   $default
     *
     * @return mixed
     */
    public function get(string $key, string $group = 'default', $default = null)
    {
        $result = false;

        $key = $this->sanitizeKey($key, $group);

        try {
            $cacheItem = $this->internalCacheInstance->getItem($key);
            if (!$cacheItem->isExpired() && !$cacheItem->isNull()) {
                $value = maybe_unserialize($cacheItem->get());

                return \is_object($value) ? clone $value : $value;
            }

            return $default;
        } catch (PhpfastcacheInvalidArgumentException $e) {
            dump($e);
        }

        return $result;
    }

    /**
     * @param string|array $keys
     * @param string|array $groups
     *
     * @return array|bool
     */
    public function getMultiple(array $keys, $groups = 'default')
    {
        $result = false;

        $keys = $this->sanitizeKeys($keys, $groups);

        try {
            $result = array_map(static function (ExtendedCacheItemInterface $item) {
                if (!$item->isExpired() && !$item->isNull()) {
                    $value = maybe_unserialize($item->get());

                    return \is_object($value) ? clone $value : $value;
                }

                return false;
            }, $this->internalCacheInstance->getItems($keys));
        } catch (PhpfastcacheInvalidArgumentException $e) {
            dump($e);
        }

        return $result;
    }

    /**
     * @param string $key
     * @param        $value
     * @param string $group
     * @param null   $ttl
     *
     * @return bool
     */
    public function set(string $key, $value, string $group = 'default', $ttl = null)
    {
        $result = false;

        $key = $this->sanitizeKey($key, $group);
        $value = maybe_serialize($value);

        try {
            $cacheItem = $this->internalCacheInstance->getItem($key);
            $cacheItem->set($value);

            if (\is_int($ttl)) {
                if ($ttl <= 0) {
                    $cacheItem->expiresAt(new \DateTime('@0'));
                } elseif ($ttl instanceof \DateInterval) {
                    $cacheItem->expiresAfter($ttl);
                }
            }

            $result = $this->internalCacheInstance->save($cacheItem);
        } catch (PhpfastcacheInvalidArgumentException $e) {
            dump($e);
        }

        return $result;
    }

    /**
     * @param string $key
     * @param string $group
     *
     * @return bool
     */
    public function delete(string $key, string $group = 'default')
    {
        $result = false;
        $key = $this->sanitizeKey($key, $group);
        try {
            $result = $this->internalCacheInstance->deleteItem($key);
        } catch (InvalidArgumentException $e) {
            dump($e);
        }

        return $result;
    }

    /**
     * @param string $key
     * @param string $group
     *
     * @return string
     */
    protected function sanitizeKey(string $key, string $group)
    {
        $key = strtolower("{$group}_{$key}");

        $key = preg_replace('/[^a-z0-9_\-]/', '', $key);

        return $key;
    }

    /**
     * @param string|array $keys
     * @param string|array $groups
     *
     * @return array
     */
    protected function sanitizeKeys($keys, $groups = 'default')
    {
        $derived_keys = [];

        // If strings sent, convert to arrays for proper handling
        if (!\is_array($groups)) {
            $groups = (array) $groups;
        }
        if (!\is_array($keys)) {
            $keys = (array) $keys;
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
}
