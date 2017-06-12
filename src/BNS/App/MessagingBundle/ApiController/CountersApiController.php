<?php

namespace BNS\App\MessagingBundle\ApiController;

use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use FOS\RestBundle\Controller\Annotations as Rest;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class CountersApiController
 *
 * @package BNS\App\MessagingBundle\ApiController
 */
class CountersApiController extends BaseMessagingApiController
{

    /**
     * @ApiDoc(
     *  section="Messagerie - Compteurs",
     *  resource=true,
     *  description="Compteurs des types de messages",
     *  requirements = {},
     *  statusCodes = {
     *      204 = "Ok",
     *  }
     * )
     * @Rest\Get("")
     * @Rest\View()
     *
     * @RightsSomeWhere("MESSAGING_ACCESS")
     *
     * @return Response
     */
    public function getAction()
    {
        $messageManager = $this->get('bns.message_manager');

        return [
            'drafts' => $messageManager->getDraftMessages(0, true),
            'unread' => $messageManager->getMessagesConversationsByStatus('NONE_READ', 0, true),
        ];
    }

}
