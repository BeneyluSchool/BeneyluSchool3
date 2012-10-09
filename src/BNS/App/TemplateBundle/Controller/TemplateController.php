<?php

namespace BNS\App\TemplateBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

use BNS\App\CoreBundle\Utils\Crypt;

use BNS\App\TemplateBundle\Model\Template;
use BNS\App\TemplateBundle\Model\TemplateEntityCollectionQuery;
use BNS\App\TemplateBundle\Model\TemplateEntityCollectionPeer;
use BNS\App\TemplateBundle\Model\TemplateEntityQuery;
use BNS\App\TemplateBundle\Model\TemplateEntityPeer;
use BNS\App\TemplateBundle\Model\TemplateEntity;
use BNS\App\TemplateBundle\Model\TemplateQuery;
use BNS\App\TemplateBundle\Model\TemplatePeer;
use BNS\App\TemplateBundle\Model\TemplateJoinObjectQuery;

class TemplateController extends Controller
{	
   	/**
	 * @Route("/sauvegarder", name="template_bundle_save")
	 */
	public function saveTemplateAction()
	{
		$request = $this->getRequest();
		// On vérifie que la méthode de la requête est bien POST
		if ('POST' != $request->getMethod()) {
			throw new HttpException('500', 'Request\'s method must be \'POST\'!');
		}
		
		// On vérifie que les informations minimales pour mettre à jour le thème soient biens présentes
		if (null == $request->get('selected_template_css_class', null) || null == $request->get('template_join_object_id', null)) {
			throw new HttpException('500', 'Atleast you have to provide two parameters: user_slug and selected_template_css_class!');
		}
		
		// On récupère l'objet Templatable
		$templateJoinObject = TemplateJoinObjectQuery::create()->findOneById(Crypt::decrypt($request->get('template_join_object_id')));
		if (null == $templateJoinObject) {
			throw new HttpException(500, 'You provide an invalid template_join_object_id!');
		}
		
		$methodQueryName = $templateJoinObject->getObjectClass().'Query';
		$templatableObject = $methodQueryName::create()->findOneById($templateJoinObject->getObjectId());
		
		// On vérifie que la classe css pour le thème fournie est valide
		$selectedTemplateCssClass = $request->get('selected_template_css_class');
		$templateSelected = TemplateQuery::create()
			->add(TemplatePeer::CSS_CLASS, $selectedTemplateCssClass)
		->findOne();
		
		if (null == $templateSelected) {
			throw new HttpException('500', 'You provide an invalid profile template css class ('.$selectedTemplateCssClass.')!');
		}
		// On sauvegarde le template
		$templatableObject->setTemplate($templateSelected);
		
		// On vérifie si l'utilisateur a choisit une CUSTOM COLOR
		if (null != $request->get('selected_color_css_class', null)) {
			// Si oui on récupère l'objet TemplateEntity associé
			$customColorSelected = $this->findTemplateEntityByCssClass($request->get('selected_color_css_class'));

			// On vérifie maintenant que la liaison entre la custom couleur et le thème choisit est existant
			// On arrête le processus de sauvegarde si une anomalie est détectée
			$this->get('bns.right_manager')->forbidIf(
				!$this->isLegalLinkBetweenTemplateAndTemplateCustomEntity($templateSelected, $customColorSelected)
			);
			
			// Finalement on sauvegarde la custom propertie
			$templatableObject->setTemplateCustomColor($customColorSelected);
		}
		
		// On vérifie si l'utilisateur a choisit une CUSTOM FONT
		if (null != $request->get('selected_font_css_class', null)) {
			// Si oui on récupère l'objet TemplateEntity associé
			$customFontSelected = $this->findTemplateEntityByCssClass($request->get('selected_font_css_class'));
			
			// On vérifie maintenant que la liaison entre la custom couleur et le thème choisit est existant
			// On arrête le processus de sauvegarde si une anomalie est détectée
			$this->get('bns.right_manager')->forbidIf(
				!$this->isLegalLinkBetweenTemplateAndTemplateCustomEntity($templateSelected, $customFontSelected)
			);
			
			// Finalement on sauvegarde la custom propertie
			$templatableObject->setTemplateCustomFont($customFontSelected);
		}
		
		// On vérifie si l'utilisateur a choisit une CUSTOM BACKGROUND
		if (null != $request->get('selected_background_css_class', null)) {
			// Si oui on récupère l'objet TemplateEntity associé
			$customBackgroundSelected = $this->findTemplateEntityByCssClass($request->get('selected_background_css_class'));
			
			// On vérifie maintenant que la liaison entre la custom couleur et le thème choisit est existant
			// On arrête le processus de sauvegarde si une anomalie est détectée
			$this->get('bns.right_manager')->forbidIf(
				!$this->isLegalLinkBetweenTemplateAndTemplateCustomEntity($templateSelected, $customBackgroundSelected)
			);
			
			// Finalement on sauvegarde la custom propertie
			$templatableObject->setTemplateCustomBackground($customBackgroundSelected);
		}

		return $this->redirect($request->headers->get('referer'));
	}
	
	
	public function renderFontCssToIncludeBlockAction(Template $template)
	{
		// On récupère tous les templates selon un type spécifique (le même que celui du $template)
		$templates = TemplateQuery::create()->findByTemplateTypeId($template->getTemplateTypeId());
		
		$SpecificTemplateTypeFontUrls = array();
		foreach ($templates as $oneTemplate)
		{
			$fontsTemplateEntity = $this->getSpecificTemplateEntityForSpecificTheme($oneTemplate->getCssClass(), TemplateEntityPeer::TYPE_FONT_INTEGER);
			foreach ($fontsTemplateEntity as $fontTemplateEntity)
			{
				$fontData = $fontTemplateEntity->getData();
				if (0 < count($fontData))
				{
					$SpecificTemplateTypeFontUrls[$fontTemplateEntity->getId()] = $fontData[0];
				}
			}
		}
		
		return $this->render('BNSAppTemplateBundle:Template:include_font_css_block.html.twig', array(
			'font_urls' => $SpecificTemplateTypeFontUrls
		));
	}
	
	
	/**
	 * @Route("/recharger-proprietes", name="template_bundle_reload_custom_properties_block", options={"expose"=true})
	 */
	public function reloadCustomPropertiesBlockAction()
	{
		$request = $this->getRequest();
		if (!$request->isXmlHttpRequest())
		{
			throw new HttpException('500', 'Must be XmlHttpRequest!');
		}
		
		if ('POST' != $request->getMethod())
		{
			throw new HttpException('500', 'Request\'s method must be \'POST\'!');
		}
				
		$templateJoinObject = TemplateJoinObjectQuery::create()->findOneById(Crypt::decrypt($request->get('template_join_object_id')));
		if (null == $templateJoinObject) {
			throw new HttpException(500, 'You provide an invalid template_join_object_id!');
		}
		
		$methodQueryName = $templateJoinObject->getObjectClass().'Query';
		$templatableObject = $methodQueryName::create()->findOneById($templateJoinObject->getObjectId());
		
		return $this->render('BNSAppTemplateBundle:Template:template_custom_property_block.html.twig', array(
			'template_css_class'	=> $request->get('selected_template_css_class'),
			'templatable_object'	=> $templatableObject
		));
	}
	
