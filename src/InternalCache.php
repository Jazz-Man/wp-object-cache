<?php

namespace JazzMan\WPObjectCache;

/**
 * Trait InternalCache.
 *
 * @property \Phpfastcache\Drivers\Memstatic\Driver _cache
 */
trait InternalCache
{

    /**
     * @param string $key
     *
     * @return mixed|null
     */
    protected function getCache(string $key)
    {
        $result = false;

        try {
            $item = $this->_cache->getItem($key);

            if ($item->isEmpty()) {
                return $result;
            }

            $result = $item->get();
        } catch (\Exception $e) {
            dump($e);
        }

        return \is_object($result) ? clone $result : $result;
    }

    /**
     * @param string $key
     * @param mixed  $value
     * @param string $group
     *
     * @return bool|mixed
     */
    protected function setCache(string $key, $value, string $group = 'default')
    {
        $result = false;
        try {
            $item = $this->_cache->getItem($key);
            $item->addTag($group);
            $item->set($value);

            $result = $this->_cache->save($item);
        } catch (\Exception $e) {
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
    protected function deleteCache(string $key, string $group = 'default')
    {
        $result = false;

        try {
            $item = $this->_cache->getItem($key);

            if (!$item->isNull()) {
                $this->_cache->detachItem($item);

                $result = true;
            }
        } catch (\Exception $e) {
            dump($e);
        }

        return $result;
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    protected function hasCache($key)
    {
        return $this->_cache->hasItem($key);
    }
}
