<?php
use JazzMan\WPObjectCache\Driver;

/**
 * @return \JazzMan\WPObjectCache\Driver
 */
function app_object_cache()
{
    return Driver::getInstance();
}
