<?php

namespace BNS\App\CoreBundle\Security\OAuth;

use BNS\App\CoreBundle\Model\User;
use HWI\Bundle\OAuthBundle\OAuth\Response\UserResponseInterface;
use HWI\Bundle\OAuthBundle\Security\Core\User\OAuthAwareUserProviderInterface;
use Symfony\Component\Security\Core\Exception\LockedException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

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
        $queryClass = $this->class . 'Query';
        $peerClass = $this->class . 'Peer';
        $constant = constant($peerClass . '::' . $this->property);
        /** @var User $user */
        $user = $queryClass::create()
            ->add($constant, $username, \Criteria::EQUAL)
            ->findOne()
        ;

        if (!$user) {
            throw new UsernameNotFoundException('The user with username : ' . $username . ' is not found !');
        }

        if ($user->getArchived()) {
            throw new LockedException('The user ' . $username . ' is archived !');
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
        return true;
        return $class === $this->class;
    }
}
