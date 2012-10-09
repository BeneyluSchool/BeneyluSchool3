$(document).ready(function() {
	guiders.createGuider({
		attachTo: ".btn-new-article",
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Cliquez sur ce bouton pour changer le message d'accueil (sur le tableau) de votre classe.</p>",
		id: "first",
		next: "finally",
		position: 7,
		title: "Changer le message d'accueil",
		xButton: true,
		offset: {
			top: 50,
			left: 0
		}
	}).show();
	
	guiders.createGuider({
		attachTo:".btn-change-module-state",
		buttons: [{name: "Ne plus afficher pour cette page", classString: "btn btn-info btn-small pull-right btn-never-display-guide-tour", onclick: guiders.hideAll }],
		description: "<p>Ce bouton vous permet d'activer ou non un module pour les élèves de la classe.</p>",
		id: "finally",
		position: 9,
		autoFocus: true,
		title: "Activer / désactiver un module",
		xButton: true,
		offset: {
			top: 217,
			left: 0
		}
	});
});
