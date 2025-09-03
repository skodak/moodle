<?php
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
 * Book view page
 *
 * @package    mod_book
 * @copyright  2004-2011 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/lib.php');
require_once(__DIR__.'/locallib.php');
require_once($CFG->libdir.'/completionlib.php');

$id        = optional_param('id', 0, PARAM_INT);        // Course Module ID
$bid       = optional_param('b', 0, PARAM_INT);         // Book id
$chapterid = optional_param('chapterid', -1, PARAM_INT); // Chapter ID
$edit      = optional_param('edit', -1, PARAM_BOOL);    // Edit mode

// =========================================================================
// security checks START - teachers edit; students view
// =========================================================================
if ($id) {
    $cm = get_coursemodule_from_id('book', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);
    $book = $DB->get_record('book', array('id'=>$cm->instance), '*', MUST_EXIST);
} else {
    $book = $DB->get_record('book', array('id'=>$bid), '*', MUST_EXIST);
    $cm = get_coursemodule_from_instance('book', $book->id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);
    $id = $cm->id;
}

require_course_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/book:read', $context);

$allowedit  = has_capability('mod/book:edit', $context);
$viewhidden = has_capability('mod/book:viewhiddenchapters', $context);

if ($allowedit) {
    if ($edit != -1 and confirm_sesskey()) {
        $USER->editing = $edit;
    } else {
        if (isset($USER->editing)) {
            $edit = $USER->editing;
        } else {
            $edit = 0;
        }
    }
} else {
    $edit = 0;
}

// read chapters
$chapters = book_preload_chapters($book);

if ($allowedit and !$chapters) {
    redirect('edit.php?cmid='.$cm->id); // No chapters - add new one.
}
// Check chapterid and read chapter data
if ($chapterid == -1) { // Go to first chapter if no given.
    foreach ($chapters as $ch) {
        if ($edit || ($ch->hidden && $viewhidden)) {
            $chapterid = $ch->id;
            break;
        }
        if (!$ch->hidden) {
            $chapterid = $ch->id;
            break;
        }
    }
}
if (!$chapterid) {
    if ($edit) {
        $viewall = false;
    } else {
        $viewall = true;
    }
} else {
    $viewall = false;
}


// Prepare header.
$pagetitle = $book->name;
if ($chapterid && $chapter = $DB->get_record('book_chapters', ['id' => $chapterid, 'bookid' => $book->id])) {
    $pagetitle .= ": {$chapter->title}";
} else {
    $chapterid = 0;
    $chapter = false;
}

$PAGE->set_url('/mod/book/view.php', ['id' => $id, 'chapterid' => $chapterid]);
$PAGE->set_other_editing_capability('mod/book:edit');
$PAGE->set_title($pagetitle);
$PAGE->set_heading($course->fullname);

if (!$chapters) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('nocontent', 'mod_book'), 'info', false);
    echo $OUTPUT->footer();
    die;
}

