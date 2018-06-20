<?php

namespace BNS\App\MessagingBundle\ApiController;

use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use BNS\App\MessagingBundle\Form\Type\ConversationType;
use BNS\App\MessagingBundle\Messaging\BNSMessageManager;
use BNS\App\MessagingBundle\Model\MessagingConversationQuery;
use BNS\App\MessagingBundle\Form\Type\AnswerType;
use BNS\App\MessagingBundle\Model\MessagingConversation;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Util\Codes;
use FOS\RestBundle\View\View;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;

class ConversationApiController extends BaseMessagingApiController
{

    /**
     * @ApiDoc(
     *  section="Messagerie - Conversations",
     *  resource=false,
     *  description="Messagerie - Suppression d'une conversation",
     *  requirements = {
     *      {
     *          "name" = "id",
     *          "dataType" = "integer",
     *          "requirement" = "\d+",
     *          "description" = "L'ID de la conversation"
     *      }
     *  },
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Les données soumises sont invalides",
     *      404 = "La conversation n'a pas été trouvée"
     *  }
     * )
     * @Rest\Delete("/{id}")
     * @Rest\View(serializerGroups={"Default", "detail"})
     *
     * @RightsSomeWhere("MESSAGING_ACCESS")
     *
     * @param MessagingConversation $conversation
     * @return Response
     */
    public function deleteConversationAction(MessagingConversation $conversation)
    {
        $rightManager = $this->get('bns.right_manager');
        $messageManager = $this->get('bns.message_manager');
        //Vérification des droits de suppressions
        if ($conversation != null) {
            $rightManager->forbidIf($conversation->getUserId() != $rightManager->getUserSession()->getId());
        }

        $messageManager->setDeleted($conversation);

        return $this->view(null, Codes::HTTP_NO_CONTENT);
    }

    /**
     * @ApiDoc(
     *  section="Messagerie - Conversations",
     *  resource=true,
     *  description="Messagerie - Conversation",
     * requirements= {
     *      {
     *          "name" = "id",
     *          "dataType" = "integer",
     *          "requirement" = "\d+",
     *          "required" = true,
     *          "description" = "ID de la conversation"
     *      }
     *   },
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Les données soumises sont invalides",
     *      404 = "La conversation n'a pas été trouvée"
     *  }
     * )
     * @Rest\Get("/{id}")
     * @Rest\View(serializerGroups={"Default", "conversation_detail", "message_detail", "media_basic", "user_list"})
     *
     * @RightsSomeWhere("MESSAGING_ACCESS")
     *
     * @param integer $id
     * @return MessagingConversation
     */
    public function getConversationAction($id)
    {
        $conversation = MessagingConversationQuery::create()->joinMessagingMessage()->findPk($id);
        $message = $conversation->getMessage();

        //Vérification du droit de lecture
        $messageManager = $this->get('bns.message_manager');
        $rightManager = $this->get('bns.right_manager');
        $rightManager->forbidIf(!$messageManager->canRead($message));

        if (in_array($messageManager->getStatus($conversation), ['NONE_READ', 'CAMPAIGN'])) {
            $messageManager->setRead($conversation);
        }

        if ($this->hasFeature('messaging_read_indicator')) {
            $this->get('hateoas.expression.evaluator')->setContextVariable('conversation_read_indicator', true);
        }

        return $conversation;
    }

