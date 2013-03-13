(function($) {

    $(function(){
        if ($('span.hide-fields').length)
            $('span.hide-fields').closest('tr').hide();

        if ($('#run-indexer').length) {
            $('#run-indexer').click(function(e) {
                var bar = $('#indexer-progress-bar > div');
                bar.removeClass('bar-success');
                $('#indexer-progress-bar').addClass('progress-striped');
                
                $('#indexer-progress-bar').fadeIn(500, function() {
                    var percentageWidth = 0;
                    var barProgress = setInterval(function() {
                        var bar = $('#indexer-progress-bar > div');

                        percentageWidth += 5;

                        bar.width(percentageWidth + '%');
                        bar.text(percentageWidth + "%");

                        if (percentageWidth >= 100) {
                            clearInterval(barProgress);
                            $('#indexer-progress-bar').removeClass('progress-striped');
                            bar.addClass('bar-success');
                            bar.text('Indexing Completed');
                        }
                    }, 100);
                });

                return false;
            });
        }

    });

})(jQuery);