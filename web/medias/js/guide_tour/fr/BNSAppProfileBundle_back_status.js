$(document).ready(function() {
	$('.simulate-click').live('click', function()
	{
		$('.header-buttons .write-new-status-btn').trigger('click');
		setTimeout(function ()
		{
			guiders.show('finally');
		}, 1000);
	});
	
	guiders.createGuider({
		attachTo: ".header-buttons .write-new-status-btn",
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right simulate-click", onclick: guiders.hideAll}],
		description: "<p>Cliquez sur ce bouton pour faire apparaître le formulaire qui vous permet de rédiger un nouveau statut.</p>",
		id: "first",
		position: 7,
		title: "Ecrire un nouveau statut",
		xButton: true,
		offset: {
			top: 50,
			left: 0
		}
	}).show();
	
	guiders.createGuider({
		attachTo:".header-buttons .publish-status-btn",
		buttons: [{name: "Ne plus afficher pour cette page", classString: "btn btn-info btn-small pull-right btn-never-display-guide-tour", onclick: guiders.hideAll }],
		description: "<p>Une fois que vous avez renseigné votre nouveau statut, cliquez sur ce bouton pour l'afficher sur votre page de profil.</p>",
		id: "finally",
		position: 5,
		title: "Enregistrer le nouveau statut",
		xButton: true,
		offset: {
			top: 50,
			left: 0
		}
	});
});
