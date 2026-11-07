/**
 * Payment Portal - Client-Side Payment Flow
 *
 * Handles the checkout process for Stripe, PayPal, and Square payments
 * on the customer-facing invoice portal.
 *
 * Uses the same SDK patterns as the existing license checkout (/pricing/standard/checkout/main.js)
 * but routes payments to the business's connected account via Stripe Connect,
 * PayPal for Marketplaces, or Square OAuth.
 */

document.addEventListener("DOMContentLoaded", function () {
  var config = window.PORTAL_CONFIG;
  if (!config) return;

  var balanceDue = config.balanceDue;
  var processingFee = config.processingFee || 0;
  var totalWithFee = config.totalWithFee || balanceDue;
  var feeProviderLabel = config.feeProviderLabel || "payment provider";
  var currency = config.currency;
  var currencySymbol = config.currencySymbol;
  var apiBase = config.apiBase;
  var invoiceToken = config.invoiceToken;

  function getPaymentAmount() {
    return totalWithFee;
  }

  // Payment method selection
  var methodButtons = document.querySelectorAll(".method-btn");
  var formContainer = document.getElementById("payment-form-container");
  methodButtons.forEach(function (btn) {
    btn.addEventListener("click", function () {
      var method = this.getAttribute("data-method");

      // Update active button
      methodButtons.forEach(function (b) {
        b.classList.remove("active");
      });
      this.classList.add("active");

      if (formContainer) formContainer.style.display = "block";

      initializeMethod(method);
    });
  });

  function initializeMethod(method) {
    switch (method) {
      case "stripe":
        setupStripePayment();
        break;
      case "paypal":
        setupPayPalPayment();
        break;
      case "square":
        setupSquarePayment();
        break;
    }
  }

  // Auto-initialize if only one payment method
  if (config.singleMethod && formContainer) {
    initializeMethod(config.singleMethod);
  }

  // ============= STRIPE =============
  function setupStripePayment() {
    formContainer.innerHTML =
      '<div class="loading-payment"><div class="spinner"></div><p>Loading payment form...</p></div>';

    if (typeof Stripe === "undefined") {
      var script = document.createElement("script");
      script.src = "https://js.stripe.com/v3/";
      script.onload = initializeStripeForm;
      script.onerror = function () {
        formContainer.innerHTML =
          '<div class="payment-error-box">Failed to load Stripe. Please refresh or try another payment method.</div>';
      };
      document.head.appendChild(script);
    } else {
      initializeStripeForm();
    }
  }

  function initializeStripeForm() {
    // Validate config before rendering the form
    if (!config.stripe.publishableKey) {
      formContainer.innerHTML =
        '<div class="payment-error-box">Stripe is not configured. Please contact the business for payment instructions.</div>';
      return;
    }

    if (!config.stripe.accountId) {
      formContainer.innerHTML =
        '<div class="payment-error-box">This business has not connected their Stripe account yet. Please contact them or try another payment method.</div>';
      return;
    }

    formContainer.innerHTML =
      '<form id="portal-stripe-form">' +
      '<div class="form-group">' +
      '<label for="portal-card-holder">Cardholder Name</label>' +
      '<input type="text" id="portal-card-holder" class="form-control" required>' +
      "</div>" +
      '<div class="form-group">' +
      '<label for="portal-card-element">Card Details</label>' +
      '<div id="portal-card-element" class="form-control card-element-container"></div>' +
      '<div id="portal-card-errors" class="field-error"></div>' +
      "</div>" +
      '<div class="form-group">' +
      '<label for="portal-email">Email Address <span class="optional">(for receipt)</span></label>' +
      '<input type="email" id="portal-email" class="form-control">' +
      "</div>" +
      '<button type="submit" id="portal-stripe-submit" class="btn-submit">' +
      "Pay " +
      currencySymbol +
      totalWithFee.toFixed(2) +
      " " +
      currency +
      "</button>" +
      '<div class="payment-secured-badge">' +
      '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
      '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>' +
      '<path d="M7 11V7a5 5 0 0 1 10 0v4"></path>' +
      "</svg>" +
      "<span>Secure payment, powered by</span>" +
      '<img src="../resources/images/Stripe-logo.svg" alt="Stripe">' +
      "</div>" +
      "</form>";

    // Initialize Stripe with the connected account
    var stripe, elements, cardElement;
    try {
      stripe = Stripe(config.stripe.publishableKey, {
        stripeAccount: config.stripe.accountId,
      });
      elements = stripe.elements();

      cardElement = elements.create("card", {
        style: {
          base: {
            color: "#32325d",
            fontFamily:
              '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
            fontSmoothing: "antialiased",
            fontSize: "16px",
            "::placeholder": { color: "#aab7c4" },
          },
          invalid: { color: "#fa755a", iconColor: "#fa755a" },
        },
      });
      cardElement.mount("#portal-card-element");
    } catch (err) {
      var errorEl = document.getElementById("portal-card-errors");
      if (errorEl) {
        errorEl.innerHTML =
          '<div class="payment-error-box">Failed to load card form: ' +
          escapeHtml(err.message) +
          ". Please refresh or try another payment method.</div>";
      }
      return;
    }

    cardElement.on("change", function (event) {
      var errorEl = document.getElementById("portal-card-errors");
      if (errorEl) {
        errorEl.textContent = event.error ? event.error.message : "";
      }
    });

    var form = document.getElementById("portal-stripe-form");
    form.addEventListener("submit", async function (e) {
      e.preventDefault();

      var amount = getPaymentAmount();
      if (amount === null) return;

      var cardHolder = document.getElementById("portal-card-holder");
      var emailInput = document.getElementById("portal-email");
      var errorEl = document.getElementById("portal-card-errors");

      if (!cardHolder || !cardHolder.value.trim()) {
        if (errorEl) errorEl.textContent = "Please enter the cardholder name.";
        return;
      }

      showProcessing();

      try {
        // Create payment intent on server
        var response = await fetch(apiBase + "/checkout.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            invoice_token: invoiceToken,
            method: "stripe",
            amount: amount,
            email: emailInput ? emailInput.value.trim() : "",
          }),
        });

        var intentData = await response.json();
        if (!intentData.success) {
          hideProcessing();
          showError(intentData.message || "Failed to create payment", intentData);
          return;
        }

        var result = await stripe.confirmCardPayment(intentData.client_secret, {
          payment_method: {
            card: cardElement,
            billing_details: {
              name: cardHolder.value.trim(),
              email: emailInput ? emailInput.value.trim() : undefined,
            },
          },
        });

        if (result.error) {
          throw new Error(result.error.message);
        }

        // Process confirmed payment on server
        var processResponse = await fetch(apiBase + "/process-payment.php", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            invoice_token: invoiceToken,
            method: "stripe",
            amount: amount,
            payment_intent_id: result.paymentIntent.id,
          }),
        });

        var processData = await processResponse.json();
        if (!processData.success) {
          throw new Error(processData.message || "Failed to record payment");
        }

        showConfirmation(amount, processData.reference_number, "Stripe");
      } catch (error) {
        hideProcessing();
        showError(error.message, null);
      }
    });
  }

  // ============= PAYPAL =============
  function setupPayPalPayment() {
    formContainer.innerHTML =
      '<div id="portal-paypal-container">' +
      '<div class="loading-payment"><div class="spinner"></div><p>Loading PayPal...</p></div>' +
      "</div>";

    if (typeof paypal === "undefined") {
      // First get merchant info
      fetch(apiBase + "/checkout.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          invoice_token: invoiceToken,
          method: "paypal",
          amount: totalWithFee,
        }),
      })
        .then(function (r) {
          return r.json();
        })
        .then(function (data) {
          if (!data.success) {
            var disconnected = getDisconnectedProvider(data);
            if (disconnected) { handleProviderDisconnected(disconnected); return; }
            throw new Error(data.message);
          }

          var script = document.createElement("script");
          script.src =
            "https://www.paypal.com/sdk/js?client-id=" +
            config.paypal.clientId +
            "&currency=" +
            currency +
            "&merchant-id=" +
            data.merchant_id;
          script.onload = function () {
            initializePayPalButtons(data.merchant_id);
          };
          script.onerror = function () {
            document.getElementById("portal-paypal-container").innerHTML =
              '<div class="payment-error-box">Failed to load PayPal. Please refresh or try another payment method.</div>';
          };
          document.head.appendChild(script);
        })
        .catch(function (err) {
          document.getElementById("portal-paypal-container").innerHTML =
            '<div class="payment-error-box">' + escapeHtml(err.message) + "</div>";
        });
    } else {
      fetch(apiBase + "/checkout.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          invoice_token: invoiceToken,
          method: "paypal",
          amount: totalWithFee,
        }),
      })
        .then(function (r) {
          return r.json();
        })
        .then(function (data) {
          if (!data.success) {
            var disconnected = getDisconnectedProvider(data);
            if (disconnected) { handleProviderDisconnected(disconnected); return; }
            throw new Error(data.message);
          }
          initializePayPalButtons(data.merchant_id);
        })
        .catch(function (err) {
          document.getElementById("portal-paypal-container").innerHTML =
            '<div class="payment-error-box">' + escapeHtml(err.message) + "</div>";
        });
    }
  }

  function initializePayPalButtons(merchantId) {
    var container = document.getElementById("portal-paypal-container");
    container.innerHTML = "";

    paypal
      .Buttons({
        createOrder: function (data, actions) {
          var amount = getPaymentAmount();
          if (amount === null) return;

          return actions.order.create({
            purchase_units: [
              {
                amount: {
                  value: amount.toFixed(2),
                  currency_code: currency,
                },
                payee: {
                  merchant_id: merchantId,
                },
                description: "Invoice " + config.invoiceId,
              },
            ],
          });
        },
        onApprove: function (data, actions) {
          showProcessing();

          return actions.order.capture().then(function (details) {
            var amount = getPaymentAmount() || balanceDue;

            return fetch(apiBase + "/process-payment.php", {
              method: "POST",
              headers: { "Content-Type": "application/json" },
              body: JSON.stringify({
                invoice_token: invoiceToken,
                method: "paypal",
                amount: amount,
                order_id: data.orderID,
              }),
            })
              .then(function (r) {
                return r.json();
              })
              .then(function (result) {
                if (result.success) {
                  showConfirmation(
                    amount,
                    result.reference_number,
                    "PayPal"
                  );
                } else {
                  throw new Error(result.message || "Payment processing failed");
                }
              });
          });
        },
        onError: function (err) {
          hideProcessing();
          showError(
            "There was an error processing your PayPal payment. Please try again."
          );
        },
      })
      .render("#portal-paypal-container");
  }

  // ============= SQUARE =============
  function setupSquarePayment() {
    formContainer.innerHTML =
      '<form id="portal-square-form">' +
      '<div class="form-group">' +
      '<label for="portal-sq-card-holder">Cardholder Name</label>' +
      '<input type="text" id="portal-sq-card-holder" class="form-control" required>' +
      "</div>" +
      '<div class="form-group">' +
      '<label>Card Details</label>' +
      '<div id="portal-sq-card-container" class="form-control card-element-container">' +
      '<div class="loading-payment"><div class="spinner"></div><p>Loading...</p></div>' +
      "</div>" +
      '<div id="portal-sq-errors" class="field-error"></div>' +
      "</div>" +
      '<div class="form-group">' +
      '<label for="portal-sq-email">Email Address <span class="optional">(for receipt)</span></label>' +
      '<input type="email" id="portal-sq-email" class="form-control">' +
      "</div>" +
      '<button type="submit" id="portal-square-submit" class="btn-submit">' +
      "Pay " +
      currencySymbol +
      totalWithFee.toFixed(2) +
      " " +
      currency +
      "</button>" +
      '<div class="payment-secured-badge">' +
      '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
      '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>' +
      '<path d="M7 11V7a5 5 0 0 1 10 0v4"></path>' +
      "</svg>" +
      "<span>Secure payment, powered by</span>" +
      '<img src="../resources/images/Square-logo.svg" alt="Square">' +
      "</div>" +
      "</form>";

    // First get Square config
    fetch(apiBase + "/checkout.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        invoice_token: invoiceToken,
        method: "square",
        amount: totalWithFee,
      }),
    })
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        if (!data.success) {
          var disconnected = getDisconnectedProvider(data);
          if (disconnected) { handleProviderDisconnected(disconnected); return; }
          throw new Error(data.message);
        }
        loadSquareSDK(data.app_id, data.location_id);
      })
      .catch(function (err) {
        formContainer.innerHTML =
          '<div class="payment-error-box">' + escapeHtml(err.message) + "</div>";
      });
  }

  function loadSquareSDK(appId, locationId) {
    function initSquare() {
      var payments = window.Square.payments(appId, locationId);

      payments
        .card()
        .then(function (card) {
          var container = document.getElementById(
            "portal-sq-card-container"
          );
          if (container) container.innerHTML = "";
          card.attach("#portal-sq-card-container");

          var form = document.getElementById("portal-square-form");
          form.addEventListener("submit", async function (e) {
            e.preventDefault();

            var amount = getPaymentAmount();
            if (amount === null) return;

            var cardHolder = document.getElementById(
              "portal-sq-card-holder"
            );
            var emailInput = document.getElementById("portal-sq-email");
            var errorEl = document.getElementById("portal-sq-errors");

            if (!cardHolder || !cardHolder.value.trim()) {
              if (errorEl)
                errorEl.textContent = "Please enter the cardholder name.";
              return;
            }

            showProcessing();

            try {
              var tokenResult = await card.tokenize();
              if (tokenResult.status !== "OK") {
                throw new Error(
                  tokenResult.errors[0]?.message || "Card tokenization failed"
                );
              }

              var idempotencyKey =
                Date.now().toString() +
                Math.random().toString(36).substring(2);

              var response = await fetch(apiBase + "/checkout.php", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                  invoice_token: invoiceToken,
                  method: "square",
                  amount: amount,
                  source_id: tokenResult.token,
                  idempotency_key: idempotencyKey,
                  email: emailInput ? emailInput.value.trim() : "",
                }),
              });

              var result = await response.json();
              if (result.success) {
                showConfirmation(
                  amount,
                  result.reference_number || result.provider_payment_id,
                  "Square"
                );
              } else {
                hideProcessing();
                showError(result.message || "Payment failed", result);
                return;
              }
            } catch (error) {
              hideProcessing();
              showError(error.message, null);
            }
          });
        })
        .catch(function (err) {
          var container = document.getElementById(
            "portal-sq-card-container"
          );
          if (container) {
            container.innerHTML =
              '<div class="payment-error-box">Failed to load card form: ' +
              escapeHtml(err.message) +
              "</div>";
          }
        });
    }

    if (window.Square) {
      initSquare();
      return;
    }

    var isSandbox = appId.startsWith("sandbox-");
    var script = document.createElement("script");
    script.src = isSandbox
      ? "https://sandbox.web.squarecdn.com/v1/square.js"
      : "https://web.squarecdn.com/v1/square.js";
    script.onload = initSquare;
    script.onerror = function () {
      formContainer.innerHTML =
        '<div class="payment-error-box">Failed to load Square. Please refresh or try another payment method.</div>';
    };
    document.head.appendChild(script);
  }

  // ============= UI HELPERS =============

  function showProcessing() {
    if (document.getElementById('portal-processing')) return;
    var overlay = document.createElement("div");
    overlay.className = "processing-overlay";
    overlay.id = "portal-processing";
    overlay.innerHTML =
      '<div class="spinner"></div>' +
      "<h2>Processing Your Payment</h2>" +
      "<p>Please do not close this window or refresh the page.</p>";
    document.body.appendChild(overlay);
  }

  function hideProcessing() {
    var overlay = document.getElementById("portal-processing");
    if (overlay) overlay.remove();
  }

  /**
   * Check if an error response indicates a provider is no longer connected.
   * Returns the provider name if so, or null otherwise.
   */
  function getDisconnectedProvider(responseData) {
    var code = responseData.errorCode || "";
    var disconnectedCodes = {
      STRIPE_NOT_CONNECTED: "stripe",
      PAYPAL_NOT_CONNECTED: "paypal",
      SQUARE_NOT_CONNECTED: "square",
      SQUARE_NOT_CONFIGURED: "square",
    };
    return disconnectedCodes[code] || null;
  }

  /**
   * Handle a payment method that was disconnected after page load.
   * Hides the method button and shows a helpful message.
   */
  function handleProviderDisconnected(provider) {
    // Hide the button for the disconnected method
    var btn = document.querySelector('.method-btn[data-method="' + provider + '"]');
    if (btn) btn.style.display = "none";

    // Check if there are other methods still available
    var remainingButtons = document.querySelectorAll(".method-btn");
    var visibleCount = 0;
    remainingButtons.forEach(function (b) {
      if (b.style.display !== "none") visibleCount++;
    });

    var providerName = provider.charAt(0).toUpperCase() + provider.slice(1);

    if (visibleCount > 0) {
      formContainer.innerHTML =
        '<div class="payment-error-box">' +
        providerName +
        " is no longer available for this invoice. Please select another payment method." +
        "</div>";
      formContainer.style.display = "block";
    } else {
      // No methods left - hide the entire payment section and show a message
      var paymentSection = document.getElementById("payment-section");
      if (paymentSection) {
        paymentSection.innerHTML =
          '<div class="invoice-no-methods">' +
          "<p>Online payments are not currently available for this invoice. " +
          'Please contact the business for payment instructions, or <a href="" onclick="location.reload();return false;">refresh the page</a> to check again.</p>' +
          "</div>";
      }
    }
  }

  function showError(message, responseData) {
    // Check if this is a "provider disconnected" error
    if (responseData) {
      var disconnected = getDisconnectedProvider(responseData);
      if (disconnected) {
        handleProviderDisconnected(disconnected);
        return;
      }
    }

    // Find or create error container
    var container = document.querySelector(".field-error:last-of-type");
    if (!container) {
      container = document.createElement("div");
      container.className = "field-error";
      if (formContainer) formContainer.appendChild(container);
    }
    container.innerHTML =
      '<div class="payment-error-box">' +
      "<strong>Error:</strong> " +
      escapeHtml(message) +
      "<p>Please try again or contact support if the problem persists.</p>" +
      "</div>";
  }

  function showConfirmation(amount, referenceNumber, method) {
    hideProcessing();

    // Hide payment section
    var paymentSection = document.getElementById("payment-section");
    if (paymentSection) paymentSection.style.display = "none";

    // Show confirmation
    var confirmation = document.getElementById("payment-confirmation");
    if (confirmation) {
      confirmation.style.display = "block";

      var confAmount = document.getElementById("conf-amount");
      var confRef = document.getElementById("conf-reference");
      var confMethod = document.getElementById("conf-method");
      var confDate = document.getElementById("conf-date");

      if (confAmount)
        confAmount.textContent =
          currencySymbol + amount.toFixed(2) + " " + currency;
      if (confRef) confRef.textContent = referenceNumber;
      if (confMethod) confMethod.textContent = method;
      if (confDate)
        confDate.textContent = new Date().toLocaleDateString("en-US", {
          year: "numeric",
          month: "long",
          day: "numeric",
        });

      // Update balance display (balance decreases by invoice amount, not fee)
      var balanceEl = document.querySelector(".total-row-balance span:last-child");
      var invoiceAmount = amount - processingFee;
      var newBalance = Math.max(0, balanceDue - invoiceAmount);
      if (balanceEl) {
        balanceEl.textContent =
          currencySymbol + newBalance.toFixed(2) + " " + currency;
      }

      // Update status badge and hide fee rows
      if (newBalance <= 0) {
        var badges = document.querySelectorAll(".status-badge");
        badges.forEach(function (badge) {
          badge.className = "status-badge status-paid";
          badge.textContent = "Paid";
        });

        // Hide processing fee and "Amount to Pay" rows since invoice is fully paid
        var feeRow = document.querySelector(".total-row-fee");
        var chargeRow = document.querySelector(".total-row-charge");
        if (feeRow) feeRow.style.display = "none";
        if (chargeRow) chargeRow.style.display = "none";
      }

      confirmation.scrollIntoView({ behavior: "smooth", block: "center" });
    }
  }

  function escapeHtml(str) {
    var div = document.createElement("div");
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
  }
});
