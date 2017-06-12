$(document).ready(function() 
{
	$('button.btn-accept-invitation, button.btn-decline-invitation, button.btn-never-accept-invitation').click(function()
	{
		var $invitationContainerDiv = $(this).closest('.invitation-row'),
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
		$invitationContainerDiv.find('button').attr('disabled', 'disabled');

		var embedded = $invitationContainerDiv.find('[data-embedded]').attr('data-embedded'),
			embeddedIds = [];
		if (embedded) {
			try {
				embedded = JSON.parse(embedded);
			} catch (e) {
				embedded = {};
			}
			for (var id in embedded) {
				if (embedded.hasOwnProperty(id) && embedded[id]) {
					embeddedIds.push(id);
				}
			}
		}

		$.ajax({
			url: Routing.generate($route),
			data: {
				'invitation_id': $invitationContainerDiv.attr('data-invitation-id'),
				'groups_embedded': embeddedIds,
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
			},
			complete: function () {
				$invitationContainerDiv.find('button').removeAttr('disabled');
			}
		});
	});
	
	$('button.skip-invitation').click(function()
	{
		window.location = $redirectUrl;
	});
});