// No content in the book.
if (!$chapter) {
    $PAGE->add_body_class('limitedwidth');
    $PAGE->add_body_class('mod_book_view_all');

    $data = [
        'title' => 'Book actions', // TODO: localise
        'actions' => [],
    ];
    $data['actions'][] = [
        'customhtml' => '<a href="#" class="dropdown-item" onclick="window.print();">' . $OUTPUT->pix_icon('e/print', '') . 'Print book</a>',
    ];

    $hook = new \mod_book\hook\fetch_book_actions($book, $context, $cm);
    \core\di::get(\core\hook\manager::class)->dispatch($hook);
    foreach ($hook->get_actions() as $action) {
        if ($action['icon']) {
            $action['icon'] = $OUTPUT->render($action['icon']);
        }
        $data['actions'][] = $action;
    }
    if ($data['actions']) {
        $PAGE->add_header_action($OUTPUT->render_from_template('mod_book/dropdown', $data));
    }

    echo $OUTPUT->header();

    $courseurl = new moodle_url('/course/view.php', ['id' => $course->id]);
    echo '<div class="mod_book_print_header">';
    echo '<h1>' . format_string($book->name) . '</h1>';
    echo '<dl>';
    echo '<dt>' . get_string('course') . '</dt><dd>' . \core\output\html_writer::link($courseurl, format_string($course->fullname)) . '</dd>';
    echo '<dt>' . get_string('printedby', 'booktool_print') . '</dt><dd>' . fullname($USER) . '</dd>';
    echo '<dt>' . get_string('date') . '</dt><dd>' . userdate(time()) . '</dd>';
    echo '</dl>';
    $introtext = file_rewrite_pluginfile_urls($book->intro, 'pluginfile.php', $context->id, 'mod_book', 'intro', null);
    $intro = format_text($introtext, $book->introformat, array('noclean' => true, 'context' => $context));
    echo '<div class="mod_book_intro">' . $intro . '</div>';
    echo '</div>';

    if ($chapters) {
        echo '<div class="book_toc_main">';
            echo book_get_main_toc($chapters, $chapter, $book, $cm, $edit, $viewall);
        echo '</div>';
    }

    if ($viewall) {
        echo '<article class="mod_book_chapters">';
        $i = 0;
        foreach ($chapters as $ch) {
            $i++;
            if ($ch->hidden and !$viewhidden) {
                continue;
            }
            $chapter = $DB->get_record('book_chapters', ['id' => $ch->id]);
            $chid = 'mod_book_chanpter_' . $chapter->id;
            $returnurl = new moodle_url($returnurl = $PAGE->url);
            $returnurl->set_anchor($chid);
            $returnurl = $returnurl->out_as_local_url(false) ;

            $data = [
                'title' => 'Chapter actions', // TODO: localise
                'actions' => [],
            ];

            $chaptertitle = format_string($chapter->title);

            $data['actions'][] = [
                'label' => get_string('viewchapter', 'mod_book'),
                'url' => new moodle_url('view.php', array('id' => $cm->id, 'chapterid' => $ch->id)),
                'icon' => $OUTPUT->pix_icon('e/file-text', ''),
            ];

            if ($allowedit) {
                $data['actions'][] = [
                    'divider' => true,
                ];

                if ($i != 1) {
                    $data['actions'][] = [
                        'label' => get_string('moveup', 'mod_book'),
                        'url' => new moodle_url('move.php', array('id' => $cm->id, 'chapterid' => $ch->id, 'up' => '1', 'sesskey' => $USER->sesskey, 'returnurl' => $returnurl)),
                        'icon' => $OUTPUT->pix_icon('t/up', ''),
                    ];
                }
                if ($i != count($chapters)) {
                    $data['actions'][] = [
                        'label' => get_string('movedown', 'mod_book'),
                        'url' => new moodle_url('move.php', array('id' => $cm->id, 'chapterid' => $ch->id, 'up' => '0', 'sesskey' => $USER->sesskey, 'returnurl' => $returnurl)),
                        'icon' => $OUTPUT->pix_icon('t/down', ''),
                    ];
                }
                $data['actions'][] = [
                    'divider' => true,
                ];

                $data['actions'][] = [
                    'label' => get_string('edit'),
                    'url' => new moodle_url('edit.php', array('cmid' => $cm->id, 'id' => $chapter->id, 'returnurl' => $returnurl)),
                    'icon' => $OUTPUT->pix_icon('t/edit', get_string('editchapter', 'mod_book', $chaptertitle)),
                ];

                if ($chapter->hidden) {
                    $label = get_string('show');
                    $icon = $OUTPUT->pix_icon('t/show', get_string('showchapter', 'mod_book', $chaptertitle));
                } else {
                    $label = get_string('hide');
                    $icon = $OUTPUT->pix_icon('t/hide', get_string('hidechapter', 'mod_book', $chaptertitle));
                }
                $data['actions'][] = [
                    'label' => $label,
                    'url' => new moodle_url('show.php', array('id' => $cm->id, 'chapterid' => $chapter->id, 'sesskey' => $USER->sesskey, 'returnurl' => $returnurl)),
                    'icon' => $icon,
                ];

                $deleteaction = new confirm_action(get_string('deletechapter', 'mod_book', $chaptertitle));
                $action = $OUTPUT->action_link(
                    new moodle_url('delete.php', [
                        'id' => $cm->id,
                        'chapterid' => $chapter->id,
                        'sesskey' => sesskey(),
                        'confirm' => 1,
                    ]),
                    get_string('delete'),
                    $deleteaction,
                    ['class' => 'dropdown-item text-danger'],
                    new pix_icon('t/delete', get_string('deletechapter', 'mod_book', $chaptertitle))
                );
                $data['actions'][] = [
                    'customhtml' => $action,
                ];
            }

            if ($data['actions']) {
                $dropdown = $OUTPUT->render_from_template('mod_book/dropdown', $data);
                echo '<div class="float-end d-print-none">' . $dropdown . '</div>';
            }

            if ($chapter->hidden) {
                echo '<div class="dimmed_text">';
            }

            if ($book->customtitles) {
                echo '<div id="'. $chid . '" />';
            } else {
                if (!$chapter->subchapter) {
                    $currtitle = book_get_chapter_title($chapter->id, $chapters, $book, $context);
                    echo $OUTPUT->heading($currtitle, 2, 'h3', $chid);
                } else {
                    $currsubtitle = book_get_chapter_title($chapter->id, $chapters, $book, $context);
                    echo $OUTPUT->heading($currsubtitle, 3, 'h4', $chid);
                }
            }

            $chaptertext = file_rewrite_pluginfile_urls($chapter->content, 'pluginfile.php', $context->id, 'mod_book', 'chapter', $chapter->id);
            echo format_text($chaptertext, $chapter->contentformat, ['noclean' => true, 'overflowdiv' => false, 'context' => $context]);

            if ($chapter->hidden) {
                echo '</div>';
            }
        }

        echo '</article>';
    }

    echo $OUTPUT->footer();
    die;
}

    $PAGE->activityheader->disable();
    //$PAGE->set_secondary_navigation(false);

    $PAGE->add_body_class('mod_book_view_chapter');
    // The chapter doesnt exist or it is hidden for students.
    if (!$chapter or ($chapter->hidden and !$viewhidden)) {
        $courseurl = new moodle_url('/course/view.php', ['id' => $course->id]);
        throw new moodle_exception('errorchapter', 'mod_book', $courseurl);
    }
    // Add the Book TOC block.
    book_view($book, $chapter, \mod_book\helper::is_last_visible_chapter($chapter->id, $chapters), $course, $cm, $context);

    $firstchapter = reset($chapters);
    $lastchapter = end($chapters);

    echo $OUTPUT->header();

    // The chapter itself.
    $hidden = $chapter->hidden ? ' dimmed_text' : null;
    echo $OUTPUT->box_start('container m-auto book_content' . $hidden, 'mod_book-chapter');
    echo '<div class="row">';

    echo '<div class="col-3">';
    echo '<div class="book_toc_chapter position-sticky d-print-none" style="top:5rem">';
    echo '<h2 class="h4">' . format_string($book->name). '</h2>';
    echo book_get_chanpter($chapters, $chapter, $book, $cm);

    echo '</div>';
    echo '</div>';

    echo '<div class="col-9">';

    $data = [
        'title' => 'Actions', // TODO: localise
        'actions' => [],
    ];

    if ($edit) {
        $data['actions'][] = [
            'label' => 'Edit book structure',
            'url' => new moodle_url('/mod/book/view.php', ['id' => $cm->id, 'chapterid' => 0]),
            'icon' => $OUTPUT->pix_icon('icon', '', 'mod_book'),
        ];
    } else {
        $data['actions'][] = [
            'label' => get_string('viewallchapters', 'mod_book'),
            'url' => new moodle_url('/mod/book/view.php', ['id' => $cm->id, 'chapterid' => 0]),
            'icon' => $OUTPUT->pix_icon('icon', '', 'mod_book'),
        ];
    }

