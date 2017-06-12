$(document).ready(function()
{	
	// Listener sur la pression sur la touche entrée dans la barre de recherche de filtre d'utilisateur
	$('input[type=text].search-input').keypress(function(event) 
	{
		if (event.keyCode == 13) {
			event.preventDefault();
			filterUser($(this));
		}
	});
	
	// Listener sur le click sur la bouton "Rechercher" pour filtrer les utilisateurs
	$(' .filter-user').click(function()
	{
		filterUser($('input[type=text].search-input'));
	});
	
	//Changement de contexte
	$('.directory-sidebar .group-directory:not(.active)').live('click',function(e)
	{
		$('.directory-sidebar .group-directory').removeClass('active');
		$('.directory-sidebar .group-directory[data-group-id|="'+ $(this).attr('data-group-id') + '"]').addClass('active');
		$('.directory-main-content .group-directory').hide();
		$('.directory-main-content .group-directory[data-group-id|="'+ $(this).attr('data-group-id') + '"]').show();
	});
	
	// Listener sur le click d'un groupe qui a des groupes fils (Ecole, Mairie, etc.)
	$('.tabbable ul.nav-tabs li').click(function(e)
	{
		$('span.reset-filter').show();
		if ($(this).attr('data-switch-context') !== undefined) {
			var $contextToSwitchTo = $(this).attr('data-switch-context');
			showHideLiNavDependsOnContext($contextToSwitchTo);
			$('.context-label-container').find('span.label').each(function()
			{
				if ($(this).attr('data-label-for-context') == $contextToSwitchTo) {
					$(this).show();
				}
			});
		}
	});
	
	// Listener sur le click sur le lien li du groupe référence, qui reset tous les filtres
	$('div.tabbable ul.nav-tabs li.default-group').click(function()
	{
		$('span.label').hide();
		showHideLiNavDependsOnContext($(this).attr('data-context'))
	});
	
	// Listener sur le reset des filtres
	$('span.reset-filter').click(function()
	{
		$('span.label').hide();
		$('.tab-pane').removeClass('active');
		$('.tab-pane:first').addClass('active');
		
		$('.tabbable .nav-tabs li').removeClass('active');
		$('.tabbable .nav-tabs li').hide();
		$('.tabbable .nav-tabs li').each(function()
		{
			if ($(this).attr('data-context') == 'default') {
				$(this).show();
			}
		});
		$('li.default-group').addClass('active');
	});

    //Suppression de la liste de sélection
	$('.selected-user-container > .user-block.cancel').live('click', function(e)
	{
		e.preventDefault();
		var $currentSelectedUserId = $(this).attr('data-user-id');
		$(this).remove();
		$('.user-block[data-user-id="' + $currentSelectedUserId + '"]').addClass('selectable checkbox').removeClass('selected');
		if($('.selected-user-container > .user-block.cancel').length == 0){
			$('.no-selection').show();
		}
	});

    //Utilisé dans le userPicker avec utilisateur déjà sélectionné
	$('.selected-user-container:first .user-block').each(function()
	{
		var $currentSelectedUserId = $(this).attr('data-user-id');
		$('div.tab-content .user-block').each(function()
		{
			if ($(this).parent().hasClass('selected-user-container')) {
				return;
			}
			
			if ($(this).attr('data-user-id')  == $currentSelectedUserId) {
				$(this).addClass('is-selected');
			}
		});
	});

    //Action de sélectionner l'intégralité d'un sous groupe
    $('.checkbox-select-group').live('click', function(e)
    {
        var cb = $(this);
        e.preventDefault();
        $(this).parents(".user-group-selectable").find('.user-block.selectable').each(function()
        {
            $(this).click();
        });
    });

    //Les profils non visualisables ne sont pas des liens , mais quand même des <a>
    $('.not-link').click(function(e){
        e.preventDefault();
    });
});

function showHideLiNavDependsOnContext($context) 
{
	$('div.tabbable ul.nav-tabs li').each(function()
	{
		if ($(this).hasClass('default-group')) {
			return;
		}

		if ($(this).attr('data-context') == $context) {
			$(this).show();
		}
		else {
			$(this).hide();
		}
	});
}

function filterUser($inputText)
{
	var $str = $.trim($inputText.val()).toLowerCase();
	$('.directory-main-content .user-block').each(function() 
	{
		if ($(this).parent().hasClass('selected-user-container')) {
			return;
		}
		
		if ($(this).attr('data-user-full-name').toLowerCase().indexOf($str) == -1) {
			$(this).hide();
		}
		else {
			$(this).show();
		}
	});
}
