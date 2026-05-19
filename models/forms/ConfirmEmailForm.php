<?php

namespace webvimark\modules\UserManagement\models\forms;

use webvimark\modules\UserManagement\models\User;
use webvimark\modules\UserManagement\UserManagementModule;
use yii\base\Model;
use Yii;

class ConfirmEmailForm extends Model
{
	public ?User   $user  = null;
	public ?string $email = null;

	public function init(): void
	{
		parent::init();

		if ($this->user?->confirmation_token !== null && $this->getTokenTimeLeft() === 0) {
			$this->user->removeConfirmationToken();
			$this->user->save(false);
		}
	}

	public function rules(): array
	{
		return [
			['email', 'required'],
			['email', 'trim'],
			['email', 'email'],
			['email', 'validateEmailConfirmedUnique'],
		];
	}

	public function validateEmailConfirmedUnique(): void
	{
		if ($this->email) {
			$exists = User::findOne(['email' => $this->email, 'email_confirmed' => 1]);

			if ($exists) {
				$this->addError('email', UserManagementModule::t('front', 'This E-mail already exists'));
			}
		}
	}

	public function attributeLabels(): array
	{
		return [
			'email' => UserManagementModule::t('front', 'E-mail'),
		];
	}

	public function getTokenTimeLeft(bool $inMinutes = false): int
	{
		if ($this->user && $this->user->confirmation_token) {
			$expire    = Yii::$app->getModule('user-management')->confirmationTokenExpire;
			$parts     = explode('_', $this->user->confirmation_token);
			$timestamp = (int)end($parts);
			$timeLeft  = $timestamp + $expire - time();

			if ($timeLeft < 0) {
				return 0;
			}

			return $inMinutes ? (int)round($timeLeft / 60) : $timeLeft;
		}

		return 0;
	}

	public function sendEmail(bool $performValidation = true): bool
	{
		if ($performValidation && !$this->validate()) {
			return false;
		}

		$this->user->email = $this->email;
		$this->user->generateConfirmationToken();
		$this->user->save(false);

		$module = Yii::$app->getModule('user-management');

		return Yii::$app->mailer
			->compose($module->mailerOptions['confirmEmailFormViewFile'], ['user' => $this->user])
			->setFrom($module->mailerOptions['from'])
			->setTo($this->email)
			->setSubject(UserManagementModule::t('front', 'E-mail confirmation for') . ' ' . Yii::$app->name)
			->send();
	}
}
