<?php

namespace BNS\App\PropelBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class BNSAppPropelBundle extends Bundle
{
	public function getParent()
	{
		return 'PropelBundle';
	}
}
