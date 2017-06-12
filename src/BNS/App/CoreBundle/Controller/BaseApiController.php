<?php

namespace BNS\App\CoreBundle\Controller;
use FOS\RestBundle\Request\ParamFetcherInterface;
use Hateoas\Representation\Factory\PagerfantaFactory;
use Pagerfanta\Adapter\PropelAdapter;
use Pagerfanta\Pagerfanta;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Hateoas\Configuration\Route;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use FOS\RestBundle\Util\Codes;
use FOS\RestBundle\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class BaseApiController
 * @package BNS\App\CoreBundle\Controller
 * Classe parent de tous les controllers d'API de chaque Bundle
 */
class BaseApiController extends Controller
{
    public static $apiVersion = "1.0";

    public function getVersion()
    {
        return self::$apiVersion;
    }

    public function checkParameter($name)
    {
        if(!$this->getRequest()->get($name))
        {
            throw new NotFoundHttpException('Please set ' . $name . ' parameter.');
        }
        return true;
    }

    public function getCurrentUser()
    {
        return $this->get('bns.right_manager')->getUserSession();
    }

    public function getCurrentUserId()
    {
        return $this->getCurrentUser()->getId();
    }

    protected function getPaginator(\ModelCriteria $query, Route $route, ParamFetcherInterface $paramFetcher, \Closure $objectCallback = null, \Closure $collectionCallback = null, $pageParameterName = 'page', $limitParameterName = 'limit')
    {
        $page = $paramFetcher->get($pageParameterName);
        $limit = $paramFetcher->get($limitParameterName);

        $pager = new Pagerfanta(new PropelAdapter($query, $objectCallback, $collectionCallback));
        $pager->setMaxPerPage($limit);
        $pager->setCurrentPage($page);

        $pagerfantaFactory = new PagerfantaFactory($pageParameterName, $limitParameterName);

        return $pagerfantaFactory->createRepresentation($pager, $route);
    }


    /**
     * @param mixed           $formType    The name of the form type or a form type
     * @param mixed           $object      The object
     * @param array           $options     The form objection
     * @param string|callable $resourceUrl The route to the resource or a callable that build the url
     * @param \Closure        $save        A callable that persist the object ex : function ($object) { $object->save(); ... }
     * @param string          $name        The form name. If left empty, posted data is expected to be found  at the first
     *                                     level, ex (json): { "property1": "foo", "property2": "bar" }. If a name is given, posted
     *                                     values must be stored under the same key, ex (json): { "myFormName": { "property1": "foo", "property2": "bar" } }
     * @param Request         $request
     *
     * @return Response|View
     */
    protected function restForm($formType, $object, array $options = array(), $resourceUrl = null, \Closure $save = null, $name = '', Request $request = null)
    {
        $statusCode = null !== $resourceUrl ? Codes::HTTP_CREATED : Codes::HTTP_NO_CONTENT;
        if (!$request) {
            $request = $this->getRequest();
        }

        // Allow to create form in controller
        if (is_string($formType) || $formType instanceof FormTypeInterface) {
            $formBuilder = $this->container->get('form.factory')->createNamedBuilder($name, $formType, $object, $options);
        }
        else {
            $formBuilder = $formType;
        }

        // Setting form method
        $formBuilder->setMethod($request->getMethod());
        $form = $formBuilder->getForm();

        $form->handleRequest($request);
        if ($form->isValid()) {
            if (null !== $save) {
                $response = $save($form->getData(), $form);
                if (null !== $response) {
                    return $response;
                }
            } else {
                $object->save();
            }

            // set the `Location` header only when creating new resources
            $response = new Response('', $statusCode);

            if (Codes::HTTP_CREATED === $statusCode) {
                if (is_callable($resourceUrl)) {
                    $url = $resourceUrl($object, $this->get('router'));
                } else {
                    $url = $this->generateUrl($resourceUrl, array(
                        'id' => $object->getId(),
                        'version' => $request->get('version')
                    ), true);
                }

                $response->headers->set('Location', $url);
            }

            return $response;
        }

        return View::create($form, Codes::HTTP_BAD_REQUEST);
    }

    /**
     * Proxy for creating a Rest view
     *
     * @param mixed $data
     * @param int $code
     * @param array $header
     * @return View
     */
    protected function view($data = null, $code = null, $header = array())
    {
        return View::create($data, $code, $header);
    }

    /**
     * @param string $route
     * @param mixed $object
     * @param int $code
     * @return Response
     */
    protected function generateLocationResponse($route, $object, $code = Codes::HTTP_CREATED)
    {
        $response = new Response('', $code);
        $url = $this->generateUrl($route, array(
            'id' => $object->getId(),
            'version' => $this->getVersion(),
        ));
        $response->headers->set('Location', $url);

        return $response;
    }

    /**
     * Publishes the given data to a channel, using redis messaging and optional serialization groups
     *
     * @param string $channel
     * @param mixed $data
     * @param array $groups
     */
    public function publish($channel, $data = null, $groups = array())
    {
        $this->get('bns.realtime.publisher')->publish($channel, $data, $groups);
    }

    /**
     * Checks that the given date is of the YYYY-MM-DD format.
     *
     * @param string $date
     * @return bool
     */
    protected function isDate($date)
    {
        list($year, $month, $day) = explode('-', $date);

        return checkdate($month, $day, $year);
    }

    /**
     * Checks that the given date is a monday.
     *
     * @param string $date
     * @return bool
     */
    protected function isMonday($date)
    {
        try {
            // check format
            if (!$this->isDate($date)) {
                return false;
            }

            // check day of week
            $date = new \DateTime($date);

            return $date->format('N') === '1'; // 1~7, 1 is Monday
        } catch (\Exception $e) {
            return false;
        }
    }

}
