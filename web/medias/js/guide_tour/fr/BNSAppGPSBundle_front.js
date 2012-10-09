$(document).ready(function() {
	guiders.createGuider({
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Bienvenue sur le module du GPS.</p> <p>Ce module est un parfait complément pour vos cours d'histoire et de géographie.</p>",
		id: "first",
		next: "second",
		overlay: true,
		title: "Le GPS",
		xButton: true
	}).show();
	
	guiders.createGuider({
		attachTo:"#input-address",
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Vous pouvez connaître la localisation d'un lieu sur la carte en saisissant son adresse partielle ou complète.</p>",
		id: "second",
		next: "third",
		position: 7,
		title: "Barre de recherche",
		xButton: true,
		offset: {
			top: 20,
			left: 0
		}
	});
	
	guiders.createGuider({
		attachTo:".menu-location",
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Vous avez un accès rapide à des lieux enregistrés par l'enseignant de la classe.</p>",
		id: "third",
		next: "fourth",
		position: 3,
		title: "Visualiser des lieux enregistrés",
		xButton: true
	});
	
	guiders.createGuider({
		attachTo:".different-map",
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Vous pouvez basculer d'un type de vue à l'autre en cliquant sur l'une des trois options proposées.</p>",
		id: "fourth",
		next: "finally",
		position: 3,
		title: "Changer le mode d'affichage",
		xButton: true
	});
	
	guiders.createGuider({
		attachTo: ".menu-location",
		buttons: [{name: "Ne plus afficher pour cette page", classString: "btn btn-info btn-small pull-right btn-never-display-guide-tour", onclick: guiders.hideAll }],
		description: "<p>Glissez / déposez le bonhomme là où vous le souhaitez sur la carte pour basculer en vue \"Street View\".</p> <p>Note : pour quitter ce mode, cliquez sur la croix située en haut à droite de la carte.</p>",
		id: "finally",
		position: 3,
		title: "Le \"Street View\"",
		xButton: true,
		offset: {
			top: 30,
			left: 75
		}
	});
});
