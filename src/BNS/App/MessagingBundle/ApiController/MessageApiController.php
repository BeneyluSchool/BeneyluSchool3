<?php

namespace BNS\App\MessagingBundle\ApiController;

use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use BNS\App\MessagingBundle\Form\Type\MessageEditType;
use BNS\App\MessagingBundle\Form\Type\MessageType;
use BNS\App\MessagingBundle\Messaging\BNSMessageManager;
use BNS\App\MessagingBundle\Model\MessagingMessage;
use BNS\App\MessagingBundle\Model\MessagingMessageQuery;
use BNS\App\MessagingBundle\Model\MessagingPreferences;
use BNS\App\MessagingBundle\Model\MessagingPreferencesQuery;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcherInterface;
use FOS\RestBundle\Util\Codes;
use Hateoas\Configuration\Route;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MessageApiController extends BaseMessagingApiController
{

    /**
     * @ApiDoc(
     *  section="Messagerie - Messages",
     *  resource=false,
     *  description="Messagerie - Visualisation message",
     *  requirements = {
     *      {
     *          "name" = "messageId",
     *          "dataType" = "integer",
     *          "requirement" = "\d+",
     *          "description" = "L'ID du message"
     *      }
     *  },
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Erreur",
     *      403 = "Pas accès à la messagerie",
     *      404 = "Le message n'a pas été trouvé"
     *  }
     * )
     * @Rest\Get("/{id}")
     * @Rest\View(serializerGroups={"Default", "message_detail", "media_basic", "user_list"})
     *
     * @RightsSomeWhere("MESSAGING_ACCESS")
     *
     * @param MessagingMessage $message
     * @return MessagingMessage
     */
    public function getMessageAction(MessagingMessage $message)
    {
        //Vérification du droit de lecture
        $messageManager = $this->get('bns.message_manager');
        $rightManager = $this->get('bns.right_manager');
        $rightManager->forbidIf(!$messageManager->canRead($message));

        if ($this->hasFeature('messaging_read_indicator')) {
            $this->get('hateoas.expression.evaluator')->setContextVariable('message_read_indicator', true);
        }

        return  $message;
    }


    /**
     * @ApiDoc(
     *  section="Messagerie - Messages",
     *  resource=false,
     *  description="Messagerie - Envoi d'un message",
     * requirements= {
     *   },
     * parameters = {
     *      {"name"="subject", "dataType"="string", "required"=true, "description"="Titre du message"},
     *      {"name"="content", "dataType"="string", "required"=true, "description"="Contenu du message"},
     *      {"name"="to", "dataType"="string", "required"=true, "description"="Destinataires du message"},
     *      {"name"="draftId", "dataType"="integer", "required"=false, "description"="ID du brouillon (si c'en était un)"}
     * },
     *  statusCodes = {
     *      201 = "Message envoyé",
     *      400 = "Les données soumises sont invalides"
     *  }
     * )
     * @Rest\Post("")
     * @Rest\View(serializerGroups={"Default", "detail"})
     *
     * @RightsSomeWhere("MESSAGING_ACCESS")
     *
     * @param Request $request
     * @return Response
     */
    public function postNewMessageAction(Request $request)
    {
        $rightManager = $this->get('bns.right_manager');
        $toList = (null !== $request->get('to') ?  $request->get('to') : []);
        $draftId = $request->get('draftId');
        $subject = $request->get('subject', '');
        $content = $request->get('content', '');
        $groupsTo = (null !== $request->get('groupTo')) ?  $request->get('groupTo') : [];

        foreach ($groupsTo as $groupId) {
            $toList = array_unique(array_merge($toList, $this->get('bns.group_manager')->setGroupById($groupId)->getUserIdsWithPermission('MESSAGING_ACCESS')));
        }
        $handle = $this->handleUserList($toList);
        $status = $handle['needModeration'] ? "IN_MODERATION" : "ACCEPTED";
        $validatedUsers = $handle['validatedUsers'];

        if (!count($validatedUsers)) {
            return $this->view('No recipient', Codes::HTTP_BAD_REQUEST);
        }
        $now = new \DateTime();
        $absentUsers = MessagingPreferencesQuery::create()
            ->filterByUserId(array_keys($validatedUsers))
            ->filterByIsAbsent(true)
            ->filterByAbsentFrom($now, \Criteria::LESS_THAN)
            ->filterByAbsentTo($now, \Criteria::GREATER_THAN)
            ->find();

        //Etait-ce un brouillon ?
        $parentId = null;
        if ($draftId){
            $message = MessagingMessageQuery::create()->findPk($draftId);
            if (!$message) {
                return $this->view(null, Codes::HTTP_NOT_FOUND);
            }

            $rightManager->forbidIf($message->getUser()->getId() != $rightManager->getUserSession()->getId());
            $message->setSubject($subject);
            $message->setContent($content);
            $arrayStatus = BNSMessageManager::$messagesStatus;
            $message->setStatus($arrayStatus[$status]);
            $message->setTosTempList(serialize( $request->get('to')));
            $message->setGroupTos($groupsTo);
            $message->save();
        } else {
            //Un message = sujet / contenu / statut / destinataires (users et groupes)
            $message = $this->get('bns.message_manager')->initMessage($subject, $content, $status, $request->get('to'), $groupsTo);
        }
        //Envoi du message
        $this->get('bns.message_manager')->sendMessage($message, $status, $parentId, $validatedUsers, $request);
        foreach ($absentUsers as $absentUser) {
            /** @var MessagingPreferences $absentUser */
            $absenceAnswer = $this->get('bns.message_manager')->initMessage($absentUser->getAbsenceSubject(), $absentUser->getAbsenceContent(), "ACCEPTED", $rightManager->getUserSession()->getId());
            $this->get('bns.message_manager')->sendMessage($absenceAnswer, "ACCEPTED", null, [$rightManager->getUserSession()], null);
        }
        //statistic action
        $this->get("stat.messaging")->sendMessage();

        return $response = $this->view([ 'status' => $status ], Codes::HTTP_CREATED);
    }

    /**
     * @ApiDoc(
     *  section="Messagerie - Messages",
     *  resource=false,
     *  description="Messagerie - Suppression d'un brouillon",
     *  requirements = {
     *      {
     *          "name" = "id",
     *          "dataType" = "integer",
     *          "requirement" = "\d+",
     *          "description" = "L'ID du brouillon"
     *      }
     *  },
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Les données soumises sont invalides",
     *      404 = "Le brouillon n'a pas été trouvée"
     *  }
     * )
     * @Rest\Delete("/draft/{id}")
     * @Rest\View(serializerGroups={"Default", "detail"})
     *
     * @RightsSomeWhere("MESSAGING_ACCESS")
     *
     * @param MessagingMessage $message
     * @return Response
     */
    public function deleteMessageDraftAction(MessagingMessage $message)
    {
        // TODO: 404 if not draft

        //Vérification des droits de suppressions
        $rightManager = $this->get('bns.right_manager');
        $rightManager->forbidIf($message->getUser()->getId() != $rightManager->getUserSession()->getId());

        $messageManager = $this->get('bns.message_manager');
        $messageManager->delete($message);

        return $this->view(null, Codes::HTTP_NO_CONTENT);
    }

    /**
     * @ApiDoc(
     *  section="Messagerie - Messages",
     *  resource=false,
     *  description="Messagerie - Éditer un brouillon ( = récupérer un brouillon )",
     *  requirements = {
     *      {
     *          "name" = "id",
     *          "dataType" = "integer",
     *          "requirement" = "\d+",
     *          "required" = true,
     *          "description" = "ID du brouillon"
     *      }
     *  },
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Les données soumises sont invalides",
     *      404 = "Le brouillon n'a pas été trouvée"
     *  }
     * )
     * @Rest\Get("/draft/{id}")
     * @Rest\View(serializerGroups={"Default", "detail", "message_detail"})
     *
     * @RightsSomeWhere("MESSAGING_ACCESS")
     *
     * @param MessagingMessage $message
     * @return array
     */
    public function getEditMessageDraftAction(MessagingMessage $message)
    {
        // TODO: 404 if not draft

        $rightManager = $this->get('bns.right_manager');
        $rightManager->forbidIf($message->getUser()->getId() != $rightManager->getUserSession()->getId());

        return array(
            'id' => $message->getId(),
            'attachments' => $message->getResourceAttachments(),
            'to' => unserialize($message->getTosTempList()),
            'groupTo' => $message->getGroupTos(),
            'subject' => $message->getSubject(),
            'content' => $message->getContent(),
            'is_draft' => $message->isDraft(),
            'status' => $message->getStatus(),
        );
    }

    /**
     * @ApiDoc(
     *  section="Messagerie - Messages",
     *  resource=false,
     *  description="Messagerie - Enregistrer un brouillon ",
     *  requirements = {
     *  },
     *  parameters = {
     *      {"name"="to", "dataType"="string", "required"=false, "description"="Destinataires du brouillon"},
     *      {"name"="subject", "dataType"="string", "required"=false, "description"="Sujet du message"},
     *      {"name"="content", "dataType"="string", "required"=false, "description"="Contenu du message"},
     *      {"name"="draftId", "dataType"="integer", "required"=false, "description"="ID du draft ( s'il existait déjà )"}
     * },
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Les données soumises sont invalides",
     *      404 = "Le brouillon n'a pas été trouvée"
     *  }
     * )
     * @Rest\Post("/draft")
     * @Rest\View(serializerGroups={"Default", "detail"})
     *
     * @RightsSomeWhere("MESSAGING_ACCESS")
     *
     * @param Request $request
     * @return Response
     */
    public function saveMessageDraftAction(Request $request)
    {
        $values = array();
        $messageManager = $this->get('bns.message_manager');
        $rightManager = $this->get('bns.right_manager');
        $mediaManager =  $this->get('bns.media.manager');

        return $this->restForm(new MessageType(), $values, array(
            'draft' => true,
            'csrf_protection' => false, // TODO
        ), null, function ($data) use ($messageManager, $rightManager, $mediaManager, $request) {
            if (isset($data['draftId'])) {
                $draft = MessagingMessageQuery::create()->findPk($data['draftId']);
                $rightManager->forbidIf($draft->getUser()->getId() != $rightManager->getUserSession()->getId());
                $draft->setSubject($data['subject']);
                $draft->setContent($data['content']);
            } else {
                $draft = $messageManager->createDraft($data['subject'], $data['content'], $request);
            }

            $toList = (null !== $request->get('to') ? $request->get('to') : []);
            $groupTo = (null !== $request->get('groupTo') ? $request->get('groupTo') : []);
            $draft->setTosTempList(serialize( $toList));
            $draft->setGroupTos($groupTo);
            $draft->save();
            $mediaManager->saveAttachments($draft, $request);

            return $draft;
        });
    }

    /**
     * @ApiDoc(
     *  section="Messagerie - Messages",
     *  resource=true,
     *  description="Messagerie - Edition d'un message",
     *  requirements = {
     *  },
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Les données soumises sont invalides",
     *      404 = "Le brouillon n'a pas été trouvée"
     *  }
     * )
     * @Rest\Patch("/{id}")
     * @Rest\View(serializerGroups={"Default", "detail"})
     *
     * @RightsSomeWhere("MESSAGING_ACCESS_BACK")
     *
     * @param MessagingMessage $message
     * @return Response
     */
    public function patchMessageAction(MessagingMessage $message)
    {
        if (!$this->get('bns.message_manager')->canRead($message)) {
            return $this->view('', Codes::HTTP_FORBIDDEN);
        }

        return $this->restForm(new MessageEditType(), $message, [
            'csrf_protection' => false, // TODO
        ]);
    }

    /**
     * @ApiDoc(
     *  section="Messagerie - Messages",
     *  resource=true,
     *  description="Messagerie - Opérations sur tous les messages",
     *  requirements = {
     *      {
     *          "name" = "action",
     *          "dataType" = "string",
     *          "requirement" = "accept|reject",
     *          "description" = "Action à effectuer"
     *      }
     *  },
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Les données soumises sont invalides",
     *  }
     * )
     * @Rest\Get("/all/{action}")
     * @Rest\View()
     *
     * @RightsSomeWhere("MESSAGING_ACCESS_BACK")
     *
     * @param string $action
     * @return Response
     */
    public function allAction($action)
    {
        // map action => current status
        $actions = [
            'accept' => 'IN_MODERATION',
            'delete' => 'REJECTED',
        ];
        if (!isset($actions[$action])) {
            return $this->view('Invalid action', Codes::HTTP_BAD_REQUEST);
        }

        // find targeted messages
        $status = $actions[$action];
        $messages = MessagingMessageQuery::create()
            ->filterByAuthorId($this->getAuthorisedUsersIds())
            ->filterByStatus(BNSMessageManager::$messagesStatus[$status])
            ->orderByCreatedAt(\Criteria::DESC)
            ->find()
        ;

        // apply action on them
        $messageManager = $this->get('bns.message_manager');
        foreach ($messages as $message) {
            $messageManager->$action($message);
        }

        return $messages->count();
    }

    /**
     * @ApiDoc(
     *  section="Messagerie - Messages",
     *  resource=true,
     *  description="Messagerie - Manipulations d'une sélection de messages",
     *  requirements = {
     *      {
     *          "name" = "action",
     *          "dataType" = "string",
     *          "requirement" = "trash",
     *          "description" = ""
     *      },
     *      {
     *          "name" = "ids",
     *          "dataType" = "array",
     *          "description" = "Tableau d'IDs des messages concernées"
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
    public function messageSelectionAction(Request $request)
    {
        $ids = $request->get('ids');
        $action = $request->get('action');

        $frontActions = ['trash'];
        $backActions = ['moderate', 'accept', 'reject', 'delete'];
        if (!in_array($action, array_merge($frontActions, $backActions))) {
            return $this->view('Invalid action', Codes::HTTP_BAD_REQUEST);
        }

        $rightManager = $this->get('bns.right_manager');
        $authorizedIds = [];

        if (in_array($action, $backActions)) {
            if (!$rightManager->hasRightSomeWhere('MESSAGING_ACCESS_BACK')) {
                return $this->view('', Codes::HTTP_FORBIDDEN);
            }

            $authorizedIds = $this->getAuthorisedUsersIds();
        }

        $messages = MessagingMessageQuery::create()->findPks($ids);
        $messageManager = $this->get('bns.message_manager');
        $user = $rightManager->getUserSession();
        $valid = [];

        foreach ($messages as $message) {
            // check that user can manage the message
            if (in_array($action, $frontActions) && $message->getUser()->getId() != $user->getId()) {
                continue;
            }

            switch ($action) {
                case 'trash':
                    // can delete only drafts
                    if ($message->getStatus() !== BNSMessageManager::$messagesStatus['DRAFT']) {
                        continue;
                    }
                    $messageManager->delete($message);
                    break;
                case 'moderate':
                case 'accept':
                case 'reject':
                case 'delete':
                    // admin actions
                    if (!in_array($message->getAuthorId(), $authorizedIds)) {
                        continue;
                    }
                    $messageManager->$action($message);
                    break;
                default:
                    continue;
            }
            $valid[] = $message->getId();
        }

        return $valid;
    }

    /**
     * @ApiDoc(
     *  section="Messagerie - Messages",
     *  resource=false,
     *  description="Messagerie - Recherche de messages",
     *  requirements= {
     *          {"name"="word", "dataType"="string", "required"=false, "description"="Contenu de la recherche"}
     *   },
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Les données soumises sont invalides",
     *      404 = "Aucun résultat"
     *  }
     * )
     * @Rest\QueryParam(name="page", requirements="\d+", description="current page", default="1")
     * @Rest\QueryParam(name="limit", requirements="\d+", description="number of elements per page", default="10")
     * @Rest\Get("/search/{word}")
     * @Rest\View(serializerGroups={"Default", "detail"})
     */
    public function getSearchMessageAction($word = "emptySearch", ParamFetcherInterface $paramFetcher)
    {
        if($word == "emptySearch"){
            $results = null;
        }else{
            $messageManager = $this->get('bns.message_manager');
            $results = $messageManager->getSearchQuery(urldecode($word));
        }

        return $this->getPaginator(
            $results,
            new Route('message_api_get_search_message', array(
                'version' => $this->getVersion(),
                'word' => $word
            ), true),
            $paramFetcher
        );
    }

}
