$('a.valid-delete.delete-process').on('click', function(e) {
    window.location = $('a.delete-item').attr('href');
})

$('a.valid-delete.archive-process').on('click', function(e) {
    window.location = $('a.archive-item').attr('href');
})