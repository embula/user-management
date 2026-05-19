<?php

namespace webvimark\modules\UserManagement\models;

use webvimark\helpers\LittleBigHelper;
use webvimark\helpers\Singleton;
use webvimark\modules\UserManagement\components\AuthHelper;
use webvimark\modules\UserManagement\components\UserIdentity;
use webvimark\modules\UserManagement\models\rbacDB\Role;
use webvimark\modules\UserManagement\models\rbacDB\Route;
use webvimark\modules\UserManagement\UserManagementModule;
use Yii;
use yii\behaviors\TimestampBehavior;

/**
 * @property integer $id
 * @property string $username
 * @property string $email
 * @property integer $email_confirmed
 * @property string $auth_key
 * @property string $password_hash
 * @property string $confirmation_token
 * @property string $bind_to_ip
 * @property string $registration_ip
 * @property integer $status
 * @property integer $superadmin
 * @property integer $created_at
 * @property integer $updated_at
 */
#[\AllowDynamicProperties]
class User extends UserIdentity
{
	const STATUS_ACTIVE = 1;
	const STATUS_INACTIVE = 0;
	const STATUS_BANNED = -1;

	public string $gridRoleSearch = '';

	public ?string $password = null;

	public ?string $repeat_password = null;

	/**
	 * Store result in singleton to prevent multiple db requests with multiple calls
	 */
	public static function getCurrentUser(bool $fromSingleton = true): ?static
	{
		if (!$fromSingleton) {
			return static::findOne(Yii::$app->user->id);
		}

		$user = Singleton::getData('__currentUser');

		if (!$user) {
			$user = static::findOne(Yii::$app->user->id);
			Singleton::setData('__currentUser', $user);
		}

		return $user;
	}

