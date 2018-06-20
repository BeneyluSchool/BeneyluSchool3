<?php

namespace BNS\App\LiaisonBookBundle\Controller;

use BNS\App\CoreBundle\Model\LiaisonBook;
use BNS\App\CoreBundle\Model\LiaisonBookQuery;
use BNS\App\CoreBundle\Model\User;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use BNS\App\CoreBundle\Annotation\Rights;

class FrontController extends Controller
{

    /**
     * Page d'accueil du module : affiche le module et les bonnes pratiques à prendre
     * Nommage "BNSAppLiaisonBookBundle_front" obligatoire, généralement sur le "/" (pas forcément)
     *
     * @Route("/{month}/{year}", name="BNSAppLiaisonBookBundle_front", requirements={"month" = "\d+", "year" = "\d+"}, defaults={"month" = 0, "year" = 0})
     * @Route("/message/{slug}", name="liaison_book_message", defaults={"month" = 0, "year" = 0})
     */
    public function indexAction(Request $request, $month, $year, $slug = null)
    {
        $rightManager = $this->get('bns.right_manager');
        // Contexte = données stockées en session sur le groupe en cours, sur lequel on navigue
        $context = $rightManager->getContext();

        // Récupération de la liste des liaisons books du groupe avec une selection de date (mois/année)
        $default = false;
        if ($month == 0 || $year == 0) {
            $month = date("n");//mois courant
            $year = date("Y");//année courante
            $default = true;
        }

        $liaisonBooks = null;
        $date = mktime(0, 0, 0, $month, 1, $year);
        if ($slug) {
            $liaisonBook = LiaisonBookQuery::create()->filterBySlug($slug)->findOne();
            if ($liaisonBook && $rightManager->hasRight('LIAISONBOOK_ACCESS', $liaisonBook->getGroupId()) && ($liaisonBook->getPublicationDate() <= new \DateTime('now'))) {
                if ($liaisonBook->getIndividualized()) {
                    // check individual access: teacher, addressed user or its parent
                    $canView = $rightManager->hasRight('LIAISONBOOK_ACCESS_BACK', $liaisonBook->getGroupId());
                    if (!$canView) {
                        /** @var User $user */
                        $user = $this->getUser();
                        $children = $user->getActiveChildren();
                        $children->append($user);
                        $validIds = $children->toArray('Id');
                        $addressedIds = $liaisonBook->getaddresseds()->toArray('Id');
                        $canView = !!count(array_intersect_key($validIds, $addressedIds));
                    }
                    if (!$canView) {
                        return $this->redirect($this->generateUrl('BNSAppLiaisonBookBundle_front'));
                    }
                }

                if ($rightManager->getCurrentGroupId() !== $liaisonBook->getGroupId()) {
                    // change context
                    $rightManager->changeContextTo($request, $liaisonBook->getGroup());

                    return $this->redirect($this->generateUrl('liaison_book_message', ['slug' => $slug]));
                }
                // highlight the month of the news
                $date = mktime(0, 0, 0, $liaisonBook->getDate('n'), 1, $liaisonBook->getDate('y'));

                $liaisonBooks = [$liaisonBook];
            } else {
                // invalid slug return to home
                return $this->redirect($this->generateUrl('BNSAppLiaisonBookBundle_front'));
            }
        }

        // only check right here to allow context change
        if (!$rightManager->hasRight('LIAISONBOOK_ACCESS')) {
            throw $this->createAccessDeniedException('');
        }

        if ($default && null === $slug) {
            $this->get('stat.liaisonbook')->visit();
        }

        if (!$liaisonBooks) {
            $liaisonBookManager = $this->get('bns.liaison_book_manager');
            if ($default) {
                $liaisonBooks = $liaisonBookManager->getLastTenLiaisonBooks($context['id'], $this->getUser());
            } else {
                $liaisonBooks = $liaisonBookManager->getLiaisonBooksByGroupIdAndDate($context['id'], $month, $year, true, $this->getUser());
            }
        }

        //L'utilisateur peut-il signer ?
        $canSign = $rightManager->hasRight('LIAISONBOOK_ACCESS_SIGN');

        // Vérification des autres carnets de liaison qu'on peut lire
        $otherLiaisonBooksViewable = $this->get('bns.right_manager')->getGroupsWherePermission("LIAISONBOOK_ACCESS");
        $otherLiaisonBooks = null;
        if (count($otherLiaisonBooksViewable) > 1) {
            $currentGroupId = $this->get('bns.right_manager')->getCurrentGroupId();
            foreach ($otherLiaisonBooksViewable as $otherLiaisonBook) {
                if ($otherLiaisonBook->getId() != $currentGroupId) {
                    $otherLiaisonBooks[] = $otherLiaisonBook;
                }
            }
        }

        return $this->render('BNSAppLiaisonBookBundle:Front:index.html.twig', [
            'context' => $context,
            'news' => $liaisonBooks,
            'canSign' => $canSign,
            'newsDate' => $date,
            'slug' => $slug,
            'isParent' => ('PARENT' === strtoupper($this->get('bns.user_manager')->getMainRole())),
            'otherLiaisonBooks' => $otherLiaisonBooks
        ]);
    }

	/**
	 * Action signature d'un liaisonBook
	 *
	 * @Route("/signer-message/{liaisonBookId}", name="BNSAppLiaisonBookBundle_front_sign", options={"expose"=true})
	 * @Rights("LIAISONBOOK_ACCESS_SIGN")
	 */
	public function signAction($liaisonBookId)
	{
		$right_manager = $this->get('bns.right_manager');
		$liaisonBookManager = $this->get('bns.liaison_book_manager');

		//Recupération des éléments
		$liaisonBook = $liaisonBookManager->getLiaisonBooksById($liaisonBookId);
		$user = $right_manager->getModelUser();
		$context = $right_manager->getContext();
		$currentGroupId = $context['id'];

		if ($user == null || $liaisonBook == null || $currentGroupId != $liaisonBook->getGroupId()) {
			throw new NotFoundHttpException("Mmmmh, ça c'est de la triche petit malin !");
		}

		//Signer
		$liaisonBookManager->signLiaisonBook($user, $liaisonBook);

        //statistic action
        $this->get("stat.liaisonbook")->newSignature();

		return new Response(json_encode(true));
	}
}

