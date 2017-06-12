<?php

namespace BNS\App\MiniSiteBundle\Install;

use BNS\App\InstallBundle\Process\AbstractInstallProcess;
use BNS\App\MiniSiteBundle\Model\MiniSiteWidgetTemplate;
use Symfony\Component\Yaml\Yaml;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class InstallProcess extends AbstractInstallProcess
{
    public function postModuleInstall()
    {
        // Installing widget templates
        $widgetDataFile = __DIR__ . '/../Resources/install/widget_template_data.yml';
        if (!is_file($widgetDataFile)) {
            throw new \RuntimeException('The minisite widget template data file is NOT found in "Resources/install/widget_template_data.yml" !');
        }

        $widgetData = Yaml::parse($widgetDataFile);
        foreach ($widgetData as $widgetName => $data) {
            $widget = new MiniSiteWidgetTemplate();
            $widget->setType($widgetName);
            $widget->save();
        }
    }
}
