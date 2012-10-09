$(document).ready(function() {
	guiders.createGuider({
		buttons: [{name: "Ne plus afficher pour cette page", classString: "btn btn-info btn-small pull-right btn-never-display-guide-tour", onclick: guiders.hideAll }],
		description: "<p>Bienvenue sur le module Blog.</p> <p>Ce module te permet de consulter tous les articles publiés par tes camarades et l'enseignant de ta classe.</p>",
		id: "first",
		overlay: true,
		title: "Le Blog",
		xButton: true
	}).show();
});
