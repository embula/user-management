<?php

namespace webvimark\modules\UserManagement\components;

use webvimark\modules\UserManagement\models\rbacDB\Route;
use webvimark\modules\UserManagement\models\User;
use yii\base\Action;
use Yii;
use yii\base\ActionFilter;
use yii\web\ForbiddenHttpException;

class GhostAccessControl extends ActionFilter
{
	/** @var callable|null */
	public $denyCallback = null;

	public function beforeAction($action): bool
	{
		if ($action->id === 'captcha') {
			return true;
		}

		$route = '/' . $action->uniqueId;

		if (Route::isFreeAccess($route, $action)) {
			return true;
		}

		if (Yii::$app->user->isGuest) {
			$this->denyAccess();
		}

		// If user record was deleted but session still exists, destroy and redirect
		if (!Yii::$app->user->isGuest && Yii::$app->user->identity === null) {
			Yii::$app->getSession()->destroy();
			$this->denyAccess();
		}

		if (Yii::$app->user->isSuperadmin) {
			return true;
		}

		if (Yii::$app->user->identity && Yii::$app->user->identity->status != User::STATUS_ACTIVE) {
			Yii::$app->user->logout();
			Yii::$app->getResponse()->redirect(Yii::$app->getHomeUrl());
		}

		if (User::canRoute($route)) {
			return true;
		}

		if (isset($this->denyCallback)) {
			call_user_func($this->denyCallback, null, $action);
		} else {
			$this->denyAccess();
		}

		return false;
	}

	protected function denyAccess(): void
	{
		if (Yii::$app->user->getIsGuest()) {
			$accept = Yii::$app->request->headers->get('Accept') ?? '';
			$isIE   = str_contains($_SERVER['HTTP_USER_AGENT'] ?? '', 'MSIE');

			if ($isIE) {
				Yii::$app->user->loginRequired(
					Yii::$app->request->isAjax,
					str_contains($accept, 'html')
				);
			} else {
				Yii::$app->user->loginRequired();
			}
		} else {
			throw new ForbiddenHttpException(Yii::t('yii', 'You are not allowed to perform this action.'));
		}
	}
}
