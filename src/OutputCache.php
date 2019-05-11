<?php

namespace JazzMan\WPObjectCache;

use tidy;
use WP_REST_Request;

/**
 * Class OutputCache.
 */
class OutputCache
{

    private static $unique = [];
    private $cache_group = 'output_cache';

    /**
     * @var
     */
    private $request;
    /**
     * @var int
     */
    private $started;

    /**
     * @var \stdClass
     */
    private $url_info;

    /**
     * @var
     */
    private $max_age = MINUTE_IN_SECONDS * 10;

    /**
     * ...in this many seconds (zero to ignore this and use spider_cache immediately).
     *
     * @var int
     */
    private $seconds = 120;

    /**
     * Only spider_cache a page after it is accessed this many times...
     * (two or more).
     *
     * @var int
     */
    private $times = 2;
    /**
     * @var array
     */
    private $keys;

    /**
     * @var string
     */
    private $key;

    /**
     * @var string
     */
    private $req_key;
    /**
     * @var string
     */
    private $url_key;
    /**
     * @var int
     */
    private $url_version;
    /**
     * @var string
     */
    private $status_header;

    /**
     * These headers will never be cached. Apply strtolower.
     *
     * @var array
     */
    private $uncached_headers = ['transfer-encoding'];
    /**
     * @var int
     */
    private $status_code;

    /**
     * Set true to enable redirect caching.
     *
     * @var bool
     */
    private $cache_redirects = false;
    /**
     * This is set to the response code during a redirect.
     *
     * @var int
     */
    private $redirect_status = false;
    /**
     * @var string
     */
    private $redirect_location;
    /**
     * @var mixed
     */
    private $cache;
    /**
     * @var bool
     */
    private $do;

    /**
     * @var bool
     */
    private $genlock = false;
    /**
     * @var int
     */
    private $requests;
    /**
     * @var array
     */
    private $headers = [];
    /**
     * Set false to disable Last-Modified and Cache-Control headers.
     *
     * @var bool
     */
    private $cache_control = true;

    /**
     * OutputCache constructor.
     */
    public function __construct()
    {
        $this->setUrlInfo($_SERVER['REQUEST_URI']);

        $this->started = time();


        $this->request = new WP_REST_Request($_SERVER['REQUEST_METHOD'], $this->getUrlInfo()->path);

        if (!empty($this->getUrlInfo()->query)) {
            wp_parse_str($this->getUrlInfo()->query, $query);
            $this->request->set_query_params(wp_unslash($query));
        }

        $this->request->set_headers($this->getHttpHeaders(wp_unslash($_SERVER)));

        if (self::enabled()) {
            return;
        }

        $this->setupCacheGroup();

        $this->run();
    }

    private function setupCacheGroup(){
        wp_cache_add_global_groups($this->cache_group);
    }

    /**
     * @param string $status_header
     * @param int    $status_code
     *
     * @return mixed
     */
    public function statusHeader(string $status_header, int $status_code = 200)
    {
        $this->status_header = $status_header;
        $this->status_code = $status_code;

        return $status_header;
    }

    /**
     * @param int    $status
     * @param string $location
     *
     * @return int
     */
    public function redirectStatus(int $status, string $location)
    {
        // Cache this redirect
        if (true === $this->cache_redirects) {
            $this->redirect_status = $status;
            $this->redirect_location = $location;
        }

        return $status;
    }

    /**
     * @return \stdClass
     */
    public function getUrlInfo()
    {
        return $this->url_info;
    }

    /**
     * @param string $url_info
     */
    public function setUrlInfo(string $url_info)
    {
        $url = network_home_url($url_info);

        $this->url_info = (object) parse_url(esc_url_raw($url));
    }

    /**
     * @return string
     */
    private function getCurrentUrl()
    {
        $url = "{$this->getUrlInfo()->scheme}://{$this->getUrlInfo()->host}{$this->getUrlInfo()->path}";

        if (!empty($this->getUrlInfo()->query)) {
            wp_parse_str($this->getUrlInfo()->query, $query);

            $url = add_query_arg($query, $url);
        }

        return $url;
    }

