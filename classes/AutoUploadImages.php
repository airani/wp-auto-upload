<?php

/**
 * @author Ali Irani <ali@irani.im>
 */
class AutoUploadImages
{
    public $post;

    public function __construct($post_id)
    {
        $this->post = get_post($post_id);
    }

    /**
     * Returns wordpress db handler
     * @return wpdb
     */
    public function getDb()
    {
        global $wpdb;
        return $wpdb;
    }

    /**
     * Find image urls in content and retrieve urls by array
     * @param $content
     * @return array|null
     */
    public function findAllImageUrls()
    {
        if ($content = $this->post->post_content) {
            $pattern = '/<img[^>]*src=["\']([^"\']*)[^"\']*["\'][^>]*>/i'; // find img tags and retrive src
            preg_match_all($pattern, $content, $urls, PREG_SET_ORDER);
            if (empty($urls)) {
                return null;
            }
            foreach ($urls as &$url) {
                $url = $url[1];
            }
            return array_unique($urls);
        }
        return null;
    }

    /**
     * Return host of $url without www
     * @param string $url
     * @return string host url
     */
    public static function getHostUrl($url = null)
    {
        $url = $url === null ? WpAutoUpload::getOption('base_url') : $url;
        $parsedUrl = parse_url($url); // Give base URL
        $url = isset($parsedUrl['port']) ? $parsedUrl['host'] . ":" . $parsedUrl['port'] : $parsedUrl['host'];
        $url = preg_split('/^(www(2|3)?\.)/i', $url, -1, PREG_SPLIT_NO_EMPTY); // Delete www from URL
        return $url[0];
    }

    /**
     * Check url is allowed to upload or not
     * @param string $url this url check is allowable or not
     * @param string $site_url host of site url
     * @return bool true | false
     */
    public function validateImageUrl($url)
    {
        $url = self::getHostUrl($url);
        $site_url = (self::getHostUrl() == null) ? self::getHostUrl(site_url('url')) : self::getHostUrl();

        if ($url === $site_url || empty($url)) {
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
     * Return custom image name with user rules
     * @param  string  $filename
     * @return string  custom file name
     */
    public function getImageName($filename)
    {
        preg_match('/(.*)?(\.+[^.]*)$/', $filename, $name_parts);

        $name = $name_parts[1];
        $postfix = $name_parts[2];

        if (preg_match('/^(\.[^?]*)\?.*/i', $postfix, $postfix_extra)) {
            $postfix = $postfix_extra[1];
        }

        $pattern_rule = WpAutoUpload::getOption('image_name');
        preg_match_all('/%[^%]*%/', $pattern_rule, $rules);

        $patterns = array(
            '%filename%' => $name,
            '%date%' => date('Y-m-j'),
            '%year%' => date('Y'),
            '%month%' => date('m'),
            '%day%' => date('j'),
            '%url%' => self::getHostUrl(get_bloginfo('url')),
            '%random%' => uniqid(),
            '%timestamp%' => time(),
            '%post_id%' => $this->post->ID,
            '%postname%' => $this->post->post_name,
        );

        if ($rules[0]) {
            foreach ($rules[0] as $rule) {
                $pattern_rule = preg_replace("/$rule/", $patterns[$rule] ? $patterns[$rule] : $rule, $pattern_rule);
            }
            return $pattern_rule . $postfix;
        }

        return $filename;
    }

    /**
     * Save image on wp_upload_dir
     * Add image to the media library and attach in the post
     * @param string $url image url
     * @param int $post_id
     * @return string new image url
     */
    public function saveImage($url, $post_id = 0)
    {
        if (function_exists('curl_init') === false) {
            return false;
        }

        setlocale(LC_ALL, "en_US.UTF8");
        $agent= 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.0.3705; .NET CLR 1.1.4322)';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_USERAGENT, $agent);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        $image_data = curl_exec($ch);

        if ($image_data === false) {
            return null;
        }

        $image_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        if (strpos($image_type,'image') === false) {
            return null;
        }

        $image_size = curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
        $image_file_name = basename($url);
        $image_name = $this->getImageName($image_file_name);
        $upload_dir = wp_upload_dir(date('Y/m', strtotime($this->post->post_date_gmt)));
        $image_path = urldecode($upload_dir['path'] . '/' . $image_name);
        $image_url = urldecode($upload_dir['url'] . '/' . $image_name);

        // check if file with same name exists in upload path, rename file
        while (file_exists($image_path)) {
            if ($image_size == filesize($image_path)) {
                return $image_url;
            } else {
                $num = rand(1, 99);
                $image_path = urldecode($upload_dir['path'] . '/' . $num . '_' . $image_name);
                $image_url = urldecode($upload_dir['url'] . '/' . $num . '_' . $image_name);
            }
        }

        curl_close($ch);
        file_put_contents($image_path, $image_data);

        // if set max width and height resize image
        if (WpAutoUpload::getOption('max_width') || WpAutoUpload::getOption('max_height')) {
            $width = WpAutoUpload::getOption('max_width');
            $height = WpAutoUpload::getOption('max_height');
            $image_resized = image_make_intermediate_size($image_path, $width, $height);
            $image_url = urldecode($upload_dir['url'] . '/' . $image_resized['file']);
        }

        $attachment = array(
            'guid' => $image_url,
            'post_mime_type' => $image_type,
            'post_title' => preg_replace('/\.[^.]+$/', '', $image_name),
            'post_content' => '',
            'post_status' => 'inherit'
        );
        $attach_id = wp_insert_attachment($attachment, $image_path, $post_id);
        $attach_data = wp_generate_attachment_metadata($attach_id, $image_path);
        wp_update_attachment_metadata($attach_id, $attach_data);

        return $image_url;
    }

    /**
     * Upload images and save new urls
     * @return bool
     */
    public function save()
    {
        $content = $this->post->post_content;
        $image_urls = $this->findAllImageUrls();

        if ($image_urls === null) {
            return false;
        }

        foreach ($image_urls as $image_url) {
            if ($this->validateImageUrl($image_url) && $new_image_url = $this->saveImage($image_url, $this->post->ID)) {
                // find image url in content and replace new image url
                $new_image_url = parse_url($new_image_url);
                $base_url = $this->getHostUrl() == null ? null : "http://{$this->getHostUrl()}";
                $new_image_url = $base_url . $new_image_url['path'];
                $content = preg_replace('/'. preg_quote($image_url, '/') .'/', $new_image_url, $content);
            }
        }

        return $this->getDb()->update(
            $this->getDb()->posts,
            array('post_content' => $content),
            array('ID' => $this->post->ID)
        ) ? true : false;
    }
}