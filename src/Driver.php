<?php

namespace JazzMan\WPObjectCache;

use JazzMan\Traits\SingletonTrait;
use Phpfastcache\CacheManager;
use Phpfastcache\Config\ConfigurationOption;
use Phpfastcache\Drivers\Apcu\Config as ApcuConfig;
use Phpfastcache\Drivers\Memcached\Config as MemcachedConfig;
use Phpfastcache\Drivers\Redis\Config as RedisConfig;

/**
 * Class ObjectCacheDriver.
 */
class Driver
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

    /**
     * @var \Phpfastcache\Core\Pool\ExtendedCacheItemPoolInterface
     */
    private $cache_instance;

    public function __construct()
    {
        $this->setCacheConfig();

        try {
            $this->driverSet();

            $this->driver_config->setItemDetailedDate(true);
            $this->driver_config->setPreventCacheSlams(true);
            $this->driver_config->setAutoTmpFallback(true);


            $this->cache_instance = CacheManager::getInstance($this->driver, $this->driver_config ?: null);

        } catch (\Exception $e) {
            error_log($e);
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
        if (self::hasRedis()) {
            $this->driver = 'Redis';

            $this->driver_config = new RedisConfig([
                'host' => $this->cache_host,
                'port' => $this->cache_port ?: 6379,
            ]);

            if (!empty($this->cache_prefix) && \is_string($this->cache_prefix)) {
                $this->driver_config->setOptPrefix($this->cache_prefix);
            }

            if (!empty($this->cache_timeout) && \is_int($this->cache_timeout)) {
                $this->driver_config->setTimeout($this->cache_timeout);
            }

            if (!empty($this->cache_pass) && \is_string($this->cache_pass)) {
                $this->driver_config->setPassword($this->cache_pass);
            }

            if (!empty($this->cache_db) && \is_int($this->cache_db)) {
                $this->driver_config->setDatabase($this->cache_db);
            }

            return;
        }

        if (self::hasMemcached()) {
            $this->driver = 'Memcached';


            $this->driver_config = new MemcachedConfig([
                'host' => $this->cache_host,
                'port' => $this->cache_port ?: 11211,
            ]);

            $this->driver_config->setCompressData(true);

            if (!empty($this->cache_user) && \is_string($this->cache_user)) {
                $this->driver_config->setSaslUser($this->cache_user);
            }
            if (!empty($this->cache_pass) && \is_string($this->cache_pass)) {
                $this->driver_config->setSaslPassword($this->cache_pass);
            }

            if (!empty($this->cache_servers) && \is_array($this->cache_servers)) {
                $this->driver_config->setServers($this->cache_servers);
            }

            return;
        }

        if (self::hasApcu()) {
            $this->driver = 'Apcu';

            $this->driver_config = new ApcuConfig();

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
     * @return \Phpfastcache\Core\Pool\ExtendedCacheItemPoolInterface
     */
    public function getCacheInstance()
    {
        return $this->cache_instance;
    }
}
