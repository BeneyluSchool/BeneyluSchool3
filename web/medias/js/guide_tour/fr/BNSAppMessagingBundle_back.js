$(document).ready(function() {
	guiders.createGuider({
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Vous vous trouvez sur la page d'administration des messages envoyés par vos élèves via le module de <strong>Messagerie</strong>.</p> <p>Si une modération de message est activée, tous les messages envoyés qui doivent être modérés seront en attente de validation sur cette page.</p>",
		id: "first",
		next: "second",
		overlay: true,
		title: "Modération des messages",
		xButton: true
	}).show();
	
	guiders.createGuider({
		attachTo:".moderation-action-button",
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Activez ou désactivez la modération des messages qui sont envoyés par les élèves de votre classe.</p>",
		id: "second",
		next: "third",
		position: 3,
		title: "Activer / désactiver la modération",
		xButton: true,
		offset: {
			top: 43,
			left: -15
		}
	});
	
	var $thirdStepNextStep = "fourth";
	if ($('.messaging-moderation-message').length == 0) {
		$thirdStepNextStep = "finally";
	}
	
	guiders.createGuider({
		attachTo:".messaging-back-sidebar-filter",
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Vous pouvez filtrer les messages selon leurs statuts.</p>",
		id: "third",
		next: $thirdStepNextStep,
		position: 3,
		title: "Filtrer les messages",
		xButton: true,
		offset: {
			top: 80,
			left: -10
		}
	});
	
	guiders.createGuider({
		attachTo:".content-link-moderation",
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Cliquez sur le bouton \"Refuser\" ou \"Valider\" pour modérer ce message.</p>",
		id: "fourth",
		next: "finally",
		position: 5,
		title: "Modérer un message",
		xButton: true,
		offset: {
			top: 50,
			left: 0
		}
	});
	
	guiders.createGuider({
		attachTo: ".btn-validate",
		buttons: [{name: "Ne plus afficher pour cette page", classString: "btn btn-info btn-small pull-right btn-never-display-guide-tour", onclick: guiders.hideAll }],
		description: "<p>En cliquant sur ce bouton, tous les messages en attente de validation passeront au statut de \"Validé\".</p>",
		id: "finally",
		position: 5,
		title: "Tout valider en un seul clic",
		xButton: true,
		offset: {
			top: 40,
			left: 0
		}
	});
});
