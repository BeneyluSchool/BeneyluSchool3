<?php
class BNSTemplatableBehavior extends Behavior
{
	
	public function objectMethods($builder)
	{
		
		$builder->declareClassNamespace("Template","BNS\App\TemplateBundle\Model");
		$builder->declareClassNamespace("TemplateQuery","BNS\App\TemplateBundle\Model");
		$builder->declareClassNamespace("TemplateTypeQuery","BNS\App\TemplateBundle\Model");
		$builder->declareClassNamespace("TemplateJoinObject","BNS\App\TemplateBundle\Model");
		$builder->declareClassNamespace("TemplateJoinObjectQuery","BNS\App\TemplateBundle\Model");
		$builder->declareClassNamespace("TemplateEntityQuery","BNS\App\TemplateBundle\Model");
		$builder->declareClassNamespace("TemplateEntityPeer","BNS\App\TemplateBundle\Model");
		$builder->declareClassNamespace("TemplateEntity","BNS\App\TemplateBundle\Model");
		
		$script = "";
		
		$script .= $this->getTemplateClassNameFunction();
		$script .= $this->getTemplateFunction();
		$script .= $this->setTemplateFunction();
		$script .= $this->getTemplateCustomColorFunction();
		$script .= $this->setTemplateCustomColorFunction();
		$script .= $this->getTemplateCustomFontFunction();
		$script .= $this->setTemplateCustomFontFunction();
		$script .= $this->getTemplateCustomBackgroundFunction();
		$script .= $this->setTemplateCustomBackgroundFunction();
		$script .= $this->toStringThemeCssClassesFunction();
		$script .= $this->getTemplateJoinObjectFunction();
		
		return $script;
	}
	
	public function objectAttributes($builder)
    {
        return "
			private \$template = null;
			private \$templateJoinObject = null;
";
    }
	
