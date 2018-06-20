<?php

namespace BNS\App\CoreBundle\Api;

use BNS\App\CoreBundle\Beta\BetaManager;

use BNS\App\CoreBundle\Model\Group;
use Symfony\Component\HttpFoundation\Request;use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

// Pour les appels dans les reset
use BNS\App\CoreBundle\Model\UserQuery;
use BNS\App\CoreBundle\Model\RankQuery;
use BNS\App\CoreBundle\Model\ModuleQuery;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Utils\Crypt;
use \BNS\App\CoreBundle\Access\BNSAccess;

/**
 * @author Eymeric Taelman
 *
 * Classe permettant l'intéraction entre l'APP et l'Auth
 * Basée sur le service buzz
 */
class BNSApi
{
    protected $buzz;
    protected $auth_url;
    protected $unique_name;
    protected $redis_connection;
    private $api_key;
    private $encode_key;
    private $apiCacheKey;

    //TTL par défaut 10 jours
    protected static $defaultTTL = 864000;

    /*
     * Définition des différents call API possibles
     * type : Type de la requête
     * route : route appelée (entre '%' les paramètres)
     */
    private $tokens = array(
        //API pour USER
        'user_read' => array(
            'type' => 'GET',
            'route' => "/users/%username%",
            'reference' => 'user',
            'reference_name' => 'username'
        ),
        'user_exists' => array(
            'type' => 'GET',
            'route' => "/users/%username%/exists.json", //force json sinon problème avec les nom d'utilisateur qui ont un "."
            'reference' => 'user',
            'reference_name' => 'username'
        ),
        // @deprecated do not use issue with encoding
        'user_read_by_email' => array(
            'type'  => 'GET',
            'route' => '/users/%email%/email'
        ),
        'user_by_email' => array(
            'type'  => 'POST',
            'route' => '/users/by-email'
        ),
        'user_rights' => array(
            'type' => 'GET',
            'route' => "/users/%username%/permissions",
            'reference' => 'user',
            'reference_name' => 'username'
        ),
        'user_rights_new' => [
            'type' => 'GET',
            'route' => "/users/%id%/all-permissions",
            'reference' => 'user',
            'reference_name' => 'username',
        ],
        'user_groups' => array(
            'type' => 'GET',
            'route' => "/users/%username%/groups",
            'reference' => 'user',
            'reference_name' => 'username'
        ),
        'user_roles' => array(
            'type' => 'GET',
            'route' => "/users/%username%/roles",
            'reference' => 'user',
            'reference_name' => 'username'
        ),
        'user_confirmation_token' => array(
            'type'	=> 'GET',
            'route'	=> '/users/%confirmation_token%/confirmationtoken'
        ),
        'get_user_invitation' => array(
            'type' => 'GET',
            'route' => '/users/%username%/invitations',
            'reference' => 'user',
            'reference_name' => 'username'
        ),
        'user_search' => array(
            'type' => 'POST',
            'route' => "/users/searches"
        ),
        'user_create' => array(
            'type' => 'POST',
            'route' => "/users"
        ),
        'users_create' => array(
            'type' => 'POST',
            'route' => "/multipleusers"
        ),
        'users_create_affectations' => array(
            'type' => 'POST',
            'route' => "/affectations"
        ),
        'reset_user_password' => array(
            'type'  => 'POST',
            'route' => "/users/%username%/resetpasswords"
        ),
        'flag_reset_user_password' => array(
            'type'  => 'POST',
            'route' => "/users/%username%/flagresetpasswords"
        ),
        'disable_user' => array(
            'type'  => 'POST',
            'route' => "/users/%username%/disables"
        ),
        'restore_user' => array(
            'type'  => 'POST',
            'route' => "/users/%username%/restores"
        ),
        'user_update' => array(
            'type' => 'PUT',
            'route' => "/users/%username%"
        ),
        'users_update' => array(
            'type' => 'PATCH',
            'route' => "/multipleusers"
        ),
        'user_update_password' => array(
            'type' => 'PUT',
            'route' => "/users/%username%/password"
        ),
        'user_flag_change_password' => array(
            'type' => 'PUT',
            'route' => "/users/%username%/flag/change/password"
        ),
        'user_update_login' => [
            'type' => 'PATCH',
            'route' => '/users/%id%/login'
        ],
        'user_delete' => array(
            'type' => 'DELETE',
            'route' => "/users/%id%"
        ),
        'user_remember' => array(
            'type' => 'POST',
            'route' => "/users/%id%/rememberme"
        ),
        'user_delete_archived' => array(
            'type' => 'DELETE',
            'route' => "/users/%id%/delete"
        ),
        'user_delete_in_group' => array(
            'type' => 'DELETE',
            'route' => "/user/in/group"
        ),
        'user_merge' => array(
            'type' => 'POST',
            'route' => "/users/merges"
        ),
        'user_move_belong' => [
          'type' => 'POST',
          'route' => '/users/move/belong'
        ],
        'user_merge_delete' => [
            'type' => 'DELETE',
            'route' => '/users/merges'
        ],
        'get_account_merges' => array(
            'type' => 'GET',
            'route' => "/users/account/merges"
        ),
        'get_user_account_merges' => array(
            'type' => 'GET',
            'route' => "/users/account/%sourceId%/%destinationId%/merges"
        ),
        'user_merges' => array(
            'type' => 'GET',
            'route' => '/user/%id%/merges',
            'reference' => 'user',
            'reference_name' => 'username'
        ),
        'user_merged_in' => array(
            'type' => 'GET',
            'route' => '/user/%id%/merged-in',
            'reference' => 'user',
            'reference_name' => 'username'
        ),
        'user_authentication' => array(
            'type' => 'POST',
            'route' => "/users/authentications"
        ),
        'user_authentication_autologin' => array(
            'type' => 'POST',
            'route' => "/users/%userId%/authentications/tokens"
        ),
        'user_belonged' => array(
            'type' => 'GET',
            'route' => '/users/%username%/belonged/%year%',
            'reference' => 'user',
            'reference_name' => 'username',
        ),
        'get_users_children_ids' => [
            'type' => 'GET',
            'route' => '/users/%id%/children',
            'reference' => 'user',
            'reference_name' => 'username'
        ],
        'get_users_parent_ids' => [
            'type' => 'GET',
            'route' => '/users/%id%/parents',
            'reference' => 'user',
            'reference_name' => 'username'
        ],
        'post_users_children' => [
            'type' => 'POST',
            'route' => '/users/%id%/children',
            'reference' => 'user',
            'reference_name' => 'username'
        ],
        'delete_users_children' => [
            'type' => 'DELETE',
            'route' => '/users/%id%/children',
            'reference' => 'user',
            'reference_name' => 'username'
        ],
        'post_user_parents' => [
            'type' => 'POST',
            'route' => '/users/%id%/parents',
            'reference' => 'user',
        ],
        'delete_user_parents' => [
            'type' => 'DELETE',
            'route' => '/users/%id%/parents',
            'reference' => 'user',
        ],
        //API GROUP
        'group_read' => array(
            'type' => 'GET',
            'route' => "/group/%id%",
            'reference' => 'group',
            'reference_name' => 'id'
        ),
        'groups_tree' => array(
            'type' => 'GET',
            'route' => "/groups/tree"
            // route: group_ids (array: id)
        ),
        'group_read_by_label' => array(
            'type' => 'GET',
            'route' => "/groups/%label%/labels/%type%/grouptype"
        ),
        'group_subgroups' => array(
            'type' => 'GET',
            'route' => "/groups/%id%/subgroups",
            'reference' => 'group',
            'reference_name' => 'id'
        ),
        'group_allsubgroups' => array(
            'type' => 'GET',
            'route' => "/groups/%id%/allsubgroups",
            'reference' => 'group',
            'reference_name' => 'id'
        ),
        'group_allsubgroupids' => array(
            'type' => 'GET',
            'route' => "/groups/%id%/allsubgroupids",
            'reference' => 'group',
            'reference_name' => 'id'
        ),
        'group_parent' => array(
            'type' => 'GET',
            'route' => "/groups/%id%/parent",
            'reference' => 'group',
            'reference_name' => 'id'
        ),
        'group_partners' => array(
            'type' => 'GET',
            'route' => "/groups/%id%/partners",
            'reference' => 'group',
            'reference_name' => 'id'
        ),
        'group_get_users' => array(
            'type' => 'GET',
            'route' => "/groups/%group_id%/members",
            'reference' => 'group',
            'reference_name' => 'id',
            'map' => 'group_id'
        ),
        'group_get_permissions_for_role' => array(
            'type' => 'GET',
            'route' => "/groups/%group_id%/roles/%role_id%/groups/%group_parent_role_id%/permissions",
            'reference' => 'group',
            'reference_name' => 'id',
            'map' => 'group_id'
        ),
        'group_get_ranks_permissions_for_role' => array(
            'type' => 'GET',
            'route' => "/groups/%group_id%/roles/%role_id%/ranks-permissions",
            'reference' => 'group',
            'reference_name' => 'id',
            'map' => 'group_id'
        ),
        'group_get_permissions_for_group' => array(
            'type' => 'GET',
            'route' => "/groups/%group_id%/groups/%group_to_test_id%/permissions",
            'reference' => 'group',
            'reference_name' => 'id',
            'map' => 'group_id'
        ),
        'group_get_users_by_roles' => array(
            'type' => 'GET',
            'route' => "/groups/%group_id%/roles/%role_id%/members",
            'reference' => 'group',
            'reference_name' => 'id',
            'map' => 'group_id'
        ),
        'group_get_users_activated_by_roles' => array(
            'type' => 'GET',
            'route' => "/groups/roles/%role_id%/membersActivated",
        ),
        'group_get_users_connection_by_roles' => array(
            'type' => 'GET',
            'route' => "/groups/%group_id%/connection/members",
            'reference' => 'group',
            'reference_name' => 'id',
            'map' => 'group_id'
        ),
        'group_get_users_with_permission' => array(
            'type' => 'GET',
            'route' => "/groups/%group_id%/permissions/%permission_unique_name%/members",
            'reference' => 'group',
            'reference_name' => 'id',
            'map' => 'group_id'
        ),
        'group_get_users_with_permission_new' => array(
            'type' => 'GET',
            'route' => "/groups/%group_id%/permissions/%permission_unique_name%/users",
            'reference' => 'group',
            'reference_name' => 'id',
            'map' => 'group_id'
        ),
        'group_get_user_ids_with_permission' => array(
            'type' => 'GET',
            'route' => "/groups/%group_id%/permissions/%permission_unique_name%/userids",
            'reference' => 'group',
            'reference_name' => 'id',
            'map' => 'group_id'
        ),
        'group_get_users_with_rank' => array(
            'type' => 'GET',
            'route' => "/groups/%group_id%/ranks/%rank_unique_name%/members",
            'reference' => 'group',
            'reference_name' => 'id',
            'map' => 'group_id'
        ),
        'partnerships_group_belongs' => array(
            'type' => 'GET',
            'route' => "/partnerships/%group_id%/list/group",
            'reference' => 'group',
            'reference_name' => 'id',
            'map' => 'group_id'
        ),
        'leave_partnership' => array(
            'type' => 'PUT',
            'route' => "/partnerships/%partnership_id%/groups/%group_id%/leave",
            'reference' => 'group',
            'reference_name' => 'id',
            'map' => 'group_id'
        ),
        'group_create' => array(
            'type' => 'POST',
            'route' => "/onegroups"
        ),
        'groups_create' => array(
            'type' => 'POST',
            'route' => "/groups"
        ),
        'group_update' => array(
            'type' => 'PUT',
            'route' => "/groups/%group_id%"
        ),
        'group_delete' => array(
            'type' => 'DELETE',
            'route' => "/groups/%id%"
        ),
        'group_restore' => array(
            'type' => 'POST',
            'route' => "/groups/%group_id%/restores"
        ),
        'group_add_parent' => array(
            'type' => 'POST',
            'route' => "/groups/%group_id%/parents"
        ),
        'group_delete_parent' => array(
            'type' => 'DELETE',
            'route' => "/groups/%group_id%/parents/%parent_id%"
        ),
        'group_subgroup_link' => array(
            'type' => 'POST',
            'route' => "/groups/%group_id%/subgroups"
        ),
        'group_assign_role_user' => array(
            'type' => 'POST',
            'route' => "/groups/%group_id%/roles/%role_id%/onemembers"
        ),
        'group_assign_role_users' => array(
            'type' => 'POST',
            'route' => "/groups/%group_id%/roles/%role_id%/members"
        ),
        'group_assign_role_group' => array(
            'type'	=> 'POST',
            'route' => "/groups/%group_id%/roles/%role_id%/rolemembers"
        ),
        'group_add_user' => array(
            'type' => 'POST',
            'route' => "/groups/%group_id%/members"
        ),
        'group_delete_user' => array(
            'type' => 'DELETE',
            'route' => "/groups/%group_id%/people/%user_id%"
        ),
        'group_delete_users' => array(
            'type' => 'DELETE',
            'route' => '/groups/%group_id%/members'
        ),
       //GROUP TYPES
        'grouptype_read_type' => array(
            'type' => 'GET',
            'route' => "/grouptypetypes/%type%"
        ),
        'grouptype_read' => array(
            'type' => 'GET',
            'route' => "/grouptypes/%id%"
        ),
        'grouptype_create' => array(
            'type' => 'POST',
            'route' => "/grouptypes"
        ),
        'grouptype_edit' => array(
            'type' => 'POST',
            'route' => "/grouptypes"
        ),
        'invitation_create' => array(
            'type' => 'POST',
            'route' => '/invitations'
        ),
        'invitation_search' => array(
            'type' => 'POST',
            'route' => '/invitations/searchs'
        ),
        'invitation_accept' => array(
            'type'	=> 'PUT',
            'route'	=> '/invitations/%invitation_id%/accept'
        ),
        'invitation_decline' => array(
            'type'	=> 'PUT',
            'route'	=> '/invitations/%invitation_id%/decline'
        ),
        'invitation_never_accept' => array(
            'type'	=> 'PUT',
            'route'	=> '/invitations/%invitation_id%/never/accept'
        ),
        'module_read' => array(
            'type' => 'GET',
            'route' => "/modules/%unique_name%"
        ),
        'module_create' => array(
            'type' => 'POST',
            'route' => "/modules"
        ),
        'module_create_permission' => array(
            'type' => 'POST',
            'route' => "/permissions"
        ),
        'module_read_permissions' => array(
            'type' => 'GET',
            'route' => "/modules/%id%/permissions"
        ),
        'permission_read' => array(
            'type' => 'GET',
            'route' => "/permissions/%unique_name%"
        ),
        'module_read_rank' => array(
            'type' => 'GET',
            'route' => "/modules/%id%/ranks"
        ),
        'rank_read' => array(
            'type' => 'GET',
            'route' => "/ranks/%unique_name%"
        ),
        'module_create_rank' => array(
            'type' => 'POST',
            'route' => "/modules/%id%/ranks"
        ),
        'module_rank_add_permission' => array(
            'type' => 'POST',
            'route' => "/modules/%module_id%/ranks/%rank_unique_name%/permissions"
        ),
        'module_rank_get_permissions' => array(
            'type' => 'GET',
            'route' => "/modules/%module_id%/ranks/%rank_unique_name%/permissions"
        ),
        'module_rank_get_permission' => array(
            'type' => 'GET',
            'route' => "/ranks/%rank_unique_name%/permissions/%permission_unique_name%"
        ),
        'module_rank_delete_permission' => array(
            'type' => 'DELETE',
            'route' => "/ranks/%rank_unique_name%/permissions/%permission_unique_name%"
        ),
        'rule_read' => array(
            'type' => 'GET',
            'route' => "/rules/%id%"
        ),
        'rule_create' => array(
            'type' => 'POST',
            'route' => "/rules"
        ),
        'rule_patch' => array(
            'type' => 'POST',
            'route' => "/rules/%id%"
        ),
        'rule_search' => array(
            'type' => 'GET',
            'route' => "/rules"
        ),
        'rule_delete' => array(
            'type' => 'DELETE',
            'route' => "/rules/%id%"
        ),
        'get_partnership' => array(
            'type' => 'GET',
            'route' => "/partnerships/%uid%"
        ),
        'join_partnership' => array(
            'type' => 'PUT',
            'route' => "/partnerships/%uid%/adds/%group_id%/group"
        ),
        'partnership_members' => array(
            'type' => 'GET',
            'route' => "/partnerships/%partnership_id%/members"
        ),
        'partnership_update' => array(
            'type' => 'PUT',
            'route' => "/partnerships/%group_id%"
        ),
        'teams_pupil_role_create' => array(
            'type' => 'PUT',
            'route' => "/teams/%group_id%/pupil/role"
        ),
        'teams_parent_role_create' => array(
            'type' => 'PUT',
            'route' => "/teams/%group_id%/parent/role"
        ),
        'teams_teacher_role_create' => array(
            'type' => 'PUT',
            'route' => "/teams/%group_id%/teacher/role"
        )
    );

