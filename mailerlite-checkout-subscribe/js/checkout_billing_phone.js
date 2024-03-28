jQuery(document).ready(function ($) {
    $(window).on("load", function () {
        var $termsCheckbox = $("input[name=terms]"); //#terms
        var $telemarketingCheckbox = $("input[name=mlcs_phone_call_subscription]"); //#mlcs_phone_call_subscription
        var $billing_phone = $("input[name=billing_phone]");
        var bill_phone = $billing_phone.length ? $billing_phone.val().toUpperCase() : "";
        var $checkout_form = $("form[name=checkout]");
        var wp_registered_phone = "";

        // Save checkbox state BEGIN
        let isChecked = localStorage.getItem('mlcs_phone_call_subscription') === 'true';
        $telemarketingCheckbox.prop('checked', isChecked);

        $telemarketingCheckbox.click(function () {
            let isChecked = $(this).is(':checked');
            localStorage.setItem('mlcs_phone_call_subscription', isChecked);
        });
        // Save checkbox state END

        $.post(mlcs_checkout_billing_phone_script.ajax_url,
            {
                action: "mlcs_phone_check",
                nonce: mlcs_checkout_billing_phone_script.nonce,
            },
            function (response) {
                if (response) {
                    wp_registered_phone = decodeURIComponent(response.toUpperCase());
                }
            }
        );

        $billing_phone.on("blur", function () {
            bill_phone = $billing_phone.val().toUpperCase()
        });

        $termsCheckbox.on("change", function () {
            bill_phone = $billing_phone.val().toUpperCase()
            if (bill_phone.length) {
                if (wp_registered_phone == "SUBSCRIBED") { //customer already subscribed
                    if (!$telemarketingCheckbox.prop("checked")) {
                        $telemarketingCheckbox.trigger("click");
                    }
                }
            } else if (!$termsCheckbox.prop("checked")) { //no billing phone address entered
                $checkout_form.submit(); //to display error messages
            }
        });
    });
});