// Edition d'une matière
$(function ()
{
    $('.group-filter').click (function ()
    {
        var thisCheck = $(this);
        var groupId = thisCheck.attr('id').split('_')[1];
        var add = 0;
        if (thisCheck.hasClass('checked') == false )
        {
            //Send add to session
            add = 1;
        }
        
        $.ajax(
        {
            url: Routing.generate('BNSAppHomeworkBundle_backajax_group_session', {'groupId': groupId, 'add': add}),
            type: 'POST',
            success: function (data)
            {
                if(add == 1)
                {
                    thisCheck.addClass('checked');
                }
                else
                {
                    thisCheck.removeClass('checked');
                }

                if($("#currentDay").length)
                {
                    $("#currentDay").click();
                }
            }
        });

    });
    
    $('.day-filter').click (function ()
    {
        var thisCheck = $(this);
        var dayId = thisCheck.attr('id').split('_')[1];
        var add = 0;
        if (thisCheck.hasClass('checked') == false )
        {
            //Send add to session
            add = 1;
        }
        
        $.ajax(
        {
            url: Routing.generate('BNSAppHomeworkBundle_backajax_day_session', {'dayId': dayId, 'add': add}),
            type: 'POST',
            success: function (data)
            {
                if(add == 1)
                {
                    thisCheck.addClass('checked');
                }
                else
                {
                    thisCheck.removeClass('checked');
                }

                if($("#currentDay").length)
                {
                    $("#currentDay").click();
                }
            }
        });

    });
	
	// Show category input help - fadeOut
	$('.homework-subjects-filter input.add-category').blur(function (e)
	{
		var $row = $(e.currentTarget).parent();
		if ($(e.currentTarget).val() == '') {
			$row.find('.add-category-help').fadeOut('slow');
		}
	});

	// Show category input help - fadeIn
	$('.homework-subjects-filter input.add-category').keypress(function (e)
	{
		var $row = $(e.currentTarget).parent();
		if (e.which != 13) {
			$row.find('.add-category-help').fadeIn('slow');
		}
	});
	
	// Add category process
	$('.homework-subjects-filter form').submit(function (e)
	{
		var $this = $(e.currentTarget),
			$row = $this.parent(),
			$input = $row.find('input[type="text"]').first();

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
			data: {'subject_title': $input.val()},
			success: function (data)
			{
				var $category = $(data);
				$category.css('display', 'none');
				$row.parent().find('.content-category ol.load-sortable').prepend($category);
				$category.slideDown('fast');
				
				// Reset
				$input.val('');
				$this.find('.add-category-help').fadeOut('fast');
			}
		}).done(function ()
		{
			$row.removeClass('loading');
			$loader.fadeOut('fast');
		});

		return false;
	});
	
	// Filtres catégories
	$('body').on('click', '.homework-subjects-filter li', function (e)
	{
		var $row = $(e.currentTarget),
			$checkbox = $row.find('.select').first();
			
		if ($(e.target).parent().hasClass('list-grip')) {
			return;
		}

		$checkbox.toggleClass('checked');
		
		// Loader
		var $loader = $('.content-homework .layer');
		$loader.addClass('show');
		
		var data = $row.attr('id').split('_'),
			id = data[1];

		$.ajax({
			url: Routing.generate('BNSAppHomeworkBundle_backajax_subject_session', {'subjectId': id, 'add': $checkbox.hasClass('checked') ? 1 : 0}),
			type: 'POST',
			success: function (data)
			{
				if ($("#currentDay").length) {
                    $("#currentDay").click();
                }
			}
		}).done(function ()
		{
			$loader.removeClass('show');
		});

		return false;
	});
    
    // Drag'n'drop categories
	$('.homework-subjects-filter ol.load-sortable').nestedSortable({
		forcePlaceholderSize: true,
		errorClass: 'nested-error',
		handle: 'div .list-grip',
		helper:	'original',
		items: 'li',
		maxLevels: 1, // si sous-catégorie, mettre 2, sinon 1
		opacity: .6,
		placeholder: 'nested-placeholder',
		revert: 200,
		tabSize: 25,
		distance: 10,
		tolerance: 'pointer',
		toleranceElement: '> div',
		/*apply: function (e, h)
		{
			var dump = $('.homework-subjects-filter ol.load-sortable').nestedSortable('toHierarchy', {
				startDepthCount: 0,
				placeholder: 'nested-placeholder'
			});
			
			// Save dump
			$.ajax({
				url: Routing.generate('homework_manager_subject_save'),
				type: 'POST',
				dataType: 'json',
				data: {'categories': dump}
			});
		}*/
	});
	
	$('.homework-subjects-filter ol.load-sortable').bind('sortupdate',
        function(event, ui)
		{
            // Récupérer la div que l'on a drag pour récupérer l'id
            // Ensuite sauvegarder cet objet après l'avoir sérialisé
            var target = $(ui.item);
            var targetId = target.attr('id').split('_')[1];
            var leftId = target.prev().attr('id') ? target.prev().attr('id').split('_')[1] : null;
            var rightId = target.next().attr('id') ? target.next().attr('id').split('_')[1] : null;
            var parentId = target.parents('li').attr('id') ? target.parents('li').attr('id').split('_')[1] : null;

            $.ajax(	
            {
                url: Routing.generate('homework_manager_subject_save'), 
                type: 'POST',
                dataType: 'json',
                data: {'left_id': leftId, 'right_id': rightId, 'parent_id': parentId, 'subject_id' : targetId}	
            });

        }
    );
});