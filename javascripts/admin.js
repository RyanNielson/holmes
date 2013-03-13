(function($) {

    $(function(){
        if ($('span.hide-fields').length)
            $('span.hide-fields').closest('tr').hide();
    });

})(jQuery);