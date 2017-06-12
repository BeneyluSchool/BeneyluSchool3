<?php

namespace BNS\App\HomeworkBundle\ApiController;

use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\HomeworkBundle\Form\Type\ApiHomeworkCreateType;
use BNS\App\HomeworkBundle\Form\Type\ApiHomeworkType;
use BNS\App\HomeworkBundle\Model\Homework;
use BNS\App\HomeworkBundle\Model\HomeworkDue;
use BNS\App\HomeworkBundle\Model\HomeworkDueQuery;
use BNS\App\HomeworkBundle\Model\HomeworkPeer;
use BNS\App\HomeworkBundle\Model\HomeworkSubject;
use BNS\App\HomeworkBundle\Model\HomeworkSubjectPeer;
use BNS\App\HomeworkBundle\Model\HomeworkSubjectQuery;
use BNS\App\HomeworkBundle\Model\HomeworkPreferences;
use BNS\App\HomeworkBundle\Model\HomeworkPreferencesQuery;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcherInterface;
use FOS\RestBundle\Util\Codes;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class HomeworkApiController
 *
 * @package BNS\App\HomeworkBundle\ApiController
 */
class HomeworkApiController extends BaseHomeworkApiController
{

    /**
     * @ApiDoc(
     *  section="Cahier de texte",
     *  resource=true,
     *  description="Liste les devoirs de la semaine",
     *  requirements = {
     *      {
     *          "name" = "date",
     *          "dataType" = "date",
     *          "description" = "La date du premier jour de la semaine (lundi)"
     *      }
     *  },
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Les données soumises sont invalides",
     *      404 = "Les devoirs de cette semaine n'ont pas été trouvés."
     *  }
     * )
     *
     * @Rest\QueryParam(name="subjects", description="list of ids of subjects to filter by")
     * @Rest\QueryParam(name="groups", description="list of ids of groups to filter by")
     * @Rest\QueryParam(name="days", description="list of day codes to filter by")
     * @Rest\Get("/week/{date}")
     * @Rest\View(serializerGroups={"Default", "homework_list", "homework_detail", "back", "media_basic"})
     * @RightsSomeWhere("HOMEWORK_ACCESS_BACK")
     *
     * @param string $date First day of week
     * @param ParamFetcherInterface $paramFetcher
     * @param Request $request
     * @return mixed
     */
    public function getWeekHomeworksAction($date, ParamFetcherInterface $paramFetcher, Request $request)
    {
        if (!$this->isMonday($date)) {
            return $this->view(null, Codes::HTTP_BAD_REQUEST);
        }

        if ($step = $request->get('step')) {
            return $this->getStarterKitList($request, $step);
        }

        $startDay = strtotime($date);
        $endDay = strtotime("+6 days", $startDay);

        $groups = $this->get('bns.right_manager')->getGroupsWherePermission('HOMEWORK_ACCESS_BACK');

        $subjectIds = $paramFetcher->get('subjects');
        $subjectIds = $subjectIds ? explode(',', $subjectIds) : null;
        $groupIds = $paramFetcher->get('groups');
        $groupIds = $groupIds ? explode(',', $groupIds) : null;
        $prefDays = HomeworkPreferencesQuery::create()->findOrInit($this->get('bns.right_manager')->getCurrentGroupId())->getDays();
        $days = $paramFetcher->get('days');
        $days = $days ? explode(',', $days) : $prefDays;

        return HomeworkDueQuery::create()->findForRangeForGroups($startDay, $endDay, $groups, $subjectIds, $groupIds, $days);
    }

    /**
     * @ApiDoc(
     *  section="Cahier de texte",
     *  resource=true,
     *  description="Liste les devoirs du jour",
     *  requirements = {
     *      {
     *          "name" = "date",
     *          "dataType" = "date",
     *          "description" = "La date du jour"
     *      }
     *  },
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Les données soumises sont invalides",
     *      404 = "Les devoirs de ce jour n'ont pas été trouvés."
     *  }
     * )
     *
     * @Rest\Get("/day/{date}")
     * @Rest\View(serializerGroups={"Default", "homework_detail", "homework_due_detail", "user_list", "media_basic"})
     * @RightsSomeWhere("HOMEWORK_ACCESS")
     *
     * @param string $date First day of week
     * @param ParamFetcherInterface $paramFetcher
     * @param Request $request
     * @return mixed
     */
    public function getDayHomeworksAction($date, ParamFetcherInterface $paramFetcher, Request $request)
    {
        if ($step = $request->get('step')) {
            return $this->getStarterKitList($request, $step);
        }

        if (!$this->isDate($date)) {
            return $this->view(null, Codes::HTTP_BAD_REQUEST);
        }

        $groupIds = $this->get('bns.right_manager')->getGroupIdsWherePermission('HOMEWORK_ACCESS');

        return HomeworkDueQuery::create()->groupByHomeworkId()->findForDay($groupIds, $date);
    }

