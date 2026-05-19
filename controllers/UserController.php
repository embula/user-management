<?php

namespace webvimark\modules\UserManagement\controllers;

use webvimark\components\AdminDefaultController;
use Yii;
use webvimark\modules\UserManagement\models\User;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class UserController extends AdminDefaultController
{
	public $modelClass       = 'webvimark\modules\UserManagement\models\User';
	public $modelSearchClass = 'webvimark\modules\UserManagement\models\search\UserSearch';

	public function actionCreate(): Response|string
	{
		$model = new User(['scenario' => 'newUser']);

		if ($model->load(Yii::$app->request->post()) && $model->save()) {
			return $this->redirect(['view', 'id' => $model->id]);
		}

		return $this->renderIsAjax('create', compact('model'));
	}

	public function actionChangePassword(int $id): Response|string
	{
		$model = User::findOne($id);

		if (!$model) {
			throw new NotFoundHttpException('User not found');
		}

		$model->scenario = 'changePassword';

		if ($model->load(Yii::$app->request->post()) && $model->save()) {
			return $this->redirect(['view', 'id' => $model->id]);
		}

		return $this->renderIsAjax('changePassword', compact('model'));
	}
}
