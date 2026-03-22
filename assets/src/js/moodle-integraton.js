jQuery(document).ready(function ($) {


    // Handle sync button click
    $(document).on('click', 'td.moodle_user_id .sync-moodle-user', function (e) {
        e.preventDefault();

        var $button = $(this);
        var $row = $button.closest('tr');
        var userId = $button.data('user-id');
        var nonce = $button.data('nonce') || '';

        // Add loading state
        $button.prop('disabled', true).find('.dashicons').addClass('spin');

        $.ajax({
            url: helperboxMoodleJs.ajaxurl,
            type: 'POST',
            data: {
                action: 'helperbox_sync_moodle_user',
                user_id: userId,
                nonce: nonce
            },
            success: function (response) {
                if (response.success) {
                    // Update column content
                    $row.find('.column-moodle_user_id').html(response.data.html);

                    // // Show success notice
                    // var notice = '<div class="notice notice-success is-dismissible" style="margin: 10px 0;"><p>' +
                    //     response.data.message + '</p></div>';
                    // $row.before(notice);
                } else {
                    // Show error
                    alert(response.message || 'Sync failed. Please try again.');
                    $button.prop('disabled', false).find('.dashicons').removeClass('spin');
                }
            },
            error: function (response) {
                console.log(response);
                alert('An error occurred. Please try again.');
                $button.prop('disabled', false).find('.dashicons').removeClass('spin');
            }
        });
    });

    // Handle dismissible notices
    $(document).on('click', '.notice.is-dismissible .notice-dismiss', function () {
        $(this).closest('.notice').fadeOut();
    });
});