$(document).ready(function() {
	var $leftOffset = $topOffset = 0;
	if ($(document).width() > 1024) {
		$leftOffset = -225;
		$topOffset = 50;
	}
	else {
		$leftOffset = 0;
		$topOffset = 22;
	}
	
	guiders.createGuider({
		attachTo: ".add-preference-form .btn-info",
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Pour ajouter un nouveau \"J'aime\", remplissez ce champ texte.</p>",
		id: "first",
		next: "finally",
		position: 9,
		title: "Ajouter un j'aime",
		xButton: true,
		offset: {
			top: $topOffset,
			left: $leftOffset
		}
	}).show();
	
	guiders.createGuider({
		attachTo:".header-buttons .submit-profile",
		buttons: [{name: "Ne plus afficher pour cette page", classString: "btn btn-info btn-small pull-right btn-never-display-guide-tour", onclick: guiders.hideAll }],
		description: "<p>Cliquez sur ce bouton pour sauvegarder vos modifications.</p> <p>Note : vous n'avez pas besoin de cliquer sur ce bouton pour enregistrer l'ajout ou la suppression d'un nouveau \"J'aime\" ou \"Je n'aime pas\".</p>",
		id: "finally",
		position: 5,
		title: "Enregistrer les modifications",
		xButton: true,
		offset: {
			top: 50,
			left: 0
		}
	});
});
