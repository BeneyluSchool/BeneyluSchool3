<?php

namespace BNS\App\AdminBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;


use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;

use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\CoreBundle\Form\Type\GroupTypeType;
use BNS\App\CoreBundle\Model\GroupType;
use BNS\App\CoreBundle\Model\GroupTypeI18n;

/**
 * @Route("/type-de-groupe")
 */

class GroupTypeController extends Controller
{
	/**
	 * Page d'accueil de la gestion des types de groupe;
	 * tous les types de groupe sont listés dans un tableau 
	 * 
	 * @Route("/", name="BNSAppAdminBundle_group_type")
	 * @Template()
	 * @Rights("ADMIN_ACCESS")
	 */
    public function indexAction()
    {
		return array(
			'groupTypes' => GroupTypeQuery::create()->joinWithI18n($this->get('bns.right_manager')->getLocale())->find()
        );
    }

    /**
     * Page détaillant les informations concernant le type de groupe $groupTypeI18n
     * 
	 * @param     $groupTypeI18n objet de type Group que l'on récupère grâce au ParamConverter de Propel 
	 * 			  (Propel retrouve l'objet grâce au slug du groupe); on utilise la classe groupTypeI18n car le slug se trouve sur la table group_type_i18n
	 *
     * @Route("/fiche/{id}", name="BNSAppAdminBundle_group_type_details")
     * @Template()
	 * @Rights("ADMIN_ACCESS")
     */
	public function detailsAction($id)
    {
		$groupType = GroupTypeQuery::create()->joinWithI18n($this->get('bns.right_manager')->getLocale())->findPk($id);
		
        return array(
			'groupType'	=> $groupType
        );
    }
	
	/**
	 * @Route("/ajouter", name="BNSAppAdminBundle_group_type_add")
	 * @Template()
	 * @Rights("ADMIN_ACCESS")
     */
	public function addAction()
	{
		$groupType = new GroupType();
		$groupTypeI18n = new GroupTypeI18n();
		$groupTypeI18n->setLang('fr');
		$groupType->addGroupTypeI18n($groupTypeI18n);
		
		$form = $this->createForm(new GroupTypeType(),$groupType);
				
		if ($this->getRequest()->getMethod() == 'POST')
		{
			$form->bindRequest($this->getRequest());
			if ($form->isValid())
			{
				$gm = $this->get('bns.group_manager');
				$groupTypeParams = array(
					'type' => $groupType->getType(),
					'label' => $groupTypeI18n->getLabel('fr'),
					'centralize' => $groupType->getCentralize(),
					'simulate_role' => $groupType->getSimulateRole(),
					'domain_id' => $this->container->getParameter('domain_id'),
					'description' => $groupTypeI18n->getDescription('fr'),
				);
				$gm->createGroupType($groupTypeParams);
				return $this->redirect($this->generateUrl('BNSAppAdminBundle_group_type'));
			}
		}
		return array('form' => $form->createView());
	}
}