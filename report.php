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
 * Proctoring report for viewing webcam capture results.
 *
 * @package   quizaccess_webcamproctor
 * @copyright 2025 Essay Grader AI
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

$id = required_param('id', PARAM_INT); // Course module ID.
$attemptid = optional_param('attemptid', 0, PARAM_INT); // Specific attempt to view.
$filter = optional_param('filter', 'all', PARAM_ALPHA); // Filter type.
$action = optional_param('action', '', PARAM_ALPHA); // Action to perform.
$proctorattemptid = optional_param('proctorattemptid', 0, PARAM_INT); // Proctoring attempt ID for actions.
$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 20, PARAM_INT);
$perpage = min($perpage, 100);
$download = optional_param('download', '', PARAM_ALPHA); // CSV download.

$cm = get_coursemodule_from_id('quiz', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('quizaccess/webcamproctor:viewreport', $context);

$PAGE->set_url('/mod/quiz/accessrule/webcamproctor/report.php', ['id' => $id]);
$PAGE->set_title(get_string('report_title', 'quizaccess_webcamproctor'));
$PAGE->set_heading($course->fullname);
$PAGE->navbar->add(get_string('report_title', 'quizaccess_webcamproctor'));

// Handle actions.
if ($action && $proctorattemptid && has_capability('quizaccess/webcamproctor:manageattempts', $context)) {
    require_sesskey();
    
    $proctorattempt = $DB->get_record('quizaccess_webcamproctor_attempts', ['id' => $proctorattemptid]);
    
    if ($proctorattempt && $proctorattempt->quizid == $quiz->id) {
        switch ($action) {
            case 'approve':
                $DB->update_record('quizaccess_webcamproctor_attempts', (object)[
                    'id' => $proctorattemptid,
                    'status' => 'clean',
                    'reviewedby' => $USER->id,
                    'reviewedtime' => time(),
                    'timemodified' => time(),
                ]);
                redirect($PAGE->url, get_string('attempt_clean', 'quizaccess_webcamproctor'), null,
                    \core\output\notification::NOTIFY_SUCCESS);
                break;
                
            case 'flag':
                $DB->update_record('quizaccess_webcamproctor_attempts', (object)[
                    'id' => $proctorattemptid,
                    'status' => 'flagged',
                    'reviewedby' => $USER->id,
                    'reviewedtime' => time(),
                    'timemodified' => time(),
                ]);
                redirect($PAGE->url, get_string('attempt_flagged', 'quizaccess_webcamproctor'), null,
                    \core\output\notification::NOTIFY_WARNING);
                break;
                
            case 'unblock':
                $DB->update_record('quizaccess_webcamproctor_attempts', (object)[
                    'id' => $proctorattemptid,
                    'submissionblocked' => 0,
                    'status' => 'clean',
                    'reviewedby' => $USER->id,
                    'reviewedtime' => time(),
                    'timemodified' => time(),
                ]);
                redirect($PAGE->url, get_string('submission_unblocked', 'quizaccess_webcamproctor'), null,
                    \core\output\notification::NOTIFY_SUCCESS);
                break;
        }
    }
}

// Handle CSV export.
if ($download === 'csv') {
    $sql = "SELECT pa.id, u.id as userid, u.firstname, u.lastname, u.email, pa.status, pa.snapshotcount,
                   pa.lowestconfidence, pa.flaggedreason, pa.timecreated, qa.timefinish
            FROM {quizaccess_webcamproctor_attempts} pa
            JOIN {user} u ON u.id = pa.userid
            LEFT JOIN {quiz_attempts} qa ON qa.id = pa.attemptid
            WHERE pa.quizid = :quizid
            ORDER BY pa.timecreated DESC";
    
    $alldata = $DB->get_records_sql($sql, ['quizid' => $quiz->id]);

    // Pre-fetch Verify ID data for CSV.
    $csvVerifyiddata = [];
    if (!empty($alldata) && $DB->get_manager()->table_exists('verifyid_attempts')) {
        $csvUserids = array_unique(array_map(fn($r) => $r->userid, $alldata));
        list($viInsqlCsv, $viInparamsCsv) = $DB->get_in_or_equal($csvUserids, SQL_PARAMS_NAMED, 'csvviuid');
        $viRecsCsv = $DB->get_records_select('verifyid_attempts',
            "userid $viInsqlCsv", $viInparamsCsv, 'userid ASC, id DESC');
        foreach ($viRecsCsv as $vi) {
            if (!isset($csvVerifyiddata[$vi->userid])) {
                $csvVerifyiddata[$vi->userid] = $vi;
            }
        }
    }
    
    // Set headers for CSV download.
    $filename = clean_filename($quiz->name . '_proctoring_report_' . date('Y-m-d') . '.csv');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    
    $output = fopen('php://output', 'w');
    
    // CSV header row.
    fputcsv($output, [
        'Student Name',
        'Email',
        'Proctoring Status',
        'Snapshots',
        'Lowest Confidence',
        'Flagged Reason',
        'Attempt Started',
        'Attempt Finished',
        'Verify ID Status',
        'Verify ID Score',
        'Verify ID Face Match',
        'Verify ID Name Match',
        'Extracted Name from ID',
        'Document Type',
    ]);
    
    // Data rows.
    foreach ($alldata as $row) {
        $vi = $csvVerifyiddata[$row->userid] ?? null;
        fputcsv($output, [
            $row->firstname . ' ' . $row->lastname,
            $row->email,
            $row->status,
            $row->snapshotcount,
            $row->lowestconfidence !== null ? $row->lowestconfidence . '%' : '',
            $row->flaggedreason ?? '',
            $row->timecreated ? userdate($row->timecreated, '%Y-%m-%d %H:%M:%S') : '',
            $row->timefinish ? userdate($row->timefinish, '%Y-%m-%d %H:%M:%S') : '',
            $vi ? $vi->status : 'Not verified',
            $vi && $vi->similarity !== null ? $vi->similarity . '%' : '',
            $vi && $vi->facesimilarity !== null ? $vi->facesimilarity . '%' : '',
            $vi && $vi->namesimilarity !== null ? $vi->namesimilarity . '%' : '',
            $vi ? ($vi->extractedname ?? '') : '',
            $vi ? ($vi->documenttype ?? '') : '',
        ]);
    }
    
    fclose($output);
    exit;
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('report_heading', 'quizaccess_webcamproctor'));
echo html_writer::tag('p', get_string('report_description', 'quizaccess_webcamproctor'));

// Export button.
$exporturl = new moodle_url($PAGE->url, ['download' => 'csv']);
echo '<div style="float: right; margin-bottom: 10px;">';
echo html_writer::link($exporturl, get_string('exportcsv', 'quizaccess_webcamproctor'), [
    'class' => 'btn btn-secondary',
    'data-testid' => 'button-export-csv'
]);
echo '</div>';
echo '<div style="clear: both;"></div>';

// Filter tabs — create a fresh moodle_url per tab to avoid reference mutation.
$tabs = [];
$filtertypes = ['all', 'flagged', 'pending', 'clean'];
foreach ($filtertypes as $f) {
    $taburl = new moodle_url($PAGE->url, ['filter' => $f]);
    $tabs[] = new tabobject($f, $taburl, get_string('filter_' . $f, 'quizaccess_webcamproctor'));
}
echo $OUTPUT->tabtree($tabs, $filter);

// Build query for attempts.
$where = 'quizid = :quizid';
$params = ['quizid' => $quiz->id];

switch ($filter) {
    case 'flagged':
        $where .= ' AND status = :status';
        $params['status'] = 'flagged';
        break;
    case 'pending':
        $where .= ' AND status IN (:s1, :s2)';
        $params['s1'] = 'pending';
        $params['s2'] = 'processing';
        break;
    case 'clean':
        $where .= ' AND status = :status';
        $params['status'] = 'clean';
        break;
}

$total = $DB->count_records_select('quizaccess_webcamproctor_attempts', $where, $params);

$sql = "SELECT pa.*, u.firstname, u.lastname, u.email, qa.timefinish
        FROM {quizaccess_webcamproctor_attempts} pa
        JOIN {user} u ON u.id = pa.userid
        LEFT JOIN {quiz_attempts} qa ON qa.id = pa.attemptid
        WHERE pa.quizid = :quizid";

// Re-apply filter conditions with table alias for the JOIN query.
$sqlparams = ['quizid' => $quiz->id];
switch ($filter) {
    case 'flagged':
        $sql .= ' AND pa.status = :status';
        $sqlparams['status'] = 'flagged';
        break;
    case 'pending':
        $sql .= ' AND pa.status IN (:s1, :s2)';
        $sqlparams['s1'] = 'pending';
        $sqlparams['s2'] = 'processing';
        break;
    case 'clean':
        $sql .= ' AND pa.status = :status';
        $sqlparams['status'] = 'clean';
        break;
}
$sql .= ' ORDER BY pa.timecreated DESC';

$attempts = $DB->get_records_sql($sql, $sqlparams, $page * $perpage, $perpage);

if (empty($attempts)) {
    echo $OUTPUT->notification(get_string('no_attempts', 'quizaccess_webcamproctor'), 'info');
} else {
    // Build table.
    $table = new html_table();
    $table->head = [
        'Student',
        'Proctoring Status',
        'Snapshots',
        'Confidence',
        'Verify ID',
        'Baseline',
        'Latest Snapshot',
        'Actions',
    ];
    $table->attributes['class'] = 'generaltable webcamproctor-report-table';

    // Prefetch images and reviewers to avoid N+1 queries.
    $baselineids = [];
    $flaggedids = [];
    $needlatestids = [];
    $reviewerids = [];
    foreach ($attempts as $attempt) {
        if (!empty($attempt->baselineimageid)) {
            $baselineids[] = $attempt->baselineimageid;
        }
        if (!empty($attempt->flaggedsnapshotid)) {
            $flaggedids[] = $attempt->flaggedsnapshotid;
        } else {
            $needlatestids[] = $attempt->id;
        }
        if (!empty($attempt->reviewedby)) {
            $reviewerids[] = $attempt->reviewedby;
        }
    }

    $prefetchedimages = [];
    $allimageids = array_unique(array_merge($baselineids, $flaggedids));
    if (!empty($allimageids)) {
        $prefetchedimages = $DB->get_records_list('quizaccess_webcamproctor_images', 'id', $allimageids);
    }

    $latestsnapshots = [];
    if (!empty($needlatestids)) {
        list($insql, $inparams) = $DB->get_in_or_equal($needlatestids, SQL_PARAMS_NAMED);
        $latestsql = "SELECT wi.*
                        FROM {quizaccess_webcamproctor_images} wi
                  INNER JOIN (
                              SELECT attemptid, MAX(id) AS maxid
                                FROM {quizaccess_webcamproctor_images}
                               WHERE imagetype = 'snapshot' AND attemptid $insql
                            GROUP BY attemptid
                             ) latest ON wi.id = latest.maxid";
        $latestresults = $DB->get_records_sql($latestsql, $inparams);
        foreach ($latestresults as $img) {
            $latestsnapshots[$img->attemptid] = $img;
            $prefetchedimages[$img->id] = $img;
        }
    }

    $prefetchedreviewers = [];
    if (!empty($reviewerids)) {
        $prefetchedreviewers = $DB->get_records_list('user', 'id', array_unique($reviewerids));
    }

    // Pre-fetch latest Verify ID attempt per user (one query, not N+1).
    $verifyidbyuser = [];
    if ($DB->get_manager()->table_exists('verifyid_attempts')) {
        $proctorUserIds = array_unique(array_map(fn($a) => $a->userid, (array)$attempts));
        if (!empty($proctorUserIds)) {
            list($viInsql, $viInparams) = $DB->get_in_or_equal($proctorUserIds, SQL_PARAMS_NAMED, 'viuid');
            $viRecs = $DB->get_records_select('verifyid_attempts',
                "userid $viInsql", $viInparams, 'userid ASC, id DESC');
            foreach ($viRecs as $vi) {
                if (!isset($verifyidbyuser[$vi->userid])) {
                    $verifyidbyuser[$vi->userid] = $vi;
                }
            }
        }
    }

    foreach ($attempts as $attempt) {
        $user = new stdClass();
        $user->firstname = $attempt->firstname;
        $user->lastname = $attempt->lastname;
        
        // Status badge.
        $statusclass = 'badge ';
        switch ($attempt->status) {
            case 'clean':
                $statusclass .= 'badge-success';
                $statustext = get_string('attempt_clean', 'quizaccess_webcamproctor');
                break;
            case 'flagged':
                $statusclass .= 'badge-warning';
                $statustext = get_string('attempt_flagged', 'quizaccess_webcamproctor');
                break;
            case 'blocked':
                $statusclass .= 'badge-danger';
                $statustext = get_string('attempt_blocked', 'quizaccess_webcamproctor');
                break;
            default:
                $statusclass .= 'badge-secondary';
                $statustext = get_string('attempt_pending', 'quizaccess_webcamproctor');
        }
        $status = html_writer::tag('span', $statustext, ['class' => $statusclass]);
        
        if ($attempt->flaggedreason) {
            $status .= html_writer::tag('small', '<br>' . s($attempt->flaggedreason), ['class' => 'text-muted']);
        }
        
        // Snapshots count.
        $snapshotcount = get_string('snapshot_count', 'quizaccess_webcamproctor', $attempt->snapshotcount);
        
        // Confidence score.
        $confidence = $attempt->lowestconfidence !== null 
            ? get_string('confidence_score', 'quizaccess_webcamproctor', $attempt->lowestconfidence)
            : '-';
        
        // Baseline image thumbnail (prefetched).
        $baselineimg = '-';
        if ($attempt->baselineimageid && isset($prefetchedimages[$attempt->baselineimageid])) {
            $baseline = $prefetchedimages[$attempt->baselineimageid];
            $baselinelabel = get_string('baseline_photo', 'quizaccess_webcamproctor');
            $baselineimg = html_writer::img($baseline->imagedata, $baselinelabel, [
                'class' => 'webcamproctor-thumbnail',
                'style' => 'max-width: 80px; max-height: 60px; cursor: pointer;',
                'data-toggle' => 'modal',
                'data-target' => '#imageModal',
                'data-image' => $baseline->imagedata,
                'data-title' => $baselinelabel,
            ]);
        }
        
        // Latest/flagged snapshot thumbnail (prefetched).
        $snapshotimg = '-';
        $snapshot = null;
        if (!empty($attempt->flaggedsnapshotid) && isset($prefetchedimages[$attempt->flaggedsnapshotid])) {
            $snapshot = $prefetchedimages[$attempt->flaggedsnapshotid];
        } else if (isset($latestsnapshots[$attempt->id])) {
            $snapshot = $latestsnapshots[$attempt->id];
        }
        if ($snapshot) {
            $snapshotlabel = get_string('snapshot_image', 'quizaccess_webcamproctor');
            $snapshotimg = html_writer::img($snapshot->imagedata, $snapshotlabel, [
                'class' => 'webcamproctor-thumbnail',
                'style' => 'max-width: 80px; max-height: 60px; cursor: pointer;',
                'data-toggle' => 'modal',
                'data-target' => '#imageModal',
                'data-image' => $snapshot->imagedata,
                'data-title' => $snapshotlabel,
            ]);
        }
        
        // Actions.
        $actions = [];
        
        // View all images button.
        $viewurl = new moodle_url($PAGE->url, ['attemptid' => $attempt->attemptid]);
        $actions[] = html_writer::link($viewurl, get_string('view_all', 'quizaccess_webcamproctor'),
            ['class' => 'btn btn-sm btn-secondary']);
        
        if (has_capability('quizaccess/webcamproctor:manageattempts', $context)) {
            $baseactionparams = [
                'proctorattemptid' => $attempt->id,
                'sesskey' => sesskey(),
            ];
            
            if ($attempt->status !== 'clean') {
                $approveurl = new moodle_url($PAGE->url, array_merge($baseactionparams, ['action' => 'approve']));
                $actions[] = html_writer::link($approveurl, get_string('mark_clean', 'quizaccess_webcamproctor'),
                    ['class' => 'btn btn-sm btn-success']);
            }
            
            if ($attempt->status !== 'flagged') {
                $flagurl = new moodle_url($PAGE->url, array_merge($baseactionparams, ['action' => 'flag']));
                $actions[] = html_writer::link($flagurl, get_string('mark_flagged', 'quizaccess_webcamproctor'),
                    ['class' => 'btn btn-sm btn-warning']);
            }
            
            if ($attempt->submissionblocked) {
                $unblockurl = new moodle_url($PAGE->url, array_merge($baseactionparams, ['action' => 'unblock']));
                $actions[] = html_writer::link($unblockurl, get_string('unblock_submission', 'quizaccess_webcamproctor'),
                    ['class' => 'btn btn-sm btn-danger']);
            }
        }
        
        // Reviewed info (prefetched).
        if ($attempt->reviewedby && isset($prefetchedreviewers[$attempt->reviewedby])) {
            $reviewer = $prefetchedreviewers[$attempt->reviewedby];
            $a = new stdClass();
            $a->user = fullname($reviewer);
            $a->time = userdate($attempt->reviewedtime);
            $actions[] = html_writer::tag('small', 
                get_string('reviewed_by', 'quizaccess_webcamproctor', $a),
                ['class' => 'text-muted d-block mt-1']
            );
        }
        
        // Verify ID cell.
        $viRecord = $verifyidbyuser[$attempt->userid] ?? null;
        if ($viRecord) {
            $viStatusClass = 'badge ';
            switch ($viRecord->status) {
                case 'verified':
                    $viStatusClass .= 'badge-success';
                    $viStatusText = 'Verified';
                    break;
                case 'rejected':
                    $viStatusClass .= 'badge-danger';
                    $viStatusText = 'Rejected';
                    break;
                default:
                    $viStatusClass .= 'badge-secondary';
                    $viStatusText = 'Pending';
            }
            $viCell = html_writer::tag('span', $viStatusText, ['class' => $viStatusClass]);
            if ($viRecord->similarity !== null) {
                $viCell .= html_writer::tag('small', '<br>' . (int)$viRecord->similarity . '% match', ['class' => 'text-muted']);
            }
        } else {
            $viCell = html_writer::tag('span', 'Not verified', ['class' => 'badge badge-light text-muted']);
        }

        $table->data[] = [
            fullname($user),
            $status,
            $snapshotcount,
            $confidence,
            $viCell,
            $baselineimg,
            $snapshotimg,
            implode(' ', $actions),
        ];
    }
    
    echo html_writer::table($table);
    
    // Pagination.
    echo $OUTPUT->paging_bar($total, $page, $perpage, $PAGE->url);
}

// Image modal for enlarged view.
echo '
<div class="modal fade" id="imageModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="imageModalTitle">Image</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body text-center">
        <img id="modalImage" src="" alt="" style="max-width: 100%; max-height: 70vh;">
      </div>
    </div>
  </div>
</div>
';

// Detail view for specific attempt.
if ($attemptid) {
    $proctorattempt = $DB->get_record('quizaccess_webcamproctor_attempts', [
        'attemptid' => $attemptid,
        'quizid' => $quiz->id,
    ]);
    
    if ($proctorattempt) {
        echo $OUTPUT->heading(get_string('attempt_details', 'quizaccess_webcamproctor'), 3);
        
        // Get all images for this attempt.
        $images = $DB->get_records('quizaccess_webcamproctor_images', 
            ['attemptid' => $proctorattempt->id],
            'timecreated ASC'
        );
        
        if ($images) {
            echo '<div class="webcamproctor-image-gallery" style="display: flex; flex-wrap: wrap; gap: 10px; margin: 20px 0;">';
            
            foreach ($images as $image) {
                $bordercolor = 'var(--wp-border)';
                if ($image->imagetype === 'baseline') {
                    $bordercolor = 'var(--wp-success)';
                    $label = get_string('baseline_photo', 'quizaccess_webcamproctor');
                } else {
                    $label = get_string('snapshot_image', 'quizaccess_webcamproctor');
                    if ($image->facedetected == 0) {
                        $bordercolor = 'var(--wp-error)';
                        $label .= ' (' . get_string('reason_no_face', 'quizaccess_webcamproctor') . ')';
                    } elseif ($image->confidence !== null && $image->confidence < 70) {
                        $bordercolor = 'var(--wp-warning)';
                        $label .= ' (' . $image->confidence . '%)';
                    }
                }
                
                echo '<div class="webcamproctor-image-card" style="text-align: center; padding: 10px; border: 2px solid ' . $bordercolor . '; border-radius: 8px;">';
                echo html_writer::img($image->imagedata, s($label), [
                    'style' => 'max-width: 160px; max-height: 120px; cursor: pointer;',
                    'data-toggle' => 'modal',
                    'data-target' => '#imageModal',
                    'data-image' => $image->imagedata,
                    'data-title' => s($label),
                ]);
                echo '<div style="font-size: 12px; margin-top: 5px;">' . s($label) . '</div>';
                echo '<div style="font-size: 11px; color: var(--wp-text-muted);">' . userdate($image->timecreated, '%H:%M:%S') . '</div>';
                echo '</div>';
            }
            
            echo '</div>';
        }
        
        // Verify ID comparison section — always shown if mod_verifyid is installed,
        // regardless of whether verifyidintegration setting is enabled on this quiz.
        // v2.2.0: Fixed table (was local_verifyid_verifications; correct: verifyid_attempts),
        // column names (selfiedata → selfie), and status value (approved → verified).
        // Now shows selfie, government ID photo, scores, extracted name, and document type.
        if ($DB->get_manager()->table_exists('verifyid_attempts')) {
            echo $OUTPUT->heading('AI Verify ID Comparison', 4);

            // Get the most recent verified attempt first; fall back to latest of any status.
            $viDetail = $DB->get_record_select('verifyid_attempts',
                "userid = :uid AND status = 'verified'",
                ['uid' => $proctorattempt->userid],
                '*', IGNORE_MULTIPLE
            );
            if (!$viDetail) {
                $viDetails = $DB->get_records_select('verifyid_attempts',
                    'userid = :uid', ['uid' => $proctorattempt->userid], 'id DESC', '*', 0, 1);
                $viDetail = !empty($viDetails) ? reset($viDetails) : null;
            }

            if ($viDetail) {
                // Status badge.
                $viDetailBadge = '';
                switch ($viDetail->status) {
                    case 'verified':
                        $viDetailBadge = '<span class="badge badge-success" style="font-size:1rem;padding:6px 12px;">Verified</span>';
                        break;
                    case 'rejected':
                        $viDetailBadge = '<span class="badge badge-danger" style="font-size:1rem;padding:6px 12px;">Rejected</span>';
                        break;
                    default:
                        $viDetailBadge = '<span class="badge badge-secondary" style="font-size:1rem;padding:6px 12px;">Pending</span>';
                }

                echo '<div style="background:#f8f9fa;border:1px solid #dee2e6;border-radius:8px;padding:20px;margin:16px 0;">';

                // Score summary row.
                echo '<div style="display:flex;gap:24px;flex-wrap:wrap;align-items:center;margin-bottom:16px;">';
                echo $viDetailBadge;
                if ($viDetail->similarity !== null) {
                    echo '<div style="text-align:center;">';
                    echo '<div style="font-size:1.4rem;font-weight:bold;color:#0d6efd;">' . (int)$viDetail->similarity . '%</div>';
                    echo '<div style="font-size:0.8rem;color:#6c757d;">Overall Match</div>';
                    echo '</div>';
                }
                if ($viDetail->facesimilarity !== null) {
                    echo '<div style="text-align:center;">';
                    echo '<div style="font-size:1.4rem;font-weight:bold;color:#198754;">' . (int)$viDetail->facesimilarity . '%</div>';
                    echo '<div style="font-size:0.8rem;color:#6c757d;">Face Match</div>';
                    echo '</div>';
                }
                if ($viDetail->namesimilarity !== null) {
                    echo '<div style="text-align:center;">';
                    echo '<div style="font-size:1.4rem;font-weight:bold;color:#0dcaf0;">' . (int)$viDetail->namesimilarity . '%</div>';
                    echo '<div style="font-size:0.8rem;color:#6c757d;">Name Match</div>';
                    echo '</div>';
                }
                if (!empty($viDetail->extractedname)) {
                    echo '<div>';
                    echo '<div style="font-size:0.8rem;color:#6c757d;">Name on ID</div>';
                    echo '<div style="font-weight:600;">' . s($viDetail->extractedname) . '</div>';
                    echo '</div>';
                }
                if (!empty($viDetail->documenttype)) {
                    echo '<div>';
                    echo '<div style="font-size:0.8rem;color:#6c757d;">Document Type</div>';
                    echo '<div style="font-weight:600;">' . s($viDetail->documenttype) . '</div>';
                    echo '</div>';
                }
                echo '</div>';

                // Side-by-side photo comparison.
                echo '<div style="display:flex;gap:16px;flex-wrap:wrap;">';

                // Verify ID selfie.
                if (!empty($viDetail->selfie)) {
                    echo '<div style="text-align:center;">';
                    echo '<div style="font-size:0.85rem;font-weight:600;margin-bottom:6px;color:#0d6efd;">Verify ID Selfie</div>';
                    echo html_writer::img($viDetail->selfie, 'Verify ID Selfie', [
                        'style' => 'max-width:160px;max-height:200px;border:3px solid #0d6efd;border-radius:8px;cursor:pointer;',
                        'data-toggle' => 'modal',
                        'data-target' => '#imageModal',
                        'data-image' => $viDetail->selfie,
                        'data-title' => 'Verify ID Selfie',
                    ]);
                    echo '</div>';
                }

                // Government ID document photo.
                if (!empty($viDetail->idimage)) {
                    echo '<div style="text-align:center;">';
                    echo '<div style="font-size:0.85rem;font-weight:600;margin-bottom:6px;color:#6f42c1;">Government ID Document</div>';
                    echo html_writer::img($viDetail->idimage, 'Government ID', [
                        'style' => 'max-width:220px;max-height:200px;border:3px solid #6f42c1;border-radius:8px;cursor:pointer;',
                        'data-toggle' => 'modal',
                        'data-target' => '#imageModal',
                        'data-image' => $viDetail->idimage,
                        'data-title' => 'Government ID Document',
                    ]);
                    echo '</div>';
                }

                // Quiz baseline photo for comparison.
                if ($proctorattempt->baselineimageid) {
                    $viBaseline = $DB->get_record('quizaccess_webcamproctor_images',
                        ['id' => $proctorattempt->baselineimageid]);
                    if ($viBaseline) {
                        echo '<div style="text-align:center;">';
                        echo '<div style="font-size:0.85rem;font-weight:600;margin-bottom:6px;color:#198754;">Quiz Baseline Photo</div>';
                        echo html_writer::img($viBaseline->imagedata, 'Quiz Baseline', [
                            'style' => 'max-width:160px;max-height:200px;border:3px solid #198754;border-radius:8px;cursor:pointer;',
                            'data-toggle' => 'modal',
                            'data-target' => '#imageModal',
                            'data-image' => $viBaseline->imagedata,
                            'data-title' => 'Quiz Baseline Photo',
                        ]);
                        echo '</div>';
                    }
                }

                echo '</div>'; // photo row
                echo '</div>'; // card

                // Reviewer comment if present.
                if (!empty($viDetail->reviewcomment)) {
                    echo '<div class="alert alert-info mt-2"><strong>Verify ID Review Note:</strong> '
                        . s($viDetail->reviewcomment) . '</div>';
                }

            } else {
                echo '<div class="alert alert-warning">This student has not completed AI Verify ID verification.</div>';
            }
        }
        
        // Back button.
        $backurl = new moodle_url($PAGE->url);
        $backurl->remove_params('attemptid');
        echo html_writer::link($backurl, get_string('back_to_attempts', 'quizaccess_webcamproctor'), ['class' => 'btn btn-secondary mt-3']);
    }
}

$PAGE->requires->js_amd_inline("
    require([], function() {
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('[data-toggle=\"modal\"]').forEach(function(img) {
                img.addEventListener('click', function() {
                    document.getElementById('modalImage').src = this.getAttribute('data-image');
                    document.getElementById('imageModalTitle').textContent = this.getAttribute('data-title') || 'Image';
                });
            });
        });
    });
");


echo $OUTPUT->footer();
