<?php
namespace BNS\App\MediaLibraryBundle\Download;

use Symfony\Component\HttpFoundation\Request;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class MediaDownloadValidator
{
    protected $secret;

    protected $request;


    public function __construct($secret, Request $request = null)
    {
        $this->secret = $secret;
        $this->request = $request;
    }

    public function validateUrl(Request $request = null)
    {
        if (null === $request) {
            $request = $this->request;
        }
        if (null === $request) {
            return false;
        }

        $path = substr($request->getScriptName(), strlen("/ent"));
        $params = $request->query->all();
        $hash = $request->get('temp_url_sig');
        $expiry = (int)$request->get('temp_url_expires', 0);

        if ($expiry < time() || !$hash) {
            return false;
        }

        unset($params['temp_url_sig']);
        unset($params['temp_url_expires']);
        // exclude size from signature
        unset($params['size']);
        ksort($params);

        $urlPath = $path . '?' . http_build_query($params);
        $body = sprintf("%s\n%d\n%s", 'GET', $expiry, $urlPath);

        return hash_hmac('sha1', $body, $this->secret) === $hash;
    }
}
