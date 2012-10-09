<?php

namespace BNS\App\ProfileBundle\Form\Model;

use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Form\Model\IFormModel;

class ProfileFormModel implements IFormModel
{
    /**
     * @var ?
     */
    public $avatar;

    /**
     * @var ExtendDateTime
     */
    public $birthday;
	
	/**
	 * @var string email
	 */
	public $email;
	
    /**
     * @var string
     */
    public $job;

    /**
     * @var string 
     */
    public $description;
	
	/**
	 * @var int
	 */
	public $avatarId;
			
    /**
     * @var string utilisé par le form builder pour pouvoir selectionner le template courant de l'utilisateur par défaut
     */
    //public $profileTemplateCssClass;

    /**
     * @param User $user 
     */
    public function __construct(User $user = null)
    {
        $this->user             = null == $user? BNSAccess::getUser() : $user;
        $this->birthday         = $this->user->getBirthday();
        $this->job              = $this->user->getProfile()->getJob();
		$this->email			= $this->user->getEmail();
        $this->description      = $this->user->getProfile()->getDescription();
        $this->avatarId			= $this->user->getProfile()->getAvatarId();
    }

    /**
     * Save into DB
     */
    public function save()
    {
        $this->user->getProfile()->setJob($this->job);
        $this->user->getProfile()->setDescription($this->description);
        if (null != $this->avatarId) {
		  $this->user->getProfile()->setAvatarId($this->avatarId);
        }

        if (null != $this->email && 0 != strcmp($this->email, $this->user->getEmail())) {
            $this->user->setEmail($this->email);
            // Mise à jour de l'email côté central
            BNSAccess::getContainer()->get('bns.user_manager')->updateUser($this->user);
        }

        $this->user->setBirthday($this->birthday);

        // Finally
        $this->user->getProfile()->save();
        $this->user->save();
    }
}