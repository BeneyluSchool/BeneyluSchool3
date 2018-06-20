<?php
namespace BNS\App\MediaLibraryBundle\Tests\Download;

use BNS\App\CoreBundle\Test\AppWebTestCase;
use BNS\App\MediaLibraryBundle\Download\MediaDownloadValidator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class MediaDownloadValidatorTest extends AppWebTestCase
{
    public function testInvalidRequest()
    {
        $validator = $this->getMediaDownloadValidator();

        $this->assertFalse($validator->validateUrl());

        $this->assertFalse($validator->validateUrl(new Request()));

        $this->assertFalse((new MediaDownloadValidator('123456', new Request()))->validateUrl());
    }

    public function testInvalidSecretRequest()
    {
        $validator = $this->getMediaDownloadValidator();

        $this->assertFalse($validator->validateUrl());

        $this->assertFalse($validator->validateUrl(new Request()));
    }

    public function testExpiredRequest()
    {
        $validator = $this->getMediaDownloadValidator();

        $this->assertFalse($validator->validateUrl(new Request(['temp_url_expires' => time() - 10000, 'temp_url_sig' => 'super_hash'])));
    }

    public function testNoHashRequest()
    {
        $validator = $this->getMediaDownloadValidator();

        $this->assertFalse($validator->validateUrl(new Request(['temp_url_expires' => time()])));
    }

    public function testValidRequest()
    {
        $validator = $this->getMediaDownloadValidator();

        $expiry = time();

        $hash = hash_hmac('sha1', sprintf("%s\n%d\n%s", 'GET', $expiry, '?'), '123456');

        $request = new Request(['temp_url_expires' => $expiry, 'temp_url_sig' => $hash]);

        $this->assertTrue($validator->validateUrl($request));
    }

    public function testInvalidKeyRequest()
    {
        $validator = $this->getMediaDownloadValidator();

        $expiry = time();

        $hash = hash_hmac('sha1', sprintf("%s\n%d\n%s", 'GET', $expiry, '?'), '123457');

        $request = new Request(['temp_url_expires' => $expiry, 'temp_url_sig' => $hash]);

        $this->assertFalse($validator->validateUrl($request));
    }


    protected function getMediaDownloadValidator()
    {
        return new MediaDownloadValidator('123456');
    }
}
