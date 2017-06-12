<?php

namespace BNS\App\MiniSiteBundle\Model;

use BNS\App\MiniSiteBundle\Model\om\BaseMiniSitePageTextPeer;

/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
class MiniSitePageTextPeer extends BaseMiniSitePageTextPeer
{
	const STATUS_DRAFT_INTEGER					= 0;
	const STATUS_PUBLISHED_INTEGER				= 1;
	const STATUS_FINISHED_INTEGER				= 2;
	const STATUS_WAITING_FOR_CORRECTION_INTEGER	= 3;
}