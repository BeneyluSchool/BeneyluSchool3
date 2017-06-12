$(function () {
	// Favorite sur un item
	$('body').on('click', '.list-resources .item .favorite', function (e)
	{
		var $this  = $(e.currentTarget),
			$item  = $this.parent().parent(),
			$img   = $item.find('img'),
			$title = $item.find('.title');
			
		$img.css('opacity', 0);
		setTimeout(function () {
			$item.toggleClass('favorite');
			
			setTimeout(function () {
				if ($item.hasClass('favorite')) {
					$img.attr('src', $img.data('favorite-src'));
					$title.find('p').text($title.data('favorite-title'));
				}
				else {
					$img.attr('src', $img.data('normal-src'));
					$title.find('p').text($title.data('normal-title'));
				}

				$img.css('opacity', 1);
			}, 350);
		}, 250);

        var data = {
            'resource_id': $item.data('id')
        };

        if (null != $item.data('provider-id')) {
            data['provider_id'] = $item.data('provider-id');
            data['uai'] = $item.data('uai');
        }

		$.ajax({
			url: $this.attr('href'),
			type: 'POST',
			data: data
		});
		
		return false;
	});

    // Filter process
    $('body').on('click', '.list-resources .filters-container a', function (e) {
        var $this = $(e.currentTarget);

        $("#resource-current").empty();
        $("#resource-navigation").hide();
        $("#resource-navigation-loading").show();

        $.ajax({
            url: Routing.generate('resource_filter'),
            type: 'POST',
            dataType: 'html',
            data: {'type': $this.data('filter')},
            success: function (data) {
                $('.container-block').addClass('resource-list-bg');
                $("#resource-navigation").html(data);

                // Disable loader
                $("#resource-navigation-loading").hide();
                $("#resource-navigation").show();

                // Toolbar
                toolBar.update();
            }
        });

        return false;
    });
});