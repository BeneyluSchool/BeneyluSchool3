$(document).ready(function() {
	guiders.createGuider({
		attachTo: ".header-buttons .create-article",
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Cliquez sur ce bouton pour écrire un nouvel article.</p>",
		id: "first",
		next: "second",
		position: 7,
		title: "Créer un article",
		xButton: true,
		offset: {
			top: 40,
			left: 0
		}
	}).show();
	
	guiders.createGuider({
		attachTo:".article-statuses-filter",
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Vous pouvez filtrer les articles selon leur statut et/ou leurs catégories en cochant la configuration souhaitée.</p>",
		id: "second",
		next: "finally",
		position: 3,
		autoFocus: true,
		title: "Filtrer les articles",
		xButton: true,
		offset: {
			top: 60,
			left: -10
		}
	});
	
	guiders.createGuider({
		attachTo:".add-category",
		buttons: [{name: "Ne plus afficher pour cette page", classString: "btn btn-info btn-small pull-right btn-never-display-guide-tour", onclick: guiders.hideAll }],
		description: "<p>Remplissez ce champ de texte avec le libellé souhaité pour ajouter une nouvelle catégorie. Vous pouvez également associer une icône.</p>",
		id: "finally",
		autoFocus: true,
		position: 7,
		title: "Ajouter une catégorie",
		xButton: true,
		offset: {
			top: 40,
			left: -30
		}
	});
});