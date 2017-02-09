<?php

namespace app\controllers;

use Yii;
use yii\filters\AccessControl;
use yii\filters\ContentNegotiator;
use yii\web\Controller;
use yii\filters\VerbFilter;
use app\models\LoginForm;
use app\models\ContactForm;
use yii\web\Response;

class SiteController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['logout'],
                'rules' => [
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
            'contentNegotiator' => [
                'class' => ContentNegotiator::className(),
                'formats' => [
                    'application/json' => Response::FORMAT_JSON
                ]
            ]
        ];
    }

    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    public function actionIndex()
    {
        return $this->render('index');
    }

    public function actionLogin()
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $model = new LoginForm();
        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->goBack();
        }
        return $this->render('login', [
            'model' => $model,
        ]);
    }

    public function actionLogout()
    {
        Yii::$app->user->logout();

        return $this->goHome();
    }

    public function actionContact()
    {
        $model = new ContactForm();
        if ($model->load(Yii::$app->request->post()) && $model->contact(Yii::$app->params['adminEmail'])) {
            Yii::$app->session->setFlash('contactFormSubmitted');

            return $this->refresh();
        }
        return $this->render('contact', [
            'model' => $model,
        ]);
    }

    public function actionAbout()
    {
        return $this->render('about');
    }

    public function actionSay(){
        return array('test' => 1,'hello' => 2);
    }

    public function actionExportdata(){
        $params = Yii::$app->params;
        $url    = $params['yms']['customerContract']['list'];
        $body = array(
            'query' => array(
                'filtered' => array(
                    'filter' => array(
                        'terms' => array(
                            'status' => array(3,10)
                        )
                    ),
                    'query' => array(
                        'bool' => array(
                            'must' => array(
                                'range' => array(
                                    'actualEndDate' => array(
                                        'gte' => '2017-01-19',
                                        'lte' => date('Y-m-d',strtotime('-1 days'))
                                    )
                                )
                            )
                        )
                    )
                )
            )
        );
        \Unirest\Request::jsonOpts(true);
        $response = \Unirest\Request::post($url,array('Content-Type' => 'application/json'),json_encode($body));
        $roomUrl = $params['yms']['room']['detail'];
        $exportData = array();
        foreach ($response->body['hits']['hits'] as $key => $value){
            $newRoomUrl = str_replace('{roomId}',$value['_source']['roomId'],$roomUrl);
            $res = \Unirest\Request::get($newRoomUrl);
            if($res->body['found'] == true){
                if($res->body['_source']['roomRealStep'] == 3){
                    $exportData[$value['_source']['roomId']] = array(
                        'propertyID'   => $res->body['_source']['propertyID'],
                        'roomStep'     => $res->body['_source']['roomStep'],
                        'roomRealStep' => $res->body['_source']['roomRealStep'],
                        'roomNO'       => $value['_source']['roomNumber']
                    );
                }
            }
        }
        \moonland\phpexcel\Excel::export(
            array(
                'models'  => $exportData,
                'fileName' => time(),
//                'asArray' => true,
                'format' => 'Excel5',
                'columns' => ['propertyID','roomStep','roomRealStep','roomNO'],
                'headers' => [
                    'propertyID' => '物业id',
                    'roomStep'   => '客房状态',
                    'roomRealStep' => '客房实际状态',
                    'roomNO'      => '客房编号'
                ]
            )

        );
    }
}
