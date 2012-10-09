$(document).ready(function ()
{    
    $('.delete-preference').live('click', function (event)
    {
        event.preventDefault();
        if ($(this).find('img').attr('src') == '/medias/images/icons/loading.gif')
        {
            return;
        }
        
        $(this).find('img').attr('src', '/medias/images/icons/loading.gif');
        var $row = $(this).parent(),
            $ul = $(this).parent().parent();

        $.ajax(
        {
            url: $(this).attr('href'),
            type: 'GET',
            success: function success(data)
            {
                if (data == 'true')
                {
                    $row.slideUp('fast', function ()
                    {
                        $row.remove();
                        if ($ul.find('li').size() == 1)
                        {
                           $ul.find('li.no-item').show('slow').removeClass('hide');
                        }
                    });
                }
            }
        });        
    });
    
    $('.add-preference-form').submit(function(event)
    {
        event.preventDefault();
        var $input = $(this).parent().find('input[type="text"]'),
            $hiddenInput = $(this).parent().find('input[type="hidden"]'),
            $this = $(this);
        if ($input.val().length < 2)
        {
            return;
        }
        
        $this.find('input').attr('disabled', 'disabled');
        $.ajax({
            url: $this.attr('action'),
            type: 'POST',
            dataType: 'html',
            data: {
                'preference_item': $input.val(),
                'preference_islike': $hiddenInput.val()
            },
            success: function (data)
            {
                var liNoItem = $this.parent().find('li.no-item');
                if (!liNoItem.hasClass('hide'))
                {
                    liNoItem.slideUp('fast', function ()
                    {
                        liNoItem.addClass('hide');
                    });
                }
                if (!$this.parent().find('li.no-item').hasClass('hide'))
                {

                }
                $this.parent().find('ul').append(data);
                $input.val('');
            }
        }).done(function () { $this.find('input').removeAttr('disabled'); });
    });    
});

function showNoItemLabel($target)
{
    $liTarget = $('.' + $target + ' .no-item');
    $ul = $liTarget.parent();
    if ($ul.find('li').size() == 1)
    {
        $liTarget.removeClass('hide');
    }    
}