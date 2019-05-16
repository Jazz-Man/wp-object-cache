<?php

namespace JazzMan\WPObjectCache;

/**
 * Class OutputCache.
 */
class OutputCache
{
    private $cache_group = 'output_cache';

    /**
     * @var string
     */
    private $request_method;
    /**
     * @var int
     */
    private $started;

    /**
     * @var \stdClass
     */
    private $url_info;


    /**
     * @var float|int
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
     * OutputCache constructor.
     */
    public function __construct()
    {
        $this->setUrlInfo($_SERVER['REQUEST_URI']);

        $this->started = time();

        $this->request_method = $_SERVER['REQUEST_METHOD'];

        if (self::enabled()) {
            return;
        }

        $this->run();
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
        $this->setupKeys();
        $this->setupUrlKey();
        $this->generateKeys();

        $this->maybeUpdateCache();
        $this->maybeOutputCache();

        // Didn't meet the minimum condition?
        if ((false === $this->do) && (false === $this->genlock)) {
            return;
        }

        // Headers and such
        add_filter('wp_redirect_status', [$this, 'redirectStatus'], 10, 2);

        // Start the spidey-sense listening
        ob_start([$this, 'ob']);
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
        $this->status_code = http_response_code();

        // Unlock regeneration
        wp_cache_delete("{$this->url_key}_genlock", $this->cache_group);

        // Do not cache blank pages unless they are HTTP redirects
        $output = trim($output);
        if (empty($output) && (empty($this->redirect_status) || empty($this->redirect_location))) {
            return $output;
        }

        // Do not cache 5xx responses
        if (isset($this->status_code) && $this->status_code >= 500) {
            return $output;
        }

        // Variants and keys
        $this->generateKeys();

        // Construct and save the spider_cache
        $this->cache = [
            'output' => $output,
            'time' => $this->started,
            'headers' => [],
            'status_header' => $this->status_code,
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
                    $this->max_age = (int) $matches[1];
                }
            }
        }

        // Set max-age & expiration
        $this->cache['max_age'] = $this->max_age;
        $this->cache['expires'] = $this->max_age + $this->seconds + 30;

        // Set cache
        wp_cache_set($this->key, $this->cache, $this->cache_group, $this->cache['expires']);

        $this->doHeaders($this->headers);

        // Pass output to next ob handler
        return $output;
    }

    /**
     * @return array|string
     */
    private function getQueryParams()
    {
        if (!empty($this->getUrlInfo()->query)) {
            wp_parse_str($this->getUrlInfo()->query, $query);

            return wp_unslash($query);
        }

        return [];
    }

    private function setupKeys()
    {
        $this->keys = [
            'host' => $this->getUrlInfo()->host,
            'method' => $this->request_method,
            'path' => $this->getUrlInfo()->path,
            'query' => $this->getQueryParams(),
            'ssl' => is_ssl(),
        ];
    }

    private function setupUrlKey()
    {
        $this->url_key = md5($this->getCurrentUrl());

        $this->url_version = (int) wp_cache_get("{$this->url_key}_version", $this->cache_group);
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
        // Get the cache
        $this->cache = wp_cache_get($this->key, $this->cache_group);

        // Are we only caching frequently-requested pages?
        if (($this->seconds < 1) || ($this->times < 2)) {
            $this->do = true;

        // No _cache item found, or ready to sample traffic again at the end of the cache life?
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

        $this->doHeaders($this->headers, $this->cache['headers']);

        // Set header if cached
        if (!empty($this->cache['status_header'])) {
            status_header($this->cache['status_header']);
        }

        // Have you ever heard a death rattle before?
        die($this->cache['output']);
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

            wp_safe_redirect($this->cache['redirect_location'], $this->cache['redirect_status']);

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
    private static function is_user_logged_in()
    {
        return is_user_logged_in();
    }

    /**
     * @return bool
     */
    private static function is_cache_debug()
    {
        return \defined('WP_CACHE_DEBUG') && WP_CACHE_DEBUG;
    }
}
