$(function () {
	// Sélection d'un item
	$('body').on('click', '.list-resources .item .select', function (e)
	{
		var $this = $(e.currentTarget),
			$item = $this.parent().parent(),
			$selection = $('#resource-selection'),
			$loader = $selection.find('.loader'),
			$box = null;

		if ($loader.css('display') == 'block') {
			return false;
		}

		if (!$item.hasClass('garbage')) {
			$loader.fadeIn('fast');
		}

		$item.toggleClass('selected');

		if ($item.hasClass('selected')) {
			$box = $selection.find('.box.template').clone();
			$box.removeClass('template');
			$box.find('img').attr('src', $item.find('img').data('normal-src'));
			$box.find('.title').text($item.data('select-name'));
			$box.attr('id', 'box-' + $item.data('id'));
			$box.data('id', $item.data('id'));

			if ($item.hasClass('garbage')) {
				$box.addClass('garbage');
				$box.attr('id', 'box-' + $item.data('id') + '-' + $item.data('label-id'));
				$box.data('label-id', $item.data('label-id'));
				$box.data('label-type', $item.data('label-type'));
			}

			// On active le mode de sélection
			if (!$selection.hasClass('in')) {
				$selection.addClass('in');
			}

			$selection.find('.area').append($box).fadeIn('fast', function () {
				calculateSelectionBoxes($selection);
			});
		}
		else {
			if ($item.hasClass('garbage')) {
				$box = $selection.find('#box-' + $item.data('id') + '-' + $item.data('label-id'));
			}
			else {
				$box = $selection.find('#box-' + $item.data('id'));
			}

			$box.fadeOut('fast', function () {
				$(this).remove();

				// On désactive le mode de sélection
				if ($selection.find('.area .box').length == 0) {
					$selection.removeClass('in');
				}

				calculateSelectionBoxes($selection);
			});
		}

		if (!$item.hasClass('garbage')) {
			$.ajax({
				url: $this.attr('href'),
				type: 'POST',
				dataType: 'json',
				data: {resource_id: $item.data('id')},
				success: function (data) {
					$loader.fadeOut('fast', function () {
						$(this).hide();
					});

					if (data.canManageResource === true) {
						toolBar.canManage = true;
					}
					else {
						toolBar.canManage = false;
					}

					if (!$item.hasClass('garbage')) {
						toolBar.update();
					}
				}
			});
		}

		return false;
	});

	function calculateSelectionBoxes($selection)
	{
		// On recalcul le nombre de documents sélectionnés
		var selectionLength = $selection.find('.area .box').length;
		$selection.find('#selection-count').text(selectionLength);

		if (selectionLength > 1) {
			$selection.find('.header .plural').show();
		}
		else {
			$selection.find('.header .plural').hide();
		}

		if (selectionLength == 0) {
			$('#resource-toolbar-navigate-folder .selection > div').fadeOut('fast');
		}
		else {
			$('#resource-toolbar-navigate-folder .selection > div').fadeIn('fast');
		}

		// ToolBar process
		toolBar.update();
	}

	// Collapse sélection
	$('body').on('click', '#resource-selection .header', function (e)
	{
		if ($(e.target).attr('id') == 'cancel-selection') {
			return;
		}

		var $this = $(e.currentTarget).parent(),
			$selectionArea = $this.find('.area');

		if ($selectionArea.css('display') == 'none') {
			$selectionArea.slideDown('fast');
		}
		else {
			$selectionArea.slideUp('fast');
		}

		$this.toggleClass('out');
	});

	// Action du bouton "Annuler la sélection"
	$('body').on('click', '#cancel-selection', function (e)
	{
		var $this = $(e.currentTarget);

		$('#resource-selection').trigger('cancel-selection');

		$.ajax({
			url: $this.attr('href')
		});

		return false;
	});

	// Création d'un évènement pour supprimer la sélection
	$('body').on('cancel-selection', '#resource-selection', function (e)
	{
		var $selection = $('#resource-selection'),
			$boxes = $selection.find('.area .box'),
			cascading = function () {
			$(this).remove();

			$boxes = $selection.find('.area .box');
			if ($boxes.length > 0) {
				$($boxes.get($boxes.length - 1)).fadeOut(50, cascading);
			}
			else {
				$selection.removeClass('in');
				calculateSelectionBoxes($selection);
				$('#resources .item').removeClass('selected');
			}
		};

		// On lance le cascading de fade out
		$($boxes.get($boxes.length - 1)).fadeOut(50, cascading);
	});

	// Annuler la sélection depuis la select container
	$('body').on('click', '#resource-selection .area .box', function (e)
	{
		var $this = $(e.currentTarget),
			$selection = $('#resource-selection'),
			$loader = $selection.find('.loader');

		if ($loader.css('display') == 'block') {
			return;
		}

		if ($this.hasClass('garbage')) {
			$('#resources .item#item-' + $this.data('id') + '-' + $this.data('label-id')).removeClass('selected');
		}
		else {
			$loader.fadeIn('fast');

			$.ajax({
				url: Routing.generate('resource_selection_toggle'),
				type: 'POST',
				dataType: 'json',
				data: {resource_id: $this.data('id')},
				success: function (data) {
					$loader.fadeOut('fast', function () {
						$(this).hide();
					});

					if (data.canManageResource === true) {
						toolBar.canManage = true;
					}
					else {
						toolBar.canManage = false;
					}

					if (!$this.hasClass('garbage')) {
						toolBar.update();
					}
				}
			});
		}

		$this.fadeOut('fast', function () {
			$(this).remove();

			if ($selection.find('.area .box').length == 0) {
				$selection.removeClass('in');
			}

			calculateSelectionBoxes($selection);
		});
	});

	// Fermeture de l'iframe
	function closeMainIframe()
	{
		$('#resource-iframe', window.parent.document).fadeOut('fast', function () {
			$('#resource-iframe', window.parent.document).remove();
		});
	}

	// Bouton d'insertion d'une sélection
	$('body').on('click', '.resource-document-selection-insert', function(e) {
		var $this = $(e.currentTarget);

		if ($this.hasClass('disabled')) {
			return false;
		}

		$this.addClass('disabled');
		$this.attr('disabled', 'disabled');

		$.ajax({
			url: $this.attr('href'),
			type: 'POST',
			dataType: 'html',
			data: {resource_id: $this.data('id')},
			success: function(data) {
				parent.ed.focus();
				parent.ed.selection.setContent(data);

				$('audio').mediaelementplayer({
				enableAutosize: true,
				plugins: ['flash','silverlight'],
				pluginPath: '/ent/medias/js/resource/',
				flashName: 'flashmediaelement.swf'});

				closeMainIframe();
			}
		});

		return false;
	});

	// Bouton de join d'une selection
	$('body').on('click', '.resource-document-selection-join', function(e) {
		var $this = $(e.currentTarget),
			ref = $this.data('reference');

		if ($this.hasClass('disabled')) {
			return false;
		}

		$this.addClass('disabled');
		$this.attr('disabled', 'disabled');

		$.ajax({
			url: $this.attr('href'),
			type: 'POST',
			dataType: 'html',
			data: {resource_id: $this.data('id')},
			success: function(data) {
				var container = $('#' + ref, window.parent.document);

				// Get the $compile service from the app's injector
				//var injector = $('[ng-app="beneyluSchoolViewerApp"]').injector();
				//var $compile = injector.get('$compile');

				// Compile the HTML into a linking function...
				//var linkFn = $compile(data);
				// ...and link it to the scope we're interested in.
				// Here we'll use the $rootScope.
				//var $rootScope = injector.get('$rootScope');
				//var elem = linkFn($rootScope);
				container.prepend(data);

				// Now that the content has been compiled, linked,
				// and added to the DOM, we must trigger a digest cycle
				// on the scope wez used in order to update bindings.
				//$rootScope.$digest();

				closeMainIframe();
			}
		});

		return false;
	});

	// Bouton de select d'une ressource
	$('body').on('click', '.resource-selection-select', function(e) {
		var $this = $(e.currentTarget),
			final_id = $this.attr('data-final-id'),
			callback = $this.attr('data-callback'),
			resource_id = $this.data('resource-id');

		if ($this.hasClass('disabled')) {
			return false;
		}

		$this.addClass('disabled');
		$this.attr('disabled', 'disabled');

		$('#' + final_id, window.parent.document).trigger('change');
		$.ajax({
			url: $this.attr('href'),
			type: 'POST',
			dataType: 'html',
			data: { resource_id: resource_id },
			success: function (data) {
				if ($('#' + final_id, window.parent.document).length > 0) {
					$('.' + final_id, window.parent.document).remove();
				}
				else {
					$('.' + final_id, window.parent.document).attr('id', final_id);
				}

				$('#' + final_id, window.parent.document).val(resource_id);
				// trigger l'event avec l'instance de jQuery de la "vraie" page, sinon ça marche pas !
				window.parent.$('#' + final_id).trigger('input').trigger('change');
				$('#' + callback, window.parent.document).html(data);
				$('#cancel-' + final_id, window.parent.document).show();
				closeMainIframe();
			}
		});

		return false;
	});

	// Bouton de restauration de la corbeille
	$('#garbage-restore-modal #garbage-restore-confirm').click(function (e)
	{
		var $this = $(e.currentTarget),
			$selection = $('#resource-selection'),
			$loader = $selection.find('.loader');

		if ($this.hasClass('disabled')) {
			return false;
		}

		$('#garbage-restore-modal').modal('hide');
		$this.addClass('disabled');
		$loader.fadeIn('fast');

		var boxes = getGarbageSelectionItems($selection);

		$.ajax({
			url: $this.attr('href'),
			type: 'POST',
			dataType: 'json',
			data: {items: boxes},
			success: function (restoredItems) {
				$('#resource-selection').trigger('cancel-selection');
				$this.removeClass('disabled');
				$loader.fadeOut('fast', function () {
					$(this).hide();
				});

				for (var i in restoredItems) {
					$(restoredItems[i]).fadeOut('fast', function () {
						var $item = $(this),
							$groupItemsContainer = $item.parent().parent();

						$item.remove();

						// Si plus d'item, on supprime aussi la ligne du dossier
						if ($groupItemsContainer.find('.item').length == 0) {
							$groupItemsContainer.slideUp('fast', function () {
								$(this).remove();

								if ($('#resources .item').length == 0) {
									$('#resources .no-item').slideDown('fast');
								}
							});
						}
					});
				}
			}
		});

		return false;
	});

	// Bouton de suppression de la corbeille
	$('#resource-toolbar #resource-delete-forever-button-container a, #resource-toolbar #resource-empty-garbage-button-container a').click(function (e)
	{
		var $this = $(e.currentTarget),
			$loader = $($this.data('target') + ' .loader'),
			$documentContainer = $($this.data('target') + ' #documents-container'),
			$selection = $('#resource-selection'),
			boxes = getGarbageSelectionItems($selection);

		// Reset modal body
		$loader.find('.message').removeClass('hide');
		$loader.fadeIn('fast');
		$documentContainer.html('');

		$.ajax({
			url: $this.attr('href'),
			type: 'POST',
			dataType: 'html',
			data: {items: boxes},
			success: function (data) {
				$documentContainer.html(data);
			}
		}).done(function () {
			$loader.fadeOut('fast', function () {
				$(this).hide();
			});
		});
	});

	// Annulation de la sélection lors de la suppression depuis la corbeille (petite croix)
	$('#garbage-delete-document-modal').on('click', '#documents-container .cross', function (e)
	{
		var $document = $(e.currentTarget).parent().parent();

		// Item container
		$('#resources ' + $document.data('selection-item')).removeClass('selected');
		// Box selection area
		$('#resource-selection .area ' + $document.data('selection-box')).click();

		// Document modal
		$document.slideUp('fast', function ()
		{
			var $parent = $document.parent().parent();
			$(this).remove();

			if ($('#garbage-delete-document-modal #documents-container .document').length == 0) {
				$('#garbage-delete-document-modal').modal('hide');
			}
			else if ($parent.find('.document').length == 0) {
				$parent.slideUp('fast');
			}
		});
	});

	// Annulation de la sélection lors de la suppression depuis la corbeille (petite croix)
	$('#garbage-delete-document-modal').on('click', '#documents-container .cross', function (e)
	{
		var $document = $(e.currentTarget).parent().parent();

		// Item container
		$('#resources ' + $document.data('selection-item')).removeClass('selected');
		// Box selection area
		$('#resource-selection .area ' + $document.data('selection-box')).click();

		// Document modal
		$document.slideUp('fast', function ()
		{
			var $parent = $document.parent().parent();
			$(this).remove();

			if ($('#garbage-delete-document-modal #documents-container .document').length == 0) {
				$('#garbage-delete-document-modal').modal('hide');
			}
			else if ($parent.find('.document').length == 0) {
				$parent.slideUp('fast');
			}
		});
	});

	// Annulation de la sélection lors de la suppression depuis la sélection (petite croix)
	$('#selection-delete-modal').on('click', '#documents-container .cross', function (e)
	{
		var $document = $(e.currentTarget).parent().parent();

		// Item container
		$('#resources ' + $document.data('selection-item')).removeClass('selected');
		// Box selection area
		$('#resource-selection .area ' + $document.data('selection-box')).click();

		// Document modal
		$document.slideUp('fast', function ()
		{
			var $parent = $document.parent().parent();
			$(this).remove();

			if ($('#selection-delete-modal #documents-container .document').length == 0) {
				$('#selection-delete-modal').modal('hide');
			}
			else if ($parent.find('.document').length == 0) {
				$parent.slideUp('fast');
			}
		});
	});

	// Bouton de suppression OU vidage définitif de la corbeille (modal)
	$('#garbage-delete-document-modal #resource-selection-delete-forever-confirm, #empty-garbage-modal #resource-garbage-empty-confirm').click(function (e)
	{
		var $this = $(e.currentTarget),
			$selection = $('#resource-selection'),
			boxes = getGarbageSelectionItems($selection),
			$loader = $($this.data('target') + ' .loader');

		if ($loader.css('display') == 'block') {
			return false;
		}

		$loader.find('.message').addClass('hide');
		$loader.fadeIn('fast');
		$.ajax({
			url: $this.attr('href'),
			type: 'POST',
			dataType: 'json',
			data: {items: boxes},
			success: function (items) {
				$('#resource-selection').trigger('cancel-selection');

				for (var i in items) {
					$('#resources ' + items[i]).fadeOut('fast', function ()
					{
						var $parent = $(this).parent().parent();
						$(this).remove();

						if ($parent.find('.item').length == 0) {
							$parent.slideUp('fast');
						}

						if ($('#resources .item').length == 0) {
							$('#resources .no-item').slideDown('fast');
						}
					});
				}
			}
		}).done(function () {
			$loader.fadeOut('fast', function () {
				$(this).hide();
			});
			$($this.data('target')).modal('hide');
		});

		return false;
	});

	// Bouton de confirmation de mise en corbeille depuis la sélection (modal)
	$('#selection-delete-modal #resource-selection-delete-confirm').click(function (e)
	{
		var $this = $(e.currentTarget),
			$loader = $('#selection-delete-modal .loader');

		if ($loader.css('display') == 'block') {
			return false;
		}

		$loader.find('.message').addClass('hide');
		$loader.fadeIn('fast');
		$.ajax({
			url: $this.attr('href'),
			type: 'POST',
			dataType: 'json',
			success: function (items) {
				$('#resource-selection').trigger('cancel-selection');

				for (var i in items) {
					$('#resources ' + items[i]).fadeOut('fast', function ()
					{
						var $parent = $(this).parent().parent();
						$(this).remove();

						if ($('#resources').hasClass('label-filter-container')) {
							if ($parent.find('.item').length == 0) {
								$parent.slideUp('fast');
							}
						}

						if ($('#resources .item').length == 0) {
							$('#resources .no-item').slideDown('fast');
						}
					});
				}
			}
		}).done(function () {
			$loader.fadeOut('fast', function () {
				$(this).hide();
			});
			$('#selection-delete-modal').modal('hide');
		});

		return false;
	});

	// Bouton de mise en corbeille
	$('#resource-toolbar-selection .delete-resource').click(function (e)
	{
		var $this = $(e.currentTarget),
			$loader = $('#selection-delete-modal .loader'),
			$documentContainer = $('#selection-delete-modal #documents-container');

		// Reset modal body
		$loader.find('.message').removeClass('hide');
		$loader.fadeIn('fast');
		$documentContainer.html('');

		$.ajax({
			url: $this.attr('href'),
			type: 'POST',
			dataType: 'html',
			success: function (data) {
				$documentContainer.html(data);
			}
		}).done(function () {
			$loader.fadeOut('fast', function () {
				$(this).hide();
			});
		});
	});
});

// Permet de récupérer le document et son label depuis la sélection de la corbeille
function getGarbageSelectionItems($selection)
{
	var boxes = [];

	$.each($selection.find('.area .box'), function (i, item) {
		var $item = $(item);
		boxes.push({
			resource_id: $item.data('id'),
			label_id: $item.data('label-id'),
			label_type: $item.data('label-type')
		});
	});

	return boxes;
}
