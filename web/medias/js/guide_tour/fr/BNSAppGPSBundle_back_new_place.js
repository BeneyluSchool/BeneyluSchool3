$(document).ready(function() {
	guiders.createGuider({
		attachTo: "#gps_place_form_label",
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Nommer le lieu que vous souhaitez ajouter. Ce nom sera celui qui s'affichera à l'écran pour vos élèves.</p>",
		id: "first",
		next: "second",
		position: 7,
		title: "Nom du lieu",
		xButton: true,
		offset: {
			top: 40,
			left: 0
		}
	}).show();
	
	guiders.createGuider({
		attachTo:"#gps_place_form_address",
		buttons: [{name: "Ne plus afficher pour cette page", classString: "btn btn-info btn-small pull-right btn-never-display-guide-tour", onclick: guiders.hideAll }],
		description: "<p>Renseignez ce champ avec l'adresse complète du lieu recherché.</p> <p>Note : une fois que vous avez rempli le champ, cliquez sur le bouton \"Voir la carte\" pour vérifier votre saisie.</p>",
		id: "second",
		position: 7,
		title: "Adresse du lieu",
		xButton: true,
		offset: {
			top: 40,
			left: 0
		}
	});
});
