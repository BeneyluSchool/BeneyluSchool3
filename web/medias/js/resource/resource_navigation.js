$(function () {
    // IE10 ingore all html conditions
    if ($.browser.msie && $.browser.version >= 8) {
        $("html").addClass("ie10");
        
        calculHeightForIE();
    }

	// Init history
	$("#resource-navigation").history({
		base_title: 'Médiathèque : ',
		/*reload: function () {
			// Affichage des données
			prepareLoading();

			//sidebarManagement($(this), false);
			$(".container-current-file").hide();

			return $.ajax({
				url: window.location,
				success: function success(data)
				{
					$('.container-block').addClass('resource-list-bg');
					$("#resource-navigation").html(data);
					endNavigationLoading();
				}
			});
		},*/
		onclick: function (e) {
			var $this = $(e.currentTarget);

			// Affichage des données
			$("#resource-current").empty();
			$("#resource-navigation").hide();
			$("#resource-navigation-loading").show();
			$(".container-current-file").hide();

			return $.ajax({
				url: $this.attr('href'),
				success: function success(data)
				{
					$('.container-block').addClass('resource-list-bg');
					$("#resource-navigation").html(data);

					// Disable loader
					$("#resource-navigation-loading").hide();
					$("#resource-navigation").show();

					// Toolbar
					toolBar.update();

                    calculHeightForIE();
				}
			});
		},
		onpopstate: function (e) {
			// Toolbar
			toolBar.update();
		}
	});

	// Resize height
	calculMinHeight();
	$(window).resize(function () {
		calculMinHeight();
	});
});

// Resize the min height of main container
function calculMinHeight()
{
	var height = $(window).height();
	$('.resource-container').css('min-height', (height - 200) + 'px');
}

function calculHeightForIE()
{
    if ($.browser.msie && $.browser.version >= 8) {
        $('#resource-sidebar').css('min-height', '0px');
        $('#resource-sidebar').css('min-height', $('.container-block').height());
    }
}