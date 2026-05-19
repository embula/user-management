<?php

namespace webvimark\modules\UserManagement\controllers;

use webvimark\components\BaseController;
use webvimark\modules\UserManagement\components\UserAuthEvent;
use webvimark\modules\UserManagement\models\forms\ChangeOwnPasswordForm;
use webvimark\modules\UserManagement\models\forms\ConfirmEmailForm;
use webvimark\modules\UserManagement\models\forms\LoginForm;
use webvimark\modules\UserManagement\models\forms\PasswordRecoveryForm;
use webvimark\modules\UserManagement\models\User;
use webvimark\modules\UserManagement\UserManagementModule;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\widgets\ActiveForm;

class AuthController extends BaseController
{
	public array $freeAccessActions = ['login', 'logout', 'confirm-registration-email'];

	public function actions(): array
	{
		return [
			'captcha' => $this->module->captchaOptions,
		];
	}

	public function actionLogin(): Response|string
	{
		if (!Yii::$app->user->isGuest) {
			return $this->goHome();
		}

		$model = new LoginForm();

		if (Yii::$app->request->isAjax && $model->load(Yii::$app->request->post())) {
			Yii::$app->response->format = Response::FORMAT_JSON;
			return ActiveForm::validate($model);
		}

		if ($model->load(Yii::$app->request->post()) && $model->login()) {
			return $this->goBack();
		}

		return $this->renderIsAjax('login', compact('model'));
	}

	public function actionLogout(): Response
	{
		Yii::$app->user->logout();

		return $this->redirect(Yii::$app->homeUrl);
	}

	public function actionChangeOwnPassword(): Response|string
	{
		if (Yii::$app->user->isGuest) {
			return $this->goHome();
		}

		$user = User::getCurrentUser();

		if ($user->status != User::STATUS_ACTIVE) {
			throw new ForbiddenHttpException();
		}

		$model = new ChangeOwnPasswordForm(['user' => $user]);

		if (Yii::$app->request->isAjax && $model->load(Yii::$app->request->post())) {
			Yii::$app->response->format = Response::FORMAT_JSON;
			return ActiveForm::validate($model);
		}

		if ($model->load(Yii::$app->request->post()) && $model->changePassword()) {
			return $this->renderIsAjax('changeOwnPasswordSuccess');
		}

		return $this->renderIsAjax('changeOwnPassword', compact('model'));
	}

	public function actionRegistration(): Response|string
	{
		if (!Yii::$app->user->isGuest) {
			return $this->goHome();
		}

		$registrationClass = $this->module->registrationFormClass;
		$model = new $registrationClass();

		if (Yii::$app->request->isAjax && $model->load(Yii::$app->request->post())) {
			Yii::$app->response->format = Response::FORMAT_JSON;

			// Ajax validation breaks captcha — skip it in AJAX validate pass
			$validateAttributes = $model->attributes;
			unset($validateAttributes['captcha']);

			return ActiveForm::validate($model, $validateAttributes);
		}

		if ($model->load(Yii::$app->request->post()) && $model->validate()) {
			if ($this->triggerModuleEvent(UserAuthEvent::BEFORE_REGISTRATION, ['model' => $model])) {
				$user = $model->registerUser(false);

				if ($this->triggerModuleEvent(UserAuthEvent::AFTER_REGISTRATION, ['model' => $model, 'user' => $user])) {
					if ($user) {
						$module = Yii::$app->getModule('user-management');
						if ($module->useEmailAsLogin && $module->emailConfirmationRequired) {
							return $this->renderIsAjax('registrationWaitForEmailConfirmation', compact('user'));
						}

						foreach ((array)$this->module->rolesAfterRegistration as $role) {
							User::assignRole($user->id, $role);
						}

						Yii::$app->user->login($user);

						return $this->redirect(Yii::$app->user->returnUrl);
					}
				}
			}
		}

		return $this->renderIsAjax('registration', compact('model'));
	}

	public function actionConfirmRegistrationEmail(string $token): Response|string
	{
		$module = Yii::$app->getModule('user-management');

		if ($module->useEmailAsLogin && $module->emailConfirmationRequired) {
			$registrationClass = $this->module->registrationFormClass;
			$model = new $registrationClass();
			$user  = $model->checkConfirmationToken($token);

			if ($user) {
				return $this->renderIsAjax('confirmEmailSuccess', compact('user'));
			}

			throw new NotFoundHttpException(UserManagementModule::t('front', 'Token not found. It may be expired'));
		}

		return $this->goHome();
	}

