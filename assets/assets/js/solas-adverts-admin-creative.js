(function($){
  'use strict';

  function setPreview(url){
    var $img = $('#solas-advert-creative-preview');
    if ($img.length){
      $img.attr('src', url).show();
    } else {
      // If preview image didn't exist (empty state), inject one.
      var html = '<img id="solas-advert-creative-preview" src="'+ url +'" alt="" style="max-width:100%;height:auto;border:1px solid #e5e5e5;border-radius:10px;" />';
      $('#solas-advert-creative-wrap').find('div').first().prepend(html);
    }
  }

  $(document).on('click', '#solas-advert-creative-pick', function(e){
    e.preventDefault();

    var frame = wp.media({
      title: 'Select advert creative',
      button: { text: 'Use this image' },
      library: { type: 'image' },
      multiple: false
    });

    frame.on('select', function(){
      var attachment = frame.state().get('selection').first().toJSON();
      if (!attachment || !attachment.id) return;

      $('#solas_image_attachment_id').val(attachment.id);
      // Prefer full URL, but keep meta url in sync too
      if (attachment.url){
        $('#solas_image_url').val(attachment.url);
        setPreview(attachment.url);
      }
    });

    frame.open();
  });

  $(document).on('click', '#solas-advert-creative-remove', function(e){
    e.preventDefault();
    $('#solas_image_attachment_id').val('0');
    $('#solas_image_url').val('');
    var $img = $('#solas-advert-creative-preview');
    if ($img.length){
      $img.remove();
    }
  });

})(jQuery);
