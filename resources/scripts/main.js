// Google Ads gtag (AW-17210317271)
(function () {
  var s = document.createElement("script");
  s.async = true;
  s.src = "https://www.googletagmanager.com/gtag/js?id=AW-17210317271";
  var first = document.getElementsByTagName("script")[0];
  first.parentNode.insertBefore(s, first);
  window.dataLayer = window.dataLayer || [];
  window.gtag = function () { window.dataLayer.push(arguments); };
  window.gtag("js", new Date());
  window.gtag("config", "AW-17210317271");
})();

// Detect the base path for the application
// This handles both production (root) and local XAMPP (subfolder)
function getBasePath() {
  var path = window.location.pathname;

  // Check if we're in a subfolder (common XAMPP setup)
  // Look for common local folder names
  var match = path.match(/^(\/[\w-]+\/)/);

  // If the path doesn't start with common site paths, assume we're in a subfolder
  var sitePaths = ['/features/', '/pricing/', '/community/', '/documentation/', '/about-us/',
                   '/contact-us/', '/whats-new/', '/admin/', '/legal/', '/resources/',
                   '/error-pages/', '/older-versions/', '/downloads/', '/portal/', '/affiliates/',
                   '/invoice/', '/api/', '/compare/', '/unsubscribe/', '/subscribe/', '/review/',
                   '/for-landscapers/', '/for-contractors/', '/for-repair-shops/',
                   '/for-rental-businesses/', '/for-cleaning-companies/',
                   '/for-local-wholesalers/', '/for-resellers/',
                   '/for-auto-detailing/', '/for-solo-operators/',
                   '/for-software-companies/',
                   '/who-its-for/'];

  var isRootPath = sitePaths.some(function(p) { return path.startsWith(p); }) || path === '/' || path === '/index.php';

  if (!isRootPath && match) {
    return match[1]; // Return the subfolder path (e.g., '/Argo-Books-website/')
  }

  return '/'; // Production or root-level local setup
}

var BASE_PATH = getBasePath();

function setDefaultAvatar() {
  const accountAvatar = document.querySelector(".account-avatar");
  if (accountAvatar) {
    accountAvatar.innerHTML = `
      <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
           viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
        <circle cx="12" cy="7" r="4"></circle>
      </svg>`;
  }
}

// Populate the account avatar in the (now server-rendered) header. Leaves the
// default avatar SVG in place when logged out or on error.
function loadAvatar() {
  const accountAvatar = document.querySelector(".account-avatar");
  if (!accountAvatar) return;

  fetch(BASE_PATH + "community/get_avatar_info.php")
    .then((response) => response.json())
    .then((data) => {
      if (data.logged_in && data.has_avatar) {
        // Apply BASE_PATH to avatar URL if it starts with /
        var avatarUrl = data.avatar_url;
        if (avatarUrl && avatarUrl.startsWith("/") && !avatarUrl.startsWith("//") && BASE_PATH !== "/") {
          avatarUrl = BASE_PATH + avatarUrl.substring(1);
        }
        accountAvatar.innerHTML = `<img src="${avatarUrl}" alt="Profile">`;
      }
    })
    .catch((error) => {
      console.error("Error fetching avatar info:", error);
      setDefaultAvatar();
    });
}

// Header dropdown hover intent: open on hover, and keep the menu open for a
// short grace period after the pointer leaves, so the user can move across the
// small gap between the button and the menu without it closing on them. Uses
// the .dropdown-open class the header CSS already supports; mouseenter/leave on
// the whole .has-dropdown (the button + the menu) so moving into the menu keeps
// it open, and a cancellable timer bridges the gap in between.
function initHeaderDropdowns() {
  var CLOSE_DELAY = 280; // ms

  document.querySelectorAll(".has-dropdown").forEach(function (dd) {
    var closeTimer = null;

    dd.addEventListener("mouseenter", function () {
      clearTimeout(closeTimer);
      dd.classList.add("dropdown-open");
    });

    dd.addEventListener("mouseleave", function () {
      clearTimeout(closeTimer);
      closeTimer = setTimeout(function () {
        dd.classList.remove("dropdown-open");
      }, CLOSE_DELAY);
    });
  });
}

// The header and footer are now rendered server-side (PHP includes), so they
// are already in the DOM. On ready we just load the avatar and wire the dropdowns.
document.addEventListener("DOMContentLoaded", function () {
  loadAvatar();
  initHeaderDropdowns();
});
