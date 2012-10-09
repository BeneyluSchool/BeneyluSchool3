<?php

namespace BNS\App\ClassroomBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Form\Type\CustomGroupType;
use BNS\App\CoreBundle\Form\Type\AttributeHomeMessageType;
use BNS\App\CoreBundle\Form\Model\AttributeHomeMessageFormModel;
use BNS\App\CoreBundle\Model\GroupTypeQuery;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\CoreBundle\Model\UserPeer;

/**
 * @author Eric
 */
class BackController extends Controller
{
	/**
	 * @Route("/", name="BNSAppClassroomBundle_back")
	 * @Rights("CLASSROOM_ACCESS_BACK")
	 */
	public function indexAction()
	{
            // Check des droits
            $rightManager = $this->get('bns.right_manager');
			$kernelDir = $this->container->getParameter('kernel.root_dir');
            include($kernelDir . '/Tools/rss_php.php');
			//Pour initialiser la classe de parse HTML
			$this->get('bns.resource_manager');
			$redis = $this->get('snc_redis.default');
			$cleaned = array();
			//Recherche en cache du flux du blog
			if(!$redis->exists('bns_blog:datas')){
				try{
					$rss = new \rss_php();
					$rss->load('http://blog.beneyluschool.com/feed/');
					$items = array_slice($rss->getItems(true),0,5);
					$i = 0;
					foreach($items as $item){
						$cleaned_item['title'] = $item['title']['value'];
						$cleaned_item['link'] = $item['link']['value'];
						$cleaned_item['description'] = str_replace("&#8217;","'",$item['description']['value']);
						$html =  file_get_html($item['link']['value']);
						$image = $redis->get('bns_blog:image:' . $cleaned_item['link']);
						if(!$image){
							$image = $html->find('img');
							$image = $image[0]->src;
							$redis->set('bns_blog:image:' . $cleaned_item['link'],$image);
						}
						$cleaned_item['image'] = $image;
						$redis->set('bns_blog:feed_' . $i,  json_encode($cleaned_item));
						$i++;
					}
				}catch(Exception $e){}
				$redis->set('bns_blog:datas',1);
				$redis->expire('bns_blog:datas',12 * 3600);
			}
			$i = 0;
			$data = $redis->get('bns_blog:feed_' . $i);
			while($data){
				$cleaned[] = json_decode($data);
				$i ++;
				$data = $redis->get('bns_blog:feed_' . $i);
			}
			
			$lastUserConnected = $this->get('bns.group_manager')
				->setGroup($this->get('bns.right_manager')->getCurrentGroup())
				->getLastUsersConnected();
			
			$activationRoles = GroupTypeQuery::create()->filterBySimulateRole(true)->orderByType(\Criteria::DESC)->findByType(array('PUPIL','PARENT'));

            $form = $this->createForm(new AttributeHomeMessageType(), new AttributeHomeMessageFormModel($this->get('bns.right_manager')->getCurrentGroup()));

            if ('POST' == $this->getRequest()->getMethod())
            {
                $form->bindRequest($this->getRequest());
                if ($form->isValid())
                {
                    $form->getData()->save();
					$this->get('session')->setFlash('update_home_message_success', 'Le message d\'accueil de votre classe a bien été mis à jour.');
                }
            }

            return $this->render('BNSAppClassroomBundle:Back:index.html.twig', array(
                'classroom'				=> $rightManager->getCurrentGroup(),
                'feed_items'			=> $cleaned,
                'form'					=> $form->createView(),
				'activationRoles'		=> $activationRoles,
				'last_users_connected'	=> $lastUserConnected
            ));
	}
	
	/**
	 * @Route("/{slug}/edit", name="BNSAppClassroomBundle_back_edit")
	 * 
	 * @param String $slug
	 */
	public function editAction($slug)
	{
		$classroom = $this->getClassroomBySlug($slug);
		$customGroupType = new CustomGroupType('CLASSROOM');
		$form = $this->createForm($customGroupType, $customGroupType->convertGroupToCustomGroupTypeArray($classroom));

		$request = $this->getRequest();
		if ('POST' === $request->getMethod()) {
			$form->bindRequest($request);
			if ($form->isValid())
			{
				$customGroupType->save($form->getData(), $classroom);

				return new RedirectResponse($this->generateUrl('BNSAppClassroomBundle_back_home', array('slug' => $classroom->getSlug())));
			}
		}

		return array(
			'classroom' => $classroom, 
			'form' => $form->createView()
		);
	}
}