    private $token = null;

    /**
     * @var BetaManager
     */
    protected $betaManager;

    /**
     * @var boolean
     */
    protected $clearExternalCache = true;

    /*
    * @param \Buzz\Browser $buzz Buzz service
    * @param string $auth_url Url de la centrale d'authentification
    */
    public function __construct(\Buzz\Browser $buzz, $auth_url, $api_key, $encode_key, $redis_connection, BetaManager $betaManager, $apiCacheKey)
    {
        $this->buzz = $buzz;
        $this->api_key = $api_key;
        $this->encode_key = $encode_key;
        //Time Out : parfois quelques problèmes si <= 5 en dev
        $this->buzz->getClient()->setTimeout(60);
        $this->buzz->getClient()->setVerifyPeer(false);
        $this->auth_url = $auth_url;
        $this->redis_connection = $redis_connection;
        $this->betaManager = $betaManager;
        $this->apiCacheKey = $apiCacheKey;
    }

    public function getRedisConnection()
    {
        return $this->redis_connection;
    }

    /*
    * Définition du header des requêtes
    * @return array Headers
    */
    public function getHeaders()
    {
        return array (
            "Content-Type: application/json; charset=utf-8",
            "Authorisation: " . Crypt::encode($this->api_key,$this->encode_key)
        );
    }

