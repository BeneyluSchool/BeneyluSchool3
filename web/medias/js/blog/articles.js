$(function ()
{
	// Filtres statut
	$('body').on('click', '.article-statuses-filter md-checkbox', function (e)
	{
		var $row = $(e.currentTarget),
			$checkbox = $(this);
		// Articles loader
		var $articlesLoader = $('.container-content .layer');
		$articlesLoader.addClass('show');

		$.ajax({
			url: Routing.generate('blog_manager_articles'),
			type: 'POST',
			dataType: 'html',
			data: {'is_enabled': $checkbox.hasClass('md-checked'), 'filter': $row.attr('value')},//data('filter')},
			success: function (data)
			{
				var injector = $(document).injector();
				var $compile = injector.get('$compile');
				var scope = injector.get('$rootScope').$new();
				var linkFn = $compile(data);
				var element = linkFn(scope);
				$('.item-list-container').html(element);
			}
		}).done(function ()
		{
			$articlesLoader.removeClass('show');
		});

		return false;
	});
	
	// Filtres catégories
	$('body').on('click', '.article-categories-filter md-checkbox', function (e)
	{
		var $row = $(e.currentTarget);

		if ($(e.target).parent().hasClass('list-grip')) {
			return;
		}

		// Articles loader
		var $articlesLoader = $('.container-content .layer');
		$articlesLoader.addClass('show');
		
		var id = $row.attr('value');

		$.ajax({
			url: Routing.generate('blog_manager_articles'),
			type: 'POST',
			dataType: 'html',
			data: {'is_enabled': $row.hasClass('md-checked'), 'category': id},
			success: function (data)
			{
				var injector = $(document).injector();
				var $compile = injector.get('$compile');
				var scope = injector.get('$rootScope').$new();
				var linkFn = $compile(data);
				var element = linkFn(scope);
				$('.item-list-container').html(element);
			}
		}).done(function ()
		{
			$articlesLoader.removeClass('show');
		});

		return false;
	});

	// Ajaxification des articles - pagination
	$('body').on('click', '.article-pager', function (e)
	{
		var $this = $(e.currentTarget),
			$layer = $('.container-content .layer');
			
		$layer.addClass('show');

		$.ajax({
			url: $this.attr('href'),
			success: function (data)
			{
				var injector = $(document).injector();
				var $compile = injector.get('$compile');
				var scope = injector.get('$rootScope').$new();
				var linkFn = $compile(data);
				var element = linkFn(scope);
				$('.item-list-container').html(element);
				$layer.removeClass('show');
			}
		});

		return false;
	});
});
