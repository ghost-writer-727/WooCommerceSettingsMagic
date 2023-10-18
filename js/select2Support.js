jQuery(document).ready(function($) {
    $('.wcsm-apply-select2').each(function() {
        var placeholderText = $(this).data('placeholder');      
        var allowClear = $(this).data('allowclear');
        $(this).selectWoo({
            placeholder: placeholderText,
            allowClear: allowClear
        });
    });
});
