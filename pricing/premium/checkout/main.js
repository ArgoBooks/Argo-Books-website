document.addEventListener("DOMContentLoaded", function () {
  // Get subscription details from PHP. The method is whitelisted server-side;
  // an empty string means the buyer has not picked one yet.
  const subscription = window.AI_SUBSCRIPTION;

  // Update the form based on payment method
  const formTitle = document.querySelector(".checkout-form h2");

  const METHOD_LABELS = { paypal: "PayPal", stripe: "Stripe", square: "Square" };
  const METHOD_SETUP = {
    paypal: setupPayPalCheckout,
    stripe: setupStripeCheckout,
    square: setupSquareCheckout,
  };
  const METHOD_CONTAINER_IDS = {
    paypal: "paypal-button-container",
    stripe: "stripe-container",
    square: "square-container",
  };

  // Each setup function injects its provider's SDK <script> and mounts a card
  // element, so calling one twice would load the script twice and double-mount.
  // Initialise each at most once; after that, switching is only show/hide, which
  // is what lets the picker work without a page reload.
  const initialised = {};

  function hideAllPaymentForms() {
    Object.values(METHOD_CONTAINER_IDS).forEach((id) => {
      const el = document.getElementById(id);
      if (el) {
        el.style.display = "none";
      }
    });
  }

  // Show a payment form. Pass updateUrl on user-driven switches so a manual
  // refresh (or a copied link) lands back on the same method.
  function activateMethod(method, updateUrl) {
    if (!METHOD_SETUP[method]) {
      return;
    }

    formTitle.textContent = METHOD_LABELS[method] + " Checkout";

    document.querySelectorAll(".payment-btn").forEach((btn) => {
      btn.classList.toggle("is-selected", btn.dataset.method === method);
    });

    hideAllPaymentForms();

    if (initialised[method]) {
      // The setup functions reveal their own container on first run; on a
      // repeat visit we just undo hideAllPaymentForms().
      const el = document.getElementById(METHOD_CONTAINER_IDS[method]);
      if (el) {
        el.style.display = "block";
      }
    } else {
      initialised[method] = true;
      METHOD_SETUP[method]();
    }

    subscription.method = method;

    if (updateUrl) {
      const url = new URL(window.location.href);
      url.searchParams.set("method", method);
      history.replaceState(null, "", url);
    }
  }

  document.querySelectorAll(".payment-btn").forEach((btn) => {
    btn.addEventListener("click", function (event) {
      event.preventDefault();
      activateMethod(this.dataset.method, true);
    });
  });

  // Billing cycle switching, also without a reload. Every figure below was
  // computed server-side into window.CHECKOUT_CYCLES; nothing here does money
  // math, and process-subscription.php recomputes the real charge from the
  // posted billing cycle anyway ("never trust client amount").
  const CYCLES = window.CHECKOUT_CYCLES || {};

  // The PayPal SDK is loaded with intent=subscription only when a plan ID
  // exists for the cycle in play. If the two cycles disagree (one plan ID
  // configured, the other not) the loaded SDK is wrong for the target cycle and
  // the buttons cannot simply be re-rendered, so fall back to a reload.
  function paypalSubscriptionMode(cycle) {
    const cfg = window.PAYMENT_CONFIG.paypal;
    const planId = cycle === "yearly" ? cfg.yearlyPlanId : cfg.monthlyPlanId;
    return !!(planId && planId.length > 0);
  }

  function applyCycle(cycle, href) {
    const figures = CYCLES[cycle];
    if (!figures || cycle === subscription.billing) {
      return false;
    }

    // PayPal binds its plan ID at render time, so its buttons must be rebuilt
    // for the new cycle. setupPayPalCheckout() re-renders in place once the SDK
    // is loaded (it early-returns past the script injection), but it cannot
    // change the SDK's intent, hence the mode check.
    const paypalActive = subscription.method === "paypal";
    if (paypalActive && paypalSubscriptionMode(cycle) !== paypalSubscriptionMode(subscription.billing)) {
      window.location.href = href;
      return true;
    }

    subscription.billing = cycle;
    subscription.basePrice = figures.base;
    subscription.finalPrice = figures.base;
    subscription.processingFee = figures.fee;
    subscription.totalCharge = figures.total;

    const setAll = (attr, value) => {
      document.querySelectorAll("[" + attr + "]").forEach((el) => {
        el.textContent = value;
      });
    };
    setAll("data-cycle-label", figures.label);
    setAll("data-cycle-base", figures.baseDisplay);
    setAll("data-cycle-fee", figures.feeDisplay);
    setAll("data-cycle-total", figures.totalDisplay);
    setAll("data-cycle-renewal", figures.totalDisplay);
    setAll("data-cycle-period", figures.period);

    document.querySelectorAll("[data-cycle-fee-row]").forEach((row) => {
      row.style.display = figures.fee > 0 ? "" : "none";
    });

    // Submit button labels are owned by JS (it rewrites them on submit and on
    // error), so they get set here rather than via data-cycle spans. Square's
    // button is built by setupSquareCheckout and would otherwise keep the
    // amount it was rendered with.
    const submitLabel = subscription.isMonthlyWithCredit
      ? "Subscribe - $0.00 Today (Credit Applied)"
      : "Subscribe - $" + figures.totalDisplay + " CAD/" + figures.period;
    ["stripe-submit-btn", "square-submit-btn"].forEach((id) => {
      const btn = document.getElementById(id);
      if (btn) {
        btn.textContent = submitLabel;
      }
    });

    document.querySelectorAll(".cycle-switcher-btn").forEach((btn) => {
      const isActive = btn.dataset.cycle === cycle;
      btn.classList.toggle("active", isActive);
      if (isActive) {
        btn.setAttribute("aria-current", "true");
      } else {
        btn.removeAttribute("aria-current");
      }
    });

    const url = new URL(window.location.href);
    url.searchParams.set("billing", cycle);
    history.replaceState(null, "", url);

    if (paypalActive) {
      setupPayPalCheckout();
    }
    return false;
  }

  document.querySelectorAll(".cycle-switcher-btn").forEach((link) => {
    link.addEventListener("click", function (event) {
      event.preventDefault();
      applyCycle(this.dataset.cycle, this.href);
    });
  });

  // PHP always resolves a method (Stripe unless the URL or the cycle-switch flow says otherwise), so this normally mounts the preselected form on load.
  activateMethod(subscription.method, false);

  function setupPayPalCheckout() {
    // Create or clear PayPal button container
    let paypalContainer = document.getElementById("paypal-button-container");
    if (!paypalContainer) {
      paypalContainer = document.createElement("div");
      paypalContainer.id = "paypal-button-container";
      document.querySelector(".checkout-form").appendChild(paypalContainer);
    }

    // Get the appropriate PayPal plan ID based on billing cycle
    const paypalConfig = window.PAYMENT_CONFIG.paypal;
    const planId = subscription.billing === "yearly"
      ? paypalConfig.yearlyPlanId
      : paypalConfig.monthlyPlanId;

    // Determine if we should use subscription mode (requires plan IDs)
    const useSubscriptionMode = planId && planId.length > 0;

    // Check if PayPal is defined
    if (typeof paypal === "undefined") {
      // Show loading message
      paypalContainer.innerHTML = `
      <div style="text-align: center; padding: 40px 0;">
        <p>Loading PayPal...</p>
        <div class="loading-spinner"></div>
      </div>
    `;

      // Load PayPal SDK dynamically with appropriate intent
      const paypalScript = document.createElement("script");
      if (useSubscriptionMode) {
        paypalScript.src = `https://www.paypal.com/sdk/js?client-id=${paypalConfig.clientId}&currency=CAD&vault=true&intent=subscription`;
      } else {
        paypalScript.src = `https://www.paypal.com/sdk/js?client-id=${paypalConfig.clientId}&currency=CAD`;
      }
      paypalScript.onload = initializePayPal;
      paypalScript.onerror = () => {
        paypalContainer.innerHTML = `
        <div style="text-align: center; padding: 40px 0; color: #b91c1c;">
          <p>Failed to load PayPal. Please refresh the page or try another payment method.</p>
        </div>
      `;
      };
      document.head.appendChild(paypalScript);
    } else {
      initializePayPal();
    }

    function initializePayPal() {
      // Clear loading message
      paypalContainer.innerHTML = "";

      // Configure buttons based on mode
      const buttonConfig = {
        style: {
          shape: "rect",
          color: "blue",
          layout: "vertical",
          label: useSubscriptionMode ? "subscribe" : "pay",
        },
        onError: function (err) {
          console.error("PayPal error:", err);
          paypalContainer.innerHTML = `
            <div style="text-align: center; padding: 40px 0; color: #b91c1c;">
              <p>Payment failed. Please try again or choose a different payment method.</p>
              <button onclick="location.reload()" style="margin-top: 15px; padding: 10px 20px; cursor: pointer;">Retry</button>
            </div>
          `;
        },
      };

      if (useSubscriptionMode) {
        // Use PayPal Subscriptions API for true recurring billing
        buttonConfig.createSubscription = function (data, actions) {
          return actions.subscription.create({
            plan_id: planId,
            custom_id: `user_${subscription.userId}`,
            application_context: {
              shipping_preference: "NO_SHIPPING",
            },
          });
        };

        buttonConfig.onApprove = function (data, actions) {
          // Process the successful subscription on our server
          return fetch("process-subscription.php", {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
            },
            body: JSON.stringify({
              subscriptionID: data.subscriptionID,
              orderID: data.orderID,
              paypal_subscription_id: data.subscriptionID,
              amount: subscription.totalCharge.toFixed(2),
              currency: "CAD",
              billing: subscription.billing,
              hasDiscount: subscription.hasDiscount,
              premiumLicenseKey: subscription.licenseKey,
              status: "completed",
              email: subscription.userEmail,
              payment_method: "paypal",
              user_id: subscription.userId,
              is_paypal_subscription: true,
              update_payment_method: subscription.isUpdatingPaymentMethod || false,
              cycle_switch: subscription.isCycleSwitch || false,
            }),
          })
            .then((response) => response.json())
            .then((result) => {
              if (result.success) {
                // Cycle switch: surface any warnings, then return to
                // subscription page (not the thank-you/welcome page).
                if (subscription.isCycleSwitch) {
                  if (Array.isArray(result.warnings) && result.warnings.length > 0) {
                    alert(result.message + "\n\n" + result.warnings.join("\n\n"));
                  }
                  window.location.href = "../../../community/users/subscription.php";
                  return;
                }
                window.location.href =
                  "../thank-you/?subscription_id=" +
                  result.subscription_id +
                  "&email=" +
                  encodeURIComponent(subscription.userEmail);
              } else {
                alert(
                  result.error || "Payment was successful but there was an error setting up your subscription. Please contact support."
                );
              }
            })
            .catch((error) => {
              console.error("Error:", error);
              alert("An error occurred. Please contact support.");
            });
        };
      } else {
        // Use one-time payment (fallback when no plan IDs configured)
        buttonConfig.createOrder = function (data, actions) {
          return actions.order.create({
            purchase_units: [
              {
                description: `Argo Books Premium Subscription (${subscription.billing})`,
                amount: {
                  value: subscription.totalCharge.toFixed(2),
                  currency_code: "CAD",
                },
              },
            ],
          });
        };

        buttonConfig.onApprove = function (data, actions) {
          return actions.order.capture().then(function (details) {
            // Process the successful payment on our server
            return fetch("process-subscription.php", {
              method: "POST",
              headers: {
                "Content-Type": "application/json",
              },
              body: JSON.stringify({
                orderID: data.orderID,
                payerID: data.payerID,
                amount: subscription.totalCharge.toFixed(2),
                currency: "CAD",
                billing: subscription.billing,
                hasDiscount: subscription.hasDiscount,
                premiumLicenseKey: subscription.licenseKey,
                status: "completed",
                payer_email: details.payer.email_address,
                payer_name:
                  details.payer.name.given_name +
                  " " +
                  details.payer.name.surname,
                payment_method: "paypal",
                user_id: subscription.userId,
                update_payment_method: subscription.isUpdatingPaymentMethod || false,
              }),
            })
              .then((response) => response.json())
              .then((result) => {
                if (result.success) {
                  window.location.href =
                    "../thank-you/?subscription_id=" +
                    result.subscription_id +
                    "&email=" +
                    encodeURIComponent(details.payer.email_address);
                } else {
                  alert(
                    "Payment was successful but there was an error setting up your subscription. Please contact support."
                  );
                }
              })
              .catch((error) => {
                console.error("Error:", error);
                alert("An error occurred. Please contact support.");
              });
          });
        };
      }

      paypal.Buttons(buttonConfig).render("#paypal-button-container");
    }
  }

  function setupStripeCheckout() {
    const stripeContainer = document.getElementById("stripe-container");
    stripeContainer.style.display = "block";

    // Load Stripe
    const stripeScript = document.createElement("script");
    stripeScript.src = "https://js.stripe.com/v3/";
    stripeScript.onload = initializeStripe;
    document.head.appendChild(stripeScript);

    function initializeStripe() {
      const stripe = Stripe(window.PAYMENT_CONFIG.stripe.publishableKey);
      const elements = stripe.elements();
      const cardElement = elements.create("card", {
        style: {
          base: {
            fontSize: "16px",
            color: "#32325d",
            fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
            "::placeholder": {
              color: "#aab7c4",
            },
          },
          invalid: {
            color: "#fa755a",
            iconColor: "#fa755a",
          },
        },
      });

      cardElement.mount("#card-element");

      // Handle form submission
      const form = document.getElementById("stripe-payment-form");
      form.addEventListener("submit", async (event) => {
        event.preventDefault();

        const submitButton = document.getElementById("stripe-submit-btn");
        submitButton.disabled = true;
        submitButton.textContent = "Processing...";

        // Show processing overlay
        const processingOverlay = document.createElement("div");
        processingOverlay.className = "processing-overlay";
        processingOverlay.innerHTML = `
          <div class="spinner"></div>
          <h2>Processing Your Payment</h2>
          <p>Please do not close this window or refresh the page.</p>
        `;
        document.body.appendChild(processingOverlay);

        const cardHolder = document.getElementById("card-holder").value;
        const email = document.getElementById("email").value;

        try {
          const { paymentMethod, error } = await stripe.createPaymentMethod({
            type: "card",
            card: cardElement,
            billing_details: {
              name: cardHolder,
              email: email,
            },
          });

          if (error) {
            document.getElementById("card-errors").textContent = error.message;
            const overlay = document.querySelector(".processing-overlay");
            if (overlay) overlay.remove();
            submitButton.disabled = false;
            submitButton.textContent = subscription.isMonthlyWithCredit
              ? "Subscribe - $0.00 Today (Credit Applied)"
              : `Subscribe - $${subscription.totalCharge.toFixed(2)} CAD/${subscription.billing === "yearly" ? "year" : "month"}`;
            return;
          }

          // Send to server
          const response = await fetch("process-subscription.php", {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
            },
            body: JSON.stringify({
              payment_method_id: paymentMethod.id,
              email: email,
              name: cardHolder,
              amount: subscription.totalCharge.toFixed(2),
              currency: "CAD",
              billing: subscription.billing,
              hasDiscount: subscription.hasDiscount,
              premiumLicenseKey: subscription.licenseKey,
              payment_method: "stripe",
              user_id: subscription.userId,
              update_payment_method: subscription.isUpdatingPaymentMethod || false,
            }),
          });

          const result = await response.json();

          if (result.success) {
            window.location.href =
              "../thank-you/?subscription_id=" +
              result.subscription_id +
              "&email=" +
              encodeURIComponent(email);
          } else if (result.requires_action) {
            // Handle 3D Secure
            const { error: confirmError } = await stripe.confirmCardPayment(
              result.client_secret
            );
            if (confirmError) {
              document.getElementById("card-errors").textContent =
                confirmError.message;
              const overlay = document.querySelector(".processing-overlay");
              if (overlay) overlay.remove();
              submitButton.disabled = false;
              submitButton.textContent = subscription.isMonthlyWithCredit
                ? "Subscribe - $0.00 Today (Credit Applied)"
                : `Subscribe - $${subscription.totalCharge.toFixed(2)} CAD/${subscription.billing === "yearly" ? "year" : "month"}`;
            } else {
              window.location.href =
                "../thank-you/?subscription_id=" +
                result.subscription_id +
                "&email=" +
                encodeURIComponent(email);
            }
          } else {
            document.getElementById("card-errors").textContent =
              result.error || "Payment failed. Please try again.";
            const overlay = document.querySelector(".processing-overlay");
            if (overlay) overlay.remove();
            submitButton.disabled = false;
            submitButton.textContent = subscription.isMonthlyWithCredit
              ? "Subscribe - $0.00 Today (Credit Applied)"
              : `Subscribe - $${subscription.totalCharge.toFixed(2)} CAD/${subscription.billing === "yearly" ? "year" : "month"}`;
          }
        } catch (err) {
          console.error("Error:", err);
          document.getElementById("card-errors").textContent =
            "An error occurred. Please try again.";
          const overlay = document.querySelector(".processing-overlay");
          if (overlay) overlay.remove();
          submitButton.disabled = false;
          submitButton.textContent = subscription.isMonthlyWithCredit
            ? "Subscribe - $0.00 Today (Credit Applied)"
            : `Subscribe - $${subscription.totalCharge.toFixed(2)} CAD/${subscription.billing === "yearly" ? "year" : "month"}`;
        }
      });
    }
  }

  function setupSquareCheckout() {
    const squareContainer = document.getElementById("square-container");
    squareContainer.style.display = "block";

    // Show loading state
    squareContainer.innerHTML = `
      <div class="loading-square">
        <div class="spinner"></div>
        <p>Loading Square payment form...</p>
      </div>
    `;

    // Load Square SDK
    const squareScript = document.createElement("script");
    const isSandbox = window.PAYMENT_CONFIG?.square?.appId?.startsWith("sandbox-");
    squareScript.src = isSandbox
      ? "https://sandbox.web.squarecdn.com/v1/square.js"
      : "https://web.squarecdn.com/v1/square.js";
    squareScript.onload = initializeSquare;
    squareScript.onerror = () => {
      squareContainer.innerHTML = `
        <div style="text-align: center; padding: 40px 0; color: #b91c1c;">
          <p>Failed to load Square. Please refresh the page or try another payment method.</p>
        </div>
      `;
    };
    document.head.appendChild(squareScript);

    async function initializeSquare() {
      try {
        const payments = Square.payments(
          window.PAYMENT_CONFIG.square.appId,
          window.PAYMENT_CONFIG.square.locationId
        );

        const card = await payments.card();

        // Create form HTML
        const buttonText = subscription.isMonthlyWithCredit
          ? "Subscribe - $0.00 Today (Credit Applied)"
          : `Subscribe - $${subscription.totalCharge.toFixed(2)} CAD/${subscription.billing === "yearly" ? "year" : "month"}`;

        squareContainer.innerHTML = `
          <form id="square-payment-form">
            <div class="form-group">
              <label for="square-email">Email Address</label>
              <input type="email" id="square-email" name="email" class="form-control" required>
            </div>
            <div class="form-group">
              <label>Card Details</label>
              <div id="card-container"></div>
            </div>
            <div id="payment-error" class="payment-error"></div>
            <button type="submit" id="square-submit-btn" class="checkout-btn ai-checkout-btn">
              ${buttonText}
            </button>
          </form>
        `;

        await card.attach("#card-container");

        // Handle form submission
        const form = document.getElementById("square-payment-form");
        form.addEventListener("submit", async (event) => {
          event.preventDefault();

          const submitButton = document.getElementById("square-submit-btn");
          const errorDiv = document.getElementById("payment-error");
          submitButton.disabled = true;
          submitButton.textContent = "Processing...";
          errorDiv.textContent = "";

          // Show processing overlay
          const processingOverlay = document.createElement("div");
          processingOverlay.className = "processing-overlay";
          processingOverlay.innerHTML = `
            <div class="spinner"></div>
            <h2>Processing Your Payment</h2>
            <p>Please do not close this window or refresh the page.</p>
          `;
          document.body.appendChild(processingOverlay);

          try {
            const result = await card.tokenize();
            if (result.status === "OK") {
              const email = document.getElementById("square-email").value;

              const response = await fetch("process-subscription.php", {
                method: "POST",
                headers: {
                  "Content-Type": "application/json",
                },
                body: JSON.stringify({
                  source_id: result.token,
                  email: email,
                  amount: subscription.totalCharge.toFixed(2),
                  currency: "CAD",
                  billing: subscription.billing,
                  hasDiscount: subscription.hasDiscount,
                  premiumLicenseKey: subscription.licenseKey,
                  payment_method: "square",
                  user_id: subscription.userId,
                  update_payment_method: subscription.isUpdatingPaymentMethod || false,
                }),
              });

              const data = await response.json();

              if (data.success) {
                window.location.href =
                  "../thank-you/?subscription_id=" +
                  data.subscription_id +
                  "&email=" +
                  encodeURIComponent(email);
              } else {
                errorDiv.textContent =
                  data.error || "Payment failed. Please try again.";
                const overlay = document.querySelector(".processing-overlay");
                if (overlay) overlay.remove();
                submitButton.disabled = false;
                submitButton.textContent = buttonText;
              }
            } else {
              errorDiv.textContent = "Card validation failed. Please try again.";
              const overlay = document.querySelector(".processing-overlay");
              if (overlay) overlay.remove();
              submitButton.disabled = false;
              submitButton.textContent = buttonText;
            }
          } catch (err) {
            console.error("Error:", err);
            errorDiv.textContent = "An error occurred. Please try again.";
            const overlay = document.querySelector(".processing-overlay");
            if (overlay) overlay.remove();
            submitButton.disabled = false;
            submitButton.textContent = buttonText;
          }
        });
      } catch (err) {
        console.error("Square initialization error:", err);
        squareContainer.innerHTML = `
          <div style="text-align: center; padding: 40px 0; color: #b91c1c;">
            <p>Failed to initialize Square payment. Please try another payment method.</p>
          </div>
        `;
      }
    }
  }
});
