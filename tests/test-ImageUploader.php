<?php

class ImageUploaderTest extends WP_UnitTestCase
{
    /**
     * @var ImageUploader
     */
    public $imageUploader;

    public function setUp()
    {
        parent::setUp();

        $samplePost = array(
            'ID' => 1,
            'post_name' => 'sample',
        );

        $this->imageUploader = new ImageUploader('https://irani.im/images/ali-irani.jpg', 'sample alt', $samplePost);
    }

    public function testGetHostUrl()
    {
        $host = ImageUploader::getHostUrl('https://www.irani.im/test');
        $this->assertEquals('irani.im', $host);

        $host = ImageUploader::getHostUrl('https://www.irani.im/test', true);
        $this->assertEquals('https://irani.im', $host);
    }

    public function testValidate()
    {
        $this->imageUploader->url = 'https://irani.im/images/ali-irani.jpg';
        $this->assertTrue($this->imageUploader->validate());

        $this->imageUploader->url = 'https://example.org/sample.jpg';
        $this->assertFalse($this->imageUploader->validate());
    }

    public function testGetFilename()
    {
        $this->imageUploader->url = 'https://irani.im/images/ali-irani.jpg?a=b&d=c';
        $result = $this->invokeMethod($this->imageUploader, 'getFilename');
        $this->assertEquals('ali-irani', $result);

        $this->imageUploader->url = 'https://irani.im/images/get.php?file=ali.jpg';
        $result = $this->invokeMethod($this->imageUploader, 'getFilename');
        $this->assertStringMatchesFormat('img_%s', $result);
    }

    public function testGetOriginalFilename()
    {
        $this->imageUploader->url = 'https://irani.im/images/ali-irani.jpg';
        $result = $this->invokeMethod($this->imageUploader, 'getOriginalFilename');
        $this->assertEquals('ali-irani', $result);

        $this->imageUploader->url = 'https://irani.im/images/ali-irani.php';
        $result = $this->invokeMethod($this->imageUploader, 'getOriginalFilename');
        $this->assertNull($result);
    }

    public function testGetUploadDir()
    {
        $result = $this->invokeMethod($this->imageUploader, 'getUploadDir', array('url'));
        $this->assertStringMatchesFormat('http://example.org/wp-content/uploads/%d/%d', $result);

        $result = $this->invokeMethod($this->imageUploader, 'getUploadDir', array('path'));
        $this->assertStringMatchesFormat('%s%ewp-content%euploads%e%d%e%d', $result);
    }

    public function testGetAlt()
    {
        $this->assertEquals('sample alt', $this->imageUploader->getAlt());
    }

    public function testResolvePattern()
    {
        $this->assertEquals('ali-irani', $this->imageUploader->resolvePattern('%filename%'));
        $this->assertEquals('sample alt', $this->imageUploader->resolvePattern('%image_alt%'));
        $this->assertEquals(date('Y-m-j'), $this->imageUploader->resolvePattern('%date%'));
        $this->assertEquals(date('Y'), $this->imageUploader->resolvePattern('%year%'));
        $this->assertEquals(date('m'), $this->imageUploader->resolvePattern('%month%'));
        $this->assertEquals(date('j'), $this->imageUploader->resolvePattern('%day%'));
        $this->assertStringMatchesFormat('%ali%', $this->imageUploader->resolvePattern('%ali%'));
    }

    /**
     * @dataProvider imageUrlsProvider
     */
    public function testDownloadImageSuccessfully($url, $filenamePattern, $mime_type, $extension)
    {
        $this->imageUploader->url = $url;
        $result = $this->imageUploader->downloadImage($url);
        $this->assertTrue(is_array($result));
        $this->assertArrayHasKey('url', $result);
        $this->assertStringMatchesFormat('http://example.org/wp-content/uploads/%d/%d/%s.%s', $result['url']);
        $this->assertStringMatchesFormat($filenamePattern, $result['filename']);
        $this->assertEquals($mime_type, $result['mime_type']);
        $this->assertEquals($extension, $result['ext']);
    }

    public function imageUrlsProvider()
    {
        return array(
            array('https://irani.im/images/ali-irani.jpg', 'ali-irani.jpg', 'image/jpeg', 'jpg'),
            array('https://d418bv7mr3wfv.cloudfront.net/s3/W1siZiIsIjIwMTgvMTAvMTEvMjMvMDIvNTUvMjQxL1dlIGFzayBxdWVzdGlvbnMgdGhhdCBjcmVhdGUgbWFnaWNhbCBjb25uZWN0aW9ucyAoNCkucG5nIl1d', 'img_%s.png', 'image/png', 'png'),
            array('https://www.geocaching.com/help/index.php?pg=file&from=2&id=760', 'img_%s.jpg', 'image/jpeg', 'jpg'),
            array('https://images.unsplash.com/photo-1511988617509-a57c8a288659?ixlib=rb-0.3.5&ixid=eyJhcHBfaWQiOjI1NDQxfQ&s=e91ec3d695b7e406f29dd80a706fc0ad&w=1000', 'img_%s.jpg', 'image/jpeg', 'jpg'),
        );
    }

    public function testDownloadImageFailed()
    {
        $result = $this->imageUploader->downloadImage('https://irani.im/images/ali-irani.php');
        $this->assertWPError($result);
    }

    public function testResizeImage()
    {
        if (!wp_image_editor_supports()) {
            $this->assertTrue(true);
            return;
        }

        $wpAutoUpload = new WpAutoUpload();
        $_POST['submit'] = true;
        $_POST['max_width'] = 50;
        $_POST['max_height'] = 50;
        ob_start();
        $wpAutoUpload->settingPage();
        ob_end_clean();
        $this->imageUploader->url = 'https://irani.im/images/ali-irani.jpg';
        $result = $this->imageUploader->downloadImage('https://irani.im/images/ali-irani.jpg');
        $this->assertTrue(is_array($result));
        $this->assertStringMatchesFormat('%s-50x50.jpg', $result['path']);
        $this->assertStringMatchesFormat('%s-50x50.jpg', $result['url']);
        $this->assertFileExists($result['path']);

        $this->assertTrue(WpAutoUpload::resetOptionsToDefaults());
        $this->assertNull(WpAutoUpload::getOption('max_width'));
    }

    /**
     * Call protected/private method of a class.
     *
     * @param object &$object    Instantiated object that we will run method on.
     * @param string $methodName Method name to call
     * @param array  $parameters Array of parameters to pass into method.
     *
     * @return mixed Method return.
     */
    public function invokeMethod(&$object, $methodName, array $parameters = array())
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
