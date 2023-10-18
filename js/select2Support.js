jQuery(document).ready(function($) {
    $('.wcsm-apply-select2').each(function() {
        var placeholderText = $(this).data('placeholder');        
        $(this).selectWoo({
            placeholder: placeholderText,
            allowClear: true
        });
    });
});
