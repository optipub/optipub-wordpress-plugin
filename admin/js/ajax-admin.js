/**
 * OptiPub Admin AJAX functionality
 */
(function($) {
    'use strict';

    // Main initialization
    const OptiPubAdmin = {
        init: function() {
            this.initProgressCheck();
            this.initEventListeners();
        },

        // Progress check functionality
        initProgressCheck: function() {
            this.checkProgress();
            window.setInterval(() => this.checkProgress(), 2000);
        },

        checkProgress: function() {
            $.post(ajaxurl, {
                nonce: optipub_ajax_admin.nonce,
                action: 'progress_hook'
            }, function(data) {
                $('.pre-response').html(data);
            });
        },

        // Event listeners initialization
        initEventListeners: function() {
            this.initTooltips();
            this.initWidgetHandlers();
            this.initAccordionHandlers();
            this.initModalHandlers();
            this.initThemeChangeHandler();
            this.initTokenEditHandler();
            this.initWidgetFormHandlers();
            this.initSyncHandlers();
            this.initCopyHandlers();
            this.initAuxiliaryHandlers();
            this.initPublicationHandlers();
        },

        // Tooltip functionality
        initTooltips: function() {
            $('.dashicons').hover(function() {
                const tooltipText = $(this).attr('data-tooltip').replace('|', '\n');
                $(this).attr('data-tooltip', tooltipText);
            });
        },

        // Widget handlers
        initWidgetHandlers: function() {
            // Bulk action confirmation
            $('#widgets-content #doaction').click(function(e) {
                if ($('#bulk-action-selector-top').val() === 'delete' && 
                    !confirm('Are you sure you want to delete these items?')) {
                    e.preventDefault();
                }
            });

            // Widget edit and view handlers
            $('.widget-edit').on('click', function() {
                const itemId = $(this).data('itemid');
                OptiPubAdmin.editWidgetContent(itemId, false);
            });

            $('.widget-view').on('click', function() {
                const itemId = $(this).data('itemid');
                OptiPubAdmin.viewWidgetContent(itemId);
            });

            // Widget export handler
            $('#export-widgets').on('click', function(e) {
                e.preventDefault();
                OptiPubAdmin.exportWidgets();
            });
        },

        // Widget content management
        editWidgetContent: function(itemId, callback) {
            $.post(ajaxurl, {
                nonce: optipub_ajax_admin.nonce,
                action: 'widgets_edit',
                itemId: itemId
            }, function(response) {
                $('#optipubModalBody').html(response);
                $('#optipubModal').css('display', 'flex');
                $('#widgets-content').css('display', 'block');

                if (typeof callback === "function") {
                    callback(response);
                }
            });
        },

        viewWidgetContent: function(itemId) {
            $.post(ajaxurl, {
                nonce: optipub_ajax_admin.nonce,
                action: 'widgets_view',
                itemId: itemId
            }, function(response) {
                $('#optipubModalBody').html(response);
                $('#optipubModal').css('display', 'flex');
                $('#widget-view-options').css('display', 'inline-block');
                $('#widget-option-display').text('(Source)');
            });
        },

        exportWidgets: function() {
            $.post(ajaxurl, {
                nonce: optipub_ajax_admin.nonce,
                action: 'widgets_export'
            }, function(data) {
                $('#optipubModalBody').html(data);
                $('#optipubModal').css('display', 'flex');
            });
        },

        // Accordion handlers
        initAccordionHandlers: function() {
            $('.accordion-container .panel a').click(function(e) {
                e.preventDefault();
            });

            $('.accordion').on('click', function() {
                $(this).next('.panel').slideToggle();
            });

            // Add these handlers for details dropdowns
            $('[data-toggle-target]').on('click', function(e) {
                e.preventDefault();
                const target = $(this).data('toggle-target');
                $(target).slideToggle();
            });

            /*

            // For file details toggles specifically
            $('.file-details-toggle').on('click', function(e) {
                e.preventDefault();
                const target = $(this).data('toggle-target');
                $(target).slideToggle();
                
                // Toggle the arrow icon
                const arrow = $(this).parent().find('.dashicons-arrow-down');
                arrow.css('transform', $(target).is(':visible') ? 'rotate(180deg)' : 'rotate(0deg)');
            });

            if (localStorage.getItem('showCopyContent') === 'true') {
                $('#copy-content').css('display', 'block');
                localStorage.removeItem('showCopyContent');
            }
            */

        },

        // Modal handlers
        initModalHandlers: function() {
            const closeModal = function() {
                $('.optipub-modal').hide();
                $('.optipub-close-sp').html('');
                $('#widget-view-options').hide();
            };

            $('.optipub-close').on('click', closeModal);
            $('#optipubModal .optipub-close').on('click', function() {
                $('#widgets-content').css('display', 'block');
            });

            $(document).on('keyup', function(event) {
                if (event.key === "Escape") {
                    closeModal();
                }
            });

            $(document).on('click', function(event) {
                if ($('#optipubModal').is(':visible') && 
                    !$(event.target).closest('.optipub-modal-content').length) {
                    closeModal();
                }
            });
        },

        // Theme change handler
        initThemeChangeHandler: function() {
            $('#closeThemeChanged').click(function() {
                $('#copy-content').show();
                if ($('#ignore-theme-change').is(':checked')) {
                    const formData = {
                        'ignore-theme-change': true
                    };
                    $.post(ajaxurl, {
                        ...formData,
                        nonce: optipub_ajax_admin.nonce,
                        action: 'ignore_theme_change'
                    }, function() {
                        localStorage.setItem('showCopyContent', 'true');
                        location.reload(true);
                    });
                }
            });
        },

        // Token edit handler
        initTokenEditHandler: function() {
            $('#editToken').on('click', function(e) {
                e.preventDefault();
                
                /*
                // Toggle readonly/disabled attribute on all inputs and selects in the form
                $("#save-changes input, #save-changes select").each(function() {
                    if ($(this).attr('readonly')) {
                        $(this).removeAttr('readonly');
                        $(this).removeAttr('disabled');
                    } else {
                        $(this).attr('readonly', 'readonly');
                        $(this).attr('disabled', 'disabled');
                    }
                });
                */
               
                // Toggle button text
                $(this).text($(this).text() === 'Edit' ? 'Finish' : 'Edit');
            });
        },

        // Widget form handlers
        initWidgetFormHandlers: function() {
            this.initWidgetFormValidation();
            this.initWidgetFormSubmission();
            this.initWidgetFormAutoFill();
        },

        // Widget form validation
        initWidgetFormValidation: function() {
            $(document).on('change', '.widget_validate, .element_validate', function() {
                validateInput($(this));
            });

            function validateInput(input) {
                // Remove existing error messages
                input.nextUntil(':not(.widget-error)').addBack().filter('.widget-error').remove();

                const value = input.val().trim();
                const errorPrefix = '<span class="widget-error error-message"></span>' +
                                  '<span class="widget-error error-message"></span>' +
                                  '<span class="widget-error error-message">';

                // Required field check
                if (value === '' && input.hasClass('widget_required')) {
                    input.after(errorPrefix + 'Invalid input: This field is required.</span>');
                    return false;
                }

                if (value === '') {
                    return true;
                }

                // Validation patterns
                const patterns = {
                    'widget_sidebar': /^[a-z0-9-_]+$/,
                    'widget_classname': /^[a-z0-9-_]+$/,
                    'widget_custom_content': /^\{\$custom_[A-Za-z0-9-_]+\}$/,
                    'widget_filename': /^[a-z0-9-_.]+\.php$/
                };

                const errorMessages = {
                    'widget_sidebar': 'Invalid input: [a-z0-9-_] characters allowed.',
                    'widget_classname': 'Invalid input: [a-z0-9-_] characters allowed.',
                    'widget_custom_content': 'Invalid input: macros start with "$custom_" - [A-Za-z0-9-_] characters allowed.',
                    'widget_filename': 'Invalid input: [a-z0-9-_.] characters allowed - must be a .php extension.'
                };

                const inputId = input.attr('id');
                if (patterns[inputId] && !patterns[inputId].test(value)) {
                    input.after(errorPrefix + errorMessages[inputId] + '</span>');
                    return false;
                }

                // Textarea validation
                if (input.hasClass('element_validate')) {
                    const textareaContent = value;
                    const classnameVal = $('#widget_classname').val();
                    
                    if (textareaContent.includes('%2$s') && classnameVal === '') {
                        input.after(errorPrefix + 'Invalid textarea: %2$s is invalid without a Classname.</span>');
                        return false;
                    }
                }

                return true;
            }

            window.validateAllInputs = function() {
                let isValid = true;
                $('#save-widgets input, #save-widgets textarea').each(function() {
                    if (!validateInput($(this))) {
                        isValid = false;
                    }
                });
                return isValid;
            };
        },

        // Widget form submission
        initWidgetFormSubmission: function() {
            $(document).on('submit', '#save-widgets', function(event) {
                event.preventDefault();

                // Remove existing error messages
                $('#submit-widgets').nextUntil(':not(.widget-error)').addBack().filter('.widget-error').remove();

                if (!window.validateAllInputs()) {
                    const errorPrefix = '<span class="widget-error error-message"></span>' +
                                     '<span class="widget-error error-message">';
                    $('#submit-widgets').after(errorPrefix + 'Cannot Save Widget: There is an issue with one of the fields.</span>');
                    return;
                }

                if (confirm('Are you sure you want to create this widget?')) {

                    const formData = $(this).serialize();
                    const combinedData = formData + '&' + $.param({
                        nonce: optipub_ajax_admin.nonce,
                        action: 'widgets_save'
                    });

                    $.post(ajaxurl, combinedData, function(response) {
                        localStorage.setItem('widgetSuccessMessage', response + ' widget saved successfully');
                        localStorage.setItem('showWidgetModal', 'true');
                        location.reload(true);
                    });
                }
            });
        },

        // Widget form auto-fill
        initWidgetFormAutoFill: function() {
            // Copy Title value to Description placeholder
            $(document).on('change', '#widget_title', function() {
                $('#widget_description').attr('placeholder', $(this).val());
            });

            // Copy Sidebar ID value to other placeholders
            $(document).on('change', '#widget_sidebar', function() {
                const sidebarVal = $(this).val();
                const filename = sidebarVal.replace(/-/g, '_') + '.php';
                
                $('#widget_filename').attr('placeholder', filename);
                $('#widget_classname').attr('placeholder', sidebarVal);
            });

            // Auto-fill placeholder values on label click
            $(document).on('click', '#save-widgets label', function() {
                const forId = $(this).attr('for');
                const element = $('#' + forId);
                
                if ((element.is('input') || element.is('textarea')) && element.val() === '') {
                    const placeholder = element.attr('placeholder');
                    if (placeholder) {
                        element.val(placeholder.replace('default: ', ''));
                        validateInput(element);
                    }
                }
            });
        },

        // Sync handlers
        initSyncHandlers: function() {
            // Re-Sync All
            $('#resync-all').on('click', function(event) {
                event.preventDefault();
                if (confirm("Resyncing will update all OptiPub resources. This may take a while.\n\nDo you want to continue?")) {
                    $.post(ajaxurl, {
                        nonce: optipub_ajax_admin.nonce,
                        action: 'resync_all'
                    }, function(data) {
                        alert("OptiPub Sync Has Run: " + data);
                    });
                }
            });

            // Save Changes submit
            $('#save-changes').on('submit', function(event) {
                event.preventDefault();
                
                // Determine which button was clicked
                const clearPressed = $(document.activeElement).attr('name') === 'submit_clear';
                
                // If clear button pressed, show confirmation
                if (clearPressed) {
                    if (!confirm('Are you sure you want to clear all content?')) {
                        return;
                    }
                }

                const formData = $(this).serialize();
                const publication_id = $('#optipub_publication_id').val();
                const publication_title = $('#optipub_publication_id option:selected').text();
                
                let pubId = publication_id;
                let pubTitle = publication_title;
                if (publication_id !== undefined && 
                    publication_id !== null && 
                    publication_id.includes('select-publication')) {
                    pubId = '';
                    pubTitle = '';
                }

                const combinedData = formData + '&' + $.param({
                    nonce: optipub_ajax_admin.nonce,
                    action: clearPressed ? 'clearing_hook' : 'save_changes',
                    optipub_publication_name: pubTitle,
                    // Include these parameters only if the clear button was pressed
                    ...(clearPressed ? {
                        pause_after_clear: $('#pause_after_clear').is(':checked') ? '1' : '0',
                        clear_publication_terms: $('#clear_publication_terms').is(':checked') ? '1' : '0'
                    } : {})
                });

                $.post(ajaxurl, combinedData, function(data) {
                    $('.pre-response').html(data);
                    location.reload(true);
                });
            });
        },

        // Copy handlers
        initCopyHandlers: function() {
            // Copy files to theme
            $('#copy-submit').on('submit', function(event) {
                event.preventDefault();
                if (confirm('Are you sure you want to copy the files to your theme?')) {
                    const formData = {
                        'page-templates': $('input[name=page-templates]').is(':checked'),
                        'template-parts': $('input[name=template-parts]').is(':checked'),
                        'widget-templates': $('input[name=widget-templates]').is(':checked'),
                        'helper-functions': $('input[name=helper-functions]').is(':checked'),
                        'copy-previous-theme': $('input[name=copy-previous-theme]').is(':checked'),
                        'src': $('#plugin-directory').val(),
                        'dst': $('#theme-directory').val(),
                    };

                    if ($('input[name=copy-previous-theme]').is(':checked')) {
                        formData['og_src'] = $('#previous-theme-directory').val();
                    }

                    const combinedData = {
                        ...formData,
                        nonce: optipub_ajax_admin.nonce,
                        action: 'copy_files'
                    };

                    $.post(ajaxurl, $.param(combinedData), function(data) {
                        $('#copy-progress').html(data);
                        localStorage.setItem('showCopyContent', 'true');
                        location.reload();
                    });
                }
            });

            // Clear copy files logs
            $('#copy-clear').on('click', function(event) {
                event.preventDefault();
                if (confirm('Are you sure you want to clear the log?')) {
                    $.post(ajaxurl, {
                        nonce: optipub_ajax_admin.nonce,
                        action: 'copy_clear'
                    }, function() {
                        localStorage.setItem('showCopyContent', 'true');
                        location.reload(true);
                    });
                }
            });
        },

        // Auxiliary handlers
        initAuxiliaryHandlers: function() {
            // Clear log button
            $('#optipub-clear-log').on('click', function() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'clear_log',
                        nonce: optipub_ajax_admin.nonce
                    },
                    success: function(response) {
                        $('#progress-log-content').html('');
                    }
                });
            });

            // Force sync button
            $('#optipub-force-sync').on('click', function() {
                $(this).prop('disabled', true);
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'trigger_sync',
                        nonce: optipub_ajax_admin.nonce
                    },
                    success: function(response) {},
                    complete: function() {
                        setTimeout(function() {
                            $('#optipub-force-sync').prop('disabled', false);
                        }, 5000);
                    }
                });
            });
        },

        // Publication selection handlers
        initPublicationHandlers: function() {
            // Handle primary publication change
            $('#optipub_publication_id').on('change', function() {
                const selectedId = $(this).val();
                
                // Remove primary publication from selector
                $('#publication_selector option').show();
                $('#publication_selector option[value="' + selectedId + '"]').hide();
            });

            // Handle adding a publication
            $('#publication_selector').on('change', function() {
                const $selected = $(this).find('option:selected');
                if (!$selected.val()) return;

                const id = $selected.val();
                const name = $selected.data('name');

                // Add to selected publications
                $('.selected-publications').append(`
                    <div class="selected-publication" data-id="${id}">
                        <span class="publication-name">${name}</span>
                        <button type="button" class="remove-publication">×</button>
                        <input type="hidden" name="optipub_additional_publications[]" value="${id}">
                    </div>
                `);

                // Remove from selector
                $selected.remove();
                
                // Reset selector
                $(this).val('');
            });

            // Handle removing a publication
            $(document).on('click', '.remove-publication', function() {
                const $pub = $(this).closest('.selected-publication');
                const id = $pub.data('id');
                const name = $pub.find('.publication-name').text();

                // Show confirmation dialog
                if (!confirm(`Are you sure you want to remove "${name}"?\n\nThis will remove all content associated with this publication when you save changes.`)) {
                    return;
                }

                // Add back to selector
                $('#publication_selector').append(`
                    <option value="${id}" data-name="${name}">${name}</option>
                `);

                // Remove from selected
                $pub.remove();
            });
        }
    };

    // Add this at the bottom to initialize when document is ready
    $(document).ready(function() {
        OptiPubAdmin.init();
    });

})(jQuery);
