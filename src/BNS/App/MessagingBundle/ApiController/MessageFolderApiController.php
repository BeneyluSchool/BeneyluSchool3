<?php

namespace BNS\App\MessagingBundle\ApiController;

use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use BNS\App\MessagingBundle\Messaging\BNSMessageManager;
use BNS\App\MessagingBundle\Model\MessagingMessageQuery;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcherInterface;
use FOS\RestBundle\Util\Codes;
use Hateoas\Configuration\Route;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\Response;

class MessageFolderApiController extends BaseMessagingApiController
{

    /**
     * @ApiDoc(
     *  section="Messagerie - Dossier de messages",
     *  resource=true,
     *  description="Récupère le contenu d'une des boites de la messagerie, sous forme de conversations ou de messages",
     *  requirements = {},
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Les données soumises sont invalides",
     *      404 = "La boite n'a pas été trouvée"
     *  }
     * )
     * @Rest\QueryParam(name="page", requirements="\d+", description="current page", default="1")
     * @Rest\QueryParam(name="limit", requirements="\d+", description="number of elements per page", default="10")
     * @Rest\QueryParam(name="search", requirements="\w+", description="a search query")
     * @Rest\Get("/{folder}", requirements={ "folder": "inbox|sent|drafts|trash" })
     * @Rest\View(serializerGroups={"Default", "conversation_list", "message_list", "user_list", "media_list", "user_messages"})
     *
     * @RightsSomeWhere("MESSAGING_ACCESS")
     *
     * @param string $folder
     * @param ParamFetcherInterface $paramFetcher
     * @return Response
     */
    public function getFolderAction($folder, ParamFetcherInterface $paramFetcher)
    {
        $query = null;
        $isConversation = true;
        $messageManager = $this->get('bns.message_manager');

        switch ($folder) {
            case 'inbox':
                $query = $messageManager->getMessagesConversationsByStatus(['NONE_READ', 'READ', 'CAMPAIGN', 'CAMPAIGN_READ']);
                break;
            case 'sent':
                $query = $messageManager->getSentMessages();
                $isConversation = false;
                break;
            case 'drafts':
                $query = $messageManager->getDraftMessages();
                $isConversation = false;
                break;
            case 'trash':
                $query = $messageManager->getMessagesConversationsByStatus(['DELETED', 'DELETED_CAMPAIGN']);
                break;
            default:
                return $this->view('', Codes::HTTP_BAD_REQUEST);
        }

        $search = $paramFetcher->get('search');
        $query = $this->processSearch($query, $search, $isConversation);

        return $this->getPaginator(
            $query,
            new Route('message_folder_api_get_folder', [
                'folder' => $folder,
                'version' => $this->getVersion()
            ], true),
            $paramFetcher
        );
    }

    /**
     * @ApiDoc(
     *  section="Messagerie - Dossier de messages",
     *  resource=true,
     *  description="Récupère les messages à gérer",
     *  requirements = {},
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Les données soumises sont invalides",
     *      404 = "La boite n'a pas été trouvée"
     *  }
     * )
     * @Rest\QueryParam(name="page", requirements="\d+", description="current page", default="1")
     * @Rest\QueryParam(name="limit", requirements="\d+", description="number of elements per page", default="10")
     * @Rest\QueryParam(name="status", requirements="IN_MODERATION|ACCEPTED|REJECTED", description="filter by message status")
     * @Rest\QueryParam(name="group", requirements="\d+", description="filter by group id")
     * @Rest\QueryParam(name="search", requirements="\w+", description="a search query")
     * @Rest\Get("/moderation")
     * @Rest\View(serializerGroups={"Default", "message_list", "message_detail", "user_list", "media_list"})
     *
     * @RightsSomeWhere("MESSAGING_ACCESS_BACK")
     *
     * @param ParamFetcherInterface $paramFetcher
     * @return Response
     */
    public function getModerationAction(ParamFetcherInterface $paramFetcher)
    {
        $status = $paramFetcher->get('status') ?: 'IN_MODERATION';
        if (!isset(BNSMessageManager::$messagesStatus[$status])) {
            return $this->view('Invalid status', Codes::HTTP_BAD_REQUEST);
        }

        if ($this->hasFeature('messaging_read_indicator')) {
            $this->get('hateoas.expression.evaluator')->setContextVariable('message_read_indicator', true);
        }

        $messagesQuery = MessagingMessageQuery::create()
            ->filterByAuthorId($this->getAuthorisedUsersIds())
            ->filterByStatus(BNSMessageManager::$messagesStatus[$status])
            ->orderByCreatedAt(\Criteria::DESC)
        ;

        $search = $paramFetcher->get('search');
        $messagesQuery = $this->processSearch($messagesQuery, $search, false);
        $group = $paramFetcher->get('group');
        $messagesQuery = $this->processGroup($messagesQuery, $group, false);

        return $this->getPaginator(
            $messagesQuery,
            new Route('message_folder_api_get_moderation', [
                'version' => $this->getVersion(),
            ], true),
            $paramFetcher
        );
    }

    protected function processSearch($query, $search, $isConversation = true)
    {
        if ($search) {
            $search = '%'.$search.'%';
            if ($isConversation) {
                $query = $query
                    ->useMessagingMessageQuery()
                    ->filterBySubject($search, \Criteria::LIKE)
                    ->_or()
                    ->filterByContent($search, \Criteria::LIKE)
                    ->endUse()
                ;
            } else {
                $query = $query
                    ->filterBySubject($search, \Criteria::LIKE)
                    ->_or()
                    ->filterByContent($search, \Criteria::LIKE)
                ;
            }
        }

        return $query;
    }

    protected function processGroup($query, $groupId, $isConversation = true)
    {
        if ($groupId) {
            $userIds = $this->getAuthorisedUsersIds([$groupId]);
            if ($isConversation) {
                $query = $query->useMessagingMessageQuery();
            }
            $query = $query->filterByAuthorId($userIds);
            if ($isConversation) {
                $query = $query->endUse();
            }
        }

        return $query;
    }

}
