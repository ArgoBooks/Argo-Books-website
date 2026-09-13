document.addEventListener("DOMContentLoaded", function () {
  // Get CSRF token from meta tag
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

  const postsPerPage = 15;
  let isLoading = false;
  let hasMorePosts = true;

  // Cache DOM elements
  const postsContainer = document.getElementById("posts-container");
  const loadingIndicator = document.getElementById("loading-indicator");
  const searchInput = document.getElementById("search-posts");
  const searchBtn = document.getElementById("search-btn");
  const sortFilter = document.getElementById("sort-filter");
  const selectAllCheckbox = document.getElementById("select-all-posts");
  const bulkActionsDiv = document.querySelector(".bulk-actions");
  const deleteSelectedBtn = document.getElementById("delete-selected");
  const selectedCountSpan = document.querySelector(".selected-count");

  // Create search filter label container
  let searchFilterLabel = document.createElement("div");
  searchFilterLabel.className = "search-filter-label";

  // Insert after community actions
  const communityActions = document.querySelector(".community-actions");
  if (communityActions) {
    communityActions.after(searchFilterLabel);
  }

  // Initial setup
  setupVoteHandlers();
  setupDeletePost();
  setupUpdatePostStatus();
  updateSearchFilterLabel();

  function isUserLoggedIn() {
    // We'll check this by looking for disabled vote buttons
    const voteBtn = document.querySelector(".vote-btn");
    return voteBtn && !voteBtn.hasAttribute("disabled");
  }

  function isUserAdmin() {
    // Check if bulk actions div exists (which are only created for admins)
    return bulkActionsDiv !== null;
  }

  function displayServerMessage(messageData) {
    if (!messageData.show_message) return;

    const message = document.createElement("div");

    if (messageData.message_class) {
      message.className = messageData.message_class;
    } else {
      message.className = messageData.success
        ? "success-message"
        : "error-message";
    }

    message.textContent = messageData.message;
    document.body.appendChild(message);

    // Remove after specified duration or default to 3 seconds
    setTimeout(() => {
      message.remove();
    }, messageData.message_duration || 3000);
  }

  function setupVoteHandlers() {
    const voteButtons = document.querySelectorAll(".vote-btn");

    voteButtons.forEach((btn) => {
      btn.addEventListener("click", function (e) {
        const postId = this.getAttribute("data-post-id");
        const voteType = this.getAttribute("data-vote") === "up" ? 1 : -1;
        const postCard = this.closest(".post-card");

        // Disable all vote buttons in this post to prevent double-clicks
        const postVoteButtons = postCard.querySelectorAll(".vote-btn");
        postVoteButtons.forEach((button) => (button.disabled = true));

        fetch("vote.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded",
          },
          body: `post_id=${postId}&vote_type=${voteType}&csrf_token=${encodeURIComponent(csrfToken)}`,
        })
          .then((response) => response.json())
          .then((data) => {
            if (data.success) {
              // Update the vote count
              const voteCountElement = postCard.querySelector(".vote-count");
              voteCountElement.textContent = data.new_vote_count;

              // Change button colors based on user's vote
              const upvoteBtn = postCard.querySelector(".upvote");
              const downvoteBtn = postCard.querySelector(".downvote");

              upvoteBtn.style.color = "";
              downvoteBtn.style.color = "";
              upvoteBtn.classList.remove("voted");
              downvoteBtn.classList.remove("voted");

              if (data.user_vote === 1) {
                upvoteBtn.style.color = "#2563eb";
                upvoteBtn.classList.add("voted");
              } else if (data.user_vote === -1) {
                downvoteBtn.style.color = "#dc2626";
                downvoteBtn.classList.add("voted");
              }
            } else {
              if (data.show_message) {
                displayServerMessage(data);
              } else {
                alert("Error voting: " + data.message);
              }
            }
          })
          .catch((error) => {
            console.error("Error:", error);
            alert("An error occurred while voting");
          })
          .finally(() => {
            // Re-enable buttons after operation is complete
            postVoteButtons.forEach((button) => (button.disabled = false));
          });
      });
    });
  }

  // Hide all posts except the first few
  let allPosts = Array.from(document.querySelectorAll(".post-card"));
  initializePostDisplay();

  function initializePostDisplay() {
    // Hide all posts first
    allPosts.forEach((post, index) => {
      if (index >= postsPerPage) {
        post.style.display = "none";
      } else {
        post.dataset.visible = "true";
      }

      // Add checkbox for selection if user is admin
      if (isUserAdmin()) {
        addSelectionCheckbox(post);
      }
    });

    // Show bulk actions immediately for admin users
    if (isUserAdmin()) {
      toggleSelectionMode(true);
    }

    // Show loading indicator if there are more posts
    if (allPosts.length > postsPerPage) {
      loadingIndicator.style.display = "block";
      setTimeout(() => {
        loadingIndicator.style.display = "none";
      }, 500); // Hide it after a brief moment for initial page load
    }
  }

  function addSelectionCheckbox(post) {
    // Create selection container
    const postSelect = document.createElement("div");
    postSelect.className = "post-select checkbox";

    const checkbox = document.createElement("input");
    checkbox.type = "checkbox";
    checkbox.className = "post-checkbox";
    checkbox.addEventListener("change", function () {
      updateSelectedCount();
      updateDeleteButtonState();
    });

    // Add label for better UX
    const label = document.createElement("label");
    label.className = "post-select-label";
    label.appendChild(checkbox);
    postSelect.appendChild(label);

    // Insert at the beginning of the post card
    post.insertBefore(postSelect, post.firstChild);
  }

  function toggleSelectionMode(enable) {
    if (!isUserLoggedIn()) {
      return; // Non-logged users can't select posts
    }

    allPosts.forEach((post) => {
      if (enable) {
        post.classList.add("selectable");
        // Display the selection checkbox
        const postSelect = post.querySelector(".post-select");
        if (postSelect) {
          postSelect.style.display = "flex";
        }
      } else {
        post.classList.remove("selectable");
        const checkbox = post.querySelector(".post-checkbox");
        if (checkbox) checkbox.checked = false;
        // Hide the selection checkbox
        const postSelect = post.querySelector(".post-select");
        if (postSelect) {
          postSelect.style.display = "none";
        }
      }
    });

    if (bulkActionsDiv) {
      bulkActionsDiv.style.display = enable ? "flex" : "none";
    }
    updateSelectedCount();
    updateDeleteButtonState();
  }

  function updateSelectedCount() {
    if (!selectedCountSpan) return;

    const selectedPosts = document.querySelectorAll(
      ".post-checkbox:checked"
    ).length;
    selectedCountSpan.textContent = selectedPosts + " selected";

    updateSelectAllCheckbox();
  }

  function updateDeleteButtonState() {
    if (!deleteSelectedBtn) return;

    const selectedPosts = document.querySelectorAll(
      ".post-checkbox:checked"
    ).length;
    deleteSelectedBtn.disabled = selectedPosts === 0;
  }

  function updateSelectAllCheckbox() {
    if (!selectAllCheckbox) return;

    const checkboxes = document.querySelectorAll(".post-checkbox");
    const visibleCheckboxes = Array.from(checkboxes).filter((checkbox) => {
      const post = checkbox.closest(".post-card");
      return post && post.style.display !== "none";
    });

    const checkedVisibleCheckboxes = Array.from(visibleCheckboxes).filter(
      (checkbox) => checkbox.checked
    );

    // If all visible checkboxes are checked, check the "Select All" checkbox
    // If some or none are checked, uncheck it
    selectAllCheckbox.checked =
      visibleCheckboxes.length > 0 &&
      checkedVisibleCheckboxes.length === visibleCheckboxes.length;

    // Add indeterminate state when some but not all are selected
    selectAllCheckbox.indeterminate =
      checkedVisibleCheckboxes.length > 0 &&
      checkedVisibleCheckboxes.length < visibleCheckboxes.length;
  }

  function updateSearchFilterLabel() {
    if (!searchFilterLabel) return;

    const searchTerm = searchInput.value.trim();
    const sortType = sortFilter
      ? sortFilter.options[sortFilter.selectedIndex].text
      : "Newest First";

    let labelText = "";

    // Build the label text based on applied filters
    if (searchTerm) {
      labelText += `Searching for "${searchTerm}"`;
    }

    // Add sort/filter information
    if (sortType.includes("Only")) {
      // This is a status filter
      labelText +=
        (labelText ? " • " : "Showing ") + sortType.replace(" Only", " posts");
    } else {
      // This is a sort option
      labelText +=
        (labelText ? " • " : "Showing posts • ") + `Sorted by ${sortType}`;
    }

    // Display the label
    searchFilterLabel.textContent = labelText;
    searchFilterLabel.style.display = "block";
  }

  // Event listener for select all checkbox
  if (selectAllCheckbox) {
    selectAllCheckbox.addEventListener("change", function () {
      const isChecked = this.checked;
      document.querySelectorAll(".post-checkbox").forEach((checkbox) => {
        const post = checkbox.closest(".post-card");
        if (post && post.style.display !== "none") {
          // Only select visible posts
          checkbox.checked = isChecked;
        }
      });
      updateSelectedCount();
      updateDeleteButtonState();
    });
  }

  // Event listener for delete selected button
  if (deleteSelectedBtn) {
    deleteSelectedBtn.addEventListener("click", function () {
      if (!isUserLoggedIn()) {
        window.location.href = "users/login.php";
        return;
      }

      const selectedPosts = document.querySelectorAll(".post-checkbox:checked");
      const numSelected = selectedPosts.length;

      if (numSelected === 0) return;

      if (
        confirm(
          `Are you sure you want to delete ${numSelected} post${
            numSelected === 1 ? "" : "s"
          }? This action cannot be undone.`
        )
      ) {
        const postIds = Array.from(selectedPosts).map((checkbox) => {
          const post = checkbox.closest(".post-card");
          return post.dataset.postId;
        });

        // Call delete API for each selected post
        Promise.all(
          postIds.map((id) => {
            return fetch("delete_post.php", {
              method: "POST",
              headers: {
                "Content-Type": "application/x-www-form-urlencoded",
              },
              body: `post_id=${id}&csrf_token=${encodeURIComponent(csrfToken)}`,
            }).then((response) => response.json());
          })
        )
          .then((results) => {
            // Check if all deletions were successful
            const allSuccessful = results.every((result) => result.success);

            if (allSuccessful) {
              // Remove deleted posts from DOM
              selectedPosts.forEach((checkbox) => {
                const post = checkbox.closest(".post-card");
                post.remove();
              });

              // Update allPosts array
              allPosts = Array.from(document.querySelectorAll(".post-card"));

              // Re-enable selection mode for admin users after deleting
              if (isUserAdmin()) {
                // Reset the selected count
                if (selectedCountSpan) {
                  selectedCountSpan.textContent = "0 selected";
                }

                // Update the delete button state
                if (deleteSelectedBtn) {
                  deleteSelectedBtn.disabled = true;
                }

                // Uncheck the "select all" checkbox
                if (selectAllCheckbox) {
                  selectAllCheckbox.checked = false;
                }
              }

              // Check if we need to load more posts
              if (allPosts.length === 0) {
                postsContainer.innerHTML = `
                        <div class="empty-state">
                            <h3>No posts yet</h3>
                            <p>Be the first to create a post in our community.</p>
                        </div>
                    `;
              } else {
                checkAndLoadMorePosts();
              }
            } else {
              alert("Some posts could not be deleted. Please try again.");
            }
          })
          .catch((error) => {
            console.error("Error deleting posts:", error);
            alert("An error occurred while deleting posts. Please try again.");
          });
      }
    });
  }

  function loadMorePosts() {
    if (isLoading || !hasMorePosts) return;

    isLoading = true;
    loadingIndicator.style.display = "block";

    const startIndex = postsPerPage;
    const endIndex = startIndex + postsPerPage;
    const filteredPosts = filterAndSortPosts();

    // Show next batch of posts
    let visibleCount = 0;

    filteredPosts.forEach((post, index) => {
      if (index >= startIndex && index < endIndex) {
        post.style.display = "flex";
        post.dataset.visible = "true";
        visibleCount++;

        // Add fade-in animation
        post.style.opacity = "0";
        setTimeout(() => {
          post.style.opacity = "1";
          post.style.transition = "opacity 0.3s ease";
        }, 50 * (index - startIndex));
      }
    });

    // Check if we have more posts to load
    hasMorePosts = endIndex < filteredPosts.length;

    // Hide loading indicator if no more posts
    setTimeout(() => {
      isLoading = false;
      loadingIndicator.style.display = hasMorePosts ? "block" : "none";
    }, 500);

    // Set up vote handlers for newly displayed posts
    setupVoteHandlers();
  }

  function checkAndLoadMorePosts() {
    // Calculate how many posts should be visible
    const shouldBeVisible = Math.min(postsPerPage, allPosts.length);
    const visiblePosts = document.querySelectorAll(
      ".post-card[style*='display: flex']"
    ).length;

    // If we don't have enough visible posts, load more
    if (visiblePosts < shouldBeVisible && hasMorePosts) {
      loadMorePosts();
    }
  }

  window.addEventListener("scroll", function () {
    if (isLoading || !hasMorePosts) return;

    // Check if user has scrolled to the bottom
    const scrollY = window.scrollY || window.pageYOffset;
    const windowHeight = window.innerHeight;
    const documentHeight = document.documentElement.scrollHeight;

    // Load more when user is near the bottom (200px threshold)
    if (scrollY + windowHeight >= documentHeight - 200) {
      loadMorePosts();
    }
  });

  /**
   * Filters and sorts the list of posts based on:
   * - Search term (fuzzy match against title)
   * - Selected sort/filter option (newest, oldest, most_voted, or status filters)
   *
   * @returns {HTMLElement[]} Array of filtered and sorted post elements
   */
  function filterAndSortPosts() {
    const searchTerm = searchInput.value.toLowerCase().trim();
    const sortBy = sortFilter?.value;

    const similarityThreshold = 0.6; // 0.0 = no match, 1.0 = perfect match

    let filtered = allPosts.filter((post) => {
      // Filter by status if a status filter is selected
      if (sortBy && sortBy.endsWith("_only")) {
        const requiredStatus = sortBy.replace("_only", "");
        const postStatus = post.dataset.postStatus;

        if (postStatus !== requiredStatus) {
          return false;
        }
      }

      // Filter by search term
      if (searchTerm) {
        const title = post
          .querySelector(".post-title")
          .textContent.toLowerCase();

        const words = title.split(/\s+/);

        // Check if any word in the title is similar to the search term
        return words.some((word) => {
          if (word.includes(searchTerm)) {
            return true;
          }

          // Otherwise check similarity using Levenshtein distance
          return getSimilarity(word, searchTerm) >= similarityThreshold;
        });
      }

      return true;
    });

    // Sort the filtered posts (only if not a status filter)
    if (sortBy && !sortBy.endsWith("_only")) {
      filtered.sort((a, b) => {
        if (sortBy === "most_voted") {
          const votesA = parseInt(
            a.querySelector(".vote-count").textContent.trim()
          );
          const votesB = parseInt(
            b.querySelector(".vote-count").textContent.trim()
          );
          return votesB - votesA;
        } else {
          const dateTextA = a.querySelector(".post-date").textContent.trim();
          const dateTextB = b.querySelector(".post-date").textContent.trim();
          const dateA = new Date(dateTextA);
          const dateB = new Date(dateTextB);
          return sortBy === "oldest" ? dateA - dateB : dateB - dateA;
        }
      });
    }

    // Remove and re-append posts in the sorted order
    filtered.forEach((post) => post.remove());
    filtered.forEach((post) => postsContainer.appendChild(post));

    return filtered;
  }
  /**
   * Returns a similarity score between 0 and 1 based on edit distance.
   * Uses a normalized Levenshtein distance.
   */
  function getSimilarity(str1, str2) {
    const longer = str1.length > str2.length ? str1 : str2;
    const shorter = str1.length > str2.length ? str2 : str1;
    const longerLength = longer.length;

    if (longerLength === 0) return 1.0;
    return (longerLength - getEditDistance(longer, shorter)) / longerLength;
  }

  /**
   * Levenshtein distance
   */
  function getEditDistance(a, b) {
    const matrix = Array.from({ length: b.length + 1 }, (_, i) =>
      Array.from({ length: a.length + 1 }, (_, j) =>
        i === 0 ? j : j === 0 ? i : 0
      )
    );

    for (let i = 1; i <= b.length; i++) {
      for (let j = 1; j <= a.length; j++) {
        matrix[i][j] =
          b[i - 1] === a[j - 1]
            ? matrix[i - 1][j - 1]
            : Math.min(
                matrix[i - 1][j - 1] + 1, // substitution
                matrix[i][j - 1] + 1, // insertion
                matrix[i - 1][j] + 1 // deletion
              );
      }
    }

    return matrix[b.length][a.length];
  }

  function applyFilters() {
    hasMorePosts = true;
    let filteredPosts = filterAndSortPosts();

    updateSearchFilterLabel();

    // Hide all posts first
    allPosts.forEach((post) => {
      post.style.display = "none";
      post.dataset.visible = "false";
    });

    // Show first batch of filtered posts
    filteredPosts.forEach((post, index) => {
      if (index < postsPerPage) {
        post.style.display = "flex";
        post.dataset.visible = "true";
      }
    });

    hasMorePosts = filteredPosts.length > postsPerPage;
    loadingIndicator.style.display = hasMorePosts ? "block" : "none";

    // Show empty state if no posts match
    if (filteredPosts.length === 0) {
      const emptyState = document.querySelector(".empty-state");
      if (emptyState) {
        emptyState.style.display = "block";
        emptyState.innerHTML = `
                <h3>No matching posts</h3>
                <p>Try different search terms or filters</p>
            `;
      } else {
        const newEmptyState = document.createElement("div");
        newEmptyState.className = "empty-state";
        newEmptyState.innerHTML = `
                <h3>No matching posts</h3>
                <p>Try different search terms or filters</p>
            `;
        postsContainer.appendChild(newEmptyState);
      }
    } else {
      const emptyState = document.querySelector(".empty-state");
      if (emptyState) {
        emptyState.style.display = "none";
      }
    }

    // Update select all checkbox and selected count
    if (selectAllCheckbox) {
      selectAllCheckbox.checked = false;
      updateSelectedCount();
      updateDeleteButtonState();
    }

    setupVoteHandlers();
  }

  // Event listeners for filters
  if (sortFilter) {
    sortFilter.addEventListener("change", function () {
      applyFilters();
      setTimeout(updateSelectAllCheckbox, 100);
    });
  }

  if (searchBtn) {
    searchBtn.addEventListener("click", applyFilters);
  }

  if (searchInput) {
    // Handle Enter key press
    searchInput.addEventListener("keyup", function (e) {
      if (e.key === "Enter") {
        applyFilters();
      }
    });

    // Handle input changes - auto search when emptied
    let typingTimer;
    searchInput.addEventListener("input", function () {
      clearTimeout(typingTimer);

      // If the search field is empty, apply filters immediately
      if (this.value.trim() === "") {
        applyFilters();
      } else {
        // Otherwise, add a small delay for typing
        typingTimer = setTimeout(function () {
          applyFilters();
        }, 500);
      }
    });
  }

  function setupDeletePost() {
    const deletePostButtons = document.querySelectorAll(".delete-post-btn");

    deletePostButtons.forEach((btn) => {
      btn.addEventListener("click", function (e) {
        e.preventDefault();
        e.stopPropagation();

        if (
          confirm(
            "Are you sure you want to delete this post? This cannot be undone."
          )
        ) {
          const postId = this.getAttribute("data-post-id");
          const postCard = this.closest(".post-card");

          fetch("delete_post.php", {
            method: "POST",
            headers: {
              "Content-Type": "application/x-www-form-urlencoded",
            },
            body: `post_id=${postId}&csrf_token=${encodeURIComponent(csrfToken)}`,
          })
            .then((response) => response.json())
            .then((data) => {
              if (data.success) {
                // Remove the post from the DOM
                postCard.remove();

                // Update allPosts array to match DOM
                allPosts = Array.from(document.querySelectorAll(".post-card"));

                // Check if we need to load more posts
                if (allPosts.length === 0) {
                  const postsContainer =
                    document.getElementById("posts-container");
                  postsContainer.innerHTML = `
                      <div class="empty-state">
                        <h3>No posts yet</h3>
                        <p>Be the first to create a post in our community.</p>
                      </div>
                    `;
                } else {
                  checkAndLoadMorePosts();
                }
              } else {
                alert("Error deleting post: " + data.message);
              }
            })
            .catch((error) => {
              console.error("Error:", error);
              alert("An error occurred while deleting the post");
            });
        }
      });
    });
  }

  function setupUpdatePostStatus() {
    const statusDropdowns = document.querySelectorAll(".status-update");

    statusDropdowns.forEach((dropdown) => {
      dropdown.addEventListener("change", function () {
        const postId = this.getAttribute("data-post-id");
        const newStatus = this.value;
        const postCard = this.closest(".post-card");

        fetch("update_status.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded",
          },
          body: `post_id=${postId}&status=${newStatus}`,
        })
          .then((response) => response.json())
          .then((data) => {
            if (data.success) {
              // Update the status label
              const statusLabel = postCard.querySelector(".post-status");

              // Remove old status class and add new one
              statusLabel.className = "post-status " + newStatus;

              // Update text content
              let statusText = "";
              switch (newStatus) {
                case "open":
                  statusText = "Open";
                  break;
                case "in_progress":
                  statusText = "In Progress";
                  break;
                case "completed":
                  statusText = "Completed";
                  break;
                case "declined":
                  statusText = "Declined";
                  break;
              }

              statusLabel.textContent = statusText;

              // Update the data attribute for filtering
              postCard.dataset.postStatus = newStatus;
            } else {
              alert("Error updating status: " + data.message);
            }
          })
          .catch((error) => {
            console.error("Error:", error);
            alert("An error occurred while updating the status");
          });
      });
    });
  }
});
