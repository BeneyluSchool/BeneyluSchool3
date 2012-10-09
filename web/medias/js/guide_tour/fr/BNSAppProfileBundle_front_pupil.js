$(document).ready(function() {
	guiders.createGuider({
		buttons: [{name: "Ne plus afficher pour cette page", classString: "btn btn-info btn-small pull-right btn-never-display-guide-tour", onclick: guiders.hideAll }],
		description: "<p>Bienvenue sur ton profil ! Celui-ci t'est dédié et il est consultable par tous tes camarades de classe.</p> <p>Tu peux changer les informations affichées sur cette page en te rendant sur la page de gestion de ton profil.</p>",
		id: "first",
		next: "second",
		overlay: true,
		title: "Le profil",
		xButton: true
	}).show();
});
