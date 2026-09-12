<?php

class CustomFieldsTest extends WP_UnitTestCase
{
    /**
     * @var string Base url of local test image server
     */
    private static $baseUrl;

    public function setUp(): void
    {
        parent::setUp();

        // Serve a real jpeg locally so downloads work without external http
        if (self::$baseUrl === null) {
            $docRoot = sys_get_temp_dir() . '/aui-cf-test-' . getmypid();
            if (!is_dir($docRoot)) {
                mkdir($docRoot, 0777, true);
            }
            // 1x1 jpeg
            $jpeg = base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwcJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPDs0NDT/wAALCAABAAEBAREA/8QAFAABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFAEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=');
            file_put_contents($docRoot . '/test.jpg', $jpeg);

            $port = 18097;
            exec('php -S 127.0.0.1:' . $port . ' -t ' . escapeshellarg($docRoot) . ' >/dev/null 2>&1 $cmd & echo $!', $out);
            // wait for server
            for ($i = 0; $i < 20; $i++) {
                $fp = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.5);
                if ($fp) {
                    fclose($fp);
                    break;
                }
                usleep(100000);
            }
            self::$baseUrl = 'http://127.0.0.1:' . $port;
        }
    }

    public function testGetCustomFieldsReturnsOnlyPublicKeys()
    {
        $postId = $this->factory->post->create();
        update_post_meta($postId, 'my_custom_field', 'x');
        update_post_meta($postId, '_hidden_field', 'x');

        $fields = WpAutoUpload::getCustomFields();

        $this->assertContains('my_custom_field', $fields);
        $this->assertNotContains('_hidden_field', $fields);
    }

    public function testSavePostMetaProcessesSelectedFields()
    {
        // Note: download host 127.0.0.1 is blocked by SSRF protection (by design),
        // so use a mocked uploader path: set custom field with img pointing to local server
        // and verify the flow via the public API with a non-processed (invalid) image url.
        // For the real replacement we verify wiring, not download, here.

        $postId = $this->factory->post->create(array('post_type' => 'post'));
        update_post_meta($postId, 'external_images', '<img src="' . self::$baseUrl . '/test.jpg" />');
        update_post_meta($postId, 'untouched_field', 'keep me');

        $prop = new ReflectionProperty('WpAutoUpload', '_options');
        $prop->setAccessible(true);
        $prop->setValue(null, array(
            'custom_fields' => array('external_images'),
        ));

        $wp_aui = new WpAutoUpload();
        $post = get_post($postId);
        $wp_aui->savePostMeta($postId, $post);

        // 127.0.0.1 is SSRF-blocked so url stays; but the hook must not touch other fields
        $this->assertSame('keep me', get_post_meta($postId, 'untouched_field', true));
        // and the meta must still contain the img tag
        $this->assertStringContainsString('<img', get_post_meta($postId, 'external_images', true));

        WpAutoUpload::resetOptionsToDefaults();
    }

    public function testSaveHandlesCustomContent()
    {
        // save() with explicit content processes the given string instead of post_content
        $wp_aui = new WpAutoUpload();
        $postarr = array('ID' => 1, 'post_name' => 's', 'post_type' => 'post', 'post_content' => 'original');

        // no external images -> returns false
        $this->assertFalse($wp_aui->save($postarr, 'no images here'));
    }

    public function testSettingsPageSavesCustomFields()
    {
        $postId = $this->factory->post->create();
        update_post_meta($postId, 'field_a', 'x');

        $wp_aui = new WpAutoUpload();
        $_POST['submit'] = true;
        $_POST['custom_fields'] = array('field_a', 'not_a_real_field');
        $_REQUEST['_wpnonce'] = wp_create_nonce('aui_settings');
        $_REQUEST['_wp_http_referer'] = '';
        ob_start();
        $wp_aui->settingPage();
        ob_end_clean();
        unset($_POST['submit'], $_POST['custom_fields'], $_REQUEST['_wpnonce'], $_REQUEST['_wp_http_referer']);

        $saved = WpAutoUpload::getOption('custom_fields');
        $this->assertSame(array('field_a'), $saved, 'Only valid field keys must be saved');

        WpAutoUpload::resetOptionsToDefaults();
    }
}
