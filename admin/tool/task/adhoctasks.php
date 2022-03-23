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
 * Ad hoc task list.
 *
 * @package    tool_task
 * @copyright  Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/cronlib.php');

admin_externalpage_setup('adhoctasks');

$failedonly = optional_param('failedonly', false, PARAM_BOOL);
$classname = optional_param('classname', null, PARAM_RAW);

$renderer = $PAGE->get_renderer('tool_task');
echo $OUTPUT->header();

if (!get_config('core', 'cron_enabled')) {
    echo $renderer->cron_disabled();
}

if ($classname) {
    // This does not work.
    $PAGE->navbar->add("$classname " . ($failedonly ? get_string('failed', 'tool_task') : ""), $PAGE->url);
    $tasks = core\task\manager::get_adhoc_tasks($classname, $failedonly);
    echo $renderer->adhoc_tasks_class_table($classname, $tasks, ['failedonly' => $failedonly]);
} else {
    $summary = core\task\manager::get_adhoc_tasks_summary();
    echo $renderer->adhoc_tasks_summary_table($summary);
}
echo $OUTPUT->footer();
