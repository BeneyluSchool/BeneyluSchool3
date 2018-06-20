<?php

namespace BNS\App\LsuBundle\Controller;

use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\CoreBundle\Controller\BaseController;
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\LsuBundle\Model\Lsu;
use BNS\App\LsuBundle\Model\LsuQuery;
use BNS\App\LsuBundle\Model\LsuTemplate;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\ProcessBuilder;

/**
 * @Route("/export")
 */
class ExportController extends BaseController
{

    /**
     * @Route("/pdf", options={"expose"=true})
     * @Rights("LSU_ACCESS_READ")
     *
     * @param Request $request
     * @return Response
     */
    public function pdfAction(Request $request)
    {
        if (!$this->hasFeature('lsu_export')) {
            throw $this->createAccessDeniedException();
        }
        $session = $this->get('session');
        $router = $this->get('router');

        // build angular app route
        $url = $router->getContext()->getScheme() . '://'
            . $router->getContext()->getHost()
            . $router->getContext()->getBaseUrl()
            . '/app/?embed=1#/lsu/print'
            . '?'.urldecode(http_build_query([
                'templateId' => $request->get('templateId'),
                'userIds' => $request->get('userIds'),
                'ids' => $request->get('ids'),
            ]))
        ;

        $ids = $request->get('ids') ? explode(',', $request->get('ids')) : [];
        $userIds = $request->get('userIds') ? explode(',', $request->get('userIds')) : [];

        if (count($ids) === 1) {
            $lsu = LsuQuery::create()
                ->filterById($ids)
                ->findOne();
            if ($lsu) {
                $user = $lsu->getUser();
                $userFullName = $user->getFirstName() . "_" . $user->getLastName();
            }
        }

        if (count($userIds) === 1) {
            $user = UserQuery::create()
                ->filterById($userIds)
                ->findOne();
            if (null === $user) {
                throw new HttpException(404, 'User not found with id ' . $userIds);
            }
            $userFullName = $user->getFirstName() . "_" . $user->getLastName();
        }

        //TO DO : restore when ng 5
//        $url = $router->generate('ng_index', [
//            'rest' => 'app/lsu/print',
//            'embed' => 1,
//            'templateId' => $request->get('templateId'),
//            'userIds' => $request->get('userIds'),
//            'ids' => $request->get('ids'),
//        ], true);

        $session->save();
        session_write_close();

        $result = $this->get('bns.exporter.pdf')->exportUrl($url, $session);

        if (isset($userFullName)) {
            return $this->makePdfResponse($result, $userFullName);
        } else {
            return $this->makePdfResponse($result);
        }
    }

    /**
     * @Route("/xml", options={"expose"=true})
     * @Rights("LSU_ACCESS_READ")
     *
     * @param Request $request
     * @return Response
     */
    public function xmlAction(Request $request)
    {
        if (!$this->hasFeature('lsu_export')) {
            throw $this->createAccessDeniedException();
        }
        $ids = $request->get('ids') ? explode(',', $request->get('ids')) : [];
        $templateId = $request->get('templateId', null);
        $userIds = $request->get('userIds') ? explode(',', $request->get('userIds')) : [];

        $lsus = LsuQuery::create()
            ->filterByValidated(true)
            ->_if(count($ids))
                ->filterById($ids)
            ->_endif()
            ->_if($templateId)
                ->filterByTemplateId($templateId)
            ->_endif()
            ->_if(count($userIds))
                ->filterByUserId($userIds)
            ->_endif()
            ->find()
        ;
        $validLsus = [];
        $user = $this->getUser();
        foreach ($lsus as $lsu) {
            try {
                $this->get('bns_app_lsu.lsu_access_manager')->validateLsu($lsu, $user);
                $validLsus[] = $lsu;
            } catch (\Exception $e) {
                // swallow error
            }
        }
        $file = $this->get('bns_app_lsu.lsu_xml_export_manager')->export($validLsus);

        return $this->makeXmlRespone($file);
    }

    protected function makePdfResponse($file, $name = 'livret')
    {
        return new Response($file, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$name.'.pdf"',
        ]);
    }

    protected function makeXmlRespone($file, $name = 'livret')
    {
        return new Response($file, 200, [
            'Content-Type'        => 'application/xml',
            'Content-Disposition' => 'attachment; filename="'.$name.'.xml"',
        ]);
    }

}
