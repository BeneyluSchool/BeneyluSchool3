$(document).ready(function() {
	guiders.createGuider({
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Bienvenue sur le module de carnet de liaison.</p> <p>Ce module te permet de lire les messages d'information à l'attention de tes parents.</p>",
		id: "first",
		next: "finally",
		overlay: true,
		title: "Le Carnet de liaison",
		xButton: true
	}).show();
	
	guiders.createGuider({
		attachTo:".content-archive-bookbinding",
		buttons: [{name: "Ne plus afficher pour cette page", classString: "btn btn-info btn-small pull-right btn-never-display-guide-tour", onclick: guiders.hideAll }],
		description: "<p>Tu peux consulter les nouveaux et les anciens messages envoyés par l'enseignant de ta classe grâce à la navigation par mois.</p>",
		id: "finally",
		position: 9,
		title: "Navigation par mois",
		xButton: true,
		offset: {
			top: 0,
			left: 15
		}
	});
});
