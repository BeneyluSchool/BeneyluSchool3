<?php

namespace BNS\App\ProfileBundle\Form\Model;

use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\Form\Model\IFormModel;
use BNS\App\CoreBundle\Model\Profile;
use BNS\App\CoreBundle\Model\User;

use Symfony\Component\Validator\ExecutionContextInterface;

class ProfileFormModel implements IFormModel
{
    /**
     * @var string
     */
    public $firstName;

    /**
     * @var string
     */
    public $lastName;

    /**
     * @var int
     */
    public $gender;

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
     * @var string email
     */
    public $email_private;

    /**
     * @var string phone
     */
    public $phone;

    /**
     * @var string $address
     */
    public $address;

    /**
     * @var string
     */
    public $job;

    /**
     * @var string
     */
    public $organization;

    /**
     * @var boolean
     */
    public $publicData;
    /**
     * @var string
     */
    public $description;

    /**
     * @var int
     */
    public $avatarId;

    /**
     * @var User
     */
    private $user;

    /**
     * @var string
     */
    public $lang;

    /**
     * @var string
     */
    public $timezone;



    /**
     * @var string utilisé par le form builder pour pouvoir selectionner le template courant de l'utilisateur par défaut
     */
    //public $profileTemplateCssClass;

    /**
     * @param User $user
     */
    public function __construct(User $user = null)
    {
        $this->user = !$user ? BNSAccess::getUser() : $user;
        /** @var Profile $profile */
        $profile = $this->user->getProfile();
        $this->firstName = $this->user->getFirstName();
        $this->lastName = $this->user->getLastName();
        $this->birthday = $this->user->getBirthday();
        $this->job = $profile->getJob();
        $this->email = $this->user->getEmail();
        $this->email_private = $this->user->getEmailPrivate();
        $this->phone = $this->user->getPhone();
        $this->organization = $profile->getOrganization();
        $this->publicData = $profile->getPublicData();
        $this->address = $profile->getAddress();
        $this->description = $profile->getDescription();
        $this->avatarId = $profile->getAvatarId();
        $this->gender = $this->user->getGender();
        $this->lang = $this->user->getLang();
    }

    /**
     * Save into DB
     */
    public function save()
    {
        $profile = $this->user->getProfile();
        $profile->setJob($this->job);
        $profile->setDescription($this->description);
        if (null != $this->avatarId && '0' != $this->avatarId) {
            $profile->setAvatarId($this->avatarId);
        } else {
            $profile->setAvatarId(null);
        }

        if (null != $this->email && 0 != strcmp($this->email, $this->user->getEmail())) {
            $this->user->setEmail($this->email);
            // Mise à jour de l'email côté central
            BNSAccess::getContainer()->get('bns.user_manager')->updateUser($this->user);
        }

        $this->user->setEmailPrivate($this->email_private);
        $this->user->setPhone($this->phone);
        $profile->setAddress($this->address)->setOrganization($this->organization)->setPublicData($this->publicData);

        if (null != $this->firstName && null != $this->lastName && null != $this->gender) {

            $this->user->setFirstName($this->firstName);
            $this->user->setLastName($this->lastName);
            $this->user->setGender($this->gender);
            $this->user->setPhone($this->phone);


            // Mise à jour de l'email côté central
            BNSAccess::getContainer()->get('bns.user_manager')->updateUser($this->user);
        }

        $this->user->setBirthday($this->birthday);

        // Finally
        $profile->save();
        $this->user->save();
    }

    /**
     * Constraint validation
     */
    public function isEmailBlankForAdult($context)
    {
        if (BNSAccess::getContainer()->get('bns.user_manager')->setUser($this->user)->isAdult(
            ) && (null == $this->email || '' == $this->email)
        ) {
            $context->addViolationAt('email', "Veuillez saisir votre adresse e-mail", array(), null);
        }
    }

}
