<?php

namespace BNS\App\NotificationBundle\Model;

use BNS\App\CoreBundle\Model\GroupQuery;
use BNS\App\NotificationBundle\TranslateFactory\NotificationTranslateFactory;
use BNS\App\NotificationBundle\Model\om\BaseNotification;
use BNS\App\CoreBundle\Access\BNSAccess;

use JMS\TranslationBundle\Annotation\Ignore;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Skeleton subclass for representing a row from the 'notification' table.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.NotificationBundle.Model
 */
class Notification extends BaseNotification
{
	const ENGINE_EMAIL		= 'EMAIL';
	const ENGINE_SYSTEM		= 'SYSTEM';

	/**
	 * @var ContainerInterface
	 */
	protected static $container;

	/**
	 * @var array<String>
	 */
	private $disabledEngines;

    /**
     * @var String Domaine pour l'envoi des emails
     */
    public $baseUrl;

    /**
     * Inject container when possible
     */
    public function __construct()
    {
        parent::__construct();
        if (!self::$container && null != BNSAccess::getContainer()) {
            self::$container = BNSAccess::getContainer();
        }
    }

	/**
	 * @param ContainerInterface $container
	 * @param int				 $groupId
	 * @param string			 $notificationType
	 * @param array				 $objects
	 */
	public function init($container, $groupId, $notificationType, array $objects)
	{
		self::$container = $container;
		$this->setGroupId($groupId);
		$this->setNotificationTypeUniqueName($notificationType);
		$this->setObjects(serialize($objects));
		$this->setDate(time());
	}

    public function setBaseUrl($baseUrl)
    {
        $this->baseUrl = $baseUrl;
    }

    public function getBaseUrl()
    {
        return $this->baseUrl;
    }

	/**
	 * Send the notification
	 */
	public function send()
	{
		//  L'utilisateur ne peut être NULL
		if (null == $this->aUser) {
			throw new \RuntimeException('The user can NOT be NULL, please specify the user before send a notification !');
		}

        $translator = self::$container->get('translator');
        $oldTranslationLocale = $translator->getLocale();
        $translator->setLocale($this->getUser()->getLang());

		$disabledNotificationEngineUniqueNames = array();

		// On ne traite pas l'envoi par email des utilisateurs qui n'ont pas d'email
		if (null == $this->aUser->getEmail()) {
			$disabledNotificationEngineUniqueNames['EMAIL'] = true;
		}

		// Si ce n'est pas une annonce
		if (null != $this->getGroupId()) {
			// On récupère tous les engines désactivés pour le groupe & le rôle
			$notificationSettings = NotificationSettingsQuery::create('n')
				->where('n.UserId = ?', $this->getTargetUserId())
				->where('n.ContextGroupId = ?', $this->getGroupId())
				->where('n.ModuleUniqueName = ?', $this->getNotificationType()->getModuleUniqueName())
			->find();
		} else {
			// Si c'est une annonce, on récupère les cas où c'est désactivé
			$notificationSettings = NotificationSettingsQuery::create('n')
				->where('n.ContextGroupId IS NULL')
				->where('n.UserId = ?', $this->getTargetUserId())
				->where('n.ModuleUniqueName = ?', $this->getNotificationType()->getModuleUniqueName())
			->find();
		}

		// On récupère les unique names
		$allNotificationEngines = array(
			self::ENGINE_SYSTEM	=> true,
			self::ENGINE_EMAIL	=> true,
		);

		// On récupère les engines désactivés pour le type de notification en cours
		if (null != $this->getNotificationType()->getDisabledEngine()) {
			$disabledNotificationTypeEngine = str_split(',', str_replace(' ', '', $this->getNotificationType()->getDisabledEngine()));
			foreach ($disabledNotificationTypeEngine as $engineUniqueName) {
				$disabledNotificationEngineUniqueNames[$engineUniqueName] = true;
			}
		}

		// On récupère les engines désactivés depuis les settings
		foreach ($notificationSettings as $engine) {
			$disabledNotificationEngineUniqueNames[$engine->getNotificationEngine()] = true;
		}

		// Si au moins un engine est désactivé on envoi sur ceux activés
		if (count($disabledNotificationEngineUniqueNames) > 0) {
			foreach ($allNotificationEngines as $uniqueName => $engine) {
				if (isset($disabledNotificationEngineUniqueNames[$uniqueName])) {
					$this->requestEngine($uniqueName, false);
				} else {
					$this->requestEngine($uniqueName);
				}
			}
		} else {
            // Sinon on envoi toute la sauce
            foreach ($allNotificationEngines as $uniqueName => $engine) {
				$this->requestEngine($uniqueName);
			}
		}

        $translator->setLocale($oldTranslationLocale);
	}

