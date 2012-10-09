<?php

namespace BNS\App\CoreBundle\Security\OAuth;

use HWI\Bundle\OAuthBundle\Security\Core\User\OAuthAwareUserProviderInterface;
use HWI\Bundle\OAuthBundle\OAuth\Response\UserResponseInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class OAuthAwareUserProvider implements UserProviderInterface, OAuthAwareUserProviderInterface
{
	/**
	 * @var string The class namespace
	 */
	private $class;
	
	/**
	 * @var string The username property name
	 */
	private $property;
	
	public function __construct($class, $property)
	{
		$this->class	= $class;
		$this->property	= strtoupper($property);
	}
	
	/**
	 * @param \HWI\Bundle\OAuthBundle\OAuth\Response\UserResponseInterface $response
	 * 
	 * @return \BNS\App\CoreBundle\Model\User The logged user
	 * 
	 * @throws \RuntimeException
	 */
	public function loadUserByOAuthUserResponse(UserResponseInterface $response)
	{
		return $this->loadUserByUsername($response->getUsername());
	}
	
	 /**
     * {@inheritDoc}
     */
    public function loadUserByUsername($username)
    {
		$queryClass	= $this->class . 'Query';
		$peerClass	= $this->class . 'Peer';
		
        $user = $queryClass::create()
			->joinWith('Profile')
			->joinWith('Profile.Resource', \Criteria::LEFT_JOIN)
			->add($peerClass::$this->property, $username)
		->findOne();
		
		if (null == $user) {
			throw new \RuntimeException('The user with username : ' . $username . ' is not found !');
		}
		
		return $user;
    }

    /**
     * {@inheritDoc}
     */
    public function refreshUser(UserInterface $user)
    {
        if (!$this->supportsClass(get_class($user))) {
            throw new UnsupportedUserException(sprintf('Unsupported user class "%s"', get_class($user)));
        }

        return $this->loadUserByUsername($user->getUsername());
    }

    /**
     * {@inheritDoc}
     */
    public function supportsClass($class)
    {
        return $class === $this->class;
    }
}