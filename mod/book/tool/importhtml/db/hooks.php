<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Book import hooks.
 *
 * @package    booktool_importhtml
 * @copyright  2025 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$callbacks = [
    [
        'hook' => \mod_book\hook\fetch_book_actions::class,
        'callback' => [\booktool_importhtml\hook_callbacks::class, 'fetch_book_actions'],
    ],
    [
        'hook' => \mod_book\hook\fetch_chapter_actions::class,
        'callback' => [\booktool_importhtml\hook_callbacks::class, 'fetch_chapter_actions'],
    ],
];
