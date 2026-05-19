<?php

namespace webvimark\modules\UserManagement\models\rbacDB;

use Exception;
use webvimark\modules\UserManagement\components\AuthHelper;
use Yii;
use yii\rbac\DbManager;

class Permission extends AbstractItem
{
	const ITEM_TYPE = self::TYPE_PERMISSION;

	public static function getUserPermissions(int $userId): array
	{
		$dbManager = Yii::$app->authManager instanceof DbManager ? Yii::$app->authManager : new DbManager();

		return $dbManager->getPermissionsByUser($userId);
	}

	/**
	 * Assign routes to a permission, creating the permission if it does not exist.
	 * Mainly used in migrations.
	 *
	 * @param string|array $routes
	 * @return true|static
	 */
	public static function assignRoutes(
		string  $permissionName,
		string|array $routes,
		?string $permissionDescription = null,
		?string $groupCode = null
	): true|static {
		$permission = static::findOne(['name' => $permissionName]);
		$routes     = (array)$routes;

		if (!$permission) {
			$permission = static::create($permissionName, $permissionDescription, $groupCode);

			if ($permission->hasErrors()) {
				return $permission;
			}
		}

		foreach ($routes as $route) {
			$route = '/' . ltrim($route, '/');
			try {
				Yii::$app->db->createCommand()
					->insert(Yii::$app->getModule('user-management')->auth_item_child_table, [
						'parent' => $permission->name,
						'child'  => $route,
					])->execute();
			} catch (Exception) {
				// Route may already be assigned — continue
			}
		}

		AuthHelper::invalidatePermissions();

		return true;
	}
}
