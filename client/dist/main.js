/******/ (function(modules) { // webpackBootstrap
/******/ 	// The module cache
/******/ 	var installedModules = {};
/******/
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/
/******/ 		// Check if module is in cache
/******/ 		if(installedModules[moduleId]) {
/******/ 			return installedModules[moduleId].exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = installedModules[moduleId] = {
/******/ 			i: moduleId,
/******/ 			l: false,
/******/ 			exports: {}
/******/ 		};
/******/
/******/ 		// Execute the module function
/******/ 		modules[moduleId].call(module.exports, module, module.exports, __webpack_require__);
/******/
/******/ 		// Flag the module as loaded
/******/ 		module.l = true;
/******/
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/
/******/
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = modules;
/******/
/******/ 	// expose the module cache
/******/ 	__webpack_require__.c = installedModules;
/******/
/******/ 	// define getter function for harmony exports
/******/ 	__webpack_require__.d = function(exports, name, getter) {
/******/ 		if(!__webpack_require__.o(exports, name)) {
/******/ 			Object.defineProperty(exports, name, { enumerable: true, get: getter });
/******/ 		}
/******/ 	};
/******/
/******/ 	// define __esModule on exports
/******/ 	__webpack_require__.r = function(exports) {
/******/ 		if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 			Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 		}
/******/ 		Object.defineProperty(exports, '__esModule', { value: true });
/******/ 	};
/******/
/******/ 	// create a fake namespace object
/******/ 	// mode & 1: value is a module id, require it
/******/ 	// mode & 2: merge all properties of value into the ns
/******/ 	// mode & 4: return value when already ns object
/******/ 	// mode & 8|1: behave like require
/******/ 	__webpack_require__.t = function(value, mode) {
/******/ 		if(mode & 1) value = __webpack_require__(value);
/******/ 		if(mode & 8) return value;
/******/ 		if((mode & 4) && typeof value === 'object' && value && value.__esModule) return value;
/******/ 		var ns = Object.create(null);
/******/ 		__webpack_require__.r(ns);
/******/ 		Object.defineProperty(ns, 'default', { enumerable: true, value: value });
/******/ 		if(mode & 2 && typeof value != 'string') for(var key in value) __webpack_require__.d(ns, key, function(key) { return value[key]; }.bind(null, key));
/******/ 		return ns;
/******/ 	};
/******/
/******/ 	// getDefaultExport function for compatibility with non-harmony modules
/******/ 	__webpack_require__.n = function(module) {
/******/ 		var getter = module && module.__esModule ?
/******/ 			function getDefault() { return module['default']; } :
/******/ 			function getModuleExports() { return module; };
/******/ 		__webpack_require__.d(getter, 'a', getter);
/******/ 		return getter;
/******/ 	};
/******/
/******/ 	// Object.prototype.hasOwnProperty.call
/******/ 	__webpack_require__.o = function(object, property) { return Object.prototype.hasOwnProperty.call(object, property); };
/******/
/******/ 	// __webpack_public_path__
/******/ 	__webpack_require__.p = "/";
/******/
/******/
/******/ 	// Load entry module and return exports
/******/ 	return __webpack_require__(__webpack_require__.s = 0);
/******/ })
/************************************************************************/
/******/ ({

/***/ "./client/src/javascript/components/GridFieldRelationsButton.js":
/*!**********************************************************************!*\
  !*** ./client/src/javascript/components/GridFieldRelationsButton.js ***!
  \**********************************************************************/
/*! no static exports found */
/***/ (function(module, exports) {

/* eslint-disable-next-line func-names */
(function ($) {
  // Replace querySelectorAll method to allow periods in selector attributes.
  function setupSelectors(elem) {
    var $elem = $(elem); // Only replace the method once.

    if (!$elem.data('selectorsSet')) {
      var oldQuerySelectorAll = elem.querySelectorAll.bind(elem);
      /* eslint-disable-next-line no-param-reassign */

      elem.querySelectorAll = function querySelectorAll(selector) {
        // Add quotes around the value of any attribute selector with a period in it.
        // e.g. [name=some.attr] becomes [name="some.attr"]
        var augmentedSelector = selector.replace(/(\[[a-z]+?=)([a-z_]+?\.[a-z_]+?)(\])/gi, '$1"$2"$3'); // Escape periods in ID selectors.
        // e.g. #some.id becomes #some/.id

        augmentedSelector = augmentedSelector.replace(/(#[a-z_]+?)(\.[a-z_]+?)/gi, '$1\\$2');
        return oldQuerySelectorAll(augmentedSelector);
      }; // Set the selectorsSet flag so we don't get nested methods.


      $elem.data('selectorsSet', true);
    }
  } // Detect any new nodes that match a given selector.
  // Entwine onmatch won't work as we can't force our onmatch function to be a higher
  // priority than display-logic's.


  var observer = new MutationObserver(function (mutations) {
    mutations.forEach(function () {
      var modalSelector = '.modal--gridfield-delete-relations';
      var $nodes = $("".concat(modalSelector, " div.display-logic,").concat(modalSelector, " div.display-logic-master,").concat(modalSelector, " form"));
      $nodes.each(function setupNodeSelectors() {
        setupSelectors(this);
      });
    });
  }); // Report any changes to which elements exist in the DOM.
  // Also report any changes to attributes, since the "display-logic-master" class is added
  // dynamically.

  var config = {
    attributes: true,
    childList: true,
    subtree: true,
    characterData: false
  };
  observer.observe(document, config);
})(jQuery);

/***/ }),

/***/ "./client/src/javascript/components/Modal.js":
/*!***************************************************!*\
  !*** ./client/src/javascript/components/Modal.js ***!
  \***************************************************/
/*! no static exports found */
/***/ (function(module, exports) {

/* eslint-disable-next-line func-names */
(function ($) {
  // Ported from SilverStripe 4 silverstripe-admin client/src/legacy/GridField.js
  $('.js-delete-relations-btn').entwine({
    onmatch: function onmatch() {
      // Stop gridfield from trying to perform an action.
      this.removeClass('action');
      /* eslint-disable-next-line no-underscore-dangle */

      this._super(); // Trigger auto-open


      if (this.data('state') === 'open') {
        this.openmodal();
      }
    },
    onclick: function onclick(e) {
      e.preventDefault();
      this.openmodal();
    },
    openmodal: function openmodal() {
      // Remove existing modal
      var modal = $(this.data('target'));
      var newModal = $(this.data('modal'));

      if (modal.length < 1) {
        // Add modal to end of body tag
        modal = newModal;
        modal.appendTo(document.body);
      } else {
        // Replace inner content
        modal.innerHTML = newModal.innerHTML;
      } // Apply backdrop


      var backdrop = $('.modal-backdrop');

      if (backdrop.length < 1) {
        backdrop = $('<div class="modal-backdrop fade"></div>');
        backdrop.appendTo(document.body);
      }

      function closeModal() {
        backdrop.removeClass('show');
        modal.removeClass('show');
        setTimeout(function () {
          backdrop.remove();
        }, 150); // Simulate the bootstrap out-transition period
      } // Set close action


      modal.find('[data-dismiss]').add('.modal-backdrop').on('click', function () {
        closeModal();
      });
      $(document).on('keydown', function (e) {
        if (e.keyCode === 27) {
          // Escape key
          closeModal();
        }
      }); // Fade each element in (use setTimeout to ensure initial render at opacity=0 works)

      setTimeout(function () {
        backdrop.addClass('show');
        modal.addClass('show');
      }, 0);
    }
  });
})(jQuery);

/***/ }),

/***/ "./client/src/javascript/main.js":
/*!***************************************!*\
  !*** ./client/src/javascript/main.js ***!
  \***************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

__webpack_require__(/*! ./components/GridFieldRelationsButton.js */ "./client/src/javascript/components/GridFieldRelationsButton.js");

__webpack_require__(/*! ./components/Modal.js */ "./client/src/javascript/components/Modal.js");

/***/ }),

/***/ "./client/src/scss/main.scss":
/*!***********************************!*\
  !*** ./client/src/scss/main.scss ***!
  \***********************************/
/*! no static exports found */
/***/ (function(module, exports) {

// removed by extract-text-webpack-plugin

/***/ }),

/***/ 0:
/*!*************************************************************************!*\
  !*** multi ./client/src/javascript/main.js ./client/src/scss/main.scss ***!
  \*************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

__webpack_require__(/*! /home/webspace/ss3-guy/_modules/silverstripe-security-headers/client/src/javascript/main.js */"./client/src/javascript/main.js");
module.exports = __webpack_require__(/*! /home/webspace/ss3-guy/_modules/silverstripe-security-headers/client/src/scss/main.scss */"./client/src/scss/main.scss");


