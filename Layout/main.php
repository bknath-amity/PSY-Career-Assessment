<?php
/* @var $this \yii\web\View */
/* @var $content string */

use backend\assets\AppAsset;
use yii\helpers\Html;
use yii\bootstrap\Nav;
use yii\bootstrap\NavBar;
use yii\widgets\Breadcrumbs;
use common\widgets\Alert;

AppAsset::register($this);
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>">
    <head>
        <meta charset="<?= Yii::$app->charset ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?= Html::csrfMetaTags() ?>
        <title><?= Html::encode($this->title) ?></title>
        <?php $this->head() ?>
        <style>
            .nav > li > a {
    padding: 10px 12px;
}
        </style>
    </head>
    <body>
        <?php $this->beginBody() ?>

        <div class="wrap">
            <?php
            NavBar::begin([
                'brandLabel' => 'CAREER ASSESSMENT EXAM',
                'brandUrl' => Yii::$app->homeUrl,
                'options' => [
                    'class' => 'navbar-inverse navbar-fixed-top',
                ],
            ]);
            $menuItems = [
                ['label' => 'Home', 'url' => ['/site/index']],
            ];
            if (Yii::$app->user->isGuest) {
                $menuItems[] = ['label' => 'Login', 'url' => ['/site/login']];
            } else {
                $exp = explode(',', Yii::$app->user->identity->school);
                count($exp);
                if (Yii::$app->user->identity->school == 1111) {
                    $menuItems[] = ['label' => 'School', 'url' => ['/school']];
                    $menuItems[] = ['label' => 'School Result', 'url' => ['/schoolresult']];
                    $menuItems[] = ['label' => 'Classes', 'url' => ['/classes']];
                    $menuItems[] = ['label' => 'Section', 'url' => ['/section']];
                    $menuItems[] = ['label' => 'Student', 'url' => ['#'], 'items'=>[
                            ['label' => 'Student Details', 'url' => ['/student']],
                            ['label' => 'Career Guidance Report', 'url' => ['/student/career-report']]  
                    ]];
                    $menuItems[] = ['label' => 'Exam', 'url' => ['/exam']];
                    $menuItems[] = ['label' => 'Question', 'url' => ['/question']];
                    $menuItems[] = ['label' => 'Stream', 'url' => ['/streamselction']];
                    $menuItems[] = ['label' => 'Report', 'url' => ['#'], 'items' => [
                            ['label' => 'MBTI', 'url' => ['/mbtireport']],
                            ['label' => 'CIS Intrest Area', 'url' => ['/cisintrestarea']],
                            ['label' => 'CIS STEN Area', 'url' => ['/cisstenarea']],
                            ['label' => 'DBDA Area', 'url' => ['/dbdaarea']],
                            ['label' => 'DBDA Score Details', 'url' => ['/dbdascoredetial']],
                    ]];
                } else if (count($exp) > 1) {
                    $menuItems[] = ['label' => 'Stream', 'url' => ['/streamselction']];
                    $menuItems[] = ['label' => 'Student', 'url' => ['/student/index']];
                } else {
                    $menuItems[] = ['label' => 'Stream', 'url' => ['/streamselction']];
                    $menuItems[] = ['label' => 'Student', 'url' => ['/student/result']];
                }
                $menuItems[] = '<li>'
                        . Html::beginForm(['/site/logout'], 'post')
                        . Html::submitButton(
                                'Logout (' . Yii::$app->user->identity->username . ')', ['class' => 'btn btn-link logout']
                        )
                        . Html::endForm()
                        . '</li>';
            }
            echo Nav::widget([
                'options' => ['class' => 'navbar-nav navbar-right'],
                'items' => $menuItems,
            ]);
            NavBar::end();
            //echo Yii::$app->user->identity->school;
            ?>

            <div class="container">
                <?=
                Breadcrumbs::widget([
                    'links' => isset($this->params['breadcrumbs']) ? $this->params['breadcrumbs'] : [],
                ])
                ?>
                <?= Alert::widget() ?>
                <?= $content ?>
            </div>
        </div>

        <footer class="footer">
            <div class="container">
                <p class="pull-left">&copy; My Company <?= date('Y') ?></p>

                <p class="pull-right"><?= Yii::powered() ?></p>
            </div>
        </footer>

        <?php $this->endBody() ?>
    </body>
</html>
<?php $this->endPage() ?>