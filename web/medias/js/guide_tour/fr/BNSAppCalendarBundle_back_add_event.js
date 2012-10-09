$(document).ready(function() {
	guiders.createGuider({
		attachTo: "#calendar_event_form_isAllDay",
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Un événement qui dure toute la journée n'a pas de d'heure de début et de fin.</p> <p>Note : les événements de ce type sont situés dans la partie supérieure du calendrier.</p>",
		id: "first",
		next: "second",
		autoFocus: true,
		position: 7,
		title: "Evénement qui dure toute la journée",
		xButton: true,
		width: 420,
		offset: {
			top: 48,
			left: -34
		}
	}).show();
	
	guiders.createGuider({
		attachTo:"#calendar_event_form_isRecurring",
		buttons: [{name: "Ne plus afficher pour cette page", classString: "btn btn-info btn-small pull-right btn-never-display-guide-tour", onclick: guiders.hideAll }],
		description: "<p>Cochez cette case pour afficher les options de récurrence.</p>",
		id: "second",
		autoFocus: true,
		position: 11,
		title: 'Créer un événement récurrent',
		xButton: true,
		width: 420,
		offset: {
			top: 50,
			left: -37
		}
	});
});
