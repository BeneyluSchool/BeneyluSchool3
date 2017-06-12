$(function(){
    $('.white-list-toggle').live('click',function(){
        var id = $(this).parent().attr('id');
        $(this).removeClass("active").addClass('loading');
        $.post(
            Routing.generate('BNSAppSearchBundle_back_white_list_toggle', {}),
            { 'media_id': id },
            function complete(html)
            {
                $('#' + id).replaceWith(html);
            }
        );
    });

    $('.toggle-general-white-list').click(function (e)
    {
        var $this = $(e.currentTarget);
        if ($this.hasClass('loading')) {
            return false;
        }

        $this.addClass('loading');

        $.ajax({
            url: Routing.generate('BNSAppSearchBundle_back_white_list_general_toggle'),
            type: 'POST',
            dataType: 'html'
        }).done(function ()
        {
            $this.toggleClass('off');
            $this.removeClass('loading');
        });
    });
});