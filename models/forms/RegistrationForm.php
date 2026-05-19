<?php

namespace webvimark\modules\UserManagement\models\forms;

use webvimark\modules\UserManagement\models\User;
use webvimark\modules\UserManagement\UserManagementModule;
use yii\base\Model;
use Yii;
use yii\helpers\Html;

class RegistrationForm extends Model
{
	public ?string $username        = null;
	public ?string $password        = null;
	public ?string $repeat_password = null;
	public ?string $captcha         = null;

	public function rules(): array
	{
		$rules = [
			['captcha', 'captcha', 'captchaAction' => '/user-management/auth/captcha'],

			[['username', 'password', 'repeat_password', 'captcha'], 'required'],
			[['username', 'password', 'repeat_password'], 'trim'],

			['username', 'unique',
				'targetClass'     => 'webvimark\modules\UserManagement\models\User',
				'targetAttribute' => 'username',
			],

			['username', 'purgeXSS'],

			['password', 'string', 'max' => 255],
			['password', 'match', 'pattern' => Yii::$app->getModule('user-management')->passwordRegexp],

			['repeat_password', 'compare', 'compareAttribute' => 'password'],
		];

		if (Yii::$app->getModule('user-management')->useEmailAsLogin) {
			$rules[] = ['username', 'email'];
		} else {
			$rules[] = ['username', 'string', 'max' => 50];
			$rules[] = ['username', 'match', 'pattern' => Yii::$app->getModule('user-management')->registrationRegexp];
			$rules[] = ['username', 'match', 'not' => true, 'pattern' => Yii::$app->getModule('user-management')->registrationBlackRegexp];
		}

		return $rules;
	}

	public function purgeXSS(string $attribute): void
	{
		$this->$attribute = Html::encode($this->$attribute);
	}

	public function attributeLabels(): array
	{
		return [
			'username'        => Yii::$app->getModule('user-management')->useEmailAsLogin ? 'E-mail' : UserManagementModule::t('front', 'Login'),
			'password'        => UserManagementModule::t('front', 'Password'),
			'repeat_password' => UserManagementModule::t('front', 'Repeat password'),
			'captcha'         => UserManagementModule::t('front', 'Captcha'),
		];
	}

	public function registerUser(bool $performValidation = true): User|false
	{
		if ($performValidation && !$this->validate()) {
			return false;
		}

		$user           = new User();
		$user->password = $this->password;

		$module = Yii::$app->getModule('user-management');

		if ($module->useEmailAsLogin) {
			$user->email = $this->username;

			if ($module->emailConfirmationRequired) {
				$user->status = User::STATUS_INACTIVE;
				$user->generateConfirmationToken();
				$user->save(false);

				$this->saveProfile($user);

				if ($this->sendConfirmationEmail($user)) {
					return $user;
				}

				$this->addError('username', UserManagementModule::t('front', 'Could not send confirmation email'));
				return false;
			}

			$user->username = $this->username;
		} else {
			$user->username = $this->username;
		}

		if ($user->save()) {
			$this->saveProfile($user);
			return $user;
		}

		$this->addError('username', UserManagementModule::t('front', 'Login has been taken'));
		return false;
	}

	protected function saveProfile(User $user): void
	{
		// Override in your own RegistrationForm subclass to save a profile model
	}

	protected function sendConfirmationEmail(User $user): bool
	{
		$module = Yii::$app->getModule('user-management');

		return Yii::$app->mailer
			->compose($module->mailerOptions['registrationFormViewFile'], ['user' => $user])
			->setFrom($module->mailerOptions['from'])
			->setTo($user->email)
			->setSubject(UserManagementModule::t('front', 'E-mail confirmation for') . ' ' . Yii::$app->name)
			->send();
	}

	public function checkConfirmationToken(string $token): User|false
	{
		$user = User::findInactiveByConfirmationToken($token);

		if ($user) {
			$user->username       = $user->email;
			$user->status         = User::STATUS_ACTIVE;
			$user->email_confirmed = 1;
			$user->removeConfirmationToken();
			$user->save(false);

			foreach ((array)Yii::$app->getModule('user-management')->rolesAfterRegistration as $role) {
				User::assignRole($user->id, $role);
			}

			Yii::$app->user->login($user);

			return $user;
		}

		return false;
	}
}
