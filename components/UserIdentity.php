<?php

namespace webvimark\modules\UserManagement\components;

use webvimark\modules\UserManagement\models\User;
use yii\base\Security;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;
use Yii;

/**
 * @property integer $id
 * @property string  $username
 * @property string  $auth_key
 * @property string  $password_hash
 * @property string  $confirmation_token
 * @property integer $status
 * @property integer $superadmin
 * @property integer $created_at
 * @property integer $updated_at
 */
#[\AllowDynamicProperties]
abstract class UserIdentity extends ActiveRecord implements IdentityInterface
{
	public static function findIdentity($id): ?static
	{
		return static::findOne($id);
	}

	public static function findIdentityByAccessToken($token, $type = null): ?static
	{
		return static::findOne(['auth_key' => $token, 'status' => User::STATUS_ACTIVE]);
	}

	public static function findByUsername(string $username): ?static
	{
		return static::findOne(['username' => $username, 'status' => User::STATUS_ACTIVE]);
	}

	public static function findByConfirmationToken(string $token): static|null
	{
		$expire    = Yii::$app->getModule('user-management')->confirmationTokenExpire;
		$parts     = explode('_', $token);
		$timestamp = (int)end($parts);

		if ($timestamp + $expire < time()) {
			return null;
		}

		return static::findOne([
			'confirmation_token' => $token,
			'status'             => User::STATUS_ACTIVE,
		]);
	}

	public static function findInactiveByConfirmationToken(string $token): static|null
	{
		$expire    = Yii::$app->getModule('user-management')->confirmationTokenExpire;
		$parts     = explode('_', $token);
		$timestamp = (int)end($parts);

		if ($timestamp + $expire < time()) {
			return null;
		}

		return static::findOne([
			'confirmation_token' => $token,
			'status'             => User::STATUS_INACTIVE,
		]);
	}

	public function getId(): mixed
	{
		return $this->getPrimaryKey();
	}

	public function getAuthKey(): ?string
	{
		return $this->auth_key;
	}

	public function validateAuthKey($authKey): bool
	{
		return $this->getAuthKey() === $authKey;
	}

	public function validatePassword(string $password): bool
	{
		return Yii::$app->security->validatePassword($password, $this->password_hash);
	}

	public function setPassword(string $password): void
	{
		if (php_sapi_name() === 'cli') {
			$this->password_hash = (new Security())->generatePasswordHash($password);
		} else {
			$this->password_hash = Yii::$app->security->generatePasswordHash($password);
		}
	}

	public function generateAuthKey(): void
	{
		if (php_sapi_name() === 'cli') {
			$this->auth_key = (new Security())->generateRandomString();
		} else {
			$this->auth_key = Yii::$app->security->generateRandomString();
		}
	}

	public function generateConfirmationToken(): void
	{
		$this->confirmation_token = Yii::$app->security->generateRandomString() . '_' . time();
	}

	public function removeConfirmationToken(): void
	{
		$this->confirmation_token = null;
	}
}