    /*
    * Récupération de la route
    * @params array tableau associatif des paramètres de la route entre pourcentages
    * @return string Route complète
    */
    public function getRoute($params,$token = null,$full = true)
    {
        if (!$token) {
            $token = $this->unique_name;
        }

        $base_route = $this->tokens[$token]['route'];

        $first = true;

        if (is_array($params)) {
            if ($this->betaManager->isBetaModeEnabled() && !isset($params['mode_beta'])) {
                $params['mode_beta'] = 1;
            }

            foreach($params as $key => $value) {
                $value = urlencode($value);

                if (strpos($base_route,"%" . $key . "%")) {
                    $base_route = str_replace("%" . $key . "%",$value,$base_route);
                }
                else {
                    $separator = $first ? "?" : "&";
                    $base_route .= $separator . $key . '=' . $value;
                    $first = false;
                }

            }
        }
        $base = $full ? $this->auth_url : "";
        return $base . '/api' . $base_route;
    }

    /*
    * Récupération du type de la requête
    * @return string GET || POST || PUT || DELETE
    */
    public function getType()
    {
        return $this->tokens[$this->unique_name]['type'];
    }

    public function useHash()
    {
        return isset($this->token['reference']) && isset($this->token['reference_name']);
    }

    public function getHashReferenceValue()
    {
        return isset($this->token['map']) ? $this->token['map'] : $this->token['reference_name'];
    }

