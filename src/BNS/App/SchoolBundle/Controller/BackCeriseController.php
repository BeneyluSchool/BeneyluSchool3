<?php

namespace BNS\App\SchoolBundle\Controller;

use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\GroupBundle\Controller\BackController;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;


/**
 * @Route("/gestion/cerise")
 */

class BackCeriseController extends Controller
{

    protected function checkKey($key,$time,$extra = null)
    {
        $current = date('U');
        $diff = $current - $time;
        $diff = abs($diff);

        if($diff > 7200)
        {
            throw new NotFoundHttpException("L'intervalle de temps est trop grand, la clé n'est pas valide.");
        }

        if(md5($time . $this->container->getParameter('cerise_secret_key') . $extra) != $key)
        {
            throw new NotFoundHttpException("La clé n'est pas valide.");
        }

        return true;
    }

    protected function hasCerise($school)
    {
        $gm = $this->get('bns.group_manager');
        if (!$school) {
            $school = $this->get('bns.right_manager')->getCurrentGroupManager();
        }
        $gm->setGroup($school);
        $env = $gm->getEnvironment();
        $value = true;
        $authorisedEnv = $this->container->getParameter('authorised.cerise.env');
        if(!in_array($env->getId(),$authorisedEnv))
        {
            $value = false;
        }

        $uai = $gm->getAttribute('UAI');
        $uaiList = unserialize($gm->getAttribute('CERISE_LIST'));


        if(!in_array($uai,$uaiList))
        {
            $value = false;
        }


        return $this->container->hasParameter('has_cerise') && $this->container->getParameter('has_cerise') == true && $value;
    }

    /**
     * @Route("/login", name="BNSAppSchoolBundle_back_cerise_login")
     */
    public function loginAction()
    {
        $rightManager = $this->get('bns.right_manager');
        $group = $this->get('bns.right_manager')->getCurrentGroup();
        $user = $this->getUser();

        $rightManager->forbidIf(!$rightManager->hasCerise($group, true));
        $groupManager = $this->get('bns.group_manager')->setGroup($group);

        $date = date('U');

        return $this->redirect('https://www.cerise-prim.fr/'. $groupManager->getAttribute('UAI') .'/?id=' . $user->getCeriseId() . '&key=' . md5($this->container->getParameter('cerise_secret_key') . $user->getCeriseId() . $date) . '&time=' . $date);

    }

	/**
	 * @Route("/", name="BNSAppSchoolBundle_back_cerise_index")
	 * @Template()
     * @Rights("SCHOOL_ACCESS_BACK")
	 */
	public function indexAction()
	{
		$this->get('bns.right_manager')->forbidIf(!$this->get('bns.right_manager')->hasCerise());
        $currentGroup = $this->get('bns.right_manager')->getCurrentGroup();
        $hasGroupBoard = $this->get('bns.group_manager')->setGroup($currentGroup)->getProjectInfo('has_group_blackboard');

		$group = $this->get('bns.right_manager')->getCurrentGroup();

        $ceriseList = unserialize($group->getAttribute('CERISE_LIST'));

		if($this->getRequest()->isMethod('POST')){
			$new_cerise_list = array();
            if(is_array($this->getRequest()->get('uai')))
            {
                foreach($this->getRequest()->get('uai') as $uai){
                    if(trim($uai) != "")
                    {
                        $new_cerise_list[] = $uai;
                    }
                }
            }
			$group->setAttribute('CERISE_LIST',  serialize($new_cerise_list));
            $this->get('session')->getFlashBag()->add('success', $this->get('translator')->trans('FLASH_LIST_SAVED', array(), 'GROUP'));
			$ceriseList = $new_cerise_list;
		}
		return array(
            'group' => $group,
            'cerise_list' => $ceriseList,
            'hasCerise' => $this->get('bns.right_manager')->hasCerise(),
            'hasGroupBoard' => $hasGroupBoard
        );
	}



    /**
     * @Route("/page/ecole", name="BNSAppSchoolBundle_back_cerise_school")
     * @Template()
     * @Rights("SCHOOL_ACCESS_BACK")
     */
    public function schoolAction(Request $request)
    {
        $this->get('bns.right_manager')->forbidIf(!$this->get('bns.right_manager')->hasCerise());

        return array(
            'hasCerise' => $this->get('bns.right_manager')->hasCerise()
        );
    }

    /**
     * @Route("/update", name="BNSAppSchoolBundle_back_cerise_update")
     * @Template()
     * @Rights("SCHOOL_ACCESS_BACK")
     */
    public function updateAction(Request $request)
    {
        $this->get('bns.right_manager')->forbidIf(!$this->get('bns.right_manager')->hasCerise());

        $group = $this->get('bns.right_manager')->getCurrentGroupManager();

        $date = date('U');

        return $this->redirect('https://www.cerise-prim.fr/supportent/beneylu/majetab.php?rne='. $group->getAttribute('UAI') .'&key=' . md5($group->getAttribute('UAI') . $this->container->getParameter('cerise_secret_key')));
    }

    /**
     * @Route("/{uai}", name="BNSAppSchoolBundle_back_cerise_xml")
     * @Template("BNSAppSchoolBundle:BackCerise:xml.xml.twig")
     */
    public function xmlAction($uai,Request $request)
    {
        $school = GroupQuery::create()->filterBySingleAttribute('UAI',$uai)->findOne();

        if(!$school)
        {
            throw new NotFoundHttpException("Cet UAI n'existe pas.");
        }

        $gm = $this->get('bns.group_manager');
        $gm->setGroup($school);



        //vérification du droit de lecture
        if(!($this->hasCerise($school) && $this->checkKey($request->get('key'),$request->get('time'),$uai)))
        {
            if(!$this->get('bns.right_manager')->hasCerise($school))
            {
                throw new NotFoundHttpException("Cet environnement n'a pas accès à Cerise");
            }else{
                throw new NotFoundHttpException("La clé n'est pas acceptée");
            }
        }

        $gm->setGroup($school);

        $directors = $gm->getUsersByRoleUniqueName('DIRECTOR',true);
        $directorsIds = array();
        foreach($directors as $director)
        {
            $directorsIds[] = $director->getId();
        }

        $classrooms = $gm->getSubgroups(true,false,GroupTypeQuery::create()->findOneByType('CLASSROOM')->getId());
        $thisYearClassrooms = array();
        foreach($classrooms as $classroom)
        {
            if($classroom->getAttribute('CURRENT_YEAR') == $this->container->getParameter('registration.current_year'))
            {
                $thisYearClassrooms[] = $classroom;
            }
        }

        //Toutes les vérifications sont faites on attaque le traitement
        return array(
            'school' => $school,
            'gm' => $gm,
            'classrooms' => $thisYearClassrooms,
            'directorsIds' => $directorsIds
        );
    }
}
