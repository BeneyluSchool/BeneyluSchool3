$(function () {
	// Too long users, show tooltip
	$('body').on('bns-user-update', '.bns-user', function (e)
	{
		var $this = $(e.currentTarget),
			$name = $this.find('span.name');

		if ($name.innerHeight() > $this.innerHeight()) {
			if ($this.hasClass('big')) {
				$name.addClass('large');
			}

			$this.attr('data-original-title', $name.text());
			$this.tooltip();
		}
	});

	$.each($('.bns-user'), function (i, item) {
		$(item).trigger('bns-user-update');
	});
});