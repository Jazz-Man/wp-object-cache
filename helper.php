<?php
use JazzMan\WPObjectCache\ObjectCacheDriver;

/**
 * @return \JazzMan\WPObjectCache\ObjectCacheDriver
 */
function app_object_cache()
{
    return ObjectCacheDriver::getInstance();
}
