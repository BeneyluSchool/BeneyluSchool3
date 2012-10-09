$(document).ready(function ()
{	
	// Listener sur le click des lignes teacher pour afficher ou non le bouton quitter la classe
	$('div.teacher-row').live('click', function () {
		$('div.pupil-actions-buttons').fadeOut('fast');
		
		if (!$(this).hasClass('active')) {
			$('div.teacher-row').removeClass('active');
			$(this).addClass('active');
			if (!$(this).hasClass('allow-leave-classroom')) {
				$('div.teacher-actions-buttons').fadeOut('fast');
				return false;
			}
			
			var $delayMS = 0;
			if ($('div.pupil-actions-buttons').css('display') == 'block') {
				$delayMS = 300;
			}
			
			$('div.pupil-actions-buttons').fadeOut('fast');
			$('div.teacher-actions-buttons').delay($delayMS).fadeIn('fast');
		}
	});
	
	$('a.btn-delete-pupil').click(function(event) {
		event.preventDefault();
	});
	
	// Listener sur le clic des liens "Ajouter un enseignant"/"Ajouter un élève"
	$('a.add-user-button').click(function (event)
	{
		$('span.user-role-label').html($(this).attr('data-role-label'));
		$('div.add-user-modal-content').html('');
		$('div.create-buttons-container').hide();
		$('div.create-user-success').hide();
		$('div.invite-user-success').hide();
		
		event.preventDefault();
		$.ajax({
			url: Routing.generate('classroom_users_render_add_user_modal_body'),
			type: 'POST',
			data: {
				user_role_requested: $(this).attr('data-role')
			},
			dataType: 'html',
			success: function (data)
			{
				$('div.add-user-modal-content').html(data);
			}
		});
	});
	
	// Listener sur le click des boutons "Créer l'enseignant/élève" et "Créer et continuer"
	$('button.btn-create-user, button.btn-create-user-more').live('click', function(event)
	{
		event.preventDefault();
		var $canSubmit = true, $errorString = 'Ce champ doit être renseigné', $firstEmptyField = null;
		$('form#add-user-classroom-form').find('input[type=text], input[type=email]').each(function() {
			if ($.trim($(this).val()) == '') {
				if ($firstEmptyField == null) {
					$firstEmptyField = $(this);
				}
				$canSubmit = false;
				$(this).attr('placeholder', $errorString);
			}
			else if ($(this).attr('type') == 'email') {
				var pattern = new RegExp('^[a-z0-9]+([_|\.|-]{1}[a-z0-9]+)*@[a-z0-9]+([_|\.|-]{1}[a-z0-9]+)*[\.]{1}[a-z]{2,6}$', 'i');
				$canSubmit = pattern.test($(this).val());
				if (!$canSubmit) {
					$(this).val('');
					$(this).attr('placeholder', 'E-mail saisi invalide');
				}
			}
		});
		
		if (!$canSubmit) {
			$firstEmptyField.focus();
			
			return false;
		}
		
		$('div.create-user-success').hide();
		
		$('form#add-user-classroom-form').submit();
		// On vérifie si le click s'est opéré sur le bouton "créer" ou "créer et continuer"
		if ($(this).hasClass('btn-create-user-more')) {
			$createMore = true;
		}
		
		// On cache les boutons et le formulaire
		$('div.create-buttons-container').hide();
		$('div.create-user-form-container').hide();
		// On affiche la div avec le loader
		$('div.create-user-loader').show();
	});
	
	// Listener sur le bouton Vérifier
	$('button.btn-check-user').live('click', function(event) {
		event.preventDefault();
		var $usernameToCheckInput = $('#username-to-check');
		var $usernameToCheck = $.trim($usernameToCheckInput.val());
		if ($usernameToCheck == '')
		{
			$usernameToCheckInput.focus();
			$usernameToCheckInput.attr('placeholder', 'Ce champ doit être renseigné');
			
			return false;
		}
		var $verifButton = $(this);
		$usernameToCheckInput.attr('disabled', 'disabled');
		$verifButton.addClass('disabled');
		$.ajax({
			url: Routing.generate('back_classrooms_users_check_username'),
			type: 'POST',
			data: {
				username_to_check: $usernameToCheck
			},
			dataType: 'html',
			success: function(response)
			{
				$('div.check-user-container').html(response).show();
				$verifButton.removeClass('disabled');
				$usernameToCheckInput.removeAttr('disabled');
			}
		});
	});
	
	// Listener sur le bouton inviter un enseignant dans sa classe...
	$('button.btn-invite-teacher').live('click', function(event) {
		event.preventDefault();
		$usernameToInvite = $(this).attr('data-username');
		console.log($usernameToInvite);
		
		$('div.create-buttons-container').hide();
		$('div.create-user-form-container').hide();
		// On affiche la div avec le loader
		$('div.invite-user-loader').show();
		
		$.ajax({
			url: Routing.generate('back_classrooms_users_invite_teacher'),
			type: 'POST',
			data: {
				username_to_invite: $usernameToInvite
			},
			dataType: 'json',
			success: function(response)
			{
				$('div.invite-user-loader').hide();
				$('div.invite-user-success').show();
			}
		});
	})
});

function addUserLastProcess($role)
{
	$('div.create-user-loader').hide();
	$('div.create-user-success').show();
	// L'utilisateur souhaite continuer la création de d'autres utilisateurs
	if ($createMore) {
		$.ajax({
			url: Routing.generate('classroom_users_render_add_user_modal_body'),
			type: 'POST',
			data: {
				user_role_requested: $role
			},
			dataType: 'html',
			success: function (data)
			{
//				$('div.create-user-success').hide();
				$('div.add-user-modal-content').html(data);
				$createMore = false;
			}
		});
	}
}