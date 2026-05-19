<?php

namespace webvimark\modules\UserManagement\models\forms;

use webvimark\modules\UserManagement\models\User;
use webvimark\modules\UserManagement\UserManagementModule;
use yii\base\Model;
use Yii;

class ChangeOwnPasswordForm extends Model
{
	public ?User   $user             = null;
	public ?string $current_password = null;
	public ?string $password         = null;
	public ?string $repeat_password  = null;

	public function rules(): array
	{
		return [
			[['password', 'repeat_password'], 'required'],
			[['password', 'repeat_password', 'current_password'], 'string', 'max' => 255],
			[['password', 'repeat_password', 'current_password'], 'trim'],
			['password', 'match', 'pattern' => Yii::$app->getModule('user-management')->passwordRegexp],

			['repeat_password', 'compare', 'compareAttribute' => 'password'],

			['current_password', 'required', 'except' => 'restoreViaEmail'],
			['current_password', 'validateCurrentPassword', 'except' => 'restoreViaEmail'],
		];
	}

	public function attributeLabels(): array
	{
		return [
			'current_password' => UserManagementModule::t('back', 'Current password'),
			'password'         => UserManagementModule::t('front', 'Password'),
			'repeat_password'  => UserManagementModule::t('front', 'Repeat password'),
		];
	}

	public function validateCurrentPassword(): void
	{
		if (!Yii::$app->getModule('user-management')->checkAttempts()) {
			$this->addError('current_password', UserManagementModule::t('back', 'Too many attempts'));
			return;
		}

		if (!Yii::$app->security->validatePassword($this->current_password, $this->user->password_hash)) {
			$this->addError('current_password', UserManagementModule::t('back', 'Wrong password'));
		}
	}

	public function changePassword(bool $performValidation = true): bool
	{
		if ($performValidation && !$this->validate()) {
			return false;
		}

		$this->user->password = $this->password;
		$this->user->removeConfirmationToken();

		return $this->user->save();
	}
}
