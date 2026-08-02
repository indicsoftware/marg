/**
 * ACF Conditional Logic for Taxonomy Field Groups
 * 
 * Shows/hides the Taxonomy field group based on the selected product type
 */

(function($) {
    'use strict';

    // Wait for ACF to be ready
    if (typeof acf !== 'undefined') {
        acf.addAction('ready', function() {
            initConditionalLogic();
        });

        // Also run on new field groups being added
        acf.addAction('new_field', function(field) {
            if (field.get('key') === margACFConditional.typeFieldKey) {
                initConditionalLogic();
            }
        });
    } else {
        console.log('ACF Conditional Logic - ACF not available, using fallback');
        // Fallback: try to initialize after DOM is ready
        $(document).ready(function() {
            setTimeout(initConditionalLogic, 1000);
        });
    }

    function initConditionalLogic() {
        console.log('ACF Conditional Logic - Initializing...');
        console.log('ACF Conditional Logic - Looking for type field:', margACFConditional.typeFieldKey);
        console.log('ACF Conditional Logic - Looking for taxonomy group:', margACFConditional.taxonomyGroupKey);
        
        var $typeField = $('[data-key="' + margACFConditional.typeFieldKey + '"]');
        var $taxonomyGroup = $('[data-key="' + margACFConditional.taxonomyGroupKey + '"]');
        
        console.log('ACF Conditional Logic - Type field found:', $typeField.length);
        console.log('ACF Conditional Logic - Taxonomy group found:', $taxonomyGroup.length);
        
        if ($typeField.length === 0 || $taxonomyGroup.length === 0) {
            console.log('ACF Conditional Logic - Required elements not found, aborting');
            return;
        }
        
        console.log('ACF Conditional Logic - Initialization successful');

        // Function to toggle taxonomy group visibility
        function toggleTaxonomyGroup() {
            var selectedValue = $typeField.find('select').val();
            var selectedTermSlug = '';
            
            // Debug logging
            if (window.console && window.console.log) {
                console.log('ACF Conditional Logic - Selected value:', selectedValue);
                console.log('ACF Conditional Logic - Available options:', $typeField.find('select option').map(function() { return $(this).val() + ': ' + $(this).text(); }).get());
            }
            
            // Get the term slug from the selected option
            if (selectedValue) {
                var $selectedOption = $typeField.find('select option:selected');
                var optionText = $selectedOption.text().trim();
                
                // Convert option text to slug format (this is how ACF taxonomy fields work)
                selectedTermSlug = optionText.toLowerCase()
                    .replace(/\s+/g, '-')
                    .replace(/[^a-z0-9-]/g, '');
            }

            // Debug logging
            if (window.console && window.console.log) {
                console.log('ACF Conditional Logic - Selected slug:', selectedTermSlug);
                console.log('ACF Conditional Logic - Show for terms:', margACFConditional.showForTerms);
                console.log('ACF Conditional Logic - Hide for terms:', margACFConditional.hideForTerms);
            }

            // Check if we should show or hide the taxonomy group
            var shouldShow = false;
            
            if (selectedTermSlug) {
                // Show for articles, books, magazines
                if (margACFConditional.showForTerms.indexOf(selectedTermSlug) !== -1) {
                    shouldShow = true;
                }
                // Hide for subscription-plans
                else if (margACFConditional.hideForTerms.indexOf(selectedTermSlug) !== -1) {
                    shouldShow = false;
                }
            }

            // Debug logging
            if (window.console && window.console.log) {
                console.log('ACF Conditional Logic - Should show taxonomy group:', shouldShow);
            }

            // Toggle visibility with smooth animation
            if (shouldShow) {
                $taxonomyGroup.slideDown(300);
            } else {
                $taxonomyGroup.slideUp(300);
            }
        }

        // Handle change events on the type field
        $typeField.find('select').on('change', function() {
            toggleTaxonomyGroup();
        });

        // Handle initial state on page load
        toggleTaxonomyGroup();

        // Also handle ACF field updates
        acf.addAction('change/' + margACFConditional.typeFieldKey, function(field) {
            setTimeout(function() {
                toggleTaxonomyGroup();
            }, 100);
        });
    }

})(jQuery);
