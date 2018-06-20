/**
 * @author Sylvain Lorinet <sylvain.lorinet@pixel-cookers.com>
 */
(function ($)
{
	"use strict"

	var lastAjaxQuery,
		clickTriggered = false;

	$.fn.history = function (params)
	{
		var $this		= $(this),
			ajaxHistory = [];

		// array_merge
		params = $.extend({
			rel: 'history',
			base_title: '',
			on: 'body',
			reload: function () {
				// Delete the last history
				this.getHistory().pop();

				// In case of history
				if (this.getHistory().length == 0) {
					var url,
						title = null;

					if (jQuery.browser.webkit) {
						url = window.location.pathname;
						title = history.state;
					}
					else {
						url = window.location.hash.substring(1);
					}

					var $a = $('<a/>').attr('href', url).attr('rel', params['rel']);
					if (title != null) {
						$a.attr('title', title);
					}

					$(params['on']).append($a);
					$a.trigger('click', [{
						disableHistory: true
					}]);

					return null;
				}

				// Call the last history object
				this.getHistoryObject().trigger('click', [{
					disableHistory: true
				}]);

				return null;
			},
			onclick: function (e) {
				var $that = $(e.currentTarget);

				return $.ajax({
					url: $that.attr('href'),
					dataType: 'html',
					success: function (data) {
						$this.html(data);
					}
				});
			},
			getHistory: function () {
				return ajaxHistory;
			},
			getHistoryObject: function () {
				var tmpHistory = this.getHistory();

				return tmpHistory[tmpHistory.length - 1].object;
			},
			onpopstate: function () { }
		}, params);

		// Catch all clicks
		$(params['on']).on('click', 'a[rel="' + params['rel'] + '"]', function (e, options) {
			e.preventDefault();

			// Trigger event
			abortAjax();
			lastAjaxQuery = params['onclick'](e);

			var $that = $(e.currentTarget);

			// If history is called by himself, ending here
			if (options != undefined && options['disableHistory'] === true || $that.data('push') == 'false' || $that.data('push') === false) {
				return;
			}

			var href  = $that.attr('href'),
				title = $that.attr('title');

			if (href.length > 0) {
				if (title != undefined) {
					document.title = params['base_title'] + title;
					title = document.title;
				}

				// Save history to retreive the object if needed
				ajaxHistory.push({
					url: href,
					object: $that
				});

				// Push State
				if (null != window.history && typeof window.history.pushState == 'function' && window.location.origin != undefined) {
					window.history.pushState(title, title, window.location.origin + href);
				}
				else {
					// IE
					window.location.hash = href;
				}

				// For IE & Mozilla
				clickTriggered = true;
			}
		});


		// Event back button or new link
		var browser = jQuery.browser;
		// Chrome
		if (browser.webkit) {
			window.onpopstate = function(event) {
				params['onpopstate'](event);

				if (event && event.state) {
					lastAjaxQuery = params['reload']();

					if (null != window.history) {
						document.title = window.history.state;
					}
				}
			}
		}
		else {
			// IE & co
			window.onhashchange = function (event) {
				if (clickTriggered) {
					clickTriggered = false;

					return;
				}

				lastAjaxQuery = params['reload']();

				if (window.history && window.history.state) {
					document.title = window.history.state;
				}
			}
		}

		/**
		 * Abort the last ajax query
		 */
		function abortAjax()
		{
			if (null != lastAjaxQuery) {
				lastAjaxQuery.abort();
				lastAjaxQuery = null;
			}
		}

		return this;
	};
})(jQuery);