// Book Chapter Search Functionality
(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        // Only initialize search on chapter view pages (not viewall)
        if (!document.body.classList.contains('mod_book_view_chapter')) {
            return;
        }
        
        // Initialize search functionality
        initBookSearch();
    });

    // Search functionality
    function initBookSearch() {
        // Create search container
        const searchContainer = document.createElement('div');
        searchContainer.className = 'book-search-container mb-3';
        searchContainer.innerHTML = `
            <div class="input-group">
                <input type="text" class="book-search-input form-control" 
                       placeholder="Search in book..." id="book-search-input" 
                       aria-label="Search in book">
                <button class="book-search-button" id="book-search-button" 
                        type="button" aria-label="Search">
                    <i class="fa fa-search"></i>
                </button>
            </div>
            <div class="book-search-results" id="book-search-results" role="listbox"></div>
        `;
        
        // Find the TOC container in chapter view
        const tocContainer = document.querySelector('.book_toc_chapter');
        if (tocContainer) {
            // Insert search after the title link
            const titleLink = tocContainer.querySelector('h2');
            if (titleLink) {
                titleLink.parentNode.insertBefore(searchContainer, titleLink.nextSibling);
            } else {
                tocContainer.insertBefore(searchContainer, tocContainer.firstChild);
            }
            
            const searchInput = document.getElementById('book-search-input');
            const searchButton = document.getElementById('book-search-button');
            const searchResults = document.getElementById('book-search-results');
            
            let searchTimeout;
            
            // Search on input with debouncing
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => performSearch(this.value), 300);
            });
            
            // Search on Enter key
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    performSearch(this.value);
                }
            });
            
            // Search on button click
            searchButton.addEventListener('click', function() {
                performSearch(searchInput.value);
            });
            
            // Close results when clicking outside
            document.addEventListener('click', function(e) {
                if (!searchContainer.contains(e.target)) {
                    searchResults.classList.remove('active');
                }
            });
            
            function performSearch(query) {
                if (query.length < 2) {
                    searchResults.classList.remove('active');
                    searchResults.innerHTML = '';
                    return;
                }
                
                // Get book ID and course module ID from URL
                const urlParams = new URLSearchParams(window.location.search);
                const cmid = urlParams.get('id');
                
                // Create loading indicator
                searchResults.innerHTML = '<div class="p-3 text-center"><i class="fa fa-spinner fa-spin"></i> Searching...</div>';
                searchResults.classList.add('active');
                
                // Perform AJAX search
                fetch(M.cfg.wwwroot + '/mod/book/search.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: 'cmid=' + cmid + '&query=' + encodeURIComponent(query) + '&sesskey=' + M.cfg.sesskey
                })
                .then(response => response.json())
                .then(data => {
                    displaySearchResults(data, query);
                })
                .catch(error => {
                    searchResults.innerHTML = '<div class="p-3 text-danger">Search failed. Please try again.</div>';
                    console.error('Search error:', error);
                });
            }
            
            function displaySearchResults(results, query) {
                if (results.length === 0) {
                    searchResults.innerHTML = '<div class="p-3 text-muted">No results found.</div>';
                    return;
                }
                
                let html = '<div class="list-group list-group-flush">';
                results.forEach(function(result) {
                    const excerpt = highlightText(result.excerpt, query);
                    html += `
                        <a href="#" class="list-group-item list-group-item-action book-search-result-item" 
                           data-chapterid="${result.id}">
                            <div class="book-search-result-title text-primary fw-bold">${result.title}</div>
                            <div class="book-search-result-excerpt small text-muted">${excerpt}</div>
                        </a>
                    `;
                });
                html += '</div>';
                
                searchResults.innerHTML = html;
                
                // Add click handlers to results
                searchResults.querySelectorAll('.book-search-result-item').forEach(function(item) {
                    item.addEventListener('click', function(e) {
                        e.preventDefault();
                        const chapterId = this.getAttribute('data-chapterid');
                        const urlParams = new URLSearchParams(window.location.search);
                        urlParams.set('chapterid', chapterId);
                        window.location.href = window.location.pathname + '?' + urlParams.toString();
                    });
                });
            }
            
            function highlightText(text, query) {
                const regex = new RegExp('(' + query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
                return text.replace(regex, '<mark>$1</mark>');
            }
        }
    }
    
    // Keyboard shortcut for search
    document.addEventListener('keydown', function(e) {
        // Don't interfere with form inputs
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
            return;
        }
        
        // Ctrl/Cmd + F to focus search (only in chapter view)
        if ((e.ctrlKey || e.metaKey) && e.key === 'f' && document.body.classList.contains('mod_book_view_chapter')) {
            const searchInput = document.getElementById('book-search-input');
            if (searchInput) {
                e.preventDefault();
                searchInput.focus();
                searchInput.select();
            }
        }
    });
})();