    /**
     * @ApiDoc(
     *  section="Cahier de texte",
     *  resource=true,
     *  description="Liste les matières",
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Les données soumises sont invalides",
     *      404 = "Les matieres de ce groupe n'ont pas été trouvées."
     *  }
     * )
     *
     * @Rest\Get("/groups/{groupId}/subjects")
     * @Rest\View(serializerGroups={"Default", "homework_detail", "homework_due_detail", "user_list", "media_basic"})
     * @RightsSomeWhere("HOMEWORK_ACCESS")
     *
     * @param integer $groupId
     * @return mixed
     */
    public function getSubjectsAction($groupId)
    {
        if (!$this->get('bns.right_manager')->hasRight('HOMEWORK_ACCESS', $groupId)) {
            return $this->view(null, Codes::HTTP_FORBIDDEN);
        }

        $subjects = HomeworkSubjectQuery::create()->fetchAndFilterByGroupId($groupId);

        return array_values($subjects);
    }

    /**
     * <pre>
     * {"subject_title" : "Matiere 1" }
     * </pre>
     *
     * @ApiDoc(
     *  section="Cahier de texte",
     *  resource=true,
     *  description="Ajoute une matière",
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Les données soumises sont invalides",
     *      404 = "La matiere n'a pas été ajoutées."
     *  }
     * )
     *
     * @Rest\Post("/groups/{groupId}/subjects")
     * @Rest\View(serializerGroups={"Default", "homework_detail", "homework_due_detail", "user_list", "media_basic"})
     * @RightsSomeWhere("HOMEWORK_ACCESS_BACK")
     *
     * @param integer $groupId
     * @param Request $request
     * @return mixed
     */
    public function postAddSubjectsAction($groupId, Request $request)
    {
        if (!$this->get('bns.right_manager')->hasRight('HOMEWORK_ACCESS_BACK', $groupId)) {
            return $this->view(null, Codes::HTTP_FORBIDDEN);
        }

        $name = $request->get('name', $request->get('title'));

        if (!$name) {
            return $this->view(null, Codes::HTTP_BAD_REQUEST);
        }

        $subject = new HomeworkSubject();

        $rootSubject = HomeworkSubject::fetchRoot($groupId);
        $subject->insertAsLastChildOf($rootSubject);
        $subject->setGroupId($groupId)
            ->setName($name)
            ->save();

        return [
            'id' => $subject->getId(),
            'name' => $subject->getName(),
            'title' => $subject->getName(), // for compatibility with current bnsChoiceCreate directive
        ];
    }

    /**
     * <pre>
     * {"subject_title" : "Matiere 1" }
     * </pre>
     *
     * @ApiDoc(
     *  section="Cahier de texte",
     *  resource=true,
     *  description="Edite une matière",
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Les données soumises sont invalides",
     *      404 = "La matiere n'a pas été éditées."
     *  }
     * )
     *
     * @Rest\Patch("/subjects/{subjectId}")
     * @Rest\View(serializerGroups={"Default", "homework_detail", "homework_due_detail", "user_list", "media_basic"})
     * @RightsSomeWhere("HOMEWORK_ACCESS_BACK")
     *
     * @param integer $subjectId
     * @param Request $request
     * @return mixed
     */
    public function editSubjectsAction($subjectId, Request $request )
    {
        $name = $request->get('name');

        if (!$name) {
            return $this->view(null, Codes::HTTP_BAD_REQUEST);
        }

        $subject = HomeworkSubjectQuery::create()
            ->filterById($subjectId)
            ->findOne();

        if ($subject) {
            if (!$this->get('bns.right_manager')->hasRight('HOMEWORK_ACCESS_BACK', $subject->getGroupId())) {
                return $this->view(null, Codes::HTTP_FORBIDDEN);
            }

            $subject->setName($name)
                ->save();

            return $subject;
        }

        return $this->view(null, Codes::HTTP_NOT_FOUND);
    }