	/**
	 * Assign role to user
	 */
	public static function assignRole(int $userId, string $roleName): bool
	{
		try {
			Yii::$app->db->createCommand()
				->insert(Yii::$app->getModule('user-management')->auth_assignment_table, [
					'user_id'   => $userId,
					'item_name' => $roleName,
					'created_at' => time(),
				])->execute();

			AuthHelper::invalidatePermissions();

			return true;
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * Revoke role from user
	 */
	public static function revokeRole(int $userId, string $roleName): bool
	{
		$result = Yii::$app->db->createCommand()
			->delete(
				Yii::$app->getModule('user-management')->auth_assignment_table,
				['user_id' => $userId, 'item_name' => $roleName]
			)->execute() > 0;

		if ($result) {
			AuthHelper::invalidatePermissions();
		}

		return $result;
	}

	/**
	 * @param string|array $roles
	 */
	public static function hasRole(string|array $roles, bool $superAdminAllowed = true): bool
	{
		if ($superAdminAllowed && Yii::$app->user->isSuperadmin) {
			return true;
		}

		$roles = (array)$roles;

		AuthHelper::ensurePermissionsUpToDate();

		return array_intersect($roles, Yii::$app->session->get(AuthHelper::SESSION_PREFIX_ROLES, [])) !== [];
	}

	public static function hasPermission(string $permission, bool $superAdminAllowed = true): bool
	{
		if ($superAdminAllowed && Yii::$app->user->isSuperadmin) {
			return true;
		}

		AuthHelper::ensurePermissionsUpToDate();

		return in_array($permission, Yii::$app->session->get(AuthHelper::SESSION_PREFIX_PERMISSIONS, []));
	}

	/**
	 * Useful for Menu widget
	 *
	 * <example>
	 *     ['label'=>'Some label', 'url'=>['/site/index'], 'visible'=>User::canRoute(['/site/index'])]
	 * </example>
	 *
	 * @param string|array $route
	 */
	public static function canRoute(string|array $route, bool $superAdminAllowed = true): bool
	{
		if ($superAdminAllowed && Yii::$app->user->isSuperadmin) {
			return true;
		}

		$baseRoute = AuthHelper::unifyRoute($route);

		if (Route::isFreeAccess($baseRoute)) {
			return true;
		}

		AuthHelper::ensurePermissionsUpToDate();

		return Route::isRouteAllowed($baseRoute, Yii::$app->session->get(AuthHelper::SESSION_PREFIX_ROUTES, []));
	}

	public static function getStatusList(): array
	{
		return [
			self::STATUS_ACTIVE   => UserManagementModule::t('back', 'Active'),
			self::STATUS_INACTIVE => UserManagementModule::t('back', 'Inactive'),
			self::STATUS_BANNED   => UserManagementModule::t('back', 'Banned'),
		];
	}

	public static function getStatusValue(string|int $val): string
	{
		$ar = self::getStatusList();

		return $ar[$val] ?? (string)$val;
	}

	public static function tableName(): string
	{
		return Yii::$app->getModule('user-management')->user_table;
	}

	public function behaviors(): array
	{
		return [
			TimestampBehavior::class,
		];
	}

	public function rules(): array
	{
		return [
			['username', 'required'],
			['username', 'unique'],
			['username', 'trim'],

			[['status', 'email_confirmed'], 'integer'],

			['email', 'email'],
			['email', 'validateEmailConfirmedUnique'],

			['bind_to_ip', 'validateBindToIp'],
			['bind_to_ip', 'trim'],
			['bind_to_ip', 'string', 'max' => 255],

			['password', 'required', 'on' => ['newUser', 'changePassword']],
			['password', 'string', 'max' => 255, 'on' => ['newUser', 'changePassword']],
			['password', 'trim', 'on' => ['newUser', 'changePassword']],
			['password', 'match', 'pattern' => Yii::$app->getModule('user-management')->passwordRegexp],

			['repeat_password', 'required', 'on' => ['newUser', 'changePassword']],
			['repeat_password', 'compare', 'compareAttribute' => 'password'],
		];
	}

	public function validateEmailConfirmedUnique(): void
	{
		if ($this->email) {
			$exists = User::findOne([
				'email'           => $this->email,
				'email_confirmed' => 1,
			]);

			if ($exists && $exists->id != $this->id) {
				$this->addError('email', UserManagementModule::t('front', 'This E-mail already exists'));
			}
		}
	}

	public function validateBindToIp(): void
	{
		if ($this->bind_to_ip) {
			$ips = explode(',', $this->bind_to_ip);

			foreach ($ips as $ip) {
				if (!filter_var(trim($ip), FILTER_VALIDATE_IP)) {
					$this->addError('bind_to_ip', UserManagementModule::t('back', 'Wrong format. Enter valid IPs separated by comma'));
				}
			}
		}
	}

	public function attributeLabels(): array
	{
		return [
			'id'                 => 'ID',
			'username'           => UserManagementModule::t('back', 'Login'),
			'superadmin'         => UserManagementModule::t('back', 'Superadmin'),
			'confirmation_token' => UserManagementModule::t('back', 'Confirmation Token'),
			'registration_ip'    => UserManagementModule::t('back', 'Registration IP'),
			'bind_to_ip'         => UserManagementModule::t('back', 'Bind to IP'),
			'status'             => UserManagementModule::t('back', 'Status'),
			'gridRoleSearch'     => UserManagementModule::t('back', 'Roles'),
			'created_at'         => UserManagementModule::t('back', 'Created'),
			'updated_at'         => UserManagementModule::t('back', 'Updated'),
			'password'           => UserManagementModule::t('back', 'Password'),
			'repeat_password'    => UserManagementModule::t('back', 'Repeat password'),
			'email_confirmed'    => UserManagementModule::t('back', 'E-mail confirmed'),
			'email'              => UserManagementModule::t('back', 'E-mail'),
		];
	}

	public function getRoles(): \yii\db\ActiveQuery
	{
		return $this->hasMany(Role::class, ['name' => 'item_name'])
			->viaTable(Yii::$app->getModule('user-management')->auth_assignment_table, ['user_id' => 'id']);
	}

	/**
	 * Prevent user deactivating himself; prevent superadmin demotion; block non-superadmin from editing superadmin.
	 */
	public function beforeSave(bool $insert): bool
	{
		if ($insert) {
			if (php_sapi_name() !== 'cli') {
				$this->registration_ip = LittleBigHelper::getRealIp();
			}
			$this->generateAuthKey();
		} else {
			if (php_sapi_name() !== 'cli') {
				if (Yii::$app->user->id == $this->id) {
					$this->status = static::STATUS_ACTIVE;

					if (Yii::$app->user->isSuperadmin && $this->superadmin != 1) {
						$this->superadmin = 1;
					}
				}

				if (isset($this->oldAttributes['superadmin']) && !Yii::$app->user->isSuperadmin && $this->oldAttributes['superadmin'] == 1) {
					return false;
				}
			}
		}

		if ($this->password) {
			$this->setPassword($this->password);
		}

		return parent::beforeSave($insert);
	}

	/**
	 * Prevent self-deletion and non-superadmin deleting superadmin.
	 */
	public function beforeDelete(): bool
	{
		if (php_sapi_name() !== 'cli') {
			if (Yii::$app->user->id == $this->id) {
				return false;
			}

			if (!Yii::$app->user->isSuperadmin && $this->superadmin == 1) {
				return false;
			}
		}

		return parent::beforeDelete();
	}
}
