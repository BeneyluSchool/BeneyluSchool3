$(document).ready(function() {
	guiders.createGuider({
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>C'est une de vos premières connexions à Beneylu School, bienvenue !</p><p>Vous allez être guidés tout au long de la découverte de cette nouvelle interface : pour continuer cliquez sur suivant.</p>",
		id: "first",
		next: "second",
		title: "Bienvenue sur Beneylu School",
		xButton: true,
		overlay: true
	}).show();
	
	guiders.createGuider({
		attachTo:".content-footer",
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Le <b>menu</b> vous permet de passer d'un module à l'autre. Il est <b>toujours visible</b> et est accessible depuis <b>toutes les pages</b>.</p>",
		id: "second",
		next: "finally",
		position: 12,
		title: "Menu de navigation",
		xButton: true,
		overlay: true,
		offset: {
			top: 10,
			left: 0
		}
	});
	
	guiders.createGuider({
		attachTo:".dropdown-switch-context",
		buttons: [{name: "Suivant", classString: "btn btn-info btn-small pull-right", onclick: guiders.next}],
		description: "<p>Ce bouton vous permet de changer de contexte, par exemple passer de la classe à l'école. Vous avez également la possibilité de vous déconnecter.</p>",
		id: "third",
		next: "finally",
		position: 1,
		title: "Changer de groupe",
		xButton: true,
		overlay: true,
		offset: {
			top: 30,
			left: -20
		}
	});
	
	guiders.createGuider({
		attachTo:".switch-button",
		buttons: [
			{name: "Ne plus me proposer l'aide pour cette page", classString: "btn btn-info btn-small pull-left btn-never-display-guide-tour", onclick: guiders.hideAll },
		],
		description: "<p>Ce bouton fait office d'interrupteur, il active le <b>mode d'administration</b>. En mode administration, vous <b>maniez</b> le contenu de l'ENT, en mode consultation vous le <b>lisez</b>.<p>",
		id: "finally",
		position: 12,
		title: "Changer de mode",
		xButton: true,
		overlay: true,
		offset: {
			top: 10,
			left: 0
		}
	});
});
