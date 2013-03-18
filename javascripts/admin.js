(function($) {

    $(function(){
        if ($('span.hide-fields').length)
            $('span.hide-fields').closest('tr').hide();

        if ($('#run-indexer').length) {
            var indexingComplete = false;

            var indexPoller = function() {
                var ajaxurl = '/wp-admin/admin-ajax.php';
                var data = { action: 'holmes_poll_indexer_progress' };

                setTimeout(function() {
                    $.getJSON(ajaxurl, data, function(response) {
                        var percentage = response['percentage_complete'];

                        var bar = $('#indexer-progress-bar > div');
                        bar.width(percentage + '%');
                        bar.text(percentage + "%");

                        if (percentage >= 100) {
                            $('#indexer-progress-bar').removeClass('progress-striped');
                            bar.addClass('bar-success');
                            bar.text('Indexing Completed');
                        } else {
                            indexPoller();
                        }
                    });
                }, 250);
            };

            var indexStart = function() {
                var ajaxurl = '/wp-admin/admin-ajax.php';
                $.get(ajaxurl, { action: 'holmes_start_indexer' }, function(response) {
                    console.log(response);
                    console.log('index completed');
                });
            };

            var indexInitiator = function() {
                var ajaxurl = '/wp-admin/admin-ajax.php';
                $.get(ajaxurl, { action: 'holmes_initiate_indexer' }, function(response) {
                    indexStart();
                    indexPoller();
                });
            };

            $('#run-indexer').click(function(e) {
                var bar = $('#indexer-progress-bar > div');
                bar.removeClass('bar-success');
                $('#indexer-progress-bar').addClass('progress-striped');

                $('#indexer-progress-bar').fadeIn(500, function() {
                    var percentageWidth = 0;

                    indexInitiator();
                });

                return false;
            });
        }

    });

})(jQuery);