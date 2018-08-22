/* Load listeners after document loaded */
var LPE = {
    Export: {}
};

LPE.Export = {

    /* set vars */
    this_el : [],
    csv_row : [],
    course_id : 0,

    /*  Initialize Script  */
    init : function () {
        LPE.Export.defineListeners();
    },

    /* define DOM listeners */
    defineListeners : function () {

        /* listen for generate button */
        jQuery('#generate-file').click(function() {
            /* show confirmation message */
            jQuery('.confirmation-message').show();

            /* disable select option */
            jQuery('#selected_course').prop('disabled' , 'disabled');
        });

        /* listen for confirmation click */
        jQuery('#confirm-yes').click(function() {

            /* hide confirmation message */
            jQuery('.confirmation-message').hide();

            /* show processing message  */
            jQuery('.processing-message').show();

            /* Disable  */
            jQuery('#generate-file').hide();

            /* get course id */
            LPE.Export.course_id = jQuery('#selected_course option:selected').val();

            /* initiate batching processing */
            console.log('now processing course id ' + LPE.Export.course_id );

            /* get batching data */
            jQuery.ajax({
                type: "POST",
                url: learnpressExport.ajaxurl,
                data: {
                    action: 'learnpress_prepare_course_batches',
                    course_id: LPE.Export.course_id
                },
                dataType: 'json',
                timeout: 60000,
                success: function ( data ) {
                    /* no users detected */
                    if (data.count<1) {
                        jQuery('.nousers-message').show();
                        jQuery('#confirm-no').trigger('click'); /* reset confirmation message */
                    }

                    LPE.Export.processBatch( data)

                },
                error: function (request, status, err) {

                }
            });

        });

        /* listen for dismiss click */
        jQuery('#confirm-no').click(function() {

            /* hide confirmation */
            jQuery('.confirmation-message').hide();

            /* remove disabled attribute from select */
            jQuery('#selected_course').removeAttr('disabled');

        });

        /* listen for CSV File delete click */
        jQuery('body').on('click' , '#delete-file' , function() {
            jQuery(this).html('Confirm');

            jQuery(this).prop('id' , 'delete-file-confirm');
        })

        /* listen for CSV File delete click */
        jQuery('body').on('click' , '#delete-file-confirm' , function() {

            /* store element */
            LPE.Export.this_el = jQuery(this);
            LPE.Export.csv_row = jQuery(this).parent();

            /* update button text to 'processing' */
            LPE.Export.this_el.html('Deleting...');

            /* get filename */
            var csv_filename = jQuery(this).parent().find('a').text();

            /* run ajax to delete file */
            jQuery.ajax({
                type: "POST",
                url: learnpressExport.ajaxurl,
                data: {
                    action: 'learnpress_export_delete_file',
                    filename: csv_filename
                },
                dataType: 'html',
                timeout: 60000,
                success: function ( results ) {
                    LPE.Export.csv_row.remove();
                },
                error: function (request, status, err) {
                    /* set button text and id back */
                    LPE.Export.this_el.text('Error');
                    LPE.Export.this_el.prop('id' , 'delete-file');
                }
            });


        });
    },
    processBatch : function( data ) {

        /* if offset not greater than total batches */
        if (data.offset <= data.batches) {
            /* create batching status */
            var batch_status = jQuery('<li>Batch ' + data.offset + ' of ' + data.batches + ' processing. Please wait.</li>');

            /* append batch status */
            batch_status.appendTo(jQuery('.batch-status-list'));

        }

        jQuery.ajax({
            type: "POST",
            url: learnpressExport.ajaxurl,
            data: {
                action: 'learnpress_process_course_batch',
                course_id: data.course_id,
                batches: data.batches,
                offset: data.offset,
                limit: data.limit
            },
            dataType: 'json',
            timeout: 60000,
            success: function ( result ) {

                if (result.success!=true) {
                    /* mark last batch status as done */
                    jQuery('.batch-status-list li:last-child').html("Done!");

                    /* process next batch */
                    LPE.Export.processBatch( result );
                    return;
                }

                /* create batching status */
                var batch_status = jQuery('<li>Complete!</li>');

                /* append batch status */
                batch_status.appendTo(jQuery('.batch-status-list'));

                /* hide processing message */
                jQuery('.processing-message').hide();

                /* build Download link */
                var download_link = jQuery('<a href="'+ result.download +'" target="_blank">'+ result.download +'</a>');

                /* append download link */
                download_link.appendTo('.success-message');

                /* display success message with download link */
                jQuery('.success-message').show();


            },
            error: function (request, status, err) {

            }
        });
    }

}

/* After Page Load */
jQuery(document).ready(function () {

    /* Initialize JS Class on Page Load */
    console.log(LPE);
    LPE.Export.init();

});