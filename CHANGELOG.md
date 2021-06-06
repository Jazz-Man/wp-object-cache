
2.1.2 / 2021-06-06
==================

  * fix all phpmd style
  * added translation
  * update phpmd rules

2.1.1 / 2021-04-14
==================

  * update plugin version
  * fix cash prefix and update redis parameters
  * removed ext-ctype

2.1.0 / 2021-04-03
==================

  * update admin page panel
  * install jazzman/wp-app-config

2.0.1 / 2021-04-03
==================

  * update license

2.0.0 / 2021-04-03
==================

  * deleted OutputCache
  * refactor code style
  * refactor RedisAdapter class
  * init phpmd rules
  * install phpstan
  * init RedisAdapter
  * add ext-ctype
  * update composer.json and code style
  * added advanced-cache.php
  * optimise performance
  * added output_cache group
  * simplified page caching checking methods
  * ini OutputCache class

v1.3 / 2019-05-01
=================

  * updated plugin version
  * removed unused private fields
  * prefix initialization has been updated
  * fixed multisite prefix
  * install symfony/var-dumper
  * removed admin_enqueue_scripts action

v1.1 / 2019-05-01
=================



v1.2 / 2019-05-01
=================

  * removed version and suggest from composer.json
  * removed version from composer.json
  * updated version of plugin

v1.0 / 2019-05-01
=================

  * Revert "sanitize pool prefix"
  * sanitize pool prefix
  * updated performance for driver config
  * removed $multisite prop
  * removed isIgnoredGroup method
  * removed $delay params
  * using var_export for showing data from cache
  * removed $delay params
  * renamed driverSet method to driverSetting
  * removed $config params from constructor
  * escaping link urls
  * create link string
  * removed getLink method from admin template
  * used filter_input function for $_GET
  * used filter_input function for $_GET
  * fix accesses the super-global variable $_GET.
  * removed helper.php
  * escape translation string
  * saved paths to files in variables
  * moved object-cache.php file to include dir

v1.0-beta / 2019-05-01
======================

  * removed jazzman/parameter-bag
  * removed debug statement
  * added admin page
  * updated driver config
  * added suggest
  * removed OutputCache class
  * removed memstatic instance
  * added cache_misses and cache_hits property
  * removed comments
  * added prefix for pool
  * removed getters
  * updated DriverAdapter
  * updated driver config
  * removed ObjectCache class
  * used DriverAdapter class
  * updated DriverAdapter class
  * refactored getCache method
  * init DriverAdapter class
  * updated "add", "set" and "get" methods
  * used InternalCache trait
  * rename class to Driver
  * rename class to Driver
  * added InternalCache trait
  * refactored setCache method
  * added memstatic driver
  * added getters
  * added base config for ObjectCacheDriver
  * install phpfastcache/phpfastcache
  * fix wp_cache_close function and added default parameters to wp_cache_get
  * init ObjectCacheDriver class
  * refactoring addGlobalGroups method
  * init memcached by group
  * updated validateExpiration method
  * refactor success method
  * build cache key
  * set cache_key_salt
  * remove unnecessary functions
  * Dependencies updated
  * updated group permissions
  * updated internal cache management
  * use ArrayObject class for internal cache
  * init ObjectCache class
  * init ObjectCacheBase class
  * init OutputCache class
  * updated wp_object_cache methods
  * init dev version
  * remove dev dependencies
  * added description to composer

0.2 / 2019-03-15
================

  * removed version from composer.json
  * added init actions

0.1 / 2019-03-12
================

  * rm composer.lock from repo
  * rm composer.lock from repo
  * Create README.md
  * added composer/installers as dependencies
  * Bump roots/wordpress from 4.9.9 to 5.1
  * updated license
  * added wp object cache methods
  * install jazzman/wp-memcached
  * Initial commit
