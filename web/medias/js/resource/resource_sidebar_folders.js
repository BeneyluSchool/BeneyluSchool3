$(function () {
	// Show my documents
    $('#resource-sidebar').on('click', '.my-documents', function (e) {
		var $this = $(e.currentTarget),
			$folders = $('.my-documents-folders');
			
		if ($folders.css('display') == 'none') {
			closeMyDocuments();
            closeThesaurus();
			$folders.slideDown('fast');
			
			$('.group-folders').slideUp('fast', function ()
			{
				$('.group-folders ul').hide();
				$('.group-folders > ul').show();
				$('.group-folders .resource-nav').removeClass('in');
			});
			$('.resource-nav.context').removeClass('in');
		}
		else {
			closeMyDocuments();
            closeThesaurus();
		}
		
		// Revert all active classes
		$('.resource-nav').removeClass('active');
		$this.removeClass('in').addClass('active');
	});
	
	function closeMyDocuments()
	{
		$('.my-documents-folders > ul').slideDown('fast').find('ul').slideUp('fast');
		$('.my-documents-folders .resource-nav').removeClass('in');
		$('.my-documents-folders li').removeClass('active');
		$('.my-documents-folders > ul > li').slideDown('fast');
		$('.resource-sidebar .add-folder-container').slideDown('fast');
	}

    $('#resource-sidebar').on('click', '.my-documents-folders .resource-nav', function (e) {
		$('.my-documents').addClass('in');
	});
	
	// Close my documents folders when click on context
    $('#resource-sidebar').on('click', '.resource-nav.context', function (e) {
		var $this = $(e.currentTarget),
			$folders = $('.group-folders');
		
		if ($folders.css('display') == 'none') {
			closeGroups();
            closeThesaurus();
			$folders.slideDown('fast');
			
			$('.my-documents-folders').slideUp('fast', function ()
			{
				$('.my-documents-folders ul').hide();
				$('.my-documents-folders > ul').show();
				$('.my-documents-folders .resource-nav').removeClass('in');
			});
			$('.my-documents').removeClass('in');
		}
		else {
			closeGroups();
            closeThesaurus();
		}
		
		// Revert all active classes
		$('.resource-nav').removeClass('active');
		$this.removeClass('in').addClass('active');
	});
	
	function closeGroups()
	{
		$('.group-folders > ul').slideDown('fast').find('ul').slideUp('fast');
		$('.group-folders .resource-nav').removeClass('in');
		$('.group-folders li').removeClass('active');
		$('.group-folders > ul > li').slideDown('fast');
		$('.resource-sidebar .add-folder-container').slideDown('fast');
	}

    $('#resource-sidebar').on('click', '.group-folders .resource-nav', function (e) {
		$('.resource-nav.context').addClass('in');
	});

    $('#resource-sidebar').on('click', '.resource-nav.thesaurus', function (e) {
        var $this = $(e.currentTarget),
            $container = $('.thesaurus-container');

        if ($container.css('display') == 'none') {
            $container.slideDown('fast');
            $this.addClass('active');
        }

        closeGroups();
        $('.group-folders').slideUp('fast', function () {
            $('.group-folders ul').hide();
            $('.group-folders > ul').show();
            $('.group-folders .resource-nav').removeClass('in');
        });
        $('.resource-nav.context').removeClass('active');

        closeMyDocuments();
        $('.my-documents-folders').slideUp('fast', function () {
            $('.my-documents-folders ul').hide();
            $('.my-documents-folders > ul').show();
            $('.my-documents-folders .resource-nav').removeClass('in');
        });
        $('.my-documents').removeClass('active');
    });

    function closeThesaurus()
    {
        $('.resource-nav.thesaurus').removeClass('in');
        $('.thesaurus-container').slideUp('fast');
    }
	
	// Navigation
    $('#resource-sidebar').on('click', '.group-folders .resource-nav, .my-documents-folders .resource-nav', function (e) {
		var $this = $(e.currentTarget);
		/*if ($this.hasClass('active')) {
			return false;
		}*/
		
		var $parent = $this.parent().parent().parent();
		var childSelectorClose = '> ul > li > .resource-nav';
		
		// Si c'est pas le grand parent
		if ($parent[0].tagName != 'DIV') {
			$parent = $parent.find('.resource-nav').first();
			$parent.removeClass('active');
			$parent.addClass('in');
		}
		else {
			childSelectorClose = '> div ' + childSelectorClose;
		}

		$('.resource-nav').removeClass('active');
		$this.removeClass('in');
		$this.addClass('active');
		
		// On cache les dossiers de même niveau lorsqu'on active celui-ci
		$.each($parent.parent().find(childSelectorClose), function (i, item)
		{
			var $item = $(item);
			if (!$item.hasClass('active')) {
				$item.parent().slideUp('fast');
			}
		});
		
		// Si le dossier courant permet la création de nouveau dossier ou non
		if ($this.hasClass('no-folder')) {
			$('.resource-sidebar .add-folder-container').slideUp('fast');
		}
		else {
			$('.resource-sidebar .add-folder-container').slideDown('fast');
		}
		
		var $li = $this.parent();
		setTimeout(function ()
		{
			$('.group-folders li, .my-documents-folders li').removeClass('active');
			$li.addClass('active');
		}, 400);
		
		// On affiche tous les enfants de niveau n+1 lorsqu'on affiche le parent
		$.each($li.find('> ul > li'), function (i, item)
		{
			var $item = $(item);
			if ($item.css('display') == 'none') {
				$item.slideDown('fast');
			}
		});
		
		// Si des enfants existent, on les affiche
		var $firstChildren = $li.find('> ul');
		if ($firstChildren.length > 0) {
			if ($firstChildren.css('display') == 'none') {
				$firstChildren.show('blind', {}, 200);
			}

			// On cache les petits enfants s'ils existent
			var $subChildren = $li.find('ul > li > ul');
			if ($subChildren.length > 0) {
				$subChildren.slideUp('fast');
			}

			// On retire l'effect active ou de parent (in)
			$li.find('ul .resource-nav').removeClass('active').removeClass('in');
		}
	});

    // Change context
    $('#resource-sidebar').on('click', '.change-context', function (e) {
        $('#change-context-modal').modal('show');

        if ($('#change-context-modal .modal-body').find('ul').length == 0) {
            $.ajax({
                url: Routing.generate('resource_context_list'),
                dataType: 'html',
                success: function (data) {
                    $('#change-context-modal .modal-body').html(data);
                    $('#change-context-modal .loader').fadeOut('fast', function () {
                        $(this).remove();
                    });
                }
            })
        }

        // prevent history
        return false;
    });
});