    public function run()
    {
        // Necessary to prevent clients using cached version after login cookies
        // set. If this is a problem, comment it out and remove all
        // Last-Modified headers.
        @header('Vary: Cookie', false);

        $this->setupKeys();
        $this->setupUrlKey();
        $this->doVariants();
        $this->generateKeys();

        $request_hash = [
            'request'=>$this->getCurrentUrl(),
            'host'=>$this->getUrlInfo()->host,
            'https'=>! empty( $_SERVER['HTTPS'] ) ? $_SERVER['HTTPS'] : '',
            'method' => $_SERVER['REQUEST_METHOD'],
            'unique' => self::$unique,
            'cookies' => self::parse_cookies( $_COOKIE ),
        ];

        if ( ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
            $request_hash['unique']['pj-auth-header'] = $_SERVER['HTTP_AUTHORIZATION'];
        }

        $request_hash = md5( serialize( $request_hash ) );

        if ( self::is_cache_debug() ) {
            header( 'X-Pj-Cache-Key: ' . $request_hash );
        }

        $this->maybeUpdateCache();
        $this->maybeOutputCache();

        // Didn't meet the minimum condition?
        if ((false === $this->do) && (false === $this->genlock)) {
            return;
        }

        // Headers and such
        add_filter('status_header', [$this, 'statusHeader'], 10, 2);
        add_filter('wp_redirect_status', [$this, 'redirectStatus'], 10, 2);


        // Start the spidey-sense listening
        ob_start([$this, 'ob']);

//        dump($this->redirect_status);
    }

    /**
     * Start the output buffer.
     *
     * @since 2.0.0
     *
     * @param string $output
     *
     * @return string
     */
    protected function ob($output = '')
    {


        // $wp_object_cache was clobbered in wp-settings.php so repeat this
        $this->setupCacheGroup();

        // Unlock regeneration
        wp_cache_delete("{$this->url_key}_genlock", $this->cache_group);

        // Do not cache blank pages unless they are HTTP redirects
        $output = trim($output);
        if (empty($output) && (empty($this->redirect_status) || empty($this->redirect_location))) {
            return $output;
        }

        // Do not cache 5xx responses
        if (isset($this->status_code) && 5 === (int) ($this->status_code / 100)) {
            return $output;
        }

        // Variants and keys
        $this->generateKeys();

        // Construct and save the spider_cache
        $this->cache = [
            'output' => $output,
            'time' => $this->started,
            'headers' => [],
            'timer' => $this->timerStop(false, 3),
            'status_header' => $this->status_header,
            'redirect_status' => $this->redirect_status,
            'redirect_location' => $this->redirect_location,
            'version' => $this->url_version,
        ];

        // PHP5 and higher (
        foreach (headers_list() as $header) {
            list($k, $v) = array_map('trim', explode(':', $header, 2));
            $this->cache['headers'][$k] = [$v];
        }

        // Unset uncached headers
        if (!empty($this->cache['headers']) && !empty($this->uncached_headers)) {
            foreach ($this->uncached_headers as $header) {
                unset($this->cache['headers'][$header]);
            }
        }

        // Set cached headers
        foreach ($this->cache['headers'] as $header => $values) {
            // Bail if cookies were set
            if ('set-cookie' === strtolower($header)) {
                return $output;
            }

            foreach ((array) $values as $value) {
                if (preg_match('/^Cache-Control:.*max-?age=(\d+)/i', "{$header}: {$value}", $matches)) {
                    $this->max_age = (int)$matches[1];
                }
            }
        }

        // Set max-age & expiration
        $this->cache['max_age'] = $this->max_age;
        $this->cache['expires'] = $this->max_age + $this->seconds + 30;

        // Set cache
        wp_cache_set($this->key, $this->cache, $this->cache_group, $this->cache['expires']);
        wp_cache_set($this->key, $this->cache, $this->cache_group, $this->cache['expires']);

        // Cache control
        if (true === $this->cache_control) {
            // Don't clobber Last-Modified header if already set, e.g. by WP::send_headers()
            if (!isset($this->cache['headers']['Last-Modified'])) {
                @header('Last-Modified: '.gmdate('D, d M Y H:i:s', $this->cache['time']).' GMT', true);
            }

            if (!isset($this->cache['headers']['Cache-Control'])) {
                @header("Cache-Control: max-age={$this->max_age}, must-revalidate", false);
            }
        }

        $this->doHeaders($this->headers);

        // Add some debug info just before <head

        if (self::is_cache_debug()){
            $this->addDebugJustCached();
        }

        // Pass output to next ob handler
        return $this->cache['output'];
    }

    /**
     * @param array $server
     *
     * @return array
     */
    private function getHttpHeaders(array $server)
    {
        $headers = [];

        // CONTENT_* headers are not prefixed with HTTP_.
        $additional = ['CONTENT_LENGTH' => true, 'CONTENT_MD5' => true, 'CONTENT_TYPE' => true];

        foreach ($server as $key => $value) {
            if (0 === strpos($key, 'HTTP_')) {
                $headers[substr($key, 5)] = $value;
            } elseif (isset($additional[$key])) {
                $headers[$key] = $value;
            }
        }

        return $headers;
    }

