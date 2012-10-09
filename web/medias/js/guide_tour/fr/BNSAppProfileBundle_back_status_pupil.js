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
		description: "<p>Clique sur ce bouton pour faire apparaître le formulaire qui te permet d'écrire un nouveau statut.</p>",
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
		description: "<p>Une fois que tu as fini d'écrire ton statut, clique sur ce bouton pour l'afficher sur ton profil.</p>",
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
