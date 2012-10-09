$(function ()
{    
    $('.header-buttons .submit-profile').click(function ()
    {
        $('form#save-profile').submit();
    });

    $('.header-buttons .write-new-status-btn').click(function ()
    {
        var $newStatusForm = $('.add-new-status');
        
		$newStatusForm.slideDown('slow');
		$newStatusForm.removeClass('hide');
    });
	
	$('.header-buttons .cancel-statut').click(function (e)
	{
		$('.add-new-status').slideUp('fast', function ()
		{
			$('div.control-group textarea').val('');
		});
	});
    
    $('.remove-avatar').click(function ()
    {
        //Set avatar null
        $('#profile_form_avatarId').val(0);
        
        //Set image default for view
        $('#resource-selection-callback').children('img').attr('src', $('#default-avatar').val());
    });
    
    $('.publish-status-btn').click(function ()
    {
        var $form = $('#new-status-form');
        var $statusTextarea = $form.find('textarea');
		
        if ($statusTextarea.val().trim() == '') {
            $statusTextarea.focus();
            var $strError = 'Vous devez obligatoirement renseigner ce champ pour publier un nouveau statut !';
            $('div.control-group').addClass('error').find('textarea').attr('placeholder', $strError);
			
            return false;
        }
        
        $form.submit();
    });
	
	$('div.control-group textarea').keypress(function (e)
	{
		var $this = $(e.currentTarget),
			$row = $this.parent();
			
		if ($row.hasClass('error')) {
			$row.removeClass('error');
			$this.removeAttr('placeholder');
		}
	});
    
});