	/**
	 * Permet de rendre le champ input=hidden qui contient l'id de l'objet TemplateJoinObject crypté
	 * 
	 * @param type $templatableObject
	 */
	public function secureTemplatableObjectAction($templatableObject)
	{
		return $this->render('BNSAppTemplateBundle:Template:templatable_hidden_input_block.html.twig', array(
			'template_join_object_id' => Crypt::encrypt($templatableObject->getTemplateJoinObject()->getId())
		));
	}
	
	
	/**
	 * Permet d'afficher la liste des thèmes que l'utilisateur peut choisir pour le Bundle en question
	 * 
	 * @param Template $currentUserTemplate l'objet template correspondant au thème actuellement utilisé par l'utilisateur
	 */
	public function renderThemePickerAction(Template $currentUserTemplate)
	{
		$allThemesForSpecificBundle = TemplateQuery::create()
			->add(TemplatePeer::TEMPLATE_TYPE_ID, $currentUserTemplate->getTemplateTypeId())
		->find();
		
		return $this->render('BNSAppTemplateBundle:ThemePicker:theme_list.html.twig', array(
			'templates'				=> $allThemesForSpecificBundle,
			'user_current_template' => $currentUserTemplate,
		));
	}
	
	
	public function renderColorPickerAction($templateCssClass, $userCurrentColorTemplateEntity = null)
	{
		$allColorsForSpecificTheme = $this->getSpecificTemplateEntityForSpecificTheme($templateCssClass, TemplateEntityPeer::TYPE_COLOR_INTEGER);
		
		return $this->render('BNSAppTemplateBundle:ColorPicker:color_list.html.twig', array(
			'colors'						=> $allColorsForSpecificTheme,
			'user_current_color_css_class'	=> (null != $userCurrentColorTemplateEntity? $userCurrentColorTemplateEntity->getCssClass() : '')
		));

	}
	
	
	public function renderFontPickerAction($templateCssClass, $userCurrentFontTemplateEntity = null)
	{
		$allFontsForSpecificTheme = $this->getSpecificTemplateEntityForSpecificTheme($templateCssClass, TemplateEntityPeer::TYPE_FONT_INTEGER);
				
		return $this->render('BNSAppTemplateBundle:FontPicker:font_list.html.twig', array(
			'fonts'							=> $allFontsForSpecificTheme,
			'user_current_font_css_class'	=> (null != $userCurrentFontTemplateEntity? $userCurrentFontTemplateEntity->getCssClass() : '')
		));
	}
	
	
	public function renderBackgroundPickerAction($templateCssClass, $userCurrentBackgroundTemplateEntity = null)
	{
		$allBackgroundsForSpecificTheme = $this->getSpecificTemplateEntityForSpecificTheme($templateCssClass, TemplateEntityPeer::TYPE_BACKGROUND_INTEGER);
	
		return $this->render('BNSAppTemplateBundle:BackgroundPicker:background_list.html.twig', array(
			'backgrounds'						=> $allBackgroundsForSpecificTheme,
			'user_current_background_css_class'	=> (null != $userCurrentBackgroundTemplateEntity? $userCurrentBackgroundTemplateEntity->getCssClass() : '')
		));
	}
	
	
	private function getSpecificTemplateEntityForSpecificTheme($templateCssClass, $templateEntityType)
	{
		$currentTemplate = TemplateQuery::create()
			->add(TemplatePeer::CSS_CLASS, $templateCssClass)
		->findOne();
		
		if (null == $currentTemplate) {
			throw new InvalidArgumentException('The $templateCssClass is not valid!');
		}
		
		$allColorForSpecificThemeIds = array();
		foreach (TemplateEntityCollectionQuery::create()->findByTemplateId($currentTemplate->getId()) as $templateEntityCollection) {
			$allColorForSpecificThemeIds[] = $templateEntityCollection->getTemplateEntityId();
		}
		
		return TemplateEntityQuery::create()
			->joinWithI18n($this->get('bns.right_manager')->getLocale())
			->add(TemplateEntityPeer::TYPE, $templateEntityType)
			->add(TemplateEntityPeer::ID, $allColorForSpecificThemeIds, \Criteria::IN)
		->find();
	}
	
	private function isLegalLinkBetweenTemplateAndTemplateCustomEntity(Template $template, TemplateEntity $templateEntity)
	{
		$templateEntityCollection = TemplateEntityCollectionQuery::create()
			->add(TemplateEntityCollectionPeer::TEMPLATE_ID, $template->getId())
			->add(TemplateEntityCollectionPeer::TEMPLATE_ENTITY_ID, $templateEntity->getId())
		->findOne();
		
		return null != $templateEntityCollection;
	}
	
	private function findTemplateEntityByCssClass($templateCssClass)
	{
		$templateEntity = TemplateEntityQuery::create()
				->add(TemplateEntityPeer::CSS_CLASS, $templateCssClass)
		->findOne();
		
		if (null == $templateEntity) {
			throw new HttpException('500', 'You provide an invalid template entity css class ('.$request->get('$templateCssClass').')!');
		}
		
		return $templateEntity;
	}
}
