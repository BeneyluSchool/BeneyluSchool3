$(document).ready(function ()
{

    $('.title').live("click", function(){
	if($(this).hasClass('active'))
	{
	    $(this).removeClass('active');
	}
	else
	{
	    $(this).addClass('active');	
	}
	
	$(this).siblings().toggle();
    });

});