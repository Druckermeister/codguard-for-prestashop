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

            // Check if payment options are visible and enabled (not just present in DOM)
            var paymentOptions = document.querySelectorAll('input[type="radio"][name="payment-option"]');
            if (paymentOptions.length === 0) {
                console.log('[CodGuard] No payment options found - not on payment step yet');
                return;
            }

            // Check if at least one payment option is visible (not hidden by CSS)
            var hasVisiblePayment = false;
            for (var i = 0; i < paymentOptions.length; i++) {
                var option = paymentOptions[i];
                // Check if element or its parent container is visible
                if (option.offsetParent !== null || window.getComputedStyle(option).display !== 'none') {
                    hasVisiblePayment = true;
                    break;
                }
            }

            if (!hasVisiblePayment) {
                console.log('[CodGuard] Payment options exist but are not visible - not on payment step yet');
                return;
            }

            console.log('[CodGuard] Found ' + paymentOptions.length + ' visible payment options - on payment step');

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

            // If we have blocked methods, show a warning banner
            if (this.blockedMethods.length > 0) {
                this.showWarningBanner();
            }

            this.processPaymentMethods();
        },

        /**
         * Show warning banner on payment page
         */
        showWarningBanner: function() {
            // Check if banner already exists
            if (document.querySelector('.codguard-payment-warning-banner')) {
                return;
            }

            // Find the CURRENT (visible) payment step section
            var paymentStep = document.querySelector('#checkout-payment-step, .checkout-step[id*="payment"]');
            if (!paymentStep) {
                console.log('[CodGuard] Could not find payment step for warning banner');
                return;
            }

            // Find the content section within the payment step
            var paymentContent = paymentStep.querySelector('.content, .step-content, #payment-confirmation');
            var targetContainer = paymentContent || paymentStep;

            // Create warning banner
            var banner = document.createElement('div');
            banner.className = 'alert alert-warning codguard-payment-warning-banner';
            banner.style.cssText = 'margin: 15px 0; padding: 12px 20px; background-color: #fff3cd; border: 1px solid #ffc107; border-radius: 3px; color: #000000; font-size: 15px; line-height: 1.3; font-weight: 500;';
            banner.innerHTML = this.escapeHtml(this.rejectionMessage);

            // Insert at the top of payment section
            targetContainer.insertBefore(banner, targetContainer.firstChild);

            console.log('[CodGuard] Warning banner added to payment step section');
        },

        /**
         * Escape HTML to prevent XSS
         */
        escapeHtml: function(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
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

            // Only refresh if payment options are visible
            var paymentOptions = document.querySelectorAll('input[type="radio"][name="payment-option"]');
            if (paymentOptions.length === 0) {
                console.log('[CodGuard] No payment options found - skipping refresh');
                return;
            }

            // Check visibility
            var hasVisiblePayment = false;
            for (var i = 0; i < paymentOptions.length; i++) {
                if (paymentOptions[i].offsetParent !== null || window.getComputedStyle(paymentOptions[i]).display !== 'none') {
                    hasVisiblePayment = true;
                    break;
                }
            }

            if (!hasVisiblePayment) {
                console.log('[CodGuard] Payment options not visible - skipping refresh');
                return;
            }

            console.log('[CodGuard] Visible payment options found (' + paymentOptions.length + ') - processing methods');
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
