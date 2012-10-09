$(document).ready(function() {
	guiders.createGuider({
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Bienvenue sur le module Médiathèque.</p> <p>Ce module regroupe toutes tes documents numériques tels que les images, les fichiers audios, vidéos, textes ou encore des liens de sites Internet.</p>",
		id: "first",
		next: "second",
		overlay: true,
		title: "Les Ressources",
		xButton: true
	}).show();
	
	guiders.createGuider({
		attachTo: ".add-resource-btn",
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Clique sur ce bouton pour ajouter une nouveau document.</p>",
		id: "second",
		next: "third",
		position: 7,
		title: "Ajouter un document",
		xButton: true,
		offset: {
			top: 50,
			left: 0
		}
	});
	
	guiders.createGuider({
		attachTo: ".resource-sidebar",
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Utilise ce menu pour parcourir les dossiers dans lesquels sont rangés tes documents.</p>",
		id: "third",
		next: "finally",
		position: 2,
		title: "Menu de navigation",
		xButton: true,
		offset: {
			top: 100,
			left: 0
		}
	});
	
	guiders.createGuider({
		attachTo: "#search-input",
		buttons: [{name: "Ne plus afficher pour cette page", classString: "btn btn-info btn-small pull-right btn-never-display-guide-tour", onclick: guiders.hideAll }],
		description: "<p>Tu peux également effectuer des recherches sur Internet en remplissant ce champ de texte.</p>",
		id: "finally",
		position: 11,
		title: "Recherche sur Internet",
		xButton: true,
		offset: {
			top: 60,
			left: 0
		}
	});
});
