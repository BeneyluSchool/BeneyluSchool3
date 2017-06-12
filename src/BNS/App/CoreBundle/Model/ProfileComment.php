<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CommentBundle\Comment\CommentInterface;
use BNS\App\CoreBundle\Access\BNSAccess;
use BNS\App\CoreBundle\Model\om\BaseProfileComment;
use BNS\App\NotificationBundle\Notification\ProfileBundle\ProfileCommentPendingValidationNotification;
use BNS\App\NotificationBundle\Notification\ProfileBundle\ProfileCommentPublishedForAuthorNotification;
use BNS\App\NotificationBundle\Notification\ProfileBundle\ProfileCommentPublishedNotification;

class ProfileComment extends BaseProfileComment implements CommentInterface
{
	/**
	 * @var ProfileComment
	 */
	private $commentBeforeSave;

	public function __construct()
	{
		$this->applyDefaultValues();
		$this->setDate(time());
	}

	/**
	 * Simple shortcut
	 *
	 * @param PropelPDO $con
	 *
	 * @return User
	 */
	public function getAuthor(PropelPDO $con = null)
	{
		return $this->getUser($con);
	}

	/**
	 * Simple shortcut
	 *
	 * @param User $author
	 */
	public function setAuthor(User $author)
	{
		$this->setUser($author);
	}

	/**
	 * @return User
	 */
	public function getObjectAuthor()
	{
		return $this->getProfileFeed()->getAuthor();
	}

	/**
	 * @return ProfileFeed
	 */
	public function getObject()
	{
		return $this->getProfileFeed();
	}

	/**
	 * @return array
	 */
	public function getObjectRoute()
	{
		return array(
			'profile_manager_feed_visualisation',
			array(
				'feedId' => $this->getObject()->getId()
			)
		);
	}

	/**
	 * @return string
	 */
	public static function getCommentAdminRight()
	{
		return 'PROFILE_ADMINISTRATION';
	}

    public static function getCommentFilter()
    {
        return false;
    }

	/**
	 * @param \PropelPDO $con
	 *
	 * @return int Affected rows
	 */
	public function save(\PropelPDO $con = null)
	{
		$affectedRows = parent::save($con);
		$container	  = BNSAccess::getContainer();
        if(BNSAccess::isConnectedUser())
        {
            $currentGroup = $container->get('bns.right_manager')->getCurrentGroup();

            // If $container == null, this method is called by CLI
            if (null == $container) {
                return $affectedRows;
            }

            if (null == $this->commentBeforeSave) {
                $this->commentBeforeSave = new ProfileComment();
            }

            if ($this->commentBeforeSave->isNew() && $this->getStatus() == 'VALIDATED' ||
                !$this->commentBeforeSave->isNew() && $this->commentBeforeSave->getStatus() != 'VALIDATED' && $this->getStatus() == 'VALIDATED') {
                // Nouveau commentaire publié PAR user POUR utilisateur(s) actif(s) sur la publication
                $users = UserQuery::create('u')
                    ->join('ProfileComment pc')
                    ->where('pc.ObjectId = ?', $this->getObjectId())
                    ->where('pc.Status <> ?', 'REFUSED')
                    ->groupBy('u.Id')
                    ->find();

                $objectAuthor = UserQuery::create('u')
                    ->join('Profile p')
                    ->join('p.ProfileFeed pf')
                    ->where('pf.Id = ?', $this->getObjectId())
                    ->findOne();


                if ($currentGroup->getType() === 'CLASSROOM') {
                    $container->get('notification_manager')->send($users, new ProfileCommentPublishedNotification($container, $this->getId(), $currentGroup->getId()), array(
                        $this->getAuthor(),
                        $objectAuthor
                    ));

                    // Nouveau commentaire publié PAR user POUR auteur
                    $container->get('notification_manager')->send($objectAuthor, new ProfileCommentPublishedForAuthorNotification($container, $this->getId(), $currentGroup->getId()), $this->getAuthor());

                } else {
                    $user = $this->getUser();
                    $classrooms = $container
                        ->get('bns.user_manager')
                        ->setUser($user)
                        ->getGroupsUserBelong('CLASSROOM');

                    if (count($classrooms) > 0) {
                        $classroom = $classrooms[0];
                        $container->get('notification_manager')->send($users, new ProfileCommentPublishedNotification($container, $this->getId(), $classroom->getId()), array(
                            $this->getAuthor(),
                            $objectAuthor
                        ));

                        // Nouveau commentaire publié PAR user POUR auteur
                        $container->get('notification_manager')->send($objectAuthor, new ProfileCommentPublishedForAuthorNotification($container, $this->getId(), $classroom->getId()), $this->getAuthor());
                    }
                }
            }
            elseif ($this->commentBeforeSave->isNew() && $this->getStatus() == 'PENDING_VALIDATION') {

                if ($currentGroup->getType() === 'CLASSROOM') {
                    // Nouveau commentaire en modération PAR user POUR enseignants (via permission)
                    $container->get('notification_manager')->send($container->get('bns.group_manager')->setGroup($currentGroup)->getUsersByPermissionUniqueName('PROFILE_ADMINISTRATION', true), new ProfileCommentPendingValidationNotification($container, $this->getId(), $currentGroup->getId()));
                } else {
                    $user = $this->getUser();
                    $classrooms = $container
                        ->get('bns.user_manager')
                        ->setUser($user)
                        ->getGroupsUserBelong('CLASSROOM');

                    if (count($classrooms) > 0) {
                        $classroom = $classrooms[0];
                        // Nouveau commentaire en modération PAR user POUR enseignants (via permission)
                        $container->get('notification_manager')->send($container->get('bns.group_manager')->setGroup($classroom)->getUsersByPermissionUniqueName('PROFILE_ADMINISTRATION', true), new ProfileCommentPendingValidationNotification($container, $this->getId(), $classroom->getId()));
                    }
                }
            }
        }

		return $affectedRows;
	}

	/**
     * Hydrates (populates) the object variables with values from the database resultset.
     *
     * An offset (0-based "start column") is specified so that objects can be hydrated
     * with a subset of the columns in the resultset rows.  This is needed, for example,
     * for results of JOIN queries where the resultset row includes columns from two or
     * more tables.
     *
     * @param array $row The row returned by PDOStatement->fetch(PDO::FETCH_NUM)
     * @param int $startcol 0-based offset column which indicates which restultset column to start with.
     * @param boolean $rehydrate Whether this object is being re-hydrated from the database.
	 *
     * @return int             next starting column
	 *
     * @throws PropelException - Any caught Exception will be rewrapped as a PropelException.
     */
    public function hydrate($row, $startcol = 0, $rehydrate = false)
    {
		$startcol = parent::hydrate($row, $startcol, $rehydrate);
		$this->commentBeforeSave = clone $this;

		return $startcol;
    }
}
