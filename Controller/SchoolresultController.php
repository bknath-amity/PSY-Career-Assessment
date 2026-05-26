<?php

namespace backend\controllers;

use Yii;
use backend\models\Schoolresult;
use backend\models\SchoolresultSearch;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;

/**
 * SchoolresultController implements the CRUD actions for Schoolresult model.
 */
class SchoolresultController extends Controller {

    /**
     * @inheritdoc
     */
    public function behaviors() {
        return [
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['POST'],
                    'sync' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Lists all Schoolresult models.
     * @return mixed
     */
    public function actionIndex() {
        $searchModel = new SchoolresultSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        return $this->render('index', [
                    'searchModel' => $searchModel,
                    'dataProvider' => $dataProvider,
        ]);
    }

    /**
     * Displays a single Schoolresult model.
     * @param integer $id
     * @return mixed
     */
    public function actionView($id) {
        return $this->render('view', [
                    'model' => $this->findModel($id),
        ]);
    }

    /**
     * Creates a new Schoolresult model.
     * If creation is successful, the browser will be redirected to the 'view' page.
     * @return mixed
     */
    public function actionCreate() {
        $model = new Schoolresult();

        if ($model->load(Yii::$app->request->post())) {
            $model->CRAETED_ON = date('Y-m-d h:m:s');
            $model->save();

            $school_id = $model->SCHOOL_ID;
            $result_publish = $model->RESULT_PUBLISH;
            $query = "UPDATE student SET `REPORT` = $result_publish WHERE SCHOOL = $school_id";
            \Yii::$app->db->createCommand($query)->execute();

            return $this->redirect(['view', 'id' => $model->ID]);
        } else {
            return $this->render('create', [
                        'model' => $model,
            ]);
        }
    }

    /**
     * Updates an existing Schoolresult model.
     * If update is successful, the browser will be redirected to the 'view' page.
     * @param integer $id
     * @return mixed
     */
    public function actionUpdate($id) {
        $model = $this->findModel($id);

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            $school_id = $model->SCHOOL_ID;
            $result_publish = $model->RESULT_PUBLISH;
            $query = "UPDATE student SET `REPORT` = $result_publish WHERE SCHOOL = $school_id";
            \Yii::$app->db->createCommand($query)->execute();
            
            return $this->redirect(['view', 'id' => $model->ID]);
        } else {
            return $this->render('update', [
                        'model' => $model,
            ]);
        }
    }

    /**
     * Deletes an existing Schoolresult model.
     * If deletion is successful, the browser will be redirected to the 'index' page.
     * @param integer $id
     * @return mixed
     */
    public function actionDelete($id) {
        $this->findModel($id)->delete();

        return $this->redirect(['index']);
    }

    /**
     * Syncs the school result data by populating view_reports for that school
     * @param integer $id
     * @return mixed
     */
    public function actionSync($id) {
        $model = $this->findModel($id);
        
        $school_id = $model->SCHOOL_ID;
        $result_publish = $model->RESULT_PUBLISH;
        
        // First, update student REPORT status
        $query = "UPDATE student SET `REPORT` = $result_publish WHERE SCHOOL = $school_id";
        $affectedRows = Yii::$app->db->createCommand($query)->execute();
        
        // Now, sync ALL students from this school to view_reports table
        // Get all student IDs for this school
        $students = Yii::$app->db->createCommand("SELECT ID FROM student WHERE SCHOOL = :school_id")
            ->bindValue(':school_id', $school_id)
            ->queryAll();
        
        $syncedCount = 0;
        $errorCount = 0;
        
        foreach ($students as $student) {
            try {
                // Call the stored procedure to sync individual student
                Yii::$app->db->createCommand("CALL sync_single_student(:student_id)")
                    ->bindValue(':student_id', $student['ID'])
                    ->execute();
                $syncedCount++;
            } catch (\Exception $e) {
                $errorCount++;
            }
        }
        
        // Set flash message
        if ($syncedCount > 0) {
            Yii::$app->session->setFlash('success', "Sync completed! $syncedCount student(s) synced to view_reports for this school. Errors: $errorCount");
        } else {
            Yii::$app->session->setFlash('warning', 'Sync completed but no students were found for this school.');
        }
        
        // Get school name for the redirect URL
        $schoolName = Yii::$app->db->createCommand("SELECT NAME FROM school WHERE ID = :school_id")
            ->bindValue(':school_id', $school_id)
            ->queryScalar();
        
        // Redirect to careerReport page with school name filter
        return $this->redirect([
            '/student/career-report', 
            'school' => $schoolName
        ]);
    }

    /**
     * Finds the Schoolresult model based on its primary key value.
     * @param integer $id
     * @return Schoolresult the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id) {
        if (($model = Schoolresult::findOne($id)) !== null) {
            return $model;
        } else {
            throw new NotFoundHttpException('The requested page does not exist.');
        }
    }

}