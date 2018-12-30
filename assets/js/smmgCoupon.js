(function($) {
  Drupal.behaviors.operetteCoupon = {
    getFirstAmountOption() {
      // get first Item of array with valid key
      const { amountOptions } = drupalSettings.coupon;
      return Object.keys(amountOptions)[0];
    },

    updateTotal() {
      // Init
      let totalAmount = 0;
      let totalNumber = 0;

      /** @namespace drupalSettings.coupon */
      const { amountOptions } = drupalSettings.coupon;

      // DOM Element for Display Results
      const $totalAmountDisplay = $('.coupon-table-total-amount');
      const $totalNumberDisplay = $('.coupon-table-total-number');

      // Get First Row Inputs

      for (let i = 1; i <= 10; i++) {
        // Row Number
        let number = 0;
        number = $(`#edit-number-${i}`).val();
        totalNumber += parseInt(number, 10);

        // Row Amount
        let amount = 0;
        amount = $(`#edit-amount-${i}`).val();

        // Update Row Total
        const total =
          parseInt(number, 10) * parseInt(amountOptions[amount], 10);
        totalAmount += total;
      }

      // Display Results
      $totalAmountDisplay.text(totalAmount);
      $totalNumberDisplay.text(totalNumber);

      // plural
      const couponSingular = Drupal.t('Coupon');
      const couponPlural = Drupal.t('Coupons');

      const label = totalNumber === 1 ? couponSingular : couponPlural;
      $('.coupon-table-total-number-label').text(label);
    },

    attach(context, settings) {
      this.getFirstAmountOption();
      this.updateTotal();

      // ser First Row to 1 if 0
      let number = $('#edit-number-1').val();

      if (parseInt(number, 10) === 0) {
        $('#edit-number-1').val(1);
      }

      const scope = this;

      // delete Rows
      for (let i = 1; i <= 10; i++) {
        const $number = $(`#edit-number-${i}`);
        const $amount = $(`#edit-amount-${i}`);
        const $delete = $(`#delete-${i}`);
        const $row = $(`#edit-row-${i}`);

        // Get Coupon number
        number = $number.val();

        // set row to active
        if (parseInt(number, 10) > 0) {
          $row.removeClass('hide').addClass('active');
        }

        // Check for Number Input change
        $number.once(`#edit-number-${i}`).change(() => {
          scope.updateTotal();
        });

        // Check for Amount Input change
        $amount.once(`#edit-amount-${i}`).change(() => {
          scope.updateTotal();
        });

        // Click Handler for Delete Rows
        $delete.once(`#delete-${i}`).click(() => {
          // reset input of Number and Amount
          $number.val(0);
          $amount.val(scope.getFirstAmountOption);

          // hide row
          $row.removeClass('active').addClass('hide');
          scope.updateTotal();
        });
      }

      // Click Handler for adding Rows
      $('.coupon-table-add')
        .once('.coupon-table-add')
        .click(() => {
          const elems = $('#edit-table').find('fieldset.hide');
          const first = elems[0];
          $(first)
            .removeClass('hide')
            .addClass('active');
          scope.updateTotal();
        });
    },
  };
})(jQuery, Drupal, drupalSettings);
