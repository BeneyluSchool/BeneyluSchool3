<?php

namespace BNS\App\PaasBundle\Manager;

use Psr\Log\LoggerInterface;
use Buzz\Browser;

class PaasWithoutRequestManager
{


    /** @var  string */
    protected $paasUrl;

    /** @var Browser $buzz */
    protected $buzz;

    /** @var LoggerInterface */
    protected $logger;

    /** @var PaasSecurityManager */
    protected $paasSecurityManager;

    public function __construct(
        $paasUrl,
        $buzz,
        LoggerInterface $logger,
        PaasSecurityManager $paasSecurityManager
    ) {
        $this->paasUrl = $paasUrl;
        $this->moveSubscriptionsUrl = $paasUrl . '/api/subscriptions/move';
        $this->buzz = $buzz;
        $this->logger = $logger;
        $this->paasSecurityManager = $paasSecurityManager;
    }

    public function moveClassroomSubscriptions($oldGroupId, $newGroupId)
    {
        return $this->moveGroupSubscriptions($oldGroupId, $newGroupId, 'classroom');
    }

    public function moveGroupSubscriptions($oldGroupId, $newGroupId, $type)
    {
        $url = $this->moveSubscriptionsUrl . sprintf('/%s', $type);
        $signedUrl = $this->paasSecurityManager->signUrl('POST', $url);

        try {
            $data = [
                'old_identifier' => $oldGroupId,
                'new_identifier' => $newGroupId,
            ];

            /** @var \Buzz\Message\Response $response */
            $response = $this->buzz->post($signedUrl, [
                "Content-Type: application/json; charset=utf-8",
            ], json_encode($data));
            if ($response->isSuccessful()) {
                return json_decode($response->getContent());
            }
        } catch (\Exception $e) {
            $this->logger->error(sprintf('PaasManager::moveGroupSubscriptions failed: "%s"', $e->getMessage()), [
                'old_identifier' => $oldGroupId,
                'new_identifier' => $newGroupId,
                'type' => $type,
            ]);
        }

        return null;
    }

}
