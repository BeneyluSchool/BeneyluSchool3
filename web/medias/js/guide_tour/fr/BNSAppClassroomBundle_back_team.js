$(document).ready(function() {
	guiders.createGuider({
		attachTo: ".btn-new-article",
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Cliquez sur ce bouton pour créer une nouvelle équipe pour votre classe.</p>",
		id: "first",
		next: "second",
		position: 7,
		title: "Créer une équipe",
		xButton: true,
		offset: {
			top: 50,
			left: 0
		}
	}).show();
	
	guiders.createGuider({
		attachTo:".delete-zone",
		buttons: [{name: "Ne plus afficher pour cette page", classString: "btn btn-info btn-small pull-right btn-never-display-guide-tour", onclick: guiders.hideAll }],
		description: "<p>Vous pouvez glisser / déposer les utilisateurs pour les sortir du groupe ou pour les changer de groupe.</p>",
		id: "second",
		next: "finally",
		position: 6,
		autoFocus: true,
		title: "Gestion intuitive des utilisateurs",
		xButton: true,
		offset: {
			top: 40,
			left: 0
		}
	});
});