    public function getHashKey($paramsRoute)
    {
        $referenceValue = $this->getHashReferenceValue();
        return $this->token['reference'] . '_' . $paramsRoute[$referenceValue];
    }

    public function getHashField($paramsRoute)
    {
        $base = $this->unique_name;
        foreach($paramsRoute as $key => $value)
        {
            if($key != $this->getHashReferenceValue())
            {
                $base .= '_' . $value;
            }
        }
        return $base;
    }

    public function useTTL()
    {
        return isset($this->token['TTL']);
    }

    /*
        * Envoi de la requête et récupération de la réponse
        * @param string $unique_name Nom du call API
        * @param array $params Paramètres données lors de l'appel (route, valeur etc ...)
        * @return array Reponse
        */
    public function send($unique_name,$params, $useCache = true, $cacheResponse = true)
    {
        $this->unique_name	= $unique_name;
        $this->token = $this->tokens[$unique_name];

        $params_route		= isset($params['route']) ? $params['route'] : array();
        $params_values		= isset($params['values']) ? $params['values'] : array();
        $route				= $this->getRoute($params_route);

        switch ($this->getType())
        {
            case 'GET':
                if (!isset($params['check']) && !isset($params['try']) && $useCache) {
                    if($this->useHash())
                    {
                        $redisInfos = $this->redis_connection->hget($this->getHashKey($params_route),$this->getHashField($params_route));
                    }else{
                        $redisInfos = $this->redis_connection->get($route);
                    }
                    if ($redisInfos) {
                        return json_decode($redisInfos, true);
                    }
                }
                $response = $this->buzz->get($route, $this->getHeaders());
                break;
            case 'POST':
                $response = $this->buzz->post($route, $this->getHeaders(), json_encode($params_values));
                break;
            case 'PUT':
                $response = $this->buzz->put($route, $this->getHeaders(), json_encode($params_values));
                break;
            case 'DELETE':
                //En cas de suppression il faut faire l'update pendant que l'objet existe encore
                $this->doUpdate($params);
                $response = $this->buzz->delete($route, $this->getHeaders(), json_encode($params_values));
                break;
            case 'PATCH':
                $response = $this->buzz->patch($route, $this->getHeaders(), json_encode($params_values));
                break;
        }

        $status_code = $response->getStatusCode();

        $array_response = json_decode($response->getContent(),true);

        //En mode "check" juste call pour tester existence, on renvoie uniquement le code réponse
        //En mode "try" => si Ok on renvoie la réponse, sinon le code d'erreur
        //Sans Mode on renvoie des erreurs si 400 etc ...


        if(!isset($params['check']) && !isset($params['try'])){
            if($status_code == "404"){
                throw new NotFoundHttpException(isset($array_response['message'])?$array_response['message']:'');
            }

            if($status_code == "400" || $status_code == "500" ){
                throw new HttpException($status_code,"CENTRAL : " . $array_response['message']);
            }

        }else{
            if(isset($params['check'])){
                return $status_code;
            }
            if(isset($params['try'])){
                if($status_code == "404")
                    return false;
            }
        }

        if ($this->getType() == "GET" && in_array($status_code, array(200, 201, 202)) && $cacheResponse) {
            $expires = $this->useTTL() ? $this->token['TTL'] : self::$defaultTTL;
            if ($this->useHash()) {
                $data = $response->getContent();
                $hfield = $this->getHashField($params_route);
                $hkey = $this->getHashKey($params_route);
                $this->redis_connection->pipeline(function($pipe) use ($hkey, $hfield, $data, $expires) {
                    $pipe->hset($hkey, $hfield, $data);
                    $pipe->expire($hkey, $expires);
                });
            } else {
                $this->redis_connection->set($route, $response->getContent(), 'EX', $expires);
            }
        }
        /**
         * Doit on faire des mises à jour ?
         */
        if ($this->getType() != "DELETE") {
            $this->doUpdate($params);
        }


        return $array_response;
    }


