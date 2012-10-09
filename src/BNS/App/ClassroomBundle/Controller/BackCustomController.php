<?php

namespace BNS\App\ClassroomBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use BNS\App\ClassroomBundle\Form\Model\EditClassroomFormModel;
use BNS\App\ClassroomBundle\Form\Type\EditClassroomType;

/**
 * @author Eric
 */
class BackCustomController extends Controller
{
	/**
	 * @Route("/", name="BNSAppClassroomBundle_back_custom")
	 */
	public function indexAction()
	{	
		$classroom = $this->get('bns.right_manager')->getCurrentGroup();
		
		return $this->render('BNSAppClassroomBundle:BackCustom:classroom_custom_index.html.twig', array(
			'classroom'				=> $classroom,
			'form'					=> $this->createForm(
											new EditClassroomType(), 
											new EditClassroomFormModel($classroom)
										)->createView(),
			'level_separator_labels' => array(
											0	=> 'Maternelle',
											3	=> 'Elémentaire',
											8	=> 'Collège',
											12	=> 'Lycée',
											15	=> 'Clis'
										)
		));
	}
	
	/**
	 * @Route("/sauvegarder", name="classroom_custom_save")
	 */
	public function saveAction()
	{
		$request = $this->getRequest();
		if (!$request->isMethod('POST')) {
			throw new HttpException(500, 'Request must be POST METHOD!');
		}
		
		$form = $this->createForm(new EditClassroomType(), new EditClassroomFormModel($this->get('bns.right_manager')->getCurrentGroup()));
		$form->bindRequest($request);
		if ($form->isValid()) {
			$form->getData()->save();
		}
		
		return $this->redirect($this->generateUrl('BNSAppClassroomBundle_back_custom'));
	}
}