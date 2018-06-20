<?php

namespace BNS\App\WorkshopBundle\Controller;

use BNS\App\CoreBundle\Controller\BaseController;
use BNS\App\MediaLibraryBundle\Model\MediaQuery;
use BNS\App\WorkshopBundle\Model\WorkshopDocument;
use BNS\App\WorkshopBundle\Model\WorkshopPageQuery;
use BNS\App\CoreBundle\Annotation\RightsSomeWhere;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class FrontController extends BaseController
{

    /**
     * @Route("/document/{id}/html", name="workshop_html")
     * @param WorkshopDocument $document
     * @return Response
     */
    public function documentHtml(WorkshopDocument $document, Request $request)
    {

        $signUrlService = $this->get('bns.signUrl');
        if (!$signUrlService->isRequestValid($request)) {
            throw new NotFoundHttpException('Page not found');
        }

        $pages = WorkshopPageQuery::create()
            ->filterByWorkshopDocument($document)
            ->joinWith('WorkshopWidgetGroup')
            ->find();

        $layoutManager = $this->get('bns.workshop.layout.manager');

        $index = 1;
        $all = '';
        foreach($pages as $page) {
            $layout = $layoutManager->getForObject($page);
            $rows = [];
            foreach ($layout['zones'] as $key => $zone) {
                @list($rowCode, $zoneCode) = explode('-', $zone['code']);
                if ($zoneCode) {
                    $rows[$rowCode][] = ['zone' => $zoneCode, 'numbers' => $zone['numbers']];
                } else {
                    $rows[$rowCode][] = ['numbers' => $zone['numbers']];
                }
            }
            foreach ($page->getWorkshopWidgetGroups() as $widgetGroup) {
                foreach ($widgetGroup->getWorkshopWidgets() as $widget) {
                    if (($widget->getType() === 'simple' || $widget->getType() === 'multiple') && $widget->getExtendedSetting()) {
                        $choices = [];
                        foreach ($widget->getExtendedSetting()->getChoices() as $choice) {
                            if (isset($choice['media_id'])) {
                                $choice['media'] = MediaQuery::create()
                                    ->findPk($choice['media_id']);
                            }
                            $choices[] = $choice;
                        }
                        $widget->getExtendedSetting()->setChoices($choices);
                    }
                }
            }
            $content = $this->render('BNSAppWorkshopBundle:EPub:row.html.twig', ['page' => $page, 'layout' => $layout, 'rows' => $rows, 'mode' => 'html'])->getContent();

            $all .= $content;
            $index++;
        }

        return new Response($all);
    }



    /**
     * @Route("/document/{id}/export")
     * @RightsSomeWhere("WORKSHOP_ACCESS")
     * @param WorkshopDocument $document
     * @return Response
     */
    public function documentExportAction(WorkshopDocument $document)
    {
        $this->checkFeatureAccess('workshop_export');
        $session = $this->get('session');
        $router = $this->get('router');
        $filename = $document->getLabel();

        // build angular app route
        $url = $router->getContext()->getScheme() . '://'
            . $router->getContext()->getHost()
            . $router->getContext()->getBaseUrl()
            . '/app/?embed=1#/workshop/documents/'
            . $document->getId()
            . '/export'
        ;

        //TO DO : restore when ng 5
//        $url = $router->generate('ng_index', [
//            'rest' => 'app/workshop/documents/'. $document->getId() .'/export',
//            'embed' => 1,
//        ], true);


        $session->save();
        session_write_close();
        $result = $this->get('bns.exporter.pdf')->exportUrl($url, $session);

        return new Response(
            $result,
            200,
            array(
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'.pdf"',
            )
        );
    }

    /**
     * @Route("/document/{id}/export_epub", name="workshop_export_epub")
     * @RightsSomeWhere("WORKSHOP_ACCESS")
     * @param WorkshopDocument $document
     * @return Response
     */
    public function documentExportEPubAction(WorkshopDocument $document)
    {
        $this->checkFeatureAccess('workshop_export');
        $bookManager = $this->get('bns.workshop.epub.manager');
        $book = $bookManager->create($document);

        $pages = WorkshopPageQuery::create()
            ->filterByWorkshopDocument($document)
            ->joinWith('WorkshopWidgetGroup')
            ->find();

        $layoutManager = $this->get('bns.workshop.layout.manager');

        $index = 1;
        foreach($pages as $page) {
            $layout = $layoutManager->getForObject($page);
            $rows = [];
            foreach ($layout['zones'] as $key => $zone) {
                @list($rowCode, $zoneCode) = explode('-', $zone['code']);
                if ($zoneCode) {
                    $rows[$rowCode][] = ['zone' => $zoneCode, 'numbers' => $zone['numbers']];
                } else {
                    $rows[$rowCode][] = ['numbers' => $zone['numbers']];
                }
            }
            foreach ($page->getWorkshopWidgetGroups() as $widgetGroup) {
                foreach ($widgetGroup->getWorkshopWidgets() as $widget) {
                    if (($widget->getType() === 'simple' || $widget->getType() === 'multiple') && $widget->getExtendedSetting()) {
                        $choices = [];
                        foreach ($widget->getExtendedSetting()->getChoices() as $choice) {
                            if (isset($choice['media_id'])) {
                                $choice['media'] = MediaQuery::create()
                                    ->findPk($choice['media_id']);
                            }
                            $choices[] = $choice;
                        }
                        $widget->getExtendedSetting()->setChoices($choices);
                    }
                }
            }
            $content = $this->render('BNSAppWorkshopBundle:EPub:row.html.twig', ['page' => $page, 'layout' => $layout, 'rows' => $rows, 'mode' => 'html'])->getContent();

            $bookManager->addChapter($book, $content, $index);
            $index++;
        }

        return $bookManager->download($book);
    }
}
