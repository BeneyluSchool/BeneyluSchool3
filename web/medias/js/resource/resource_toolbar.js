/**
 * Classe de gestion de la toolbar
 */
var Toolbar = function() {
	this.toolBarSelector	= '#resource-toolbar';

	// Here public attributes
	this.canAddResource		= true;
	this.hasHistory			= false;
	this.canManage			= false;

	// File configurations
	this.isFavoriteFile		= false;
	this.isDeletedFile		= false;
	this.isEditing			= false;
	this.resource			= {};
	this.label				= {};

	/**
	 * @var string GALLERY, FILE, GARBAGE
	 */
	this.navigationContext	= 'GALLERY';

	/**
	 * @var string NULL, INSERT, JOIN, SELECT
	 */
	this.objectiveContext	= null;

	/**
	 * Type de ressource autorisé, utilisé lors de la sélection
	 *
	 * @type {string}
	 */
	this.allowedType = '';
};

Toolbar.prototype = {
	update: function () {
		// Add resource button
		if (this.canAddResource) {
			this.showAddResourceButton();
		}
		else {
			this.hideAddResourceButton();
		}

		// Back button
		this.processBackButton();

		// Navigation context
		if (this.navigationContext == 'GALLERY') {
			this.hideSelectionFileContainer();
			this.hideGarbageContainer();
            this.hideProviderResourceContainer();

			// Selection container
			if (this.hasSelection()) {
				// Objective context
				if (this.objectiveContext == 'INSERT') {
					this.hideSelectionObjectiveButtons();
					this.showSelectionInsertButton();
				}
				else if (this.objectiveContext == 'JOIN') {
					this.hideSelectionObjectiveButtons();
					this.showSelectionJoinButton();
				}
				else {
					this.hideSelectionObjectiveButtons();
				}

				this.showSelectionContainer(this.canManage);
			}
			else {
				this.hideSelectionContainer();
				this.hideSelectionObjectiveButtons();
			}
		}
		else if (this.navigationContext == 'FILE') {
			this.hideSelectionContainer();
			this.hideGarbageContainer();
            this.hideProviderResourceContainer();

			if (this.isFavoriteFile) {
				this.markAsFavorite();
			}
			else {
				this.unMarkAsFavorite();
			}

			// Objective context
			this.hideObjectiveFileContextContainer();
			if (this.objectiveContext != null) {
				this.showObjectiveFileContextContainer();
			}

			if (this.canManage) {
				if (this.isEditing) {
					this.hideEditFileButton();
					this.hideDeleteFileButton();
				}
				else {
					this.showEditFileButton();
					
					if (!this.isDeletedFile) {
						this.showDeleteFileButton();
						this.hideRestoreFileButton();
					}
					else {
						this.hideDeleteFileButton();
						this.showRestoreFileButton();
					}
				}
			}
			else {
				this.hideEditFileButton();
				this.hideDeleteFileButton();
			}

			this.showSelectionFileContainer();
		}
		else if (this.navigationContext == 'GARBAGE') {
			this.hideSelectionContainer();
			this.hideSelectionFileContainer();
			this.hideSelectionObjectiveButtons();
            this.hideProviderResourceContainer();
			this.showGarbageContainer();
			
			if (this.hasSelection()) {
				this.showSelectionGarbageContainer();
			}
			else {
				this.hideSelectionGarbageContainer();
			}
		}
        else if (this.navigationContext == 'FAVORITES') {
            this.hideSelectionObjectiveButtons();
            this.hideSelectionContainer();
            this.hideSelectionFileContainer();
            this.hideProviderResourceContainer();
        }
        else if (this.navigationContext == 'PROVIDER_RESOURCE') {
            this.hideSelectionObjectiveButtons();
            this.hideSelectionContainer();
            this.hideSelectionFileContainer();
            this.showProviderResourceContainer();
        }
	},

	hasSelection: function () {
		return $('#resource-selection .area .box').length > 0;
	},

	showAddResourceButton: function () {
		$(this.toolBarSelector + ' #add-resource-container').removeClass('hide');
	},
	hideAddResourceButton: function () {
		$(this.toolBarSelector + ' #add-resource-container').addClass('hide');
	},

	processBackButton: function () {
		if (this.hasHistory || window.history.state) {
			this.showBackButton();
		}
		else {
			this.hideBackButton();
		}
	},
	showBackButton: function () {
		$(this.toolBarSelector + ' #back-button-container').removeClass('hide');
	},
	hideBackButton: function () {
		$(this.toolBarSelector + ' #back-button-container').addClass('hide');
	},

	showSelectionContainer: function (canManage) {
		if (canManage) {
			$(this.toolBarSelector + ' #resource-toolbar-selection .can-manage').removeClass('hide');
		}
		else {
			$(this.toolBarSelector + ' #resource-toolbar-selection .can-manage').addClass('hide');
		}

		$(this.toolBarSelector + ' #resource-toolbar-selection > div').removeClass('hide');
	},
	hideSelectionContainer: function () {
		$(this.toolBarSelector + ' #resource-toolbar-selection > div').addClass('hide');
	},

	hideSelectionObjectiveButtons: function () {
		$(this.toolBarSelector + ' #navigation-context-join-selection-container').addClass('hide');
		$(this.toolBarSelector + ' #navigation-context-insert-selection-container').addClass('hide');
	},
	showSelectionJoinButton: function () {
		$(this.toolBarSelector + ' #navigation-context-join-selection-container').removeClass('hide');
	},
	showSelectionInsertButton: function () {
		$(this.toolBarSelector + ' #navigation-context-insert-selection-container').removeClass('hide');
	},

	/*
	 * FILE
	 */
	showSelectionFileContainer: function () {
		// Set all parameters
		$(this.toolBarSelector + ' #resource-fav-file-container a').data('id', this.resource.id);
		$(this.toolBarSelector + ' #resource-edit-file-container a').attr('title', 'édition du document : ' + this.resource.label);
		$(this.toolBarSelector + ' #resource-manage-file-container ul.dropdown-menu').data('id', this.resource.id);
		$(this.toolBarSelector + ' #resource-join-file-container a').data('id', this.resource.id);
		$(this.toolBarSelector + ' #resource-insert-file-container a').data('id', this.resource.id);
		$(this.toolBarSelector + ' #resource-select-file-container a').data('resource-id', this.resource.id);
		$(this.toolBarSelector + ' #resource-delete-file-container a').data('resource-id', this.resource.id);
		$(this.toolBarSelector + ' #resource-delete-file-container a').data('label-type', this.label.type);
		$(this.toolBarSelector + ' #resource-delete-file-container a').data('label-id', this.label.id);
		$(this.toolBarSelector + ' #resource-edit-file-container a').attr('href', Routing.generate('resource_navigate_file_edit', {'labelSlug': this.resource.label_slug, 'resourceSlug': this.resource.slug}));
		
		// Show the container
		if (this.isEditing) {
			this.hideBackButton();
			$(this.toolBarSelector + ' #back-file-button-container a').attr('href', Routing.generate('resource_navigate_file', {'labelSlug': this.resource.label_slug, 'resourceSlug': this.resource.slug}));
			$(this.toolBarSelector + ' #back-file-button-container').removeClass('hide');
			$(this.toolBarSelector + ' #resource-fav-file-container').addClass('hide');
			$(this.toolBarSelector + ' #resource-manage-file-container').addClass('hide');
			$(this.toolBarSelector + ' #resource-confidentiality-file-container').removeClass('hide');
			$(this.toolBarSelector + ' #resource-save-file-container').removeClass('hide').find('a').removeClass('disabled');
			
			if (this.resource.is_private) {
				$(this.toolBarSelector + ' #resource-confidentiality-file-container a').addClass('toggle');
				$('#resource_is_private').val('true');
			}
			else {
				$(this.toolBarSelector + ' #resource-confidentiality-file-container a').removeClass('toggle');
				$('#resource_is_private').val('false');
			}
		}
		else {
			$(this.toolBarSelector + ' #back-file-button-container').addClass('hide');
			this.showBackButton();
			$(this.toolBarSelector + ' #resource-confidentiality-file-container').addClass('hide');
			$(this.toolBarSelector + ' #resource-save-file-container').addClass('hide');
			$(this.toolBarSelector + ' #resource-fav-file-container').removeClass('hide');
			$(this.toolBarSelector + ' #resource-manage-file-container').removeClass('hide');
		}
	},
	hideSelectionFileContainer: function () {
		$(this.toolBarSelector + ' #resource-fav-file-container').addClass('hide');
		$(this.toolBarSelector + ' #resource-manage-file-container').addClass('hide');
		$(this.toolBarSelector + ' #resource-save-file-container').addClass('hide');
		$(this.toolBarSelector + ' #resource-confidentiality-file-container').addClass('hide');
		$(this.toolBarSelector + ' #back-file-button-container').addClass('hide');
		
		this.processBackButton();
		this.hideObjectiveFileContextContainer();
		this.hideEditFileButton();
		this.hideDeleteFileButton();
		this.hideRestoreFileButton();
	},
	
	showObjectiveFileContextContainer: function () {
		if (this.objectiveContext == 'JOIN') {
			$(this.toolBarSelector + ' #resource-join-file-container').removeClass('hide');
		}
		else if (this.objectiveContext == 'INSERT') {
			$(this.toolBarSelector + ' #resource-insert-file-container').removeClass('hide');
		}
		else if (this.objectiveContext == 'SELECT' && (this.resource.type == this.allowedType || 'ALL' == this.allowedType)) {
			$(this.toolBarSelector + ' #resource-select-file-container').removeClass('hide');
		}
	},
	hideObjectiveFileContextContainer: function () {
		$(this.toolBarSelector + ' #resource-join-file-container').addClass('hide');
		$(this.toolBarSelector + ' #resource-insert-file-container').addClass('hide');
		$(this.toolBarSelector + ' #resource-select-file-container').addClass('hide');
	},

	showEditFileButton: function () {
		$(this.toolBarSelector + ' #resource-edit-file-container').removeClass('hide');
	},
	hideEditFileButton: function () {
		$(this.toolBarSelector + ' #resource-edit-file-container').addClass('hide');
	},

	showDeleteFileButton: function () {
		$(this.toolBarSelector + ' #resource-delete-file-container').removeClass('hide');
	},
	hideDeleteFileButton: function () {
		$(this.toolBarSelector + ' #resource-delete-file-container').addClass('hide');
	},

	showRestoreFileButton: function () {
		$(this.toolBarSelector + ' #resource-restore-file-container').removeClass('hide');
	},
	hideRestoreFileButton: function () {
		$(this.toolBarSelector + ' #resource-restore-file-container').addClass('hide');
	},

	markAsFavorite: function () {
		$(this.toolBarSelector + ' #resource-fav-file-container a').addClass('toggle');
	},
	unMarkAsFavorite: function () {
		$(this.toolBarSelector + ' #resource-fav-file-container a').removeClass('toggle');
	},

	showGarbageContainer: function () {
		if ($('#resources .no-item').hasClass('hide')) {
			$(this.toolBarSelector + ' #resource-empty-garbage-button-container').removeClass('hide');
		}
	},
	hideGarbageContainer: function () {
		$(this.toolBarSelector + ' #resource-empty-garbage-button-container').addClass('hide');

		this.hideSelectionGarbageContainer();
	},
	
	showSelectionGarbageContainer: function () {
		$(this.toolBarSelector + ' #resource-restore-button-container').removeClass('hide');
		$(this.toolBarSelector + ' #resource-delete-forever-button-container').removeClass('hide');
	},
	hideSelectionGarbageContainer: function () {
		$(this.toolBarSelector + ' #resource-restore-button-container').addClass('hide');
		$(this.toolBarSelector + ' #resource-delete-forever-button-container').addClass('hide');
	},

    showProviderResourceContainer: function () {
        $(this.toolBarSelector + ' #back-button-container').addClass('hide');
        $(this.toolBarSelector + ' #back-file-button-container').addClass('hide');
        $(this.toolBarSelector + ' #back-thesaurus-button-container').removeClass('hide');
        $(this.toolBarSelector + ' #resource-manage-file-container').removeClass('hide');
        $(this.toolBarSelector + ' #resource-manage-file-container ul.dropdown-menu').data('id', this.resource.id);
        $(this.toolBarSelector + ' #resource-manage-file-container ul.dropdown-menu').data('provider-id', this.resource.providerId);
        $(this.toolBarSelector + ' #resource-manage-file-container ul.dropdown-menu').data('uai', this.resource.uai);
    },
    hideProviderResourceContainer: function () {
        $(this.toolBarSelector + ' #resource-manage-file-container').addClass('hide');
        $(this.toolBarSelector + ' #back-thesaurus-button-container').addClass('hide');
        $(this.toolBarSelector + ' #resource-manage-file-container ul.dropdown-menu').data('id');
        $(this.toolBarSelector + ' #resource-manage-file-container ul.dropdown-menu').removeData('provider-id');
        $(this.toolBarSelector + ' #resource-manage-file-container ul.dropdown-menu').removeData('uai');
    }
};

