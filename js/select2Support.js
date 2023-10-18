jQuery(document).ready(function($) {
    $('.wcsm-apply-select2').each(function() {
        var placeholderText = $(this).data('placeholder');
        var defaultValue = $(this).data('default');
        
        $(this).selectWoo({
            placeholder: placeholderText,
            allowClear: true
        });
    });
});
