$(document).ready(function() {
	guiders.createGuider({
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>C'est une de vos premières connexions à Beneylu School !</p> <p>Vous vous trouvez actuellement sur la page d'accueil de votre classe.</p>",
		id: "first",
		next: "second",
		title: "Bienvenue sur Beneylu School",
		xButton: true
	}).show();
	
	guiders.createGuider({
		attachTo:".content-footer",
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Vous retrouverez sur toutes les pages de la Beneylu School cette barre de navigation qui vous permettera de passer d'un module à l'autre.</p>",
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
		description: "<p>Ce bouton vous permet de changer de contexte, par exemple passer de la classe à l'école. Vous avez également la possibilité de vous déconnecter.</p>",
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
