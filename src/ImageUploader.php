<?php

/**
 * @author Ali Irani <ali@irani.im>
 */
class ImageUploader
{
    public $post;
    public $url;
    public $alt;

    public function __construct($url, $alt, $post)
    {
        $this->post = $post;
        $this->url = $url;
        $this->alt = $alt;
    }

    /**
     * Return host of url
     * @param null|string $url
     * @param bool $scheme
     * @param bool $www
     * @return null|string
     */
    public static function getHostUrl($url = null, $scheme = false, $www = false)
    {
        $url = $url ?: WpAutoUpload::getOption('base_url');

        $urlParts = parse_url($url);

        if (array_key_exists('host', $urlParts) === false) {
            return null;
        }

        $host = array_key_exists('port', $urlParts) ? $urlParts['host'] . ":" . $urlParts['port'] : $urlParts['host'];
        if (!$www) {
            $withoutWww = preg_split('/^(www(2|3)?\.)/i', $host, -1, PREG_SPLIT_NO_EMPTY); // Delete www from host
            $host = is_array($withoutWww) && array_key_exists(0, $withoutWww) ? $withoutWww[0] : $host;
        }
        return $scheme && array_key_exists('scheme', $urlParts) ? $urlParts['scheme'] . '://' . $host : $host;
    }

