<?php

use backend\models\Schoolresult;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\grid\ActionColumn;
use yii\grid\GridView;
use yii\widgets\Pjax;
use yii\helpers\ArrayHelper;
use backend\models\School;

/** @var yii\web\View $this */
/** @var backend\models\SchoolresultSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = Yii::t('app', 'Schoolresults');
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="schoolresult-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a(Yii::t('app', 'Create Schoolresult'), ['create'], ['class' => 'btn btn-success']) ?>
    </p>

    <?php Pjax::begin(); ?>
    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

            [
                'label' => 'School',
                'attribute' => 'SCHOOL_ID',
                'value' => function($model) {
                    // Manually fetch the school name
                    $school = \backend\models\School::findOne($model->SCHOOL_ID);
                    return $school ? $school->NAME : $model->SCHOOL_ID;
                },
                'filter' => ArrayHelper::map(School::find()->all(), 'ID', 'NAME')
            ],
            'RESULT_PUBLISH:boolean',
            [
                'class' => ActionColumn::className(),
                'template' => '{view} {update} {delete} {sync}',
                'urlCreator' => function ($action, Schoolresult $model, $key, $index, $column) {
                    return Url::toRoute([$action, 'id' => $model->ID]);
                },
                'buttons' => [
                    'sync' => function ($url, $model) {
                        // This button will POST to the sync action
                        return Html::a('Sync to Report', ['sync', 'id' => $model->ID], [
                            'class' => 'btn btn-info btn-sm',
                            'title' => 'Sync student data for this school to Career Report',
                            'data' => [
                                'confirm' => "Are you sure you want to sync all students from this school to the Career Report? This will populate the report data.",
                                'method' => 'post',
                                        ],
                                ]);
                            },
                    ],
            ],
        ],
    ]); ?>

    <?php Pjax::end(); ?>

</div>