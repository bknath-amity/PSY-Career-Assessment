<?php

namespace backend\controllers;

use Yii;
use backend\models\Student;
use backend\models\StudentSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\web\UploadedFile;
use yii\db\mssql\PDO;

/**
 * StudentController implements the CRUD actions for Student model.
 */
class StudentController extends Controller {

    /**
     * @inheritdoc
     */
    public function behaviors() {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Lists all Student models.
     * @return mixed
     */
    public function actionIndex() {
        $searchModel = new StudentSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
                    'searchModel' => $searchModel,
                    'dataProvider' => $dataProvider,
        ]);
    }

    public function actionResult() {
        $searchModel = new StudentSearch();
        var_dump(Yii::$app->request->queryParams);
        var_dump(Yii::$app->user->identity->school);
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('result', [
                    'searchModel' => $searchModel,
                    'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * import student model.
     */
    public function actionImport() {
        /**/
        $modeImport = new Import();
        $modeImport->FILE = UploadedFile::getInstances($modeImport, "file");
        $modeImport->FILE->saveAs('uploads/' . time() . $modeImport->FILE->extension);
        /**/
        $inputFile = 'uploads/student.xlsx';
        try {
            $inputFileType = \PHPExcel_IOFactory::identify($inputFile);
            $objReader = \PHPExcel_IOFactory::createReader($inputFileType);
            $objPHPExcel = $objReader->load($inputFile);
        } catch (Exception $ex) {
            die('Error');
        }

        $sheet = $objPHPExcel->getSheet(0);
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();
        $connection = Yii::$app->getDb();
        $command = $connection->createCommand("");
        echo "<pre>";
        for ($index = 2;
                $index <= $highestRow;
                $index++) {
            $rowData = $sheet->rangeToArray('A' . $index . ":" . $highestColumn . $index, NULL, TRUE, FALSE);
            $ROWS[] = ARRAY($rowData[0][0], $rowData[0][1], $rowData[0][2], $rowData[0][3], $rowData[0][4], $rowData[0][5], $rowData[0][6], date('Y-m-d H:i:s'));
        }
        $COLUMNS = ARRAY("NAME", "FATHER", "DOB", "SCHOOL", "SCHOOL_CLASS", "SECTION", "ADMISSION_NO", "CRAETED_ON");
        $command->batchInsert("student", $COLUMNS, $ROWS)->execute();
        Yii::$app->db->createCommand('UPDATE student SET EXAM_KEY=LEFT(UUID(),8) WHERE EXAM_KEY IS NULL')->execute();
        
        // After import, sync all imported students to student_report only (NOT view_reports)
        $this->syncAllToStudentReport();
        
        // DO NOT sync to view_reports here - wait for manual sync button
        // The sync button will populate view_reports when clicked
        
        echo "Import completed. Use 'Sync to Report' button on School Results page to populate career reports.";
        echo "</pre>";
    }

    /**
     * Displays a single Student model.
     * @param integer $id
     * @return mixed
     */
    public function actionView($id) {
        return $this->render('view', [
                    'model' => $this->findModel($id),
        ]);
    }

    /**
     * Displays a single Student model from view_reports table.
     * @param integer $id
     * @return mixed
     */
    public function actionReport() {
        $studentId = Yii::$app->request->get('id');
        
        // DO NOT auto-sync - only show if already synced
        // $this->syncToViewReports($studentId);
        
        // Get student data from view_reports table
        $studentData = \Yii::$app->db->createCommand('
            SELECT 
                student_id AS ID,
                name AS NAME,
                father AS FATHER,
                admission_no AS ADMISSION_NO,
                dob AS DOB,
                school_name AS SCHOOL_NAME,
                class_name AS CLASS_NAME,
                section_name AS SECTION_NAME,
                exam_key AS EXAM_KEY,
                dbda_ca,
                dbda_cl,
                dbda_ma,
                dbda_na,
                dbda_ra,
                dbda_sa,
                dbda_va,
                dbda_total,
                areas_of_proficiency,
                areas_of_strength,
                areas_of_improvement,
                mbti_output,
                cis_interest_areas,
                feedback_academic,
                feedback_child,
                feedback_parents,
                feedback_career_opt1,
                feedback_career_opt2,
                feedback_remarks,
                feedback_counsellor
            FROM view_reports 
            WHERE student_id = :student
        ')
        ->bindValue(':student', $studentId, PDO::PARAM_INT)
        ->queryOne();
        
        // If not found in view_reports, show message
        if (empty($studentData)) {
            Yii::$app->session->setFlash('warning', 'Student data not synced yet. Please ask administrator to sync this school.');
            return $this->redirect(['index']);
        }
        
        // Format student array for the view
        $student = [[
            'ID' => $studentData['ID'],
            'NAME' => $studentData['NAME'],
            'FATHER' => $studentData['FATHER'],
            'ADMISSION_NO' => $studentData['ADMISSION_NO'],
            'DOB' => $studentData['DOB'],
            'SCHOOL_NAME' => $studentData['SCHOOL_NAME'],
            'CLASS_NAME' => $studentData['CLASS_NAME'],
            'SECTION_NAME' => $studentData['SECTION_NAME'],
            'EXAM_KEY' => $studentData['EXAM_KEY'],
        ]];
        
        // Format DBDA report array
        $dbdaReport = [];
        $dbdaItems = [
            ['SHORT_NAME' => 'CA', 'NAME' => 'Closure Ability', 'STEN_SCORE' => $studentData['dbda_ca'] ?? 0],
            ['SHORT_NAME' => 'CL', 'NAME' => 'Clerical Ability', 'STEN_SCORE' => $studentData['dbda_cl'] ?? 0],
            ['SHORT_NAME' => 'MA', 'NAME' => 'Mechanical Ability', 'STEN_SCORE' => $studentData['dbda_ma'] ?? 0],
            ['SHORT_NAME' => 'NA', 'NAME' => 'Numerical Ability', 'STEN_SCORE' => $studentData['dbda_na'] ?? 0],
            ['SHORT_NAME' => 'RA', 'NAME' => 'Reasoning Ability', 'STEN_SCORE' => $studentData['dbda_ra'] ?? 0],
            ['SHORT_NAME' => 'SA', 'NAME' => 'Spatial Ability', 'STEN_SCORE' => $studentData['dbda_sa'] ?? 0],
            ['SHORT_NAME' => 'VA', 'NAME' => 'Verbal Ability', 'STEN_SCORE' => $studentData['dbda_va'] ?? 0],
        ];
        
        foreach ($dbdaItems as $item) {
            $score = $item['STEN_SCORE'];
            if ($score <= 3) {
                $scoreCategory = 1;
            } elseif ($score <= 7) {
                $scoreCategory = 2;
            } else {
                $scoreCategory = 3;
            }
            $dbdaReport[] = [
                'SHORT_NAME' => $item['SHORT_NAME'],
                'NAME' => $item['NAME'],
                'STEN_SCORE' => $score,
                'SCORE' => $scoreCategory,
                'DETAIL' => $this->getDbdaDetailText($item['SHORT_NAME'], $score)
            ];
        }
        
        // Format MBTI report
        $mbtiReport = [['OUTPUT' => $studentData['mbti_output'] ?? 'Not Available']];
        
        // Format CIS report
        $cisReport = [[
            'STEN_AREAS' => $studentData['cis_interest_areas'] ?? 'Not Available',
            'iNTREST_AREAS' => $studentData['cis_interest_areas'] ?? 'Not Available'
        ]];
        
        // Format CIS scores
        $cisScore = $this->getCisScoresFromViewReports($studentId);
        
        // Format feedback
        $feedbackReport = [
            'academic' => $studentData['feedback_academic'] ?? '',
            'child' => $studentData['feedback_child'] ?? '',
            'parents' => $studentData['feedback_parents'] ?? '',
            'careeropt1' => $studentData['feedback_career_opt1'] ?? '',
            'careeropt2' => $studentData['feedback_career_opt2'] ?? '',
            'remarks' => $studentData['feedback_remarks'] ?? '',
            'name' => $studentData['feedback_counsellor'] ?? ''
        ];

        return $this->render('report', array(
            "student" => $student, 
            "cisReport" => $cisReport, 
            "cisScore" => $cisScore, 
            "dbdaReport" => $dbdaReport, 
            "mbtiReport" => $mbtiReport, 
            'feedbackReport' => $feedbackReport
        ));
    }

    /**
     * Displays a single Student model from view_reports table (view version).
     * @param integer $id
     * @return mixed
     */
    public function actionReportview() {
        $studentId = Yii::$app->request->get('id');
        
        // DO NOT auto-sync - only show if already synced
        
        // Get student data from view_reports table
        $studentData = \Yii::$app->db->createCommand('
            SELECT 
                student_id AS ID,
                name AS NAME,
                father AS FATHER,
                admission_no AS ADMISSION_NO,
                dob AS DOB,
                school_name AS SCHOOL_NAME,
                class_name AS CLASS_NAME,
                section_name AS SECTION_NAME,
                exam_key AS EXAM_KEY,
                dbda_ca,
                dbda_cl,
                dbda_ma,
                dbda_na,
                dbda_ra,
                dbda_sa,
                dbda_va,
                dbda_total,
                areas_of_proficiency,
                areas_of_strength,
                areas_of_improvement,
                mbti_output,
                cis_interest_areas,
                feedback_academic,
                feedback_child,
                feedback_parents,
                feedback_career_opt1,
                feedback_career_opt2,
                feedback_remarks,
                feedback_counsellor
            FROM view_reports 
            WHERE student_id = :student
        ')
        ->bindValue(':student', $studentId, PDO::PARAM_INT)
        ->queryOne();
        
        if (empty($studentData)) {
            Yii::$app->session->setFlash('warning', 'Student data not synced yet. Please ask administrator to sync this school.');
            return $this->redirect(['index']);
        }
        
        // Format student array for the view
        $student = [[
            'ID' => $studentData['ID'],
            'NAME' => $studentData['NAME'],
            'FATHER' => $studentData['FATHER'],
            'ADMISSION_NO' => $studentData['ADMISSION_NO'],
            'DOB' => $studentData['DOB'],
            'SCHOOL_NAME' => $studentData['SCHOOL_NAME'],
            'CLASS_NAME' => $studentData['CLASS_NAME'],
            'SECTION_NAME' => $studentData['SECTION_NAME'],
            'EXAM_KEY' => $studentData['EXAM_KEY'],
        ]];
        
        // Format DBDA report array
        $dbdaReport = [];
        $dbdaItems = [
            ['SHORT_NAME' => 'CA', 'NAME' => 'Closure Ability', 'STEN_SCORE' => $studentData['dbda_ca'] ?? 0],
            ['SHORT_NAME' => 'CL', 'NAME' => 'Clerical Ability', 'STEN_SCORE' => $studentData['dbda_cl'] ?? 0],
            ['SHORT_NAME' => 'MA', 'NAME' => 'Mechanical Ability', 'STEN_SCORE' => $studentData['dbda_ma'] ?? 0],
            ['SHORT_NAME' => 'NA', 'NAME' => 'Numerical Ability', 'STEN_SCORE' => $studentData['dbda_na'] ?? 0],
            ['SHORT_NAME' => 'RA', 'NAME' => 'Reasoning Ability', 'STEN_SCORE' => $studentData['dbda_ra'] ?? 0],
            ['SHORT_NAME' => 'SA', 'NAME' => 'Spatial Ability', 'STEN_SCORE' => $studentData['dbda_sa'] ?? 0],
            ['SHORT_NAME' => 'VA', 'NAME' => 'Verbal Ability', 'STEN_SCORE' => $studentData['dbda_va'] ?? 0],
        ];
        
        foreach ($dbdaItems as $item) {
            $score = $item['STEN_SCORE'];
            if ($score <= 3) {
                $scoreCategory = 1;
            } elseif ($score <= 7) {
                $scoreCategory = 2;
            } else {
                $scoreCategory = 3;
            }
            $dbdaReport[] = [
                'SHORT_NAME' => $item['SHORT_NAME'],
                'NAME' => $item['NAME'],
                'STEN_SCORE' => $score,
                'SCORE' => $scoreCategory,
                'DETAIL' => $this->getDbdaDetailText($item['SHORT_NAME'], $score)
            ];
        }
        
        // Format MBTI report
        $mbtiReport = [['OUTPUT' => $studentData['mbti_output'] ?? 'Not Available']];
        
        // Format CIS report
        $cisReport = [[
            'STEN_AREAS' => $studentData['cis_interest_areas'] ?? 'Not Available',
            'iNTREST_AREAS' => $studentData['cis_interest_areas'] ?? 'Not Available'
        ]];
        
        // Format CIS scores
        $cisScore = $this->getCisScoresFromViewReports($studentId);
        
        // Format feedback
        $feedbackReport = [
            'academic' => $studentData['feedback_academic'] ?? '',
            'child' => $studentData['feedback_child'] ?? '',
            'parents' => $studentData['feedback_parents'] ?? '',
            'careeropt1' => $studentData['feedback_career_opt1'] ?? '',
            'careeropt2' => $studentData['feedback_career_opt2'] ?? '',
            'remarks' => $studentData['feedback_remarks'] ?? '',
            'name' => $studentData['feedback_counsellor'] ?? ''
        ];

        return $this->render('reportview', array(
            "student" => $student, 
            "cisReport" => $cisReport, 
            "cisScore" => $cisScore, 
            "dbdaReport" => $dbdaReport, 
            "mbtiReport" => $mbtiReport, 
            'feedbackReport' => $feedbackReport
        ));
    }

    public function actionReportsection() {
        $schoolId = Yii::$app->request->get('school');
        $sectionId = Yii::$app->request->get('section');
        
        // Get students from view_reports table
        $students = \Yii::$app->db->createCommand('
            SELECT 
                student_id AS ID,
                name AS NAME,
                father AS FATHER,
                admission_no AS ADMISSION_NO,
                dob AS DOB,
                school_name AS SCHOOL_NAME,
                class_name AS CLASS_NAME,
                section_name AS SECTION_NAME,
                exam_key AS EXAM_KEY
            FROM view_reports
            WHERE school_name = (SELECT NAME FROM school WHERE ID = :school) 
            AND section_name = (SELECT TITLE FROM section WHERE ID = :section)
            ORDER BY name
        ')
        ->bindValue(':school', $schoolId, PDO::PARAM_INT)
        ->bindValue(':section', $sectionId, PDO::PARAM_INT)
        ->queryAll();
        
        foreach ($students as $student) {
            $studentId = $student['ID'];
            $studentData[] = $student;
            $cisReportData[] = $this->getCisReportFromViewReports($studentId);
            $cisScoreData[] = $this->getCisScoresFromViewReports($studentId);
            $dbdaReportData[] = $this->getDbdaReportFromViewReports($studentId);
            $mbtiReportData[] = $this->getMbtiReportFromViewReports($studentId);
        }
        return $this->render('reportsection', array(
            "studentData" => $studentData, 
            "cisReportData" => $cisReportData, 
            "cisScoreData" => $cisScoreData, 
            "dbdaReportData" => $dbdaReportData, 
            "mbtiReportData" => $mbtiReportData
        ));
    }

    /**
     * Displays Career Guidance Report for all students
     * Fetches data directly from view_reports table with filters
     * @return mixed
     */
    public function actionCareerReport() {
        // Get filter parameters from URL
        $filterSchool = Yii::$app->request->get('school', '');
        $filterYear = Yii::$app->request->get('year', '');
        $filterName = Yii::$app->request->get('name', '');
        $filterFather = Yii::$app->request->get('father', '');
        $filterAdmission = Yii::$app->request->get('admission_no', '');
        $filterClass = Yii::$app->request->get('class', '');
        $filterSection = Yii::$app->request->get('section', '');
        
        // Build the query
        $query = "SELECT 
            student_id AS id,
            name,
            father,
            admission_no,
            dob,
            exam_key,
            school_name,
            class_name,
            section_name,
            dbda_ca,
            dbda_cl,
            dbda_ma,
            dbda_na,
            dbda_ra,
            dbda_sa,
            dbda_va,
            dbda_total,
            areas_of_proficiency,
            areas_of_strength,
            areas_of_improvement,
            mbti_output,
            cis_interest_areas,
            cis_adm, cis_ent, cis_def, cis_sp, cis_cr, cis_per,
            cis_med, cis_tech, cis_exp, cis_comp, cis_hum, cis_edn, cis_nat, cis_cl,
            feedback_academic,
            feedback_child,
            feedback_parents,
            feedback_career_opt1,
            feedback_career_opt2,
            feedback_remarks,
            feedback_counsellor,
            created_at,
            updated_at
        FROM view_reports 
        WHERE 1=1";
        
        // Apply school filter
        if (!empty($filterSchool)) {
            if (is_numeric($filterSchool)) {
                $schoolName = Yii::$app->db->createCommand("SELECT NAME FROM school WHERE ID = :id")
                    ->bindValue(':id', $filterSchool)
                    ->queryScalar();
                if ($schoolName) {
                    $query .= " AND school_name = :school_name";
                }
            } else {
                $query .= " AND school_name LIKE :school_name";
            }
        }
        
        // Apply class filter
        if (!empty($filterClass)) {
            $query .= " AND class_name = :class_name";
        }
        
        // Apply section filter
        if (!empty($filterSection)) {
            $query .= " AND section_name = :section_name";
        }
        
        // Apply name filter
        if (!empty($filterName)) {
            $query .= " AND name LIKE :name";
        }
        
        // Apply father filter
        if (!empty($filterFather)) {
            $query .= " AND father LIKE :father";
        }
        
        // Apply admission number filter
        if (!empty($filterAdmission)) {
            $query .= " AND admission_no LIKE :admission_no";
        }
        
        // Apply year filter
        if (!empty($filterYear)) {
            $query .= " AND YEAR(created_at) = :year";
        }
        
        $query .= " ORDER BY school_name, class_name, section_name, name";
        
        // Prepare and execute the command
        $command = Yii::$app->db->createCommand($query);
        
        // Bind parameters
        if (!empty($filterSchool)) {
            if (is_numeric($filterSchool)) {
                $schoolName = Yii::$app->db->createCommand("SELECT NAME FROM school WHERE ID = :id")
                    ->bindValue(':id', $filterSchool)
                    ->queryScalar();
                if ($schoolName) {
                    $command->bindValue(':school_name', $schoolName);
                } else {
                    $command->bindValue(':school_name', $filterSchool);
                }
            } else {
                $command->bindValue(':school_name', '%' . $filterSchool . '%');
            }
        }
        
        if (!empty($filterClass)) {
            $command->bindValue(':class_name', $filterClass);
        }
        
        if (!empty($filterSection)) {
            $command->bindValue(':section_name', $filterSection);
        }
        
        if (!empty($filterName)) {
            $command->bindValue(':name', '%' . $filterName . '%');
        }
        
        if (!empty($filterFather)) {
            $command->bindValue(':father', '%' . $filterFather . '%');
        }
        
        if (!empty($filterAdmission)) {
            $command->bindValue(':admission_no', '%' . $filterAdmission . '%');
        }
        
        if (!empty($filterYear)) {
            $command->bindValue(':year', $filterYear);
        }
        
        try {
            $students = $command->queryAll();
        } catch (\Exception $e) {
            $students = [];
        }
        
        // Return the view with students (empty array if none found)
        return $this->render('careerReport', [
            'students' => $students ?: [],
        ]);
    }

    /**
     * Sync ALL students to view_reports table
     * This method can be called via URL: index.php?r=student/sync-all-to-view-reports
     */
    public function actionSyncAllToViewReports() {
        // Set maximum execution time to 10 minutes for large datasets
        set_time_limit(600);
        
        // Get total student count
        $totalStudents = Yii::$app->db->createCommand("SELECT COUNT(*) FROM student")->queryScalar();
        
        echo "<html><body><pre>";
        echo "Starting sync of $totalStudents students to view_reports table...\n";
        echo str_repeat("=", 60) . "\n\n";
        
        $students = Yii::$app->db->createCommand("SELECT ID FROM student ORDER BY ID")->queryAll();
        
        $successCount = 0;
        $errorCount = 0;
        $errorList = [];
        
        foreach ($students as $student) {
            try {
                $this->syncToViewReports($student['ID']);
                $successCount++;
                echo "[SUCCESS] Synced student ID: " . $student['ID'] . "\n";
            } catch (\Exception $e) {
                $errorCount++;
                $errorList[] = "ID: " . $student['ID'] . " - " . $e->getMessage();
                echo "[ERROR] Failed to sync student ID: " . $student['ID'] . " - " . $e->getMessage() . "\n";
            }
            
            // Flush output buffer to show progress
            ob_flush();
            flush();
        }
        
        echo "\n" . str_repeat("=", 60) . "\n";
        echo "SYNC COMPLETED!\n";
        echo str_repeat("=", 60) . "\n";
        echo "Total Students: $totalStudents\n";
        echo "Successfully Synced: $successCount\n";
        echo "Failed: $errorCount\n";
        
        if (!empty($errorList)) {
            echo "\nError Details:\n";
            foreach ($errorList as $error) {
                echo "- $error\n";
            }
        }
        
        echo "</pre></body></html>";
        
        Yii::$app->end();
    }

    /**
     * Sync ALL students to view_reports table (alias for backward compatibility)
     */
    public function actionSyncAllReports() {
        return $this->actionSyncAllToViewReports();
    }

    /**
     * Creates a new Student model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate() {
        $model = new Student();

        if ($model->load(Yii::$app->request->post())) {
            $model->CRAETED_ON = date('Y-m-d H:i:s');
            $model->save();
            Yii::$app->db->createCommand("UPDATE student SET EXAM_KEY=LEFT(UUID(),8) WHERE ID=:student")
                    ->bindValue(":student", $model->ID, PDO::PARAM_INT)
                    ->execute();
            
            // Sync the new student to student_report only (NOT view_reports)
            $this->syncSingleToStudentReport($model->ID);
            
            // DO NOT sync to view_reports - wait for manual sync button
            
            return $this->redirect(['view', 'id' => $model->ID]);
        } else {
            return $this->render('create', [
                        'model' => $model,
            ]);
        }
    }

    /**
     * Updates an existing Student model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id) {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            // Sync the updated student to student_report only (NOT view_reports)
            $this->syncSingleToStudentReport($model->ID);
            
            // DO NOT sync to view_reports - wait for manual sync button
            
            return $this->redirect(['view', 'id' => $model->ID]);
        } else {
            return $this->render('update', [
                        'model' => $model,
            ]);
        }
    }

    /**
     * Deletes an existing Student model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     */
    public function actionDelete($id) {
        // Also delete from student_report
        Yii::$app->db->createCommand("DELETE FROM student_report WHERE student_id = :student_id")
            ->bindValue(':student_id', $id, PDO::PARAM_INT)
            ->execute();
        
        // Also delete from view_reports
        Yii::$app->db->createCommand("DELETE FROM view_reports WHERE student_id = :student_id")
            ->bindValue(':student_id', $id, PDO::PARAM_INT)
            ->execute();
        
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    public function actionFeedback() {
        if (Yii::$app->request->isAjax) {
            if (
                    $_REQUEST["academic"] != '' && $_REQUEST["intChild"] != '' && $_REQUEST["intParents"] != '' && $_REQUEST["option1"] != '' && $_REQUEST["option2"] != '' && $_REQUEST["remarks"] != ''
            ) {

                $insert = "INSERT INTO student_feedback(student, academic, child, parents, careeropt1, careeropt2, remarks, createdby)
                    select * from (
                select :student as student, :academic as academic, :child as child, :parents as parents, 
                :careeropt1 as careeropt1, :careeropt2 as careeropt2, :remarks as remarks, :createdby as createdby
                ) a WHERE NOT EXISTS (SELECT student FROM student_feedback WHERE student=:student)";

                \Yii::$app->db->createCommand($insert)
                        ->bindValue(':student', $_REQUEST["student"], PDO::PARAM_INT)
                        ->bindValue(':academic', $_REQUEST["academic"], PDO::PARAM_STR)
                        ->bindValue(':child', $_REQUEST["intChild"], PDO::PARAM_STR)
                        ->bindValue(':parents', $_REQUEST["intParents"], PDO::PARAM_STR)
                        ->bindValue(':careeropt1', $_REQUEST["option1"], PDO::PARAM_STR)
                        ->bindValue(':careeropt2', $_REQUEST["option2"], PDO::PARAM_STR)
                        ->bindValue(':remarks', $_REQUEST["remarks"], PDO::PARAM_STR)
                        ->bindValue(':createdby', Yii::$app->user->identity->id, PDO::PARAM_STR)
                        ->execute();
                $update = "update student_feedback 
                set academic=:academic, child=:child, parents=:parents, careeropt1=:careeropt1, 
                careeropt2=:careeropt2, remarks=:remarks, createdby=:createdby
                WHERE student=:student";
                \Yii::$app->db->createCommand($update)
                        ->bindValue(':student', $_REQUEST["student"], PDO::PARAM_INT)
                        ->bindValue(':academic', $_REQUEST["academic"], PDO::PARAM_STR)
                        ->bindValue(':child', $_REQUEST["intChild"], PDO::PARAM_STR)
                        ->bindValue(':parents', $_REQUEST["intParents"], PDO::PARAM_STR)
                        ->bindValue(':careeropt1', $_REQUEST["option1"], PDO::PARAM_STR)
                        ->bindValue(':careeropt2', $_REQUEST["option2"], PDO::PARAM_STR)
                        ->bindValue(':remarks', $_REQUEST["remarks"], PDO::PARAM_STR)
                        ->bindValue(':createdby', Yii::$app->user->identity->id, PDO::PARAM_STR)
                        ->execute();

                // Sync to view_reports after feedback update
                // $this->syncToViewReports($_REQUEST["student"]);
                
                echo 'done';
            }
        } else {
            echo 'sorry';
        }
    }

    /**
     * Sync a single student to view_reports table using stored procedure
     * This is now ONLY called by the sync button
     */
    protected function syncToViewReports($studentId) {
        try {
            // Make sure the student exists
            $studentExists = Yii::$app->db->createCommand("SELECT ID FROM student WHERE ID = :student_id")
                ->bindValue(':student_id', $studentId)
                ->queryScalar();
            
            if ($studentExists) {
                // Call the stored procedure
                Yii::$app->db->createCommand("CALL sync_single_student(:student_id)")
                    ->bindValue(':student_id', $studentId, PDO::PARAM_INT)
                    ->execute();
            }
        } catch (\Exception $e) {
            // Log error silently - don't break the flow
            Yii::error("Sync error for student $studentId: " . $e->getMessage());
        }
    }

    /**
     * Get DBDA report from view_reports
     */
    protected function getDbdaReportFromViewReports($studentId) {
        $data = Yii::$app->db->createCommand('
            SELECT 
                dbda_ca, dbda_cl, dbda_ma, dbda_na, dbda_ra, dbda_sa, dbda_va,
                areas_of_proficiency, areas_of_strength, areas_of_improvement
            FROM view_reports 
            WHERE student_id = :student_id
        ')->bindValue(':student_id', $studentId)->queryOne();
        
        $dbdaReport = [];
        $items = [
            ['SHORT_NAME' => 'CA', 'NAME' => 'Closure Ability', 'SCORE' => $data['dbda_ca'] ?? 0],
            ['SHORT_NAME' => 'CL', 'NAME' => 'Clerical Ability', 'SCORE' => $data['dbda_cl'] ?? 0],
            ['SHORT_NAME' => 'MA', 'NAME' => 'Mechanical Ability', 'SCORE' => $data['dbda_ma'] ?? 0],
            ['SHORT_NAME' => 'NA', 'NAME' => 'Numerical Ability', 'SCORE' => $data['dbda_na'] ?? 0],
            ['SHORT_NAME' => 'RA', 'NAME' => 'Reasoning Ability', 'SCORE' => $data['dbda_ra'] ?? 0],
            ['SHORT_NAME' => 'SA', 'NAME' => 'Spatial Ability', 'SCORE' => $data['dbda_sa'] ?? 0],
            ['SHORT_NAME' => 'VA', 'NAME' => 'Verbal Ability', 'SCORE' => $data['dbda_va'] ?? 0],
        ];
        
        foreach ($items as $item) {
            $score = $item['SCORE'];
            if ($score <= 3) {
                $scoreCategory = 1;
            } elseif ($score <= 7) {
                $scoreCategory = 2;
            } else {
                $scoreCategory = 3;
            }
            $dbdaReport[] = [
                'SHORT_NAME' => $item['SHORT_NAME'],
                'NAME' => $item['NAME'],
                'STEN_SCORE' => $score,
                'SCORE' => $scoreCategory,
                'DETAIL' => $this->getDbdaDetailText($item['SHORT_NAME'], $score)
            ];
        }
        
        return $dbdaReport;
    }

    /**
     * Get MBTI report from view_reports
     */
    protected function getMbtiReportFromViewReports($studentId) {
        $data = Yii::$app->db->createCommand('
            SELECT mbti_output 
            FROM view_reports 
            WHERE student_id = :student_id
        ')->bindValue(':student_id', $studentId)->queryOne();
        
        return [['OUTPUT' => $data['mbti_output'] ?? 'Not Available']];
    }

    /**
     * Get CIS report from view_reports
     */
    protected function getCisReportFromViewReports($studentId) {
        $data = Yii::$app->db->createCommand('
            SELECT cis_interest_areas 
            FROM view_reports 
            WHERE student_id = :student_id
        ')->bindValue(':student_id', $studentId)->queryOne();
        
        return [[
            'STEN_AREAS' => $data['cis_interest_areas'] ?? 'Not Available',
            'iNTREST_AREAS' => $data['cis_interest_areas'] ?? 'Not Available'
        ]];
    }

    /**
     * Get CIS scores from view_reports
     */
    protected function getCisScoresFromViewReports($studentId) {
        $data = Yii::$app->db->createCommand('
            SELECT 
                cis_adm, cis_ent, cis_def, cis_sp, cis_cr, cis_per,
                cis_med, cis_tech, cis_exp, cis_comp, cis_hum, cis_edn, cis_nat, cis_cl
            FROM view_reports 
            WHERE student_id = :student_id
        ')->bindValue(':student_id', $studentId)->queryOne();
        
        $cisScores = [];
        $areas = [
            'ADM' => 'Administration', 'ENT' => 'Entrepreneurship', 'DEF' => 'Defence',
            'SP' => 'Sports', 'CR' => 'Creative', 'PER' => 'Personal Services',
            'MED' => 'Medical', 'TECH' => 'Technical', 'EXP' => 'Expressive Arts',
            'COMP' => 'Computer', 'HUM' => 'Humanities', 'EDN' => 'Education',
            'NAT' => 'Natural Sciences', 'CL' => 'Clerical'
        ];
        
        $index = 0;
        foreach ($areas as $area => $title) {
            $column = 'cis_' . strtolower($area);
            $cisScores[] = [
                'AREA_TITLE' => $title,
                'STEN_SCORE' => $data[$column] ?? 0,
                'AREA' => $area
            ];
            $index++;
        }
        
        return $cisScores;
    }

    /**
     * Get DBDA detail text based on area and score
     */
    protected function getDbdaDetailText($area, $score) {
        $details = [
            'CA' => [
                'low' => 'Has below average ability to form a perceptual cluster from vague or jumbled data.',
                'medium' => 'Has an average ability to form a perceptual cluster from vague or jumbled data.',
                'high' => 'Has high ability to form a perceptual cluster from a number of vague or jumbled data present in the surroundings.'
            ],
            'CL' => [
                'low' => 'Has below average perceptual speed and accuracy.',
                'medium' => 'Has an average ability for perceptual speed and accuracy.',
                'high' => 'Has high ability for perceptual speed and accuracy.'
            ],
            'MA' => [
                'low' => 'Has below average understanding of basic mechanical principles and concepts.',
                'medium' => 'Has an average understanding of basic mechanical principles and concepts.',
                'high' => 'Has high ability to understand basic mechanical principles and concepts.'
            ],
            'NA' => [
                'low' => 'Has below average fluency or comfort with numbers and calculations.',
                'medium' => 'Has an average ability to wield numerical operations rapidly and accurately.',
                'high' => 'Has high ability to wield numerical operations rapidly and accurately.'
            ],
            'RA' => [
                'low' => 'Has below average ability to deduce logical principles from information.',
                'medium' => 'Has an average ability to deduce logical principles from information.',
                'high' => 'Has fine ability to apply the process of induction or logical reasoning in order to comprehend relationships.'
            ],
            'SA' => [
                'low' => 'Has below average ability to visualize objects in 3D space.',
                'medium' => 'Has an average ability to visualize objects in 3D space.',
                'high' => 'Has high ability to visualize objects in 3D space.'
            ],
            'VA' => [
                'low' => 'Has below average vocabulary and comprehension of the English language.',
                'medium' => 'Has an average ability to comprehend English vocabulary and its usage.',
                'high' => 'Has an extremely good vocabulary and understands its profound grammatical usage.'
            ]
        ];
        
        if ($score <= 3) return $details[$area]['low'];
        if ($score <= 7) return $details[$area]['medium'];
        return $details[$area]['high'];
    }

    /**
     * Sync a single student to student_report table
     */
    protected function syncSingleToStudentReport($studentId) {
        $query = "
            SELECT 
                A.ID AS student_id,
                A.NAME AS name,
                A.FATHER AS father,
                A.ADMISSION_NO AS admission_no,
                A.DOB AS dob,
                A.SCHOOL AS school_id,
                B.NAME AS school_name,
                C.TITLE AS class_name,
                D.TITLE AS section_name,
                A.EXAM_KEY AS exam_key
            FROM student A 
            JOIN school B ON A.SCHOOL = B.ID 
            JOIN classes C ON A.SCHOOL_CLASS = C.ID 
            JOIN section D ON A.SECTION = D.ID
            WHERE A.ID = :student_id
        ";
        
        $studentData = Yii::$app->db->createCommand($query)
            ->bindValue(':student_id', $studentId)
            ->queryOne();
        
        if ($studentData) {
            $exists = Yii::$app->db->createCommand("SELECT id FROM student_report WHERE student_id = :student_id")
                ->bindValue(':student_id', $studentId)
                ->queryOne();
            
            if ($exists) {
                Yii::$app->db->createCommand()->update('student_report', $studentData, ['student_id' => $studentId])->execute();
            } else {
                Yii::$app->db->createCommand()->insert('student_report', $studentData)->execute();
            }
        }
    }

    /**
     * Sync ALL students to student_report table
     */
    protected function syncAllToStudentReport() {
        $query = "
            INSERT INTO `student_report` (`student_id`, `name`, `father`, `admission_no`, `dob`, `school_id`, `school_name`, `class_name`, `section_name`, `exam_key`)
            SELECT 
                A.ID AS student_id,
                A.NAME AS name,
                A.FATHER AS father,
                A.ADMISSION_NO AS admission_no,
                A.DOB AS dob,
                A.SCHOOL AS school_id,
                B.NAME AS school_name,
                C.TITLE AS class_name,
                D.TITLE AS section_name,
                A.EXAM_KEY AS exam_key
            FROM student A 
            JOIN school B ON A.SCHOOL = B.ID 
            JOIN classes C ON A.SCHOOL_CLASS = C.ID 
            JOIN section D ON A.SECTION = D.ID
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                father = VALUES(father),
                admission_no = VALUES(admission_no),
                school_name = VALUES(school_name),
                class_name = VALUES(class_name),
                section_name = VALUES(section_name),
                exam_key = VALUES(exam_key)
        ";
        
        Yii::$app->db->createCommand($query)->execute();
    }

    /**
     * Finds the Student model based on its primary key value.
     * @param integer $id
     * @return Student the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    public function findExamFinished($studentId) {
        $student = \Yii::$app->db->createCommand('SELECT case when a.EXAM_KEY is null then 10 else SUM(CASE WHEN STATUS=0 THEN 1 ELSE 0 END) end as FINISH_COUNT 
FROM student a 
JOIN student_test b on a.ID=b.STUDENT WHERE a.ID=:student')
                ->bindValue(':student', $studentId, PDO::PARAM_INT)
                ->queryAll();
        if ($student[0]['FINISH_COUNT'] == 10) {
            return 1;
        } else if ($student[0]['FINISH_COUNT'] < 10 && $student[0]['FINISH_COUNT'] > 0) {
            return 2;
        } else {
            return 0;
        }
    }

    public function findSchoolresult($schoolId) {
        $student = \Yii::$app->db->createCommand('
            SELECT count(ID) as STATUS FROM schoolresult WHERE SCHOOL_ID=:school AND RESULT_PUBLISH=1')
                ->bindValue(':school', $schoolId, PDO::PARAM_INT)
                ->queryOne();
        if ($student['STATUS'] == 1) {
            return 1;
        } else {
            return 0;
        }
    }

    protected function findModel($id) {
        if (($model = Student::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }

}