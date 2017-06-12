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
	var isEnabled		= true,
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
			$el = $(this);
			
			return process($el.attr('data-target'), e);
		}
	}
	
	/**
	 * @params target The target DOM selector
	 */
	function process(target, e)
	{
		// Only if managment is enabled
		if (!isEnabled) {
			return false;
		}
		
		if (target.length == 0) {
			throw new Error('You must be specify a target with data-target attribute !');
		}
			
		var $menu = $(target);
		
		// Run only if the menu is not already active
		var isActive = $menu.css('display') == 'block';
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

		// Delegate event for keypress event & inputs
		if (e && e.type == 'keypress' || e.target.tagName == 'INPUT' || e.target.tagName == 'SELECT') {
			return true;
		}
		
		return false;
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
		
		$.each($(defaultMenu), function (i, item)
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
		el: function () {
			if (undefined == $el || null == $el) {
				return false;
			}

			return $el;
		},
		
		/**
		 * Drop manually the menu
		 */
		drop: function (target) {
			return process(target);
		},
		
		disable: function () {
			isEnabled = false;
		},
		
		enable: function () {
			isEnabled = true;
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