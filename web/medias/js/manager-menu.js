/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
!function($)
{
	"use strict"

	// Different toggle
	var toggleClick		= '[data-toggle="menu"]',
		toggleKeypress	= '[data-toggle="menu-keypress"]';

	// Vars
	var header			= 'div.header-buttons',
		defaultMenu		= '.switchable-menu',
		$el,
		
	Menu = function (options)
	{
		// TODO configurator
	}

	Menu.prototype = {
		constructor: Menu,
		
		toggle: function (e)
		{
			var $this = $el = $(this),
				target = $this.attr('data-target'),
				$menu = $(header + ' ' + target),
				isActive = $menu.css('display') == 'block';
				
			console.log(header + ' ' + target);
				
			if (target.length == 0) {
				throw new Error('You must be specify a target with data-target attribute !');
			}
			
			// Run only if the menu is not already active
			if (!isActive) {
				run(function ()
				{
					if (defaultMenu == target) {
						// We must clear menu & $el
						$el = null;
					}

					$menu.fadeIn('fast');
				});
			}

			// Delegate the event for keypress only
			if (e.type == 'keypress') {
				return true;
			}
			
			return false;
		}
	}

	/**
	 * Make menu animation, clear the last menu and call the callback
	 */
	function run(callback) {
		if (typeof callback != 'function') {
			callback = function () {};
		}
		
		var first = true,
			appearCallback = function () {
				if (first) {
					first = false;
					callback();
				}
			}
		;
		
		$.each($(header + ' ' + defaultMenu), function (i, item)
		{
			var $item = $(item),
				effect = $item.data('effect') != null ? $item.data('effect') : 'slideUp',
				direction = $item.data('direction') != null ? $item.data('direction') : 'left';
			
			if ($item.css('display') == 'block') {
				if (effect == 'slideUp') {
					$item.slideUp(400, appearCallback);
				}
				else {
					$item.hide(effect, {'direction': direction}, 500, appearCallback);
				}
			}
		});
	}
	
	$.menu = {
		/**
		* Return the current active element menu
		*/
		el: function ()
		{
			if (undefined == $el || null == $el) {
				return false;
			}

			return $el;
		}
	}

	/**
	 * Events
	 */
	$(function () {
		$('body').on('click.menu.data-api', toggleClick, Menu.prototype.toggle);
		$('body').on('keypress.menu.data-api', toggleKeypress, Menu.prototype.toggle);
	})

}(window.jQuery);