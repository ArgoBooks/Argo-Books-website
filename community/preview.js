/**
 * Post Preview System
 * Shared preview functionality for create and edit post pages
 */
(function() {
    'use strict';

    // Initialize preview system when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        const editTab = document.getElementById('edit-tab');
        const previewTab = document.getElementById('preview-tab');
        const editContainer = document.getElementById('edit-container');
        const previewContainer = document.getElementById('preview-container');
        const previewContent = document.getElementById('preview-content');

        // Form fields
        const postTitle = document.getElementById('post_title') || document.getElementById('title');
        const postType = document.getElementById('post_type');
        const postContent = document.getElementById('post_content') || document.getElementById('content');
        const bugFields = document.getElementById('bug-specific-fields');
        const contentLabel = document.getElementById('content_label');

        // Bug-specific fields
        const bugLocation = document.getElementById('bug_location');
        const bugVersion = document.getElementById('bug_version');
        const bugSteps = document.getElementById('bug_steps');
        const bugExpected = document.getElementById('bug_expected');
        const bugActual = document.getElementById('bug_actual');

        // Exit if required elements don't exist
        if (!editTab || !previewTab || !editContainer || !previewContainer) {
            return;
        }

        let previewTimeout;

        // Tab switching
        editTab.addEventListener('click', function() {
            switchToEdit();
        });

        previewTab.addEventListener('click', function() {
            switchToPreview();
        });

        function switchToEdit() {
            editTab.classList.add('active');
            previewTab.classList.remove('active');
            editContainer.style.display = 'block';
            previewContainer.classList.remove('active');
        }

        function switchToPreview() {
            editTab.classList.remove('active');
            previewTab.classList.add('active');
            editContainer.style.display = 'none';
            previewContainer.classList.add('active');
            updatePreview();
        }

        // Post type change handler
        if (postType) {
            postType.addEventListener('change', function() {
                const selectedType = this.value;

                if (bugFields && contentLabel) {
                    if (selectedType === 'bug') {
                        bugFields.style.display = 'block';
                        bugFields.classList.remove('hidden');
                        contentLabel.textContent = 'Additional Details or Context';
                    } else {
                        bugFields.style.display = 'none';
                        bugFields.classList.add('hidden');
                        contentLabel.textContent = 'Content';
                    }
                }

                // Update preview if we're on preview tab
                if (previewTab.classList.contains('active')) {
                    updatePreview();
                }
            });
        }

        // Update preview when any field changes
        const formFields = [postTitle, postType, postContent, bugLocation, bugVersion, bugSteps, bugExpected, bugActual];
        formFields.forEach(field => {
            if (field) {
                field.addEventListener('input', function() {
                    if (previewTab.classList.contains('active')) {
                        updatePreview();
                    }
                });
            }
        });

        function updatePreview() {
            const title = postTitle ? postTitle.value.trim() : '';
            const type = postType ? postType.value : '';
            const content = postContent ? postContent.value.trim() : '';

            // If no content, show empty state
            if (!title && !type && !content) {
                previewContent.innerHTML = `
                    <div class="preview-empty-state">
                        <div class="preview-empty-icon"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></div>
                        <p>Fill out the form to see a preview of your post</p>
                    </div>
                `;
                return;
            }

            // Clear existing timeout
            if (previewTimeout) {
                clearTimeout(previewTimeout);
            }

            // Show loading state
            previewContent.innerHTML = `
                <div class="preview-empty-state">
                    <div class="preview-empty-icon">⏳</div>
                    <p>Generating preview...</p>
                </div>
            `;

            // Debounce the AJAX request
            previewTimeout = setTimeout(() => {
                fetchServerPreview();
            }, 300);
        }

        function fetchServerPreview() {
            const formData = new FormData();
            formData.append('preview_request', '1');
            // Include CSRF token from meta tag or hidden form input
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                || document.querySelector('input[name="csrf_token"]')?.value || '';
            formData.append('csrf_token', csrfToken);
            formData.append('title', postTitle ? postTitle.value : '');
            formData.append('content', postContent ? postContent.value : '');
            formData.append('post_type', postType ? postType.value : '');

            // Add bug-specific fields
            if (bugLocation) formData.append('bug_location', bugLocation.value);
            if (bugVersion) formData.append('bug_version', bugVersion.value);
            if (bugSteps) formData.append('bug_steps', bugSteps.value);
            if (bugExpected) formData.append('bug_expected', bugExpected.value);
            if (bugActual) formData.append('bug_actual', bugActual.value);

            fetch('preview_handler.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        throw new Error(data.error);
                    }
                    renderPreview(data);
                })
                .catch(error => {
                    console.error('Preview error:', error);
                    // Fallback to client-side preview
                    renderClientSidePreview();
                });
        }

        function renderPreview(data) {
            let previewHTML = '';

            // Post header
            previewHTML += '<div class="preview-header">';
            previewHTML += `<h1 class="preview-title">${data.title || 'Untitled Post'}</h1>`;
            if (data.post_type) {
                const typeName = data.post_type === 'bug' ? 'Bug Report' : 'Feature Request';
                previewHTML += `<span class="preview-type-badge ${data.post_type}">${typeName}</span>`;
            }
            previewHTML += '</div>';

            // Post meta
            previewHTML += '<div class="preview-meta">';
            previewHTML += '<div class="preview-author">';

            if (data.user.avatar) {
                previewHTML += `<img src="${data.user.avatar}" alt="${data.user.username}" class="preview-avatar">`;
            } else {
                previewHTML += `<div class="preview-avatar">${data.user.username.charAt(0).toUpperCase()}</div>`;
            }

            previewHTML += `<span>Posted by ${data.user.username}</span>`;
            previewHTML += '</div>';
            previewHTML += '</div>';

            // Bug-specific content
            if (data.post_type === 'bug' && data.bug_metadata) {
                const bug = data.bug_metadata;
                const hasMetadata = Object.values(bug).some(field => field.raw);

                if (hasMetadata) {
                    previewHTML += '<div class="preview-bug-info">';

                    if (bug.location.raw) {
                        previewHTML += '<div class="preview-bug-section">';
                        previewHTML += '<div class="preview-bug-section-title">Location</div>';
                        previewHTML += `<div class="preview-bug-section-content">${bug.location.formatted}</div>`;
                        previewHTML += '</div>';
                    }

                    if (bug.version.raw) {
                        previewHTML += '<div class="preview-bug-section">';
                        previewHTML += '<div class="preview-bug-section-title">Version/Browser</div>';
                        previewHTML += `<div class="preview-bug-section-content">${bug.version.formatted}</div>`;
                        previewHTML += '</div>';
                    }

                    if (bug.steps.raw) {
                        previewHTML += '<div class="preview-bug-section">';
                        previewHTML += '<div class="preview-bug-section-title">Steps to Reproduce</div>';
                        previewHTML += `<div class="preview-bug-section-content">${bug.steps.formatted}</div>`;
                        previewHTML += '</div>';
                    }

                    if (bug.expected.raw) {
                        previewHTML += '<div class="preview-bug-section">';
                        previewHTML += '<div class="preview-bug-section-title">Expected Behavior</div>';
                        previewHTML += `<div class="preview-bug-section-content">${bug.expected.formatted}</div>`;
                        previewHTML += '</div>';
                    }

                    if (bug.actual.raw) {
                        previewHTML += '<div class="preview-bug-section">';
                        previewHTML += '<div class="preview-bug-section-title">Actual Behavior</div>';
                        previewHTML += `<div class="preview-bug-section-content">${bug.actual.formatted}</div>`;
                        previewHTML += '</div>';
                    }

                    if (data.content) {
                        previewHTML += '<div class="preview-bug-section">';
                        previewHTML += '<div class="preview-bug-section-title">Additional Details</div>';
                        previewHTML += `<div class="preview-bug-section-content">${data.content}</div>`;
                        previewHTML += '</div>';
                    }

                    previewHTML += '</div>';
                } else if (data.content) {
                    previewHTML += `<div class="preview-content">${data.content}</div>`;
                }
            } else {
                // Regular content
                if (data.content) {
                    previewHTML += `<div class="preview-content">${data.content}</div>`;
                }
            }

            previewContent.innerHTML = previewHTML;
        }

        function renderClientSidePreview() {
            // Fallback to basic client-side preview
            const title = postTitle ? postTitle.value.trim() : '';
            const type = postType ? postType.value : '';
            const content = postContent ? postContent.value.trim() : '';

            let previewHTML = '';

            // Post header
            previewHTML += '<div class="preview-header">';
            previewHTML += `<h1 class="preview-title">${escapeHtml(title) || 'Untitled Post'}</h1>`;
            if (type) {
                previewHTML += `<span class="preview-type-badge ${type}">${type === 'bug' ? 'Bug Report' : 'Feature Request'}</span>`;
            }
            previewHTML += '</div>';

            // Post meta
            previewHTML += '<div class="preview-meta">';
            previewHTML += '<div class="preview-author">';
            previewHTML += `<div class="preview-avatar">U</div>`;
            previewHTML += `<span>Posted by Current User</span>`;
            previewHTML += '</div>';
            previewHTML += '</div>';

            // Basic content
            if (content) {
                previewHTML += `<div class="preview-content">${formatText(content)}</div>`;
            }

            previewContent.innerHTML = previewHTML;
        }

        // Basic text formatting (simplified version for fallback)
        function formatText(text) {
            if (!text) return '';

            // Escape HTML first
            text = escapeHtml(text);

            // Convert line breaks
            text = text.replace(/\n/g, '<br>');

            // Basic markdown-style formatting
            text = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>'); // Bold
            text = text.replace(/\*(.*?)\*/g, '<em>$1</em>'); // Italic
            text = text.replace(/`(.*?)`/g, '<code>$1</code>'); // Code

            // Simple @mention detection (just highlighting)
            text = text.replace(/@(\w+)/g, '<span style="color: var(--primary-blue); font-weight: 500;">@$1</span>');

            return text;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Initialize form state on page load (for edit page with existing data)
        if (postType && postType.value === 'bug' && bugFields) {
            bugFields.style.display = 'block';
            bugFields.classList.remove('hidden');
            if (contentLabel) {
                contentLabel.textContent = 'Additional Details or Context';
            }
        }
    });
})();
