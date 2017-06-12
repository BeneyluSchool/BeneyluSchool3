<?php

namespace BNS\App\GroupBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use BNS\App\CoreBundle\Annotation\Rights;
use BNS\App\GroupBundle\Form\Type\ImportFromCSVType;
use BNS\App\CoreBundle\Date\ExtendedDateTime;
use \BNS\App\CoreBundle\Model\Import;
use \BNS\App\CoreBundle\Utils\Crypt;
use BNS\App\CoreBundle\Import\ImportValidator;


use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * @Route("/gestion/importation")
 * @author Florian Rotagnon <florian.rotagnon@atos.net>
 */
class BackImportController extends Controller
{
 
    /**
	 * @Route("/", name="BNSAppGroupBundle_backImportIndex")
	 * @Template("BNSAppGroupBundle:Import:import.html.twig")
	 * @Rights("GROUP_ACCESS_BACK")
	 */
	public function indexAction()
    {	
        $group = $this->get('bns.right_manager')->getCurrentGroup();
        $rm = $this->get('bns.right_manager');

        /* @var $gm \BNS\App\CoreBundle\Group\BNSGroupManager */
        $gm = $this->get('bns.group_manager');
        $gm->setGroup($group);

        $listImports = array();
        //retourne les 10 derniers imports
        $numberLastImport = 10;
		
        $imports = \BNS\App\CoreBundle\Model\ImportQuery::create()
                ->filterByGroupId($group->getId())
                ->limit($numberLastImport)
                ->orderByDateCreate(\Criteria::DESC)
                ->find();
        
		return array(
            'list_imports' => $imports
		);
    }
   
    /**
	 * @Route("/importer/{type}", name="BNSAppGroupBundle_backImportCsv")	 * 
     * @Rights("GROUP_ACCESS_BACK")
	 */
	public function importFromCSVIndexAction($type)
	{
        $typeName = '';
        
        switch($type){
            case 'ecole':
                $type = "school";
                $typeName = $this->get('translator')->trans('OF_SCHOOLS', array(), 'GROUP');
                break;
            case 'classe':
                $type = "classroom";
                $typeName = $this->get('translator')->trans('OF_CLASS', array(), 'GROUP');
                break;
            case 'adulte':
                $type = "adult";
                $typeName = $this->get('translator')->trans('OF_ADTULTS', array(), 'GROUP');
                break;
            default:
                $type = "pupil";
                $typeName = $this->get('translator')->trans('OF_PUPILS', array(), 'GROUP');
                break;
        }
        
		return $this->render('BNSAppGroupBundle:Import:import_csv.html.twig', array(
			'form' => $this->createForm(new ImportFromCSVType($type, $typeName))->createView(),
            'type' => $type,
            'type_name' => $typeName
		));
	}
    
