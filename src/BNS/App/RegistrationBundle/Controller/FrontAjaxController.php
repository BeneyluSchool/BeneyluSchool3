<?php

namespace BNS\App\RegistrationBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Response;

use BNS\App\CoreBundle\Annotation\Anon;
use BNS\App\RegistrationBundle\Model\SchoolInformationQuery;
use BNS\App\RegistrationBundle\Model\SchoolInformationPeer;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class FrontAjaxController extends Controller
{
	/**
	 * @Route("/rechercher-ecole", name="registration_search_school", options={"expose"=true})
	 * @Anon
	 */
	public function searchSchoolAction()
	{
        /**
         * Deprecated
         */
        return $this->render($this->generateUrl('home'));

		if (!$this->getRequest()->isMethod('POST') || !$this->getRequest()->isXmlHttpRequest()) {
			throw new NotFoundHttpException('This page excepts AJAX & POST header !');
		}
		
		$data = $this->getRequest()->get('data', null);
		if (null == $data) {
			throw new \InvalidArgumentException('The parameter "data" is missing !');
		}
		
		$schoolInfos = SchoolInformationQuery::create('si')
			->joinWith('Group', \Criteria::LEFT_JOIN)
			->condition('zipcode', 'si.ZipCode = ?', $data)
			->condition('uai', 'si.Uai LIKE ?', $data . '%')
			->combine(array('zipcode', 'uai'), \Criteria::LOGICAL_OR)
			->where('si.status = ?', SchoolInformationPeer::STATUS_VALIDATED)
		->find();
		
		if (isset($schoolInfos[0])) {
			// Sort by zip code and uai code
			$schoolsUAI = array();
			$schoolsZipCode = array();

			foreach ($schoolInfos as $school) {
				if ($school->getZipCode() == $data) {
					$schoolsZipCode[] = $school;
				}
				else {
					$schoolsUAI[] = $school;
				}
			}

			$html = $this->renderView('BNSAppRegistrationBundle:Ajax:search_school.html.twig', array(
				'schoolsUAI'		=> $schoolsUAI,
				'schoolsZipCode'	=> $schoolsZipCode
			));
		}
		else {
			$html = $this->renderView('BNSAppRegistrationBundle:Ajax:search_school_not_found.html.twig');
		}
		
		return new Response(json_encode(array(
			'is_found'	=> null != $schoolInfos,
			'html'		=> $html
		)));
	}
}