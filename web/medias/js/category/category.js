$(function ()
{
	// Check if routes are parameted
	if (typeof categoriesRoutes == undefined) {
		throw new Error('Vous devez paramétrer les routes avant l\'utilisation du script des catégories !');
	}
	
	if (typeof categoriesRoutes.sort != undefined) {
		// Drag'n'drop categories
		$('.content-categories-management ol.load-sortable').nestedSortable({
			forcePlaceholderSize: true,
			errorClass: 'nested-error',
			handle: 'div .list-grip',
			helper:	'original',
			items: 'li',
			maxLevels: 2, // si sous-catégorie, mettre 2, sinon 1
			opacity: .6,
			placeholder: 'nested-placeholder',
			revert: 200,
			tabSize: 25,
			distance: 10,
			tolerance: 'pointer',
			toleranceElement: '> div',
			//cancel: '.active',
			apply: function (e, h)
			{
				var dump = $('.content-categories-management ol.load-sortable').nestedSortable('toHierarchy', {
					startDepthCount: 0,
					placeholder: 'nested-placeholder'
				});

				// Save dump
				$.ajax({
					url: categoriesRoutes.sort,
					type: 'POST',
					dataType: 'json',
					data: {'categories': dump}
				});
			}
		});
	}

	// Expand category editor process
	$('body').on('click', '.content-categories-management ol.load-sortable li > div:first-child, .content-categories-management ol.load-sortable li > ol > li > div:first-child', function (e)
	{
		var $this = $(e.currentTarget);

		// Return true because we waiting for the delete modal event click
		if ($(e.target).hasClass('close-button')) {
			return true;
		}

		// Avoid click event on drag
		if ($(e.target).hasClass('list-grip') || $this.hasClass('active') || $this.hasClass('loading')) {
			return false;
		}

		// Remove active class & other category editor
		$('.content-categories-management ol.load-sortable li > div:first-child, .content-categories-management ol.load-sortable li > ol > li > div:first-child').removeClass('active');
		$('.content-categories-management ol.load-sortable .category-editor .category-icons-list').slideUp('fast', function ()
		{
			$(this).unbind('clickoutside');
		});
		$('.content-categories-management ol.load-sortable .category-editor').slideUp('fast');

		$this.addClass('active');
		var $categoryEditor = $this.parent().find('.category-editor').first();
		if ($categoryEditor.find('.category-icons-list').length == 0) {
			$categoryEditor.append($('.content-categories-management .category-icons-list').first().clone().css('display', 'none'));
		}

		$categoryEditor.slideDown('fast');
	});

	// Add icons list to category editor process
	$('.add-category-button').click(function (e)
	{
		if ($('#new-category-modal .modal-body .category-icons-list').length > 0) {
			return true;
		}

		$('#new-category-modal .modal-body .category-editor').append($('.content-categories-management .category-icons-list').first().clone().css('display', 'none'));
	});

	// Show icons list process
	$('body').on('click', '.content-categories-management .category-icon-selector', function (e)
	{
		var $iconList = $(e.currentTarget).parent().find('.category-icons-list');

		if ($iconList.css('display') == 'none') {
			$iconList.slideDown('fast', function ()
			{
				$iconList.bind('clickoutside', function (e)
				{
					$(this).slideUp('fast');
					$(this).unbind(e);
				});
			});
		}
	});

	// Replace icon process
	$('body').on('click', '.content-categories-management .category-editor .category-icons-list', function (e)
	{
		var $this = $(e.currentTarget),
			$icon = $this.parent().find('.category-icon-selector span'),
			$target = $(e.target);

		if ($target[0].tagName == 'DIV') {
			return false;
		}

		// Icons are differents, replace process
		if (!$icon.hasClass($target.attr('class'))) {
			$icon.fadeOut('fast', function ()
			{
				$icon.removeClass();
				if ($target[0].tagName == 'IMG') {
					$icon.addClass($target.attr('class'));
				}
				else if ($target[0].tagName == 'A') {
					$icon.addClass('default');
				}
				$icon.fadeIn('fast');
			});
		}

		$this.slideUp('fast').unbind('clickoutside');

		return false;
	});

	// Show delete category modal process
	$('body').on('click', '.content-categories-management ol.load-sortable span.close-button', function (e)
	{
		var $this = $(e.currentTarget),
			$div = $this.parent(),
			$row = $div.parent(),
			$modalBody = $('#delete-category-modal .modal-body'),
			categoryId = $row.attr('id').split('_');

		$modalBody.find('span.title').text($div.find('div.title').text());
		$modalBody.find('input#delete-category-id').val(categoryId[1]);

		// This category has sub-categories, show the warn message
		if ($row.find('ol').length > 0) {
			$modalBody.find('p.sub-category-warning').show();
		}
		else {
			$modalBody.find('p.sub-category-warning').hide();
		}

		$('#delete-category-modal').modal('show');
		
		return false;
	});

	// Deleting category modal process
	$('#delete-category-modal .delete-category-button').click(function (e)
	{
		var $this = $(e.currentTarget),
			$modal = $('#delete-category-modal'),
			categoryId = $modal.find('input#delete-category-id').val();

		$this.addClass('disabled').attr('disabled', 'disabled');
		$('.content-categories-management ol.load-sortable li#list_' + categoryId).slideUp('fast', function ()
		{
			$modal.modal('hide');
			$this.removeClass('disabled').removeAttr('disabled');
			$modal.find('p.sub-category-warning').hide();
			$(this).remove();
		});

		$.ajax({
			url: categoriesRoutes.remove,
			type: 'POST',
			data: {'id': categoryId}
		});
	});

	// Cancel modifications
	$('div.header-buttons #category-edit-mode .cancel-button').click(function (e)
	{
		closeCategory();
	});

	// Submit modifications
	$('body').on('click', '.content-categories-management ol.load-sortable .submit-category', function (e)
	{
		var $this = $(e.currentTarget).parent().parent(),
			categoryId = $this.attr('id').split('_'),
			$input = $this.find('input').first(),
			$loader = $this.find('.loader').first(),
			$icon = $this.find('.category-icon-selector span').first(),
			categoryId = categoryId[1];

		closeCategory();
		$this.find('div').first().addClass('loading');
		$loader.fadeIn('fast');
		
		$.ajax({
			url: categoriesRoutes.edit,
			type: 'POST',
			dataType: 'json',
			data: {
				'title': $input.val(),
				'id': categoryId,
				'iconName': $icon.attr('class')
			},
			success: function (data)
			{
				$this.find('div.title').first().text($input.val());
				$this.find('.category-icon').removeClass().addClass('category-icon sprite').addClass($icon.attr('class'));
			}
		}).done(function ()
		{
			$loader.fadeOut('fast');
			$this.find('div').first().removeClass('loading');
		});
	});

	// Submit new category
	$('#new-category-modal .submit-create-category').click(function (e)
	{
		var $this = $(e.currentTarget),
			$modalBody = $('#new-category-modal .modal-body'),
			$loader = $modalBody.find('.loader');

		$loader.fadeIn('fast');

		$.ajax({
			url: categoriesRoutes.insert,
			type: 'POST',
			dataType: 'html',
			data: {'title': $modalBody.find('input[type="text"]').val(), 'iconName': $modalBody.find('.category-icon-selector span').attr('class')},
			success: function (data)
			{
				var $category = $(data);
				$category.css('display', 'none').find('div').first().addClass('new-animation new');
				$('.content-categories-management ol.load-sortable').prepend($category);

				$category.slideDown('fast', function ()
				{
					$category.find('div').first().removeClass('new');
					setTimeout(function ()
					{
						$category.find('div').first().removeClass('new-animation');
					}, 5000);
				});
			}
		}).done(function ()
		{
			// Reset modal
			$modalBody.find('input[type="text"]').val('');
			$modalBody.find('.category-icon-selector span').removeClass().addClass('default');

			$loader.fadeOut('fast');

			if ($this.data('dismiss') == 'modal') {
				$('#new-category-modal').modal('hide');
			}
		});

		return false;
	});

	function closeCategory()
	{
		$('.content-categories-management ol.load-sortable div.active').removeClass('active');
		$('.content-categories-management ol.load-sortable .category-editor .category-icons-list').slideUp('fast', function ()
		{
			$(this).unbind('clickoutside');
		});
		$('.content-categories-management ol.load-sortable .category-editor').slideUp('fast');
	}
});