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

namespace mod_book\hook;

use stdClass;
use moodle_url;

/**
 * Discover additional chapter actions.
 *
 * @package    mod_book
 * @copyright  2025 Petr Skoda
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\tags('actions', 'mod_book')]
#[\core\attribute\label('Allows plugins to extends book actions menu')]
class fetch_chapter_actions {
    /** @var stdClass */
    public $chapter;
    /** @var stdClass */
    public $book;
    /** @var \context_module */
    public $context;
    /** @var stdClass  */
    public $cm;
    /** @var array action menu items */
    protected $actions;

    public function __construct(stdClass $chapter, stdClass $book, \context_module $context, stdClass $cm) {
        $this->chapter = $chapter;
        $this->book = $book;
        $this->context = $context;
        $this->cm = $cm;
    }

    public function add_item(string $name, moodle_url $url, ?\pix_icon $icon = null): void {
        $this->actions[] = ['name' => $name, 'url' => $url, 'icon' => $icon];
    }

    public function get_actions(): array {
        return $this->actions;
    }
}