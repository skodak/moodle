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
// phpcs:disable moodle.Files.RequireLogin.Missing

/**
 * Page for preventing WS and session errors during login/logout.
 *
 * @package    core_auth
 * @category   test
 * @copyright  2023 Open LMS
 * @author     Petr Skoda
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Make sure logout will not collide with any pending WS request.
usleep(300000);

require(__DIR__.'/../../../config.php');

$behatrunning = defined('BEHAT_SITE_RUNNING') && BEHAT_SITE_RUNNING;
if (!$behatrunning) {
    redirect(new moodle_url('/'));
}

$PAGE->set_url('/auth/tests/behat/wait.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('maintenance');
$PAGE->set_popup_notification_allowed(false);

echo $OUTPUT->header();
echo 'Behat wait page.';
echo $OUTPUT->footer();
