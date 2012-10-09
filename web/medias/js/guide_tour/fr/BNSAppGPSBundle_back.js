$(document).ready(function() {
	guiders.createGuider({
		attachTo: ".add-gps-place",
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Cliquez sur ce bouton pour enregistrer un nouveau lieu.</p>",
		id: "first",
		next: "second",
		position: 7,
		title: "Ajouter un lieu",
		xButton: true,
		offset: {
			top: 50,
			left: 0
		}
	}).show();
	
	var $secondStepButton = [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}];
	if ($('.gps-category').length == 0) {
		$secondStepButton = [{name: "Ne plus afficher pour cette page", classString: "btn btn-info btn-small pull-right btn-never-display-guide-tour", onclick: guiders.hideAll }];
	}
	
	guiders.createGuider({
		attachTo:"#gps-add-category-label",
		buttons: $secondStepButton,
		description: "<p>Vous pouvez ajouter une nouvelle catégorie pour classer les lieux enregistrés.</p>",
		id: "second",
		next: "third",
		position: 7,
		title: "Ajouter une catégorie",
		xButton: true,
		offset: {
			top: 40,
			left: 0
		}
	});
	
	var $thirdStepButton = [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}];
	if ($('.gps-place').length == 0) {
		$thirdStepButton = [{name: "Ne plus afficher pour cette page", classString: "btn btn-info btn-small pull-right btn-never-display-guide-tour", onclick: guiders.hideAll }];
	}
	
	guiders.createGuider({
		attachTo:".gps-category",
		buttons: $thirdStepButton,
		description: "<p>Vous pouvez choisir de rendre visible ou non pour vos élèves cette catégorie et ses lieux associés.</p>",
		id: "third",
		next: "fourth",
		position: 7,
		title: "Activer / désactiver une catégorie",
		xButton: true,
		offset: {
			top: 38,
			left: -21
		}
	});
	
	guiders.createGuider({
		attachTo:".gps-place",
		buttons: [{name: "Ne plus afficher pour cette page", classString: "btn btn-info btn-small pull-right btn-never-display-guide-tour", onclick: guiders.hideAll }],
		description: "<p>Cliquez sur ce lieu pour faire apparaître en haut à droite de la page les boutons \"Supprimer un lieu\" et \"Editer un lieu\".</p>",
		id: "fourth",
		next: "finally",
		position: 6,
		title: "Editer ou supprimer un lieu",
		xButton: true,
		offset: {
			top: 0,
			left: 0
		}
	});
});
