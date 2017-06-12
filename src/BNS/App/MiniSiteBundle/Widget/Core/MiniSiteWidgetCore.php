<?php

namespace BNS\App\MiniSiteBundle\Widget\Core;

use BNS\App\MiniSiteBundle\Model\MiniSiteWidget;
use BNS\App\MiniSiteBundle\Form\Type\MiniSiteWidgetType;
use BNS\App\CoreBundle\Access\BNSAccess;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
abstract class MiniSiteWidgetCore extends MiniSiteWidget
{
	/**
	 * @var array<String>
	 */
	protected $properties;

	/**
	 * @var \BNS\App\MiniSiteBundle\Form\Type\MiniSiteWidgetType
	 */
	protected $formType;

	public function __construct()
	{
		// Configure all extra properties & form
		$this->__configure();
		$this->__execute();
	}

	/**
	 * Configure all extra properties & form
	 */
	protected abstract function __configure();

	/**
	 * Register all extra properties with the behavior to create human getter/setter
	 */
	protected function __execute()
	{
		foreach ($this->properties as $property => $data) {
			$this->registerProperty(strtoupper($property));
		}

		$this->formType = new MiniSiteWidgetType();
		$this->formType->setProperties($this->properties);
		$this->formType->setNamespace(get_called_class());
	}

	/**
	 * @param \BNS\App\MiniSiteBundle\Model\MiniSiteWidget $widget
	 *
	 * @return WidgetTemplate
	 */
	public static function create(MiniSiteWidget $widget)
	{
		$className = get_called_class();
		$widgetTemplate = new $className();
		$widgetData = $widget->toArray(\BasePeer::TYPE_NUM);
		$widgetTemplate->hydrate($widgetData);
		$widgetTemplate->setMiniSiteWidgetTemplate($widget->getMiniSiteWidgetTemplate());
		$widgetTemplate->replaceMiniSiteWidgetExtraPropertys($widget->getMiniSiteWidgetExtraProperties());
		$widgetTemplate->getMiniSiteWidgetTemplate();

		if (null == $widgetTemplate->getTitle()) {
			$widgetTemplate->setTitle($widgetTemplate->getMiniSiteWidgetTemplate()->getLabel());
		}

		// Must executed after hydratation
		$widgetTemplate->getFormType()->setId($widget->getId());

		return $widgetTemplate;
	}

	/**
	 * By default the view name is the type to lower. For example :
	 * With widget of type : RSS_FEED, the view name is rss_feed.html.twig
	 *
	 * @return string The widget view name
	 */
	public function getViewName()
	{
		return strtolower($this->getMiniSiteWidgetTemplate()->getType()) . '.html.twig';
	}

	/**
	 * @return string The bundle name where view is located
	 */
	public function getViewBundleName()
	{
		return 'BNSAppMiniSiteBundle:Widget';
	}

	/**
	 * @param boolean $isBack True if back view is needed, false otherwise
	 *
	 * @return string The complete view path, for example : BNSAppMiniSiteBundle:Widget:back(front)_rss_feed.html.twig
	 */
	public function getViewPath($isBack = false)
	{
		return $this->getViewBundleName() . ':' . ($isBack ? 'back_' : 'front_') . $this->getViewName();
	}

	/**
	 * @return MiniSiteWidgetType
	 */
	public function getFormType()
	{
		return $this->formType;
	}

	/**
	 * @return string Return properties as string "property1,property2,property3"
	 */
	public function getPropertiesAsString()
	{
		$properties = '';
		foreach ($this->properties as $property => $data) {
			$properties .= strtolower($property) . ',';
		}

		return substr($properties, 0, -1);
	}
}
