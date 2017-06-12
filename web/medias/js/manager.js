$(function ()
{
	// Collapse title sidebar
	$('body').on('click', '.container-sidebar .title:has(.icon-arrow)', function (e)
	{
		var $this = $(e.currentTarget),
			$row = $this.parent(),
			$content = $row.find('.content-title');
			
		if ($content.css('display') == 'none') {
			$content.slideDown('fast');
			$this.addClass('active');
		}
		else {
			$content.slideUp('fast');
			$this.removeClass('active');
		}
	});

    $(".show-calendar").click(function(){
        $(this).prev('input').focus();
    });
});