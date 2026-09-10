jQuery(document).ready(function ($) {
  // Login form handler
  $('#loginform').on('submit', function (e) {
    e.preventDefault();

    var $form = $(this);
    var $submitButton = $form.find('#wp-submit');
    var $errorDiv = $('#login_error');

    $submitButton.prop('disabled', true);
    $submitButton.val('Logging in...');
    $errorDiv.hide();

    $.ajax({
      type: 'POST',
      dataType: 'json',
      url: optipubLogin.ajaxurl,
      data: {
        action: 'optipub_ajax_login',
        username: $('#user_login').val(),
        password: $('#user_pass').val(),
        security: $('#security').val(),
        redirect_to: $('input[name="redirect_to"]').val(),
        rememberme: $('#rememberme').is(':checked') ? 1 : 0
      },
      success: function (response) {
        console.log('Success response:', response);
        if (response.success) {
          window.location.href = response.data.redirect;
        } else {
          $errorDiv.css({
            'color': '#28a745',
            'background-color': '#f0fff4',
            'border': '1px solid #28a745'
          });
          $errorDiv.html('<p class="mb-2">' + response.data.message + '</p>');
          $errorDiv.show();
          $submitButton.prop('disabled', false);
          $submitButton.val('Log In');
        }
      },
      error: function (xhr, status, error) {
        console.log('Error response:', xhr.responseText);
        var message = 'An error occurred. Please try again.';
        try {
          var response = JSON.parse(xhr.responseText);
          if (response.data && response.data.message) {
            message = response.data.message;
          }
        } catch (e) {
          console.error('Error parsing response:', e);
        }
        $errorDiv.css({
          'color': '#dc3545',
          'background-color': '#fff5f5',
          'border': '1px solid #dc3545'
        });
        $errorDiv.html('<p class="mb-2">' + message + '</p>');
        $errorDiv.show();
        $submitButton.prop('disabled', false);
        $submitButton.val('Log In');
      }
    });
  });

  // Lost password form handler
  $('#lostpasswordform').on('submit', function (e) {
    e.preventDefault();

    var $form = $(this);
    var $submitButton = $form.find('#wp-submit');
    var $errorDiv = $('#login_error');

    $submitButton.prop('disabled', true);
    $submitButton.val('Sending...');
    $errorDiv.hide();

    $.ajax({
      type: 'POST',
      dataType: 'json',
      url: optipubLogin.ajaxurl,
      data: {
        action: 'optipub_ajax_lostpassword',
        user_login: $('#user_login').val(),
        security: $('#security').val(),
        redirect_to: $('input[name="redirect_to"]').val()
      },
      success: function (response) {
        console.log('Success response:', response);
        var message = response.data.message;
        $errorDiv.css({
          'color': '#28a745',
          'background-color': '#f0fff4',
          'border': '1px solid #28a745'
        });
        $errorDiv.html('<p class="mb-0">' + message + '</p>');
        $errorDiv.show();
        $submitButton.prop('disabled', false);
        $submitButton.val('Get New Password');
      },
      error: function (xhr, status, error) {
        console.log('Error response:', xhr.responseText);
        var message = 'An error occurred. Please try again.';
        try {
          var response = JSON.parse(xhr.responseText);
          if (response.data && response.data.message) {
            message = response.data.message;
          }
        } catch (e) {
          console.error('Error parsing response:', e);
        }
        $errorDiv.css({
          'color': '#dc3545',
          'background-color': '#fff5f5',
          'border': '1px solid #dc3545'
        });
        $errorDiv.html('<p class="mb-0">' + message + '</p>');
        $errorDiv.show();
        $submitButton.prop('disabled', false);
        $submitButton.val('Get New Password');
      }
    });
  });

  // Reset password form handler
  $('#resetpassform').on('submit', function (e) {
    e.preventDefault();

    var $form = $(this);
    var $submitButton = $form.find('#wp-submit');
    var $errorDiv = $('#login_error');

    if ($('#pass1').val() !== $('#pass2').val()) {
      $errorDiv.css({
        'color': '#dc3545',
        'background-color': '#fff5f5',
        'border': '1px solid #dc3545'
      });
      $errorDiv.html('<p class="mb-2">The passwords do not match.</p>');
      $errorDiv.show();
      return;
    }

    $submitButton.prop('disabled', true);
    $submitButton.val('Resetting...');
    $errorDiv.hide();

    $.ajax({
      type: 'POST',
      dataType: 'json',
      url: optipubLogin.ajaxurl,
      data: {
        action: 'optipub_ajax_resetpassword',
        new_password: $('#pass1').val(),
        confirm_password: $('#pass2').val(),
        key: $('input[name="key"]').val(),
        login: $('input[name="login"]').val(),
        security: $('#security').val(),
        redirect_to: $('input[name="redirect_to"]').val()
      },
      success: function (response) {
        console.log('Success response:', response);
        if (response.success) {
          window.location.href = response.data.redirect;
        } else {
          $errorDiv.css({
            'color': '#28a745',
            'background-color': '#f0fff4',
            'border': '1px solid #28a745'
          });
          $errorDiv.html('<p class="mb-2">' + response.data.message + '</p>');
          $errorDiv.show();
          $submitButton.prop('disabled', false);
          $submitButton.val('Reset Password');
        }
      },
      error: function (xhr, status, error) {
        console.log('Error response:', xhr.responseText);
        var message = 'An error occurred. Please try again.';
        try {
          var response = JSON.parse(xhr.responseText);
          if (response.data && response.data.message) {
            message = response.data.message;
          }
        } catch (e) {
          console.error('Error parsing response:', e);
        }
        $errorDiv.css({
          'color': '#dc3545',
          'background-color': '#fff5f5',
          'border': '1px solid #dc3545'
        });
        $errorDiv.html('<p class="mb-2">' + message + '</p>');
        $errorDiv.show();
        $submitButton.prop('disabled', false);
        $submitButton.val('Reset Password');
      }
    });
  });
});
