<?php

use yii\helpers\Html;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $students array — from view_reports table */

$this->title = 'Career Guidance Report';
$this->params['breadcrumbs'][] = ['label' => 'Students', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

// Register Highcharts
$this->registerJsFile('https://code.highcharts.com/highcharts.js', ['position' => \yii\web\View::POS_HEAD]);

// ── Search / filter parameters (GET) ─────────────────────────────────────────
$filterName      = Yii::$app->request->get('name', '');
$filterFather    = Yii::$app->request->get('father', '');
$filterAdmission = Yii::$app->request->get('admission_no', '');
$filterSchool    = Yii::$app->request->get('school', '');
$filterClass     = Yii::$app->request->get('class', '');
$filterSection   = Yii::$app->request->get('section', '');
$filterYear      = Yii::$app->request->get('year', '');

// ── Filter students based on request parameters ────────────────────────────────
$filtered = [];
foreach ($students as $student) {
    $name      = mb_strtolower($student['name'] ?? '');
    $father    = mb_strtolower($student['father'] ?? '');
    $admNo     = mb_strtolower((string)($student['admission_no'] ?? ''));
    $school    = mb_strtolower($student['school_name'] ?? '');
    $cls       = mb_strtolower($student['class_name'] ?? '');
    $section   = mb_strtolower($student['section_name'] ?? '');

    if ($filterName      && strpos($name,   mb_strtolower($filterName))      === false) continue;
    if ($filterFather    && strpos($father, mb_strtolower($filterFather))    === false) continue;
    if ($filterAdmission && strpos($admNo,  mb_strtolower($filterAdmission)) === false) continue;
    if ($filterSchool    && strpos($school, mb_strtolower($filterSchool))    === false) continue;
    if ($filterClass     && strpos($cls,    mb_strtolower($filterClass))     === false) continue;
    if ($filterSection   && strpos($section,mb_strtolower($filterSection))   === false) continue;

    if ($filterYear && !empty($student['created_at'])) {
        $recordYear = date('Y', strtotime($student['created_at']));
        if ($recordYear != $filterYear) continue;
    }

    $filtered[] = $student;
}

// ── Pagination ────────────────────────────────────────────────────────────────
$pageSize    = 20;
$currentPage = (int) Yii::$app->request->get('page', 1);
if ($currentPage < 1) $currentPage = 1;

$totalCount = count($filtered);
$totalPages = max(1, (int)ceil($totalCount / $pageSize));
if ($currentPage > $totalPages) $currentPage = $totalPages;
$offset     = ($currentPage - 1) * $pageSize;
$pageSlice  = array_slice($filtered, $offset, $pageSize);

$queryParams = array_filter([
    'name'         => $filterName,
    'father'       => $filterFather,
    'admission_no' => $filterAdmission,
    'school'       => $filterSchool,
    'class'        => $filterClass,
    'section'      => $filterSection,
    'year'         => $filterYear,
]);

$pageUrl = function(int $p) use ($queryParams): string {
    return Url::current(array_merge($queryParams, ['page' => $p]));
};

function truncateText($text, $length = 70, $default = 'None identified') {
    if (empty($text) || $text === 'NULL' || $text === null) return $default;
    $cleanText = strip_tags($text);
    if (mb_strlen($cleanText) > $length) return mb_substr($cleanText, 0, $length) . '…';
    return $cleanText ?: $default;
}

function formatCareerOptions($opt1, $opt2) {
    $options = [];
    if (!empty($opt1) && $opt1 != '-') $options[] = $opt1;
    if (!empty($opt2) && $opt2 != '-') $options[] = $opt2;
    return !empty($options) ? implode(' | ', $options) : 'Not specified';
}

// Helper function to check if student has any DBDA data
function hasDbdaData($student) {
    $dbdaFields = ['dbda_ca', 'dbda_cl', 'dbda_ma', 'dbda_na', 'dbda_ra', 'dbda_sa', 'dbda_va'];
    foreach ($dbdaFields as $field) {
        $value = $student[$field] ?? null;
        if ($value !== null && $value !== '' && $value !== '-') {
            if (is_numeric($value) && intval($value) > 0) {
                return true;
            }
        }
    }
    return false;
}
?>

<?php $this->registerCss("
    .career-report-page .container {
        width: 100% !important;
        max-width: 100% !important;
        padding: 0 15px !important;
        margin: 0 !important;
        overflow-x: auto !important;
    }
    .career-report-page {
        width: 100%;
        overflow-x: auto;
    }
") ?>

<style>
/* ── Base layout ─────────────────────────────────────────── */
.career-report {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    padding: 20px;
    min-width: 1400px;
}
.page-header {
    border-bottom: 3px solid #337ab7;
    padding-bottom: 10px;
    margin-top: 0;
    margin-bottom: 20px;
}
.page-header h1 { color: #333; margin-bottom: 5px; font-size: 24px; }

.table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; width: 100%; }

/* ── Main table ──────────────────────────────────────────── */
.table {
    width: 100%;
    min-width: 1300px;
    margin-bottom: 0;
    font-size: 12px;
    border-collapse: collapse;
    table-layout: auto;
}
.table td, .table th { padding: 8px 10px; border: 1px solid #ddd; vertical-align: middle; }

/* Column widths */
.col-sno      { width: 40px;  text-align: center; }
.col-name     { width: 130px; }
.col-father   { width: 130px; }
.col-admission{ width: 90px;  text-align: center; }
.col-school   { width: 120px; }
.col-class    { width: 50px;  text-align: center; }
.col-section  { width: 60px;  text-align: center; }
.col-dbda-score { width: 45px; text-align: center; font-weight: bold; }
.col-total    { width: 70px;  text-align: center; font-weight: bold; }
.col-text     { width: 160px; }
.col-mbti     { width: 150px; }
.col-cis      { width: 150px; }
.col-career   { width: 160px; }
.col-remarks  { width: 160px; }
.col-action   { width: 85px;  text-align: center; }

.table thead tr.header-row th {
    background-color: #337ab7 !important;
    color: #fff !important;
    font-weight: 600;
    text-align: center;
    border-color: #2a6496;
    white-space: nowrap;
    line-height: 1.3;
    padding: 10px 8px;
    font-size: 12px;
}
.table thead tr.search-row th { background-color: #f9f9f9; padding: 5px 8px; }
.table thead tr.search-row input {
    width: 100%;
    box-sizing: border-box;
    padding: 4px 6px;
    font-size: 11px;
    border: 1px solid #ccc;
    border-radius: 3px;
}
.table thead tr.search-row input:focus {
    border-color: #337ab7;
    box-shadow: 0 0 3px rgba(51,122,183,.4);
    outline: none;
}
.table tbody tr:nth-child(even) { background-color: #f9f9f9; }
.table tbody tr:hover { background-color: #eaf3fb; }
.table td { font-size: 12px; }

/* ── Hover cell ──────────────────────────────────────────── */
.hover-cell {
    cursor: pointer;
    white-space: normal;
    word-wrap: break-word;
    line-height: 1.4;
    max-width: 200px;
    transition: color .15s;
}
.hover-cell:hover { color: #337ab7; text-decoration: underline; }

/* ── Hover dialog (tooltip) ─────────────────────────────── */
.hover-dialog {
    position: fixed;
    z-index: 10000;
    background: #2c3e50;
    color: #fff;
    padding: 12px 16px;
    border-radius: 6px;
    font-size: 12px;
    line-height: 1.6;
    max-width: 450px;
    min-width: 250px;
    box-shadow: 0 4px 18px rgba(0,0,0,.3);
    word-wrap: break-word;
    white-space: normal;
    pointer-events: none;
    animation: fdIn .12s ease;
}
.hover-dialog::before {
    content: '';
    position: absolute;
    top: -7px;
    left: 18px;
    border: 7px solid transparent;
    border-top: 0;
    border-bottom-color: #2c3e50;
}
@keyframes fdIn {
    from { opacity: 0; transform: translateY(-4px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* ── Pagination ──────────────────────────────────────────── */
.pagination-container { margin-top: 20px; text-align: center; }
.pagination { display: inline-flex; padding: 0; margin: 0; list-style: none; border-radius: 4px; }
.pagination li a,
.pagination li span {
    display: block;
    padding: 6px 12px;
    margin-left: -1px;
    color: #337ab7;
    background: #fff;
    border: 1px solid #ddd;
    text-decoration: none;
    font-size: 12px;
}
.pagination li.active a,
.pagination li.active span { background: #337ab7; border-color: #337ab7; color: #fff; }
.pagination li a:hover { background: #eaf3fb; }

/* ── Misc ────────────────────────────────────────────────── */
.record-count  { font-size: 13px; color: #555; margin-top: 6px; margin-bottom: 10px; }
.filter-inline { display: flex; align-items: center; gap: 15px; margin-bottom: 15px; flex-wrap: wrap; }
.filter-inline label { font-weight: 600; font-size: 13px; margin: 0; }
.year-select   { padding: 5px 10px; font-size: 13px; border: 1px solid #ccc; border-radius: 3px; min-width: 130px; }
.custom-back-btn { background-color: #5cb85c !important; border-color: #4cae4c !important; color: white !important; }
.custom-back-btn:hover { background-color: #4cae4c !important; }
.dbda-score    { font-weight: bold; text-align: center; color: #333; font-size: 13px; }
.btn-sm        { padding: 3px 8px; font-size: 11px; }

/* Alert for school filter */
.school-filter-alert {
    margin-bottom: 15px;
    padding: 10px 15px;
    background-color: #d9edf7;
    border: 1px solid #bce8f1;
    border-radius: 4px;
    color: #31708f;
}
.school-filter-alert .clear-filter {
    float: right;
    margin-top: -2px;
}

/* ── Full-screen Modal ───────────────────────────────────── */
.detail-modal {
    display: none;
    position: fixed;
    z-index: 20000;
    left: 0; top: 0;
    width: 100%; height: 100%;
    background-color: rgba(0,0,0,0.8);
    overflow: hidden;
}
.detail-modal-content {
    background-color: #fff;
    margin: 0 auto;
    width: 100%; max-width: 100%;
    height: 100vh;
    position: relative;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
.modal-header {
    background: #337ab7;
    color: #fff;
    padding: 12px 20px;
    flex-shrink: 0;
    z-index: 10;
    position: relative;
}
.modal-header h2 { margin: 0; font-size: 20px; }
.close-modal {
    position: absolute;
    right: 20px; top: 10px;
    font-size: 28px; font-weight: bold;
    color: #fff; cursor: pointer;
}
.close-modal:hover { color: #f8c301; }
.modal-body {
    padding: 20px;
    flex: 1 1 auto;
    overflow-y: auto;
    overflow-x: hidden;
}
.modal-footer {
    padding: 12px 20px;
    background: #f5f5f5;
    border-top: 1px solid #ddd;
    text-align: right;
    flex-shrink: 0;
    z-index: 10;
}
.print-btn  { background:#f8c301; color:#000; border:none; padding:8px 16px; border-radius:4px; cursor:pointer; margin-right:10px; }
.close-btn  { background:#337ab7; color:#fff; border:none; padding:8px 16px; border-radius:4px; cursor:pointer; }

/* ── Modal inner layout (matches original report.php styling) ── */
.modal-body .form_div  { border:#000 solid 2px; display:block; clear:both; overflow:hidden; }
.modal-body .hrader    { border-bottom:#000 solid 2px; }
.modal-body .innerdiv  { padding:0 10px; }
.modal-body .student_info { padding:10px; font-size:14px; }
.modal-body .linetx    { display:block; border-bottom:#000 solid 1px; margin-top:4px; }
.modal-body .schedule h1  { background:#f8c301; text-transform:uppercase; text-align:center; font-size:18px; padding:10px; margin:0 0 10px; }
.modal-body .dbda_bg   { background:#333; padding:7px 0; width:100%; float:left; margin:4px 0; clear:both; }
.modal-body .dbda_bg_proficiency { background:#5cb85c; padding:7px 0; width:100%; float:left; margin:4px 0; clear:both; }

/* New - Areas of Strength - Yellow */
.modal-body .dbda_bg_strength { background:#f0ad4e; padding:7px 0; width:100%; float:left; margin:4px 0; clear:both; }

/* New - Areas of Improvement - Red */
.modal-body .dbda_bg_improvement { background:#d9534f; padding:7px 0; width:100%; float:left; margin:4px 0; clear:both; }
.modal-body .strng     { color:#fff; padding:0; margin:0; text-align:center; }
.modal-body .pagebreak { page-break-after:always; }
.modal-body .table     { min-width:unset; width:100%; font-size:13px; }
.modal-body .table td,
.modal-body .table th  { padding:8px 10px; white-space:normal; word-break:break-word; border:1px solid #ddd; }
.modal-body h4         { font-size:16px; margin:8px 0; }

@media print {
    .navbar, .footer, .btn, .pagination-container, .search-row { display:none !important; }
    .container { width:100% !important; margin:0 !important; padding:0 !important; }
    .hover-dialog { display:none !important; }
    .table { min-width:100% !important; }
}
</style>

<div class="career-report-page">
    <div class="career-report">
        <div class="row">
            <div class="col-md-12">
                <div class="page-header">
                    <h1>Career Guidance Report</h1>
                </div>
            </div>
        </div>

        <!-- School Filter Alert -->
        <?php 
        $schoolParam = Yii::$app->request->get('school', '');
        if (!empty($schoolParam)):
            $schoolName = '';
            if (is_numeric($schoolParam)) {
                $schoolModel = \backend\models\School::findOne($schoolParam);
                $schoolName = $schoolModel ? $schoolModel->NAME : $schoolParam;
            } else {
                $schoolName = $schoolParam;
            }
        ?>
            <div class="school-filter-alert">
                <strong>Filtered by School:</strong> <?= Html::encode($schoolName) ?>
                <?= Html::a('Clear Filter', ['career-report'], ['class' => 'btn btn-default btn-sm clear-filter']) ?>
                <div style="clear:both"></div>
            </div>
        <?php endif; ?>

        <div class="filter-inline">
            <?= Html::a('← Back to Students', ['index'], ['class' => 'btn custom-back-btn']) ?>
            <div style="display:flex; align-items:center; gap:8px;">
                <label for="f-year">Filter by Year:</label>
                <select id="f-year" class="year-select">
                    <option value="">— Select Year —</option>
                    <?php for ($yr = 2024; $yr <= (int)date('Y'); $yr++): ?>
                        <option value="<?= $yr ?>" <?= (string)$filterYear === (string)$yr ? 'selected' : '' ?>><?= $yr ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>

        <div class="record-count">
            Showing <strong><?= $offset + 1 ?>–<?= min($offset + $pageSize, $totalCount) ?></strong>
            of <strong><?= $totalCount ?></strong> students
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-striped table-hover">
                <thead>
                    <tr class="header-row">
                        <th class="col-sno"       rowspan="2">#</th>
                        <th class="col-name"      rowspan="2">Student Name</th>
                        <th class="col-father"    rowspan="2">Father's Name</th>
                        <th class="col-admission" rowspan="2">Adm No.</th>
                        <th class="col-school"    rowspan="2">School</th>
                        <th class="col-class"     rowspan="2">Class</th>
                        <th class="col-section"   rowspan="2">Section</th>
                        <th colspan="7">DBDA Scores</th>
                        <th class="col-total"   rowspan="2">Total Score</th>
                        <th class="col-text"    rowspan="2">Areas of Proficiency</th>
                        <th class="col-text"    rowspan="2">Areas of Strength</th>
                        <th class="col-text"    rowspan="2">Areas of Improvement</th>
                        <th class="col-mbti"    rowspan="2">MBTI</th>
                        <th class="col-career"  rowspan="2">Career Options</th>
                        <th class="col-remarks" rowspan="2">Counsellor Remarks</th>
                        <th class="col-action"  rowspan="2">Action</th>
                    </tr>
                    <tr class="header-row">
                        <th class="col-dbda-score">CA</th>
                        <th class="col-dbda-score">CL</th>
                        <th class="col-dbda-score">MA</th>
                        <th class="col-dbda-score">NA</th>
                        <th class="col-dbda-score">RA</th>
                        <th class="col-dbda-score">SA</th>
                        <th class="col-dbda-score">VA</th>
                    </tr>
                    <tr class="search-row">
                        <th class="col-sno"></th>
                        <th class="col-name">    <input type="text" id="f-name"      placeholder="Search name…"   value="<?= Html::encode($filterName) ?>"></th>
                        <th class="col-father">  <input type="text" id="f-father"    placeholder="Search father…" value="<?= Html::encode($filterFather) ?>"></th>
                        <th class="col-admission"><input type="text" id="f-admission" placeholder="Adm no…"        value="<?= Html::encode($filterAdmission) ?>"></th>
                        <th class="col-school">  <input type="text" id="f-school"    placeholder="School…"        value="<?= Html::encode($filterSchool) ?>"></th>
                        <th class="col-class">   <input type="text" id="f-class"     placeholder="Class…"         value="<?= Html::encode($filterClass) ?>"></th>
                        <th class="col-section"> <input type="text" id="f-section"   placeholder="Section…"       value="<?= Html::encode($filterSection) ?>"></th>
                        <th class="col-dbda-score"></th>
                        <th class="col-dbda-score"></th>
                        <th class="col-dbda-score"></th>
                        <th class="col-dbda-score"></th>
                        <th class="col-dbda-score"></th>
                        <th class="col-dbda-score"></th>
                        <th class="col-dbda-score"></th>
                        <th class="col-total"></th>
                        <th class="col-text"></th>
                        <th class="col-text"></th>
                        <th class="col-text"></th>
                        <th class="col-mbti"></th>
                        <th class="col-career"></th>
                        <th class="col-remarks"></th>
                        <th class="col-action"></th>
                    </tr>
                </thead>
                <tbody>
                <?php $counter = $offset + 1; foreach ($pageSlice as $student): ?>
                    <tr>
                        <td class="col-sno" style="text-align:center;"><?= $counter++ ?></td>
                        <td class="col-name"><?= Html::encode($student['name'] ?? '') ?></td>
                        <td class="col-father"><?= Html::encode($student['father'] ?? '') ?></td>
                        <td class="col-admission" style="text-align:center;"><?= Html::encode($student['admission_no'] ?? '') ?></td>
                        <td class="col-school"><?= Html::encode($student['school_name'] ?? '') ?></td>
                        <td class="col-class"    style="text-align:center;"><?= Html::encode($student['class_name'] ?? '') ?></td>
                        <td class="col-section"  style="text-align:center;"><?= Html::encode($student['section_name'] ?? '') ?></td>

                        <!-- DBDA Scores -->
                        <td class="col-dbda-score"><?= ($student['dbda_ca'] ?? '-') ?></td>
                        <td class="col-dbda-score"><?= ($student['dbda_cl'] ?? '-') ?></td>
                        <td class="col-dbda-score"><?= ($student['dbda_ma'] ?? '-') ?></td>
                        <td class="col-dbda-score"><?= ($student['dbda_na'] ?? '-') ?></td>
                        <td class="col-dbda-score"><?= ($student['dbda_ra'] ?? '-') ?></td>
                        <td class="col-dbda-score"><?= ($student['dbda_sa'] ?? '-') ?></td>
                        <td class="col-dbda-score"><?= ($student['dbda_va'] ?? '-') ?></td>

                        <td class="col-total"><?= $student['dbda_total'] ?? 0 ?></td>

                        <!-- Hoverable text columns -->
                        <td class="col-text hover-cell"
                            data-hover="<?= Html::encode(strip_tags($student['areas_of_proficiency'] ?? '')) ?>">
                            <?= Html::encode(truncateText($student['areas_of_proficiency'] ?? '', 70)) ?>
                        </td>
                        <td class="col-text hover-cell"
                            data-hover="<?= Html::encode(strip_tags($student['areas_of_strength'] ?? '')) ?>">
                            <?= Html::encode(truncateText($student['areas_of_strength'] ?? '', 70)) ?>
                        </td>
                        <td class="col-text hover-cell"
                            data-hover="<?= Html::encode(strip_tags($student['areas_of_improvement'] ?? '')) ?>">
                            <?= Html::encode(truncateText($student['areas_of_improvement'] ?? '', 70)) ?>
                        </td>
                        <td class="col-mbti hover-cell"
                            data-hover="<?= Html::encode(strip_tags($student['mbti_output'] ?? 'Not Available')) ?>">
                            <?= Html::encode(truncateText($student['mbti_output'] ?? 'Not Available', 60)) ?>
                        </td>
                        <td class="col-career hover-cell"
                            data-hover="<?= Html::encode(formatCareerOptions($student['feedback_career_opt1'] ?? '', $student['feedback_career_opt2'] ?? '')) ?>">
                            <?= Html::encode(truncateText(formatCareerOptions($student['feedback_career_opt1'] ?? '', $student['feedback_career_opt2'] ?? ''), 70, 'Not specified')) ?>
                        </td>
                        <td class="col-remarks hover-cell"
                            data-hover="<?= Html::encode($student['feedback_remarks'] ?? 'No remarks yet') ?>">
                            <?= Html::encode(truncateText($student['feedback_remarks'] ?? 'No remarks yet', 70)) ?>
                        </td>

                        <td class="col-action" style="text-align:center;">
                            <?php if (hasDbdaData($student)): ?>
                                <button class="btn btn-primary btn-sm view-detail-btn"
                                    data-student='<?= htmlspecialchars(json_encode($student), ENT_QUOTES, 'UTF-8') ?>'>
                                    View Details
                                </button>
                            <?php else: ?>
                                <span style="color:#ccc; font-size:11px;">No data</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (empty($pageSlice)): ?>
                    <tr>
                        <td colspan="23" style="text-align:center; color:#999; padding:40px;">
                            No students found matching your criteria.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="pagination-container">
            <ul class="pagination">
                <?php if ($currentPage > 1): ?>
                    <li><a href="<?= $pageUrl(1) ?>">« First</a></li>
                    <li><a href="<?= $pageUrl($currentPage - 1) ?>">‹ Previous</a></li>
                <?php endif; ?>
                <?php for ($p = max(1, $currentPage - 2); $p <= min($totalPages, $currentPage + 2); $p++): ?>
                    <li class="<?= $p === $currentPage ? 'active' : '' ?>">
                        <a href="<?= $pageUrl($p) ?>"><?= $p ?></a>
                    </li>
                <?php endfor; ?>
                <?php if ($currentPage < $totalPages): ?>
                    <li><a href="<?= $pageUrl($currentPage + 1) ?>">Next ›</a></li>
                    <li><a href="<?= $pageUrl($totalPages) ?>">Last »</a></li>
                <?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Full-screen Detail Modal -->
<div id="detailModal" class="detail-modal">
    <div class="detail-modal-content">
        <div class="modal-header">
            <h2>Career Guidance Report — <span id="modalStudentName"></span></h2>
            <span class="close-modal">&times;</span>
        </div>
        <div class="modal-body" id="modalBody"></div>
        <div class="modal-footer">
            <button class="print-btn" onclick="printModalContent()">Print Report</button>
            <button class="close-btn close-modal-btn">Close</button>
        </div>
    </div>
</div>

<!-- JS - container fix -->
<script>
(function() {
    var container = document.querySelector('.wrap > .container');
    if (container) {
        container.style.width    = '100%';
        container.style.maxWidth = '100%';
        container.style.padding  = '0 15px';
        container.style.overflowX = 'auto';
    }
})();
</script>

<!-- JS - Hover dialog (tooltip) -->
<script>
(function () {
    var dialog = null;

    function getDialog() {
        if (!dialog) {
            dialog = document.createElement('div');
            dialog.className = 'hover-dialog';
            dialog.style.display = 'none';
            document.body.appendChild(dialog);
        }
        return dialog;
    }

    function show(content, x, y) {
        var d  = getDialog();
        d.innerHTML = content.replace(/\n/g, '<br>');
        d.style.display = 'block';
        var dw = d.offsetWidth, dh = d.offsetHeight;
        var left = x + 15, top = y - dh - 10;
        if (left + dw > window.innerWidth)  left = window.innerWidth  - dw - 10;
        if (top < 0)                        top  = y + 22;
        d.style.left = left + 'px';
        d.style.top  = top  + 'px';
    }

    function hide() { if (dialog) dialog.style.display = 'none'; }

    var hideTimer;

    document.querySelectorAll('.hover-cell').forEach(function (cell) {
        cell.addEventListener('mouseenter', function (e) {
            clearTimeout(hideTimer);
            var content = this.getAttribute('data-hover') || '';
            var skip    = ['None identified', 'Not Available', 'Not specified', 'No remarks yet', ''];
            if (content.trim() && skip.indexOf(content.trim()) === -1 && content !== 'null') {
                var r = this.getBoundingClientRect();
                show(content, r.left + window.scrollX, r.top + window.scrollY);
            }
        });
        cell.addEventListener('mouseleave', function () {
            hideTimer = setTimeout(hide, 100);
        });
    });

    window.addEventListener('scroll', hide);
    window.addEventListener('resize', hide);
})();
</script>

<!-- JS - Year filter -->
<script>
(function () {
    var sel = document.getElementById('f-year');
    if (sel) {
        sel.addEventListener('change', function () {
            var url = new URL(window.location.href);
            url.searchParams.delete('page');
            if (this.value) url.searchParams.set('year', this.value);
            else            url.searchParams.delete('year');
            window.location.href = url.toString();
        });
    }
})();
</script>

<!-- JS - Search inputs (Enter + debounce) -->
<script>
(function () {
    var fields = {
        'f-name'     : 'name',
        'f-father'   : 'father',
        'f-admission': 'admission_no',
        'f-school'   : 'school',
        'f-class'    : 'class',
        'f-section'  : 'section'
    };
    var timer;
    function submit() {
        var url = new URL(window.location.href);
        url.searchParams.delete('page');
        Object.keys(fields).forEach(function (id) {
            var el = document.getElementById(id);
            if (el) {
                var val = el.value.trim();
                if (val) url.searchParams.set(fields[id], val);
                else     url.searchParams.delete(fields[id]);
            }
        });
        var yearSel = document.getElementById('f-year');
        if (yearSel && yearSel.value) url.searchParams.set('year', yearSel.value);
        window.location.href = url.toString();
    }
    Object.keys(fields).forEach(function (id) {
        var el = document.getElementById(id);
        if (el) {
            el.addEventListener('keydown', function (e) { if (e.key === 'Enter') { clearTimeout(timer); submit(); } });
            el.addEventListener('input',   function ()  { clearTimeout(timer); timer = setTimeout(submit, 600); });
        }
    });
})();
</script>

<!-- JS - View Details Modal -->
<script>
var modal            = document.getElementById('detailModal');
var modalBody        = document.getElementById('modalBody');
var modalStudentName = document.getElementById('modalStudentName');

function closeModal() { modal.style.display = 'none'; }

function printModalContent() {
    var printWindow = window.open('', '_blank');
    printWindow.document.write('<html><head><title>Career Guidance Report</title>');
    printWindow.document.write('<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">');
    printWindow.document.write('<script src="https://code.highcharts.com/highcharts.js"><\/script>');
    printWindow.document.write('<style>body{padding:20px;font-size:14px;color:#000;} .form_div{border:#000 solid 2px;display:block;clear:both;overflow:hidden;} .hrader{border-bottom:#000 solid 2px;} .schedule h1{background:#f8c301;text-transform:uppercase;text-align:center;font-size:18px;padding:10px;} .dbda_bg{background:#333;padding:7px 0;width:100%;} .strng{color:#fff;margin:0;padding:0;text-align:center;} .student_info{padding:10px;} .linetx{display:block;border-bottom:#000 solid 1px;} .pagebreak{page-break-after:always;} .table{width:100%;border-collapse:collapse;} .table td,.table th{border:1px solid #ddd;padding:8px;}</style>');
    printWindow.document.write('</head><body>');
    printWindow.document.write(modalBody.innerHTML);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    setTimeout(function(){ printWindow.print(); }, 800);
}

document.querySelectorAll('.view-detail-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var student = JSON.parse(this.dataset.student);

        // DBDA items
        var dbdaItems = [
            { short_name:'CA', name:'Closure Ability',    score: parseInt(student.dbda_ca) || 0 },
            { short_name:'CL', name:'Clerical Ability',   score: parseInt(student.dbda_cl) || 0 },
            { short_name:'MA', name:'Mechanical Ability', score: parseInt(student.dbda_ma) || 0 },
            { short_name:'NA', name:'Numerical Ability',  score: parseInt(student.dbda_na) || 0 },
            { short_name:'RA', name:'Reasoning Ability',  score: parseInt(student.dbda_ra) || 0 },
            { short_name:'SA', name:'Spatial Ability',    score: parseInt(student.dbda_sa) || 0 },
            { short_name:'VA', name:'Verbal Ability',     score: parseInt(student.dbda_va) || 0 }
        ];

        var dbdaDetail = {
            CA: {
                high:   'Has high ability to form a perceptual cluster from a number of vague or jumbled data present in the surroundings.',
                medium: 'Has an average ability to form a perceptual cluster from vague or jumbled data.',
                low:    'Has below average ability to form a perceptual cluster from vague or jumbled data.'
            },
            CL: {
                high:   'Has high ability for perceptual speed and accuracy.',
                medium: 'Has an average ability for perceptual speed and accuracy.',
                low:    'Has below average perceptual speed and accuracy.'
            },
            MA: {
                high:   'Has high ability to understand basic mechanical principles and concepts.',
                medium: 'Has an average understanding of basic mechanical principles and concepts.',
                low:    'Has below average understanding of basic mechanical principles.'
            },
            NA: {
                high:   'Has high ability to wield numerical operations rapidly and accurately.',
                medium: 'Has an average ability to wield numerical operations rapidly and accurately.',
                low:    'Has below average fluency with numbers and calculations.'
            },
            RA: {
                high:   'Has fine ability to apply the process of induction or logical reasoning in order to comprehend relationships.',
                medium: 'Has an average ability to deduce logical principles from information.',
                low:    'Has below average ability to deduce logical principles from information.'
            },
            SA: {
                high:   'Has high ability to visualize objects in 3D space.',
                medium: 'Has an average ability to visualize objects in 3D space.',
                low:    'Has below average ability to visualize objects in 3D space.'
            },
            VA: {
                high:   'Has an extremely good vocabulary and intelligibility in its profound grasping of the relationships among words.',
                medium: 'Has an average ability to comprehend English vocabulary and its usage.',
                low:    'Has below average vocabulary and comprehension of the English language.'
            }
        };

        function getDetail(area, score) {
            var d = dbdaDetail[area];
            if (!d) return '';
            if (score >= 8) return d.high;
            if (score >= 4) return d.medium;
            return d.low;
        }

        function getScoreCat(score) {
            if (score >= 8) return 3;
            if (score >= 4) return 2;
            return 1;
        }

        var proficiencyRows = '', strengthRows = '', improvementRows = '';
        dbdaItems.forEach(function(item) {
            var detail = getDetail(item.short_name, item.score);
            var cat    = getScoreCat(item.score);
            if (cat === 3) proficiencyRows  += '<li>' + detail + '</li>';
            if (cat === 2) strengthRows     += '<li>' + detail + '</li>';
            if (cat === 1) improvementRows  += '<li>' + detail + '</li>';
        });

        var dbdaTableRows = dbdaItems.map(function(item) {
            return '<tr>'
                 + '<td valign="middle"><b>' + item.name + ' (' + item.short_name + ')</b></td>'
                 + '<td align="center" valign="middle"><b>' + item.score + '</b></td>'
                 + '</tr>';
        }).join('');

        var cisFields = [
            { area:'Administration',       key:'cis_adm' },
            { area:'Entrepreneurship',     key:'cis_ent' },
            { area:'Defence',              key:'cis_def' },
            { area:'Sports',               key:'cis_sp' },
            { area:'Creative',             key:'cis_cr' },
            { area:'Personal Services',    key:'cis_per' },
            { area:'Medical',              key:'cis_med' },
            { area:'Technical',            key:'cis_tech' },
            { area:'Expressive Arts',      key:'cis_exp' },
            { area:'Computer',             key:'cis_comp' },
            { area:'Humanities',           key:'cis_hum' },
            { area:'Education',            key:'cis_edn' },
            { area:'Natural Sciences',     key:'cis_nat' },
            { area:'Clerical',             key:'cis_cl' }
        ];
        
        var cisScores = cisFields.map(function(f) {
            var raw = student[f.key];
            var parsed = parseInt(raw);
            var score = 1;
            if (!isNaN(parsed) && raw !== null && raw !== '' && raw !== undefined && parsed >= 0) {
                score = Math.max(1, parsed);
            }
            return { area: f.area, score: score };
        });

        var cisTableRows = cisScores.map(function(item) {
            return '<tr>'
                 + '<td valign="middle">' + item.area + '</td>'
                 + '<td align="center" valign="middle"><b>' + item.score + '</b></td>'
                 + '</tr>';
        }).join('');

        var cisInterestText = (student.cis_interest_areas || '').trim();
        if (!cisInterestText) {
            cisInterestText = '<div class="well" style="background:#f5f5f5; padding:10px;">No interest areas identified. Please complete the CIS assessment.</div>';
        }

        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }

        var html = `
<div class="form_div">

    <div class="hrader">
        <table width="100%" style="background:#f8c301;">
            <tr>
                <td width="20%"><img class="h1tx" style="margin-left:6px;" src="images/LogoAis.png" /></td>
                <td width="60%" align="center">
                    <h2 style="margin:0 0 4px;">Amity Career Counselling and Guidance Cell, Amity Schools</h2>
                    <h3 style="margin:0;">Career Guidance Report</h3>
                </td>
                <td width="20%"><img class="h1tx" style="margin-right:6px;" src="images/logo.png" /></td>
            </tr>
        </table>
    </div>

    <div class="innerdiv">

        <div class="schedule">
            <h1 style="font-size:18px;">Student Information</h1>
            <div class="row" style="margin:0;">
                <div class="col-sm-6 col-xs-6">
                    <div class="student_info">
                        <strong>Student Name:</strong> ${escapeHtml(student.name || '')}
                        <span class="linetx"></span>
                    </div>
                </div>
                <div class="col-sm-6 col-xs-6">
                    <div class="student_info">
                        <strong>Father's Name:</strong> ${escapeHtml(student.father || '')}
                        <span class="linetx"></span>
                    </div>
                </div>
                <div class="col-sm-6 col-xs-6">
                    <div class="student_info">
                        <strong>School:</strong> ${escapeHtml(student.school_name || '')}
                        <span class="linetx"></span>
                    </div>
                </div>
                <div class="col-sm-6 col-xs-6">
                    <div class="student_info">
                        <strong>Admission No.:</strong> ${escapeHtml(student.admission_no || '')}
                        <span class="linetx"></span>
                    </div>
                </div>
                <div class="col-sm-6 col-xs-6">
                    <div class="student_info">
                        <strong>DOB:</strong> ${escapeHtml(student.dob || '')}
                        <span class="linetx"></span>
                    </div>
                </div>
                <div class="col-sm-6 col-xs-6">
                    <div class="student_info">
                        <strong>Class:</strong> ${escapeHtml(student.class_name || '')} - ${escapeHtml(student.section_name || '')}
                        <span class="linetx"></span>
                    </div>
                </div>
            </div>
        </div>
        <div class="clearfix"></div>

        <div class="col-sm-12 col-md-12 col-xs-12" id="rpt-dbdaArea">
            <table class="table table-striped table-bordered">
                <tbody>
                    <tr align="center">
                        <td colspan="3"><h4>DBDA (Aptitude Test)</h4></td>
                    </tr>
                    <tr align="center">
                        <td colspan="3">
                            <table class="table table-striped table-bordered">
                                <tr class="active">
                                    <th>Items</th>
                                    <th>Score</th>
                                    <th width="65%" style="text-align:center;">
                                        An Evaluation Tool to assess the cognitive ability and achievement of an individual
                                    </th>
                                </tr>
                                <tr>
                                    <td style="padding:0; border:0;"></td>
                                    <td style="padding:0; border:0;"></td>
                                    <td rowspan="8" style="vertical-align:top; padding:4px;">
                                        <div id="rpt-dbdaChart" style="width:100%; height:340px;"></div>
                                    </td>
                                </tr>
                                ${dbdaTableRows}
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td valign="middle" colspan="3">
                            <div class="dbda_bg_proficiency"><h4 class="strng" style="text-align:center;">Areas of Proficiency</h4></div>
                            <ul>${proficiencyRows || '<li>None identified</li>'}</ul>
                        </td>
                    </tr>
                    <tr>
                        <td valign="middle" colspan="3">
                            <div class="dbda_bg_strength"><h4 class="strng" style="text-align:center;">Areas of Strength</h4></div>
                            <ul>${strengthRows || '<li>None identified</li>'}</ul>
                        </td>
                    </tr>
                    <tr>
                        <td valign="middle" colspan="3">
                            <div class="dbda_bg_improvement"><h4 class="strng" style="text-align:center;">Areas of Improvement</h4></div>
                            <ul>${improvementRows || '<li>None identified</li>'}</ul>
                        </td>
                    </tr>
                    <tr align="center">
                        <td colspan="3"><h4>MBTI (Personality Test)</h4></td>
                    </tr>
                    <tr>
                        <td valign="middle" colspan="3">
                            ${student.mbti_output || 'Not Available'}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="clearfix pagebreak"></div>

        <div class="col-sm-12 col-md-12 col-xs-12" id="rpt-cisArea">
            <table class="table table-striped table-bordered">
                <tbody>
                    <tr align="center">
                        <td colspan="3"><h4>CIS (Interest Test)</h4></td>
                    </tr>
                    <tr>
                        <th>Items</th>
                        <th>Score</th>
                        <th width="65%" style="text-align:center;">
                            Indicates Areas of interest to get an index for job persistence and job satisfaction
                        </th>
                    </tr>
                    <tr>
                        <td style="padding:0; border:0;"></td>
                        <td style="padding:0; border:0;"></td>
                        <td rowspan="14" style="vertical-align:top; padding:4px;">
                            <div id="rpt-cisChart" style="width:100%; height:420px;"></div>
                            <div style="padding:8px; font-size:13px; line-height:1.6;">
                                ${cisInterestText || ''}
                            </div>
                        </td>
                    </tr>
                    ${cisTableRows}
                </tbody>
            </table>
        </div>

        <div class="clearfix pagebreak"></div>

        <div class="col-sm-12 col-md-12 col-xs-12" id="rpt-feedbackArea">
            <table class="table table-striped table-bordered">
                <tbody>
                    <tr align="center">
                        <td colspan="3"><h4>COUNSELLOR COMMENTS</h4></td>
                    </tr>
                    <tr><th>Academic</th></tr>
                    <tr><td>${student.feedback_academic  || 'Not specified'}</td></tr>
                    <tr><th>Personal interaction Child</th></tr>
                    <tr><td>${student.feedback_child     || 'Not specified'}</td></tr>
                    <tr><th>Personal interaction Parents</th></tr>
                    <tr><td>${student.feedback_parents   || 'Not specified'}</td></tr>
                    <tr><th>Suggested Career and Stream Option-1</th></tr>
                    <tr><td>${student.feedback_career_opt1 || 'Not specified'}</td></tr>
                    <tr><th>Suggested Career and Stream Option-2</th></tr>
                    <tr><td>${student.feedback_career_opt2 || 'Not specified'}</td></tr>
                    <tr><th>Counsellor Remarks</th></tr>
                    <tr><td>${student.feedback_remarks   || 'No remarks yet'}</td></tr>
                    <tr><th>Counsellor Name</th></tr>
                    <tr><td>${student.feedback_counsellor || 'Not specified'}</td></tr>
                </tbody>
            </table>
        </div>

    </div><!-- /innerdiv -->
</div><!-- /form_div -->
        `;

        modalBody.innerHTML = html;
        modalStudentName.innerHTML = student.name || '';
        modal.style.display = 'block';

        // ── Render Highcharts ─────────────────────────────────────────────
        setTimeout(function() {
            if (typeof Highcharts === 'undefined') return;

            Highcharts.chart('rpt-dbdaChart', {
                chart: { type:'column' },
                title: { text:'DBDA Score' },
                subtitle: { text:'' },
                xAxis: { type:'category' },
                yAxis: {
                    max: 10, min: 0, tickInterval: 1,
                    title: { text:'SCORE' }
                },
                legend: { enabled:false },
                credits: { enabled:false },
                plotOptions: {
                    series: {
                        borderWidth: 0,
                        dataLabels: { 
                            enabled: true, 
                            format: '{point.y:.0f}',
                            style: { 
                                fontSize: '12px',
                                fontWeight: 'bold',
                                color: '#333333'
                            },
                            y: -5
                        }
                    }
                },              
                tooltip: {
                    headerFormat: '<span style="font-size:14px">{series.name}</span><br>',
                    pointFormat: '<span style="font-size:16px; color:{point.color}">{point.name}</span>: <b style="font-size:18px;">{point.y:.0f}</b><br/>',
                    style: { fontSize: '14px' }
                },
                series: [{
                    name: 'DBDA Area',
                    colorByPoint: true,
                    data: dbdaItems.map(function(i){ return { name:i.short_name, y:i.score }; })
                }],
                events: { load: function(e){ e.target.reflow(); } }
            });

            Highcharts.chart('rpt-cisChart', {
                chart: { type:'column' },
                title: { text:'CIS Score' },
                subtitle: { text:'' },
                xAxis: {
                    type: 'category',
                    labels: { rotation:-45, style:{ fontSize:'10px' } }
                },
                yAxis: {
                    max: 10, min: 0, tickInterval: 1,
                    title: { text:'SCORE' }
                },
                legend: { enabled:false },
                credits: { enabled:false },
                plotOptions: {
                    series: {
                        borderWidth: 0,
                        dataLabels: { enabled:true, format:'{point.y:.0f}' }
                    }
                },
                tooltip: {
                    headerFormat: '<span style="font-size:11px">{series.name}</span><br>',
                    pointFormat:  '<span style="color:{point.color}">{point.name}</span>: <b>{point.y:.0f}</b><br/>'
                },
                series: [{
                    name: 'CIS Area',
                    colorByPoint: true,
                    data: cisScores.map(function(i){ return { name:i.area, y:i.score }; })
                }],
                events: { load: function(e){ e.target.reflow(); } }
            });
        }, 150);
    });
});

// Close modal
document.querySelector('.close-modal')?.addEventListener('click', closeModal);
document.querySelector('.close-modal-btn')?.addEventListener('click', closeModal);
window.addEventListener('click', function(e) { if (e.target === modal) closeModal(); });
</script>