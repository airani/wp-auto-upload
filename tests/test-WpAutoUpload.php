<?php

class WpAutoUploadTest extends WP_UnitTestCase
{
    public function testFindAllImageUrls() {
        $content = "Hello World!\n";
        $content .= "<img src=\"https://irani.im/images/ali-irani.jpg\" title='sample title' alt='Ali Irani' /> This is my picture.\n";
        $content .= "another image like this <img src='http://example.org/image.php?name=sample.jpg'>";

        $wp_aui = new WpAutoUpload();
        $results = $wp_aui->findAllImageUrls($content);

        $this->assertTrue(is_array($results));
        $this->assertEquals(2, count($results));

        $results = $wp_aui->findAllImageUrls('');
        $this->assertEquals(0, count($results));
    }
}
