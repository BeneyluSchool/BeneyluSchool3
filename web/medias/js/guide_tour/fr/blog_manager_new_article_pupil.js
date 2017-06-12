$(document).ready(function() {
	guiders.createGuider({
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Lors de la rédaction d'un article de blog, la fonctionnalité sauvegarde automatique est activée. Elle se déclenche toutes les minutes pour sauvegarder ton article en tant que brouillon.</p>",
		id: "first",
		next: "second",
		overlay: true,
		title: "Sauvegarde automatique",
		xButton: true
	}).show();
	
	guiders.createGuider({
		attachTo:".header-buttons .save",
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Tant que tu n'as pas fini de rédiger ton article, tu peux l'enregistrer manuellement en tant que brouillon pour le récupérer plus tard.</p>",
		id: "second",
		next: "finally",
		position: 5,
		title: "Enregistrer en tant que brouillon",
		xButton: true,
		offset: {
			top: 40,
			left: 0
		}
	});
	
	guiders.createGuider({
		attachTo:".header-buttons .finish",
		buttons: [{name: "Ne plus afficher pour cette page", classString: "btn btn-info btn-small pull-right btn-never-display-guide-tour", onclick: guiders.hideAll }],
		description: "<p>Ce bouton te permet d'enregistrer ton article et de passer son statut en \"Terminé\".</p>",
		id: "finally",
		position: 5,
		title: "Terminer l'article",
		xButton: true,
		offset: {
			top: 40,
			left: 0
		}
	});
});