    /**
	 * Action appelé lorsque l'utilisateur clique sur le bouton "J'ai terminé" de la page d'importation
	 *
	 * @Route("/importation", name="BNSAppGroupBundle_doImportCsv")
	 * @Rights("GROUP_ACCESS_BACK")
	 */
	public function doImportFromCSVAction()
	{
        $rm = $this->get('bns.right_manager');
        $result = array();
		$request = $this->getRequest();
        
		if (!$request->isMethod('POST')) {
			throw new HttpException(500, 'Request must be `POST`\'s method!');
		}
        
		$form = $this->createForm(new ImportFromCSVType());
		$form->bind($request);
        
        $type = $form['type']->getData();
        $typeName = $form['type_name']->getData();
        $hasRight = true;
        $errors = null;
                
        //verification des droits
        switch($type) {
            case 'adult':
                if(!$rm->hasRight('GROUP_IMPORT_SCHOOL_ADULTS')) {
                    $hasRight = false;
                }
                break;
            case 'classroom':
                if(!$rm->hasRight('GROUP_IMPORT_CLASSROOMS')) {
                    $hasRight = false;
                }
                break;
            case 'school':
                if(!$rm->hasRight('GROUP_IMPORT_SCHOOLS')) {
                    $hasRight = false;
                }
                break;
            default :
                if(!$rm->hasRight('GROUP_IMPORT_SCHOOL_PUPILS')) {
                    $hasRight = false;
                }
                break;
        }
        
        //si pas les droits message d'erreur
        if(!$hasRight) {
            $this->get('session')->getFlashBag()->add(
						'error',
                $this->get('translator')->trans('FLASH_HAVENT_RIGHT_TO_IMPORT', array('%typeName%' => $typeName), 'GROUP')
            );
            return $this->redirect($this->generateUrl('BNSAppGroupBundle_backImportIndex'));
        }
        
        //si le formulaire est valide
		if (null !== $form['file']->getData() && $form->isValid()) {
			try {
                
                //creation d'un ID unique

                
                //appel de la méthode d'import
                $import = new Import();

                $import->setGroupId($this->get('bns.right_manager')->getCurrentGroupId());
                $import->setUserId($this->getUser()->getId());
                $import->setDateCreate(new \DateTime("NOW"));
                $import->setStatus("IN_PROGRESS");
                $import->setType(strtoupper($type));
                $import->save();
                
                $this->get("import.manager")->createFile($import, $form['file']->getData());
                
                //verification de l'extension
                $nameTab = explode(".", $form['file']->getData()->getClientOriginalName());
                $extension = $nameTab[count($nameTab)-1];
                
                if($extension == "csv") {
                    //test le fichier, retourne un tableau d'erreur
                    $errors_list = ImportValidator::validateImport($import, $type, $this->get("bns.file_system_manager"));
                    
                    //s'il n'y a pas d'erreurs
                    if($import->getDataErrorNbr() == 0)
                    {
                        //importe les donnees
                        $this->get("import.manager")->import($import, $form['file']->getData());
                        $this->get('session')->getFlashBag()->add(
                            'success',
                            $this->get('translator')->trans('IMPORT_IN_PROGRESS_COME_LATER', array('%typeName%' => $typeName), 'GROUP')
                        );
                        $this->get('session')->set("errors_import", null);
                    }
                    else { //sinon on importe pas
                        $tabError = array();
                        $tabError= $this->getErrorsMessages($errors_list);
                        $this->get('session')->set("errors_import", $tabError);
                    }
                }
                else {//l'extension n'est pas csv
                    $this->get('session')->getFlashBag()->add(
                        'error',
                        $this->get('translator')->trans('IMPORT_FAILED_FORMAT_INCORRECT', array('%typeName%' => $typeName), 'GROUP')
                    );
                }
			}
			catch (UploadException $e) {
                $msg = $this->get('translator')->trans('ERROR_TRY_AGAIN_OR_CONTACT_BENEYLU_TEAM', array('%beneylu_brand_name%' => $this->container->getParameter('beneylu_brand_name')), 'GROUP');

				$this->get('session')->getFlashBag()->add('error', $msg);

				return $this->redirect($this->generateUrl('BNSAppGroupBundle:Import:import_csv.html.twig'));
			}

			return $this->redirect($this->generateUrl('BNSAppGroupBundle_backImportIndex'));
		}

		$this->get('session')->getFlashBag()->add('submit_import_form_error', '');

		return $this->render('BNSAppGroupBundle:Import:import_csv.html.twig', array(
            'form' => $form->createView(),
            'type' => $type,
            'type_name' => $typeName
        ));
	}
        
