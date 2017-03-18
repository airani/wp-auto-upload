<?php

/**
 * @author Ali Irani <ali@irani.im>
 */
class ImageUploader
{
    public $post;
    public $url;
    public $alt;
    public $filename;

    public function __construct($url, $alt, $post)
    {
        $this->post = $post;
        $this->url = $url;
        $this->alt = $alt;
    }

    /**
     * Return host of url simplified without www
     * @param null|string $url
     * @param bool $scheme
     * @return null|string
     */
    public static function getHostUrl($url = null, $scheme = false)
    {
        $url = $url ?: WpAutoUpload::getOption('base_url');

        $urlParts = parse_url($url);

        if (array_key_exists('host', $urlParts) === false) {
            return null;
        }

        $url = array_key_exists('port', $urlParts) ? $urlParts['host'] . ":" . $urlParts['port'] : $urlParts['host'];
        $urlSimplified = preg_split('/^(www(2|3)?\.)/i', $url, -1, PREG_SPLIT_NO_EMPTY)[0]; // Delete www from URL
        $url = $scheme && array_key_exists('scheme', $urlParts) ? $urlParts['scheme'] . '://' . $urlSimplified : $urlSimplified;

        return $url;
    }

    /**
     * Check url is allowed to upload or not
     * @param string $url this url check is allowable or not
     * @param string $site_url host of site url
     * @return bool
     */
    public function validate()
    {
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
     * Return custom image name with user rules
     * @return string Custom file name
     */
    public function getFilename()
    {
        $filename = basename($this->url);
        preg_match('/(.*)?(\.+[^.]*)$/', $filename, $name_parts);

        $this->filename = $name_parts[1];
        $postfix = $name_parts[2];

        if (preg_match('/^(\.[^?]*)\?.*/i', $postfix, $postfix_extra)) {
            $postfix = $postfix_extra[1];
        }

        $filename = $this->patterned(WpAutoUpload::getOption('image_name'));
        return $filename . $postfix;
    }

    /**
     * Return custom alt name with user rules
     * @return string Custom alt name
     */
    public function getAlt()
    {
        return $this->patterned(WpAutoUpload::getOption('alt_name'));
    }

    /**
     * Returns string patterned
     * @param $pattern
     * @return string
     */
    public function patterned($pattern)
    {
        preg_match_all('/%[^%]*%/', $pattern, $rules);

        $patterns = array(
            '%filename%' => $this->filename,
            '%image_alt%' => $this->alt,
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
                $pattern = preg_replace("/$rule/", $patterns[$rule] ? $patterns[$rule] : $rule, $pattern);
            }
        }
        return $pattern;
    }

    /**
     * Save image on wp_upload_dir
     * Add image to the media library and attach in the post
     * @return bool
     */
    public function save()
    {
        if (function_exists('curl_init') === false) {
            return false;
        }

        setlocale(LC_ALL, "en_US.UTF8");
        $agent= 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.0.3705; .NET CLR 1.1.4322)';
        $ch = curl_init($this->url);
        curl_setopt($ch, CURLOPT_USERAGENT, $agent);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        $image_data = curl_exec($ch);

        if ($image_data === false) {
            return false;
        }

        $image_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        if (strpos($image_type,'image') === false) {
            return false;
        }

        $image_size = curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
        $image_name = $this->getFilename();
        $upload_dir = wp_upload_dir(date('Y/m', strtotime($this->post->post_date_gmt)));
        $image_path = urldecode($upload_dir['path'] . '/' . $image_name);
        $image_url = urldecode($upload_dir['url'] . '/' . $image_name);

        // check if file with same name exists in upload path, rename file
        while (file_exists($image_path)) {
            if ($image_size == filesize($image_path)) {
                $this->url = $image_url;
                return true;
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
            'post_title' => $this->alt ?: preg_replace('/\.[^.]+$/', '', $image_name),
            'post_content' => '',
            'post_status' => 'inherit'
        );
        $attach_id = wp_insert_attachment($attachment, $image_path, $this->post->ID);
        $attach_data = wp_generate_attachment_metadata($attach_id, $image_path);
        wp_update_attachment_metadata($attach_id, $attach_data);
        $this->url = $image_url;
        return true;
    }
}