    /**
     * @param string $url
     *
     * @return array|bool|\WP_Post|null
     */
    private function getPostFormUrl(string $url)
    {
        $result = false;

        $url = filter_var($url, FILTER_VALIDATE_URL);

        if (!empty($url)) {
            global $wpdb;

            $home_url = home_url();

            $home_url_info = (object) parse_url($home_url);

            $url_info = (object) parse_url($url);

            if ($url_info && $url_info->host !== $home_url_info->host) {
                return $result;
            }

            if (!empty($url_info->query)) {
                parse_str($url_info->query, $query);

                $query = array_filter($query, static function ($value, $key) {
                    return \in_array($key, ['p', 'page_id', 'attachment_id'], true);
                }, ARRAY_FILTER_USE_BOTH);

                if (!empty($query)) {
                    return get_post((int) $query[key($query)]);
                }
            }

            $url_info->scheme = $home_url_info->scheme;

            if (false !== strpos($home_url_info->host, 'www.') && false === strpos($url_info->host, 'www.')) {
                $url_info->host = "www.{$url_info->host}";
            } elseif (false === strpos($home_url_info->host, 'www.')) {
                $url_info->host = ltrim($url_info->host, 'www.');
            }

            if (trim($url, '/') === $home_url && 'page' === get_option('show_on_front')) {
                $page_on_front = get_option('page_on_front');

                if ($page_on_front) {
                    return get_post($page_on_front);
                }
            }

            $url = trailingslashit("{$url_info->scheme}://{$url_info->host}{$url_info->path}");

            $sql = $wpdb->prepare("SELECT SQL_CALC_FOUND_ROWS ID FROM $wpdb->posts p WHERE p.guid = %s LIMIT 1", [
                $url,
            ]);

            $results = $wpdb->get_var($sql);

            if (!empty($results)) {
                return get_post((int) $results);
            }

            return $result;
        }

        return $result;
    }

    private function setupKeys()
    {
        $this->keys = [
            'host' => $this->getUrlInfo()->host,
            'method' => $this->request->get_method(),
            'path' => $this->getUrlInfo()->path,
            'query' => $this->request->get_query_params(),
            'ssl' => is_ssl(),
        ];
    }

    private function setupUrlKey()
    {
        $this->url_key = md5($this->getCurrentUrl());

        $this->url_version = (int) wp_cache_get("{$this->url_key}_version", $this->cache_group);
    }

    /**
     * Set the cache with it's variant keys.
     *
     * @param bool $dimensions
     */
    private function doVariants($dimensions = false)
    {
        // This function is called without arguments early in the page load,
        // then with arguments during the OB handler.

        if (false === $dimensions) {
            $dimensions = wp_cache_get("{$this->url_key}_vary", $this->cache_group);
        } elseif (!empty($dimensions)) {
            wp_cache_set("{$this->url_key}_vary", $dimensions, $this->cache_group, $this->max_age + 10);
        }

        // Bail if no dimensions
        if (empty($dimensions) || !\is_array($dimensions)) {
            return;
        }

        //        dump($dimensions);
    }

    /**
     * @return bool
     */
    private static function enabled()
    {
        return self::is_cli() || self::is_wp_cli() || self::is_cron() || self::is_importing() || self::is_user_logged_in() || self::is_admin() || self::is_post_request() || self::is_ajax();
    }

    private function maybeUpdateCache()
    {
        // Get the spider_cache
        $this->cache = wp_cache_get($this->key, $this->cache_group);

        // Are we only caching frequently-requested pages?
        if (($this->seconds < 1) || ($this->times < 2)) {
            $this->do = true;

        // No spider_cache item found, or ready to sample traffic again at the end of the spider_cache life?
        } elseif (!\is_array($this->cache) || ($this->started >= ($this->cache['time'] + $this->max_age - $this->seconds))) {
            wp_cache_add($this->req_key, 0, $this->cache_group);

            $this->requests = wp_cache_incr($this->req_key, 1, $this->cache_group);

            $this->do = ($this->requests >= $this->times);
        }

        // If the document has been updated and we are the first to notice, regenerate it.
        if ((true === $this->do) && isset($this->cache['version']) && ($this->cache['version'] < $this->url_version)) {
            $this->genlock = wp_cache_add("{$this->url_key}_genlock", 1, $this->cache_group, 10);
        }
    }

    private function generateKeys()
    {
        $this->key = md5(serialize($this->keys));
        $this->req_key = "{$this->key}_req";
    }