	protected  function getTemplateClassNameFunction() 
	{
		return "
/**
 * Retourne la classe de l'objet courant qui est templatable
 *
 */
public function getTemplateClassName()
{
	return substr(strrchr(get_class(\$this),'\\\'),1);
}
";
	}
	
	protected function getTemplateFunction() 
	{
		return "
/**
 * Récupère l'objet Template associé à l'objet courant
 *
 */
public function getTemplate()
{
	if (!isset(\$this->template)) {
		 \$this->template = TemplateQuery::create()->findOneById(\$this->getTemplateJoinObject()->getTemplateId());
	}
	
	return \$this->template;
}
";
	}
	
	protected function setTemplateFunction() 
	{
		return "
/**
 * Permet d'associer un template à l'objet templatable courant
 *
 */
public function setTemplate(Template \$template)
{
	if (TemplateTypeQuery::create()->findOneByBundleName(\$this->getTemplateClassName())->getId() != \$template->getTemplateTypeId())
	{
		throw new \InvalidArgumentException('Le template que vous avez fourni en paramètre ne peut être associé à l\'objet courant !');
	}
	
	if (null != \$template)
	{
		\$templateJoinObject = TemplateJoinObjectQuery::create()
			->filterByObjectId(\$this->getId())
			->filterByObjectClass(get_class(\$this))
		->findOne();
		if (null != \$templateJoinObject)
		{
			\$templateJoinObject->setTemplateId(\$template->getId());
		}
		else
		{
			\$templateJoinObject = new TemplateJoinObject();
			\$templateJoinObject->setTemplateId(\$template->getId());
			\$templateJoinObject->setObjectId(\$this->getId());
			\$templateJoinObject->setObjectClass(get_class(\$this));
		}
		
		\$templateJoinObject->save();
	}
}
";
	}
	
	protected function getTemplateCustomColorFunction() 
	{
		return "
/**
 * Récupère l'objet TemplateEntity de type COLOR associé à l'objet courant
 *
 * @return TemplateEntity retourne l'objet correspondant à la couleur personnalisée ou null si aucune couleur n'a été spécifiée
 */
public function getTemplateCustomColor()
{
	\$templateEntityColorId = \$this->getTemplateJoinObject()->getTemplateEntityColorId();
	\$templateEntityColor = null;
	if (null != \$templateEntityColorId)
	{
		\$templateEntityColor = TemplateEntityQuery::create()->findOneById(\$templateEntityColorId);
	}
	
	return \$templateEntityColor;
}
";
	}
	
	protected function setTemplateCustomColorFunction() 
	{
		return "
/**
 * Sauvegarde la nouvelle couleur personnalisée si elle a était modifiée
 *
 * @params TemplateEntity \$templateEntity objet qui doit avoir son attribut type setté à COLOR
 */
public function setTemplateCustomColor(TemplateEntity \$templateEntity)
{
	if (TemplateEntityPeer::TYPE_COLOR != \$templateEntity->getType())
	{
		throw new \InvalidArgumentException('You try to set TemplateEntity which has type ('.\$templateEntity->getType().') != COLOR!');
	}
	
	\$templateJoinObject = \$this->getTemplateJoinObject();
	\$templateJoinObject->setTemplateEntityColorId(\$templateEntity->getId());
	
	\$templateJoinObject->save();
}
";
	}
	
	protected function getTemplateCustomFontFunction() 
	{
		return "
/**
 * Récupère l'objet TemplateEntity de type FONT associé à l'objet courant
 *
 * @return TemplateEntity retourne l'objet correspondant à la police d'écriture personnalisée ou null si aucune police n'a été spécifiée
 */
public function getTemplateCustomFont()
{
	\$templateEntityFontId = \$this->getTemplateJoinObject()->getTemplateEntityFontId();
	\$templateEntityFont = null;
	if (null != \$templateEntityFontId)
	{
		\$templateEntityFont = TemplateEntityQuery::create()->findOneById(\$templateEntityFontId);
	}
	
	return \$templateEntityFont;
}
";
	}
	
	protected function setTemplateCustomFontFunction() 
	{
		return "
/**
 * Sauvegarde la nouvelle police d'écriture si elle a était modifiée
 *
 * @params TemplateEntity \$templateEntity objet qui doit avoir son attribut type setté à FONT
 */
public function setTemplateCustomFont(TemplateEntity \$templateEntity)
{
	if (TemplateEntityPeer::TYPE_FONT != \$templateEntity->getType())
	{
		throw new \InvalidArgumentException('You try to set TemplateEntity which has type ('.\$templateEntity->getType().') != FONT!');
	}
	
	\$templateJoinObject = \$this->getTemplateJoinObject();
	\$templateJoinObject->setTemplateEntityFontId(\$templateEntity->getId());
	
	\$templateJoinObject->save();
}
";
	}
	
	protected function getTemplateCustomBackgroundFunction() 
	{
		return "
/**
 * Récupère l'objet TemplateEntity de type BACKGROUND associé à l'objet courant
 *
 * @return TemplateEntity retourne l'objet correspondant au fond de la page ou null si aucun fond n'a été spécifié
 */
public function getTemplateCustomBackground()
{
	\$templateEntityBackgroundId = \$this->getTemplateJoinObject()->getTemplateEntityBackgroundId();
	\$templateEntityBackground = null;
	if (null != \$templateEntityBackgroundId)
	{
		\$templateEntityBackground = TemplateEntityQuery::create()->findOneById(\$templateEntityBackgroundId);
	}
	
	return \$templateEntityBackground;
}
";		
	}
	
	protected function setTemplateCustomBackgroundFunction() 
	{
		return "
/**
 * Sauvegarde le nouveau fond de page si elle'il a était modifié
 *
 * @params TemplateEntity \$templateEntity objet qui doit avoir son attribut type setté à BACKGROUND
 */
public function setTemplateCustomBackground(TemplateEntity \$templateEntity)
{
	if (TemplateEntityPeer::TYPE_BACKGROUND != \$templateEntity->getType())
	{
		throw new \InvalidArgumentException('You try to set TemplateEntity which has type ('.\$templateEntity->getType().') != BACKGROUND!');
	}
	
	\$templateJoinObject = \$this->getTemplateJoinObject();
	\$templateJoinObject->setTemplateEntityBackgroundId(\$templateEntity->getId());
	
	\$templateJoinObject->save();
}
";		
	}

	protected function toStringThemeCssClassesFunction() 
	{
		return "
/**
 * Renvoi toutes les classes CSS pour le thème et les propriétés personnalisées (FONT, COLOR, BACKGROUND) en une seule et même chaîne
 * de caractères
 */
public function toStringThemeCssClasses()
{
	\$strThemeCssClasses = '' . \$this->getTemplate()->getCssClass();
	\$customColor = \$this->getTemplateCustomColor();
	if (null != \$customColor) {
		\$strThemeCssClasses .= ' ' . \$customColor->getCssClass();
	}
	
	\$customFont = \$this->getTemplateCustomFont();
	if (null != \$customFont) {
		\$strThemeCssClasses .= ' ' . \$customFont->getCssClass();
	}
	
	\$customBackground = \$this->getTemplateCustomBackground();
	if (null != \$customBackground) {
		\$strThemeCssClasses .= ' ' . \$customBackground->getCssClass();
	}
	
	return \$strThemeCssClasses;
}
";		
	}
	
	protected function getTemplateJoinObjectFunction() 
	{
		return "
/**
 * Rend l'objet TemplateJoinObject qui permet de faire le lien avec un objet templatable (type BLOG, MINISITE, PROFILE) et un template
 *
 * @return TemplateJoinObject retourne l'objet correspondant à la jointure objet templatable et son template
 */
public function getTemplateJoinObject()
{
	\$query = TemplateJoinObjectQuery::create();
	\$query->filterByObjectId(\$this->getId());
	\$query->filterByObjectClass(get_class(\$this));
	\$templateJoinObject = \$query->findOne();
	if(null == \$templateJoinObject) {
		\$defaultTemplate = TemplateQuery::create()
		->findOneByTemplateTypeId(TemplateTypeQuery::create()->findOneByBundleName(\$this->getTemplateClassName())->getId());
		\$templateJoinObject = new TemplateJoinObject();
		\$templateJoinObject->setObjectId(\$this->getId());
		\$templateJoinObject->setObjectClass(get_class(\$this));
		\$templateJoinObject->setTemplateId(\$defaultTemplate->getId());
		\$templateJoinObject->save();
	}
		
	return \$templateJoinObject;
}
";
	}
}

	