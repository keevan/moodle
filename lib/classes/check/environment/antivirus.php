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
 * Checks status of antivirus scanners by looking back at any recent scans.
 *
 * @package    core
 * @category   check
 * @copyright  2020 Kevin Pham <kevinpham@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace core\check\environment;

defined('MOODLE_INTERNAL') || die();

use core\check\check;
use core\check\result;

/**
 * Checks status of antivirus scanners by looking back at any recent scans.
 *
 * @copyright  2020 Kevin Pham <kevinpham@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class antivirus extends check {

    /**
     * Get the short check name
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('check_antivirus_name', 'report_security');
    }

    /**
     * A link to a place to action this
     *
     * @return action_link|null
     */
    public function get_action_link(): ?\action_link {
        return new \action_link(
            new \moodle_url('/admin/settings.php?section=manageantiviruses'),
            get_string('antivirussettings', 'antivirus'));
    }

    /**
     * Return result
     * @return result
     */
    public function get_result(): result {
        global $CFG, $DB, $USER;
        $details = get_string('check_antivirus_details', 'report_security');

        // If no scanners are enabled, then return an INFO describing this state.
        if (empty($CFG->antiviruses)) {
            $status = result::INFO;
            $summary = get_string('check_antivirus_info', 'report_security');
            return new result($status, $summary, $details);
        }

        $logmanager = get_log_manager();
        $readers = $logmanager->get_readers();
        $reader = reset($readers);

        // If reader is not a sql_internal_table_reader return info since we
        // aren't able to fetch the required information. Legacy logs are not
        // supported here. They do not hold enough adequate information to be
        // used for these checks.
        if (!($reader instanceof \core\log\sql_internal_table_reader)) {
            $status = result::INFO;
            $summary = get_string('check_antivirus_logstore_not_supported', 'report_security');
            return new result($status, $summary, $details);
        }

        // If there has been a recent timestamp within threshold period, then
        // set the status to ERROR and describe the problem, e.g. X issues in
        // the last N hours.
        $threshold = get_config('antivirus', 'threshold');
        if (empty($threshold)) {
            $threshold = core\antivirus\scanner::DEFAULT_SCAN_ERROR_THRESHOLD;
            set_config('threshold', $threshold, 'antivirus'); // In seconds.
        }
        $logtable = $reader->get_internal_log_table_name();
        $timefield = 'timecreated';
        $params = array('userid' => $USER->id);

        $lookbackselect = ':lookback';
        $lookback = time() - $threshold;
        $params['lookback'] = $lookback;

        // Type of "targets" to include.
        list($targetsqlin, $inparams) = $DB->get_in_or_equal([
            'antivirus_scan_file',
            'antivirus_scan_data',
        ], SQL_PARAMS_NAMED);
        $params = array_merge($inparams, $params);

        // Type of "actions" to include.
        list($actionsqlin, $inparams) = $DB->get_in_or_equal([
            'error',
        ], SQL_PARAMS_NAMED);
        $params = array_merge($inparams, $params);

        $count = $DB->get_record_sql("SELECT COUNT(*) AS nbresults
                                      FROM {" . $logtable . "}
                                      WHERE $timefield > $lookbackselect
                                        AND target $targetsqlin
                                        AND action $actionsqlin", $params);
        $totalerrors = $count->nbresults;
        if (!empty($totalerrors)) {
            $status = result::ERROR;
            $summary = get_string('check_antivirus_error', 'report_security', [
                'errors' => $totalerrors,
                'lookback' => format_time($threshold)
            ]);
        } else if (!empty($CFG->antiviruses)) {
            $status = result::OK;
            // Fetch count of enabled antiviruses (we don't care about which ones).
            $totalantiviruses = !empty($CFG->antiviruses) ? count(explode(',', $CFG->antiviruses)) : 0;
            $summary = get_string('check_antivirus_ok', 'report_security', [
                'scanners' => $totalantiviruses,
                'lookback' => format_time($threshold)
            ]);
        }
        return new result($status, $summary, $details);
    }
}

