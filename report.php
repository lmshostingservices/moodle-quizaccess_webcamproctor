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
 * Proctoring report: review flagged attempts and compare captured images.
 *
 * @package   quizaccess_webcamproctor
 * @copyright 2025 Essay Grader AI
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/csvlib.class.php');

use quizaccess_webcamproctor\local\manager;

$id = required_param('id', PARAM_INT);
$attemptid = optional_param('attemptid', 0, PARAM_INT);
$filter = optional_param('filter', 'all', PARAM_ALPHA);
$action = optional_param('action', '', PARAM_ALPHA);
$proctorattemptid = optional_param('proctorattemptid', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$perpage = min(optional_param('perpage', 20, PARAM_INT), 100);
$download = optional_param('download', '', PARAM_ALPHA);

$cm = get_coursemodule_from_id('quiz', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('quizaccess/webcamproctor:viewreport', $context);

$component = 'quizaccess_webcamproctor';
$settings = $DB->get_record('quizaccess_webcamproctor', ['quizid' => $quiz->id]);
$canmanage = has_capability('quizaccess/webcamproctor:manageattempts', $context);
$showverifyid = $settings && $settings->verifyidintegration
    && has_capability('quizaccess/webcamproctor:viewverifyid', $context)
    && $DB->get_manager()->table_exists('verifyid_attempts');
if (!in_array($filter, ['all', 'flagged', 'pending', 'clean'])) {
    $filter = 'all';
}

$baseurl = new moodle_url('/mod/quiz/accessrule/webcamproctor/report.php', ['id' => $id]);
$PAGE->set_url($baseurl, ['filter' => $filter]);
$PAGE->set_title(get_string('report_title', $component));
$PAGE->set_heading(format_string($course->fullname, true, ['context' => $context]));
$PAGE->navbar->add(get_string('report_title', $component));

// Review actions.
if ($action && $proctorattemptid) {
    require_sesskey();
    require_capability('quizaccess/webcamproctor:manageattempts', $context);
    $proctorattempt = $DB->get_record(
        'quizaccess_webcamproctor_attempts',
        ['id' => $proctorattemptid, 'quizid' => $quiz->id],
        '*',
        MUST_EXIST
    );
    $update = (object) ['id' => $proctorattempt->id, 'reviewedby' => $USER->id, 'reviewedtime' => time(),
        'timemodified' => time()];
    $returnurl = new moodle_url($baseurl, ['filter' => $filter]);
    switch ($action) {
        case 'approve':
            $update->status = 'clean';
            $update->submissionblocked = 0;
            $message = get_string('attempt_clean', $component);
            break;
        case 'flag':
            $update->status = 'flagged';
            $message = get_string('attempt_flagged', $component);
            break;
        case 'unblock':
            $update->submissionblocked = 0;
            $message = get_string('submission_unblocked', $component);
            break;
        default:
            throw new moodle_exception('invalidaction', 'error');
    }
    $DB->update_record('quizaccess_webcamproctor_attempts', $update);
    redirect($returnurl, $message, null, \core\output\notification::NOTIFY_SUCCESS);
}

$namefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;

/**
 * Latest Verify ID attempt for each user, if that plugin is installed.
 *
 * @param array $userids User ids.
 * @return array userid => record.
 */
function quizaccess_webcamproctor_verifyid_for_users(array $userids): array {
    global $DB;
    if (empty($userids)) {
        return [];
    }
    [$insql, $params] = $DB->get_in_or_equal(array_unique($userids), SQL_PARAMS_NAMED, 'vu');
    $result = [];
    foreach ($DB->get_records_select('verifyid_attempts', "userid $insql", $params, 'userid ASC, id DESC') as $record) {
        if (!isset($result[$record->userid])) {
            $result[$record->userid] = $record;
        }
    }
    return $result;
}

/**
 * Translate a status code into a label.
 *
 * @param string $status The stored status.
 * @return string
 */
function quizaccess_webcamproctor_status_label(string $status): string {
    $map = ['clean' => 'attempt_clean', 'flagged' => 'attempt_flagged', 'blocked' => 'attempt_blocked'];
    return get_string($map[$status] ?? 'attempt_pending', 'quizaccess_webcamproctor');
}

// CSV export.
if ($download === 'csv') {
    $sql = "SELECT pa.*, u.email, qa.timefinish, $namefields
              FROM {quizaccess_webcamproctor_attempts} pa
              JOIN {user} u ON u.id = pa.userid
         LEFT JOIN {quiz_attempts} qa ON qa.id = pa.attemptid
             WHERE pa.quizid = :quizid
          ORDER BY pa.timecreated DESC";
    $rows = $DB->get_records_sql($sql, ['quizid' => $quiz->id]);
    $verifyid = $showverifyid ? quizaccess_webcamproctor_verifyid_for_users(array_column($rows, 'userid')) : [];

    $csv = new csv_export_writer();
    $csv->set_filename(format_string($quiz->name) . '_' . get_string('report_title', $component));
    $header = [get_string('student', $component), get_string('email'), get_string('col_status', $component),
        get_string('col_snapshots', $component), get_string('col_confidence', $component),
        get_string('col_reason', $component), get_string('col_started', $component), get_string('col_finished', $component),
        get_string('col_held', $component)];
    if ($showverifyid) {
        $header[] = get_string('col_verifyid', $component);
        $header[] = get_string('col_verifyidscore', $component);
    }
    $csv->add_data($header);
    foreach ($rows as $row) {
        $line = [fullname($row), $row->email, quizaccess_webcamproctor_status_label($row->status), $row->snapshotcount,
            $row->lowestconfidence !== null ? $row->lowestconfidence . '%' : '', (string) $row->flaggedreason,
            userdate($row->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
            $row->timefinish ? userdate($row->timefinish, get_string('strftimedatetimeshort', 'langconfig')) : '',
            $row->submissionblocked ? get_string('yes') : get_string('no')];
        if ($showverifyid) {
            $vi = $verifyid[$row->userid] ?? null;
            $line[] = $vi ? $vi->status : get_string('verifyid_not_completed', $component);
            $line[] = ($vi && $vi->similarity !== null) ? $vi->similarity . '%' : '';
        }
        $csv->add_data($line);
    }
    $csv->download_file();
    die();
}

$bs = manager::bs_prefix();
$PAGE->requires->js_call_amd('quizaccess_webcamproctor/report', 'init');

/**
 * Render an image thumbnail that opens the enlarged view.
 *
 * @param string $src The data URI.
 * @param string $label Accessible label.
 * @param string $class Extra CSS class.
 * @return string HTML.
 */
function quizaccess_webcamproctor_thumbnail(string $src, string $label, string $class = ''): string {
    $bs = manager::bs_prefix();
    $img = html_writer::img($src, $label, ['class' => 'webcamproctor-thumbnail ' . $class]);
    return html_writer::tag('button', $img, [
        'type' => 'button',
        'class' => 'webcamproctor-thumbnail-button',
        $bs . 'toggle' => 'modal',
        $bs . 'target' => '#webcamproctor-image-modal',
        'data-title' => $label,
        'aria-label' => get_string('enlarge', 'quizaccess_webcamproctor', $label),
    ]);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('report_heading', $component));
echo html_writer::tag('p', get_string('report_description', $component));
if (manager::is_paused()) {
    echo $OUTPUT->notification(get_string('ai_paused_notice', $component), 'warning');
} else if (manager::get_api() === null) {
    echo $OUTPUT->notification(get_string('ai_noservice_notice', $component), 'info');
}

echo html_writer::div(html_writer::link(
    new moodle_url($baseurl, ['download' => 'csv']),
    get_string('exportcsv', $component),
    ['class' => 'btn btn-secondary']
), 'webcamproctor-report-actions');

$tabs = [];
foreach (['all', 'flagged', 'pending', 'clean'] as $f) {
    $tabs[] = new tabobject($f, new moodle_url($baseurl, ['filter' => $f]), get_string('filter_' . $f, $component));
}
echo $OUTPUT->tabtree($tabs, $filter);

$where = 'pa.quizid = :quizid';
$params = ['quizid' => $quiz->id];
if ($filter === 'flagged' || $filter === 'clean') {
    $where .= ' AND pa.status = :status';
    $params['status'] = $filter;
} else if ($filter === 'pending') {
    $where .= " AND pa.status IN ('pending', 'processing')";
}

$total = $DB->count_records_sql("SELECT COUNT(1) FROM {quizaccess_webcamproctor_attempts} pa WHERE $where", $params);
$attempts = $DB->get_records_sql("SELECT pa.*, qa.timefinish, $namefields
                                    FROM {quizaccess_webcamproctor_attempts} pa
                                    JOIN {user} u ON u.id = pa.userid
                               LEFT JOIN {quiz_attempts} qa ON qa.id = pa.attemptid
                                   WHERE $where
                                ORDER BY pa.timecreated DESC", $params, $page * $perpage, $perpage);

if (empty($attempts)) {
    echo $OUTPUT->notification(get_string('no_attempts', $component), 'info');
} else {
    // Prefetch images and reviewers to avoid one query per row.
    $imageids = [];
    $needlatest = [];
    $reviewerids = [];
    foreach ($attempts as $attempt) {
        if ($attempt->baselineimageid) {
            $imageids[] = $attempt->baselineimageid;
        }
        if ($attempt->flaggedsnapshotid) {
            $imageids[] = $attempt->flaggedsnapshotid;
        } else {
            $needlatest[] = $attempt->id;
        }
        if ($attempt->reviewedby) {
            $reviewerids[] = $attempt->reviewedby;
        }
    }
    $images = $imageids ? $DB->get_records_list('quizaccess_webcamproctor_images', 'id', array_unique($imageids)) : [];
    $latest = [];
    if ($needlatest) {
        [$insql, $inparams] = $DB->get_in_or_equal($needlatest, SQL_PARAMS_NAMED);
        $sql = "SELECT wi.*
                  FROM {quizaccess_webcamproctor_images} wi
                  JOIN (SELECT attemptid, MAX(id) AS maxid
                          FROM {quizaccess_webcamproctor_images}
                         WHERE imagetype = 'snapshot' AND attemptid $insql
                      GROUP BY attemptid) latest ON wi.id = latest.maxid";
        foreach ($DB->get_records_sql($sql, $inparams) as $img) {
            $latest[$img->attemptid] = $img;
        }
    }
    $reviewers = [];
    if ($reviewerids) {
        $reviewers = $DB->get_records_list(
            'user',
            'id',
            array_unique($reviewerids),
            '',
            'id, ' . \core_user\fields::for_name()->get_sql('', false, '', '', false)->selects
        );
    }
    $verifyid = $showverifyid ? quizaccess_webcamproctor_verifyid_for_users(array_column($attempts, 'userid')) : [];

    $table = new html_table();
    $table->head = [get_string('student', $component), get_string('col_status', $component),
        get_string('col_snapshots', $component), get_string('col_confidence', $component)];
    if ($showverifyid) {
        $table->head[] = get_string('col_verifyid', $component);
    }
    array_push(
        $table->head,
        get_string('baseline_photo', $component),
        get_string('col_latest', $component),
        get_string('actions')
    );
    $table->attributes['class'] = 'generaltable webcamproctor-report-table';

    $badges = ['clean' => 'success', 'flagged' => 'warning', 'blocked' => 'danger'];
    foreach ($attempts as $attempt) {
        $badge = $badges[$attempt->status] ?? 'secondary';
        $status = html_writer::span(
            quizaccess_webcamproctor_status_label($attempt->status),
            "badge bg-{$badge} badge-{$badge}"
        );
        if ($attempt->submissionblocked) {
            $status .= ' ' . html_writer::span(get_string('attempt_held', $component), 'badge bg-danger badge-danger');
        }
        if ($attempt->flaggedreason) {
            $status .= html_writer::div(s($attempt->flaggedreason), 'small text-muted');
        }

        $baseline = '-';
        if ($attempt->baselineimageid && isset($images[$attempt->baselineimageid])) {
            $baseline = quizaccess_webcamproctor_thumbnail(
                $images[$attempt->baselineimageid]->imagedata,
                get_string('baseline_photo', $component)
            );
        }
        $snapshot = $attempt->flaggedsnapshotid ? ($images[$attempt->flaggedsnapshotid] ?? null) : ($latest[$attempt->id] ?? null);
        $snapshothtml = $snapshot ? quizaccess_webcamproctor_thumbnail(
            $snapshot->imagedata,
            get_string('snapshot_image', $component)
        ) : '-';

        $actions = [html_writer::link(
            new moodle_url($baseurl, ['attemptid' => $attempt->attemptid]),
            get_string('view_all', $component),
            ['class' => 'btn btn-sm btn-secondary']
        )];
        if ($canmanage) {
            $actionurl = fn($name) => new moodle_url($baseurl, ['proctorattemptid' => $attempt->id,
                'action' => $name, 'filter' => $filter, 'sesskey' => sesskey()]);
            if ($attempt->status !== 'clean') {
                $actions[] = html_writer::link(
                    $actionurl('approve'),
                    get_string('mark_clean', $component),
                    ['class' => 'btn btn-sm btn-success']
                );
            }
            if ($attempt->status !== 'flagged') {
                $actions[] = html_writer::link(
                    $actionurl('flag'),
                    get_string('mark_flagged', $component),
                    ['class' => 'btn btn-sm btn-warning']
                );
            }
            if ($attempt->submissionblocked) {
                $actions[] = html_writer::link(
                    $actionurl('unblock'),
                    get_string('unblock_submission', $component),
                    ['class' => 'btn btn-sm btn-danger']
                );
            }
        }
        if ($attempt->reviewedby && isset($reviewers[$attempt->reviewedby])) {
            $actions[] = html_writer::div(get_string('reviewed_by', $component, (object) [
                'user' => fullname($reviewers[$attempt->reviewedby]),
                'time' => userdate($attempt->reviewedtime),
            ]), 'small text-muted');
        }

        $row = [fullname($attempt), $status, get_string('snapshot_count', $component, $attempt->snapshotcount),
            $attempt->lowestconfidence !== null ? get_string('confidence_score', $component, $attempt->lowestconfidence) : '-'];
        if ($showverifyid) {
            $vi = $verifyid[$attempt->userid] ?? null;
            $row[] = $vi ? s($vi->status) . ($vi->similarity !== null ? ' (' . (int) $vi->similarity . '%)' : '') :
                get_string('verifyid_not_completed', $component);
        }
        array_push($row, $baseline, $snapshothtml, implode(' ', $actions));
        $table->data[] = $row;
    }
    echo html_writer::table($table);
    echo $OUTPUT->paging_bar($total, $page, $perpage, new moodle_url($baseurl, ['filter' => $filter]));
}

// Detail view for one attempt.
if ($attemptid) {
    $proctorattempt = $DB->get_record('quizaccess_webcamproctor_attempts', ['attemptid' => $attemptid, 'quizid' => $quiz->id]);
    if ($proctorattempt) {
        echo $OUTPUT->heading(get_string('attempt_details', $component), 3);
        $images = $DB->get_records('quizaccess_webcamproctor_images', ['attemptid' => $proctorattempt->id], 'timecreated ASC');
        $cards = '';
        foreach ($images as $image) {
            $state = 'ok';
            if ($image->imagetype === 'baseline') {
                $label = get_string('baseline_photo', $component);
                $state = 'baseline';
            } else {
                $label = get_string('snapshot_image', $component);
                if ($image->processstatus === 'unverified') {
                    $label .= ' (' . get_string('image_unverified', $component) . ')';
                    $state = 'muted';
                } else if ($image->processstatus === 'error') {
                    $label .= ' (' . get_string('image_error', $component) . ')';
                    $state = 'muted';
                } else if ((int) $image->facedetected === 0) {
                    $label .= ' (' . get_string('reason_no_face', $component) . ')';
                    $state = 'error';
                } else if ($image->confidence !== null) {
                    $label .= ' (' . (int) $image->confidence . '%)';
                    $threshold = $settings ? (int) $settings->sensitivitythreshold : 70;
                    $state = ((int) $image->confidence < $threshold) ? 'warning' : 'ok';
                }
            }
            $cards .= html_writer::div(
                quizaccess_webcamproctor_thumbnail($image->imagedata, $label, 'webcamproctor-thumbnail-large') .
                html_writer::div(s($label), 'webcamproctor-image-label') .
                html_writer::div(userdate($image->timecreated, get_string('strftimetime', 'langconfig')), 'small text-muted'),
                'webcamproctor-image-card webcamproctor-image-' . $state
            );
        }
        echo html_writer::div($cards, 'webcamproctor-image-gallery');

        if ($showverifyid) {
            echo $OUTPUT->heading(get_string('verifyid_section', $component), 4);
            $vi = quizaccess_webcamproctor_verifyid_for_users([$proctorattempt->userid])[$proctorattempt->userid] ?? null;
            if ($vi) {
                $facts = [];
                foreach (
                    ['similarity' => 'verifyid_overall', 'facesimilarity' => 'verifyid_face',
                        'namesimilarity' => 'verifyid_name'] as $field => $key
                ) {
                    if (isset($vi->$field) && $vi->$field !== null) {
                        $facts[] = get_string($key, $component, (int) $vi->$field);
                    }
                }
                echo html_writer::tag('p', s($vi->status) . ($facts ? ' · ' . implode(' · ', $facts) : ''));
                $photos = '';
                if (!empty($vi->selfie)) {
                    $photos .= quizaccess_webcamproctor_thumbnail(
                        $vi->selfie,
                        get_string('verifyid_photo', $component),
                        'webcamproctor-thumbnail-large'
                    );
                }
                if (!empty($vi->idimage)) {
                    $photos .= quizaccess_webcamproctor_thumbnail(
                        $vi->idimage,
                        get_string('verifyid_document', $component),
                        'webcamproctor-thumbnail-large'
                    );
                }
                echo html_writer::div($photos, 'webcamproctor-image-gallery');
            } else {
                echo $OUTPUT->notification(get_string('verifyid_not_completed', $component), 'info');
            }
        }
        echo html_writer::link($baseurl, get_string('back_to_attempts', $component), ['class' => 'btn btn-secondary mt-3']);
    }
}

// Enlarged image dialog.
$closebutton = ($bs === 'data-bs-')
    ? html_writer::tag('button', '', ['type' => 'button', 'class' => 'btn-close', 'data-bs-dismiss' => 'modal',
        'aria-label' => get_string('closebuttontitle')])
    : html_writer::tag(
        'button',
        html_writer::span('&times;', '', ['aria-hidden' => 'true']),
        ['type' => 'button', 'class' => 'close', 'data-dismiss' => 'modal', 'aria-label' => get_string('closebuttontitle')]
    );
echo html_writer::div(
    html_writer::div(
        html_writer::div(
            html_writer::div(html_writer::tag('h5', '', ['class' => 'modal-title', 'id' => 'webcamproctor-image-title']) .
                $closebutton, 'modal-header') .
            html_writer::div(
                html_writer::img('', '', ['id' => 'webcamproctor-image-large', 'class' => 'img-fluid']),
                'modal-body text-center'
            ),
            'modal-content'
        ),
        'modal-dialog modal-lg'
    ),
    'modal fade',
    ['id' => 'webcamproctor-image-modal', 'tabindex' => '-1', 'aria-labelledby' => 'webcamproctor-image-title',
    'aria-hidden' => 'true']
);

echo $OUTPUT->footer();
