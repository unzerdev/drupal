/**
 * @file
 * Defines behaviors for the Unzer Payment hosted fields payment method form.
 */

(function ($, Drupal, drupalSettings) {

  'use strict';

  Drupal.commerceUnzerPayment = function ($form, drupalSettings) {

    Promise.all([
      customElements.whenDefined("unzer-payment"),
      customElements.whenDefined("unzer-pay-page"),
    ]).then(() => {
      const btnCheckout = document.getElementById("edit-actions-next");
      btnCheckout.addEventListener("click", function(evt) { showCheckout(evt); } , false);
    });

    $form.append('<div id="unzer-container"></div>');

    var $submit = $form.find(':input.button--primary.form-submit');
    var unzerPubKey = $form.attr('unzer_pub_key');
    var unzerPayPageId = $form.attr('unzer_paypage_id');
    var that = this;
    var submitForm = false;

    const unzerContainer = document.getElementById("unzer-container");
    unzerContainer.innerHTML = `
            <unzer-payment publicKey="${unzerPubKey}">
                <unzer-pay-page
                    id="unzer-checkout"
                    payPageId="${unzerPayPageId}"
                ></unzer-pay-page>
            </unzer-payment>
        `;

    function showCheckout(evt) {

      evt.preventDefault();

      if (!submitForm) {
        const checkout = document.getElementById("unzer-checkout");

        // Subscribe to the abort event
        checkout.abort(function () {
          console.log("checkout -> aborted");
        });

        // Subscribe to the success event
        checkout.success(function (data) {
          console.log("checkout -> success", data);
          submitForm = true;
          console.log('should trigger redirect!');
          console.log($submit);
          $submit.trigger('click');
        });

        // Subscribe to the error event
        checkout.error(function (error) {
          console.log("checkout -> error", error);
        });

        console.log('opening layer');
        // Render the Embedded Payment Page overlay
        checkout.open();

        return false;
      } else {
        return true;
      }
    }

  }

  var $form = $("body").find(".commerce-checkout-flow").first();

  Drupal.commerceUnzerPayment($form, drupalSettings);

})(jQuery, Drupal, drupalSettings);

