$(function () {
	guiders.createGuider({
		attachTo: ".header-buttons .create-article",
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Clique sur ce bouton pour écrire un nouvel article.</p>",
		id: "first",
		next: "finally",
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
		buttons: [{name: "Ne plus afficher pour cette page", classString: "btn btn-info btn-small pull-right btn-never-display-guide-tour", onclick: guiders.hideAll }],
		description: "<p>Tu peux filtrer les articles selon leur statut et/ou leurs catégories en cochant la case souhaitée.</p>",
		id: "finally",
		position: 3,
		autoFocus: true,
		title: "Filtrer les articles",
		xButton: true,
		offset: {
			top: 60,
			left: -10
		}
	});
});