    /*
     * Fonction générique se basant sur les conventions de nommage pour faire un "get" simple
     * @params $type String type de l'objet par rapport au nommage API
     * @params $pk Integer Clé primaire
     * @return Object tel qu'il est renvoyé par la méthode GET de l'API du type d'objet
     */
    public function getObjectFromPk($type,$pk)
    {
        return $this->send($type.'_read',array('values' => array('id' => $pk),'try' => true));
    }

    /**
     * Mise à jour des données pour ne pas conserver en cache Redis des informations obsolètes
     * Développement spécifique pour chaque méthjode d'API le nécessitant, mutualisation la plus forte possible des méthodes employées
     */

    public function doUpdate($params)
    {
        $params_route = isset($params['route']) ? $params['route'] : null;
        $params_values = isset($params['values']) ? $params['values'] : null;


        switch($this->unique_name) {
            case "user_create":
                // Création, pas de conséquence
                break;
            case "user_update":
            case "user_update_password":
            case "user_flag_change_password":
            case "flag_reset_user_password":
            case "disable_user":
            case "restore_user":
            case "reset_user_password":
                $this->resetUser($params_route['username']);
                break;
            case "invitation_create":
                break;
            case "invitation_accept":
            case "invitation_decline":
            case "invitation_never_accept":
                $this->resetUser($params_values['username']);
                break;
            case "group_create":
                // Création, pas de conséquence
                break;
            case "group_update":
                // TODO optimize me only clear parent if updated
                $this->resetGroup($params_route['group_id'], true);
                break;
            case "group_delete":
                $this->resetGroup($params_route['id'], true);
                $this->resetGroupUsers($params_route['id'], true, true);
                break;
            case "group_restore":
                $this->resetGroup($params_route['group_id'], true);
                $this->resetGroupUsers($params_route['group_id'], true, true);
                break;
            case "group_subgroup_link":
                $this->resetGroup($params_route['group_id'], true);
                break;
            case "group_assign_role_user":
                $this->resetGroup($params_route['group_id'], false);
                $this->resetGroupUsers($params_route['group_id'], false, true);
                $username = UserQuery::create()->select('login')->filterById($params_values['user_id'])->findOne();
                if ($username) {
                    $this->resetUser($username);
                }
                break;
            case "group_assign_role_group":
                $this->resetGroup($params_route['group_id'], false);
                $this->resetGroupUsers($params_route['group_id'], true, true);
                break;
            case "group_add_parent":
                $this->resetGroup($params_route['group_id'], true);
                $this->resetGroup($params_values['parent_id'], true);
                break;
            case "group_delete_parent":
                $this->resetGroup($params_route['group_id'],false);
                $this->resetGroup($params_route['parent_id'],false);
                break;
            case "group_add_user":
                $this->resetGroup($params_route['group_id'], false);
                $this->resetGroupUsers($params_route['group_id'], false, true);
                $username = UserQuery::create()->select('login')->filterById($params_values['id'])->findOne();
                if ($username) {
                    $this->resetUser($username);
                }
                break;
            case "group_delete_user":
                $this->resetGroup($params_route['group_id'], false);
                $this->resetGroupUsers($params_route['group_id'], false, true);
                $username = UserQuery::create()->select('login')->filterById($params_route['user_id'])->findOne();
                if ($username) {
                    $this->resetUser($username);
                }
                break;
            case "join_partnership":
            case "leave_partnership":
                $partnershipId = isset($params_route['uid']) ? $params_route['uid'] : $params_route['partnership_id'];
                $this->resetPartnership($partnershipId);
                $this->resetGroup($params_route['group_id'], false);
                break;
            case "grouptype_create":
                //Création, pas de conséquence
                break;
            case "grouptype_edit":
                //Non utilisée
                break;
            case "invitation_search":
                // Aucun impact
                break;
            /* Jusque ici */
            case "module_create":
                //Création, pas de conséquence
                break;
            case "module_create_permission":
                $this->resetModule($params_values['module_id']);
                break;
            case "module_create_rank":
                $this->resetModule($params_route['id']);
                break;
            case "module_rank_add_permission":
                $this->resetModule($params_route['module_id']);
                $this->resetRank($params_route['rank_unique_name']);
                break;
            case "module_rank_delete_permission":
                $this->resetRank($params_route['rank_unique_name']);
                break;
            case "rule_create":
                //Création : pour les droits liés aux utilisateurs CF application
                break;
            case "rule_patch":
                $this->resetRule($params_route['id']);
                break;
            case "rule_delete":
                $this->resetRule($params_route['id']);
                break;
            case "join_partnership":
                $this->resetPartnershipsGroupBelongs($params_route['id']);
                break;
        }
    }

