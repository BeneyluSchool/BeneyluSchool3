$(document).ready(function() {
	var $firstStepButton = [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}];
	if ($('.item-list-container .item').length == 0) {
		$firstStepButton = [{name: "Ne plus afficher pour cette page", classString: "btn btn-info btn-small pull-right btn-never-display-guide-tour", onclick: guiders.hideAll }];
	}
	
	guiders.createGuider({
		attachTo: ".btn-new-article",
		buttons: $firstStepButton,
		description: "<p>Cliquez sur ce bouton pour ajouter un nouvel élève ou un nouvel enseignant à votre classe.</p>",
		id: "first",
		next: "finally",
		position: 7,
		title: "Ajouter un nouvel utilisateur",
		xButton: true,
		offset: {
			top: 50,
			left: 0
		}
	}).show();
	
	guiders.createGuider({
		attachTo:".item-list-container .item",
		buttons: [{name: "Ne plus afficher pour cette page", classString: "btn btn-info btn-small pull-right btn-never-display-guide-tour", onclick: guiders.hideAll }],
		description: "<p>Cliquez sur cette ligne pour vous rendre sur la fiche de cet élève.</p>",
		id: "finally",
		position: 7,
		autoFocus: true,
		title: "Fiche élève",
		xButton: true,
		offset: {
			top: 35,
			left: 50
		}
	});
});
