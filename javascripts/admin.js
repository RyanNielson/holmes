(function($) {

    $(function(){
        if ($('span.hide-fields').length)
            $('span.hide-fields').closest('tr').hide();

        if ($('#run-indexer').length) {
            var indexingComplete = false;

            var indexBarAnimate = function(percentage) {
                var bar = $('#indexer-progress-bar > div');
                bar.width(percentage + '%');
                bar.text(percentage + "%");
                console.log(percentage + "%");
                if (percentage >= 100) {
                    $('#indexer-progress-bar').removeClass('progress-striped');
                    bar.addClass('bar-success');
                    bar.text('Indexing Completed');
                }
            };

            var indexStart = function() {
                var ajaxurl = '/wp-admin/admin-ajax.php';
                indexBarAnimate(0);
                $.get(ajaxurl, { action: 'holmes_start_indexer' }, function(response) {
                    console.log(response);
                    if (response.result == 'more') {
                        indexBarAnimate(Math.floor(100 * (response.looped_through / response.total)));
                        indexContinue();
                    }
                    else if (response.result == 'complete') {
                        indexBarAnimate(100);
                        console.log('completed index');
                    }
                    else {
                        console.log('indexing error');
                    }
                }, 'json');
            };

             var indexContinue = function() {
                var ajaxurl = '/wp-admin/admin-ajax.php';
                $.get(ajaxurl, { action: 'holmes_run_indexer' }, function(response) {
                    console.log(response);
                    if (response.result == 'more') {
                        indexBarAnimate(Math.floor(100 * (response.looped_through / response.total)));
                        indexContinue();
                    }
                    else if (response.result == 'complete') {
                        indexBarAnimate(100);
                        console.log('completed index');

                    }
                    else {
                        console.log('indexing error');
                    }
                }, 'json');
            };

            $('#run-indexer').click(function(e) {
                var bar = $('#indexer-progress-bar > div');
                bar.removeClass('bar-success');
                $('#indexer-progress-bar').addClass('progress-striped');

                $('#indexer-progress-bar').fadeIn(500, function() {
                    indexStart();
                });

                return false;
            });
        }

    });

})(jQuery);