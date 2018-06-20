<?php

namespace BNS\App\MainBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Class AppController
 *
 * @package BNS\App\MainBundle\Controller
 *
 * TODO: ng5 update apps urls
 * @Route("/app")
 */
class AppController extends Controller
{

    /**
     * Bridge between Symfony and angularjs routers. It only serves as a hook to declare and generate routes for angularjs apps.
     *
     * @see NgController
     *
     * @Route("/{rest}/", name="BNSAppMainBundle_front", requirements={"rest"="|.+"}, defaults={"rest": ""})
     * @Route("/#/media-library/boot", name="BNSAppMediaLibraryBundle_front")
     * @Route("/#/media-library/dossiers/{slug}", name="BNSAppMediaLibraryBundle_user_folder")
     * @Route("/#/media-library/medias/{mediaId}", name="BNSAppMediaLibraryBundle_front_media")
     * @Route("/#/media-library/medias/{groupId}-{activityName}", name="bns_activity_front")
     * @Route("/#/media-library/dossiers/external-{groupId}", name="bns_medialibrary_resource_spot_folder")
     * @Route("/#/media-library/boot", name="BNSAppMediaLibraryBundle_back")
     * @Route("/#/user-directory", name="BNSAppUserDirectoryBundle_front")
     * @Route("/#/user-directory", name="BNSAppUserDirectoryBundle_back")
     * @Route("/#/workshop", name="BNSAppWorkshopBundle_front")
     * @Route("/#/statistics", name="BNSAppStatisticsBundle_front")
     * @Route("/#/calendar", name="BNSAppCalendarBundle_front")
     * @Route("/#/calendar/manage", name="BNSAppCalendarBundle_back")
     * @Route("/#/homework", name="BNSAppHomeworkBundle_front")
     * @Route("/#/homework/manage", name="BNSAppHomeworkBundle_back")
     * @Route("/#/lunch/", name="BNSAppLunchBundle_front")
     * @Route("/#/lunch/manage/", name="BNSAppLunchBundle_back")
     * @Route("/#/messaging", name="BNSAppMessagingBundle_front")
     * @Route("/#/messaging/manage", name="BNSAppMessagingBundle_back")
     * @Route("/#/breakfast-tour", name="breakfast_tour_app")
     * @Route("/#/builders", name="builders_front")
     * @Route("/#/builders/manage", name="builders_back")
     * @Route("/#/embed/tour", name="tour_front")
     * @Route("/#/embed/pssst", name="pssst_front")
     * @Route("/#/search", name="BNSAppSearchBundle_front")
     * @Route("/#/search/manage", name="BNSAppSearchBundle_back")
     * @Route("/#/campaign", name="BNSAppCampaignBundle_front")
     * @Route("/#/two-degrees", name="two_degrees_front")
     * @Route("/#/minisite/{slug}", name="BNSAppMiniSiteBundle_front", defaults={"slug":null})
     * @Route("/#/minisite/{slug}/{page}", name="BNSAppMiniSiteBundle_front_page")
     * @Route("/#/space-ops", name="space_ops_front")
     * @Route("/#/account", name="BNSAppAccountBundle_front")
     * @Route("/#/account/link", name="account_link")
     * @Route("/#/account/link-parent", name="account_link_parent")
     * @Route("/#/account/password/change", name="account_password_change")
     * @Route("/#/circus-birthday", name="circus_birthday_front")
     * @Route("/#/archeology", name="archeology_front")
     * @Route("/#/olympics", name="olympics_front")
     * @Route("/#/olympics-training", name="olympics_training_front")
     * @Route("/#/lsu", name="BNSAppLsuBundle_front")
     * @Route("/#/lsu/manage", name="BNSAppLsuBundle_back")
     * @Route("/#/competition/competition", name="competition_front")
     * @Route("/#/competition/course", name="course_front")
     * @Route("/#/competition/competition/manage", name="competition_back")
     * @Route("/#/competition/course/manage", name="course_back")
     * @Route("/#/competition/show/{id}", name="BNSAppCompetitionBundle_details")
     * @Route("/#/competition/show/{id}/book/{bookId}", name="BNSAppCompetitionBundle_bookDetails")
     *
     * @Template("BNSAppMainBundle:App:index.html.twig")
     *
     * @param Request $request
     * @param string $rest
     * @return RedirectResponse
     */
    public function indexAction(Request $request, $rest = null)
    {
        // TODO: ng5 remove this
        return [
            'isEmbed' => $request->get('embed', false),
        ];

        if ($rest) {
            $rest = 'app/'.$rest;
        } else {
            // router matched an app-specific url, without populating rest param of default route
            $rest = str_replace('/ng/'.$request->getLocale().'/', '', $request->getPathInfo());
        }

        // redirect from old app to new app
        return $this->redirect($this->generateUrl('ng_index', [
            '_locale' => $this->get('bns.locale_manager')->slugify($request->getLocale()),
            'rest' => $rest,
        ]));
    }

