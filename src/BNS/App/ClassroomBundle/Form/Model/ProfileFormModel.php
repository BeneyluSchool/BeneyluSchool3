<?php

namespace BNS\App\ClassroomBundle\Form\Model;

use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\Form\Model\IFormModel;
use BNS\App\CoreBundle\Model\User;

use Symfony\Component\Validator\ExecutionContext;

class ProfileFormModel implements IFormModel
{
    /**
     * @var string firstName
     */
    public $firstName;

    /**
     * @var string lastName
     */
    public $lastName;

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
     * @var String
     */
    public $gender;

    /**
     * @var User
     */
    private $user;

    /**
     * @var String
     */
    public $parentsIdsToDissociate;

    /**
     * @var String
     */
    public $siblingsIdsToDissociate;

    /**
     * @var string utilisé par le form builder pour pouvoir selectionner le template courant de l'utilisateur par défaut
     */
    //public $profileTemplateCssClass;

    /**
     * @var string
     */
    public $lang;

    /**
     * @param User $user
     */
    public function __construct(User $user = null)
    {
        $this->user             = null == $user? BNSAccess::getUser() : $user;
        $this->firstName        = $this->user->getFirstName();
        $this->lastName         = $this->user->getLastName();
        $this->birthday         = $this->user->getBirthday();
        $this->gender           = $this->user->getGender();
        $this->job              = $this->user->getProfile()->getJob();
        $this->email		    = $this->user->getEmail();
        $this->description      = $this->user->getProfile()->getDescription();
        $this->avatarId		    = $this->user->getProfile()->getAvatarId();
        $this->lang = $this->user->getLang();
    }

    /**
     * Save into DB
     */
    public function save()
    {
        $this->user->setFirstName($this->firstName);
        $this->user->setLastName($this->lastName);
        $this->user->setGender($this->gender);
        $this->user->getProfile()->setJob($this->job);
        $this->user->getProfile()->setDescription($this->description);
        if (null != $this->avatarId && '0' != $this->avatarId) {
            $this->user->getProfile()->setAvatarId($this->avatarId);
        }
        else
        {
            $this->user->getProfile()->setAvatarId(null);
        }

        if (null != $this->email && 0 != strcmp($this->email, $this->user->getEmail())) {
            $this->user->setEmail($this->email);
        }

        $this->user->setBirthday($this->birthday);

        // Mise à jour du côté central
        BNSAccess::getContainer()->get('bns.user_manager')->updateUser($this->user);

        // Finally
        $this->user->getProfile()->save();
        $this->user->save();
    }

    /**
     * Constraint validation
     */
    public function isEmailBlankForAdult($context)
    {
        if ($this->user->isAdult() && !$this->email) {
            $isParent = (0 == strcmp('parent', BNSAccess::getContainer()->get('bns.user_manager')->setUser($this->user)->getMainRole()));

            if (!$isParent) {
                $context->buildViolation('ENTER_EMAIL')
                    ->atPath('email')
                    ->setTranslationDomain('CLASSROOM')
                    ->addViolation();
            }
        }
    }

    public function isEmailAlreadyUsed($context)
    {
        if ($this->user->isAdult() && $this->email) {
            $emailUser = BNSAccess::getContainer()->get('bns.user_manager')->getUserByEmail($this->email);
            if ($emailUser && $emailUser->getId() != $this->user->getId()) {
                $context->buildViolation('EMAIL_ALREADY_USED')
                    ->atPath('email')
                    ->setTranslationDomain('CLASSROOM')
                    ->addViolation();
            }
        }
    }
}
