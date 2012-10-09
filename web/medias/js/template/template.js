$(document).ready(function ()
{
	// Listener des clics sur les petites images des thèmes pour changer la valeur du champ caché
	$('img.theme-small-img').click(function ()
	{
		var $this = $(this);
		if ($this.hasClass('selected'))
		{	
			return;
		}
		
		var $hiddenInputCssClass = $('input#selected-template'),
		$selectedTheme = $this.attr('id').split('_');
		$selectedTheme = $selectedTheme[0];
		$hiddenInputCssClass.val($selectedTheme).change();
		$this.parent().find('img.selected').removeClass('selected');
		$this.addClass('selected');
		$.ajax({
			url: Routing.generate('template_bundle_reload_custom_properties_block'),
			type: 'POST',
			dataType: 'html',
			data: {
				'selected_template_css_class': $selectedTheme,
				'template_join_object_id': $('input#template_join_object_id').val()
			},
			success: function(data) 
			{
				$('div.custom-properties-container').html(data);
			}
		});
	});
	
	// Listener click sur les custom background
	$('img.one-bg-choice').live('click', function ()
	{
		var $this = $(this);
		var $hiddenInputCssClass = $('input#selected-background');
		if ($this.hasClass('selected'))
		{
			$hiddenInputCssClass.val('').change();
			$this.removeClass('selected');
			
			return;
		}
		
		
		var $selectedBackground = $this.attr('id').split('_');
		$selectedBackground = $selectedBackground[0];
		$hiddenInputCssClass.val($selectedBackground).change();
		$this.parent().find('img.selected').removeClass('selected');
		$this.addClass('selected');
	});
	
	// Listener click sur les custom color
	$('span.one-color-choice').live('click', function ()
	{
		var $this = $(this);
		var $hiddenInputCssClass = $('input#selected-color');
		if ($this.hasClass('selected'))
		{
			$hiddenInputCssClass.val('').change();
			$this.removeClass('selected');
			
			return;
		}
		
		var $selectedColor = $this.attr('id').split('_');
		$selectedColor = $selectedColor[0];
		$hiddenInputCssClass.val($selectedColor).change();
		$this.parent().find('span.selected').removeClass('selected');
		$this.addClass('selected');
	});
	
	// Listener click sur les custom font
	$('button.one-font-choice').live('click', function (event)
	{
		event.preventDefault();
		var $this = $(this);
		var $hiddenInputCssClass = $('input#selected-font');
		if ($this.hasClass('disabled'))
		{
			$hiddenInputCssClass.val('').change();
			$this.removeClass('disabled');
			
			return;
		}
				
		var $selectedFont = $this.attr('id').split('_');
		$selectedFont = $selectedFont[0];
		$hiddenInputCssClass.val($selectedFont).change();
		$this.parent().find('button.disabled').removeClass('disabled');
		$this.addClass('disabled');
	});
	
	// Listener sur le clic du bouton "Aperçu du thème"
	$('button.btn-theme-preview').click(function (event)
	{
		event.preventDefault();
		if ($(this).hasClass('disabled'))
		{
			return false;
		}
		
		$('div.theme-preview-container').hide();
		$('div.theme-preview-loader').show();
		// On concataine toutes les classes css du thème et de ses éventuelles custom propriétés
		var $themeAllCssClassForPreview = $('input#selected-template').val() + ' ' + $('input#selected-background').val() + ' ' 
			+ $('input#selected-font').val() + ' ' + $('input#selected-color').val();
		$.ajax({
			url: $routeToTemplateContentPreview,
			type: 'POST',
			dataType: 'html',
			data: {
				'theme_css_class_for_preview': $themeAllCssClassForPreview
			},
			success: function (data)
			{
				$('div.theme-preview-loader').hide();
				$('div.theme-preview-container').html(data).fadeIn('fast');
			}
		});
	});
	
	// Listener sur le clic du bouton "J'ai terminé"
	$('button.btn-save-theme').click(function ()
	{
		$('form#select-theme-form').submit();
	});
});