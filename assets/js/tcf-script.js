jQuery(document).ready(function($) {
    $('#telegram-contact-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $button = $form.find('.tcf-submit-btn');
        var $message = $form.find('.form-message');
        $message.hide().removeClass('success error');

        // Блокируем кнопку и показываем индикатор отправки
        $button.prop('disabled', true).addClass('sending').text('Sending...');

        var formData = {
            action: 'tcf_submit_form',
            nonce: tcfAjax.nonce,
            name: $form.find('#tcf-name').length ? $form.find('#tcf-name').val() : '',
            email: $form.find('#tcf-email').length ? $form.find('#tcf-email').val() : '',
            phone: $form.find('#tcf-phone').length ? $form.find('#tcf-phone').val() : '',
            telegram_username: $form.find('#tcf-telegram').length ? $form.find('#tcf-telegram').val() : '',
            message: $form.find('#tcf-message').length ? $form.find('#tcf-message').val() : '',
        };

        $.ajax({
            url: tcfAjax.ajax_url,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    $message.addClass('success').text(response.data).show();
                    $form[0].reset();
                } else {
                    $message.addClass('error').text(response.data).show();
                }
            },
            error: function() {
                $message.addClass('error').text('An error occurred. Please try again.').show();
            },
            complete: function() {
                // Разблокируем кнопку и убираем индикатор
                $button.prop('disabled', false).removeClass('sending').text('Send Request');
            }
        });
    });
});