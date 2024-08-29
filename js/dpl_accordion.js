/*(function (Drupal) {
    'use strict';
  
    // Define the behavior.
    Drupal.behaviors.accordionBehavior = {
      attach: function (context, settings) {
        const accordions = context.querySelectorAll('.accordion');
        accordions.forEach(function (accordion) {
          const collapseElements = accordion.querySelectorAll('[data-bs-toggle="collapse"]');
          collapseElements.forEach(function (element) {
            element.addEventListener('click', function (event) {
              // Prevent default form submission or other default actions
              event.preventDefault();
              const targetSelector = this.getAttribute('data-bs-target');
              const targetElement = document.querySelector(targetSelector);
            });
          });
        });
      }
    };
  })(Drupal);
 */

  (function (Drupal) {
    'use strict';
  
    // Define the behavior.
    Drupal.behaviors.accordionBehavior = {
      attach: function (context, settings) {
        // Ensure that the behavior is applied to elements in the context.
        const accordions = context.querySelectorAll('.accordion');
  
        accordions.forEach(function (accordion) {
          const collapseElements = accordion.querySelectorAll('[data-bs-toggle="collapse"]');
  
          collapseElements.forEach(function (element) {
            element.addEventListener('click', function (event) {
              // Prevent default form submission or other default actions
              event.preventDefault();
  
              const targetSelector = this.getAttribute('data-bs-target');
              const targetElement = document.querySelector(targetSelector);
  
              // Check if the target element exists
              if (targetElement) {
                // Initialize the Bootstrap Collapse instance
                const bsCollapse = new bootstrap.Collapse(targetElement, {
                  toggle: true
                });
  
                // Close all other collapsible items in the same accordion
                accordion.querySelectorAll('.collapse').forEach(function (collapse) {
                  if (collapse !== targetElement && collapse.classList.contains('show')) {
                    new bootstrap.Collapse(collapse, {
                      toggle: false
                    }).hide();
                  }
                });
              }
            });
          });
        });
      }
    };
  })(Drupal);