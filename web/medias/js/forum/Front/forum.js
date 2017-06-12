$('a.create-subject').on('click', function(e) {
    $('#subject_form').submit();
})

$(function () {
    $('.activable').click(function (e) {
        var $this = $(e.currentTarget);
        
        if ($this.hasClass('disabled')) {
            return false;
        }
        
        $this.addClass('disabled').attr('disabled', 'disabled');
        
        $.ajax({
            url: $this.attr('href') + "?value=" + ($this.hasClass('desactivated')?  '1':'0')
        }).done(function (e) {
            $this.toggleClass('desactivated');
            
            $this.removeClass('disabled').removeAttr('disabled');
        });
        
        return false;
    });
});