<?php

namespace BNS\App\CoreBundle\Model;

use BNS\App\CoreBundle\Model\om\BaseMiniSitePagePeer;

/**
 * Skeleton subclass for performing query and update operations on the 'mini_site_page' table.
 *
 * You should add additional methods to this class to meet the
 * application requirements.  This class will only be generated as
 * long as it does not already exist in the output directory.
 *
 * @package    propel.generator.src.BNS.App.CoreBundle.Model
 */
class MiniSitePagePeer extends BaseMiniSitePagePeer
{
	const STATUS_DRAFT_INTEGER					= 0;
	const STATUS_PUBLISHED_INTEGER				= 1;
	const STATUS_FINISHED_INTEGER				= 2;
	const STATUS_WAITING_FOR_CORRECTION_INTEGER	= 3;
}