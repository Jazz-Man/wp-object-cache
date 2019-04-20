<?php

namespace JazzMan\WPObjectCache;

use JazzMan\Traits\SingletonTrait;
use Phpfastcache\CacheManager;
use Phpfastcache\Config\ConfigurationOption;
use Phpfastcache\Drivers\Memcached\Config as MC;
use Phpfastcache\Drivers\Redis\Config as RC;

/**
 * Class ObjectCacheDriver.
 */
class ObjectCacheDriver
{
    use SingletonTrait;

    /**
     * @var string
     */
    private $driver;

    /**
     * @var ConfigurationOption|null
     */
    private $driver_config;
    private $driver_global_config = [
        'itemDetailedDate' => true,
        'preventCacheSlams' => true,
        'autoTmpFallback' => true,
    ];
    /**
     * @var \Phpfastcache\Core\Pool\ExtendedCacheItemPoolInterface
     */
    private $cache_instance;

    private function __construct()
    {
        try {
            $this->driverSet();

            CacheManager::setDefaultConfig(new ConfigurationOption($this->driver_global_config));

            $this->cache_instance = CacheManager::getInstance($this->driver, $this->driver_config ?: null);
        } catch (\Exception $e) {
            dump($e->getMessage());
        }
    }

    /**
     * @throws \Phpfastcache\Exceptions\PhpfastcacheInvalidConfigurationException
     * @throws \ReflectionException
     */
    private function driverSet()
    {
        $cache_servers = \defined('WP_CACHE_SERVERS') ? WP_CACHE_SERVERS : false;
        $cache_host = \defined('WP_CACHE_HOST') ? WP_CACHE_HOST : '127.0.0.1';
        $cache_port = \defined('WP_CACHE_PORT') ? WP_CACHE_PORT : false;
        $cache_timeout = \defined('WP_CACHE_TIMEOUT') ? WP_CACHE_TIMEOUT : false;
        $cache_user = \defined('WP_CACHE_USERNAME') ? WP_CACHE_USERNAME : false;
        $cache_pass = \defined('WP_CACHE_PASSWORD') ? WP_CACHE_PASSWORD : false;
        $cache_db = \defined('WP_CACHE_DATABASE') ? WP_CACHE_DATABASE : false;
        $cache_prefix = \defined('WP_CACHE_PREFIX') ? WP_CACHE_PREFIX : false;

        if (\extension_loaded('Redis')) {
            $this->driver = 'Redis';

            $this->driver_config = new RC([
                'host' => $cache_host,
                'port' => $cache_port ?: 6379,
            ]);

            if (!empty($cache_prefix) && \is_string($cache_prefix)) {
                $this->driver_config->setOptPrefix($cache_prefix);
            }

            if (!empty($cache_timeout) && \is_int($cache_timeout)) {
                $this->driver_config->setTimeout($cache_timeout);
            }

            if (!empty($cache_pass) && \is_string($cache_pass)) {
                $this->driver_config->setPassword($cache_pass);
            }

            if (!empty($cache_db) && \is_int($cache_db)) {
                $this->driver_config->setDatabase($cache_db);
            }

            return;
        }
        if (class_exists('Memcached')) {
            $this->driver = 'Memcached';

            $this->driver_global_config['compressData'] = true;

            $this->driver_config = new MC([
                'host' => $cache_host,
                'port' => $cache_port ?: 11211,
            ]);

            if (!empty($cache_user) && \is_string($cache_user)) {
                $this->driver_config->setSaslUser($cache_user);
            }
            if (!empty($cache_pass) && \is_string($cache_pass)) {
                $this->driver_config->setSaslPassword($cache_pass);
            }

            if (!empty($cache_servers) && \is_array($cache_servers)) {
                $this->driver_config->setServers($cache_servers);
            }

            return;
        }

        if (\extension_loaded('apcu') && ini_get('apc.enabled')) {
            $this->driver = 'Apcu';

            return;
        }
    }

    /**
     * @return \Phpfastcache\Core\Pool\ExtendedCacheItemPoolInterface
     */
    public function getCacheInstance()
    {
        return $this->cache_instance;
    }
}
