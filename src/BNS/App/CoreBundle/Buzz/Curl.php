<?php
namespace BNS\App\CoreBundle\Buzz;

use Buzz\Client\Curl as BaseCurl;
use Buzz\Message\MessageInterface;
use Buzz\Message\RequestInterface;

/**
 * @author Eymeric Taelman
 */
class Curl extends BaseCurl
{
    public function send(RequestInterface $request, MessageInterface $response, array $options = array())
    {
        $options[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
        try{
            return parent::send($request,$response,$options);
        }catch(\Exception $e)
        {
            $this->send($request,$response,$options);
        }

    }
}
