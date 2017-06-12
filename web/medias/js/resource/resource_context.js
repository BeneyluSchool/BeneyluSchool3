$(function () {
    // Collapse row
    $('#resource-sidebar').on('click', '#change-context-modal ul li div .big-arrow', function (e) {
        var $this = $(e.currentTarget);

        if ($this.hasClass('in')) {
            $this.parent().parent().find('> ul').slideUp('fast');
        }
        else {
            $this.parent().parent().find('> ul').slideDown('fast');
        }

        $this.toggleClass('in');
    });

    // Select context
    $('#resource-sidebar').on('click', '#change-context-modal ul li div', function (e) {
        var $this = $(e.currentTarget),
            $loader = $('#resource-sidebar').parent().find('.loader');

        if ($(e.target).hasClass('big-arrow') || $this.hasClass('disabled')) {
            return false;
        }

        $loader.fadeIn('fast');
        $('#change-context-modal').modal('hide');

        $.ajax({
            url: Routing.generate('resource_context_change'),
            type: 'POST',
            dataType: 'html',
            data: {'groupId' : $this.data('group')},
            success: function (data) {
                $('#resource-sidebar').html(data);
                $loader.fadeOut('fast');

                // Change context, force click
                $('.resource-nav.context').click();
            }
        })
    });
});