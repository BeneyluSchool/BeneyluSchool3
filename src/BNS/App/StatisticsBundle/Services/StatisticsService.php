<?php
namespace BNS\App\StatisticsBundle\Services;
use Symfony\Component\DependencyInjection\Container;

/**
 * StatisticsService est le service template de statistiques
 * on doit hérité de ce service lorsque l'on crée un
 * nouveau service pour un nouveau bundle
 *
 * @author Florian Rotagnon <florian.rotagnon@atos.net>
 */
abstract class StatisticsService
{

    private $dateFormat;
    private $hoursFormat;
    /** @var Container  */
    private $container;

    protected $cascadeParentGroup = true;

    CONST NOT_AUTHORISED_USER = 'test';

    /**
     * constructeur
     * @param Container $container
     */
    public function __construct($container)
    {
        //crée le format des dates
        $this->dateFormat = date("Y-m-d");
        $this->hoursFormat = date("H-i");
        $this->container = $container;
    }

    /**
     * méthode d'incrémentation d'un indicateur
     * @param string $indicateurId
     * @param mixed $info
     */
    public function increment($indicateurId, $info = null)
    {
        if (!$this->canUseStat()) {
            return false;
        }

        $group = $this->container->get("bns.right_manager")->getCurrentGroup();

        if ($group) {
            $groupId = $group->getId();
        } else {
            //Inscription sur www.beneyluschool.net
            $groupId = 1;
        }

        $user = $this->getUser();
        if ($user) {
            //le role courant est le plus haut role
            $roleId = $user->getHighRoleId();
        } else {
            //Enseignant
            $roleId = 7;
        }

        $this->container->get("main_service_bns_app_statistics_bundle")->increment(
            $indicateurId,
            $this->dateFormat,
            $this->hoursFormat,
            $groupId,
            $roleId,
            $info
        );
        if ($user && $this->cascadeParentGroup) {
            $gm = $this->container->get("bns.right_manager")->getCurrentGroupManager();
            foreach ($gm->getAncestors() as $ancestor) {
                $this->container->get("main_service_bns_app_statistics_bundle")->increment(
                    $indicateurId,
                    $this->dateFormat,
                    $this->hoursFormat,
                    $ancestor->getId(),
                    $roleId,
                    $info
                );
            }
        }
    }

    /**
     * méthode de décrémentation d'un indicateur
     * @param type $indicateurId
     */
    public function decrement($indicateurId)
    {
        if (!$this->canUseStat()) {
            return false;
        }
        $groupId = $this->container->get("bns.right_manager")->getCurrentGroup()->getId();
        $gm = $this->container->get("bns.right_manager")->getCurrentGroupManager();
        //le role courant est le plus haut role
        $roleId = $this->getUser()->getHighRoleId();

        $this->container->get("main_service_bns_app_statistics_bundle")->decrement(
            $indicateurId,
            $this->dateFormat,
            $this->hoursFormat,
            $groupId,
            $roleId
        );
        if ($this->cascadeParentGroup) {
            foreach ($gm->getAncestors() as $ancestor) {
                $this->container->get("main_service_bns_app_statistics_bundle")->decrement(
                    $indicateurId,
                    $this->dateFormat,
                    $this->hoursFormat,
                    $ancestor->getId(),
                    $roleId
                );
            }
        }
    }

    public function canUseStat()
    {
        if ($this->getUser()) {
            return $this->getUser()->getLogin() != self::NOT_AUTHORISED_USER;
        } else {
            return true;
        }
    }

    /**
     * Enable/disable parent cascading
     * @param $cascadeParentGroup
     */
    protected function disableCascadeParentGroup()
    {
        $this->cascadeParentGroup = false;
    }

    /**
     * Enable/disable parent cascading
     * @param $cascadeParentGroup
     */
    protected function enableCascadeParentGroup()
    {
        $this->cascadeParentGroup = true;
    }

    /**
     * récupère l'utilisateur courant
     * @return null
     * @throws \LogicException
     */
    private function getUser()
    {
        if ($this->container->get("security.context") == null) {
            throw new \LogicException('The SecurityBundle is not registered in your application.');
        }

        if (null === $token = $this->container->get("security.context")->getToken()) {
            return null;
        }

        if (!is_object($user = $token->getUser())) {
            return null;
        }

        return $user;
    }
}
