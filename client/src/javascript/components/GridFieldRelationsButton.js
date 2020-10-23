(function ($) {

    // Replace querySelectorAll method to allow periods in selector attributes.
    function setupSelectors(elem) {
      const $elem = $(elem);
      // Only replace the method once.
      if (!$elem.data('selectorsSet')) {
        const old_querySelectorAll = elem.querySelectorAll.bind(elem);
        elem.querySelectorAll = function(selector) {
          // Add quotes around the value of any attribute selector with a period in it.
          // e.g. [name=some.attr] becomes [name="some.attr"]
          selector = selector.replace(/(\[[a-z]+?=)([a-z_]+?\.[a-z_]+?)(\])/gi, '$1"$2"$3');
          // Escape periods in ID selectors.
          // e.g. #some.id becomes #some/.id
          selector = selector.replace(/(#[a-z_]+?)(\.[a-z_]+?)/gi, '$1\\$2');
          return old_querySelectorAll(selector);
        }
        // Set the selectorsSet flag so we don't get nested methods.
        $elem.data('selectorsSet', true);
      }
    }
  
    // Detect any new nodes that match a given selector.
    // Entwine onmatch won't work as we can't force our onmatch function to be a higher
    // priority than display-logic's.
    var observer = new MutationObserver(function( mutations ) {
      mutations.forEach(function( mutation ) {
        var $nodes = $('div.display-logic, div.display-logic-master, form');
        $nodes.each(function() {
          setupSelectors(this);
        });
      });    
    });
  
    // Report any changes to which elements exist in the DOM.
    // Also report any changes to attributes, since the "display-logic-master" class is added dynamically.
    var config = { 
      attributes: true, 
      childList: true,
      subtree: true,
      characterData: false 
    };
  
    observer.observe(document, config);
  
  }(jQuery));