    /**
     * Maybe output the contents from cache, including performing a redirection
     * if necessary.
     */
    private function maybeOutputCache()
    {
        // Bail if no page, or is locked or expired
        if (true === $this->genlock || !isset($this->cache['time']) || ($this->started > ($this->cache['time'] + $this->cache['max_age']))) {
            return;
        }

        // Maybe perform a redirection
        $this->maybeDoRedirect();

        // Use the spider_cache save time for Last-Modified so we can issue
        // "304 Not Modified" but don't clobber a cached Last-Modified header.
        if ((true === $this->cache_control) && !isset($this->cache['headers']['Last-Modified'][0])) {
            @header('Last-Modified: '.gmdate('D, d M Y H:i:s', $this->cache['time']).' GMT', true);
            @header('Cache-Control: max-age='.($this->cache['max_age'] - $this->started + $this->cache['time']).', must-revalidate',
                true);
        }

        if (self::is_cache_debug()){
            $this->addDebugFromCache();
        }

        $this->doHeaders($this->headers, $this->cache['headers']);

        // Bail if not modified
        if ($this->notModified()) {
            @header('HTTP/1.1 304 Not Modified', true, 304);
            die;
        }

        // Set header if cached
        if (!empty($this->cache['status_header'])) {
            @header($this->cache['status_header'], true);
        }

        $tidy = new tidy();

        $tidy->parseString($this->cache['output'],[
            'clean'=>true,
            'merge-divs'=>false,
            'join-classes'=>true,
            'hide-comments'=>true,
            'hide-endtags'=>true,
//            'break-before-br'=>true,
            'indent'=>true,
            'indent-attributes'=>true,
            'punctuation-wrap'=>true,
//            'wrap-attributes'=>true,
//            'wrap-script-literals'=>true,
//            'decorate-inferred-ul'=>true,

        ]);
        $tidy->cleanRepair();

//        $string = gzcompress((string)$tidy,-1);
//
//        dump($string);

        // Have you ever heard a death rattle before?
        die((string)$tidy);
    }

    /**
     * Maybe perform a redirection.
     */
    private function maybeDoRedirect()
    {
        // Issue redirect if cached and enabled
        if ($this->cache['redirect_status'] && $this->cache['redirect_location'] && $this->cache_redirects) {
            // Do headers
            $this->doHeaders($this->headers);

            // From vars.php
            $is_IIS = (false !== strpos($_SERVER['SERVER_SOFTWARE'],
                    'Microsoft-IIS') || false !== strpos($_SERVER['SERVER_SOFTWARE'], 'ExpressionDevServer'));

            // IIS
            if (!empty($is_IIS)) {
                @header("Refresh: 0;url={$this->cache['redirect_location']}");
            } else {
                if (\PHP_SAPI !== 'cgi-fcgi') {
                    $texts = [
                        300 => 'Multiple Choices',
                        301 => 'Moved Permanently',
                        302 => 'Found',
                        303 => 'See Other',
                        304 => 'Not Modified',
                        305 => 'Use Proxy',
                        306 => 'Reserved',
                        307 => 'Temporary Redirect',
                    ];

                    // Get the protocol
                    $protocol = wp_get_server_protocol();

                    // Found/Redirect header
                    isset($texts[$this->cache['redirect_status']]) ? @header("{$protocol} {$this->cache['redirect_status']} {$texts[$this->cache['redirect_status']]}") : @header("{$protocol} 302 Found");
                }

                @header("Location: {$this->cache['redirect_location']}");
            }

            // Exit so redirect takes effect
            exit;
        }
    }

    /**
     * @param array $headers1
     * @param array $headers2
     */
    private function doHeaders(array $headers1, array $headers2 = [])
    {
        // Merge the arrays of headers into one
        $headers = [];
        $keys = array_unique(array_merge(array_keys($headers1), array_keys($headers2)));

        foreach ($keys as $k) {
            $headers[$k] = [];

            if (isset($headers1[$k]) && isset($headers2[$k])) {
                $headers[$k] = array_merge((array) $headers2[$k], (array) $headers1[$k]);
            } elseif (isset($headers2[$k])) {
                $headers[$k] = (array) $headers2[$k];
            } else {
                $headers[$k] = (array) $headers1[$k];
            }

            $headers[$k] = array_unique($headers[$k]);
        }

        // These headers take precedence over any previously sent with the same names
        foreach ($headers as $k => $values) {
            $clobber = true;
            foreach ($values as $v) {
                @header("{$k}: {$v}", $clobber);
                $clobber = false;
            }
        }
    }

