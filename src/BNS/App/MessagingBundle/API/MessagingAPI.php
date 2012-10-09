<?php

namespace BNS\App\MessagingBundle\API;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Buzz\Message\RequestInterface;
use Buzz\Message\Form\FormRequest,
    Buzz\Message\Response,
    Buzz\Client\Curl,
    Buzz\Client\FileGetContents;

/**
 * @author Pierre-Luc ROUAYS
 * @author brian clozel
 * Classe réalisant les appels APIs à GoLive! messagerie
 * Elle est basée sur la librairie buzz
 */
class MessagingAPI {

    protected $buzz;
    protected $api_host;
    protected $api_baseurl;
    protected $unique_name;
    protected $token;
    protected $serializer;
    protected $right_manager;
    protected $request;
    protected $mail_api_downloadurl;
    protected $mail_domain;
    protected $mail_password;


    /*
     * Définition des différents call API possibles
     * type : Type de la requête
     * route : route appelée (entre '%' les paramètres)
     */
    private $resources = array(
        'authentication' => array(
            'type' => 'POST',
            'route' => "/login.xml"
        ),
        'get_folders' => array(
            'type' => 'GET',
            'route' => "/getFolderList.json"
        ),
        'list_messages' => array(
            'type' => 'POST',
            'route' => "/getMailHeaderList.json"
        ),
        'get_mail' => array(
            'type' => 'POST',
            'route' => "/getMail.json"
        ),
        'send_mail' => array(
            'type' => 'POST',
            'route' => "/sendMail.json"
        ),
        'save_as_draft' => array(
            'type' => 'POST',
            'route' => "/saveAsDraft.json"
        ),
        'delete_mails' => array(
            'type' => 'POST',
            'route' => "/deleteMails.json"
        ),
        'search_mails' => array(
            'type' => 'POST',
            'route' => "/searchMails.json"
        ),
        'add_attachment' => array(
            'type' => 'POST_FILE',
            'route' => "/addAttachment.json"
        ),
        'free_all_composition_space' => array(
            'type' => 'POST',
            'route' => "/freeAllCompositionSpace.json"
        ),
        'check_attachment_upload' => array(
            'type' => 'POST',
            'route' => "/checkAttachmentUpload.json"
        ),
        'invalid_session' => array(
            'type' => 'POST',
            'route' => "/invalidateSession.json"
        )
    );

    /*
     * @param \Buzz\Browser $buzz Buzz service
     * @param string $auth_url Url de l'API GoLive
     */

    public function __construct(\Buzz\Browser $buzz, $api_host, $api_baseurl, $serializer, $right_manager, $request, $mail_api_downloadurl, $mail_domain, $mail_password) {
        $this->buzz = $buzz;

        //Time Out : parfois quelques problèmes si <= 5 en dev
        $this->buzz->getClient()->setTimeout(30);
        
        $this->api_baseurl = $api_baseurl;
        $this->api_host = $api_host;

        $this->serializer = $serializer;

        $this->right_manager = $right_manager;

        $this->request = $request;
        
        $this->mail_api_downloadurl = $mail_api_downloadurl;
        $this->mail_domain = $mail_domain;
        $this->mail_password = $mail_password;

        $this->authenticate();
    }

    /**
     * Stocke le token d'authentification pour les appels API suivants
     * @param string $token Token d'authentification (cookie de session)
     */
    public function setToken($token) {
        $this->token = $token;
    }

    /*
     * Définition du header des requêtes
     * @param string $token Token d'authentification (cookie de session)
     * @return array Headers
     */

    public function getHeaders($token) {

        if ($token) {
            return array(
                "Cookie: " . $token
            );
        } else {
            return array(
            );
        }
    }

    /*
     * Récupération de la route
     * @params array tableau associatif des paramètres de la route entre pourcentages
     * @return string Route complète
     */

    public function getRoute($params = null) {
        $base_route = $this->resources[$this->unique_name]['route'];
        $first = true;

        if (is_array($params)) {
            foreach ($params as $key => $value) {
                if (strpos($base_route, "%" . $key . "%")) {
                    $base_route = str_replace("%" . $key . "%", $value, $base_route);
                } else {
                    $separator = $first ? "?" : "&";
                    $base_route .= $separator . $key . '=' . $value;
                    $first = false;
                }
            }
        }
        return $this->api_baseurl . $base_route;
    }

    /*
     * Récupération du type de la requête
     * @return string GET || POST || PUT || DELETE
     */

    public function getType() {
        return $this->resources[$this->unique_name]['type'];
    }

    /*
     * Envoi de la requête et récupération de la réponse
     * @param string $unique_name Nom du call API
     * @param array $params Paramètres donnés lors de l'appel (route, json, form)
     * @return array Reponse
     */

    public function send($unique_name, $params = null, $user = null) {
        start:

        if($user != null)
        {
            $this->authenticateAs($user);
        }

        $this->unique_name = $unique_name;

        $params_route = isset($params['route']) ? $params['route'] : null;
        
        switch ($this->getType()) {
            case 'GET':
                $response = $this->buzz->get($this->api_host.$this->getRoute($params_route), $this->getHeaders($this->token));
                break;
            case 'POST':                
                $request = new FormRequest('POST', $this->getRoute($params_route), $this->api_host);
                $request->addHeaders($this->getHeaders($this->token));
                
                if (isset($params['form'])) {
                    $request->setFields($params['form']);
                }
                
                $response = new Response();
                $this->buzz->send($request, $response);
                
                break;
            case 'POST_FILE':
                $request = new FormRequest('POST', $this->getRoute($params_route), $this->api_host);
                $headers = $this->getHeaders($this->token);
                $headers[] = 'Expect:';
                $request->addHeaders($headers);
                if (isset($params['form'])) {
                    foreach($params['form'] as $field => $value){
                         $request->setField($field, $value);
                    }
                }
                

                $response = new Response();
                $this->buzz->send($request, $response);
                
                break;
        }

        $status_code = $response->getStatusCode();

        $complete_response = json_decode($response->getContent(), true);
        
        $array_response = $complete_response['response'];
 
        if ($status_code > 200 || $array_response['status']['code'] >= 200) {
            throw new HttpException($status_code, $array_response['status']['code'] . ' ' . $array_response['status']['mnemo']);
        }

        //Si perte de session
        if ($array_response['status']['code'] == null) {
            if($user == null)
            {
                $this->authenticate(true);
            }
            else
            {
                $this->authenticateAs($user);
            }
            goto start;
        }

        // TODO: trouver mieux pour supprimer le "response" du JSON
        return $this->deserialize(json_encode($complete_response['response']));
    }
    
