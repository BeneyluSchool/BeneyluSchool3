<?php

namespace BNS\App\LunchBundle\ApiController;

use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use BNS\App\CoreBundle\Controller\BaseApiController;
use BNS\App\LunchBundle\Form\Type\LunchWeekType;
use BNS\App\LunchBundle\Model\LunchDayQuery;
use BNS\App\LunchBundle\Model\LunchWeek;
use BNS\App\LunchBundle\Model\LunchWeekQuery;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Util\Codes;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LunchWeekApiController extends BaseApiController
{
    /**
     * @ApiDoc(
     *  section="Cantine",
     *  resource=false,
     *  description="Récupérer les menus de la semaine",
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
     *      404 = "Les menus de cette semaine n'ont pas été trouvés."
     *  }
     * )
     * @Rest\Get("/{date}")
     * @Rest\View(serializerGroups={"Default", "detail"})
     * @Rights("LUNCH_ACCESS")
     *
     * @param string $date
     * @return LunchWeek
     */
    public function getWeekMenusAction($date)
    {
        if (!$this->isMonday($date)) {
            return $this->view(null, Codes::HTTP_BAD_REQUEST);
        }

        $group = $this->get('bns.right_manager')->getCurrentGroup();

        $week = LunchWeekQuery::create()
            ->filterByGroupId($group->getId())
            ->findOneByDateStart($date);

        if (!$week){
            throw new NotFoundHttpException();
        }
        $this->get('stat.lunch')->visit();

        return $week;
    }

     /**
     * @ApiDoc(
     *  section="Cantine",
     *  resource=false,
     *  description="Récupérer les menus du mois",
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
     *      404 = "Les menus de cette semaine n'ont pas été trouvés."
     *  }
     * )
     * @Rest\Get("/{date}/monthly")
     * @Rest\View(serializerGroups={"Default", "detail"})
     * @Rights("LUNCH_ACCESS")
     *
     * @param string $date
     * @return LunchWeek
     */
    public function getMonthMenusAction($date)
    {

        $group = $this->get('bns.right_manager')->getCurrentGroup();

        $dateMonth = \DateTime::createFromFormat("Y-m-d", $date);
        $dateMonth = $dateMonth->format("Y-m");

        $week = LunchWeekQuery::create()
            ->filterByGroupId($group->getId())
            ->filterByDateStart(array("min" => $dateMonth."-01", "max" => $dateMonth."-31"))
            ->orderByDateStart()
            ->find();

        if (!$week){
            throw new NotFoundHttpException();
        }
        $this->get('stat.lunch')->visit();
        return $week;
    }

    /**
     * @ApiDoc(
     *  section="Cantine",
     *  resource=false,
     *  description="Soumettre les menus de la semaine",
     *  requirements = {
     *      {
     *          "name" = "date",
     *          "dataType" = "date",
     *          "description" = "La date du premier jour de la semaine (lundi)"
     *      }
     *  },
     *  statusCodes = {
     *      204 = "Ok",
     *      400 = "Les données soumises sont invalides"
     *  }
     * )
     * @Rest\Post("/{date}")
     * @Rest\View(serializerGroups={"Default", "detail"})
     * @Rights("LUNCH_ACCESS_BACK")
     */
    public function postWeekMenusAction($date, Request $request)
    {
        if (!$this->isMonday($date)) {
            return $this->view(null, Codes::HTTP_BAD_REQUEST);
        }

         $group = $this->get('bns.right_manager')->getCurrentGroup();

         if (null != LunchWeekQuery::create()->filterByGroupId($group->getId())->findOneByDateStart($date)){
             return $this->view(null, Codes::HTTP_CONFLICT);
         }

        $week = $this->get('bns.lunch_manager')->createWeek();

        return $this->restForm(new LunchWeekType(), $week, array(
            'csrf_protection' => false,
        ), null, function (LunchWeek $week, $form) use ($date, $group) {
            $week->setDateStart($date);
            $week->setGroupId($group->getId());
            $week->save();
        });
    }

    /**
     * @ApiDoc(
     *  section="Cantine",
     *  resource=false,
     *  description="Modifier les menus de la semaine",
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
     *      404 = "Les menus n'ont pas été trouvés"
     *  }
     * )
     * @Rest\Patch("/{date}")
     * @Rest\View(serializerGroups={"Default", "detail"})
     * @Rights("LUNCH_ACCESS_BACK")
     */
    public function patchWeekMenusAction($date, Request $request)
    {
        $group = $this->get('bns.right_manager')->getCurrentGroup();
        $week = LunchWeekQuery::create()
            ->filterByGroupId($group->getId())
            ->findOneByDateStart($date)
        ;

        if (!$week){
            return $this->view(null, Codes::HTTP_NOT_FOUND);
        }

        return $this->restForm(new LunchWeekType(), $week, array(
            'csrf_protection' => false,
        ));
    }

    /**
     * @ApiDoc(
     *  section="Cantine",
     *  resource=false,
     *  description="Supprimer les menus de la semaine",
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
     *      404 = "Aucun menu pour cette semaine"
     *  }
     * )
     * @Rest\Delete("/{date}")
     * @Rest\View(serializerGroups={"Default", "detail"})
     * @Rights("LUNCH_ACCESS_BACK")
     */
    public function deleteWeekMenusAction($date)
    {
        $group = $this->get('bns.right_manager')->getCurrentGroup();

        $week = LunchWeekQuery::create()->filterByGroupId($group->getId())->findOneByDateStart($date);
        $menus = LunchDayQuery::create()->findByWeekId($week->getId());

        foreach ($menus as $menu){
            $menu->delete();
        }
        $week->delete();

        return $this->view(null, Codes::HTTP_NO_CONTENT);
    }

}