    /**
     * Has the cached page changed?
     */
    private function notModified()
    {
        // Default value
        $three_oh_four = false;

        // Respect ETags served with feeds.
        if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && isset($this->cache['headers']['ETag'][0]) && ($_SERVER['HTTP_IF_NONE_MATCH'] == $this->cache['headers']['ETag'][0])) {
            $three_oh_four = true;

        // Respect If-Modified-Since.
        } elseif ((true === $this->cache_control) && isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
            // Get times
            $client_time = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
            $cache_time = isset($this->cache['headers']['Last-Modified'][0]) ? strtotime($this->cache['headers']['Last-Modified'][0]) : $this->cache['time'];

            // Maybe 304
            if ($client_time >= $cache_time) {
                $three_oh_four = true;
            }
        }

        // Return 304 status
        return $three_oh_four;
    }

    /**
     * @return bool
     */
    private static function is_cli()
    {
        return \PHP_SAPI === 'cli';
    }

    /**
     * @return bool
     */
    private static function is_wp_cli()
    {
        return \defined('WP_CLI') && WP_CLI;
    }

    /**
     * @return bool
     */
    private static function is_cron()
    {
        return \defined('DOING_CRON') && DOING_CRON;
    }

    /**
     * @return bool
     */
    private static function is_importing()
    {
        return \defined('WP_IMPORTING') && WP_IMPORTING;
    }

    /**
     * @return bool
     */
    private static function is_admin()
    {
        return is_admin() || is_blog_admin() || is_network_admin();
    }

    /**
     * @return bool
     */
    private static function is_ajax()
    {
        return \defined('DOING_AJAX') && DOING_AJAX;
    }

    /**
     * @return bool
     */
    private static function is_post_request()
    {
        return 'POST' === $_SERVER['REQUEST_METHOD'];
    }

    /**
     * @return bool
     */
    private static function is_user_logged_in(){
        return \is_user_logged_in();
    }

    /**
     * @return bool
     */
    private static function is_cache_debug(){
        return \defined('WP_CACHE_DEBUG') && WP_CACHE_DEBUG;
    }

    /**
     * @param bool $display
     * @param int  $precision
     *
     * @return string
     */
    private function timerStop($display = true, $precision = 3){
        global $timestart, $timeend;

        $mtime = microtime();
        $mtime = explode(' ', $mtime);
        $timeend = $mtime[1] + $mtime[0];
        $timetotal = $timeend - $timestart;
        $r = number_format($timetotal, $precision);

        if (true === $display) {
            echo $r;
        }

        return $r;
    }

    private function addDebugFromCache(){
        $time = $this->started;
        $seconds_ago = $time - $this->cache['time'];
        $generation = $this->cache['timer'];
        $serving = $this->timerStop(false, 3);
        $expires = $this->cache['max_age'] - $time + $this->cache['time'];
        $html = <<<HTML
<!--
	generated {$seconds_ago} seconds ago
	generated in {$generation} seconds
	served from Spider-Cache in {$serving} seconds
	expires in {$expires} seconds
-->

HTML;
        $this->addDebugHtmlToOutput($html);
    }

    /**
     * @param string $html
     */
    private function addDebugHtmlToOutput(string $html)
    {
        // Casing on the Content-Type header is inconsistent
        foreach (['Content-Type', 'Content-type'] as $key) {
            if (isset($this->cache['headers'][$key][0]) && 0 !== strpos($this->cache['headers'][$key][0], 'text/html')) {
                return;
            }
        }


        // Bail if output does not include a head tag
        $head_position = strpos($this->cache['output'], '<head');

        if (false === $head_position) {
            return;
        }

        // Put debug HTML ahead of the <head> tag
        $this->cache['output'] = substr_replace($this->cache['output'], $html, $head_position, 0);
    }

    private function addDebugJustCached()
    {
        $generation = $this->cache['timer'];
        $bytes = strlen(serialize($this->cache));
        $html = <<<HTML
<!--
	generated in {$generation} seconds
	{$bytes} bytes Spider-Cached for {$this->max_age} seconds
-->

HTML;
        $this->addDebugHtmlToOutput($html);
    }

    private static function parse_cookies(array $cookies)
    {
        foreach ( $cookies as $key => $value ) {
//            if ( in_array( strtolower( $key ), self::$ignore_cookies ) ) {
//                unset( $cookies[ $key ] );
//                continue;
//            }
            // Skip cookies beginning with _
            if (strpos($key, '_') === 0) {
                unset( $cookies[ $key ] );
                continue;
            }
        }
        return $cookies;
    }
}
