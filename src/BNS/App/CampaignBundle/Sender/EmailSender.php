<?php
namespace BNS\App\CampaignBundle\Sender;

use BNS\App\CampaignBundle\Model\Campaign;
use BNS\App\CampaignBundle\Model\CampaignEmail;
use BNS\App\MediaLibraryBundle\Manager\MediaManager;
use BNS\App\MediaLibraryBundle\Model\Media;
use BNS\App\MediaLibraryBundle\Parser\PublicMediaParser;
use Psr\Log\LoggerInterface;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class EmailSender implements CampaignSenderInterface
{
    /**
     * @var array
     */
    protected $options;

    /**
     * @var MediaManager
     */
    protected $mediaManager;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var \Swift_Mailer
     */
    protected $mailer;

    /**
     * @var PublicMediaParser
     */
    protected $mediaParser;

    /**
     * @var \HTMLPurifier
     */
    protected $purifier;

    /**
     * max duration for embedded link in email (default: 5 Years)
     * @var int
     */
    protected $linkDuration = 157680000;

    public function __construct(
        \Swift_Mailer $mailer,
        MediaManager $mediaManager,
        LoggerInterface $logger,
        \HTMLPurifier $purifier,
        PublicMediaParser $mediaParser,
        array $options = array()
    ) {
        $this->mailer = $mailer;
        $this->mediaManager = $mediaManager;
        $this->logger = $logger;
        $this->mediaParser = $mediaParser;
        $this->purifier = $purifier;

        $this->options = array_merge([
            'sender' => 'support@beneylu.com',
            'sender_name' => 'Beneylu',
            'reply_to' => 'support@beneylu.com',
            'reply_to_name' => 'Beneylu',
        ], $options);
    }

    /**
     * @inheritDoc
     */
    public function send(Campaign $campaign, $users)
    {
        $swiftMessage = $this->buildSwiftMessage($campaign);

        $count = 0;
        foreach ($users as $user) {
            $emailAddress = $user->getNotificationEmail();
            if ($emailAddress) {
                $swiftMessage->setTo(array($emailAddress));

                if ($this->mailer->send($swiftMessage)) {
                    $count++;
                } else {
                    // TODO handle failure
                }
            }
        }

        return $count;
    }

    /**
     * @inheritDoc
     */
    public function support(Campaign $campaign)
    {
        return $campaign instanceOf CampaignEmail;
    }

    /**
     * @param Campaign $campaign
     * @return \Swift_Message
     */
    protected function buildSwiftMessage(Campaign $campaign)
    {
        $subject = $campaign->getTitle();

        $message = $campaign->getMessage();
        $plainMessage = strip_tags($message);
        // TODO purify + link to embedded media
        $htmlMessage = $this->mediaParser->parse($this->purifier->purify($message), false, 'medium', false, $this->linkDuration);

        $swiftMessage = new \Swift_Message($subject);

        // Set sender / reply to
        $swiftMessage->setFrom(array($this->options['sender'] => $this->options['sender_name']));
        $swiftMessage->setReplyTo(array($this->options['reply_to'] => $this->options['reply_to_name']));

        $swiftMessage->setBody($plainMessage);
        $swiftMessage->addPart($htmlMessage, 'text/html');

        /** @var Media $media */
        foreach ($campaign->getResourceAttachments() as $media) {
            try {
                $this->mediaManager->setMediaObject($media);
                $data = $this->mediaManager->read();
                if (false !== $data) {
                    $attachment = \Swift_Attachment::newInstance()
                        ->setFilename($media->getFilename())
                        ->setContentType($media->getFileMimeType())
                        ->setBody($data);

                    $swiftMessage->attach($attachment);
                }
            } catch (\Exception $e) {
                $this->logger->error(sprintf('Campaign EmailSender cannot attach file "%s" for campaign "%s" : error "%s"', $media->getFilename(), $campaign->getId(), $e->getMessage()));
            }
        }

        return $swiftMessage;
    }


}
