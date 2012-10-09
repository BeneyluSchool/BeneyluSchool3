$(document).ready(function() {
	guiders.createGuider({
		attachTo: ".btn-new-article",
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Cliquez sur ce bouton pour créer un nouvel événement.</p>",
		id: "first",
		next: "second",
		position: 7,
		title: "Créer un événement",
		xButton: true
	}).show();
	
	guiders.createGuider({
		attachTo:".btn-calendar-prev",
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Les boutons \"Voir la semaine dernière\" et \"Voir la semaine prochaine\" vous permettent de naviguer de semaine en semaine.</p>",
		id: "second",
		next: "third",
		position: 6,
		title: 'Navigation par semaine',
		xButton: true,
		offset: {
			top: -20,
			left: 0
		}
	});
	
	guiders.createGuider({
		attachTo:".agenda-filter-container",
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Changez la couleur associée à l'agenda en cliquant sur le carreau de couleur.</p>",
		id: "third",
		next: "fourth",
		position: 11,
		title: 'Changer de couleur',
		xButton: true,
		offset: {
			top: 0,
			left: -17
		}
	});
	
	guiders.createGuider({
		buttons: [{name: "Ne plus afficher pour cette page", classString: "btn btn-info btn-small pull-right btn-never-display-guide-tour", onclick: guiders.hideAll }],
		description: 
			"<p>Glissez / déposez les événements pour éditer intuitivement la date de début et de fin.</p> <p>Note : les événements récurrents ne peuvent être édités au moyen d'un glisser / déposer.</p>",
		id: "fourth",
		title: "Edition d'événement intuitive",
		xButton: true
	});
});
