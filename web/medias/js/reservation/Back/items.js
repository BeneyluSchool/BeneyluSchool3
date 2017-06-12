// Show delete category modal process
$('body').on('click', '.item-list-container a.delete-item-button', function (e) {
    var $this = $(e.currentTarget),
        $modalBody = $('#delete-item-modal .modal-body'),
        itemSlug = $this.data('slug');

    $modalBody.find('span.title').text($this.find('span.title').text());
    $modalBody.find('input#delete-item-slug').val(itemSlug);

    $('#delete-item-modal').modal('show');
    
    return false;
});

// Deleting category modal process
$('#delete-item-modal .delete-item-button').click(function (e) {
    var $this = $(e.currentTarget),
        $modal = $('#delete-item-modal'),
        itemSlug = $modal.find('input#delete-item-slug').val();

    window.location = Routing.generate('BNSAppReservationBundle_back_delete_item', {'slug': itemSlug});

});