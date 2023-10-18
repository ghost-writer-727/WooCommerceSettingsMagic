jQuery(document).ready(function($) {
    $('.wcsm-apply-select2').each(function() {
        var placeholderText = $(this).data('placeholder');
        var defaultValue = $(this).data('default');
        
        $(this).selectWoo({
            placeholder: placeholderText,
            allowClear: true
        });

        // If no value or if value is an empty array
        if ( !$(this).val() || $(this).val().length === 0) {
            console.log('No value')
            $(this).val(null).trigger('change');
            $(this).val(null).trigger('change.select2');
        } else {
            console.log($(this).val(), 'Value found')
        }
    });
});