    /**
     * @ApiDoc(
     *  section="Cahier de texte",
     *  resource=true,
     *  description="Supprime une matière",
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Les données soumises sont invalides",
     *      404 = "La matiere n'a pas été supprimées."
     *  }
     * )
     *
     * @Rest\Delete("/subjects/{subjectId}")
     * @Rest\View(serializerGroups={"Default", "homework_detail", "homework_due_detail", "user_list", "media_basic"})
     * @RightsSomeWhere("HOMEWORK_ACCESS_BACK")
     *
     * @param integer $subjectId
     * @return mixed
     */
    public function deleteSubjectsAction($subjectId)
    {
        $subject = HomeworkSubjectQuery::create()
            ->filterById($subjectId)
            ->findOne();

        if ($subject) {
            if (!$this->get('bns.right_manager')->hasRight('HOMEWORK_ACCESS_BACK', $subject->getGroupId())) {
                return $this->view(null, Codes::HTTP_FORBIDDEN);
            }

            $subject->delete();
            return $this->view(null, Codes::HTTP_OK);
        } else {
            return $this->view(null, Codes::HTTP_BAD_REQUEST);
        }
    }

    /**
     * <pre>
     * {"ids" : [4, 8, 15, 16, 23, 42] }
     * </pre>
     *
     * @ApiDoc(
     *  section="Cahier de texte",
     *  resource=true,
     *  description="Trie les matières d'un groupe",
     *  statusCodes = {
     *      200 = "Ok",
     *      400 = "Les données soumises sont invalides",
     *      404 = "Une des matières n'a pas été trouvée"
     *  }
     * )
     *
     * @Rest\Patch("/groups/{groupId}/subjects/sort")
     * @RightsSomeWhere("HOMEWORK_ACCESS_BACK")
     *
     * @param integer $groupId
     * @param Request $request
     * @return mixed
     */
    public function sortSubjectsAction($groupId, Request $request)
    {
        if (!$this->get('bns.right_manager')->hasRight('HOMEWORK_ACCESS_BACK', $groupId)) {
            return $this->view(null, Codes::HTTP_FORBIDDEN);
        }

        $subjects = HomeworkSubjectQuery::create()->fetchAndFilterByGroupId($groupId);

        $ids = $request->get('ids', []);

        // check that we have ids of all existing subjects
        if (count(array_diff(array_keys($subjects), $ids))) {
            return $this->view(array_diff(array_keys($subjects), $ids), Codes::HTTP_BAD_REQUEST);
        }

        // reorder subjects based on submitted ids
        $orderedSubjects = [];
        foreach ($ids as $id) {
            $orderedSubjects[$id] = $subjects[$id];
        }

        // apply new order
        $rootSubject = HomeworkSubject::fetchRoot($groupId);
        $left = $rootSubject->getLeftValue();
        $con = \Propel::getConnection(HomeworkSubjectPeer::DATABASE_NAME);
        $con->beginTransaction();
        try  {
            /** @var HomeworkSubject $subject */
            foreach ($orderedSubjects as $subject) {
                $subject->setLeftValue($left + 1);
                $subject->setRightValue($left + 2);
                $subject->save($con);
                $left += 2;
            }
        } catch (\Exception $e) {
            $con->rollBack();
        }
        $con->commit();

        return $this->view(null, Codes::HTTP_OK);
    }

