<?php

namespace BNS\App\ProfileBundle\Controller;

use BNS\App\CoreBundle\Annotation\RightsSomeWhere;
use BNS\App\CoreBundle\Model\ProfileFeedPeer;
use BNS\App\CoreBundle\Model\ProfileFeedQuery;
use BNS\App\ProfileBundle\Form\Model\ProfileFeedFormModel;
use BNS\App\ProfileBundle\Form\Type\ProfileFeedType;
use Criteria;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;

class BackFeedStatusController extends Controller
{
    /**
     * @Route("/", name="BNSAppProfileBundle_back_status")
	 * @RightsSomeWhere("PROFILE_ACCESS_BACK")
     * 
     * @return type
     */
    public function indexAction()
    {
    	$user = $this->getUser();
        // On récupère les 5 dernières publications
        $feeds = ProfileFeedQuery::create()
            ->joinWith('ProfileFeedStatus', Criteria::LEFT_JOIN)
			->joinWith('ProfileFeedStatus.Resource', Criteria::LEFT_JOIN)
            ->joinWith('ProfileFeedResource', Criteria::LEFT_JOIN)
            ->add(ProfileFeedPeer::PROFILE_ID, $user->getId())
            ->orderByDate(Criteria::DESC)
        ->find();
		
		//$feeds = ProfileCommentQuery::populateRelation($feeds);
		$user->getProfile()->replaceProfileFeeds($feeds);

        $form = $this->createForm(new ProfileFeedType(), new ProfileFeedFormModel());
        return $this->render('BNSAppProfileBundle:BackFeedStatus:back_status_index.html.twig', array(
            'user'  => $user,
            'form'  => $form->createView(),
            'feeds' => $feeds,
        ));
    }
	
	/**
	 * @Route("/publier-statut", name="BNSAppProfileBundle_back_status_post")
	 * @RightsSomeWhere("PROFILE_ACCESS_BACK")
	 * 
	 * @return RedirectResponse 
	 */
	public function postAction()
	{
        // TODO: Check des droits de publication
        //$user = $this->getUser();
        
        if ('POST' == $this->getRequest()->getMethod() && null != $this->getRequest()->get(ProfileFeedType::FORM_NAME))
        {
            $form = $this->createForm(new ProfileFeedType(), new ProfileFeedFormModel());
            $form->bindRequest($this->getRequest());
            
            $errors = $this->get('validator')->validate($form->getData());
			if (isset($errors[0])) {
				throw new \InvalidArgumentException($errors[0]->getMessage());
			}
            else {
				$form->getData()->save();
            }
        }

        return new RedirectResponse($this->generateUrl('BNSAppProfileBundle_back_status', array()));
	}
}