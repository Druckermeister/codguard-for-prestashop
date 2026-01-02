/**
 * CodGuard Payment Method Manager
 * Handles disabling payment methods and displaying descriptions dynamically
 */
(function() {
    'use strict';

    var CodGuardPaymentManager = {
        blockedMethods: [],
        rejectionMessage: '',
        initialized: false,

        /**
         * Initialize the payment manager
         */
        init: function() {
            if (this.initialized) {
                return;
            }

            console.log('[CodGuard] Initializing payment manager');

            // Check if we're on the payment step (not address step)
            // We need to be very specific - only run if actual payment methods are visible
            var paymentRadios = document.querySelectorAll('input[type="radio"][name="payment-option"], input[name*="payment"]');
            if (paymentRadios.length === 0) {
                console.log('[CodGuard] No payment radio buttons found - skipping (not on payment step yet)');
                return;
            }

            console.log('[CodGuard] Found ' + paymentRadios.length + ' payment options, proceeding with initialization');

            // Try to get configuration from PrestaShop global variable (preferred method)
            if (typeof prestashop !== 'undefined' && prestashop.codguardConfig) {
                console.log('[CodGuard] Configuration found in prestashop.codguardConfig');
                this.blockedMethods = prestashop.codguardConfig.blockedMethods || [];
                this.rejectionMessage = prestashop.codguardConfig.rejectionMessage || 'This payment method is not available.';

                console.log('[CodGuard] Configuration loaded from global:', {
                    blockedMethods: this.blockedMethods,
                    rejectionMessage: this.rejectionMessage,
                    email: prestashop.codguardConfig.email,
                    rating: prestashop.codguardConfig.rating
                });
            }
            // Fallback: try data attribute
            else {
                var configElement = document.querySelector('[data-codguard-config]');
                if (!configElement) {
                    console.log('[CodGuard] No configuration found (neither global nor data attribute)');
                    return;
                }

                try {
                    var config = JSON.parse(configElement.getAttribute('data-codguard-config'));
                    this.blockedMethods = config.blockedMethods || [];
                    this.rejectionMessage = config.rejectionMessage || 'This payment method is not available.';

                    console.log('[CodGuard] Configuration loaded from data attribute:', {
                        blockedMethods: this.blockedMethods,
                        rejectionMessage: this.rejectionMessage
                    });
                } catch (e) {
                    console.error('[CodGuard] Failed to parse configuration:', e);
                    return;
                }
            }

            this.initialized = true;
            this.processPaymentMethods();
        },

        /**
         * Find payment method element by various selectors
         */
        findPaymentElement: function(methodName) {
            var selectors = [
                // By data attribute
                '[data-module-name="' + methodName + '"]',
                // By input value
                'input[value="' + methodName + '"]',
                // By ID
                '#payment-option-' + methodName,
                '#' + methodName + '-container',
                // By class
                '.payment-option.' + methodName,
                '.' + methodName
            ];

            for (var i = 0; i < selectors.length; i++) {
                var elements = document.querySelectorAll(selectors[i]);
                if (elements.length > 0) {
                    console.log('[CodGuard] Found ' + elements.length + ' element(s) with selector: ' + selectors[i]);
                    return elements;
                }
            }

            return [];
        },

        /**
         * Find payment container (parent wrapper)
         */
        findPaymentContainer: function(element) {
            // Try to find the closest payment option container
            var container = element.closest('.payment-option') ||
                          element.closest('.custom-radio') ||
                          element.closest('[class*="payment"]') ||
                          element.closest('div[id*="payment"]');

            // If no specific container found, use parent element
            if (!container && element.parentElement) {
                container = element.parentElement;
            }

            return container;
        },

        /**
         * Disable a payment method
         */
        disablePaymentMethod: function(element) {
            console.log('[CodGuard] Disabling payment element:', element);

            // Find the interactive element (input, radio, checkbox)
            var interactiveElement = element;
            if (element.tagName !== 'INPUT') {
                interactiveElement = element.querySelector('input[type="radio"], input[type="checkbox"]') || element;
            }

            // Set disabled property
            if (interactiveElement && typeof interactiveElement.disabled !== 'undefined') {
                interactiveElement.disabled = true;
                console.log('[CodGuard] Set disabled=true on:', interactiveElement);
            }

            // Find container for visual feedback
            var container = this.findPaymentContainer(element);
            if (container) {
                // Add visual feedback class
                container.classList.add('codguard-disabled-payment-method');
                console.log('[CodGuard] Added disabled class to container');

                // Add description
                this.addPaymentDescription(container);
            }
        },

        /**
         * Add description element to payment method
         */
        addPaymentDescription: function(container) {
            // Check if description already exists
            if (container.querySelector('.codguard-payment-description')) {
                console.log('[CodGuard] Description already exists');
                return;
            }

            // Create description element
            var descriptionElement = document.createElement('p');
            descriptionElement.className = 'codguard-payment-description';
            descriptionElement.textContent = this.rejectionMessage;

            // Find the best place to insert the description
            var lastChild = container.lastElementChild;
            if (lastChild) {
                // Insert after the last child
                lastChild.insertAdjacentElement('afterend', descriptionElement);
            } else {
                // Append to container
                container.appendChild(descriptionElement);
            }

            console.log('[CodGuard] Added description element');
        },

        /**
         * Process all payment methods
         */
        processPaymentMethods: function() {
            var self = this;

            console.log('[CodGuard] Processing payment methods, blocked:', this.blockedMethods);

            this.blockedMethods.forEach(function(methodName) {
                console.log('[CodGuard] Processing blocked method: ' + methodName);

                // Find elements for this payment method
                var elements = self.findPaymentElement(methodName);

                if (elements.length === 0) {
                    // Try to find by text content
                    console.log('[CodGuard] No direct match found, searching by text content');
                    elements = self.findByTextContent(methodName);
                }

                // Disable all found elements
                Array.prototype.forEach.call(elements, function(element) {
                    self.disablePaymentMethod(element);
                });

                if (elements.length === 0) {
                    console.warn('[CodGuard] No elements found for payment method: ' + methodName);
                }
            });
        },

        /**
         * Find payment methods by text content
         */
        findByTextContent: function(methodName) {
            var foundElements = [];
            var searchTerms = [
                'cash on delivery',
                'payment on delivery',
                'cod',
                methodName.toLowerCase()
            ];

            var allLabels = document.querySelectorAll('label, .payment-option, [class*="payment"]');

            Array.prototype.forEach.call(allLabels, function(element) {
                var text = element.textContent.toLowerCase();

                for (var i = 0; i < searchTerms.length; i++) {
                    if (text.indexOf(searchTerms[i]) !== -1) {
                        foundElements.push(element);
                        console.log('[CodGuard] Found by text content: ' + searchTerms[i]);
                        break;
                    }
                }
            });

            return foundElements;
        },

        /**
         * Re-process payment methods (for dynamically loaded content)
         */
        refresh: function() {
            console.log('[CodGuard] Refreshing payment methods');
            this.processPaymentMethods();
        }
    };

    /**
     * Initialize when DOM is ready
     */
    function initializeWhenReady() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                CodGuardPaymentManager.init();
            });
        } else {
            CodGuardPaymentManager.init();
        }
    }

    /**
     * Run with delays to catch dynamically loaded content
     */
    function initializeWithRetries() {
        initializeWhenReady();

        // Retry after delays to catch dynamic content
        setTimeout(function() { CodGuardPaymentManager.refresh(); }, 100);
        setTimeout(function() { CodGuardPaymentManager.refresh(); }, 500);
        setTimeout(function() { CodGuardPaymentManager.refresh(); }, 1000);
    }

    // Start initialization
    initializeWithRetries();

    // Expose to global scope for manual refresh if needed
    window.CodGuardPaymentManager = CodGuardPaymentManager;

})();
