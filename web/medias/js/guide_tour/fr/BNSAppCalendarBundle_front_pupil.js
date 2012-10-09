$(document).ready(function() {
	guiders.createGuider({
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Bienvenue sur le module de calendrier !</p> <p>Tu as accès à tous tes agendas et ses événements liés. Par exemple, tu peux consulter tous les événements planifiés par ton professeur.</p>",
		id: "first",
		next: "second",
		title: "Le Calendrier",
		xButton: true
	}).show();
	
	guiders.createGuider({
		attachTo:".btn-calendar-prev",
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Utilise les boutons \"Voir la semaine précédente\" et \"Voir la semaine prochaine\" pour passer d'une semaine à l'autre !</p>",
		id: "second",
		next: "third",
		position: 6,
		title: "Navigation par semaine",
		xButton: true
	});
	
	guiders.createGuider({
		attachTo:"#BBIT_DP_CONTAINER",
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Tu peux aussi naviguer mois par mois.</p>",
		id: "third",
		next: "fourth",
		position: 3,
		title: "Navigation par mois",
		xButton: true
	});
	
	guiders.createGuider({
		attachTo:".agenda-container",
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Un clic sur ce bouton permet d'afficher ou de masquer les événements liés à cet agenda.</p>",
		id: "fourth",
		next: "finally",
		position: 3,
		title: "Filtrer l'affichage des agendas",
		xButton: true
	});
	
	guiders.createGuider({
		buttons: [{name: "Ne plus afficher pour cette page", classString: "btn btn-info btn-small pull-right btn-never-display-guide-tour", onclick: guiders.hideAll }],
		description: "<p>Le clic sur un événement te ménera sur une nouvelle page avec des informations supplémentaires.</p>",
		id: "finally",
		position: 6,
		title: "Détails d'un événement",
		xButton: true
	});
});
