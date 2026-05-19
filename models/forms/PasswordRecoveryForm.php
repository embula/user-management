<?php

namespace webvimark\modules\UserManagement\models\forms;

use webvimark\modules\UserManagement\models\User;
use webvimark\modules\UserManagement\UserManagementModule;
use yii\base\Model;
use Yii;

class PasswordRecoveryForm extends Model
{
	protected ?User $user = null;

	public ?string $email   = null;
	public ?string $captcha = null;

	public function rules(): array
	{
		return [
			['captcha', 'captcha', 'captchaAction' => '/user-management/auth/captcha'],

			[['email', 'captcha'], 'required'],
			['email', 'trim'],
			['email', 'email'],

			['email', 'validateEmailConfirmedAndUserActive'],
		];
	}

	public function validateEmailConfirmedAndUserActive(): void
	{
		if (!Yii::$app->getModule('user-management')->checkAttempts()) {
			$this->addError('email', UserManagementModule::t('front', 'Too many attempts'));
			return;
		}

		$user = User::findOne([
			'email'           => $this->email,
			'email_confirmed' => 1,
			'status'          => User::STATUS_ACTIVE,
		]);

		if ($user) {
			$this->user = $user;
		} else {
			$this->addError('email', UserManagementModule::t('front', 'E-mail is invalid'));
		}
	}

	public function attributeLabels(): array
	{
		return [
			'email'   => 'E-mail',
			'captcha' => UserManagementModule::t('front', 'Captcha'),
		];
	}

	public function sendEmail(bool $performValidation = true): bool
	{
		if ($performValidation && !$this->validate()) {
			return false;
		}

		$this->user->generateConfirmationToken();
		$this->user->save(false);

		$module = Yii::$app->getModule('user-management');

		return Yii::$app->mailer
			->compose($module->mailerOptions['passwordRecoveryFormViewFile'], ['user' => $this->user])
			->setFrom($module->mailerOptions['from'])
			->setTo($this->email)
			->setSubject(UserManagementModule::t('front', 'Password reset for') . ' ' . Yii::$app->name)
			->send();
	}
}
