<?php

namespace webvimark\modules\UserManagement\components;

use yii\web\User;
use Yii;

class UserConfig extends User
{
	public $identityClass = 'webvimark\modules\UserManagement\models\User';

	public $enableAutoLogin = true;

	public $cookieLifetime = 2592000;

	public $loginUrl = ['/user-management/auth/login'];

	/**
	 * Allows Yii::$app->user->isSuperadmin
	 */
	public function getIsSuperadmin(): bool
	{
		return Yii::$app->user->identity?->superadmin == 1;
	}

	public function getUsername(): ?string
	{
		return Yii::$app->user->identity?->username;
	}

	protected function afterLogin($identity, $cookieBased, $duration): void
	{
		AuthHelper::updatePermissions($identity);

		parent::afterLogin($identity, $cookieBased, $duration);
	}
}