    public function downloadFile($url, $name, $tempDir)
    {
        $filename = rand().''.$name;
        
        if (!is_dir($tempDir))
        {
            mkdir($tempDir, 0777, true);
        }
        
        $file = fopen($tempDir.$filename, 'wb');
        
        $request = new FormRequest('GET', $this->mail_api_downloadurl."/".$url, $this->api_host);
        $request->addHeaders($this->getHeaders($this->token));
        $response = new Response();
        $client = new Curl();
        //Téléchargement
        $client->setTimeout(60);
        $client->setOption(CURLOPT_TIMEOUT , 60);
        $client->setOption(CURLOPT_CONNECTTIMEOUT  , 60);
        $client->setOption(CURLOPT_FILE, $file);
        $client->setOption(CURLOPT_HEADER, 0);
        $client->send($request, $response);
        
        fclose($file);
        
        return $tempDir.$filename;
    }

    /**
     * 
     * @param string $login Login de messagerie de l'utilisateur
     * @param string $password Mot de passe de l'utilisateur
     * @return string Token d'authentification à renvoyer dans les headers (cookie de session)
     * @throws HttpException si l'authentification échoue
     */
    public function authenticate($renew = false) {
        //Si on n'a pas le token ou que l'on demande un renouvellement (perte de session) on récupère un nouveau token
        if ($this->request->getSession()->get('mail_token') == null || $renew == true) {
            $login = $this->right_manager->getUserSession()->getSlug().'@'.$this->mail_domain;
            $password = $this->mail_password;

            $this->unique_name = 'authentication';
            $response = $this->buzz->submit($this->api_host . $this->getRoute(), array('LOGIN' => $login, 'PASSWORD' => $password));

            if (false !== strpos($response->getContent(), "<code>400</code>")) {
                throw new HttpException(500, "La connnexion à la messagerie à échoué");
            }

            $token = $this->parseCookieHeader($response);

            $this->request->getSession()->set('mail_token', $token);

            $this->token = $token;
        }
        //Sinon on récupère celui en session
        else {
            $token = $this->request->getSession()->get('mail_token');
            $this->token = $token;
        }
    }
    
    /**
     * 
     * @param string $login Login de messagerie de l'utilisateur
     * @param string $password Mot de passe de l'utilisateur
     * @return string Token d'authentification à renvoyer dans les headers (cookie de session)
     * @throws HttpException si l'authentification échoue
     */
    public function authenticateAs($user) {
        //Si on n'a pas le token ou que l'on demande un renouvellement (perte de session) on récupère un nouveau token
        if ($this->request->getSession()->get('mail_token') == null) {
            //S'identifier en tant que le compte modéré
            $login = $user->getSlug().'@'.$this->mail_domain;
            $password = $this->mail_password;
            
            $this->unique_name = 'authentication';
            $response = $this->buzz->submit($this->api_host . $this->getRoute(), array('LOGIN' => $login, 'PASSWORD' => $password));

            if (false !== strpos($response->getContent(), "<code>400</code>")) {
                throw new HttpException(500, "La connnexion à la messagerie à échoué");
            }

            $token = $this->parseCookieHeader($response);

            $this->request->getSession()->set('mail_token', $token);

            $this->token = $token;
        }
        //Sinon on récupère celui en session
        else {
            $token = $this->request->getSession()->get('mail_token');
            $this->token = $token;
        }
    }
    
    public function closeAuthenticateAs()
    {
        //Supprimer le token
        $this->request->getSession()->remove('mail_token');
    }

    private function parseCookieHeader($response) {

        // récupérer le header complet
        $header = $response->getHeader("Set-Cookie");
        // récupérer la value du header
        // cookie_name=cookie_value EST LA VALUE DU HEADER Set-Cookie
        // ce n'est pas un attribute du header.
        $cookie = explode(';', $header);
        // récupérer la valeur du cookie
        //list(,$token) = explode('=',$cookie[0],2);

        return $cookie[0];
    }

    private function deserialize($jsondata) {

        switch ($this->unique_name) {
            case 'get_folders':
                $class = 'FolderResponse';
                break;
            case 'list_messages':
                $class = 'ListMessagesResponse';
                break;
            case 'get_mail':
                $class = 'MailResponse';
                break;
            case 'save_as_draft':
                $class = 'SaveAsDraftResponse';
                break;
            case 'search_mails':
                $class = 'SearchResponse';
                break;
            case 'add_attachment':
                $class = 'AddAttachmentResponse';
                break;
            case 'check_attachment_upload':
                $class = 'CheckAttachmentUploadResponse';
                break;
            default :
                $class = 'Response';
                break;
        }

        return $this->serializer->deserialize($jsondata, 'BNS\\App\\MessagingBundle\\API\\Model\\' . $class, 'json');
    }

}