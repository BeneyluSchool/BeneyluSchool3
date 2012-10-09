$(document).ready(function() {
	guiders.createGuider({
		attachTo: ".add-preference-form .btn-info",
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Tu peux ajouter un nouveau \"J'aime\" en remplissant ce champ.</p>",
		id: "first",
		next: "finally",
		position: 9,
		title: "Ajouter un j'aime",
		xButton: true,
		offset: {
			top: 50,
			left: -225
		}
	}).show();
	
	guiders.createGuider({
		attachTo:".header-buttons .submit-profile",
		buttons: [{name: "Ne plus afficher pour cette page", classString: "btn btn-info btn-small pull-right btn-never-display-guide-tour", onclick: guiders.hideAll }],
		description: "<p>Clique sur ce bouton pour sauvegarder les modifications que tu as apportées à ton profil.</p>",
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