    public function resetHashField($uniqueName,$referenceValue,$params = null)
    {
        $token = $this->tokens[$uniqueName];
        if(isset($token['reference']))
        {
            if($params != null)
            {
                foreach($params as $key => $value)
                {
                    $uniqueName .= '_' . $value;
                }
            }
            if ($uniqueName === 'group_allsubgroupids') {
                $hkeys = ($this->redis_connection->hkeys($token['reference'] . '_' . $referenceValue));
                foreach ($hkeys as $hkey) {
                    if (strpos($hkey, $uniqueName) === 0) {
                        $this->redis_connection->hdel($token['reference'] . '_' . $referenceValue, $hkey);
                    }
                }
            } else {
                $this->redis_connection->hdel($token['reference'] . '_' . $referenceValue, $uniqueName);
            }
        }
    }

    public function resetUser($username)
    {
        $this->redis_connection->del('user_' . $username);

        $this->resetLinkedEnvCache('cache_api_post_reset_cache_user', ['username' => $username]);
    }

    /**
     * @param array|int[] $groupIds
     */
    public function resetGroups(array $groupIds)
    {
        $keys = array_map(function($item) { return 'group_' . $item; }, $groupIds);
        $this->redis_connection->del($keys);

        $this->resetLinkedEnvCache('cache_api_post_reset_cache_groups', [], [
            'groupIds' => $groupIds,
        ]);
    }

