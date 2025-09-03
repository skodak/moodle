// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Drag and drop functionality for reordering book chapters
 *
 * @module     mod_book/dragdrop
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/notification', 'core/str', 'core/pending'], 
function($, Ajax, Notification, Str, Pending) {

    var moveMode = false;
    var selectedChapter = null;
    var selectedChapterId = null;
    
    /**
     * Initialize drag and drop functionality
     * @param {Number} cmid The course module ID
     */
    var init = function(cmid) {
        var pendingPromise = new Pending('mod_book/dragdrop:init');
        
        // Get string translations
        Str.get_strings([
            {key: 'chapterreordering', component: 'mod_book'},
            {key: 'dropchapterhere', component: 'mod_book'},
            {key: 'dropassubchapter', component: 'mod_book'},
            {key: 'movemode', component: 'mod_book'},
            {key: 'selectdestination', component: 'mod_book'},
            {key: 'cancelmove', component: 'mod_book'}
        ]).done(function(strings) {
            setupDragAndDrop(cmid, strings);
            setupMoveMode(cmid, strings);
            pendingPromise.resolve();
        }).fail(function() {
            // Use fallback strings if language pack fails
            var fallbackStrings = [
                'Drag to reorder chapter',
                'Drop chapter here',
                'Drop here to make subchapter',
                'Move mode - select destination',
                'Click to select destination',
                'Cancel move'
            ];
            setupDragAndDrop(cmid, fallbackStrings);
            setupMoveMode(cmid, fallbackStrings);
            pendingPromise.resolve();
        });
    };

    /**
     * Setup move mode for mobile/keyboard accessibility
     * @param {Number} cmid The course module ID
     * @param {Array} strings Translated strings
     */
    var setupMoveMode = function(cmid, strings) {
        // Make drag handle also work as move button on click
        $(document).on('click', '.book-drag-handle', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var $chapter = $(this).closest('.book-chapter-item');
            
            if (moveMode && selectedChapter && selectedChapter[0] === $chapter[0]) {
                // Cancel move mode
                exitMoveMode();
            } else {
                // Enter move mode
                enterMoveMode($chapter, strings);
            }
        });
        
        // Handle drop zone click in move mode
        $(document).on('click', '.book-drop-zone.move-mode-visible', function(e) {
            e.preventDefault();
            
            if (!moveMode || !selectedChapter) {
                return;
            }
            
            var $dropZone = $(this);
            var targetChapterId = $dropZone.data('chapterid');
            var position = $dropZone.data('position');
            var parentId = 0;
            
            // If dropping as subchapter, set the parent ID
            if (position === 'subchapter') {
                parentId = targetChapterId;
            }
            
            // Make AJAX call to save the new order
            performMove(cmid, selectedChapterId, position, targetChapterId, parentId);
        });
        
        // Add escape key handler to cancel move mode
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && moveMode) {
                exitMoveMode();
            }
        });
    };
    
    /**
     * Enter move mode
     * @param {jQuery} $chapter The chapter being moved
     * @param {Array} strings Translated strings
     */
    var enterMoveMode = function($chapter, strings) {
        // Exit any existing move mode
        exitMoveMode();
        
        moveMode = true;
        selectedChapter = $chapter;
        selectedChapterId = $chapter.data('chapterid');
        
        // Highlight the selected chapter
        $chapter.addClass('move-mode-selected');
        
        // Show drop zones
        $('.book-drop-zone').addClass('move-mode-visible');
        
        // Hide drop zones for the selected chapter itself
        $chapter.prev('.book-drop-zone').removeClass('move-mode-visible');
        $chapter.next('.book-drop-zone').removeClass('move-mode-visible');
        $chapter.nextAll('.book-drop-zone-subchapter:first').removeClass('move-mode-visible');
        
        // Add visual feedback
        $('.book_toc').addClass('move-mode-active');
        
        // Update drop zone messages
        $('.book-drop-zone.move-mode-visible .drop-message').text(strings[4] || 'Click to select destination');
        
        // Add cancel button
        if ($('#book-move-cancel').length === 0) {
            var $cancelBtn = $('<button id="book-move-cancel" class="btn btn-secondary">' + 
                             (strings[5] || 'Cancel move') + '</button>');
            $('.book_toc').prepend($cancelBtn);
            $cancelBtn.on('click', exitMoveMode);
        }
    };
    
    /**
     * Exit move mode
     */
    var exitMoveMode = function() {
        moveMode = false;
        selectedChapter = null;
        selectedChapterId = null;
        
        $('.book-chapter-item').removeClass('move-mode-selected');
        $('.book-drop-zone').removeClass('move-mode-visible');
        $('.book_toc').removeClass('move-mode-active');
        $('#book-move-cancel').remove();
    };
    
    /**
     * Perform the move operation
     */
    var performMove = function(cmid, chapterId, position, targetChapterId, parentId) {
        Ajax.call([{
            methodname: 'mod_book_reorder_chapters',
            args: {
                cmid: cmid,
                chapterId: chapterId,
                targetPosition: position,
                targetChapterId: targetChapterId || 0,
                parentId: parentId || 0
            }
        }])[0].done(function(response) {
            if (response.success) {
                // Reload the page to show the updated structure
                window.location.reload();
            } else {
                Notification.alert('Error', response.message || 'Failed to reorder chapters');
                exitMoveMode();
            }
        }).fail(function(error) {
            Notification.exception(error);
            exitMoveMode();
        });
    };

    /**
     * Setup drag and drop event handlers
     * @param {Number} cmid The course module ID
     * @param {Array} strings Translated strings
     */
    var setupDragAndDrop = function(cmid, strings) {
        var draggedElement = null;
        var draggedChapterId = null;
        var originalParent = null;
        
        // Add drag handles and drop zones
        $('.book-chapter-item').each(function() {
            var $item = $(this);
            var chapterId = $item.data('chapterid');
            var isSubchapter = $item.data('subchapter') === 1;
            
            // Check if drag handle already exists
            if ($item.find('.book-drag-handle').length === 0) {
                // Add drag handle that is draggable
                var $dragHandle = $('<span class="book-drag-handle" draggable="true" title="' + strings[0] + ' (drag or click)">' +
                                  '<i class="icon fa fa-arrows-v fa-fw" aria-hidden="true"></i></span>');
                
                // Insert drag handle at the beginning of the flex container
                $item.find('.d-flex').first().prepend($dragHandle);
            }
            
            // Make sure the chapter item itself is NOT draggable (only the handle is)
            $item.attr('draggable', false);
            
            // Add drop zone indicators if they don't exist
            if ($item.prev('.book-drop-zone').length === 0) {
                var $dropZoneBefore = $('<div class="book-drop-zone book-drop-zone-before" data-position="before" ' +
                                        'data-chapterid="' + chapterId + '">' +
                                        '<span class="drop-message">' + strings[1] + '</span></div>');
                $item.before($dropZoneBefore);
            }
            
            if ($item.next('.book-drop-zone').length === 0) {
                var $dropZoneAfter = $('<div class="book-drop-zone book-drop-zone-after" data-position="after" ' +
                                       'data-chapterid="' + chapterId + '">' +
                                       '<span class="drop-message">' + strings[1] + '</span></div>');
                $item.after($dropZoneAfter);
            }
            
            // Add subchapter drop zone for main chapters
            if (!isSubchapter && $item.nextAll('.book-drop-zone-subchapter:first').length === 0) {
                var $dropZoneSubchapter = $('<div class="book-drop-zone book-drop-zone-subchapter" ' +
                                            'data-position="subchapter" data-chapterid="' + chapterId + '">' +
                                            '<span class="drop-message">' + strings[2] + '</span></div>');
                $item.after($dropZoneSubchapter);
            }
        });
        
        // Drag start event on the drag handle
        $(document).on('dragstart', '.book-drag-handle', function(e) {
            // Don't allow drag in move mode
            if (moveMode) {
                e.preventDefault();
                return false;
            }
            
            var $chapter = $(this).closest('.book-chapter-item');
            draggedElement = $chapter[0];
            draggedChapterId = $chapter.data('chapterid');
            originalParent = $chapter.parent();
            
            $chapter.addClass('dragging');
            e.originalEvent.effectAllowed = 'move';
            e.originalEvent.dataTransfer.setData('text/html', $chapter.html());
            
            // Show drop zones
            $('.book-drop-zone').addClass('visible');
            
            // Hide drop zones for the dragged chapter itself
            $chapter.prev('.book-drop-zone').removeClass('visible');
            $chapter.next('.book-drop-zone').removeClass('visible');
            $chapter.nextAll('.book-drop-zone-subchapter:first').removeClass('visible');
        });
        
        // Drag over event for drop zones
        $(document).on('dragover', '.book-drop-zone', function(e) {
            e.preventDefault();
            e.originalEvent.dataTransfer.dropEffect = 'move';
            $(this).addClass('drag-over');
        });
        
        // Drag leave event for drop zones
        $(document).on('dragleave', '.book-drop-zone', function(e) {
            $(this).removeClass('drag-over');
        });
        
        // Drop event
        $(document).on('drop', '.book-drop-zone', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            if (!draggedElement) {
                return;
            }
            
            var $dropZone = $(this);
            var targetChapterId = $dropZone.data('chapterid');
            var position = $dropZone.data('position');
            
            // Clean up UI
            $('.book-drop-zone').removeClass('visible drag-over');
            $(draggedElement).removeClass('dragging');
            
            // Perform the move
            performMove(cmid, draggedChapterId, position, targetChapterId, position === 'subchapter' ? targetChapterId : 0);
            
            draggedElement = null;
            draggedChapterId = null;
            originalParent = null;
        });
        
        // Drag end event (cleanup) on the drag handle
        $(document).on('dragend', '.book-drag-handle', function(e) {
            var $chapter = $(this).closest('.book-chapter-item');
            $chapter.removeClass('dragging');
            $('.book-drop-zone').removeClass('visible drag-over');
            draggedElement = null;
            draggedChapterId = null;
            originalParent = null;
        });
        
        // Prevent default drag over on non-drop zones
        $(document).on('dragover', function(e) {
            if (!$(e.target).hasClass('book-drop-zone')) {
                e.preventDefault();
            }
        });
    };

    return {
        init: init
    };
});
