
$(document).ready(function ()
{
    $('.btn.btn-info.btn-new-article').show();

    //Load edit/delete
    $('body').click(function ()
    {
        $('.btn.btn-danger.btn-delete-article').hide();
        $('.btn.btn-info.btn-edit-article').hide();
		$('.content-news').removeClass('selected');
    });

    //Load edit/delete
    $('.content-news').click(function ()
    {
        $('.content-news').removeClass('selected');
        //Balise en cours
        $(this).addClass('selected');

        var idNew = $(this).attr('id');
        var editLink = Routing.generate('BNSAppLiaisonBookBundle_back_edit', { 'liaisonBookId': idNew });
        var deleteLink = Routing.generate('BNSAppLiaisonBookBundle_back_delete', { 'liaisonBookId': idNew });

        $('.btn.btn-danger.btn-delete-article').attr('href', deleteLink);
        $('.btn.btn-info.btn-edit-article').attr('href', editLink);

        $('.btn.btn-danger.btn-delete-article').show();
        $('.btn.btn-info.btn-edit-article').show();
        return false;
    });
    
    $('.valid-delete').click(function ()
    {
        //Show modal here
        window.location.href = $('.btn.btn-danger.btn-delete-article').attr('href'); 
        return false;
    });

});

