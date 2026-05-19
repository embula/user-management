<?php

namespace webvimark\modules\UserManagement\models\forms;

use webvimark\helpers\LittleBigHelper;
use webvimark\modules\UserManagement\models\User;
use webvimark\modules\UserManagement\UserManagementModule;
use yii\base\Model;
use Yii;

class LoginForm extends Model
{
	public ?string $username   = null;
	public ?string $password   = null;
	public bool    $rememberMe = false;

	private ?User $_user = null;

	public function rules(): array
	{
		return [
			[['username', 'password'], 'required'],
			['rememberMe', 'boolean'],
			['password', 'validatePassword'],
			['username', 'validateIP'],
		];
	}

	public function attributeLabels(): array
	{
		return [
			'username'   => UserManagementModule::t('front', 'Username'),
			'password'   => UserManagementModule::t('front', 'Password'),
			'rememberMe' => UserManagementModule::t('front', 'Remember me'),
		];
	}

	public function validatePassword(): void
	{
		if (!Yii::$app->getModule('user-management')->checkAttempts()) {
			$this->addError('password', UserManagementModule::t('front', 'Too many attempts'));
			return;
		}

		if (!$this->hasErrors()) {
			$user = $this->getUser();
			if (!$user || !$user->validatePassword($this->password)) {
				$this->addError('password', UserManagementModule::t('front', 'Incorrect username or password.'));
			}
		}
	}

	public function validateIP(): void
	{
		$user = $this->getUser();

		if ($user && $user->bind_to_ip) {
			$ips = array_map('trim', explode(',', $user->bind_to_ip));

			if (!in_array(LittleBigHelper::getRealIp(), $ips, true)) {
				$this->addError('password', UserManagementModule::t('front', 'You could not login from this IP'));
			}
		}
	}

	public function login(): bool
	{
		if ($this->validate()) {
			return Yii::$app->user->login($this->getUser(), $this->rememberMe ? Yii::$app->user->cookieLifetime : 0);
		}

		return false;
	}

	public function getUser(): ?User
	{
		if ($this->_user === null) {
			$identityClass    = Yii::$app->user->identityClass;
			$identityInstance = new $identityClass();
			$this->_user      = ($identityInstance instanceof User)
				? $identityInstance->findByUsername($this->username)
				: User::findByUsername($this->username);
		}

		return $this->_user;
	}
}
