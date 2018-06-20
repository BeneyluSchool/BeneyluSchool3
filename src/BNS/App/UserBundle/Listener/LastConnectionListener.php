<?php
namespace BNS\App\UserBundle\Listener;

use BNS\App\CoreBundle\Date\DateI18n;
use BNS\App\CoreBundle\Model\User;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

class LastConnectionListener
{

    /**
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @var DateI18n
     */
    private $dateI18n;

    public function __construct(TranslatorInterface $translator, DateI18n $dateI18n)
    {
        $this->translator = $translator;
        $this->dateI18n = $dateI18n;
    }

    /**
     * @param InteractiveLoginEvent $event
     */
    public function onInteractiveLogin(InteractiveLoginEvent $event)
    {
        $session = $event->getRequest()->getSession();
        $session->remove('need_policy_validation');
        $user = $event->getAuthenticationToken()->getUser();

        if ($user instanceof User) {
            if (!$session->has('previous_connection_message')) {
                // Update last connection to now
                $user->updateLastConnection();

                $date = $user->getPreviousConnection();
                if ($date) {
                    $token = ($user->isAdult() ? 'ADULT' : 'CHILD') . '_WELCOME_PREVIOUS_CONNECTION';
                    $text = $this->translator->trans(/** @Ignore() */$token, [
                        '%firstname%' => $user->getFirstName(),
                        '%date%' => $this->dateI18n->process($date, 'none', 'none', 'd LLLL'),
                        '%time%' => $this->dateI18n->process($date, 'none', 'short')
                    ], 'CLASSROOM', $user->getLang()); // locale still not guessed from request, force it from user settings
                } else {
                    $token = ($user->isAdult() ? 'ADULT' : 'CHILD') . '_WELCOME_FIRST_CONNECTION';
                    $text = $this->translator->trans(/** @Ignore() */$token, [
                        '%firstname%' => $user->getFirstName(),
                    ], 'CLASSROOM', $user->getLang()); // locale still not guessed from request, force it from user settings
                }
                $session->getFlashBag()->add('success', $text);
                $session->set('previous_connection_message', true);
            }
        }
    }

}
