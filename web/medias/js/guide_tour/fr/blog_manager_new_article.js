$(document).ready(function() {
	guiders.createGuider({
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Lors de la rédaction d'un article de blog, la fonctionnalité sauvegarde automatique est activée. Elle se déclenche toutes les minutes pour sauvegarder votre article en tant que brouillon.</p>",
		id: "first",
		next: "second",
		overlay: true,
		title: "Sauvegarde automatique",
		xButton: true
	}).show();
	
	guiders.createGuider({
		attachTo:".header-buttons .save",
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Tant que vous n'avez pas fini de rédiger votre article, vous pouvez le sauvegarder manuellement en tant que brouillon.</p>",
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
		description: "<p>Une fois votre article terminé, vous pouvez le publier sur votre blog grâce à ce bouton.</p>",
		id: "finally",
		position: 5,
		title: "Publier l'article",
		xButton: true,
		offset: {
			top: 40,
			left: 0
		}
	});
});