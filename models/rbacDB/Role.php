<?php

namespace webvimark\modules\UserManagement\models\rbacDB;

use Exception;
use webvimark\modules\UserManagement\components\AuthHelper;
use Yii;
use yii\helpers\ArrayHelper;
use yii\rbac\DbManager;

class Role extends AbstractItem
{
	const ITEM_TYPE = self::TYPE_ROLE;

	public static function getUserRoles(int $userId): array
	{
		$dbManager = Yii::$app->authManager instanceof DbManager ? Yii::$app->authManager : new DbManager();

		return $dbManager->getRolesByUser($userId);
	}

	public static function getPermissionsByRole(string $roleName, bool $asArray = true): array
	{
		$dbManager        = Yii::$app->authManager instanceof DbManager ? Yii::$app->authManager : new DbManager();
		$rbacPermissions  = $dbManager->getPermissionsByRole($roleName);
		$permissionNames  = ArrayHelper::map($rbacPermissions, 'name', 'description');

		return $asArray
			? $permissionNames
			: Permission::find()->andWhere(['name' => array_keys($permissionNames)])->all();
	}

	/**
	 * Returns roles available to assign: all roles if superadmin, or only roles the current user holds.
	 */
	public static function getAvailableRoles(bool $showAll = false, bool $asArray = false): array
	{
		$condition = ($showAll || Yii::$app->user->isSuperadmin)
			? []
			: ['name' => Yii::$app->session->get(AuthHelper::SESSION_PREFIX_ROLES)];

		$result = static::find()->andWhere($condition)->all();

		return $asArray ? ArrayHelper::map($result, 'name', 'name') : $result;
	}

	/**
	 * Assign routes to a role via a named permission, creating either if needed.
	 * Mainly used in migrations.
	 *
	 * @return true|static
	 */
	public static function assignRoutesViaPermission(
		string  $roleName,
		string  $permissionName,
		array   $routes,
		?string $permissionDescription = null,
		?string $groupCode = null
	): true|static {
		$role = static::findOne(['name' => $roleName]);

		if (!$role) {
			throw new \InvalidArgumentException("Role with name = {$roleName} not found");
		}

		$permission = Permission::findOne(['name' => $permissionName]);

		if (!$permission) {
			$permission = Permission::create($permissionName, $permissionDescription, $groupCode);

			if ($permission->hasErrors()) {
				return $permission;
			}
		}

		try {
			Yii::$app->db->createCommand()
				->insert(Yii::$app->getModule('user-management')->auth_item_child_table, [
					'parent' => $role->name,
					'child'  => $permission->name,
				])->execute();
		} catch (Exception) {
			// Permission already assigned to role — continue
		}

		foreach ((array)$routes as $route) {
			$route = '/' . ltrim($route, '/');

			Route::create($route);

			try {
				Yii::$app->db->createCommand()
					->insert(Yii::$app->getModule('user-management')->auth_item_child_table, [
						'parent' => $permission->name,
						'child'  => $route,
					])->execute();
			} catch (Exception) {
				// Route already assigned — continue
			}
		}

		AuthHelper::invalidatePermissions();

		return true;
	}
}
