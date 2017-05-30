<?php namespace pceuropa\forms\controllers;
#Copyright (c) 2016-2017 Rafal Marguzewicz pceuropa.net LTD
use Yii;
use yii\db\Query;
use yii\data\ActiveDataProvider;

use yii\web\Response;
use yii\web\NotFoundHttpException;

use yii\filters\VerbFilter;
use yii\helpers\Url;
use yii\helpers\Json;
use yii\helpers\ArrayHelper;

use pceuropa\forms\FormBase;
use pceuropa\forms\Form;
use pceuropa\forms\FormBuilder;
use pceuropa\forms\Module;
use pceuropa\forms\models\FormModel;
use pceuropa\forms\models\FormModelSearch;


/**
 * Example controller help to use all functions of formBuilder
 *
 * FormBuilder controller of module.
 * @author Rafal Marguzewicz <info@pceuropa.net>
 * @version 1.4.1
 * @license MIT
 *
 * https://github.com/pceuropa/yii2-forum
 * Please report all issues at GitHub
 * https://github.com/pceuropa/yii2-forum/issues
 *
 */
class ModuleController extends \yii\web\Controller {

    protected $list_action = ['create', 'update', 'delete', 'user'];

    /**
     * This method is invoked before any actions
     *
     * @param string $arg
     * @return void
     */
    public function behaviors() {
        return [
                   'access' => [
                       'class' => \yii\filters\AccessControl::className(),
                       'only' => ['user', 'create', 'update', 'delete', 'clone'],
                       'rules' => $this->module->rules
                   ],
                   'verbs' => [
                       'class' => VerbFilter::className(),
                       'actions' => [
                           'delete' => ['post'],
                       ],
                   ],
               ];
    }

    public function actionIndex() {
        $searchModel = new FormModelSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        return $this->render('index', [
                                 'searchModel' => $searchModel,
                                 'dataProvider' => $dataProvider,
                             ]);
    }

    public function actionUser() {
        $searchModel = new FormModelSearch();
        $searchModel->author    = (isset(Yii::$app->user->identity->id)) ? Yii::$app->user->identity->id : null;
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        return $this->render('user', [
                                 'searchModel' => $searchModel,
                                 'dataProvider' => $dataProvider,
                             ]);
    }


    public function actionView($url) {

        $form = FormModel::findModelByUrl($url);

        if (($data = Yii::$app->request->post('DynamicModel')) !== null) {

            foreach ($data as $i => $v) {
                if (is_array($data[$i])) $data[$i] = join(',', $data[$i]);
            }

            $query = (new Query)->createCommand()->insert($this->module->formDataTable.$form->form_id, $data);

            if ($query->execute()) {
                $form->updateCounters(['answer' => 1 ]);
                Yii::$app->session->setFlash('success', Yii::t('app', 'Registration successfully completed'));
                Form::sendEmail($form, $data, 'info@pceuropa.eu', 'subject');



            } else {
                Yii::$app->session->setFlash('error', Yii::t('app', 'An confirmation email was not sent'));
            }

            return $this->redirect(['index']);
        } else {
            return $this->render('view', [ 'form' => $form->body] );
        }
    }


    public function actionList($id) {

        $form = FormModel::findModel($id);
        $form = Json::decode($form->body);
        $form = FormBase::onlyCorrectDataFields($form);

        $dataProvider = new ActiveDataProvider([
                'query' => (new Query)->from( $this->module->formDataTable.$id ),
                'db' => $this->module->db
                                               ]);

        return $this->render('list', [
                                 'dataProvider' => $dataProvider,
                                 'only_data_fields' => ArrayHelper::getColumn($form, 'name')
                             ]);
    }

/**
 * Create Form action
 * @throws yii\base\InvalidParamException
 * @return string
 */
    public function actionCreate() {
        $r = Yii::$app->request;

        if ($r->isAjax) {
            $form = new FormBuilder([
                                        'formTable' => $this->module->formTable,
                                        'formDataTable' => $this->module->formDataTable,
                                        'formData' => $r->post()
                                    ]);
            $form->save();
            $form->createTable();
            return $form->response();
        } else {
            return $this->render('create');
        }
    }


/**
 * Create Form action
 * @throws yii\base\InvalidParamException
 * @return string
 */
    public function actionUpdate($id) {
        $form = new FormBuilder([
                                    'formTable' => $this->module->formTable,
                                    'formDataTable' => $this->module->formDataTable,
                                ]);

        $form->findModel($id);
        $r = Yii::$app->request;

        if ($r->isAjax) {
            \Yii::$app->response->format = 'json';

            switch (true) {
                case $r->isGet:
                    return $form->model;
                case $r->post('body'):
                    $form->load($r->post());
                    $form->save();
                case $r->post('add'):
                    $form->addColumn($r->post('add'));
                    break;
                case $r->post('delete'):
                    $form->dropColumn($r->post('delete'));
                    break;
                case $r->post('change'):
                    $form->renameColumn($r->post('change'));
                    break;
                default:
                    return ['success' => false];
            }

            return ['success' => $form->success];
        } else {
            return $this->render('update', ['id' => $id]);
        }
    }

/**
 * Create Form action
 * @throws yii\base\InvalidParamException
 * @return void
 */
    public function actionClone($id) {

        $form = FormModel::find()->select(['body', 'title', 'author', 'date_start', 'date_start', 'maximum', 'meta_title', 'url', 'response'])->where(['form_id' => $id])->one();
        $form->answer = 0;
        $this->uniqueUrl($form);

        $db = Yii::$app-> {$this->module->db};
        $db->createCommand()->insert( $this->module->formTable , $form)->execute();

        $last_id = $db->getLastInsertID();
        $schema = FormBuilder::tableSchema($form->body);

        $db->createCommand()->createTable($this->module->formDataTable.$last_id, $schema, 'CHARACTER SET utf8 COLLATE utf8_general_ci')->execute();

        $this->redirect(['user']);
    }

    public function actionDelete($id) {
        $form = new FormBuilder();
        $form = $form->model->findModel($id);
        $form->delete();
        return $this->redirect(['user']);
    }

    /**
     * Unique URL
     * @param $array form
     * @return void
     */
    public function uniqueUrl($form) {
        do {
            $form->url = $form->url.'_2';
            $count = FormModel::find()->select(['url'])->where(['url' => $form->url])->count();
        } while ($count > 0);
    }
}
















