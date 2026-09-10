<?php

/**
 * Functions for managing multiple user roles in admin UI
 *
 * These functions are only needed in the admin area
 */

if (! defined('ABSPATH')) {
    exit;
}

// Only load in admin area for better performance
if (!is_admin()) {
    return;
}

/**
 * Add multiple roles UI to user edit screen
 */
function optipub_multiple_roles_ui($user)
{
    // Check if current user can edit users
    if (!current_user_can('edit_users')) {
        return;
    }

    // Get all available roles
    $roles = wp_roles()->roles;

    // Filter for OptiPub roles only (customize as needed)
    $optipub_roles = array_filter($roles, function ($key) {
        return strpos($key, 'optipub_') === 0;
    }, ARRAY_FILTER_USE_KEY);

    // Check if we're on the add new user form
    $is_new_user = !is_object($user) || !property_exists($user, 'ID') || empty($user->ID);

    // Get current user roles (empty array for new users)
    $user_roles = $is_new_user ? [] : $user->roles;

    ?>
    <h3>OptiPub Publication Access</h3>
    <table class="form-table">
        <tr>
            <th><label for="optipub_roles">Publication Access</label></th>
            <td>
                <?php foreach ($optipub_roles as $role_id => $role) { ?>
                    <label style="display: block; margin-bottom: 8px;">
                        <input type="checkbox" name="optipub_roles[]" value="<?php echo esc_attr($role_id); ?>"
                            <?php checked(in_array($role_id, $user_roles)); ?>>
                        <?php echo esc_html($role['name']); ?>
                    </label>
                <?php } ?>
                <p class="description">Select which publications this user can access.</p>
            </td>
        </tr>
    </table>
<?php

        // Add nonce for security
        wp_nonce_field('optipub_multiple_roles', 'optipub_multiple_roles_nonce');
}
add_action('user_new_form', 'optipub_multiple_roles_ui', 0);
add_action('show_user_profile', 'optipub_multiple_roles_ui', 0);
add_action('edit_user_profile', 'optipub_multiple_roles_ui', 0);

/**
 * Save multiple roles when user is updated
 */
function optipub_save_multiple_roles($user_id)
{
    // Check permissions
    if (!current_user_can('edit_users')) {
        return;
    }

    // Verify nonce
    $nonce = isset($_POST['optipub_multiple_roles_nonce']) ? sanitize_text_field(wp_unslash($_POST['optipub_multiple_roles_nonce'])) : '';

    if (!wp_verify_nonce($nonce, 'optipub_multiple_roles')) {
        return;
    }

    // Get the user object
    $user = get_userdata($user_id);

    // Get existing non-optipub roles
    $existing_wp_roles = [];

    foreach ($user->roles as $role) {
        if (strpos($role, 'optipub_') !== 0) {
            $existing_wp_roles[] = $role;
        }
    }

    // Get existing optipub roles that should be preserved
    $existing_optipub_roles = [];

    foreach ($user->roles as $role) {
        if (strpos($role, 'optipub_') === 0) {
            $existing_optipub_roles[] = $role;
        }
    }

    // Remove all roles
    $user->set_role('');

    // Re-add non-optipub roles
    foreach ($existing_wp_roles as $role) {
        $user->add_role($role);
    }

    // Add selected optipub roles from form
    $selected_roles = isset($_POST['optipub_roles']) ? array_map('sanitize_text_field', wp_unslash($_POST['optipub_roles'])) : [];

    if (!empty($selected_roles) && is_array($selected_roles)) {
        foreach ($selected_roles as $role) {
            $role = sanitize_text_field($role);

            // Only add optipub roles for security
            if (strpos($role, 'optipub_') === 0) {
                $user->add_role($role);
            }
        }
    }
    // If no optipub roles selected and this is a profile update
    // (not a new user registration), remove all optipub roles
    // This happens when all checkboxes are unchecked
}
add_action('personal_options_update', 'optipub_save_multiple_roles');
add_action('edit_user_profile_update', 'optipub_save_multiple_roles');

/**
 * Add multiple roles support to user creation
 */
function optipub_user_register_multiple_roles($user_id)
{
    // Only run during admin user creation
    $action = isset($_POST['action']) ? sanitize_text_field(wp_unslash($_POST['action'])) : '';

    if (!is_admin() || $action !== 'createuser') {
        return;
    }

    // Verify nonce for security
    $nonce = isset($_POST['optipub_multiple_roles_nonce']) ? sanitize_text_field(wp_unslash($_POST['optipub_multiple_roles_nonce'])) : '';

    if (!wp_verify_nonce($nonce, 'optipub_multiple_roles')) {
        return;
    }

    // Check for our custom roles array
    $selected_roles = isset($_POST['optipub_roles']) ? array_map('sanitize_text_field', wp_unslash($_POST['optipub_roles'])) : [];

    if (!empty($selected_roles) && is_array($selected_roles)) {
        $user = get_userdata($user_id);

        foreach ($selected_roles as $role) {
            // Only add optipub roles for security
            if (strpos($role, 'optipub_') === 0) {
                $user->add_role($role);
            }
        }
    }
}
add_action('user_register', 'optipub_user_register_multiple_roles');
