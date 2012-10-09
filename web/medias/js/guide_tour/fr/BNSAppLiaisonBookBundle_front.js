$(document).ready(function() {
	guiders.createGuider({
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Bienvenue sur le module de carnet de liaison.</p> <p>Ce module permet aux enseignants de s'adresser à l'ensemble des parents d'élèves de la classe.</p>",
		id: "first",
		next: "second",
		overlay: true,
		title: "Le Carnet de liaison",
		xButton: true
	}).show();
	
	var $secondStepButton = [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}];
	if ($('.btn-sign').length == 0) {
		$secondStepButton = [{name: "Ne plus afficher pour cette page", classString: "btn btn-info btn-small pull-right btn-never-display-guide-tour", onclick: guiders.hideAll }];
	}
	
	guiders.createGuider({
		attachTo:".content-archive-bookbinding",
		buttons: $secondStepButton,
		description: "<p>Accèdez aux nouveaux ou aux anciens messages postés par l'enseignant grâce à la navigation par mois.</p>",
		id: "second",
		next: "finally",
		position: 9,
		title: "Navigation par mois",
		xButton: true,
		offset: {
			top: 0,
			left: 15
		}
	});
	
	guiders.createGuider({
		attachTo:".btn-sign",
		buttons: [{name: "Ne plus afficher pour cette page", classString: "btn btn-info btn-small pull-right btn-never-display-guide-tour", onclick: guiders.hideAll }],
		description: "<p>Cliquez sur ce bouton pour signer le message et signaler à l'enseignant que vous avez bien pris connaissance du message.</p>",
		id: "finally",
		position: 6,
		title: "Signer un message",
		xButton: true,
		offset: {
			top: 40,
			left: 0
		}
	});
});
