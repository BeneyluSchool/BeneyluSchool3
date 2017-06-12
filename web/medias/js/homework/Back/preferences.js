$(document).ready(function ()
{   
 
    if ($('.container-content .alert.alert-success').length > 0) {
	setTimeout(function ()
	{
	    $('.bns-alert div').slideUp('fast', function () {
		var $this = $(this);
		$this.parent().slideUp('fast', function () {
		    $this.show()
		})
	    });
	}, 8000); // 8 seconds
    }
    
    
    $('.btn-sidebar-validation').click(function(){
	var currentValue = $('#homeworkpreferences_activate_validation').val();
	var future_value = currentValue == 1 ? 0 : 1;
	
	if(future_value == 1)
	{
	    $(this).removeClass('off');
	    $(this).addClass('on');
	}
	else
	{
	    $(this).removeClass('on');
	    $(this).addClass('off');
	}
	
	$('#homeworkpreferences_activate_validation').val(future_value);
    });
    
     $('.btn-sidebar-past').click(function(){
	var currentValue = $('#homeworkpreferences_show_tasks_done').val();
	var future_value = currentValue == 1 ? 0 : 1;
	
	if(future_value == 1)
	{
	    $(this).removeClass('off');
	    $(this).addClass('on');
	}
	else
	{
	    $(this).removeClass('on');
	    $(this).addClass('off');
	}
	
	$('#homeworkpreferences_show_tasks_done').val(future_value);
    });
    
});