<?php

/*
Plugin Name: Memcached
Description: Memcache or Memcached backend for the WP Object Cache
Version: 0.1
Plugin URI: https://github.com/Jazz-Man/wp-object-cache
Author: Vasyl Sokolyk

*/

define('MOC_ROOT', __FILE__);

register_activation_hook(MOC_ROOT, function () {
    $oc = WP_CONTENT_DIR.'/object-cache.php';
    $moc_file = plugin_dir_path(MOC_ROOT).'/object-cache.php';

    if (!file_exists($oc) && function_exists('symlink')) {
        @symlink($moc_file, $oc);
    }
});

register_deactivation_hook(MOC_ROOT, function () {
    $oc = WP_CONTENT_DIR.'/object-cache.php';

    if (file_exists($oc)) {
        unlink($oc);
    }
});
