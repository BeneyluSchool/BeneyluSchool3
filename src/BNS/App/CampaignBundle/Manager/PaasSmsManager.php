<?php
namespace BNS\App\CampaignBundle\Manager;

use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\PaasBundle\Manager\PaasManager;
use BNS\App\PaasBundle\Manager\PaasSecurityManager;
use Buzz\Browser;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class PaasSmsManager
{
    /** @var string  */
    protected $paasUrl;

    /** @var Browser  */
    protected $buzz;

    /** @var LoggerInterface  */
    protected $logger;

    /** @var PaasSecurityManager */
    protected $paasSecurityManager;

    public function __construct(LoggerInterface $logger, $paasUrl, Browser $buzz, PaasSecurityManager $paasSecurityManager)
    {
        $this->logger = $logger;
        $this->buzz = $buzz;
        $this->paasUrl = $paasUrl;
        $this->paasSecurityManager = $paasSecurityManager;
    }


    public function getSmsCredit(Group $group)
    {
        $url = $this->paasUrl . sprintf('/api/sms-credit/by-identifier/%s/%s.json', $group->getId(), $group->getType());

        $signedUrl = $this->paasSecurityManager->signUrl('GET', $url, [
            'country' => $group->getCountry() ?: null,
        ]);

        try {
            /** @var \Buzz\Message\Response $response */
            $response = $this->buzz->get($signedUrl);
            if ($response->isSuccessful()) {
                return json_decode($response->getContent());
            }
        } catch (\Exception $e) {
            $this->logger->error(sprintf('PaasManager::getSmsCredit failed: "%s"', $e->getMessage()), [
                'group' => $group->getId()
            ]);
        }

        return null;
    }

    /**
     *
     * @param string $message
     * @param array $recipients
     * @param int $groupId
     */
    public function send($message, array $recipients, $groupId)
    {
        $group = GroupQuery::create()
            ->joinWith('GroupType')
            ->findPk($groupId)
        ;
        if (!$group) {
            throw new NotFoundHttpException('Try to send sms with an invalid group');
        }

        $url = $this->paasUrl . sprintf('/api/sms-credit/spend/by-identifier/%s/%s.json', $group->getId(), $group->getType());
        $signedUrl = $this->paasSecurityManager->signUrl('POST', $url);

        $data = ['form' => [
            'message' => $message,
            'phone_numbers' => $recipients
        ]];

        /** @var \Buzz\Message\Response $response */
        $response = $this->buzz->post($signedUrl, [
            "Content-Type: application/json; charset=utf-8",
        ], json_encode($data));
        if ($response->isSuccessful()) {
            return json_decode($response->getContent());
        }
        // todo thow exception
        return $response;
    }

}
