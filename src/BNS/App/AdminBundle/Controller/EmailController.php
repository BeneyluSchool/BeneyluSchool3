<?php

namespace BNS\App\AdminBundle\Controller;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use BNS\App\CoreBundle\Model\EmailTemplateQuery;
use BNS\App\CoreBundle\Form\Type\EmailTemplateType;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use BNS\App\CoreBundle\Annotation\Rights;
use Criteria;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;


/**
 * Controller pour toutes les actions liées à la manipulation des templates de mail
 * @Route("/email")
 */

class EmailController extends Controller
{
	/**
	 * Liste des gabarits
	 * @Route("/", name="BNSAppAdminBundle_email", options={"expose"=true})
	 * @Template()
	 * @Rights("ADMIN_ACCESS")
	 */
    public function indexAction()
    {
		return array('emails' => EmailTemplateQuery::create()->joinWithI18n($this->get('bns.right_manager')->getLocale())->find());
    }
	
	/**
	 * Edition d'un template d'Email : pas de création
	 * @Route("/editer/{uniqueName}", name="BNSAppAdminBundle_email_edit", options={"expose"=true})
	 * @Template()
	 * @Rights("ADMIN_ACCESS")
	 */
    public function editAction($uniqueName)
    {
		$emailTemplate = EmailTemplateQuery::create()->joinWithI18n($this->get('bns.right_manager')->getLocale())->findOneByUniqueName($uniqueName);
        $form = $this->createForm(new EmailTemplateType(), $emailTemplate);
		$request = $this->getRequest();
        if ($request->isMethod('POST')) {
            $form->bindRequest($request);
            if ($form->isValid()){
				$emailTemplate->save();
				return $this->redirect($this->generateUrl('BNSAppAdminBundle_email'));
            }
        }
        return array(
            'form' => $form->createView(),
			'emailTemplate' => $emailTemplate
        );
    }
}