    public function resetGroup($groupId, $withParent = true)
    {
        $this->redis_connection->del('group_' . $groupId);
        if (BNSAccess::getContainer() && $withParent) {
            $gm = BNSAccess::getContainer()->get('bns.group_manager');
            foreach ($gm->getUniqueAncestorIds($groupId) as $parentId) {
               $this->resetHashField('group_allsubgroupids', $parentId);
            }
        }

        $this->resetLinkedEnvCache('cache_api_post_reset_cache_group', [
            'id' => $groupId,
            'with_parent' => $withParent
        ]);
    }

    public function resetPartnership($partnershipId)
    {
        $this->redis_connection->del('/partnerships/' . $partnershipId);
        $this->redis_connection->del('/partnerships/' . $partnershipId . '/members');

        $this->resetLinkedEnvCache('cache_api_post_reset_cache_partnership', ['uid' => $partnershipId]);
    }

    /**
     * @param $groupId
     * @param bool $withRights
     * @param bool $withSubGroups
     */
    public function resetGroupUsers($groupId, $withRights = false, $withSubGroups = false)
    {
        if (!$withRights && !$withSubGroups) {
            @trigger_error('resetGroupUsers with no parameters set to true has no impact', E_USER_DEPRECATED);
            return;
        }

        $oldClearExternalCache = $this->isClearExternalCache();
        $this->setClearExternalCache(false);

        /** @var Group $group */
        $group = GroupQuery::create()->joinWith('GroupType')->findPk($groupId);
        if (!$group) {
            return;
        }

        if ($withRights) {
            if ($group->getType() == "ENVIRONMENT") {
                return;
            }

            //GroupManager innaccessible ici, on refait en quelque sorte la méthode
            $userIds = $this->send('group_get_users',array('route' => array('group_id' => $groupId)));
            $usernames = UserQuery::create()
                ->filterById($userIds)
                ->select(['login'])
                ->find()
                ->getArrayCopy();
            if (is_array($usernames) && count($usernames) > 0) {
                $keys = array_map(function($username) {
                    return 'user_' . $username;
                }, $usernames);
                $this->redis_connection->del($keys);
            }
        }
        //Opti : distinguer le clear pour subgroups role set subgroups non roles
        if ($withSubGroups && BNSAccess::getContainer()) {
            $groupManager = BNSAccess::getContainer()->get('bns.group_manager')->setGroup($group);
            if ($groupManager) {
                if($group->getType() != "ENVIRONMENT") {
                    foreach ($groupManager->getSubgroups() as $subgroup) {
                        $this->resetGroup($subgroup->getId(), false);
                    }
                }
                $partnerships = BNSAccess::getContainer()->get('bns.partnership_manager')->getPartnershipsGroupBelongs($group->getId());
                foreach ($partnerships as $partnership) {
                    $this->resetGroup($partnership->getId(), false);
                }
            }
        }
        $this->setClearExternalCache($oldClearExternalCache);
        $this->resetLinkedEnvCache('cache_api_post_reset_cache_group_users',[
            'id' => $groupId,
            'with_rights' => $withRights,
            'with_sub_groups' => $withSubGroups,
        ]);
    }

    public function resetGroupType($groupTypeId)
    {
        $this->redis_connection->del($this->getRoute(array('id' => $groupTypeId),'grouptype_read'));

        $this->resetLinkedEnvCache('cache_api_post_reset_cache_group_type', [
            'id' => $groupTypeId
        ]);
    }


