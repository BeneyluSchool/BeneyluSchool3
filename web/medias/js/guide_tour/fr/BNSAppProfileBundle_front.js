$(document).ready(function() {
	guiders.createGuider({
		buttons: [{name: "Ne plus afficher pour cette page", classString: "btn btn-info btn-small pull-right btn-never-display-guide-tour", onclick: guiders.hideAll }],
		description: "<p>Vous vous trouvez sur la page de votre profil. Cette page est consultable par les autres élèves et enseignants via le module <strong>Annuaire</strong>.</p> <p>N'hésitez pas à aller sur la page d'administration pour éditer votre profil.</p>",
		id: "first",
		next: "second",
		overlay: true,
		title: "Le profil",
		xButton: true
	}).show();
});
