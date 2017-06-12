<?php

namespace BNS\App\ResourceBundle\Search;

use BNS\App\CoreBundle\Group\BNSGroupManager;
use BNS\App\CoreBundle\Model\Group;
use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\User\BNSUserManager;
use BNS\App\ResourceBundle\Model\ResourceInternetSearch;
use BNS\App\ResourceBundle\Model\ResourceQuery;
use BNS\App\ResourceBundle\ProviderResource\ProviderResource;
use BNS\App\ScolomBundle\Model\ScolomTemplatePeer;
use BNS\App\ScolomBundle\Model\ScolomTemplateQuery;
use BNS\App\StoreBundle\Client\StoreClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\SecurityContext;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class SearchManager
{
    /**
     * @var StoreClient
     */
    private $client;

    /**
     * @var SecurityContext
     */
    private $securityContext;

    /**
     * @var BNSUserManager
     */
    private $userManager;

    /**
     * @var BNSGroupManager
     */
    private $groupManager;

    /**
     * @var array
     */
    private $searchQuery;

    /**
     * @var \PropelObjectCollection
     */
    private $filters;

    /**
     *
     * @param StoreClient     $client
     * @param SecurityContext $securityContext
     * @param BNSUserManager  $userManager
     */
    public function __construct($client, $securityContext, $userManager, $groupManager, $canHaveCatalog = false)
    {
        $this->client          = $client;
        $this->securityContext = $securityContext;
        $this->userManager     = $userManager;
        $this->groupManager    = $groupManager;
        $this->canHaveCatalog  = $canHaveCatalog;

        $this->initSearchQuery();
    }

    /**
     *
     */
    private function initSearchQuery()
    {
        $this->searchQuery = array(
            'TERM'    => array(),
            'CONTEXT' => array(),
            'GROUP'   => array(),
            'USER'    => array(),
            'FILTER'  => array(),
            'SORT'    => array()
        );
    }

    /**
     * @param string $term
     *
     * @return SearchManager
     */
    public function term($term)
    {
        $this->searchQuery['TERM'][] = $term;

        return $this;
    }

    /**
     * @param array $terms
     *
     * @return \BNS\App\ResourceBundle\Search\SearchManager
     */
    public function terms(array $terms)
    {
        foreach ($terms as $term) {
            $this->term($term);
        }

        return $this;
    }

    /**
     * @param string $context
     *
     * @return SearchManager
     *
     * @throws \InvalidArgumentException
     */
    public function context($context)
    {
        if (!in_array($context, array('my-documents', 'provider_resources', 'internet'))) {
            throw new \InvalidArgumentException('The context "' . $context . '" is NOT defined. Please use "my-documents", "internet" or "provider_resources" contexts.');
        }

        if ('my-documents' == $context) {
            $this->inMyDocuments();
        }

        $this->searchQuery['CONTEXT'][] = $context;

        return $this;
    }

    /**
     * @return SearchManager
     */
    public function inMyDocuments()
    {
        return $this->user($this->securityContext->getToken()->getUser()->getId());
    }

    /**
     * @param int|Group $group
     *
     * @return SearchManager
     */
    public function group($group, $searchUsers = true)
    {
        if ($group instanceof Group) {
            $group = $group->getId();

            if ($searchUsers) {
                $this->groupManager->setGroup($group);
            }
        }
        else {
            if ($searchUsers) {
                $this->groupManager->setGroupById($group);
            }
        }

        // Already added
        if (in_array($group, $this->searchQuery['GROUP'])) {
            return $this;
        }

        $this->searchQuery['GROUP'][] = $group;

        // Search on group users
        if ($searchUsers) {
            $this->userManager->setUser($this->securityContext->getToken()->getUser());
            if ($this->userManager->hasRight('RESOURCE_ADMINISTRATION', $group) || $this->userManager->hasRight('RESOURCE_USERS_ADMINISTRATION', $group)) {
                $this->users($this->groupManager->getUsersIds());
            }
        }

        return $this;
    }

    /**
     * @param \PropelObjectCollection|array $groups
     *
     * @return SearchManager
     */
    public function groups($groups, $searchUsers = true)
    {
        foreach ($groups as $group) {
            $this->group($group, $searchUsers);
        }

        return $this;
    }

    /**
     * @param User|int $user
     *
     * @return SearchManager
     */
    public function user($user)
    {
        if ($user instanceof User) {
            $user = $user->getId();
        }

        // Already added
        if (in_array($user, $this->searchQuery['USER'])) {
            return $this;
        }

        $this->searchQuery['USER'][] = $user;

        return $this;
    }

    /**
     * @param array $users
     *
     * @return \BNS\App\ResourceBundle\Search\SearchManager
     */
    public function users($users)
    {
        foreach ($users as $user) {
            $this->user($user);
        }

        return $this;
    }

    /**
     * @param string $uniqueName
     * @param mixed $value
     *
     * @return SearchManager
     *
     * @throws \InvalidArgumentException
     */
    public function filter($uniqueName, $value = null)
    {
        $parsedValue = $this->isValidFilter($uniqueName, $value);
        if (false === $parsedValue) {
            throw new \InvalidArgumentException('The filter "' . $uniqueName . '" has an invalid value : ' . $value);
        }

        $this->searchQuery['FILTER'][$uniqueName][] = $parsedValue;

        return $this;
    }

    /**
     * @param string $uniqueName
     * @param array  $values
     *
     * @return SearchManager
     */
    public function filters($uniqueName, array $values)
    {
        foreach ($values as $value) {
            $this->filter($uniqueName, $value);
        }

        return $this;
    }

    /**
     * @param string $sort
     *
     * @return \BNS\App\ResourceBundle\Search\SearchManager
     *
     * @throws \InvalidArgumentException
     */
    public function sort($sort, $column = 'Label')
    {
        $sort = strtoupper($sort);
        if (!in_array($sort, array('ASC', 'DESC'))) {
            throw new \InvalidArgumentException('The sort can be "ASC" or "DESC" only');
        }

        $this->searchQuery['SORT'] = array(
            $column,
            $sort
        );

        return $this;
    }

    /**
     * @param int    $page
     * @param int    $limit
     * @param string $forceScope
     *
     * @return array
     * @throws Exception si l'utilisateur n'a pas accès à des médiathèques
     */
    public function find($page = 1, $limit = 10, $forceScope = null)
    {
        $results = array();
        $conditions = array();
        $tmpConditions = array();
        $askGroup = false;

        // Context
        if ('RESOURCES' == $forceScope ||
            isset($this->searchQuery['CONTEXT'][0]) && in_array('my-document', $this->searchQuery['CONTEXT']) || !isset($this->searchQuery['CONTEXT'][0]) ||
            isset($this->searchQuery['USER'][0]) || isset($this->searchQuery['USER'][0])) {
            $query = ResourceQuery::create('r')
                ->join('r.ResourceLinkUser rlu', \Criteria::LEFT_JOIN)
                ->join('rlu.ResourceLabelUser rlau', \Criteria::LEFT_JOIN)
                ->join('r.ResourceLinkGroup rlg', \Criteria::LEFT_JOIN)
                ->join('rlg.ResourceLabelGroup rlag', \Criteria::LEFT_JOIN)
            ;

            // Terms
            if (isset($this->searchQuery['TERM'][0])) {
                foreach ($this->searchQuery['TERM'] as $i => $term) {
                    $query->condition('term_' . $i, 'r.Label LIKE ?', '%' . $term . '%');
                    $tmpConditions[] = 'term_' . $i;
                }

                // Combine all terms
                $query->combine($tmpConditions, \Criteria::LOGICAL_AND, 'terms');
                $conditions[] = 'terms';

                // Clear
                unset($tmpConditions);
                $tmpConditions = array();
            }


            if (!isset($this->searchQuery['GROUP'][0])) {
                foreach($this->userManager->getGroupsWherePermission('RESOURCE_ACCESS') as $accessableGroup)
                {
                    $this->searchQuery['GROUP'][] = $accessableGroup->getId();
                }
            }else{
                $askGroup = true;
            }

            // User folder
            if (isset($this->searchQuery['USER'][0]) ||
                isset($this->searchQuery['GROUP'][0])) {
                // Group folder
                if (isset($this->searchQuery['GROUP'][0])) {
                    foreach ($this->searchQuery['GROUP'] as $groupId) {
                        $conditionName = 'group_folder_' . $groupId;
                        $query->condition($conditionName, 'rlag.GroupId = ?', $groupId);
                        $currentConditions[$groupId] = array(
                            $conditionName
                        );

                        // Privacy process
                        $this->userManager->setUser($this->securityContext->getToken()->getUser());
                        if (!$this->userManager->hasRight('RESOURCE_ADMINISTRATION', $groupId) &&
                            !$this->userManager->hasRight('RESOURCE_USERS_ADMINISTRATION', $groupId)) {
                            $query->condition('privacy_' . $groupId . '_foreign_status', 'r.IsPrivate = ?', false)
                                ->condition('privacy_' . $groupId . '_foreign_user', 'r.UserId != ?', $this->securityContext->getToken()->getUser()->getId())
                                ->condition('privacy_' . $groupId . '_me', 'r.UserId = ?', $this->securityContext->getToken()->getUser()->getId())
                                ->combine(array(
                                    'privacy_' . $groupId . '_foreign_status',
                                    'privacy_' . $groupId . '_foreign_user'
                                ), \Criteria::LOGICAL_AND, 'privacy_' . $groupId . '_foreign')
                                ->combine(array(
                                    'privacy_' . $groupId . '_foreign',
                                    'privacy_' . $groupId . '_me'
                                ), \Criteria::LOGICAL_OR, 'privacy_' . $groupId)
                            ;

                            $currentConditions[$groupId][] = 'privacy_' . $groupId;
                        }

                    }

                    foreach ($currentConditions as $groupId => $conds) {
                        $query->combine($conds, \Criteria::LOGICAL_AND, 'group_' . $groupId);
                        $tmpConditions[] = 'group_' . $groupId;
                    }
                }

                // Users process
                if (isset($this->searchQuery['USER'][0])) {
                    $query->condition('users', 'rlau.UserId IN ?', $this->searchQuery['USER']);
                    $tmpConditions[] = 'users';
                }

                // Combine all user & group folders
                $query->combine($tmpConditions, \Criteria::LOGICAL_OR, 'context');
                $conditions[] = 'context';

                // Clear
                unset($tmpConditions);
                $tmpConditions = array();
            }else{
                throw new \Exception('The user does not have access to any resource');
            }

            // Status process
            $query->where('r.StatusDeletion = ?', 1)
				->where('rlu.Status = ?', 1)
				->orWhere('rlg.Status = ?', 1)
			;

            // Sort process
            if (isset($this->searchQuery['SORT'][0])) {
                $query->orderBy('r.' . $this->searchQuery['SORT'][0][0], $this->searchQuery['SORT'][0][1]);
            }

            // Filters process
            if (count($this->searchQuery['FILTER']) > 0) {
                $query->join('r.ResourceScolom rs')
                    ->join('rs.ResourceScolomData rsd')
                ;

                $countFilters = 0;
                foreach ($this->searchQuery['FILTER'] as $uniqueName => $filters) {
                    foreach ($filters as $i => $value) {
                        $query->condition('filter_' . $uniqueName . '_' . $i . '_choice_cond', 'rs.Value IS NULL')
                            ->condition('filter_' . $uniqueName . '_' . $i . '_choice_value', 'rsd.DataTemplateId = ?', $value)
                            ->condition('filter_' . $uniqueName . '_' . $i . '_string', 'rs.Value = ?', $value)
                            ->condition('filter_' . $uniqueName . '_' . $i . '_template', 'rs.ScolomUniqueName = ?', $uniqueName)
                            ->combine(array(
                                'filter_' . $uniqueName . '_' . $i . '_choice_cond',
                                'filter_' . $uniqueName . '_' . $i . '_choice_value'
                            ), \Criteria::LOGICAL_AND, 'filter_' . $uniqueName . '_' . $i . '_choice')
                            ->combine(array(
                                'filter_' . $uniqueName . '_' . $i . '_choice',
                                'filter_' . $uniqueName . '_' . $i . '_string',
                            ), \Criteria::LOGICAL_OR, 'filter_' . $uniqueName . '_' . $i . '_value')
                            ->combine(array(
                                'filter_' . $uniqueName . '_' . $i . '_value',
                                'filter_' . $uniqueName . '_' . $i . '_template'
                            ), \Criteria::LOGICAL_AND, 'filter_' . $uniqueName . '_' . $i)
                        ;

                        $tmpConditions[] = 'filter_' . $uniqueName . '_' . $i;
                        ++$countFilters;
                    }
                }

                $query->combine($tmpConditions, \Criteria::LOGICAL_OR, 'filters')
                    ->having('COUNT(r.Id) = ?', $countFilters, \PDO::PARAM_INT)
                ;

                $conditions[] = 'filters';

                // Clear
                unset($tmpConditions);
                $tmpConditions = array();
            }

            // Finally, combine main conditions
            if (isset($conditions[0])) {
                $query->groupBy('r.Id')
                    ->where($conditions)
                ;
            }

            $results['RESOURCES'] = $query->paginate($page, $limit);
        }

        // Resource provider folder
        if($this->canHaveCatalog)
        {
            if ('PROVIDER_RESOURCES' == $forceScope || (!isset($this->searchQuery['CONTEXT'][0]) && !$askGroup) || in_array('provider_resources', $this->searchQuery['CONTEXT'])) {
                if (!isset($this->searchQuery['GROUP'][0])) {
                    $groups = $this->userManager->setUser($this->securityContext->getToken()->getUser())->getGroupsWherePermission('RESOURCE_ACCESS');
                }
                else {
                    $groups = GroupQuery::create('g')
                        ->joinWith('g.GroupType gt')
                        ->where('g.Id IN ?', $this->searchQuery['GROUP'])
                    ->find();
                }

                $uais = $this->groupManager->getUais($groups);

                $response = $this->client->post('/resources/search', array(), array(
                    'uai'     => json_encode($uais),
                    'terms'   => json_encode($this->searchQuery['TERM']),
                    'filters' => json_encode($this->searchQuery['FILTER'])
                ))->send();

                if (206 == $response->getStatusCode()) {
                    $results['PROVIDER_RESOURCES'] = false;
                }
                else {
                    $resourcesUai = $response->toArray();
                    foreach ($resourcesUai as $uai => $resources) {
                        foreach ($resources as $resource) {
                            $results['PROVIDER_RESOURCES'][$uai][] = new ProviderResource($uai, $resource);
                        }
                    }
                }
            }
        }

        // Internet search
        if (('INTERNET' == $forceScope || (!isset($this->searchQuery['CONTEXT'][0]) && !$askGroup) || in_array('internet', $this->searchQuery['CONTEXT'])) &&
            isset($this->searchQuery['TERM'][0])) {
            $search = new ResourceInternetSearch();
            $search->setUserId($this->securityContext->getToken()->getUser()->getId());
            $search->setLabel(join(' ', $this->searchQuery['TERM']));
            $search->save();

            $results['INTERNET'] = true;
        }

        // Clear query for other instances
        $this->initSearchQuery();

        return $results;
    }

    /**
     * Reload filters from database
     */
    private function refreshFilters()
    {
        $filters = ScolomTemplateQuery::create('st')
            ->joinWith('st.ScolomDataTemplate sdt', \Criteria::LEFT_JOIN)
        ->find();

        foreach ($filters as $filter) {
            $this->filters[$filter->getUniqueName()] = $filter;
        }
    }

    /**
     * @param string $uniqueName
     *
     * @return boolean
     */
    private function hasFilter($uniqueName)
    {
        if (!isset($this->filters)) {
            $this->refreshFilters();
        }

        return isset($this->filters[$uniqueName]);
    }

    /**
     * @param string $uniqueName
     * @param mixed $value
     *
     * @return boolean
     *
     * @throws \InvalidArgumentException
     */
    private function isValidFilter($uniqueName, $value = null)
    {
        if (!isset($this->filters)) {
            $this->refreshFilters();
        }

        // Filter exists ?
        if (!$this->hasFilter($uniqueName)) {
            throw new \InvalidArgumentException('The filter with unique name "' . $uniqueName . '" is NOT found !');
        }

        // Validate value
        switch ($this->filters[$uniqueName]->getType()) {
            case ScolomTemplatePeer::TYPE_NULL:
            case ScolomTemplatePeer::TYPE_MULTIPLE_NULL:
            return null == $value ? null : false;

            case ScolomTemplatePeer::TYPE_STRING:
            case ScolomTemplatePeer::TYPE_MULTIPLE_STRING:
            return null != $value ? $value : false;

            case ScolomTemplatePeer::TYPE_CHOICE:
            case ScolomTemplatePeer::TYPE_MULTIPLE_CHOICE:
                $data = $this->filters[$uniqueName]->getScolomDataTemplates();
                foreach ($data as $item) {
                    if ($item->getUniqueName() == $value) {
                        return $item->getId();
                    }
                }
        }

        return false;
    }
}
