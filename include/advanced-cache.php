<?php

use JazzMan\WPObjectCache\OutputCache;

function wp_cache_postload()
{

    new OutputCache();

}

///**
// * @param string $url
// *
// * @return array|bool|\WP_Post|null
// */
//function get_post_form_by_url(string $url)
//{
//    $result = false;
//
//    $url = filter_var($url, FILTER_VALIDATE_URL);
//
//    if (!empty($url)) {
//        global $wpdb;
//
//        $home_url = home_url();
//
//        $home_url_info = (object) parse_url($home_url);
//
//        $url_info = (object) parse_url($url);
//
//        if ($url_info && $url_info->host !== $home_url_info->host) {
//            return $result;
//        }
//
//        if (!empty($url_info->query)) {
//            parse_str($url_info->query, $query);
//
//            $query = array_filter($query, static function ($value, $key) {
//                return in_array($key, ['p', 'page_id', 'attachment_id'], true);
//            }, ARRAY_FILTER_USE_BOTH);
//
//            if (!empty($query)) {
//                return get_post((int) $query[key($query)]);
//            }
//        }
//
//        $url_info->scheme = $home_url_info->scheme;
//
//        if (false !== strpos($home_url_info->host, 'www.') && false === strpos($url_info->host, 'www.')) {
//            $url_info->host = "www.{$url_info->host}";
//        } elseif (false === strpos($home_url_info->host, 'www.')) {
//            $url_info->host = ltrim($url_info->host, 'www.');
//        }
//
//        if (trim($url, '/') === $home_url && 'page' === get_option('show_on_front')) {
//            $page_on_front = get_option('page_on_front');
//
//            if ($page_on_front) {
//                return get_post($page_on_front);
//            }
//        }
//
//        $url = trailingslashit("{$url_info->scheme}://{$url_info->host}{$url_info->path}");
//
//        $sql = $wpdb->prepare("SELECT SQL_CALC_FOUND_ROWS ID FROM $wpdb->posts p WHERE p.guid = %s LIMIT 1", [
//            $url,
//        ]);
//
//        $results = $wpdb->get_var($sql);
//
//        if (!empty($results)) {
//            return get_post((int) $results);
//        }
//
//        return $result;
//    }
//
//    return $result;
//}
