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
      const $nodes = $('div.display-logic, div.display-logic-master, form');
      $nodes.each(() => {
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
}(jQuery));
