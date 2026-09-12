<?php

class SsrfProtectionTest extends WP_UnitTestCase
{
    public function testIsIpSafeBlocksPrivateAndReservedRanges()
    {
        $blocked = array(
            '127.0.0.1', '10.0.0.5', '192.168.1.1', '172.16.0.1',
            '169.254.169.254', '0.0.0.0', '0.1.2.3', '100.64.0.1',
            '224.0.0.1', '239.1.1.1', '240.0.0.1', '255.255.255.255',
            '::1', '::ffff:127.0.0.1', '::ffff:169.254.169.254',
            'fe80::1', 'fec0::1', 'fc00::1', 'fd12::1', 'ff00::1', 'ff02::1',
        );
        foreach ($blocked as $ip) {
            $this->assertFalse(ImageUploader::isIpSafe($ip), "IP $ip should be blocked");
        }

        $allowed = array(
            '93.184.216.34',
            '2606:2800:220:1:248:1893:25c8:1946',
            '2a00:1450:4001:81::200e',
        );
        foreach ($allowed as $ip) {
            $this->assertTrue(ImageUploader::isIpSafe($ip), "IP $ip should be allowed");
        }
    }

    public function testValidateBlocksSsrfTargets()
    {
        $samplePost = array('ID' => 1, 'post_name' => 'sample');
        $urls = array(
            'http://127.0.0.1/image.jpg',
            'http://192.168.1.100/logo.png',
            'http://10.0.0.5/pic.gif',
            'http://169.254.169.254/latest/meta-data/',
            'https://[::1]/avatar.jpg',
            'file:///etc/passwd',
            'ftp://example.com/image.jpg',
            'gopher://127.0.0.1:80/x',
        );
        foreach ($urls as $url) {
            $uploader = new ImageUploader($url, 'test', $samplePost);
            $this->assertFalse($uploader->validate(), "URL $url should be blocked");
        }
    }
}
