$(document).ready(function() {
	
	guiders.createGuider({
		attachTo: ".signatures",
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Suivez le nombre de parents d'élèves qui ont déjà pris connaissance de votre message.</p>",
		id: "first",
		next: "finally",
		position: 7,
		title: "Voir les signatures",
		xButton: true,
		offset: {
			top: 50,
			left: 0
		}
	}).show();
	
	guiders.createGuider({
		attachTo:".bns-info.btn-24.medium-return.button-return",
		buttons: [{name: "Ne plus afficher pour cette page", classString: "btn btn-info btn-small pull-right btn-never-display-guide-tour", onclick: guiders.hideAll }],
		description: "<p>Vous pouvez agir en cliquant sur les boutons \"Editer le message\" et \"Supprimer le message\" en haut à gauche de l'écran.</p>",
		id: "finally",
		position: 6,
		title: "Plus d'actions sur le message",
		xButton: true,
		offset: {
			top: 40,
			left: 0
		}
	});
});
