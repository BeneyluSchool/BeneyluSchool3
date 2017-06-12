$(document).ready(function() {
	if ($('.generate-details').length > 0) {
		guiders.createGuider({
			attachTo: ".generate-details",
			buttons: [{name: "Ne plus afficher pour cette page", classString: "btn btn-info btn-small pull-right btn-never-display-guide-tour", onclick: guiders.hideAll }],
			description: "<p>Générez une fiche ou un nouveau mot de passe pour l'élève concerné(e) ou ses parents.</p>",
			id: "first",
			position: 5,
			width: 420,
			title: "Générer une fiche ou un mot de passe",
			xButton: true,
			offset: {
				top: 50,
				left: 0
			}
		}).show();
	}
});