	/**
	 * La notification est prête pour être envoyée grâce aux engines.
	 * Si l'engine est activé on traite la notification, sinon on la traite d'une manière différente voire pas du tout.
	 *
	 * @param string $engine Notification Engine
	 * @param boolean $engineIsActivated
	 */
	public function requestEngine($engine, $engineIsActivated = true)
	{
		switch ($engine)
		{
			case self::ENGINE_SYSTEM:
				$this->sendSystemEngine($engineIsActivated);
			break;

			case self::ENGINE_EMAIL:
				if ($engineIsActivated) {
					$this->sendEmailEngine();
				}
			break;
		}
	}

	/**
	 * @param boolean $engineIsActivated
	 */
	private function sendSystemEngine($engineIsActivated)
	{
		// On n'affiche pas le callback de la notification : on la met directement comme lue
		if (!$engineIsActivated) {
			$this->setIsNew(false);
		}

		$this->save();
	}

	/**
	 * Email
	 */
	private function sendEmailEngine()
	{
		// use target user locale for routes
		if (!self::$container->get('router')->getContext()->getParameter('_locale')) {
			self::$container->get('router')->getContext()->setParameter('_locale', $this->getUser()->getLang());
		}
		$translation = NotificationTranslateFactory::translate($this, self::ENGINE_EMAIL, self::$container->get('translator'), $this->getUser()->getLang());

		$variables = array(
			'title'			=> $translation['title'],
			'notification'	=> $translation['content']
		);

		self::$container->get('bns.mailer')->sendUser('NOTIFICATION', $variables, $this->aUser, array(), $this->getBaseUrl());
	}

	/**
	 * @param string $engine
	 *
	 * @return array
	 */
	public function trans($engine = null)
	{
		return NotificationTranslateFactory::translate($this, $engine, self::$container->get('translator'), $this->getUser()->getLang());
	}

	/**
	 * @param array|string $disabledEngines
	 */
	public function setDisabledEngines($disabledEngines)
	{
		if (is_array($disabledEngines)) {
			$this->disabledEngines = $disabledEngines;
		} else {
			$this->disabledEngines = array($disabledEngines);
		}
	}

	/**
	 * @param Notification $notification
	 * @param Object|mixed $finalObjects
	 * @param string|null $translationName
	 *
	 * @return array
	 */
	protected static function getTranslation(Notification $notification, $finalObjects, $translationName = null)
	{
		if (null == $translationName) {
			$translationNames = array(
				$notification->getNotificationTypeUniqueName() . '.EMAIL.title',
				$notification->getNotificationTypeUniqueName() . '.EMAIL.content',
				$notification->getNotificationTypeUniqueName() . '.SYSTEM.content',
			);

			$translations = array();
			foreach ($translationNames as $translationName) {
				$engine = preg_split('#\.#', $translationName);

				$translations[$engine[1]][$engine[2]] = /** @Ignore */ self::$container->get('translator')->trans($translationName, $finalObjects, $notification->getNotificationTypeUniqueName());
			}

			return $translations;
		}

        /** @Ignore */
		return self::$container->get('translator')->trans($translationName, $finalObjects, $notification->getNotificationTypeUniqueName());
	}

    public static function getGroupLabel($objects)
    {
        if ($objects && isset($objects['groupId'])) {
            $groupLabel = GroupQuery::create()->filterById($objects['groupId'])->select('Label')->findOne();
            if ($groupLabel) {
                return "[" . $groupLabel . "] ";
            }
        }

        return null;
    }
}
