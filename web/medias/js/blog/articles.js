$(function ()
{
	// Filtres statut
	$('.article-statuses-filter li').click(function (e)
	{
		var $row = $(e.currentTarget),
			$parent = $row.parent().parent(),
			$checkbox = $row.find('.select');

		// Show loader
		$checkbox.toggleClass('checked');
		// Articles loader
		var $articlesLoader = $('.container-content .layer');
		$articlesLoader.addClass('show');

		$.ajax({
			url: Routing.generate('blog_manager_articles'),
			type: 'POST',
			dataType: 'html',
			data: {'is_enabled': $checkbox.hasClass('checked'), 'filter': $row.data('filter')},
			success: function (data)
			{
				$('.blog-container .item-list-container').html(data);
			}
		}).done(function ()
		{
			$articlesLoader.removeClass('show');
		});

		return false;
	});
	
	// Filtres catégories
	$('body').on('click', '.article-categories-filter li', function (e)
	{
		var $row = $(e.currentTarget),
			$checkbox = $row.find('.select').first();
			
		if ($(e.target).parent().hasClass('list-grip')) {
			return;
		}

		$checkbox.toggleClass('checked');
		
		// Articles loader
		var $articlesLoader = $('.container-content .layer');
		$articlesLoader.addClass('show');
		
		var data = $row.attr('id').split('_'),
			id = data[1];

		$.ajax({
			url: Routing.generate('blog_manager_articles'),
			type: 'POST',
			dataType: 'html',
			data: {'is_enabled': $checkbox.hasClass('checked'), 'category': id},
			success: function (data)
			{
				$('.blog-container .item-list-container').html(data);
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
				$('.blog-container .item-list-container').html(data);
				$layer.removeClass('show');
			}
		});

		return false;
	});
});