// Instantiation de la toolbar
var toolBar = new Toolbar();


$(function () {
	// Bouton "Revenir"
	$('.resource-toolbar .back-button').click(function (e)
	{
		window.history.back();

		// TODO FIXME un clic en trop pour dropper le menu
		if (Routing.generate('BNSAppResourceBundle_front') == window.location.pathname) {
			$.menu.drop('#resource-toolbar-default');
		}

		return false;
	});

	// Bouton d'ajout de document, on ferme la sélection
	$('.resource-toolbar .add-resource-btn').click(function (e)
	{
		$('.resource-toolbar .selection > div').fadeOut('fast');
	});

	// Focus sur les boutons "Copier vers..." et "Déplacer vers..."
	$('#resource-toolbar-selection .dropdown-menu a').click(function (e)
	{
		if ($('#resource-toolbar-selection .dropdown-menu a.active').length > 0) {
			$('#resource-toolbar-selection .dropdown-menu a').removeClass('active');
		}
		else {
			$(e.currentTarget).addClass('active');
		}
	});

	// Gestion des actions sur les boutons de rangement (Copier & Déplacer vers...)
	$('#resource-toolbar-selection .selection-manage-button').bind('selection-manage', function (e)
	{
		var $this = $(e.currentTarget),
			$targetLabel = $('#resource-toolbar-selection .destination-choice-toolbar'),
			$layer = $('#resource-content .loader.loader-sb'),
			type = null;

		if ($this.hasClass('copy')) {
			$layer.find('.message.copy').css('display', 'inline-block');
			type = 'copy';
		}
		else {
			$layer.find('.message.move').css('display', 'inline-block');
			type = 'move';
		}

		$('#resource-selection').trigger('cancel-selection');
		$layer.fadeIn('fast');

		$.ajax({
			url: $this.attr('href'),
			type: 'POST',
			dataType: 'json',
			data: {target_label: $targetLabel.attr('id')},
			success: function (data) {
				if (type == 'move') {
					var selectionIds = data.selection_ids;
					for (var i in selectionIds) {
						$('#resources #item-' + selectionIds[i]).fadeOut('fast', function () {
							$(this).remove();
						});
					}
				}

				// TODO success alert

				$layer.fadeOut('fast', function () {
					$layer.find('.message').hide();
				});
			}
		});
	});

	// Focus sur les boutons "Copier vers..." et "Déplacer vers..."
	$('#resource-toolbar #resource-manage-file-container .selection-manage-button').click(function (e)
	{
		if ($('#resource-toolbar #resource-manage-file-container .dropdown-menu a.active').length > 0) {
			$('#resource-toolbar #resource-manage-file-container .dropdown-menu a').removeClass('active');
		}
		else {
			$(e.currentTarget).addClass('active');
		}
	});

	// Gestion des actions sur les boutons de rangement (Copier & Déplacer vers...)
	$('#resource-toolbar #resource-manage-file-container .selection-manage-button').bind('selection-manage', function (e)
	{
		var $this = $(e.currentTarget),
			$targetLabel = $('#resource-toolbar #resource-manage-file-container .destination-choice-toolbar'),
			$layer = $('#resource-content .loader').first();

		if ($this.hasClass('copy')) {
			$layer.find('.message.copy').css('display', 'inline-block');
		}
		else {
			$layer.find('.message.move').css('display', 'inline-block');
		}

		$layer.fadeIn('fast');

        var $parent = $this.parent().parent(),
            data = {
                target_label: $targetLabel.attr('id'),
                resource_id: $parent.data('id')
            };

        if ($parent.data('provider-id') != null) {
            data['provider_id'] = $parent.data('provider-id');
            data['uai'] = $parent.data('uai');
        }

		$.ajax({
			url: $this.attr('href'),
			type: 'POST',
			dataType: 'html',
			data: data,
			success: function (data) {
				if (null != data) {
                    $('#resource-navigation').html(data);
                    toolBar.update();
                }

				$layer.fadeOut('fast', function () {
					$layer.find('.message').hide();
				});
			}
		});
	});
	
	// Bouton de confirmation
	$('#resource-toolbar #resource-save-file-container .confirm-button').click(function (e)
	{
		$('#file-form').submit();
		$(e.currentTarget).addClass('disabled');

		return false;
	});

	// Bouton de privatisation
	$('#resource-toolbar #resource-confidentiality-file-container .private-button').click(function (e)
	{
		var $this = $(e.currentTarget);

		$this.toggleClass('toggle');
		$('#resource_is_private').val($this.hasClass('toggle'));

		return false;
	});

	// Bouton de suppression d'un document
	$('#resource-toolbar #resource-delete-file-container a').click(function (e)
	{
		var $this = $(e.currentTarget),
			$loader = $('#file-label-delete-loading-modal .loader'),
			$container = $('#file-label-delete-loading-modal #file-delete-label-modal-container'),
			$submitButton = $('#file-label-delete-loading-modal .file-label-delete-confirm');

		$container.html('');
		$loader.find('.message').show();
		$loader.fadeIn('fast');

		$submitButton.data('label-id', $this.data('label-id'));
		$submitButton.data('is-current', 'true');
		
		$.ajax({
			url: $this.attr('href'),
			type: 'POST',
			dataType: 'html',
			data: {label_id: $this.data('label-id'), label_type: $this.data('label-type'), resource_id: $this.data('resource-id')},
			success: function (data) {
				$container.html(data);
			}
		}).done(function () {
			$loader.fadeOut('fast', function () {
				$(this).hide();
			});
		});
	});

	// Restauration d'un document
	$('#resource-file-restore-modal #file-restore-confirm').click(function (e)
	{
		var $this = $(e.currentTarget),
			$modal = $('#resource-file-restore-modal'),
			$loader = $modal.find('.loader');

		$loader.fadeIn('fast');
		$.ajax({
			url: $this.attr('href'),
			type: 'POST',
			data: {label_id: toolBar.label.id, label_type: toolBar.label.type, resource_id: toolBar.resource.id},
			success: function () {
				toolBar.isDeletedFile = false;
				toolBar.update();
			}
		}).done(function () {
			$modal.modal('hide');
			$loader.hide();
		});

		return false;
	});

    // Click sur le bouton revenir
    $('#back-thesaurus-button-container').click(function (e) {
        $('#form-search').submit();
    });
});
