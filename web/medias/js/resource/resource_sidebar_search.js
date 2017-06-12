var thesaurusData;

$(function () {
    // Fixed search container
    var searchContainerOffset = $('.thesaurus').offset().top + 48;
    $(window).scroll(function (e) {
        var $searchContainer = $('.search-container');
        if ($(window).scrollTop() > searchContainerOffset) {
            $searchContainer.addClass('fixed');
        }
        else {
            $searchContainer.removeClass('fixed');
        }
    });

    // Show more buttons
    $('body').on('click', '.resource-sidebar .thesaurus-container .filters-container .filter li.more', function (e) {
        var $this = $(e.currentTarget),
            $moreUl = $this.parent().parent().find('ul.more');

        $this.slideUp('fast');
        $moreUl.slideDown('fast');
    });

    // Filters scolom checking
    $('body').on('click', '.resource-sidebar .thesaurus-container .filters-container .filter li', function (e) {
        var $this = $(e.currentTarget);
        if ($this.hasClass('more')) {
            return false;
        }
        
        var dataAttribute = $this.parent().parent().hasClass('group') ? 'group' : 'template-data';

        if ($this.hasClass('checked')) {
            $this.removeClass('checked');
            $('.resource-sidebar .thesaurus-container .tags-container li#' + $this.data(dataAttribute)).remove();
        }
        else {
            var $tag = $('<li />').text($this.text()).attr('id', $this.data(dataAttribute)).data('type', dataAttribute),
            $cross = $('<span />').addClass('cross').html('&times;');
            
            $('.resource-sidebar .thesaurus-container .tags-container').append($tag.append($cross));
            $this.addClass('checked');
        }

        // Update height
        $('.resource-sidebar .search-container').height($('.resource-sidebar .search-container > div').height());

        // Finally
        search();
    });

    // Delete tag
    $('body').on('click', '.resource-sidebar .thesaurus-container .tags-container li', function (e) {
        var $this = $(e.currentTarget),
            dataAttribute = $this.data('type'),
            data = $this.attr('id');

        if ('term' != dataAttribute) {
            $('.resource-sidebar .thesaurus-container .filters-container .filter li[data-' + dataAttribute + '="' + data + '"]').removeClass('checked');
        }

        $this.remove();

        // Update height
        $('.resource-sidebar .search-container').height($('.resource-sidebar .search-container > div').height());

        // Finally
        if ($('.resource-sidebar .thesaurus-container .tags-container li').length > 0) {
            search();
        }
    });

    // Close filter block
    $('body').on('click', '.resource-sidebar .thesaurus-container .filters-container .filter p', function (e) {
        var $filter = $(e.currentTarget).parent();

        if ($filter.hasClass('closed')) {
            $filter.find('ul').first().slideDown('fast');
        }
        else {
            $filter.find('ul').slideUp('fast', function () {
                $filter.find('ul li.more').show();
            });
        }

        $filter.toggleClass('closed');
    });

	// Add new term
	$('body').on('submit', '.resource-sidebar .search-container form', function (e) {
		var $this = $(e.currentTarget),
			$input = $this.find('input[type="text"]');
			
		if ($input.val().length == 0) {
            // Finally
            search();

			return false;
		}

        var found = false;
        $.each($('.resource-sidebar .thesaurus-container .tags-container li'), function (i, item) {
            var $item = $(item);
            if ($item.data('term') == $input.val()) {
                $item.addClass('focus');
                setTimeout(function () {
                    $item.removeClass('focus');
                }, 50);
                found = true;
                
                return false;
            }
        });

        if (found) {
            $input.val('');
            
            return false;
        }

        var $tag = $('<li />').text($input.val()).data('type', 'term').data('term', $input.val()).addClass('term'),
            $cross = $('<span />').addClass('cross').html('&times;');

        $('.resource-sidebar .thesaurus-container .tags-container').append($tag.append($cross));
        $input.val('');

        // Update height
        $('.resource-sidebar .search-container').height($('.resource-sidebar .search-container > div').height());

        // Finally
        search();

		return false;
	});

    // Search pagination
    $('body').on('click', '#search-pagination a', function (e) {
        search($(e.currentTarget).data('page'));
    });

    var ajaxSearchQuery = null;
    function search(page)
    {
        if (page == undefined) {
            page = 1;
        }
        
        var $terms = $('.resource-sidebar .thesaurus-container .tags-container li.term'),
            terms = [];

        $.each($terms, function (i, item) {
            terms.push($(item).data('term'));
        });

        var $filters = $('.resource-sidebar .thesaurus-container .filters-container .filter.scolom li.checked'),
            filters = {};

        $.each($filters, function (i, item) {
            var $item = $(item),
                template = $item.parent().parent().data('template');

            if (typeof filters[template] == 'undefined') {
                filters[template] = [];
            }
            
            filters[template].push($item.data('template-data'));
        });

        // Validate terms & filters
        if (terms.length == 0 && $filters.length == 0) {
            return false;
        }

        var $contexts = $('.resource-sidebar .thesaurus-container .filters-container .filter.group li.checked'),
            contexts = [],
            groups = [];

        $.each($contexts, function (i, item) {
            var $item = $(item);

            if ($item.hasClass('is-group')) {
                groups.push($(item).data('group'));
            }
            else {
                contexts.push($(item).data('group'));
            }
        });

        // Launch search only if one ore more term OR filter has been selected
        if ((contexts.length > 0 || groups.length > 0) && terms.length == 0 && $filters.length == 0) {
            return;
        }

        $("#resource-current").empty();
        $("#resource-navigation").hide();
        $("#resource-navigation-loading").show();

        var url = Routing.generate('resource_search');
        if (page > 1) {
            url = Routing.generate('resource_search_page', {'page': page});
        }

        thesaurusData = {'terms': terms, 'filters': filters, 'contexts': contexts, 'groups': groups};

        // Abort last search
        if (null != ajaxSearchQuery) {
            ajaxSearchQuery.abort();
            ajaxSearchQuery = null;
        }

        ajaxSearchQuery = $.ajax({
            url: url,
            type: 'POST',
            dataType: 'html',
            data: thesaurusData,
            success: function (data) {
                $('.container-block').addClass('resource-list-bg');
                $("#resource-navigation").html(data);

                // Disable loader
                $("#resource-navigation-loading").hide();
                $("#resource-navigation").show();

                // Toolbar
                toolBar.update();

                setTimeout(function () {
                    calculHeightForIE();
                }, 1500);
            }
        })
    }
});