<?php

namespace OptiPub\Base;

if (!defined('ABSPATH')) {
    exit;
}

class UserRoleManager extends BaseController
{
    public function register()
    {
        // Only hook UI functions in admin
        if (is_admin()) {
            add_action('user_new_form', [$this, 'multipleRolesUI'], 0);
            add_action('show_user_profile', [$this, 'multipleRolesUI'], 0);
            add_action('edit_user_profile', [$this, 'multipleRolesUI'], 0);
            add_action('personal_options_update', [$this, 'saveMultipleRoles']);
            add_action('edit_user_profile_update', [$this, 'saveMultipleRoles']);
            add_action('user_register', [$this, 'registerMultipleRoles']);
        }
    }

    /**
     * Create a user role for a publication if it doesn't exist
     *
     * @param string $pub_name Publication name for display
     * @param string $pub_id Publication ID for role name
     */
    public function createPublicationUserRole(string $pub_name, string $pub_id): void
    {
        $role_name = Constants::OPTIPUB_ROLE_PREFIX . sanitize_title($pub_id);
        $display_name = $pub_name;

        // Only create if role doesn't exist
        if (!get_role($role_name)) {
            add_role(
                $role_name,
                $display_name,
                [
                    'read'                                => true,
                    'read_optipub_publication_' . $pub_id => true,
                ]
            );
            $this->logStaticError("Created user role: {$display_name}");
        }
    }

    /**
     * Add multiple roles UI to user edit screen
     */
    public function multipleRolesUI($user)
    {
        // [Convert the function to a method - content similar to optipub_multiple_roles_ui]
    }

    /**
     * Save multiple roles when user is updated
     */
    public function saveMultipleRoles($user_id)
    {
        // [Convert the function to a method - content similar to optipub_save_multiple_roles]
    }

    /**
     * Add multiple roles support to user creation
     */
    public function registerMultipleRoles($user_id)
    {
        // [Convert the function to a method - content similar to optipub_user_register_multiple_roles]
    }
}
