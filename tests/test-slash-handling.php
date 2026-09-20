<?php

/**
 * Regression tests for slash handling in save()/savePost()/savePostMeta().
 *
 * The wp_insert_post_data filter receives SLASHED post data and wp_insert_post()
 * calls wp_unslash() on the filtered data right before writing to the database.
 * Any content returned from the filter must therefore stay slashed, otherwise
 * one level of backslashes is silently removed on every save. That corrupts
 * Gutenberg block attributes, which escape "--" as \u002d\u002d and "<>&" as
 * \u003c\u003e\u0026\u0022: after the slashes are dropped the markup literally
 * reads u002du002d and the block (and its CSS custom properties) breaks.
 */
class SlashHandlingTest extends WP_UnitTestCase
{
    /**
     * @var string Base url of local test image server
     */
    private static $baseUrl;

    public function setUp(): void
    {
        parent::setUp();

        if (self::$baseUrl === null) {
            $docRoot = sys_get_temp_dir() . '/aui-slash-test-' . getmypid();
            if (!is_dir($docRoot)) {
                mkdir($docRoot, 0777, true);
            }
            // 1x1 jpeg
            $jpeg = base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwcJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPDs0NDT/wAALCAABAAEBAREA/8QAFAABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFAEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwCdABmX/9k=');
            file_put_contents($docRoot . '/test.jpg', $jpeg);

            $port = 18098;
            exec('php -S 127.0.0.1:' . $port . ' -t ' . escapeshellarg($docRoot) . ' >/dev/null 2>&1 $cmd & echo $!', $out);
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

    public function tearDown(): void
    {
        // Reset plugin options between tests.
        WpAutoUpload::resetOptionsToDefaults();
        parent::tearDown();
    }

    /**
     * The exact markup WordPress' block serializer produces for a CSS custom
     * property attribute: "--" is escaped as \u002d\u002d.
     */
    private function gutenbergMarkup()
    {
        return '<!-- wp:generateblocks/container {"styles":"var(' . '\u002d\u002d' . 'gb-container-width)"} /-->'
            . '<img src="' . self::$baseUrl . '/test.jpg" alt="a test image" />';
    }

    /**
     * save() must return content in the same slashed state it arrived in, so the
     * caller (wp_insert_post_data) can hand it back to wp_insert_post() unchanged.
     */
    public function testSaveReturnsSlashedContent()
    {
        $wp_aui = new WpAutoUpload();

        $slashed = wp_slash($this->gutenbergMarkup());
        $postarr = array('ID' => 1, 'post_name' => 's', 'post_type' => 'post', 'post_content' => $slashed);

        $result = $wp_aui->save($postarr);

        $this->assertNotFalse($result, 'save() must return content when images are found');
        $this->assertSame($slashed, $result, 'save() must return slashed content when given slashed content');

        // What actually lands in the database after wp_insert_post() wp_unslash()es it.
        $this->assertSame($this->gutenbergMarkup(), wp_unslash($result));
    }

    /**
     * The full wp_insert_post_data filter path: what the filter returns is what
     * wp_insert_post() stores. The \u002d escapes must survive a save.
     */
    public function testSavePostPreservesGutenbergAttributeEscapes()
    {
        $wp_aui = new WpAutoUpload();

        $slashed = wp_slash($this->gutenbergMarkup());
        $data = array('post_content' => $slashed);
        $postarr = array('ID' => 1, 'post_name' => 's', 'post_type' => 'post', 'post_content' => $slashed);

        $filtered = $wp_aui->savePost($data, $postarr);

        $this->assertStringContainsString('\u002d\u002d', wp_unslash($filtered['post_content']));
        $this->assertStringNotContainsString('u002du002dgb-container-width', wp_unslash($filtered['post_content']));
    }

    /**
     * Custom fields go through get_post_meta (unslashed) and update_post_meta
     * (which wp_unslash()es its input). The round trip must be lossless too.
     */
    public function testSavePostMetaPreservesBackslashes()
    {
        $postId = $this->factory->post->create(array('post_type' => 'post'));
        $markup = 'var(' . '\u002d\u002d' . 'gb-container-width) <img src="' . self::$baseUrl . '/test.jpg" alt="a test image" />';
        update_post_meta($postId, 'custom_markup', $markup);

        $prop = new ReflectionProperty('WpAutoUpload', '_options');
        $prop->setAccessible(true);
        $prop->setValue(null, array('custom_fields' => array('custom_markup')));

        $wp_aui = new WpAutoUpload();
        $wp_aui->savePostMeta($postId, get_post($postId));

        $stored = get_post_meta($postId, 'custom_markup', true);
        $this->assertStringContainsString('\u002d\u002d', $stored, 'meta backslashes must survive the round trip');
        $this->assertStringNotContainsString('u002du002dgb', $stored);
    }
}
