/* eslint-disable-next-line func-names */
(function ($) {
  // Ported from SilverStripe 4 silverstripe-admin client/src/legacy/GridField.js
  $('.js-delete-relations-btn').entwine({
    onmatch() {
      // Stop gridfield from trying to perform an action.
      this.removeClass('action');
      /* eslint-disable-next-line no-underscore-dangle */
      this._super();
      // Trigger auto-open
      if (this.data('state') === 'open') {
        this.openmodal();
      }
    },
    onclick(e) {
      e.preventDefault();
      this.openmodal();
    },

    openmodal() {
      // Remove existing modal
      let modal = $(this.data('target'));
      const newModal = $(this.data('modal'));

      if (modal.length < 1) {
        // Add modal to end of body tag
        modal = newModal;
        modal.appendTo(document.body);
      } else {
        // Replace inner content
        modal.innerHTML = newModal.innerHTML;
      }

      // Apply backdrop
      let backdrop = $('.modal-backdrop');
      if (backdrop.length < 1) {
        backdrop = $('<div class="modal-backdrop fade"></div>');
        backdrop.appendTo(document.body);
      }

      function closeModal() {
        backdrop.removeClass('show');
        modal.removeClass('show');
        setTimeout(() => {
          backdrop.remove();
        }, 150); // Simulate the bootstrap out-transition period
      }

      // Set close action
      modal.find('[data-dismiss]').add('.modal-backdrop')
        .on('click', () => {
          closeModal();
        });

      $(document).on('keydown', (e) => {
        if (e.keyCode === 27) { // Escape key
          closeModal();
        }
      });

      // Fade each element in (use setTimeout to ensure initial render at opacity=0 works)
      setTimeout(() => {
        backdrop.addClass('show');
        modal.addClass('show');
      }, 0);
    },
  });
}(jQuery));