    /**
     * poster une des actions suivantes
     *
     * <ul>
     * <li>restore</li>
     * <li>read</li>
     * <li>unread</li>
     * </ul>
     * exemple :
     * <pre>
     * {
     *   "action": "restore"
     * }
     * </pre>
     *
     * @ApiDoc(
     *  section="Messagerie - Conversations",
     *  resource=false,
     *  description="Messagerie - Restaurer une conversation supprimée",
     * requirements= {
     *      {
     *          "name" = "id",
     *          "dataType" = "integer",
     *  "       requirement" = "\d+",
     *          "required" = true,
     *          "description" = "ID de la conversation"
     *      }
     *   },
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Les données soumises sont invalides",
     *      404 = "La conversation n'a pas été trouvée"
     *  }
     * )
     * @Rest\Patch("/{id}")
     * @Rest\View(serializerGroups={"Default", "detail"})
     *
     * @RightsSomeWhere("MESSAGING_ACCESS")
     *
     * @param MessagingConversation $conversation
     * @return Response|View
     */
    public function patchConversationAction(Request $request, MessagingConversation $conversation)
    {
        $user = $this->getUser();
        // check that user can manage the conversation
        if ($conversation->getUserId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }
        $messageManager = $this->get('bns.message_manager');

        switch ($request->request->get('action')) {
            case 'restore':
                $messageManager->setRead($conversation);
                break;
            case 'read':
                $messageManager->setRead($conversation);
                break;
            case 'unread':
                $messageManager->setUnread($conversation);
                break;
            default:
                return View::create(['error' => 'invalid_action'], Response::HTTP_BAD_REQUEST);
        }

        return View::create(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @ApiDoc(
     *  section="Messagerie - Conversations",
     *  resource=false,
     *  description="Messagerie - Réponse à une conversation",
     *  requirements = {
     *  },
     * parameters = {
     *      {"name"="answer", "dataType"="string", "required"=true, "description"="Contenu du message"},
     *      {"name"="conversation_id", "dataType"="integer", "required"=true, "description"="ID de la conversation"}
     * },
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Les données soumises sont invalides",
     *      404 = "La conversation n'a pas été trouvée"
     *  }
     * )
     * @Rest\Post("/{id}")
     * @Rest\View(serializerGroups={"Default", "detail"})
     *
     * @RightsSomeWhere("MESSAGING_ACCESS")
     *
     * @param MessagingConversation $conversation
     * @param Request $request
     * @return Response
     */
    public function postAnswerMessageAction(MessagingConversation $conversation, Request $request)
    {
        $rightManager = $this->get('bns.right_manager');
        $rightManager->forbidIf($rightManager->getUserSession()->getId() != $conversation->getUserId());

        // Récupération de l'expéditeur initial
        // TODO : faire recheck du destinataire
        $user = $this->get('bns.user_manager')->findUserById($conversation->getUserWithId());
        $handle = $this->handleUserList(array($user->getId()));
        $status = $handle['needModeration'] ? "IN_MODERATION" : "ACCEPTED";
        $values = array();
        $messageManager = $this->get('bns.message_manager');
        $stats = $this->get("stat.messaging");

        return $this->restForm(new AnswerType(), $values, array(
            'csrf_protection' => false, // TODO
        ), null, function ($data) use ($conversation, $messageManager, $status, $request, $stats) {
            $answer = $messageManager->answerMessage($conversation, $data['answer'], $status, $request);
            $stats->sendMessage();

            return $answer;
        });
    }

    /**
     * @ApiDoc(
     *  section="Messagerie - Conversations",
     *  resource=true,
     *  description="Messagerie - Manipulations d'une sélection de conversations",
     *  requirements = {
     *      {
     *          "name" = "action",
     *          "dataType" = "string",
     *          "requirement" = "trash|restore|read|unread",
     *          "description" = ""
     *      },
     *      {
     *          "name" = "ids",
     *          "dataType" = "array",
     *          "description" = "Tableau d'IDs des conversations concernées"
     *      }
     *  },
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Les données soumises sont invalides",
     *  }
     * )
     * @Rest\Patch("/selection")
     * @Rest\View()
     *
     * @RightsSomeWhere("MESSAGING_ACCESS")
     *
     * @param Request $request
     * @return Response
     */
    public function conversationSelectionAction(Request $request)
    {
        $ids = $request->get('ids');
        $action = $request->get('action');
        if (!in_array($action, ['trash', 'restore', 'read', 'unread'])) {
            return $this->view('Invalid action', Codes::HTTP_BAD_REQUEST);
        }

        $conversations = MessagingConversationQuery::create()->findPks($ids);
        $messageManager = $this->get('bns.message_manager');
        $rightManager = $this->get('bns.right_manager');
        $user = $rightManager->getUserSession();
        $valid = [];

        foreach ($conversations as $conversation) {
            // check that user can manage the conversation
            if ($conversation->getUserId() != $user->getId()) {
                continue;
            }

            switch ($action) {
                case 'trash':
                    $messageManager->setDeleted($conversation);
                    break;
                case 'restore':
                    $messageManager->setRead($conversation);
                    break;
                case 'read':
                    $messageManager->setRead($conversation);
                    break;
                case 'unread':
                    $messageManager->setUnread($conversation);
                    break;
                default:
                    continue;
            }
            $valid[] = $conversation->getId();
        }

        return $valid;
    }

}
