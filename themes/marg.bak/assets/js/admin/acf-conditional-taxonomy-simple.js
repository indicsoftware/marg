/**
 * Simple ACF Conditional Logic Test
 * 
 * This is a simplified version to test basic functionality
 */

(function($) {
    'use strict';

    console.log('Simple ACF Conditional Logic - Script loaded');

    $(document).ready(function() {
        console.log('Simple ACF Conditional Logic - DOM ready');
        
        // Wait a bit for ACF to load
        setTimeout(function() {
            console.log('Simple ACF Conditional Logic - Starting initialization');
            
            // Look for any select field that might be our type field
            var $allSelects = $('select');
            console.log('Simple ACF Conditional Logic - Found selects:', $allSelects.length);
            
            $allSelects.each(function(index) {
                var $select = $(this);
                var $field = $select.closest('[data-key]');
                var fieldKey = $field.attr('data-key');
                
                console.log('Simple ACF Conditional Logic - Select ' + index + ':', {
                    fieldKey: fieldKey,
                    options: $select.find('option').map(function() { 
                        return $(this).val() + ': ' + $(this).text(); 
                    }).get()
                });
                
                // If this looks like our type field
                if (fieldKey === 'field_68ac2f304ad26') {
                    console.log('Simple ACF Conditional Logic - Found type field!');
                    
                    // Add change handler
                    $select.on('change', function() {
                        var selectedText = $(this).find('option:selected').text().trim();
                        console.log('Simple ACF Conditional Logic - Type changed to:', selectedText);
                        
                        // Look for taxonomy group
                        var $taxonomyGroup = $('[data-key="group_68e48ee0308e8"]');
                        console.log('Simple ACF Conditional Logic - Taxonomy group found:', $taxonomyGroup.length);
                        
                        if ($taxonomyGroup.length > 0) {
                            // Simple logic: hide if "Subscription Plans"
                            if (selectedText.toLowerCase().includes('subscription')) {
                                console.log('Simple ACF Conditional Logic - Hiding taxonomy group');
                                $taxonomyGroup.hide();
                            } else {
                                console.log('Simple ACF Conditional Logic - Showing taxonomy group');
                                $taxonomyGroup.show();
                            }
                        }
                    });
                    
                    // Trigger initial state
                    $select.trigger('change');
                }
            });
            
            // Also look for the taxonomy group
            var $taxonomyGroup = $('[data-key="group_68e48ee0308e8"]');
            console.log('Simple ACF Conditional Logic - Taxonomy group found:', $taxonomyGroup.length);
            
        }, 2000);
    });

})(jQuery);

