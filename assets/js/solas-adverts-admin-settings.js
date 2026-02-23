(function($){
  'use strict';

  function ensurePreview(targetId, attachment){
    var $input = $('#' + targetId);
    var $wrap = $input.closest('td').find('[data-preview-for="' + targetId + '"]');
    if($wrap.length){
      if(attachment && attachment.sizes && attachment.sizes.thumbnail){
        $wrap.html('<img src="' + attachment.sizes.thumbnail.url + '" style="width:80px;height:80px;object-fit:contain;border-radius:10px" />');
      } else if(attachment && attachment.url){
        $wrap.html('<img src="' + attachment.url + '" style="width:80px;height:80px;object-fit:contain;border-radius:10px" />');
      } else {
        $wrap.html('<div style="width:80px;height:80px;border:1px dashed #bbb;display:flex;align-items:center;justify-content:center">No image</div>');
      }
    }
  }

  function pickMedia(targetId){
    var frame = wp.media({
      title: 'Select image',
      button: { text: 'Use this image' },
      multiple: false
    });

    frame.on('select', function(){
      var attachment = frame.state().get('selection').first().toJSON();
      $('#' + targetId).val(attachment.id).trigger('change');
      ensurePreview(targetId, attachment);
    });

    frame.open();
  }

  $(document).on('click', '.solas-media-pick', function(e){
    e.preventDefault();
    var target = $(this).data('target');
    if(!target) return;
    pickMedia(target);
  });

  $(document).on('click', '.solas-media-clear', function(e){
    e.preventDefault();
    var target = $(this).data('target');
    if(!target) return;
    $('#' + target).val('').trigger('change');
    ensurePreview(target, null);
  });

})(jQuery);
