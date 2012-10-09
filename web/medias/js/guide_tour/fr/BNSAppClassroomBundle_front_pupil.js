$(document).ready(function() {
	guiders.createGuider({
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>C'est une des premières fois que tu te connectes sur la Beneylu School, bravo !</p> <p>Tu te trouves actuellement sur la page d'accueil de ta classe.</p>",
		id: "first",
		next: "second",
		title: "Bienvenue sur la Beneylu School",
		xButton: true
	}).show();
	
	guiders.createGuider({
		attachTo:".content-footer",
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Sur toutes les pages de la Beneylu School, tu retrouveras cette barre de navigation qui te permet de changer de module.</p>",
		id: "second",
		next: "finally",
		position: 12,
		title: "Menu de navigation",
		xButton: true,
		offset: {
			top: 10,
			left: 0
		}
	});
	
	guiders.createGuider({
		attachTo:".dropdown-switch-context",
		buttons: [{name: "Ne plus afficher pour cette page", classString: "btn btn-info btn-small pull-right btn-never-display-guide-tour", onclick: guiders.hideAll }],
		description: "<p>Un clic sur ce bouton te donne le choix d'aller voir ta classe, tes équipes ou de te déconnecter.</p>",
		id: "finally",
		position: 1,
		title: "Changer de groupe",
		xButton: true,
		offset: {
			top: 30,
			left: -20
		}
	});
});
