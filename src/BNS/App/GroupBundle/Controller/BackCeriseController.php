<?php

namespace BNS\App\GroupBundle\Controller;

use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use BNS\App\CoreBundle\Annotation\Rights;


/**
 * @Route("/gestion/cerise")
 */

class BackCeriseController extends CommonController
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
     * @Route("/login", name="BNSAppGroupBundle_back_cerise_login")
     * @Template()
     */
    public function loginAction()
    {

        $group = $this->get('bns.right_manager')->getCurrentGroupManager();

        $this->get('bns.right_manager')->forbidIf(!$this->get('bns.right_manager')->hasCerise($group,true));

        $date = date('U');

        return $this->redirect('https://www.cerise-prim.fr/'. $group->getAttribute('UAI') .'/?id=' . $this->get('bns.right_manager')->getUserSession()->getCeriseId() . '&key=' . md5($this->container->getParameter('cerise_secret_key') . $this->get('bns.right_manager')->getUserSession()->getCeriseId() . $date) . '&time=' . $date);

    }

	/**
	 * @Route("/", name="BNSAppGroupBundle_back_cerise_index")
	 * @Template()
	 * @Rights("GROUP_ACCESS_BACK")
	 */
	public function indexAction()
	{
		$this->get('bns.right_manager')->forbidIf(!$this->get('bns.right_manager')->hasCerise());

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
        );
	}



    /**
     * @Route("/page/ecole", name="BNSAppGroupBundle_back_cerise_school")
     * @Template()
     */
    public function schoolAction(Request $request)
    {
        $this->get('bns.right_manager')->forbidIf(!$this->get('bns.right_manager')->hasCerise());
    }

    /**
     * @Route("/update", name="BNSAppGroupBundle_back_cerise_update")
     * @Template()
     */
    public function updateAction(Request $request)
    {
        $this->get('bns.right_manager')->forbidIf(!$this->get('bns.right_manager')->hasCerise());

        $group = $this->get('bns.right_manager')->getCurrentGroupManager();

        $date = date('U');

        return $this->redirect('https://www.cerise-prim.fr/supportent/beneylu/majetab.php?rne='. $group->getAttribute('UAI') .'&key=' . md5($group->getAttribute('UAI') . $this->container->getParameter('cerise_secret_key')));
    }

    /**
     * @Route("/{uai}", name="BNSAppGroupBundle_back_cerise_xml")
     * @Template("BNSAppGroupBundle:BackCerise:xml.xml.twig")
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
