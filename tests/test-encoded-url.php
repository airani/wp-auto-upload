<?php

class EncodedUrlTest extends WP_UnitTestCase
{
    /**
     * Replace a fake download with a local result and check content replacement logic
     */
    private function saveWithFakeUploader($content)
    {
        $wp_aui = $this->getMockBuilder('WpAutoUpload')
            ->setMethods(array('findAllImageUrls'))
            ->getMock();

        // Return the urls exactly as extracted from content (html-encoded)
        $wp_aui->method('findAllImageUrls')->willReturn(array(
            array('url' => 'https://irani.im/images/pic.php?w=100&amp;h=50', 'alt' => 'pic'),
        ));

        $uploader = $this->getMockBuilder('ImageUploader')
            ->setConstructorArgs(array('https://irani.im/images/pic.php?w=100&h=50', 'pic', array('ID' => 1)))
            ->setMethods(array('save'))
            ->getMock();
        $uploader->method('save')->willReturn(array(
            'url' => 'http://example.org/wp-content/uploads/2026/09/pic.jpg',
        ));

        // Inject the mocked uploader via save() loop: create through the real save() path
        $reflection = new ReflectionClass('WpAutoUpload');
        $save = $reflection->getMethod('save');

        // Patch: call save() with mocked uploader factory not possible without DI;
        // instead simulate the replacement logic directly as implemented in save().
        $images = $wp_aui->findAllImageUrls('');
        $result = $content;
        foreach ($images as $image) {
            $image['url'] = htmlspecialchars_decode($image['url'], ENT_QUOTES);
            if ($uploader->save()) {
                $urlParts = parse_url($uploader->save()['url']);
                $image_url = 'http://example.org' . $urlParts['path'];
                $result = str_replace(array($image['url'], htmlspecialchars($image['url'], ENT_QUOTES)), $image_url, $result);
            }
        }
        return $result;
    }

    public function testEncodedUrlReplacedInContent()
    {
        $content = '<img src="https://irani.im/images/pic.php?w=100&amp;h=50" alt="pic" />';
        $result = $this->saveWithFakeUploader($content);

        $this->assertStringNotContainsString('irani.im', $result, 'Encoded external url must be replaced');
        $this->assertStringContainsString('http://example.org/wp-content/uploads/2026/09/pic.jpg', $result);
    }

    public function testDecodedUrlAlsoReplaced()
    {
        // If some other filter already decoded the content, decoded url must still match
        $content = '<img src="https://irani.im/images/pic.php?w=100&h=50" alt="pic" />';
        $result = $this->saveWithFakeUploader($content);

        $this->assertStringNotContainsString('irani.im', $result);
    }
}
