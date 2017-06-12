<?php
namespace BNS\App\CoreBundle\Exception;

use BNS\App\CoreBundle\Model\User;
use Exception;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class WrongBetaModeException extends \Exception
{
    protected $user;

    protected $betaMode;

    /**
     * @inheritDoc
     */
    public function __construct($betaMode, User $user, $message = "", $code = 0, Exception $previous = null)
    {
        $this->betaMode = $betaMode;
        $this->user = $user;
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param User $user
     */
    public function setUser($user)
    {
        $this->user = $user;
    }

    /**
     * @return string
     */
    public function getBetaMode()
    {
        return $this->betaMode;
    }

    /**
     * @param string $betaMode
     */
    public function setBetaMode($betaMode)
    {
        $this->betaMode = $betaMode;
    }
}