    /**
     * @Route("/dev")
     *
     * @Template("BNSAppMainBundle:App:dev.html.twig")
     *
     * @param $request
     *
     * @return array
     */
    public function devAction(Request $request)
    {
        $choices = [
            1 => 'Label of choice 1',
            2 => 'Label 2',
            'three' => 'Label choice 3',
            4 => '4'
        ];

        $form = $this->createFormBuilder()
            ->setErrorBubbling(true)
            ->add('field_text', 'text', array(
                'constraints' => array(
                    new Length(array(
                        'min' => 10,
                        'minMessage' => 'INVALID_TOO_SHORT',
                        'max' => 20,
                        'maxMessage' => 'INVALID_TOO_LONG',
                    )),
                    new Regex(array(
                        'pattern' => '/[a-z\']+/',
                        'htmlPattern' => '[a-z\']*',
                    )),
                ),
                'attr' => array(
                    'minlenght' => 10,
                    'maxlength' => 20,
                    'pattern' => '[a-z\']*',
                ),
                'proxy' => true,
                'error_bubbling' => true,
            ))
            ->add('field_textarea', 'textarea', array(
                'proxy' => true,
            ))
            ->add('field_integer', 'integer', array(
                'constraints' => array(
                    new NotBlank(),
                ),
                'attr' => array(
                    'min' => 1,
                    'max' => 10,
                ),
                'proxy' => true,
                'error_bubbling' => true,
            ))
            ->add('field_number', 'number')
            ->add('field_email', 'email')
            ->add('field_money', 'money')
            ->add('field_url', 'url')
            ->add('field_search', 'search')
            ->add('field_percent', 'percent')
            ->add('field_select', 'choice', array(
                'choices' => $choices,
                'empty_value' => '',
                'proxy' => true,
            ))
            ->add('field_select_2', 'choice', array(
                'choices' => $choices,
                'empty_value' => '',
                'required' => false,
            ))
            ->add('field_select_multiple', 'choice', array(
                'choices' => $choices,
                'multiple' => true,
            ))
            ->add('field_radios', 'choice', array(
                'choices' => $choices,
                'expanded' => true,
                'proxy' => true,
            ))
            ->add('field_checkboxes', 'choice', array(
                'choices' => $choices,
                'expanded' => true,
                'multiple' => true,
                'proxy' => true,
            ))
            ->add('field_checkboxes_2', 'choice', array(
                'choices' => $choices,
                'expanded' => true,
                'multiple' => true,
            ))
            ->add('field_date', 'date', array(
                'widget' => 'single_text',
                'proxy' => true,
            ))
            ->add('field_time', 'time', array(
                'widget' => 'single_text',
            ))
            ->add('field_datetime', 'datetime', array(
                'widget' => 'single_text',
            ))
            ->add('field_submit', 'submit')
            ->getForm();

        $form->handleRequest($request);

        return [
            'form' => $form->createView(),
        ];
    }

}
