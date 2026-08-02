<?php
/**
 * Debug ACF Conditional Logic
 * 
 * Access this via: yoursite.com/wp-content/themes/marg/debug-acf.php
 * 
 * This will help debug the conditional logic implementation
 */

// Load WordPress
require_once('../../../wp-load.php');

// Check if user is logged in and has admin capabilities
if (!is_user_logged_in() || !current_user_can('manage_options')) {
    wp_die('Access denied. You must be logged in as an administrator.');
}

// Get the current user
$current_user = wp_get_current_user();

?>
<!DOCTYPE html>
<html>
<head>
    <title>ACF Conditional Logic Debug</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .debug-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        .warning { color: orange; }
        pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>ACF Conditional Logic Debug</h1>
    
    <div class="debug-section">
        <h2>System Information</h2>
        <p><strong>WordPress Version:</strong> <?php echo get_bloginfo('version'); ?></p>
        <p><strong>Theme:</strong> <?php echo get_template(); ?></p>
        <p><strong>Current User:</strong> <?php echo $current_user->display_name; ?></p>
        <p><strong>ACF Version:</strong> <?php echo function_exists('acf_get_setting') ? acf_get_setting('version') : 'Unknown'; ?></p>
    </div>
    
    <div class="debug-section">
        <h2>ACF Field Groups Check</h2>
        <?php
        if (function_exists('acf_get_field_group')) {
            $type_group = acf_get_field_group('group_68ac2f305640f');
            $taxonomy_group = acf_get_field_group('group_68e48ee0308e8');
            
            if ($type_group) {
                echo '<p class="success">✓ Product Details field group exists</p>';
                echo '<pre>' . print_r($type_group, true) . '</pre>';
            } else {
                echo '<p class="error">✗ Product Details field group not found</p>';
            }
            
            if ($taxonomy_group) {
                echo '<p class="success">✓ Taxonomy field group exists</p>';
                echo '<pre>' . print_r($taxonomy_group, true) . '</pre>';
            } else {
                echo '<p class="error">✗ Taxonomy field group not found</p>';
            }
        } else {
            echo '<p class="error">✗ ACF functions not available</p>';
        }
        ?>
    </div>
    
    <div class="debug-section">
        <h2>Product Categories</h2>
        <?php
        $product_categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
        ));
        
        if (!is_wp_error($product_categories) && !empty($product_categories)) {
            echo '<p class="success">✓ Product categories found:</p>';
            echo '<ul>';
            foreach ($product_categories as $category) {
                echo '<li><strong>' . esc_html($category->name) . '</strong> (ID: ' . $category->term_id . ', Slug: ' . esc_html($category->slug) . ')</li>';
            }
            echo '</ul>';
        } else {
            echo '<p class="error">✗ No product categories found</p>';
        }
        ?>
    </div>
    
    <div class="debug-section">
        <h2>File Check</h2>
        <?php
        $files_to_check = array(
            'ACFConditionalLogic.php' => get_template_directory() . '/src/ACFConditionalLogic.php',
            'acf-conditional-taxonomy.js' => get_template_directory() . '/assets/js/admin/acf-conditional-taxonomy.js',
            'acf-conditional-taxonomy-simple.js' => get_template_directory() . '/assets/js/admin/acf-conditional-taxonomy-simple.js',
        );
        
        foreach ($files_to_check as $name => $path) {
            if (file_exists($path)) {
                echo '<p class="success">✓ ' . $name . ' exists</p>';
                echo '<p class="info">Size: ' . filesize($path) . ' bytes</p>';
            } else {
                echo '<p class="error">✗ ' . $name . ' not found</p>';
            }
        }
        ?>
    </div>
    
    <div class="debug-section">
        <h2>Quick Test Links</h2>
        <p><a href="<?php echo admin_url('post-new.php?post_type=product'); ?>" target="_blank">Create New Product</a></p>
        <p><a href="<?php echo admin_url('edit.php?post_type=product'); ?>" target="_blank">Edit Products</a></p>
    </div>
    
    <div class="debug-section">
        <h2>Instructions</h2>
        <ol>
            <li>Click "Create New Product" above</li>
            <li>Open browser console (F12)</li>
            <li>Look for debug messages starting with "Simple ACF Conditional Logic"</li>
            <li>Try changing the Type field and see if the Taxonomy group shows/hides</li>
        </ol>
    </div>
    
    <script>
        console.log('Debug page loaded');
        console.log('jQuery version:', typeof $ !== 'undefined' ? $.fn.jquery : 'jQuery not loaded');
    </script>
</body>
</html>

