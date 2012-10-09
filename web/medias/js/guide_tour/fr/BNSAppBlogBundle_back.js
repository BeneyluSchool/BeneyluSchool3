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
		next: "third",
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
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Remplissez ce champ de texte avec le libellé souhaité pour ajouter une nouvelle catégorie. Vous pouvez également associer une icône.</p>",
		id: "third",
		next: "fifth",
		autoFocus: true,
		position: 7,
		title: "Ajouter une catégorie",
		xButton: true,
		offset: {
			top: 40,
			left: -30
		}
	});
	
	$fourthStepButton = [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}];
	if ($('.article').length == 0) {
		$fourthStepButton = [];
	}
	
	guiders.createGuider({
		attachTo:".actions-bar",
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Vous pouvez gérer le statut d'un article.</p>",
		id: "fifth",
		position: 7,
		autoFocus: true,
		title: "Modifier le statut d'un article",
		xButton: true,
		offset: {
			top: 35,
			left: 0
		}
	});
});