<?php

/**
 * The template for displaying login page
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#single-post
 *
 * @package optipub
 */

if (! defined('ABSPATH')) {
    exit;
}

// Set action at the very top
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading URL parameter for display logic, not processing form data
$action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : 'login';

// Check if user is logged in and handle reset password parameters
if (is_user_logged_in()) {
    $user = wp_get_current_user();

    // If we're on the lost password page, redirect to reset password
    if (in_array($action, ['lostpassword', 'forgot-password'])) {
        // Generate a new reset key
        $key = get_password_reset_key($user);

        if (!is_wp_error($key)) {
            // Redirect to the same page but with reset password action
            $redirect_url = add_query_arg([
                'action' => 'rp',
                'key'    => $key,
                'login'  => rawurlencode($user->user_login),
            ], wp_get_referer());

            // Use wp_safe_redirect instead of wp_redirect
            wp_safe_redirect($redirect_url);
            exit;
        }
    }
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading URL parameter for display logic, not processing form data
$action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : 'login';
$post_type = get_post_type($post->ID);
$logged_in_user = false;

// For logged-in users, we need to generate a new key first
if (is_user_logged_in()) {
    $logged_in_user = true;

    $first_login = get_user_meta($user->ID, 'optipub_first_login', true);
    $user = wp_get_current_user();
    $key = get_password_reset_key($user);
    //update_user_meta($user->ID, 'user_activation_key', $key);

    if ($first_login) {
        $_GET['action'] = 'rp';
    }

    if (!is_wp_error($key)) {
        // Update the key in the URL to match the newly generated one
        $_GET['key'] = wp_unslash($key);
        $_GET['login'] = rawurlencode($user->user_login);
    } else {
        $action = 'login';
        $error_message = __('Error generating password reset key. Please try again.', 'optipub');
    }
} else {
    if (in_array($action, ['rp', 'reset-password', 'change-password'])) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checking WordPress password reset parameters
        if (!isset($_GET['key']) || !isset($_GET['login'])) {
            // Missing required parameters
            $action = 'login';
            $error_message = __('Invalid password reset link. Please request a new one.', 'optipub');
        } else {
            // For non-logged-in users, verify the key
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Using WordPress's check_password_reset_key for validation
            $user = check_password_reset_key(sanitize_text_field(wp_unslash($_GET['key'])), sanitize_text_field(wp_unslash($_GET['login'])));

            if (is_wp_error($user)) {
                // Invalid or expired key
                $action = 'login';
                $error_message = __('Invalid or expired password reset link. Please request a new one.', 'optipub');
            }
        }
    }
}

$sidebar_show_hide = get_post_meta($post->ID, 'sidebar_show_hide', true);

if (!empty($sidebar_show_hide) && $sidebar_show_hide == 1) {
    $col_class = 'col-xl-8 ';
} else {
    $col_class = '';
}

get_header();

wp_enqueue_script('jquery');
wp_enqueue_script(
    'optipub-login-forms',
    OPTIPUB_PLUGIN_URL . 'public/js/login-forms.js',
    ['jquery'],
    '1.0.0',
    true
);

wp_localize_script('optipub-login-forms', 'optipubLogin', [
    'ajaxurl' => admin_url('admin-ajax.php')
]);

?>

