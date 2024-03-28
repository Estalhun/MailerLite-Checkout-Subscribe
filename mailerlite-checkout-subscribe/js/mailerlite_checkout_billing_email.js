jQuery(document).ready(function ($) {
    $(window).on("load", function () {
        var $termsCheckbox = $("input[name=terms]"); //#terms input[name=terms]
        var $mailerLiteCheckbox = $("input[name=woo_ml_subscribe]"); //#woo_ml_subscribe
        var $billing_email = $("input[name=billing_email]");
        var bill_email = $billing_email.length ? $billing_email.val().toUpperCase() : "";
        var $checkout_form = $("form[name=checkout]");
        var wp_registered_email = "";

        $.post(mlcs_mailerlite_checkout_billing_email_script.ajax_url,
            {
                action: "mlcs_email_check",
                nonce: mlcs_mailerlite_checkout_billing_email_script.nonce,
            },
            function (response) {
                if ('success' in response && response.success) {
                    if (response.data.subscribed) {
                        wp_registered_email = decodeURIComponent(response.data.email.toUpperCase());
                    }
                }
            }
        );

        $billing_email.on("blur", function () {
            bill_email = $billing_email.val().toUpperCase()
        });

        $termsCheckbox.on("change", function () {
            bill_email = $billing_email.val().toUpperCase()
            if (bill_email.length) {
                if (bill_email == wp_registered_email) { //customer already subscribed
                    if (!$mailerLiteCheckbox.prop("checked")) {
                        $mailerLiteCheckbox.trigger("click");
                    }
                }
            } else if (!$termsCheckbox.prop("checked")) { //no billing email address entered
                $checkout_form.submit(); //to display error messages
            }
        });
    });
});