    /**
     * Check url is allowed to upload or not
     * @return bool
     */
    public function validate()
    {
        $parsedUrl = parse_url($this->url);

        // Only allow http(s) urls with a host
        if (!$parsedUrl || !isset($parsedUrl['host']) || !in_array(strtolower(isset($parsedUrl['scheme']) ? $parsedUrl['scheme'] : ''), array('http', 'https'), true)) {
            return false;
        }

        // Block SSRF targets (private/reserved IPs, cloud metadata, ...)
        if (!self::isHostSafe($parsedUrl['host'])) {
            return false;
        }

        $url = self::getHostUrl($this->url);
        $site_url = self::getHostUrl() === null ? self::getHostUrl(site_url('url')) : self::getHostUrl();

        if ($url === $site_url || !$url) {
            return false;
        }

        if ($urls = WpAutoUpload::getOption('exclude_urls')) {
            $exclude_urls = explode("\n", $urls);

            foreach ($exclude_urls as $exclude_url) {
                if ($url === self::getHostUrl(trim($exclude_url))) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Check if a host resolves only to safe public IPs (SSRF protection)
     * Blocks redirects to private addresses: wp_remote_get follows redirects without re-validation.
     * ponytail: full fix needs a custom redirect handler that re-checks each hop; add if a redirect-following request API becomes available.
     * @param string $host
     * @return bool
     */
    public static function isHostSafe($host)
    {
        // Strip brackets from IPv6 literals and port
        $host = trim(preg_replace('/^\[(.+)\]$/', '$1', $host));

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return self::isIpSafe($host);
        }

        // Resolve all A/AAAA records once; every resolved IP must be safe
        $ips = array();
        foreach (array(DNS_A => 'ip', DNS_AAAA => 'ipv6') as $type => $field) {
            $records = @dns_get_record($host, $type);
            if (is_array($records)) {
                foreach ($records as $record) {
                    if (isset($record[$field])) {
                        $ips[] = $record[$field];
                    }
                }
            }
        }

        if (empty($ips)) {
            $ip = @gethostbyname($host);
            if ($ip !== $host && filter_var($ip, FILTER_VALIDATE_IP)) {
                $ips[] = $ip;
            }
        }

        if (empty($ips)) {
            return false; // unresolvable host
        }

        foreach (array_unique($ips) as $ip) {
            if (!self::isIpSafe($ip)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if an IP is public and safe to fetch from
     * @param string $ip
     * @return bool
     */
    public static function isIpSafe($ip)
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        // Blocks private (10/8, 172.16/12, 192.168/16, fc00::/7, ...) and reserved ranges.
        // Note: PHP's FILTER_FLAG_NO_RES_RANGE misses 224/4 multicast, 100.64/10 CGNAT and IPv4-mapped IPv6 (::ffff:x) — covered below.
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return false;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ipLong = ip2long($ip);
            foreach (array(
                array(0,          16777215),    // 0.0.0.0/8        "this host"
                array(1681915904, 1686110207),  // 100.64.0.0/10    carrier-grade NAT
                array(2851995648, 2852061183),  // 169.254.0.0/16   link-local / cloud metadata
                array(3758096384, 4026531839),  // 224.0.0.0/4      multicast
            ) as $range) {
                if ($ipLong >= $range[0] && $ipLong <= $range[1]) {
                    return false;
                }
            }
            return true;
        }

        // IPv6: block IPv4-mapped/compatible, link-local, ULA, multicast (first byte)
        $packed = @inet_pton($ip);
        $firstByte = ord($packed);
        if ($firstByte === 0 || ($firstByte & 0xfe) === 0xfe) { // ::x family (::ffff:x, ::1), fe80::/10, ff00::/8
            return false;
        }

        // 6to4 (2002::/16) and Teredo (2001:0::/32) embed an IPv4 address; check it too
        if (substr($packed, 0, 2) === "\x20\x02" || substr($packed, 0, 4) === "\x20\x01\x00\x00") {
            $embedded = substr($packed, $packed[1] === "\x02" ? 2 : 12, 4);
            if ($embedded !== false && strlen($embedded) === 4) {
                return self::isIpSafe(inet_ntop($embedded));
            }
        }

        return true;
    }

    /**
     * Return custom image filename with user rules
     * @return string
     */
    protected function getFilename()
    {
        $filename = trim($this->resolvePattern(WpAutoUpload::getOption('image_name', '%filename%')));
        return sanitize_file_name($filename ?: uniqid('img_', false));
    }

    /**
     * Returns original image filename if valid
     * @return string|null
     */
    protected function getOriginalFilename()
    {
        // Strip query string and fragment: pathinfo() on a full URL mangles it (issue #96)
        $path = parse_url($this->url, PHP_URL_PATH);
        if (empty($path)) {
            return null;
        }

        $urlParts = pathinfo($path);

        // Only accept image-like extensions
        $ext = isset($urlParts['extension']) ? strtolower($urlParts['extension']) : '';
        if (in_array($ext, array('jpg', 'jpeg', 'png', 'gif', 'bmp', 'tif', 'tiff', 'webp'), true) === false) {
            return null;
        }

        return sanitize_file_name($urlParts['filename']);
    }

    private $_uploadDir;
    private $_redirects = 0;

    /**
     * Return information of upload directory
     * fields: path, url, subdir, basedir, baseurl
     * @param $field
     * @return string|null
     */
    protected function getUploadDir($field)
    {
        if ($this->_uploadDir === null) {
            $this->_uploadDir = wp_upload_dir(date('Y/m', time()));
        }
        return is_array($this->_uploadDir) && array_key_exists($field, $this->_uploadDir) ? $this->_uploadDir[$field] : null;
    }

    /**
     * Return custom alt name with user rules
     * @return string Custom alt name
     */
    public function getAlt()
    {
        return esc_attr($this->resolvePattern(WpAutoUpload::getOption('alt_name')));
    }

    /**
     * Returns string patterned
     * @param $pattern
     * @return string
     */
    public function resolvePattern($pattern)
    {
        preg_match_all('/%[^%]*%/', $pattern, $rules);

        $postDateGmt = isset($this->post['post_date_gmt']) && $this->post['post_date_gmt']
            ? strtotime($this->post['post_date_gmt'])
            : false;

        $patterns = array(
            '%filename%' => $this->getOriginalFilename(),
            '%image_alt%' => $this->alt,
            '%date%' => date('Y-m-j'), // deprecated
            '%today_date%' => date('Y-m-j'),
            '%year%' => date('Y'),
            '%month%' => date('m'),
            '%day%' => date('j'), // deprecated
            '%today_day%' => date('j'),
            '%post_date%' => date('Y-m-j', $postDateGmt ?: time()),
            '%post_year%' => date('Y', $postDateGmt ?: time()),
            '%post_month%' => date('m', $postDateGmt ?: time()),
            '%post_day%' => date('j', $postDateGmt ?: time()),
            '%url%' => self::getHostUrl(get_bloginfo('url')),
            '%random%' => uniqid('img_', false),
            '%timestamp%' => time(),
            '%post_id%' => $this->post['ID'],
            '%postname%' => $this->post['post_name'],
        );

        if ($rules[0]) {
            foreach ($rules[0] as $rule) {
                // str_replace: no regex interpretation of rule or replacement (ReDoS/injection safe)
                $pattern = str_replace($rule, array_key_exists($rule, $patterns) ? $patterns[$rule] : $rule, $pattern);
            }
        }

        return $pattern;
    }

    /**
     * Save image and validate
     * @return null|array image data
     */
    public function save()
    {
        if (!$this->validate()) {
            return null;
        }

        $image = $this->downloadImage($this->url);

        if (is_wp_error($image)) {
            return null;
        }

        return $image;
    }

    /**
     * Download image
     * @param $url
     * @return array|WP_Error
     */
    public function downloadImage($url)
    {
        $url = self::normalizeUrl($url);

        $parsedUrl = parse_url($url);
        if (!$parsedUrl || !isset($parsedUrl['scheme'], $parsedUrl['host']) || !in_array(strtolower($parsedUrl['scheme']), array('http', 'https'), true)) {
            return new WP_Error('aui_invalid_url', 'AUI: Invalid URL provided.');
        }

        // Final SSRF check before making request
        if (!self::isHostSafe($parsedUrl['host'])) {
            return new WP_Error('aui_blocked_url', 'AUI: URL blocked for security reasons.');
        }

        $args = array(
            'user-agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url(),
            'timeout' => 30,
            'redirection' => 0, // follow redirects manually so each hop is SSRF-checked
            'sslverify' => true,
            'limit_response_size' => 50 * 1024 * 1024, // 50MB
        );
        $response = wp_remote_get($url, $args);

        // Manually follow redirects, re-validating each hop against SSRF
        while ($response !== null && !is_wp_error($response) && isset($response['response']['code'])
            && in_array($response['response']['code'], array(301, 302, 303, 307, 308), true)
            && isset($response['headers']['location'])) {

            if (++$this->_redirects > 3) {
                return new WP_Error('aui_too_many_redirects', 'AUI: Too many redirects.');
            }

            $location = $response['headers']['location'];
            // Resolve relative redirects against the current url
            if (!preg_match('/^(https?:)?\/\//', $location)) {
                $base = parse_url($url);
                if ($base === false || !isset($base['scheme'], $base['host'])) {
                    return new WP_Error('aui_invalid_redirect', 'AUI: Invalid redirect location.');
                }
                $location = $base['scheme'] . '://' . $base['host']
                    . (isset($base['port']) ? ':' . $base['port'] : '')
                    . (strpos($location, '/') === 0 ? $location : (isset($base['path']) ? rtrim(dirname($base['path']), '/') : '') . '/' . $location);
            }
            $url = self::normalizeUrl($location);

            $parsedUrl = parse_url($url);
            if (!$parsedUrl || !isset($parsedUrl['scheme'], $parsedUrl['host']) || !in_array(strtolower($parsedUrl['scheme']), array('http', 'https'), true)) {
                return new WP_Error('aui_invalid_redirect', 'AUI: Invalid redirect location.');
            }
            if (!self::isHostSafe($parsedUrl['host'])) {
                return new WP_Error('aui_blocked_url', 'AUI: Redirected URL blocked for security reasons.');
            }

            $response = wp_remote_get($url, $args);
        }

        if ($response instanceof WP_Error) {
            return $response;
        }

        if (isset($response['response']['code'], $response['body']) && $response['response']['code'] !== 200) {
            return new WP_Error('aui_download_failed', 'AUI: Image file bad response.');
        }

        if (empty($response['body'])) {
            return new WP_Error('aui_empty_response', 'AUI: Empty response body.');
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'WP_AUI');
        file_put_contents($tempFile, $response['body']);
        $mime = wp_get_image_mime($tempFile);
        unlink($tempFile);

        if ($mime === false || strpos($mime, 'image/') !== 0) {
            return new WP_Error('aui_invalid_file', 'AUI: File type is not image.');
        }

        $body = $response['body'];

        $image = [];
        $image['mime_type'] = $mime;
        $image['ext'] = self::getExtension($mime);
        $image['filename'] = $this->getFilename() . '.' . $image['ext'];
        $image['base_path'] = rtrim($this->getUploadDir('path'), DIRECTORY_SEPARATOR);
        $image['base_url'] = rtrim($this->getUploadDir('url'), '/');
        $image['path'] = $image['base_path'] . DIRECTORY_SEPARATOR . $image['filename'];
        $image['url'] = $image['base_url'] . '/' . $image['filename'];
        $c = 1;

        $sameFileExists = false;
        while (is_file($image['path'])) {
            if (sha1($response['body']) === sha1_file($image['path'])) {
                $sameFileExists = true;
                break;
            }

            $image['path'] = $image['base_path'] . DIRECTORY_SEPARATOR . $c . '_' . $image['filename'];
            $image['url'] = $image['base_url'] . '/' . $c . '_' . $image['filename'];
            $c++;
        }

        if ($sameFileExists) {
            return $image;
        }

        if (file_put_contents($image['path'], $body) === false || !is_file($image['path'])) {
            return new WP_Error('aui_image_save_failed', 'AUI: Image save to upload dir failed.');
        }

        $this->attachImage($image);

        if ($this->isNeedToResize() && ($resized = $this->resizeImage($image))) {
            $image['url'] = $resized['url'];
            $image['path'] = $resized['path'];
            $this->attachImage($image);
        }

        return $image;
    }

    /**
     * Attach image to post and media management
     * @param array $image
     * @return bool|int
     */
    public function attachImage($image)
    {
        $attachment = array(
            'guid' => $image['url'],
            'post_mime_type' => $image['mime_type'],
            'post_title' => $this->alt ?: preg_replace('/\.[^.]+$/', '', $image['filename']),
            'post_content' => '',
            'post_status' => 'inherit'
        );
        $attach_id = wp_insert_attachment($attachment, $image['path'], $this->post['ID']);
        if (!function_exists('wp_generate_attachment_metadata')) {
            include_once( ABSPATH . 'wp-admin/includes/image.php' );
        }
        $attach_data = wp_generate_attachment_metadata($attach_id, $image['path']);

        return wp_update_attachment_metadata($attach_id, $attach_data);
    }

    /**
     * Resize image and returns resized url
     * @param $image
     * @return false|array
     */
    public function resizeImage($image)
    {
        $width = WpAutoUpload::getOption('max_width');
        $height = WpAutoUpload::getOption('max_height');
        $image_resized = image_make_intermediate_size($image['path'], $width, $height);

        if (!$image_resized) {
            return false;
        }

        return array(
            'url' => $image['base_url'] . '/' . urldecode($image_resized['file']),
            'path' => $image['base_path'] . DIRECTORY_SEPARATOR . urldecode($image_resized['file']),
        );
    }

    /**
     * Check image need to resize or not
     * @return bool
     */
    public function isNeedToResize()
    {
        return WpAutoUpload::getOption('max_width') || WpAutoUpload::getOption('max_height');
    }

    /**
     * Returns Image file extension by mime type
     * @param $mime
     * @return string|null
     */
    public static function getExtension($mime)
    {
        $mimes = array(
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'image/bmp'  => 'bmp',
            'image/tiff' => 'tif',
            'image/webp' => 'webp',
        );

        return array_key_exists($mime, $mimes) ? $mimes[$mime] : null;
    }

    /**
     * @param $url
     * @return string
     */
    public static function normalizeUrl($url)
    {
        if (preg_match('/^\/\/.*$/', $url)) {
            return 'https:' . $url;
        }
        return $url;
    }
}