<section id="page-content">
	<div class="container">
		<div class="row">
			<?php
            while (have_posts()) {
                the_post();
                ?>
				<div class="container my-2 my-md-4" style="background-color: #f8f9fa; padding: 2rem; border-radius: 8px;">
					<?php
                ?>
					<?php
                // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading URL parameter for error display
                $preauth_error = isset($_GET['preauth_error']);
                ?>
					<div id="login_error" class="login-error" style="<?php echo isset($error_message) || $preauth_error ? 'display: block;' : 'display: none;'; ?> margin-bottom: 1rem; border-radius: 4px; padding: 1rem; <?php echo isset($error_message) ? 'color: #dc3545; background-color: #fff5f5; border: 1px solid #dc3545;' : 'color: #28a745; background-color: #f0fff4; border: 1px solid #28a745;'; ?>">
						<?php if (isset($error_message)) { ?>
							<p><?php echo esc_html($error_message); ?></p>
						<?php } ?>
						<?php if ($preauth_error) { ?>
							<p><?php echo esc_html__('Preauth code expired or invalid', 'optipub'); ?></p>
						<?php } ?>
					</div>

					<?php
                switch ($action) {
                    case 'lostpassword':
                    case 'forgot-password':
                        ?>
							<h1><?php esc_html_e('Forgot Password?', 'optipub'); ?></h1>
							<p><?php esc_html_e('Please enter your email address. You will receive a link to create a new password via email.', 'optipub'); ?></p>
							<form id="lostpasswordform" method="post">
								<p class="form-row">
									<label for="user_login"><?php esc_html_e('Email Address', 'optipub'); ?></label>
									<input type="email" name="user_login" id="user_login" class="input" required>
								</p>
								<?php wp_nonce_field('ajax-lostpassword-nonce', 'security'); ?>
								<?php
                                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading standard WordPress redirect parameter
                                    $redirect_to = isset($_GET['redirect_to']) ? sanitize_text_field(wp_unslash($_GET['redirect_to'])) : '';
                        ?>
								<input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirect_to); ?>">
								<p class="submit">
									<input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="<?php esc_attr_e('Get New Password', 'optipub'); ?>">
								</p>
							</form>
							<p id="nav">
								<a href="<?php echo esc_url(wp_login_url($redirect_to)); ?>"><?php esc_html_e('← Back to login', 'optipub'); ?></a>
							</p>
							<?php
                            break;
                    case 'rp':
                    case 'change-password':
                    case 'reset-password':
                        // Only show reset password form if we have valid key and user
                        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checking WordPress password reset parameters
                        if (isset($_GET['key']) && isset($_GET['login'])) {
                            ?>
								<h1><?php $first_login ? esc_html_e('Set Initial Password', 'optipub') : esc_html_e('Change Password', 'optipub'); ?></h1>
								<form id="resetpassform" method="post" autocomplete="off">
									<input type="hidden" id="user_pass" name="rp_pass" autocomplete="new-password">
									<?php
                                    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Using WordPress password reset parameters
                                    $reset_key = sanitize_text_field(wp_unslash($_GET['key']));
                            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Using WordPress password reset parameters
                            $reset_login = sanitize_text_field(wp_unslash($_GET['login']));
                            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading standard WordPress redirect parameter
                            $reset_redirect_to = isset($_GET['redirect_to']) ? sanitize_text_field(wp_unslash($_GET['redirect_to'])) : '';
                            ?>
									<input type="hidden" name="key" value="<?php echo esc_attr($reset_key); ?>">
									<input type="hidden" name="login" value="<?php echo esc_attr($reset_login); ?>">
									<input type="hidden" name="redirect_to" value="<?php echo esc_attr($reset_redirect_to); ?>">
									<?php wp_nonce_field('ajax-resetpassword-nonce', 'security'); ?>

									<p class="form-row">
										<label for="pass1"><?php esc_html_e('New password', 'optipub'); ?></label>
										<input type="password" name="pass1" id="pass1" class="input" size="20" value="" autocomplete="new-password" required>
									</p>
									<p class="form-row">
										<label for="pass2"><?php esc_html_e('Confirm new password', 'optipub'); ?></label>
										<input type="password" name="pass2" id="pass2" class="input" size="20" value="" autocomplete="new-password" required>
									</p>
									<p class="submit">
										<input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="<?php esc_attr_e('Reset Password', 'optipub'); ?>">
									</p>
								</form>
								<p id="nav">
									<a href="<?php echo esc_url(wp_login_url($reset_redirect_to)); ?>"><?php esc_html_e('← Back to login', 'optipub'); ?></a>
								</p>
							<?php
                        }
                        break;
                    default:
                        ?>
							<h1><?php esc_html_e('Login', 'optipub'); ?></h1>
							<?php
                        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Reading standard WordPress redirect parameter
                        $login_redirect_to = isset($_GET['redirect_to']) ? sanitize_text_field(wp_unslash($_GET['redirect_to'])) : '';
                        $current_url = (is_ssl() ? 'https://' : 'http://') . (isset($_SERVER['HTTP_HOST']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_HOST'])) : '') . (isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '');
                        $args = [
                            'echo'           => true,
                            'redirect'       => !empty($login_redirect_to) ? $login_redirect_to : $current_url,
                            'form_id'        => 'loginform',
                            'label_username' => esc_html__('Email Address', 'optipub'),
                            'label_password' => esc_html__('Password', 'optipub'),
                            'label_remember' => esc_html__('Remember Me', 'optipub'),
                            'label_log_in'   => esc_html__('Log In', 'optipub'),
                            'id_username'    => 'user_login',
                            'id_password'    => 'user_pass',
                            'id_remember'    => 'rememberme',
                            'id_submit'      => 'wp-submit',
                            'remember'       => true,
                            'value_username' => '',
                            'value_remember' => false,
                        ];

                        wp_login_form($args);
                        wp_nonce_field('ajax-login-nonce', 'security');
                        ?>
							<p id="nav">
								<a href="<?php echo esc_url(wp_lostpassword_url($login_redirect_to)); ?>"><?php esc_html_e('Lost your password?', 'optipub'); ?></a>
							</p>
					<?php
                        break;
                }
                ?>
				</div>

			<?php } // End of the loop.

if (!empty($sidebar_show_hide) && $sidebar_show_hide == 1) { ?>
				<div class="col-xl-4 col-12 pl-xl-5 my-4">
					<?php get_template_part('inc/optipub/template-parts/sidebars/sidebar', 'single'); ?>
				</div>
			<?php } ?>
		</div>
	</div>
</section>
<?php
// get_sidebar();
get_footer();
