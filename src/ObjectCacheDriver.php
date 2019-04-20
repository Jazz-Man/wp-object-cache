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
     * @var array|bool
     */
    private $cache_servers = false;
    /**
     * @var string
     */
    private $cache_host = '127.0.0.1';
    /**
     * @var int|bool
     */
    private $cache_port = false;
    /**
     * @var int|bool
     */
    private $cache_timeout = false;
    /**
     * @var string|bool
     */
    private $cache_user = false;
    /**
     * @var string|bool
     */
    private $cache_pass = false;
    /**
     * @var int|bool
     */
    private $cache_db = false;
    /**
     * @var string|bool
     */
    private $cache_prefix = false;
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
        $this->setCacheConfig();

        try {
            $this->driverSet();

            CacheManager::setDefaultConfig(new ConfigurationOption($this->driver_global_config));

            $this->cache_instance = CacheManager::getInstance($this->driver, $this->driver_config ?: null);
        } catch (\Exception $e) {
            dump($e->getMessage());
        }
    }

    private function setCacheConfig()
    {
        foreach (
            [
                'servers',
                'host',
                'port',
                'timeout',
                'user',
                'pass',
                'db',
                'prefix',
            ] as $setting
        ) {
            $constant = sprintf('WP_CACHE_%s', strtoupper($setting));
            if (\defined($constant)) {
                $this->{"cache_$setting"} = \constant($constant);
            }
        }
    }

    /**
     * @throws \Phpfastcache\Exceptions\PhpfastcacheInvalidConfigurationException
     * @throws \ReflectionException
     */
    private function driverSet()
    {
        $cache_servers = $this->getCacheServers();
        $cache_host = $this->getCacheHost();
        $cache_port = $this->getCachePort();
        $cache_timeout = $this->getCacheTimeout();
        $cache_user = $this->getCacheUser();
        $cache_pass = $this->getCachePass();
        $cache_db = $this->getCacheDb();
        $cache_prefix = $this->getCachePrefix();

        if (self::hasRedis()) {
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

        if (self::hasMemcached()) {
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

        if (self::hasApcu()) {
            $this->driver = 'Apcu';

            return;
        }
    }

    /**
     * @return bool
     */
    public static function hasRedis()
    {
        return \extension_loaded('Redis');
    }

    /**
     * @return bool
     */
    public static function hasMemcached()
    {
        return class_exists('Memcached');
    }

    /**
     * @return bool
     */
    public static function hasApcu()
    {
        return \extension_loaded('apcu') && ini_get('apc.enabled');
    }


    /**
     * @return array|bool
     */
    public function getCacheServers()
    {
        return $this->cache_servers;
    }


    /**
     * @return string
     */
    public function getCacheHost()
    {
        return $this->cache_host;
    }

    /**
     * @return bool|int
     */
    public function getCachePort()
    {
        return $this->cache_port;
    }

    /**
     * @return bool|int
     */
    public function getCacheTimeout()
    {
        return $this->cache_timeout;
    }

    /**
     * @return bool|string
     */
    public function getCacheUser()
    {
        return $this->cache_user;
    }

    /**
     * @return bool|string
     */
    public function getCachePass()
    {
        return $this->cache_pass;
    }

    /**
     * @return bool|int
     */
    public function getCacheDb()
    {
        return $this->cache_db;
    }

    /**
     * @return bool|string
     */
    public function getCachePrefix()
    {
        return $this->cache_prefix;
    }

    /**
     * @return \Phpfastcache\Core\Pool\ExtendedCacheItemPoolInterface
     */
    public function getCacheInstance()
    {
        return $this->cache_instance;
    }
}