$data['actions'][] = [
    'customhtml' => '<a href="#" class="dropdown-item" onclick="window.print();">' . $OUTPUT->pix_icon('e/print', '') . 'Print chapter</a>',
];

    if ($allowedit) {
        $data['actions'][] = [
            'divider' => true,
        ];

        if ($chapter->id != $firstchapter->id) {
            $data['actions'][] = [
                'label' => 'Move chapter up', // TODO localise
                'url' => new moodle_url('move.php', array('id' => $cm->id, 'chapterid' => $chapter->id, 'up' => '1', 'sesskey' => $USER->sesskey)),
                'icon' => $OUTPUT->pix_icon('t/up', ''),
            ];
        }
        if ($chapter->id != $lastchapter->id) {
            $data['actions'][] = [
                'label' => 'Move chapter down', // TODO localise
                'url' => new moodle_url('move.php', array('id' => $cm->id, 'chapterid' => $chapter->id, 'up' => '0', 'sesskey' => $USER->sesskey)),
                'icon' => $OUTPUT->pix_icon('t/down', ''),
            ];
        }
    }


$chaptertitle = format_string($chapter->title);

    $button = '';
    if ($allowedit) {

        $data['actions'][] = [
            'divider' => true,
        ];

        $url = new moodle_url('edit.php', array('cmid' => $cm->id, 'id' => $chapter->id));
        $button = $OUTPUT->single_button($url, get_string('edit'), 'get');

        $buttontitle = get_string('addafterchapter', 'mod_book', ['title' => $chapter->title]);
        $data['actions'][] = [
            'label' => get_string('addafter', 'mod_book'),
            'url' => new moodle_url('edit.php', array('cmid' => $cm->id, 'pagenum' => $chapter->pagenum, 'subchapter' => $chapter->subchapter)),
            'icon' => $OUTPUT->pix_icon('add', $buttontitle, 'mod_book'),
        ];

        if ($chapter->hidden) {
            $label = get_string('show');
            $icon = $OUTPUT->pix_icon('t/show', get_string('showchapter', 'mod_book', $chaptertitle));
        } else {
            $label = get_string('hide');
            $icon = $OUTPUT->pix_icon('t/hide', get_string('hidechapter', 'mod_book', $chaptertitle));
        }
        $data['actions'][] = [
            'label' => $label,
            'url' => new moodle_url('show.php', array('id' => $cm->id, 'chapterid' => $chapter->id, 'sesskey' => $USER->sesskey)),
            'icon' => $icon,
        ];

        $deleteaction = new confirm_action(get_string('deletechapter', 'mod_book', $chaptertitle));
        $action = $OUTPUT->action_link(
            new moodle_url('delete.php', [
                'id' => $cm->id,
                'chapterid' => $chapter->id,
                'sesskey' => sesskey(),
                'confirm' => 1,
            ]),
            get_string('delete'),
            $deleteaction,
            ['class' => 'dropdown-item text-danger'],
            new pix_icon('t/delete', get_string('deletechapter', 'mod_book', $chaptertitle))
        );
        $data['actions'][] = [
            'customhtml' => $action,
        ];
    }


    $hook = new \mod_book\hook\fetch_chapter_actions($chapter, $book, $context, $cm);
    \core\di::get(\core\hook\manager::class)->dispatch($hook);
    $actions = $hook->get_actions();
    if ($actions) {
        foreach ($actions as $action) {
            if ($action['icon']) {
                $action['icon'] = $OUTPUT->render($action['icon']);
            }
            $data['actions'][] = $action;
        }
    }

    if ($data['actions']) {
        $dropdown = $OUTPUT->render_from_template('mod_book/dropdown', $data);
        echo '<div class="float-end d-print-none">' . $button . $dropdown . '</div>';
    }

    if (!$book->customtitles) {
        if (!$chapter->subchapter) {
            $currtitle = book_get_chapter_title($chapter->id, $chapters, $book, $context);
            echo $OUTPUT->heading($currtitle, 2, 'h3');
        } else {
            $currtitle = book_get_chapter_title($chapters[$chapter->id]->parent, $chapters, $book, $context);
            $currsubtitle = book_get_chapter_title($chapter->id, $chapters, $book, $context);
            echo $OUTPUT->heading($currtitle, 2, 'h3');
            echo $OUTPUT->heading($currsubtitle, 3, 'h4');
        }
    }

    $chaptertext = file_rewrite_pluginfile_urls($chapter->content, 'pluginfile.php', $context->id, 'mod_book',
        'chapter', $chapter->id);
    echo format_text($chaptertext, $chapter->contentformat, ['noclean' => true, 'overflowdiv' => false,
        'context' => $context]);

    $actionmenu = new \mod_book\output\main_action_menu($cm->id, $chapters, $chapter);
    $prevchapter = $actionmenu->get_previous_chapter();
    $nextcahpter = $actionmenu->get_next_chapter();

    if ($prevchapter || $nextcahpter) {
        echo '<nav class="bg-light my-4 p-3 d-flex justify-content-between d-print-none">';

        if ($prevchapter) {
            $strprev = get_string('navprev', 'mod_book');
            $prevurl = new moodle_url('/mod/book/view.php', ['id' => $cm->id, 'chapterid' => $prevchapter->id]);
            $nav = <<<EOF
        <div class="mr-1">
          <div>
          <a href="$prevurl">
            <small>
              <i class="fa-solid fa-arrow-left-long mr-1"></i>
              $strprev
            </small><br />
            $prevchapter->title
          </a>
          </div>
        </div>
EOF;

            echo $nav;
        } else {
            echo '<div class="text-right ml-1"></div>';
        }
        if ($nextcahpter) {
            $strnext = get_string('navnext', 'mod_book');
            $nexturl = new moodle_url('/mod/book/view.php', ['id' => $cm->id, 'chapterid' => $nextcahpter->id]);
            $nav = <<<EOF
        <div class="text-right ml-1">

          <div class="text-right">
          <a href="$nexturl">          
            <small>$strnext<i class="fa-solid fa-arrow-right-long ml-1"></i></small><br />
            $nextcahpter->title
          </a>
          </div>
        </div>
EOF;

            echo $nav;
        }

        echo '</nav>';
    }

    echo '</div>';
    echo '</div>';
    echo $OUTPUT->box_end();

    if (core_tag_tag::is_enabled('mod_book', 'book_chapters')) {
        echo $OUTPUT->tag_list(core_tag_tag::get_item_tags('mod_book', 'book_chapters', $chapter->id), null, 'book-tags');
    }

echo $OUTPUT->footer();
