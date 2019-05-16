<?php

class WpAutoUploadTest extends WP_UnitTestCase
{
    public function testFindAllImageUrls() {
        $content = "Hello World!\n";
        $content .= "<img src=\"https://irani.im/images/ali-irani.jpg\" title='sample title' alt='Ali Irani' /> This is my picture.\n";
        $content .= "another image like this <img src='http://example.org/image.php?name=sample.jpg'>";
        $content .= "srcset test <img class=\"wp-image-50084\" src=\"https://irani.im/image/w800/test.jpg\" sizes=\"(max-width: 1024px) 100vw, 1024px\" srcset=\"https://irani.im/image/w600/test.jpg 600w, https://irani.im/image/w300/test.jpg 300w, https://irani.im/image/w400/test.jpg 400w, https://irani.im/image/w200/test.jpg 200w\" alt=\"\" />";
        $content .= "srcset2 test <img class=\"wp-image-50084\" src=\"https://irani.im/image/w800/test-b.jpg\" sizes=\"(max-width: 1024px) 100vw, 1024px\" srcset=\"https://irani.im/image/w600/test-b.jpg 600w, https://irani.im/image/w300/test-b.jpg 300w, https://irani.im/image/w400/test-b.jpg 400w, https://irani.im/image/w200/test-b.jpg 200w\" alt=\"\" />";

        $wp_aui = new WpAutoUpload();
        $results = $wp_aui->findAllImageUrls($content);

        $this->assertTrue(is_array($results));
        $this->assertEquals(12, count($results));

        $results = $wp_aui->findAllImageUrls('');
        $this->assertEquals(0, count($results));
    }
}
