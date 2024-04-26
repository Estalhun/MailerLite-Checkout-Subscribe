
function findNodebyName(node, value) {

    if (node.nodeName === value.toUpperCase() || node.id === value) {
        return node;
    }

    for (let i = 0; i < node.children.length; i++) {
        let found = findNodebyName(node.children[i], value);
        if (found) {
            return found;
        }
    }
    return null;
}


jQuery(document).ready(function ($) {

    const targetNode = document.getElementById("order_review");
    const config = { attributes: true, childList: true, subtree: true };
    var wp_registered_email = "";

    const callback = (mutationList, observer) => {
        for (const mutation of mutationList) {

            if (mutation.type === "attributes") {

                const foundNode = findNodebyName(mutation.target, "terms");

                if (foundNode) {

                    var $termsCheckbox = $(foundNode);
                    var $mailerLiteCheckbox = $("#woo_ml_subscribe");

                    $termsCheckbox.on("change", function () {
                        var $billing_email = $("input[name=billing_email]");
                        var bill_email = $billing_email.length ? $billing_email.val().toUpperCase() : "";

                        bill_email = $billing_email.val().toUpperCase();
                        if (bill_email.length) {
                            if (bill_email == wp_registered_email) { //customer already subscribed
                                if (!$mailerLiteCheckbox.prop("checked") && $termsCheckbox.prop("checked")) {
                                    $mailerLiteCheckbox.trigger("click");
                                }
                            }
                        } else if (!$termsCheckbox.prop("checked")) { //no billing email address entered
                            $checkout_form.submit(); //to display error messages
                        }

                        if (observer !== null) {
                            observer.disconnect();
                            observer = null;
                        }

                    });

                    var $billing_email = $("input[name=billing_email]");
                    var bill_email = $billing_email.length ? $billing_email.val().toUpperCase() : "";

                    bill_email = $billing_email.val().toUpperCase();
                    if (bill_email.length) {
                        if (bill_email == wp_registered_email) { //customer already subscribed
                            if (!$mailerLiteCheckbox.prop("checked") && $termsCheckbox.prop("checked")) {
                                $mailerLiteCheckbox.trigger("click");
                            }
                        }
                    } else if (!$termsCheckbox.prop("checked")) { //no billing email address entered
                        $checkout_form.submit(); //to display error messages
                    }
                }
            }
        }
    };

    const observer = new MutationObserver(callback);
    observer.observe(targetNode, config);

    $(window).on("load", function () {
        var $termsCheckbox = $("input[name=terms]"); //#terms input[name=terms]
        var $mailerLiteCheckbox = $("input[name=woo_ml_subscribe]"); //#woo_ml_subscribe
        var $billing_email = $("input[name=billing_email]");
        var bill_email = $billing_email.length ? $billing_email.val().toUpperCase() : "";
        var $checkout_form = $("form[name=checkout]");

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
            bill_email = $billing_email.val().toUpperCase();
        });
    });
});