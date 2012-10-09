$(document).ready(function() {
	var $firstStepButton = [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}];
	if ($('.content-news').length == 0) {
		$firstStepButton = [];
	}
	
	guiders.createGuider({
		attachTo: ".btn-new-article",
		buttons: $firstStepButton,
		description: "<p>Cliquez sur ce bouton pour écrire un nouveau message qui apparaîtra sur le carnet de liaison.</p>",
		id: "first",
		next: "second",
		position: 7,
		title: "Ecrire un message",
		xButton: true,
		offset: {
			top: 50,
			left: 0
		}
	}).show();
	
	guiders.createGuider({
		attachTo:".title",
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Suivez le nombre de parents d'élèves qui ont déjà pris connaissance de votre message.</p>",
		id: "second",
		next: "finally",
		position: 3,
		title: "Nombre de signature",
		xButton: true,
		offset: {
			top: 50,
			left: 0
		}
	});
	
	guiders.createGuider({
		attachTo:".news",
		buttons: [{name: "Ne plus afficher pour cette page", classString: "btn btn-info btn-small pull-right btn-never-display-guide-tour", onclick: guiders.hideAll }],
		description: "<p>Cliquez sur ce message pour faire apparaître les boutons \"Editer le message\" et \"Supprimer le message\" en haut à droite de l'écran.</p>",
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
