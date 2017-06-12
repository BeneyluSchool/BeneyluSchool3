
$(document).ready(function ()
{
    $('.btn.btn-info.btn-new-article').show();

    //Load edit/delete
    $('.content-news').click(function ()
    {
        var slug = $(this).attr('id');
        
        var url = Routing.generate('BNSAppNoteBookBundle_back_detail', { 'slug': slug });
        window.location.href = url;

        return false;
    });
    
     if ($('.container-content .alert.alert-success').length > 0) {
        setTimeout(function ()
        {
                $('.bns-alert div').slideUp('fast', function () { var $this = $(this); $this.parent().slideUp('fast', function () { $this.show() }) });
        }, 8000); // 8 seconds
    }
    
});