    /**
     * @ApiDoc(
     *  section="Cahier de texte",
     *  resource=true,
     *  description="Crée un ou plusieurs devoirs pour le même jour",
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Les données soumises sont invalides",
     *      404 = "Les devoirs de cette semaine n'ont pas été trouvés."
     *  }
     * )
     *
     * @Rest\Post("")
     * @Rest\View(serializerGroups={"Default", "list"})
     * @RightsSomeWhere("HOMEWORK_ACCESS_BACK")
     *
     * @param Request $request
     *
     * @return mixed
     */
    public function postAction(Request $request)
    {
        $data = [
            'homeworks' => [],
        ];

        foreach ($request->get('homeworks', []) as $h) {
            $homework = new Homework();
            $homework->setRecurrenceType(HomeworkPeer::RECURRENCE_TYPE_ONCE);
            $data['homeworks'][] =  $homework;
        }

        $mediaManager = $this->get('bns.media.manager');
        $lockerManager = $this->get('bns.media_folder.locker_manager');
        $homeworkManager = $this->get('bns.homework_manager');
        $currentGroup = $this->get('bns.right_manager')->getCurrentGroup();
        $groups = $this->get('bns.right_manager')->getGroupsWherePermission('HOMEWORK_ACCESS_BACK');
        $subjects = HomeworkSubjectQuery::create()->fetchAndFilterByGroupId($currentGroup->getId());

        return $this->restForm(new ApiHomeworkCreateType($groups, $subjects), $data, [
            'csrf_protection' => false, // TODO
        ], null, function ($data, $form) use ($request, $mediaManager, $lockerManager, $homeworkManager, $currentGroup) {
            /** @var Homework $homework */
            foreach ($data['homeworks'] as $key => $homework) {
                $homework->setRecurrenceDays([strtoupper(substr($homework->getDate()->format("D"), 0, 2))]);
                $homework->save();
                $this->get('stat.homework')->newWork();
                if ($homework->getHasLocker()) {
                    $lockerManager->createForHomework($homework);
                }
                $homeworkManager->processHomework($homework);
                $attachments = isset($request->get('homeworks')[$key]['resource-joined']) ? $request->get('homeworks')[$key]['resource-joined'] : null;
                if ($attachments) {
                    $mediaManager->saveAttachments($homework, $attachments);
                }
                if ($step = $request->get('step')) {
                    $starterKitHomeworks = $request->getSession()->get('starter_kit_homeworks', []);
                    $starterKitHomeworks[] = $homework->getId();
                    $request->getSession()->set('starter_kit_homeworks', $starterKitHomeworks);
                }
            }
        });

    }

    /**
     * @ApiDoc(
     *  section="Cahier de texte",
     *  resource=true,
     *  description="Récupération d'un devoir",
     *  requirements = {
     *    {
     *      "name" = "id",
     *      "dataType" = "integer",
     *      "requirement" = "\d+",
     *      "description" = "ID du devoir"
     *    }
     *  },
     *  statusCodes = {
     *      204 = "Ok",
     *      404 = "Le devoir n'a pas été trouvé."
     *  }
     * )
     *
     * @Rest\Get("/{id}")
     * @Rest\View()
     * @RightsSomeWhere("HOMEWORK_ACCESS_BACK")
     *
     * @param Homework $homework
     * @return mixed
     */
    public function getAction(Homework $homework)
    {
        if (!$this->canManageHomework($homework)) {
            return $this->view(null, Codes::HTTP_FORBIDDEN);
        }

        return $homework;
    }

    /**
     * @ApiDoc(
     *  section="Cahier de texte",
     *  description="Modification d'un devoir",
     *  requirements = {
     *    {
     *      "name" = "id",
     *      "dataType" = "integer",
     *      "requirement" = "\d+",
     *      "description" = "ID du devoir"
     *    }
     *  },
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Les données soumises sont invalides",
     *      404 = "Le devoir n'a pas été trouvé."
     *  }
     * )
     *
     * @Rest\Patch("/{id}")
     * @Rest\View()
     * @RightsSomeWhere("HOMEWORK_ACCESS_BACK")
     *
     * @param Homework $homework
     * @param Request $request
     * @return mixed
     */
    public function patchAction(Homework $homework, Request $request)
    {
        if (!$this->canManageHomework($homework)) {
            return $this->view(null, Codes::HTTP_FORBIDDEN);
        }
        $data = json_decode($request->getContent(), true);
        $groups = GroupQuery::create()
            ->filterById($data['groups'])
            ->find();
        $homework->setGroups($groups);

        $groups = $this->get('bns.right_manager')->getGroupsWherePermission('HOMEWORK_ACCESS_BACK');
        $mediaManager = $this->get('bns.media.manager');
        $homeworkManager = $this->get('bns.homework_manager');
        $lockerManager = $this->get('bns.media_folder.locker_manager');
        $currentGroup = $this->get('bns.right_manager')->getCurrentGroup();
        $subjects = HomeworkSubjectQuery::create()->fetchAndFilterByGroupId($currentGroup->getId());

        return $this->restForm(new ApiHomeworkType($groups, $subjects), $homework, [
            'csrf_protection' => false, // TODO,
        ], null, function ($homework) use ($request, $lockerManager, $homeworkManager, $mediaManager) {
            /** @var Homework $homework */
            if ($homework->isColumnModified(HomeworkPeer::RECURRENCE_TYPE)
                || $homework->isColumnModified(HomeworkPeer::DATE)
                || $homework->isColumnModified(HomeworkPeer::RECURRENCE_END_DATE)
            ) {
                $homework->getHomeworkDues()->delete();
            }

            $homework->save();

            if ($homework->getHasLocker()) {
                $lockerManager->createForHomework($homework);
            }
            $homeworkManager->processHomework($homework);
            $mediaManager->saveAttachments($homework, $request);

            return $homework;
        });
    }