/***/ })

/******/ });
//# sourceMappingURL=data:application/json;charset=utf-8;base64,eyJ2ZXJzaW9uIjozLCJzb3VyY2VzIjpbIndlYnBhY2s6Ly8vd2VicGFjay9ib290c3RyYXAiLCJ3ZWJwYWNrOi8vLy4vY2xpZW50L3NyYy9qYXZhc2NyaXB0L2NvbXBvbmVudHMvR3JpZEZpZWxkUmVsYXRpb25zQnV0dG9uLmpzIiwid2VicGFjazovLy8uL2NsaWVudC9zcmMvamF2YXNjcmlwdC9jb21wb25lbnRzL01vZGFsLmpzIiwid2VicGFjazovLy8uL2NsaWVudC9zcmMvamF2YXNjcmlwdC9tYWluLmpzIiwid2VicGFjazovLy8uL2NsaWVudC9zcmMvc2Nzcy9tYWluLnNjc3M/NzJjNiJdLCJuYW1lcyI6WyIkIiwic2V0dXBTZWxlY3RvcnMiLCJlbGVtIiwiJGVsZW0iLCJkYXRhIiwib2xkUXVlcnlTZWxlY3RvckFsbCIsInF1ZXJ5U2VsZWN0b3JBbGwiLCJiaW5kIiwic2VsZWN0b3IiLCJhdWdtZW50ZWRTZWxlY3RvciIsInJlcGxhY2UiLCJvYnNlcnZlciIsIk11dGF0aW9uT2JzZXJ2ZXIiLCJtdXRhdGlvbnMiLCJmb3JFYWNoIiwibW9kYWxTZWxlY3RvciIsIiRub2RlcyIsImVhY2giLCJzZXR1cE5vZGVTZWxlY3RvcnMiLCJjb25maWciLCJhdHRyaWJ1dGVzIiwiY2hpbGRMaXN0Iiwic3VidHJlZSIsImNoYXJhY3RlckRhdGEiLCJvYnNlcnZlIiwiZG9jdW1lbnQiLCJqUXVlcnkiLCJlbnR3aW5lIiwib25tYXRjaCIsInJlbW92ZUNsYXNzIiwiX3N1cGVyIiwib3Blbm1vZGFsIiwib25jbGljayIsImUiLCJwcmV2ZW50RGVmYXVsdCIsIm1vZGFsIiwibmV3TW9kYWwiLCJsZW5ndGgiLCJhcHBlbmRUbyIsImJvZHkiLCJpbm5lckhUTUwiLCJiYWNrZHJvcCIsImNsb3NlTW9kYWwiLCJzZXRUaW1lb3V0IiwicmVtb3ZlIiwiZmluZCIsImFkZCIsIm9uIiwia2V5Q29kZSIsImFkZENsYXNzIiwicmVxdWlyZSJdLCJtYXBwaW5ncyI6IjtRQUFBO1FBQ0E7O1FBRUE7UUFDQTs7UUFFQTtRQUNB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Q0FBVyxnQkFBWCxFQUE2QkMsR0FBN0IsQ0FBaUMsaUJBQWpDLEVBQ0dDLEVBREgsQ0FDTSxPQUROLEVBQ2UsWUFBTTtBQUNqQkwsa0JBQVU7QUFDWCxPQUhIO0FBS0ExQyxPQUFDLENBQUN5QixRQUFELENBQUQsQ0FBWXNCLEVBQVosQ0FBZSxTQUFmLEVBQTBCLFVBQUNkLENBQUQsRUFBTztBQUMvQixZQUFJQSxDQUFDLENBQUNlLE9BQUYsS0FBYyxFQUFsQixFQUFzQjtBQUFFO0FBQ3RCTixvQkFBVTtBQUNYO0FBQ0YsT0FKRCxFQW5DVSxDQXlDVjs7QUFDQUMsZ0JBQVUsQ0FBQyxZQUFNO0FBQ2ZGLGdCQUFRLENBQUNRLFFBQVQsQ0FBa0IsTUFBbEI7QUFDQWQsYUFBSyxDQUFDYyxRQUFOLENBQWUsTUFBZjtBQUNELE9BSFMsRUFHUCxDQUhPLENBQVY7QUFJRDtBQTlEbUMsR0FBdEM7QUFnRUQsQ0FsRUEsRUFrRUN2QixNQWxFRCxDQUFELEM7Ozs7Ozs7Ozs7O0FDREF3QixtQkFBTyxDQUFDLGdIQUFELENBQVA7O0FBQ0FBLG1CQUFPLENBQUMsMEVBQUQsQ0FBUCxDOzs7Ozs7Ozs7OztBQ0RBLHlDIiwiZmlsZSI6Ii9jbGllbnQvZGlzdC9tYWluLmpzIiwic291cmNlc0NvbnRlbnQiOlsiIFx0Ly8gVGhlIG1vZHVsZSBjYWNoZVxuIFx0dmFyIGluc3RhbGxlZE1vZHVsZXMgPSB7fTtcblxuIFx0Ly8gVGhlIHJlcXVpcmUgZnVuY3Rpb25cbiBcdGZ1bmN0aW9uIF9fd2VicGFja19yZXF1aXJlX18obW9kdWxlSWQpIHtcblxuIFx0XHQvLyBDaGVjayBpZiBtb2R1bGUgaXMgaW4gY2FjaGVcbiBcdFx0aWYoaW5zdGFsbGVkTW9kdWxlc1ttb2R1bGVJZF0pIHtcbiBcdFx0XHRyZXR1cm4gaW5zdGFsbGVkTW9kdWxlc1ttb2R1bGVJZF0uZXhwb3J0cztcbiBcdFx0fVxuIFx0XHQvLyBDcmVhdGUgYSBuZXcgbW9kdWxlIChhbmQgcHV0IGl0IGludG8gdGhlIGNhY2hlKVxuIFx0XHR2YXIgbW9kdWxlID0gaW5zdGFsbGVkTW9kdWxlc1ttb2R1bGVJZF0gPSB7XG4gXHRcdFx0aTogbW9kdWxlSWQsXG4gXHRcdFx0bDogZmFsc2UsXG4gXHRcdFx0ZXhwb3J0czoge31cbiBcdFx0fTtcblxuIFx0XHQvLyBFeGVjdXRlIHRoZSBtb2R1bGUgZnVuY3Rpb25cbiBcdFx0bW9kdWxlc1ttb2R1bGVJZF0uY2FsbChtb2R1bGUuZXhwb3J0cywgbW9kdWxlLCBtb2R1bGUuZXhwb3J0cywgX193ZWJwYWNrX3JlcXVpcmVfXyk7XG5cbiBcdFx0Ly8gRmxhZyB0aGUgbW9kdWxlIGFzIGxvYWRlZFxuIFx0XHRtb2R1bGUubCA9IHRydWU7XG5cbiBcdFx0Ly8gUmV0dXJuIHRoZSBleHBvcnRzIG9mIHRoZSBtb2R1bGVcbiBcdFx0cmV0dXJuIG1vZHVsZS5leHBvcnRzO1xuIFx0fVxuXG5cbiBcdC8vIGV4cG9zZSB0aGUgbW9kdWxlcyBvYmplY3QgKF9fd2VicGFja19tb2R1bGVzX18pXG4gXHRfX3dlYnBhY2tfcmVxdWlyZV9fLm0gPSBtb2R1bGVzO1xuXG4gXHQvLyBleHBvc2UgdGhlIG1vZHVsZSBjYWNoZVxuIFx0X193ZWJwYWNrX3JlcXVpcmVfXy5jID0gaW5zdGFsbGVkTW9kdWxlcztcblxuIFx0Ly8gZGVmaW5lIGdldHRlciBmdW5jdGlvbiBmb3IgaGFybW9ueSBleHBvcnRzXG4gXHRfX3dlYnBhY2tfcmVxdWlyZV9fLmQgPSBmdW5jdGlvbihleHBvcnRzLCBuYW1lLCBnZXR0ZXIpIHtcbiBcdFx0aWYoIV9fd2VicGFja19yZXF1aXJlX18ubyhleHBvcnRzLCBuYW1lKSkge1xuIFx0XHRcdE9iamVjdC5kZWZpbmVQcm9wZXJ0eShleHBvcnRzLCBuYW1lLCB7IGVudW1lcmFibGU6IHRydWUsIGdldDogZ2V0dGVyIH0pO1xuIFx0XHR9XG4gXHR9O1xuXG4gXHQvLyBkZWZpbmUgX19lc01vZHVsZSBvbiBleHBvcnRzXG4gXHRfX3dlYnBhY2tfcmVxdWlyZV9fLnIgPSBmdW5jdGlvbihleHBvcnRzKSB7XG4gXHRcdGlmKHR5cGVvZiBTeW1ib2wgIT09ICd1bmRlZmluZWQnICYmIFN5bWJvbC50b1N0cmluZ1RhZykge1xuIFx0XHRcdE9iamVjdC5kZWZpbmVQcm9wZXJ0eShleHBvcnRzLCBTeW1ib2wudG9TdHJpbmdUYWcsIHsgdmFsdWU6ICdNb2R1bGUnIH0pO1xuIFx0XHR9XG4gXHRcdE9iamVjdC5kZWZpbmVQcm9wZXJ0eShleHBvcnRzLCAnX19lc01vZHVsZScsIHsgdmFsdWU6IHRydWUgfSk7XG4gXHR9O1xuXG4gXHQvLyBjcmVhdGUgYSBmYWtlIG5hbWVzcGFjZSBvYmplY3RcbiBcdC8vIG1vZGUgJiAxOiB2YWx1ZSBpcyBhIG1vZHVsZSBpZCwgcmVxdWlyZSBpdFxuIFx0Ly8gbW9kZSAmIDI6IG1lcmdlIGFsbCBwcm9wZXJ0aWVzIG9mIHZhbHVlIGludG8gdGhlIG5zXG4gXHQvLyBtb2RlICYgNDogcmV0dXJuIHZhbHVlIHdoZW4gYWxyZWFkeSBucyBvYmplY3RcbiBcdC8vIG1vZGUgJiA4fDE6IGJlaGF2ZSBsaWtlIHJlcXVpcmVcbiBcdF9fd2VicGFja19yZXF1aXJlX18udCA9IGZ1bmN0aW9uKHZhbHVlLCBtb2RlKSB7XG4gXHRcdGlmKG1vZGUgJiAxKSB2YWx1ZSA9IF9fd2VicGFja19yZXF1aXJlX18odmFsdWUpO1xuIFx0XHRpZihtb2RlICYgOCkgcmV0dXJuIHZhbHVlO1xuIFx0XHRpZigobW9kZSAmIDQpICYmIHR5cGVvZiB2YWx1ZSA9PT0gJ29iamVjdCcgJiYgdmFsdWUgJiYgdmFsdWUuX19lc01vZHVsZSkgcmV0dXJuIHZhbHVlO1xuIFx0XHR2YXIgbnMgPSBPYmplY3QuY3JlYXRlKG51bGwpO1xuIFx0XHRfX3dlYnBhY2tfcmVxdWlyZV9fLnIobnMpO1xuIFx0XHRPYmplY3QuZGVmaW5lUHJvcGVydHkobnMsICdkZWZhdWx0JywgeyBlbnVtZXJhYmxlOiB0cnVlLCB2YWx1ZTogdmFsdWUgfSk7XG4gXHRcdGlmKG1vZGUgJiAyICYmIHR5cGVvZiB2YWx1ZSAhPSAnc3RyaW5nJykgZm9yKHZhciBrZXkgaW4gdmFsdWUpIF9fd2VicGFja19yZXF1aXJlX18uZChucywga2V5LCBmdW5jdGlvbihrZXkpIHsgcmV0dXJuIHZhbHVlW2tleV07IH0uYmluZChudWxsLCBrZXkpKTtcbiBcdFx0cmV0dXJuIG5zO1xuIFx0fTtcblxuIFx0Ly8gZ2V0RGVmYXVsdEV4cG9ydCBmdW5jdGlvbiBmb3IgY29tcGF0aWJpbGl0eSB3aXRoIG5vbi1oYXJtb255IG1vZHVsZXNcbiBcdF9fd2VicGFja19yZXF1aXJlX18ubiA9IGZ1bmN0aW9uKG1vZHVsZSkge1xuIFx0XHR2YXIgZ2V0dGVyID0gbW9kdWxlICYmIG1vZHVsZS5fX2VzTW9kdWxlID9cbiBcdFx0XHRmdW5jdGlvbiBnZXREZWZhdWx0KCkgeyByZXR1cm4gbW9kdWxlWydkZWZhdWx0J107IH0gOlxuIFx0XHRcdGZ1bmN0aW9uIGdldE1vZHVsZUV4cG9ydHMoKSB7IHJldHVybiBtb2R1bGU7IH07XG4gXHRcdF9fd2VicGFja19yZXF1aXJlX18uZChnZXR0ZXIsICdhJywgZ2V0dGVyKTtcbiBcdFx0cmV0dXJuIGdldHRlcjtcbiBcdH07XG5cbiBcdC8vIE9iamVjdC5wcm90b3R5cGUuaGFzT3duUHJvcGVydHkuY2FsbFxuIFx0X193ZWJwYWNrX3JlcXVpcmVfXy5vID0gZnVuY3Rpb24ob2JqZWN0LCBwcm9wZXJ0eSkgeyByZXR1cm4gT2JqZWN0LnByb3RvdHlwZS5oYXNPd25Qcm9wZXJ0eS5jYWxsKG9iamVjdCwgcHJvcGVydHkpOyB9O1xuXG4gXHQvLyBfX3dlYnBhY2tfcHVibGljX3BhdGhfX1xuIFx0X193ZWJwYWNrX3JlcXVpcmVfXy5wID0gXCIvXCI7XG5cblxuIFx0Ly8gTG9hZCBlbnRyeSBtb2R1bGUgYW5kIHJldHVybiBleHBvcnRzXG4gXHRyZXR1cm4gX193ZWJwYWNrX3JlcXVpcmVfXyhfX3dlYnBhY2tfcmVxdWlyZV9fLnMgPSAwKTtcbiIsIi8qIGVzbGludC1kaXNhYmxlLW5leHQtbGluZSBmdW5jLW5hbWVzICovXG4oZnVuY3Rpb24gKCQpIHtcbiAgLy8gUmVwbGFjZSBxdWVyeVNlbGVjdG9yQWxsIG1ldGhvZCB0byBhbGxvdyBwZXJpb2RzIGluIHNlbGVjdG9yIGF0dHJpYnV0ZXMuXG4gIGZ1bmN0aW9uIHNldHVwU2VsZWN0b3JzKGVsZW0pIHtcbiAgICBjb25zdCAkZWxlbSA9ICQoZWxlbSk7XG4gICAgLy8gT25seSByZXBsYWNlIHRoZSBtZXRob2Qgb25jZS5cbiAgICBpZiAoISRlbGVtLmRhdGEoJ3NlbGVjdG9yc1NldCcpKSB7XG4gICAgICBjb25zdCBvbGRRdWVyeVNlbGVjdG9yQWxsID0gZWxlbS5xdWVyeVNlbGVjdG9yQWxsLmJpbmQoZWxlbSk7XG4gICAgICAvKiBlc2xpbnQtZGlzYWJsZS1uZXh0LWxpbmUgbm8tcGFyYW0tcmVhc3NpZ24gKi9cbiAgICAgIGVsZW0ucXVlcnlTZWxlY3RvckFsbCA9IGZ1bmN0aW9uIHF1ZXJ5U2VsZWN0b3JBbGwoc2VsZWN0b3IpIHtcbiAgICAgICAgLy8gQWRkIHF1b3RlcyBhcm91bmQgdGhlIHZhbHVlIG9mIGFueSBhdHRyaWJ1dGUgc2VsZWN0b3Igd2l0aCBhIHBlcmlvZCBpbiBpdC5cbiAgICAgICAgLy8gZS5nLiBbbmFtZT1zb21lLmF0dHJdIGJlY29tZXMgW25hbWU9XCJzb21lLmF0dHJcIl1cbiAgICAgICAgbGV0IGF1Z21lbnRlZFNlbGVjdG9yID0gc2VsZWN0b3IucmVwbGFjZSgvKFxcW1thLXpdKz89KShbYS16X10rP1xcLlthLXpfXSs/KShcXF0pL2dpLCAnJDFcIiQyXCIkMycpO1xuICAgICAgICAvLyBFc2NhcGUgcGVyaW9kcyBpbiBJRCBzZWxlY3RvcnMuXG4gICAgICAgIC8vIGUuZy4gI3NvbWUuaWQgYmVjb21lcyAjc29tZS8uaWRcbiAgICAgICAgYXVnbWVudGVkU2VsZWN0b3IgPSBhdWdtZW50ZWRTZWxlY3Rvci5yZXBsYWNlKC8oI1thLXpfXSs/KShcXC5bYS16X10rPykvZ2ksICckMVxcXFwkMicpO1xuICAgICAgICByZXR1cm4gb2xkUXVlcnlTZWxlY3RvckFsbChhdWdtZW50ZWRTZWxlY3Rvcik7XG4gICAgICB9O1xuICAgICAgLy8gU2V0IHRoZSBzZWxlY3RvcnNTZXQgZmxhZyBzbyB3ZSBkb24ndCBnZXQgbmVzdGVkIG1ldGhvZHMuXG4gICAgICAkZWxlbS5kYXRhKCdzZWxlY3RvcnNTZXQnLCB0cnVlKTtcbiAgICB9XG4gIH1cblxuICAvLyBEZXRlY3QgYW55IG5ldyBub2RlcyB0aGF0IG1hdGNoIGEgZ2l2ZW4gc2VsZWN0b3IuXG4gIC8vIEVudHdpbmUgb25tYXRjaCB3b24ndCB3b3JrIGFzIHdlIGNhbid0IGZvcmNlIG91ciBvbm1hdGNoIGZ1bmN0aW9uIHRvIGJlIGEgaGlnaGVyXG4gIC8vIHByaW9yaXR5IHRoYW4gZGlzcGxheS1sb2dpYydzLlxuICBjb25zdCBvYnNlcnZlciA9IG5ldyBNdXRhdGlvbk9ic2VydmVyKCgobXV0YXRpb25zKSA9PiB7XG4gICAgbXV0YXRpb25zLmZvckVhY2goKCkgPT4ge1xuICAgICAgY29uc3QgbW9kYWxTZWxlY3RvciA9ICcubW9kYWwtLWdyaWRmaWVsZC1kZWxldGUtcmVsYXRpb25zJztcbiAgICAgIGNvbnN0ICRub2RlcyA9ICQoYCR7bW9kYWxTZWxlY3Rvcn0gZGl2LmRpc3BsYXktbG9naWMsJHttb2RhbFNlbGVjdG9yfSBkaXYuZGlzcGxheS1sb2dpYy1tYXN0ZXIsJHttb2RhbFNlbGVjdG9yfSBmb3JtYCk7XG4gICAgICAkbm9kZXMuZWFjaChmdW5jdGlvbiBzZXR1cE5vZGVTZWxlY3RvcnMoKSB7XG4gICAgICAgIHNldHVwU2VsZWN0b3JzKHRoaXMpO1xuICAgICAgfSk7XG4gICAgfSk7XG4gIH0pKTtcblxuICAvLyBSZXBvcnQgYW55IGNoYW5nZXMgdG8gd2hpY2ggZWxlbWVudHMgZXhpc3QgaW4gdGhlIERPTS5cbiAgLy8gQWxzbyByZXBvcnQgYW55IGNoYW5nZXMgdG8gYXR0cmlidXRlcywgc2luY2UgdGhlIFwiZGlzcGxheS1sb2dpYy1tYXN0ZXJcIiBjbGFzcyBpcyBhZGRlZFxuICAvLyBkeW5hbWljYWxseS5cbiAgY29uc3QgY29uZmlnID0ge1xuICAgIGF0dHJpYnV0ZXM6IHRydWUsXG4gICAgY2hpbGRMaXN0OiB0cnVlLFxuICAgIHN1YnRyZWU6IHRydWUsXG4gICAgY2hhcmFjdGVyRGF0YTogZmFsc2UsXG4gIH07XG5cbiAgb2JzZXJ2ZXIub2JzZXJ2ZShkb2N1bWVudCwgY29uZmlnKTtcbn0oalF1ZXJ5KSk7XG4iLCIvKiBlc2xpbnQtZGlzYWJsZS1uZXh0LWxpbmUgZnVuYy1uYW1lcyAqL1xuKGZ1bmN0aW9uICgkKSB7XG4gIC8vIFBvcnRlZCBmcm9tIFNpbHZlclN0cmlwZSA0IHNpbHZlcnN0cmlwZS1hZG1pbiBjbGllbnQvc3JjL2xlZ2FjeS9HcmlkRmllbGQuanNcbiAgJCgnLmpzLWRlbGV0ZS1yZWxhdGlvbnMtYnRuJykuZW50d2luZSh7XG4gICAgb25tYXRjaCgpIHtcbiAgICAgIC8vIFN0b3AgZ3JpZGZpZWxkIGZyb20gdHJ5aW5nIHRvIHBlcmZvcm0gYW4gYWN0aW9uLlxuICAgICAgdGhpcy5yZW1vdmVDbGFzcygnYWN0aW9uJyk7XG4gICAgICAvKiBlc2xpbnQtZGlzYWJsZS1uZXh0LWxpbmUgbm8tdW5kZXJzY29yZS1kYW5nbGUgKi9cbiAgICAgIHRoaXMuX3N1cGVyKCk7XG4gICAgICAvLyBUcmlnZ2VyIGF1dG8tb3BlblxuICAgICAgaWYgKHRoaXMuZGF0YSgnc3RhdGUnKSA9PT0gJ29wZW4nKSB7XG4gICAgICAgIHRoaXMub3Blbm1vZGFsKCk7XG4gICAgICB9XG4gICAgfSxcbiAgICBvbmNsaWNrKGUpIHtcbiAgICAgIGUucHJldmVudERlZmF1bHQoKTtcbiAgICAgIHRoaXMub3Blbm1vZGFsKCk7XG4gICAgfSxcblxuICAgIG9wZW5tb2RhbCgpIHtcbiAgICAgIC8vIFJlbW92ZSBleGlzdGluZyBtb2RhbFxuICAgICAgbGV0IG1vZGFsID0gJCh0aGlzLmRhdGEoJ3RhcmdldCcpKTtcbiAgICAgIGNvbnN0IG5ld01vZGFsID0gJCh0aGlzLmRhdGEoJ21vZGFsJykpO1xuXG4gICAgICBpZiAobW9kYWwubGVuZ3RoIDwgMSkge1xuICAgICAgICAvLyBBZGQgbW9kYWwgdG8gZW5kIG9mIGJvZHkgdGFnXG4gICAgICAgIG1vZGFsID0gbmV3TW9kYWw7XG4gICAgICAgIG1vZGFsLmFwcGVuZFRvKGRvY3VtZW50LmJvZHkpO1xuICAgICAgfSBlbHNlIHtcbiAgICAgICAgLy8gUmVwbGFjZSBpbm5lciBjb250ZW50XG4gICAgICAgIG1vZGFsLmlubmVySFRNTCA9IG5ld01vZGFsLmlubmVySFRNTDtcbiAgICAgIH1cblxuICAgICAgLy8gQXBwbHkgYmFja2Ryb3BcbiAgICAgIGxldCBiYWNrZHJvcCA9ICQoJy5tb2RhbC1iYWNrZHJvcCcpO1xuICAgICAgaWYgKGJhY2tkcm9wLmxlbmd0aCA8IDEpIHtcbiAgICAgICAgYmFja2Ryb3AgPSAkKCc8ZGl2IGNsYXNzPVwibW9kYWwtYmFja2Ryb3AgZmFkZVwiPjwvZGl2PicpO1xuICAgICAgICBiYWNrZHJvcC5hcHBlbmRUbyhkb2N1bWVudC5ib2R5KTtcbiAgICAgIH1cblxuICAgICAgZnVuY3Rpb24gY2xvc2VNb2RhbCgpIHtcbiAgICAgICAgYmFja2Ryb3AucmVtb3ZlQ2xhc3MoJ3Nob3cnKTtcbiAgICAgICAgbW9kYWwucmVtb3ZlQ2xhc3MoJ3Nob3cnKTtcbiAgICAgICAgc2V0VGltZW91dCgoKSA9PiB7XG4gICAgICAgICAgYmFja2Ryb3AucmVtb3ZlKCk7XG4gICAgICAgIH0sIDE1MCk7IC8vIFNpbXVsYXRlIHRoZSBib290c3RyYXAgb3V0LXRyYW5zaXRpb24gcGVyaW9kXG4gICAgICB9XG5cbiAgICAgIC8vIFNldCBjbG9zZSBhY3Rpb25cbiAgICAgIG1vZGFsLmZpbmQoJ1tkYXRhLWRpc21pc3NdJykuYWRkKCcubW9kYWwtYmFja2Ryb3AnKVxuICAgICAgICAub24oJ2NsaWNrJywgKCkgPT4ge1xuICAgICAgICAgIGNsb3NlTW9kYWwoKTtcbiAgICAgICAgfSk7XG5cbiAgICAgICQoZG9jdW1lbnQpLm9uKCdrZXlkb3duJywgKGUpID0+IHtcbiAgICAgICAgaWYgKGUua2V5Q29kZSA9PT0gMjcpIHsgLy8gRXNjYXBlIGtleVxuICAgICAgICAgIGNsb3NlTW9kYWwoKTtcbiAgICAgICAgfVxuICAgICAgfSk7XG5cbiAgICAgIC8vIEZhZGUgZWFjaCBlbGVtZW50IGluICh1c2Ugc2V0VGltZW91dCB0byBlbnN1cmUgaW5pdGlhbCByZW5kZXIgYXQgb3BhY2l0eT0wIHdvcmtzKVxuICAgICAgc2V0VGltZW91dCgoKSA9PiB7XG4gICAgICAgIGJhY2tkcm9wLmFkZENsYXNzKCdzaG93Jyk7XG4gICAgICAgIG1vZGFsLmFkZENsYXNzKCdzaG93Jyk7XG4gICAgICB9LCAwKTtcbiAgICB9LFxuICB9KTtcbn0oalF1ZXJ5KSk7XG4iLCJyZXF1aXJlKCcuL2NvbXBvbmVudHMvR3JpZEZpZWxkUmVsYXRpb25zQnV0dG9uLmpzJyk7XG5yZXF1aXJlKCcuL2NvbXBvbmVudHMvTW9kYWwuanMnKTtcbiIsIi8vIHJlbW92ZWQgYnkgZXh0cmFjdC10ZXh0LXdlYnBhY2stcGx1Z2luIl0sInNvdXJjZVJvb3QiOiIifQ==