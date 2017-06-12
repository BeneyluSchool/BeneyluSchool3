<?php

namespace BNS\App\ClassroomBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Form\Type\AttributeHomeMessageType;
use BNS\App\CoreBundle\Form\Model\AttributeHomeMessageFormModel;
use BNS\App\CoreBundle\Model\GroupTypeQuery;

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
        $currentGroup = $this->get('bns.right_manager')->getCurrentGroup();

        $hasGroupBoard = $this->get('bns.group_manager')->setGroup($currentGroup)->getProjectInfo('has_group_blackboard');

        $env = $this->get('bns.right_manager')->getCurrentEnvironment();
        $cleaned = array();
        if($env->hasAttribute('BOARD_FEED'))
        {
            $redis = $this->get('snc_redis.default');
            $feed = $env->getAttribute('BOARD_FEED');
            if($feed != null && $feed != "")
            {
                // Recherche en cache du flux du blog
                if (!$redis->exists('feed:' . $feed . ':datas')) {
                    try {
                        $rss = new \rss_php();
                        $rss->load($feed);
                        $items = array_slice($rss->getItems(true), 0, 5);
                        $i = 0;

                        foreach ($items as $item) {
                            $cleaned_item['title'] = $item['title']['value'];
                            $cleaned_item['link'] = $item['link']['value'];
                            $cleaned_item['description'] = str_replace('&#8217;', "'", html_entity_decode($item['description']['value']));
                            $html = \simple_html_dom::file_get_html($item['link']['value']);
                            $image = $redis->get('feed:' . $feed . ':image:' . $cleaned_item['link']);

                            if (!$image) {
                                $image = $html->find('img');
                                $image = $image[0]->src;
                                $redis->set('feed:' . $feed . ':image:' . $cleaned_item['link'], $image);
                            }

                            $cleaned_item['image'] = $image;
                            $redis->set('feed' . $feed . ':feed_' . $i, json_encode($cleaned_item));
                            $i++;
                        }
                    } catch (\Exception $e) {
                        // Nothing
                    }

                    $redis->set('feed:' . $feed . ':datas', 1);
                    $redis->expire('feed:' . $feed . ':datas', 12 * 3600);
                }

                $i = 0;
                $data = $redis->get('feed' . $feed . ':feed_' . $i);

                while ($data && $i <= 10) {
                    $cleaned[] = json_decode($data);
                    $i++;
                    $data = $redis->get('feed' . $feed . ':feed_' . $i);
                }
            }
        }

        $lastUsersLimit = 6;
        if ($this->get('bns_core.application_manager')->isEnabled()) {
            $lastUsersLimit = 20;
        }

		$lastUserConnected = $this->get('bns.group_manager')
			->setGroup($currentGroup)
		->getLastUsersConnected($lastUsersLimit);

		$activationRoles = GroupTypeQuery::create()->filterBySimulateRole(true)->orderByType(\Criteria::DESC)->findByType(array('PUPIL','PARENT'));
		$form = $this->createForm(new AttributeHomeMessageType(), new AttributeHomeMessageFormModel($currentGroup));

		if ('POST' == $this->getRequest()->getMethod()) {
			$form->bind($this->getRequest());

			if ($form->isValid()) {
				$form->getData()->save();
				$this->get('session')->getFlashBag()->add('update_home_message_success', $this->get('translator')->trans('FLASH_HOME_MESSAGE_UPDATED', array(), "CLASSROOM"));
			}
		}

		return $this->render('BNSAppClassroomBundle:Back:index.html.twig', array(
			'classroom'			   => $currentGroup,
			'feed_items'		   => $cleaned,
			'form'				   => $form->createView(),
			'activationRoles'	   => $activationRoles,
			'last_users_connected' => $lastUserConnected,
            'uid'                  => substr(sha1($currentGroup->getId() . $this->container->getParameter('symfony_secret')), 5, 10) . $currentGroup->getId(),
            'hasGroupBoard'       => $hasGroupBoard
		));
	}
}
