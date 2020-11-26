/* eslint-disable-next-line func-names */
(function ($) {
  // Replace querySelectorAll method to allow periods in selector attributes.
  function setupSelectors(elem) {
    const $elem = $(elem);
    // Only replace the method once.
    if (!$elem.data('selectorsSet')) {
      const oldQuerySelectorAll = elem.querySelectorAll.bind(elem);
      /* eslint-disable-next-line no-param-reassign */
      elem.querySelectorAll = function querySelectorAll(selector) {
        // Add quotes around the value of any attribute selector with a period in it.
        // e.g. [name=some.attr] becomes [name="some.attr"]
        let augmentedSelector = selector.replace(/(\[[a-z]+?=)([a-z_]+?\.[a-z_]+?)(\])/gi, '$1"$2"$3');
        // Escape periods in ID selectors.
        // e.g. #some.id becomes #some/.id
        augmentedSelector = augmentedSelector.replace(/(#[a-z_]+?)(\.[a-z_]+?)/gi, '$1\\$2');
        return oldQuerySelectorAll(augmentedSelector);
      };
      // Set the selectorsSet flag so we don't get nested methods.
      $elem.data('selectorsSet', true);
    }
  }

  // Detect any new nodes that match a given selector.
  // Entwine onmatch won't work as we can't force our onmatch function to be a higher
  // priority than display-logic's.
  const observer = new MutationObserver(((mutations) => {
    mutations.forEach(() => {
      const modalSelector = '.modal--gridfield-delete-relations';
      const $nodes = $(`${modalSelector} div.display-logic,${modalSelector} div.display-logic-master,${modalSelector} form`);
      $nodes.each(function setupNodeSelectors() {
        setupSelectors(this);
      });
    });
  }));

  // Report any changes to which elements exist in the DOM.
  // Also report any changes to attributes, since the "display-logic-master" class is added
  // dynamically.
  const config = {
    attributes: true,
    childList: true,
    subtree: true,
    characterData: false,
  };

  observer.observe(document, config);

  // eslint-disable-next-line no-shadow
  $.entwine(($) => {
    $('div.datetime.display-logic').entwine({
      // Provide the necessary data for display-logic.
      // See https://github.com/unclecheese/silverstripe-display-logic/issues/119
      onmatch() {
        const hiddenField = this.find('input[type="hidden"]');
        const field = this.find('input.datetime');
        if (hiddenField.length && field.data('display-logic-eval') && field.data('display-logic-masters')) {
          this.data('display-logic-eval', field.data('display-logic-eval'))
            .data('display-logic-masters', field.data('display-logic-masters'))
            .data('display-logic-animation', field.data('display-logic-animation'));
          hiddenField.data('display-logic-eval', field.data('display-logic-eval'))
            .data('display-logic-masters', field.data('display-logic-masters'))
            .data('display-logic-animation', field.data('display-logic-animation'));
          // Also add a clear example of input format, since no datepicker will be provided.
          this.parent().find('.js-placeholder-txt').text(field.attr('placeholder'));
        }
        // eslint-disable-next-line no-underscore-dangle
        this._super();
      },
    });
  });
}(jQuery));
