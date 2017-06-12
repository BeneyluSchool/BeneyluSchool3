<?php

namespace BNS\App\BoardBundle\Form\Model;

use BNS\App\CoreBundle\Model\LiaisonBook;
use BNS\App\BoardBundle\Model\BoardQuery;
use BNS\App\BoardBundle\Model\Board;
use BNS\App\BoardBundle\Model\BoardInformation;
use BNS\App\BoardBundle\Model\BoardInformationPeer;
use BNS\App\CoreBundle\Model\User;
use BNS\App\CoreBundle\Right\BNSRightManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ExecutionContextInterface;

/**
 * @author Jérémie Augustin <jeremie.augustin@pixel-cookers.com>
 */
class BoardInformationFormModel
{
    public $title;
    public $status;
    public $content;
    public $programmation_day;
    public $programmation_time;
    public $publication_day;
    public $publication_time;
    public $publication_end_day;
    public $publication_end_time;
    public $is_alert;
    public $destination;

    /**
     * @var BoardInformation
     */
    private $information;

    /**
     * @param \BNS\App\BoardBundle\Model\BoardInformation $information
     */
    public function __construct(BoardInformation $information = null)
    {
        if (null == $information) {
            $this->information = new BoardInformation();
            $this->is_alert = false;
            $this->destination = array('board');

            return;
        }

        $this->information = $information;
        $this->title = $information->getTitle();
        $this->content = $information->getContent();

        $this->is_alert = $information->getIsAlert();

        if (null !== ($expiresAt = $information->getExpiresAt())) {
            $this->publication_end_day = $expiresAt;
            $this->publication_end_time = $expiresAt->getTime();
        }

        if ($information->isProgrammed()) {
            $this->programmation_day = $information->getPublishedAt();
            $this->programmation_time = $information->getPublishedAt()->getTime();
            $this->status = 'PROGRAMMED';
        } elseif ($information->isPublished()) {
            $this->publication_day = $information->getPublishedAt();
            $this->publication_time = $information->getPublishedAt()->getTime();
            $this->status = 'PUBLISHED';
        }
    }

    /**
     * Void
     */
    public function preSave()
    {
        $this->information->setTitle($this->title);
        $this->information->setContent($this->content);
    }

    /**
     * @param BNSRightManager $rightManager
     * @param User $user
     * @param Request $request
     * @param Board $board
     *
     * @throws \RuntimeException
     */
    public function save(BNSRightManager $rightManager, User $user, Request $request, Board $board = null)
    {
        $this->preSave();

        // Process for new information only
        if ($this->information->isNew()) {
            $this->information->setBoard($board);
            $this->information->setUser($user);
        }

        if ($this->status == 'PROGRAMMED') {
            $this->information->setPublishedAt(date('Y-m-d', $this->programmation_day->getTimestamp()) . ' ' . $this->programmation_time);
        } elseif ($this->information->isNew()) {
            $this->information->setPublishedAt(time());
        } elseif (null != $this->publication_day && null != $this->publication_time) {
            $this->information->setPublishedAt(date('Y-m-d', $this->publication_day->getTimestamp()) . ' ' . $this->publication_time);
        } elseif (!$this->information->isPublished()) {
            $this->information->setPublishedAt(time());
        }

        if (null == $this->publication_end_day) {
            $this->information->setExpiresAt(null);
        }

        $this->information->setStatus('PUBLISHED');
        $this->information->setIsAlert($this->is_alert);

        if (null != $this->publication_end_day && null != $this->publication_end_time) {
            $this->information->setExpiresAt(date('Y-m-d', $this->publication_end_day->getTimestamp()) . ' ' . $this->publication_end_time);
        }
        
        if ($this->information->isNew() && $this->status == 'PUBLISHED') {
            // we handle destination save to currente board / liaison book or sub group liaison book
            foreach ($this->destination as $destination) {
                if ('board' === $destination) {
                    $this->information->setBoard($this->getBoard($rightManager->getCurrentGroupId()));
                    $this->information->save();
                } else if ('liaisonbook' === $destination && $rightManager->hasRight('LIAISONBOOK_ACCESS_BACK')) {
                    $liaisonbook = $this->iniLiaisonBook($user, $rightManager->getCurrentGroupId());
                    $liaisonbook->save();
                } else {
                    list($dest, $groupId) = explode('_', $destination);
                    if ('liaisonbook' === $dest && null !== $groupId && $rightManager->hasRight('LIAISONBOOK_ACCESS_BACK', $groupId)) {
                        $liaisonbook = $this->iniLiaisonBook($user, $groupId);
                        $liaisonbook->save();
                    }
                }
            }
        }
        else {
            $this->information->save();
        }
    }

