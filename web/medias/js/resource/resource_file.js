$(function ()
{
	// Ajouter aux favoris
	$('#resource-toolbar #resource-fav-file-container .favorite-button').click(function (e)
	{
		var $this = $(e.currentTarget);

		if ($this.hasClass('disabled')) {
			return false;
		}

		$this.addClass('disabled');
		$this.attr('disabled', 'disabled');

		$.ajax({
			url: $this.attr('href'),
			type: 'POST',
			data: {resource_id: $this.data('id')},
			success: function () {
				$this.toggleClass('toggle');
				$this.removeClass('disabled');
				$this.removeAttr('disabled');
			}
		});

		return false;
	});

	// Suppression d'un link
	$('.document-container').on('click', '.label-container .delete-label', function (e)
	{
		var $label = $(e.currentTarget).parent(),
			$submitButton = $('#file-label-delete-modal .file-label-delete-confirm'),
			$loader = $('#file-label-delete-modal .loader');

		$loader.hide();
		$loader.find('.message').show();
		$submitButton.data('label-id', $label.data('label-id'));
		$submitButton.data('is-current', 'false');
	});

	// Confirmation de suppression de link (modal)
	$('body').on('click', '.file-label-delete-confirm', function (e)
	{
		var $this = $(e.currentTarget),
			$label = $('.document-container #file-label-' + $this.data('label-id')),
			$modal,
			$loader;

		if ($this.data('is-current') == 'true') {
			$modal = $('#file-label-delete-loading-modal'),
			$loader = $modal.find('.loader');
			$loader.find('.message').hide();
		}
		else {
			$modal = $('#file-label-delete-modal'),
			$loader = $modal.find('.loader');
		}
		
		$loader.fadeIn('fast');

		$.ajax({
			url: $this.attr('href'),
			type: 'POST',
			data: {label_id: $label.data('label-id'), label_type: $label.data('label-type'), resource_id: $label.data('resource-id')},
			success: function () {
				if ($this.data('is-current') == 'true') {
					toolBar.isDeletedFile = true;
					toolBar.update();
				}
				else {
					$label.slideUp('fast');
				}
			}
		}).done(function () {
			$modal.modal('hide');
			$loader.fadeOut('fast', function () {
				$(this).hide();
			});
		});

		return false;
	});

    // Access to the provider resource url
    $('body').on('click', '.resource-provider-goto', function (e) {
        var $this = $(e.currentTarget),
            $loader = $('#download-loader'),
            i = 0;

        $loader.fadeIn('fast');

        ++i;
        var reloadProviderResource = function () {
            $.ajax({
                url: $this.attr('href'),
                dataType: 'html',
                statusCode: {
                    200: function (data) {
                        $loader.fadeOut('fast');
                        $('body').prepend(data);
                        $('#provider-iframe .iframe-container').height($(window).height() - 60);
                    },
                    206: function (data) {
                        if (i < 6) { // 30 sec
                            setTimeout(function () { reloadProviderResource(); }, 5000);
                        }
                    }
                }
            });
        }

        reloadProviderResource();
        
        return false;
    });

    // Close iframe
    $('body').on('click', '#provider-iframe .back-button', function (e) {
        $('#provider-iframe').fadeOut('fast', function () {
            $(this).remove();
        });
    });

    // Resize iframe
    $(window).resize(function (e) {
        if ($('#provider-iframe').length > 0) {
            $('#provider-iframe .iframe-container').height($(window).height() - 60);
        }
    })
});