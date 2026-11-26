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
 * Site-wide settings for webcam proctoring.
 *
 * @package   quizaccess_webcamproctor
 * @copyright 2026 LMS Labs
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings->add(new admin_setting_heading(
        'quizaccess_webcamproctor/serviceheading',
        get_string('setting_serviceheading', 'quizaccess_webcamproctor'),
        get_string('setting_serviceheading_desc', 'quizaccess_webcamproctor')
    ));

    $settings->add(new admin_setting_configtext(
        'quizaccess_webcamproctor/apiurl',
        get_string('setting_apiurl', 'quizaccess_webcamproctor'),
        get_string('setting_apiurl_desc', 'quizaccess_webcamproctor'),
        '',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'quizaccess_webcamproctor/apikey',
        get_string('setting_apikey', 'quizaccess_webcamproctor'),
        get_string('setting_apikey_desc', 'quizaccess_webcamproctor'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'quizaccess_webcamproctor/processorname',
        get_string('setting_processorname', 'quizaccess_webcamproctor'),
        get_string('setting_processorname_desc', 'quizaccess_webcamproctor'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_heading(
        'quizaccess_webcamproctor/responsibleaiheading',
        get_string('setting_responsibleaiheading', 'quizaccess_webcamproctor'),
        get_string('setting_responsibleaiheading_desc', 'quizaccess_webcamproctor')
    ));

    $settings->add(new admin_setting_configcheckbox(
        'quizaccess_webcamproctor/paused',
        get_string('setting_paused', 'quizaccess_webcamproctor'),
        get_string('setting_paused_desc', 'quizaccess_webcamproctor'),
        0
    ));

    $retention = [
        0 => get_string('retention_never', 'quizaccess_webcamproctor'),
        30 => get_string('numdays', '', 30),
        60 => get_string('numdays', '', 60),
        90 => get_string('numdays', '', 90),
        180 => get_string('numdays', '', 180),
        365 => get_string('numdays', '', 365),
    ];
    $settings->add(new admin_setting_configselect(
        'quizaccess_webcamproctor/retentiondays',
        get_string('setting_retentiondays', 'quizaccess_webcamproctor'),
        get_string('setting_retentiondays_desc', 'quizaccess_webcamproctor'),
        90,
        $retention
    ));
}
