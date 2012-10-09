// Edition d'une catégorie
$(function ()
{
	// Tooltip for category icons list
	$('.container-sidebar .content-title .category-icon').tooltip({
		placement: 'right'
	});

	// Show icons list
	$('.article-categories-filter .category-icon').click(function (e)
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
	$('.article-categories-filter .category-icons-list').click(function (e)
	{
		var $this = $(e.currentTarget),
			$icon = $this.parent().find('.category-icon span'),
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

	// Show category input help - fadeOut
	$('.article-categories-filter input.add-category').blur(function (e)
	{
		var $row = $(e.currentTarget).parent();
		if ($(e.currentTarget).val() == '') {
			$row.find('.add-category-help').fadeOut('slow');
		}
	});

	// Show category input help - fadeIn
	$('.article-categories-filter input.add-category').keypress(function (e)
	{
		var $row = $(e.currentTarget).parent();
		if (e.which != 13) {
			$row.find('.add-category-help').fadeIn('slow');
		}
	});

	// Add category process
	$('.article-categories-filter form').submit(function (e)
	{
		var $this = $(e.currentTarget),
			$row = $this.parent(),
			$input = $row.find('input[type="text"]').first(),
			$icon = $this.find('.category-icon span');

		if ($row.hasClass('loading')) {
			return false;
		}

		$row.addClass('loading');
		var $loader = $row.find('.loader');
		$loader.fadeIn('fast');

		$.ajax({
			url: $this.attr('action'),
			type: 'POST',
			dataType: 'html',
			data: {'title': $input.val(), 'iconName': $icon.attr('class')},
			success: function (data)
			{
				var $category = $(data);
				$category.css('display', 'none');
				$row.parent().find('.content-category ol.load-sortable').prepend($category);
				$category.slideDown('fast');
				
				// Reset
				$input.val('');
				$icon.removeClass().addClass('default');
				$this.find('.add-category-help').fadeOut('fast');
			}
		}).done(function ()
		{
			$row.removeClass('loading');
			$loader.fadeOut('fast');
		});

		return false;
	});

	// Drag'n'drop categories
	$('.article-categories-filter ol.load-sortable').nestedSortable({
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
		apply: function (e, h)
		{
			var dump = $('.article-categories-filter ol.load-sortable').nestedSortable('toHierarchy', {
				startDepthCount: 0,
				placeholder: 'nested-placeholder'
			});
			
			// Save dump
			$.ajax({
				url: Routing.generate('blog_manager_category_save'),
				type: 'POST',
				dataType: 'json',
				data: {'categories': dump}
			});
		}
	});
});