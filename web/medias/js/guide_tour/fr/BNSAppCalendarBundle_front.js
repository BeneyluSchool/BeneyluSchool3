$(document).ready(function() {
	guiders.createGuider({
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Bienvenue sur le module de calendrier.</p> <p>Ici, vous avez accès à tous vos agendas et les évènements liés.</p>",
		id: "first",
		next: "second",
		title: "Le Calendrier",
		xButton: true
	}).show();
	
	guiders.createGuider({
		attachTo:".btn-calendar-prev",
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Cliquez sur ce bouton pour voir les évènements de la semaine passée ou sur le bouton \"Voir la semaine prochaine\" pour visualiser les évènements de la semaine suivante.</p>",
		id: "second",
		next: "third",
		position: 6,
		title: "Navigation par semaine",
		xButton: true
	});
	
	guiders.createGuider({
		attachTo:"#BBIT_DP_CONTAINER",
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Vous avez également la possibilité de naviguer mois par mois à travers le calendrier.</p>",
		id: "third",
		next: "fourth",
		position: 3,
		title: "Navigation par mois",
		xButton: true
	});
	
	guiders.createGuider({
		attachTo:".agenda-container",
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Masquez ou affichez les évènements de l'agenda en cliquant sur l'un de ces boutons.</p>",
		id: "fourth",
		next: "finally",
		position: 3,
		title: "Filtrer l'affichage des agendas",
		xButton: true
	});
	
	guiders.createGuider({
		buttons: [{name: "Ne plus afficher pour cette page", classString: "btn btn-info btn-small pull-right btn-never-display-guide-tour", onclick: guiders.hideAll }],
		description: "<p>Le clic sur un évènement vous ménera sur une nouvelle page fournissant des informations supplémentaires.</p>",
		id: "finally",
		position: 6,
		title: "Détails d'un évènement",
		xButton: true
	});
});