	public function actionPasswordRecovery(): Response|string
	{
		if (!Yii::$app->user->isGuest) {
			return $this->goHome();
		}

		$model = new PasswordRecoveryForm();

		if (Yii::$app->request->isAjax && $model->load(Yii::$app->request->post())) {
			Yii::$app->response->format = Response::FORMAT_JSON;

			$validateAttributes = $model->attributes;
			unset($validateAttributes['captcha']);

			return ActiveForm::validate($model, $validateAttributes);
		}

		if ($model->load(Yii::$app->request->post()) && $model->validate()) {
			if ($this->triggerModuleEvent(UserAuthEvent::BEFORE_PASSWORD_RECOVERY_REQUEST, ['model' => $model])) {
				if ($model->sendEmail(false)) {
					if ($this->triggerModuleEvent(UserAuthEvent::AFTER_PASSWORD_RECOVERY_REQUEST, ['model' => $model])) {
						return $this->renderIsAjax('passwordRecoverySuccess');
					}
				} else {
					Yii::$app->session->setFlash('error', UserManagementModule::t('front', 'Unable to send message for email provided'));
				}
			}
		}

		return $this->renderIsAjax('passwordRecovery', compact('model'));
	}

	public function actionPasswordRecoveryReceive(string $token): Response|string
	{
		if (!Yii::$app->user->isGuest) {
			return $this->goHome();
		}

		$user = User::findByConfirmationToken($token);

		if (!$user) {
			throw new NotFoundHttpException(UserManagementModule::t('front', 'Token not found. It may be expired. Try reset password once more'));
		}

		$model = new ChangeOwnPasswordForm([
			'scenario' => 'restoreViaEmail',
			'user'     => $user,
		]);

		if ($model->load(Yii::$app->request->post()) && $model->validate()) {
			if ($this->triggerModuleEvent(UserAuthEvent::BEFORE_PASSWORD_RECOVERY_COMPLETE, ['model' => $model])) {
				$model->changePassword(false);

				if ($this->triggerModuleEvent(UserAuthEvent::AFTER_PASSWORD_RECOVERY_COMPLETE, ['model' => $model])) {
					return $this->renderIsAjax('changeOwnPasswordSuccess');
				}
			}
		}

		return $this->renderIsAjax('changeOwnPassword', compact('model'));
	}

	public function actionConfirmEmail(): Response|string
	{
		if (Yii::$app->user->isGuest) {
			return $this->goHome();
		}

		$user = User::getCurrentUser();

		if ($user->email_confirmed == 1) {
			return $this->renderIsAjax('confirmEmailSuccess', compact('user'));
		}

		$model = new ConfirmEmailForm([
			'email' => $user->email,
			'user'  => $user,
		]);

		if (Yii::$app->request->isAjax && $model->load(Yii::$app->request->post())) {
			Yii::$app->response->format = Response::FORMAT_JSON;
			return ActiveForm::validate($model);
		}

		if ($model->load(Yii::$app->request->post()) && $model->validate()) {
			if ($this->triggerModuleEvent(UserAuthEvent::BEFORE_EMAIL_CONFIRMATION_REQUEST, ['model' => $model])) {
				if ($model->sendEmail(false)) {
					if ($this->triggerModuleEvent(UserAuthEvent::AFTER_EMAIL_CONFIRMATION_REQUEST, ['model' => $model])) {
						return $this->refresh();
					}
				} else {
					Yii::$app->session->setFlash('error', UserManagementModule::t('front', 'Unable to send message for email provided'));
				}
			}
		}

		return $this->renderIsAjax('confirmEmail', compact('model'));
	}

	public function actionConfirmEmailReceive(string $token): Response|string
	{
		$user = User::findByConfirmationToken($token);

		if (!$user) {
			throw new NotFoundHttpException(UserManagementModule::t('front', 'Token not found. It may be expired'));
		}

		$user->email_confirmed = 1;
		$user->removeConfirmationToken();
		$user->save(false);

		return $this->renderIsAjax('confirmEmailSuccess', compact('user'));
	}

	protected function triggerModuleEvent(string $eventName, array $data = []): bool
	{
		$event = new UserAuthEvent($data);

		$this->module->trigger($eventName, $event);

		return $event->isValid;
	}
}
