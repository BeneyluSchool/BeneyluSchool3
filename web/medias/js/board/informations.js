$(function ()
{
	// Filtres statut
	$('.information-statuses-filter li').click(function (e)
	{
		var $row = $(e.currentTarget),
			$parent = $row.parent().parent(),
			$checkbox = $row.find('.select');

		// Show loader
		$checkbox.toggleClass('checked');
		// Informations loader
		var $informationsLoader = $('.container-content .layer');
		$informationsLoader.addClass('show');

		$.ajax({
			url: Routing.generate('board_manager_informations'),
			type: 'POST',
			dataType: 'html',
			data: {'is_enabled': $checkbox.hasClass('checked'), 'filter': $row.data('filter')},
			success: function (data)
			{
				$('.board-container .item-list-container').html(data);
			}
		}).done(function ()
		{
			$informationsLoader.removeClass('show');
		});

		return false;
	});
	
	// Filtres statut
	$('.rss-status-filter li').click(function (e)
	{
		var $row = $(e.currentTarget),
			$parent = $row.parent().parent(),
			$checkbox = $row.find('.select');
		
		$enableCheckBox = $parent.find('.rss-enable .select');
		$disableCheckBox = $parent.find('.rss-disable .select');

		// Show loader
		$checkbox.toggleClass('checked');
		// Rss loader
		var $rssLoader = $('.container-content .layer');
		$rssLoader.addClass('show');

		$.ajax({
			url: Routing.generate('board_manager_rss_list'),
			type: 'POST',
			dataType: 'html',
			data: {'enabled': $enableCheckBox.hasClass('checked'), 'disabled': $disableCheckBox.hasClass('checked')},
			success: function (data) {
				$('.board-container .item-list-container').html(data);
			}
		}).done(function () {
			$rssLoader.removeClass('show');
		});

		return false;
	});
	

	// Ajaxification des informations - pagination
	$('body').on('click', '.information-pager', function (e)
	{
		var $this = $(e.currentTarget),
			$layer = $('.container-content .layer');
			
		$layer.addClass('show');

		$.ajax({
			url: $this.attr('href'),
			success: function (data)
			{
				$('.board-container .item-list-container').html(data);
				$layer.removeClass('show');
			}
		});

		return false;
	});
});