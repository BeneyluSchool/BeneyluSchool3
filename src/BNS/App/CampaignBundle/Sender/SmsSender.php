<?php
namespace BNS\App\CampaignBundle\Sender;

use BNS\App\CampaignBundle\Manager\PaasSmsManager;
use BNS\App\CampaignBundle\Model\Campaign;
use BNS\App\CampaignBundle\Model\CampaignSms;
use BNS\App\CoreBundle\Model\User;
use Psr\Log\LoggerInterface;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class SmsSender implements CampaignSenderInterface
{
    // 140 8bit char (allow spécial char lize @é€)
    const SMS_SIZE = 140;


    protected $logger;

    protected $paasSmsManager;

    public function __construct(LoggerInterface $logger, PaasSmsManager $paasSmsManager)
    {
        $this->logger = $logger;
        $this->paasSmsManager = $paasSmsManager;
    }

    /**
     * @inheritDoc
     */
    public function send(Campaign $campaign, $users)
    {
        $message = $campaign->getMessage();

        if (!$this->validateSmsSize($message)) {
            throw new \Exception(sprintf('Error SmsSender message of campaign "%s" is too long (more than 10 sms)', $campaign->getId()));
        }

        $numbers = [];

        /** @var User $user */
        foreach ($users as $user) {
            if ($user->getPhone()) {
                // TODO validate phone number
                $numbers[] = $user->getPhone();
            } else {
                // TODO handle error
            }
        }

        if ($this->paasSmsManager) {
            return $this->paasSmsManager->send($message, $numbers, $campaign->getGroupId());
        }


        return false;
    }

    /**
     * @inheritDoc
     */
    public function support(Campaign $campaign)
    {
        return $campaign instanceOf CampaignSms;
    }


    protected function validateSmsSize($message)
    {
        if (mb_strlen($message) < 10 * self::SMS_SIZE) {
            return true;
        }

        return false;
    }
}
