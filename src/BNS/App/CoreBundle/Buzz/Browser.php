<?php
namespace BNS\App\CoreBundle\Buzz;

use Buzz\Browser as BaseBrowser;
use Buzz\Message\MessageInterface;
use Buzz\Message\RequestInterface;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class Browser extends BaseBrowser
{
    /**
     * @var int
     */
    protected $maxTries;

    /**
     * Sends a request.
     *
     * @param RequestInterface $request  A request object
     * @param MessageInterface $response A response object
     * @param int $maxTries the number of retry
     *
     * @return MessageInterface The response
     */
    public function send(RequestInterface $request, MessageInterface $response = null, $maxTries = null)
    {

        $this->getClient()->setTimeout(1200);

        $try = 0;
        $maxTries = (int) $maxTries ?: $this->getMaxTries();

        if ($maxTries < 1) {
            $maxTries = 1;
        }

        while ($try < $maxTries) {
            $try++;
            try {
                return parent::send($request, $response);
            } catch (\Exception $e) {
                if ($try >= $maxTries) {
                    throw $e;
                }
                sleep(1);
            }
        }

        return null;
    }

    /**
     * @return int
     */
    public function getMaxTries()
    {
        return $this->maxTries;
    }

    /**
     * @param int $maxTries
     *
     * @return Browser
     */
    public function setMaxTries($maxTries)
    {
        $this->maxTries = $maxTries;

        return $this;
    }
}