    /**
     * @return BoardInformation
     */
    public function getInformation()
    {
        return $this->information;
    }

    /**
     * Constraint validation
     */
    public function isProgrammationValid($context)
    {
        if ($this->status == 'PROGRAMMED') {
            if (!$this->programmation_day instanceof \DateTime) {
                $context->addViolationAt('programmation_day', "La date est invalide", array(), null);
            } elseif (!preg_match('/^((([0]?[1-9]|1[0-2])(:|\.)[0-5][0-9]((:|\.)[0-5][0-9])?( )?(AM|am|aM|Am|PM|pm|pM|Pm))|(([0]?[0-9]|1[0-9]|2[0-3])(:|\.)[0-5][0-9]((:|\.)[0-5][0-9])?))$/', $this->programmation_time)) {
                $context->addViolationAt('programmation_time', "L'heure est invalide", array(), null);
            } elseif (date('Y-m-d', $this->programmation_day->getTimestamp()) < date('Y-m-d', time()) || strtotime(date('Y-m-d', $this->programmation_day->getTimestamp()) . ' ' . $this->programmation_time) < time()) {
                $context->addViolationAt('programmation_day', "La date et l'heure doivent obligatoirement être dans le futur", array(), null);
            } elseif ($this->publication_end_day instanceof \DateTime && strtotime(date('Y-m-d', $this->publication_end_day->getTimestamp()) . ' ' . $this->publication_end_time) < strtotime(date('Y-m-d', $this->programmation_day->getTimestamp()) . ' ' . $this->programmation_time)) {
                $context->addViolationAt('programmation_day', "La date de fin de publication ne peut pas être plus ancienne que la date de publication", array(), null);
            }
        }
    }

    /**
     * Constraint validation
     */
    public function isProgrammedAndDestination($context)
    {
        if ($this->status == 'PROGRAMMED' && $this->information->isNew() && (count($this->destination) > 1 || !in_array('board', $this->destination))) {
            $context->addViolationAt('status', "Vous ne pouvez pas utiliser la publication programmée si vous souhaitez publier une information dans les carnets de liaison de vos classes", array(), null);
        }
    }

	/**
	 * @param \BNS\App\CoreBundle\Model\User $user
	 * @param int $groupId
	 *
	 * @return \BNS\App\CoreBundle\Model\LiaisonBook
	 */
    protected function iniLiaisonBook(User $user, $groupId)
    {
        $liaisonbook = new LiaisonBook();
        $liaisonbook->setUser($user);
        $liaisonbook->setGroupId($groupId);
        $liaisonbook->setDate('now');
        $liaisonbook->setTitle($this->information->getTitle());
        $liaisonbook->setContent($this->information->getContent());

        return $liaisonbook;
    }

	/**
	 * @param int $groupId
	 * 
	 * @return Board
	 */
    protected function getBoard($groupId)
    {
        return BoardQuery::create()
			->filterByGroupId($groupId)
		->findOne();
    }

    /**
     * Constraint validation
     */
    public function isStatusExists($context)
    {
        $statuses = BoardInformationPeer::getValueSet(BoardInformationPeer::STATUS);
        $statuses[] = 'PROGRAMMED'; // custom status

        if (!in_array($this->status, $statuses)) {
            $context->addViolationAt('status', "Le statut de l'information n'est pas correct, veuillez réessayer", array(), null);
        }
    }
}