        /**
         *  Fait correspondre à chaque erreur un log significatif pour l'utilisateur avec 
         * le numéro de ligne qui correspond.
         */
        private function getErrorsMessages ($errors_list) {
            $tabError = array();
            $maxLogOfAnError = 20; // Limite le nbr de log pour une même erreur (ex : mauvais format pour toutes les lignes d'une colone )
            foreach ($errors_list as $key=> $error) {
                switch ($key) {
                    case 'size':
                        if ( sizeof($error)!=0 ){
                            $message = "";
                            $errorNb=0;
                            foreach ( $error as $value) {
                                $errorNb++;
                                $message .= $value;
                                if ( $errorNb!=sizeof($error)) {
                                    $message .= "-";
                                }
                                if ( $errorNb >= $maxLogOfAnError) {
                                    $message .= "...";
                                    break;
                                }
                            }
                            $tabError[] = $this->get('translator')->trans('ERROR_NUMBER_EXCEL_COLUMN_INCORRECT', array('%error%' => $message), 'GROUP');
                        }
                        break;
                    case 'uai_form':
                        if ( sizeof($error)!=0 ){
                            $message = "";
                            $errorNb=0;
                            foreach ( $error as $value) {
                                $errorNb++;
                                $message .= $value;
                                if ( $errorNb!=sizeof($error)) {
                                    $message .= "-";
                                }
                                if ( $errorNb >= $maxLogOfAnError) {
                                    $message .= "...";
                                    break;
                                }
                            }
                            $tabError[] = $this->get('translator')->trans('ERROR_UAI_INCORRECTLY_CONSTRUCT', array('%error%' => $message), 'GROUP');
                        }
                        break;
                    case 'uai_exist':
                        if ( sizeof($error)!=0 ){
                            $message = "";
                            $errorNb=0;
                            foreach ( $error as $value) {
                                $errorNb++;
                                $message .= $value;
                                if ( $errorNb!=sizeof($error)) {
                                    $message .= "-";
                                }
                                if ( $errorNb >= $maxLogOfAnError) {
                                    $message .= "...";
                                    break;
                                }
                            }
                            $tabError[] = $this->get('translator')->trans('ERROR_UAI_NUMBER_UNKNOW_IN_DATABASE', array('%error%' => $message), 'GROUP');
                        }
                        break;
                    case "string":
                        if ( sizeof($error)!=0 ){
                            $message = "";
                            $errorNb=0;
                            foreach ( $error as $value) {
                                $errorNb++;
                                $message .= $value;
                                if ( $errorNb!=sizeof($error)) {
                                    $message .= "-";
                                }
                                if ( $errorNb >= $maxLogOfAnError) {
                                    $message .= "...";
                                    break;
                                }
                            }
                            $tabError[] = $this->get('translator')->trans('ERROR_TEXT_INCORRECT', array('%error%' => $message), 'GROUP');
                        }
                        break;
                    case "mail":
                        if ( sizeof($error)!=0 ){
                            $message = "";
                            $errorNb=0;
                            foreach ( $error as $value) {
                                $errorNb++;
                                $message .= $value;
                                if ( $errorNb!=sizeof($error)) {
                                    $message .= "-";
                                }
                                if ( $errorNb >= $maxLogOfAnError) {
                                    $message .= "...";
                                    break;
                                }
                            }
                            $tabError[] = $this->get('translator')->trans('ERROR_EMAIL_INCORRECT', array('%error%' => $message), 'GROUP');
                        }
                        break;
                    case "structure_form":
                        if ( sizeof($error)!=0 ){
                            $message = "";
                            $errorNb=0;
                            foreach ( $error as $value) {
                                $errorNb++;
                                $message .= $value;
                                if ( $errorNb!=sizeof($error)) {
                                    $message .= "-";
                                }
                                if ( $errorNb >= $maxLogOfAnError) {
                                    $message .= "...";
                                    break;
                                }
                            }
                            $tabError[] = $this->get('translator')->trans('ERROR_CODE_STRUCTURE_INCORRECTLY_CONSTRUCT', array('%error%' => $message), 'GROUP');
                        }
                        break;
                    case "structure_exist":
                        if ( sizeof($error)!=0 ){
                            $message = "";
                            $errorNb=0;
                            foreach ( $error as $value) {
                                $errorNb++;
                                $message .= $value;
                                if ( $errorNb!=sizeof($error)) {
                                    $message .= "-";
                                }
                                if ( $errorNb >= $maxLogOfAnError) {
                                    $message .= "...";
                                    break;
                                }
                            }
                            $tabError[] = $this->get('translator')->trans('ERROR_CODE_STRUCTURE_UNKNOW_IN_DATABASE', array('%error%' => $message), 'GROUP');
                        }
                        break;
                    case "insee_form":
                        if ( sizeof($error)!=0 ){
                            $message = "";
                            $errorNb=0;
                            foreach ( $error as $value) {
                                $errorNb++;
                                $message .= $value;
                                if ( $errorNb!=sizeof($error)) {
                                    $message .= "-";
                                }
                                if ( $errorNb >= $maxLogOfAnError) {
                                    $message .= "...";
                                    break;
                                }
                            }
                            $tabError[] = $this->get('translator')->trans('ERROR_INSEE_NUMBER_INCORRECTLY_CONSTRUCT', array('%error%' => $message), 'GROUP');
                        }
                        break;
                    case "insee_exist":
                        if ( sizeof($error)!=0 ){
                            $message = "";
                            $errorNb=0;
                            foreach ( $error as $value) {
                                $errorNb++;
                                $message .= $value;
                                if ( $errorNb!=sizeof($error)) {
                                    $message .= "-";
                                }
                                if ( $errorNb >= $maxLogOfAnError) {
                                    $message .= "...";
                                    break;
                                }
                            }
                            $tabError[] = $this->get('translator')->trans('ERROR_INSEE_NUMBER_UNKNOW_IN_DATABASE', array('%error%' => $message), 'GROUP');
                        }
                        break;
                     case "circo_form":
                        if ( sizeof($error)!=0 ){
                            $message = "";
                            $errorNb=0;
                            foreach ( $error as $value) {
                                $errorNb++;
                                $message .= $value;
                                if ( $errorNb!=sizeof($error)) {
                                    $message .= "-";
                                }
                                if ( $errorNb >= $maxLogOfAnError) {
                                    $message .= "...";
                                    break;
                                }
                            }
                            $tabError[] = $this->get('translator')->trans('ERROR_CIRCO_NUMBER_INCORRECTLY_CONSTRUCT', array('%error%' => $message), 'GROUP');
                        }
                        break;
                    case "circo_exist":
                        if ( sizeof($error)!=0 ){
                            $message = "";
                            $errorNb=0;
                            foreach ( $error as $value) {
                                $errorNb++;
                                $message .= $value;
                                if ( $errorNb!=sizeof($error)) {
                                    $message .= "-";
                                }
                                if ( $errorNb >= $maxLogOfAnError) {
                                    $message .= "...";
                                    break;
                                }
                            }
                            $tabError[] = $this->get('translator')->trans('ERROR_CIRCO_NUMBER_UNKNOW_IN_DATABASE', array('%error%' => $message), 'GROUP');
                        }
                        break;
                    case "zip":
                        if ( sizeof($error)!=0 ){
                            $message = "";
                            $errorNb=0;
                            foreach ( $error as $value) {
                                $errorNb++;
                                $message .= $value;
                                if ( $errorNb!=sizeof($error)) {
                                    $message .= "-";
                                }
                                if ( $errorNb >= $maxLogOfAnError) {
                                    $message .= "...";
                                    break;
                                }
                            }
                            $tabError[] = $this->get('translator')->trans('ERROR_ZIP_CODE_INCORRECTLY_CONSTRUCT', array('%error%' => $message), 'GROUP');
                        }
                        break;
                    case "sexe":
                        if ( sizeof($error)!=0 ){
                            $message = "";
                            $errorNb=0;
                            foreach ( $error as $value) {
                                $errorNb++;
                                $message .= $value;
                                if ( $errorNb!=sizeof($error)) {
                                    $message .= "-";
                                }
                                if ( $errorNb >= $maxLogOfAnError) {
                                    $message .= "...";
                                    break;
                                }
                            }
                            $tabError[] = $this->get('translator')->trans('ERROR_GENDER_MUST_BE_M_F', array('%error%' => $message), 'GROUP');
                        }
                        break;
                    case "role":
                        if ( sizeof($error)!=0 ){
                            $message = "";
                            $errorNb=0;
                            foreach ( $error as $value) {
                                $errorNb++;
                                $message .= $value;
                                if ( $errorNb!=sizeof($error)) {
                                    $message .= "-";
                                }
                                if ( $errorNb >= $maxLogOfAnError) {
                                    $message .= "...";
                                    break;
                                }
                            }
                            $tabError[] = $this->get('translator')->trans('ERROR_ROLE_INCORRECT', array('%error%' => $message), 'GROUP');
                        }
                        break;
                    default:
                        break;
                }
            }
            return ($tabError);
        }
}

?>
