$(document).ready(function() 
{
	$('button.btn-accept-invitation, button.btn-decline-invitation, button.btn-never-accept-invitation').click(function()
	{
		var $invitationContainerDiv = $(this).parent().parent(), 
		$route = '',
		$currentButtonClasses = $(this).attr('class');
		if (-1 != $currentButtonClasses.indexOf('btn-accept-invitation')) {
			$route = 'invitation_accept';
		}
		else if (-1 != $currentButtonClasses.indexOf('btn-decline-invitation')) {
			$route = 'invitation_decline';
		}
		else if (-1 != $currentButtonClasses.indexOf('btn-never-accept-invitation')) {
			$route = 'invitation_never_accept';
		}
		$(this).parent().find('button').addClass('disabled');
		
		$.ajax({
			url: Routing.generate($route),
			data: {
				'invitation_id': $invitationContainerDiv.attr('data-invitation-id')
			},
			dataType: 'json',
			success: function(response)
			{
				
				$invitationContainerDiv.fadeOut('slow');
				$invitationContainerDiv.remove();
				if (0 == $('div.invitation-row').length) {
					$('span.skip-button-label').html('J\'ai terminé');
					window.location = $redirectUrl;
				}
			}
		});
	});
	
	$('button.skip-invitation').click(function()
	{
		window.location = $redirectUrl;
	});
});