    public function resetModule($moduleId)
    {
        $module = ModuleQuery::create()->findOneById($moduleId);
        if (null != $module) {
            $this->redis_connection->del($this->getRoute(array('unique_name' => $module->getUniqueName()),'module_read'));
        }

        $this->redis_connection->del($this->getRoute(array('id' => $moduleId),'module_read_permissions'));
        $this->redis_connection->del($this->getRoute(array('id' => $moduleId),'module_read_rank'));

        $this->resetLinkedEnvCache('cache_api_post_reset_cache_module', [
            'id' => $moduleId
        ]);
    }


    public function resetRank($rankUniqueName)
    {
        $this->redis_connection->del($this->getRoute(array('unique_name' => $rankUniqueName),'rank_read'));
        $rank = RankQuery::create()->findOneByUniqueName($rankUniqueName);
        if ($rank) {
            $this->redis_connection->del($this->getRoute(array('module_id' => $rank->getModuleId(),'rank_unique_name' => $rankUniqueName),'module_rank_get_permissions'));
        }

        $this->resetLinkedEnvCache('cache_api_post_reset_cache_rank', ['uniqueName' => $rankUniqueName]);
    }


    public function resetRule($ruleId)
    {
        $this->redis_connection->del($this->getRoute(array('id' => $ruleId),'rule_read'));

        $this->resetLinkedEnvCache('cache_api_post_reset_cache_rule', ['id' => $ruleId]);
    }


    public function resetAll()
    {
        $this->redis_connection->flushall();
    }

    public function resetDB()
    {
        $this->redis_connection->flushdb();
    }


    public function resetPartnershipsGroupBelongs($groupId)
    {
        $this->redis_connection->del($this->getRoute(array('group_id' => $groupId),'partnerships_group_belongs'));

        $this->resetLinkedEnvCache('cache_api_post_reset_cache_partnership_belong', ['id' => $groupId]);
    }

    public function resetPartnershipMembers($partnershipId)
    {
        $this->redis_connection->del($this->getRoute(array('partnership_id' => $partnershipId),'partnership_members'));

        $this->resetLinkedEnvCache('cache_api_post_reset_cache_partnership_members', ['uid' => $partnershipId]);
    }

    public function resetPartnershipRead($uid)
    {
        $this->redis_connection->del($this->getRoute(array('uid' => $uid),'get_partnership'));

        $this->resetLinkedEnvCache('cache_api_post_reset_cache_partnership_read', ['uid' => $uid]);
    }

    /**
     * @return boolean
     */
    public function isClearExternalCache()
    {
        return $this->clearExternalCache;
    }

    /**
     * @param boolean $clearExternalCache
     */
    public function setClearExternalCache($clearExternalCache)
    {
        $this->clearExternalCache = (boolean)$clearExternalCache;

        return $this;
    }


    public function isClearCacheRequestValid(Request $request)
    {
        if (!$this->apiCacheKey) {
            return false;
        }

        $method = strtoupper($request->getMethod());
        $signatureData = $method . $request->getBaseUrl() . $request->getPathInfo();
        $query = $request->query->all();
        $key = $request->query->get('key');
        $time = $request->query->get('time');

        if (!$time || !$key || abs(time() - abs($time)) > 3600) {
            return false;
        }
        unset($query['key']);

        $signatureData .= '?' . http_build_query($query);
        $calculatedKey = hash_hmac('sha256', $signatureData, $this->apiCacheKey);

        if ($calculatedKey === $key) {
            return true;
        }

        return false;
    }


    public function signUrlForCacheCall($method, $url)
    {
        if (!$this->apiCacheKey) {
            return $url;
        }

        $request = Request::create($url, $method);
        $method = strtoupper($request->getMethod());
        $query = $request->query->all();
        $path = $request->getBaseUrl() . $request->getPathInfo();

        $baseUrl = $request->getSchemeAndHttpHost() . $path;

        $query['time'] = time();
        $signatureData = $method . $path;
        $signatureData .= '?' . http_build_query($query);

        $query['key'] = hash_hmac('sha256', $signatureData, $this->apiCacheKey);

        return $baseUrl . '?' . http_build_query($query);
    }

    /**
     * reset remote cache for dual env normal / beta
     *
     * @param $routeName
     * @param array $params
     */
    protected function resetLinkedEnvCache($routeName, $params = [], $body = null)
    {
        if (!$this->betaManager->isBetaModeAllowed() || !$this->clearExternalCache) {
            return;
        }

        if (!isset($params['version'])) {
            $params['version'] = '1.0';
        }

        if ($this->betaManager->isBetaModeEnabled()) {
            $url = $this->betaManager->generateNormalRoute($routeName, $params);
        } else {
            $url = $this->betaManager->generateBetaRoute($routeName, $params);
        }
        if ($body !== null) {
            $body = json_encode($body);
        }

        try {
            // clear external cache
            $url = $this->signUrlForCacheCall('POST', $url);
            $this->buzz->post($url, [], $body);
        } catch (\Exception $e) {
            // TODO log me
        }
    }


}