    /**
     * @ApiDoc(
     *  section="Cahier de texte",
     *  description="Suppression d'un devoir",
     *  requirements = {
     *    {
     *      "name" = "id",
     *      "dataType" = "integer",
     *      "requirement" = "\d+",
     *      "description" = "ID du devoir"
     *    }
     *  },
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Les données soumises sont invalides",
     *      404 = "Le devoir n'a pas été trouvé."
     *  }
     * )
     *
     * @Rest\Delete("/{id}")
     * @Rest\View()
     * @RightsSomeWhere("HOMEWORK_ACCESS_BACK")
     *
     * @param Homework $homework
     * @return mixed
     */
    public function deleteAction(Homework $homework)
    {
        if (!$this->canManageHomework($homework)) {
            return $this->view(null, Codes::HTTP_FORBIDDEN);
        }

        $homework->delete();

        return $this->view(null, Codes::HTTP_OK);
    }

    public function getStarterKitList (Request $request, $step = null)
    {
        $translator = $this->get('translator');
        $step = $step ? : $request->get('step');
        $data = [];
        if ($step >= '1-1.1') {
            $h = new Homework();
            $h->fromArray([
                'Id' => -1,
                'Name' => $translator->trans('EMBED_1_1_1_NAME', [], 'SK_HOMEWORK'),
                'Description' => $translator->trans('EMBED_1_1_1_DESCRIPTION', [], 'SK_HOMEWORK'),
            ]);
            $hd = new HomeworkDue();
            $hd->fromArray([
                'Id' => -1,
                'DueDate' => date('Y-m-d'),
            ]);
            $hd->setHomework($h);
            $hd->done = false;

            $data[] = $hd;
        }
        if ($step > '1-1.2') {
            // mark previous homework as done
            $data[0]->done = true;
        }
        if ($step >= '1-1.4') {
            // add a new homework
            $h = new Homework();
            $h->fromArray([
                'Id' => -2,
                'Name' => $translator->trans('EMBED_1_1_4_NAME', [], 'SK_HOMEWORK'),
                'Description' => $translator->trans('EMBED_1_1_4_DESCRIPTION', [], 'SK_HOMEWORK'),
            ]);
            $hd = new HomeworkDue();
            $hd->fromArray([
                'Id' => -2,
                'DueDate' => date('Y-m-d'),
            ]);
            $hd->setHomework($h);
            $hd->done = false;

            $data[] = $hd;
        }
        if ($step > '1-4.1') {
            // mark previous homework as done
            $data[1]->done = true;
        }
        if ($step > '2-1.1') {
            $h = new Homework();
            $h->fromArray([
                'Id' => -3,
                'Name' => $translator->trans('EMBED_2_1_1_NAME', [], 'SK_HOMEWORK'),
                'Description' => $translator->trans('EMBED_2_1_1_DESCRIPTION', [], 'SK_HOMEWORK'),
            ]);
            $hd = new HomeworkDue();
            $hd->fromArray([
                'Id' => -3,
                'DueDate' => date('Y-m-d'),
            ]);
            $hd->setHomework($h);
            $hd->done = false;

            $data[] = $hd;
        }
        if ($step > '2-7.3') {
            // mark previous homework as done
            $data[2]->done = true;
        }
        if ($step > '3-0') {
            $h = new Homework();
            $h->fromArray([
                'Id' => -4,
                'Name' => $translator->trans('EMBED_3_0_NAME', [], 'SK_HOMEWORK'),
                'Description' => $translator->trans('EMBED_3_0_DESCRIPTION', [], 'SK_HOMEWORK'),
            ]);
            $hd = new HomeworkDue();
            $hd->fromArray([
                'Id' => -4,
                'DueDate' => date('Y-m-d'),
            ]);
            $hd->setHomework($h);
            $hd->done = false;

            $data[] = $hd;
        }

        if ($step > '1-3.4') {
            $starterKitHomeworks = $request->getSession()->get('starter_kit_homeworks', []);
            if (count($starterKitHomeworks)) {
                $actualHomeworkDues = HomeworkDueQuery::create()
                    ->filterByHomeworkId($starterKitHomeworks)
                    ->find()
                ;
                foreach ($actualHomeworkDues as $actualHomeworkDue) {
                    $data[] = $actualHomeworkDue;
                }
            }
        }

        return $data;
    }

}
