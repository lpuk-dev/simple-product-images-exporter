(function($) {
  'use strict';
  $(document).ready(function() {
    $('#spie-loader-container').hide();
    $('#spie-loader-container .spie-loader').removeClass('spie-animate');
    var formsIds = ['spie_check_folder','spie_delete_folder', 'spie_copy_images','spie_download_folder'];
    formsIds.forEach(function(formId) {
      if($('#' + formId)) {
        $('#' + formId).submit(function(e) {
          e.preventDefault();
          $('#spie-loader-container .spie-loader').addClass('spie-animate');
          $('#spie-loader-container').show();
          var form = $(this);
          var url = form.attr('action');
          var data = form.serialize();
          $.ajax({
            type: 'POST',
            url: url,
            data: data,
            success: function(data) {
              var mex = '';
              if(data.success) {
                // remove class
                $('#spie-total-files-text').removeClass('spie-error');
                if(data.data) {
                  if(data.data.mex) mex = data.data.mex;
                  if(data.data.zip_link) {
                    $('#spie-download-link').attr('href', data.data.zip_link);
                    if(data.data.zip_name) $('#spie-download-link').text(data.data.zip_name);
                    $('#spie-download-text').removeClass('spie-hide');
                  }
                }
              } else {
                if(data.data) {
                  mex = data.data;
                  // add class
                  $('#spie-total-files-text').addClass('spie-error');
                }
              }
              $('#spie-total-files-text').text(mex);
              $('#spie-loader-container').hide();
              $('#spie-loader-container .spie-loader').removeClass('spie-animate');
            },
            error: function(data) {
              console.log(data);
              $('#spie-total-files-text').text('Error');
              $('#spie-loader-container').hide();
              $('#spie-loader-container .spie-loader').removeClass('spie-animate');
            }
          })
        })
      }
    });
  })
})(jQuery);
