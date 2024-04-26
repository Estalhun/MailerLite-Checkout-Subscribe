
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
    var wp_registered_phone = "";

    const callback = (mutationList, observer) => {
        for (const mutation of mutationList) {

            if (mutation.type === "attributes") {

                const foundNode = findNodebyName(mutation.target, "terms");

                if (foundNode) {

                    var $termsCheckbox = $(foundNode);
                    var $telemarketingCheckbox = $("input[name=mlcs_phone_call_subscription]"); //#mlcs_phone_call_subscription

                    $termsCheckbox.on("change", function () {
                        var $billing_phone = $("input[name=billing_phone]");
                        var bill_phone = $billing_phone.length ? $billing_phone.val().toUpperCase() : "";

                        bill_phone = $billing_phone.val().toUpperCase();
                        if (bill_phone.length) {
                            console.log("change wp_registered_phone: " + wp_registered_phone);
                            if (wp_registered_phone == "SUBSCRIBED") { //customer already subscribed
                                if (!$telemarketingCheckbox.prop("checked") && $termsCheckbox.prop("checked")) {
                                    $telemarketingCheckbox.trigger("click");
                                }
                            }
                        } else if (!$termsCheckbox.prop("checked")) { //no billing phone address entered
                            $checkout_form.submit(); //to display error messages
                        }

                        if (observer !== null) {
                            observer.disconnect();
                            observer = null;
                        }
                    });

                    var $billing_phone = $("input[name=billing_phone]");
                    var bill_phone = $billing_phone.length ? $billing_phone.val().toUpperCase() : "";

                    bill_phone = $billing_phone.val().toUpperCase();
                    if (bill_phone.length) {
                        if (wp_registered_phone == "SUBSCRIBED") { //customer already subscribed
                            if (!$telemarketingCheckbox.prop("checked") && $termsCheckbox.prop("checked")) {
                                $telemarketingCheckbox.trigger("click");
                            }
                        }
                    } else if (!$termsCheckbox.prop("checked")) { //no billing phone address entered
                        $checkout_form.submit(); //to display error messages
                    }
                }
            }
        }
    };

    const observer = new MutationObserver(callback);
    observer.observe(targetNode, config);

    $(window).on("load", function () {
        var $termsCheckbox = $("input[name=terms]"); //#terms
        var $billing_phone = $("input[name=billing_phone]");
        var bill_phone = $billing_phone.length ? $billing_phone.val().toUpperCase() : "";
        var $checkout_form = $("form[name=checkout]");

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
            bill_phone = $billing_phone.val().toUpperCase();
        });
    });
});