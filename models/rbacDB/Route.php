<?php

namespace webvimark\modules\UserManagement\models\rbacDB;

use webvimark\modules\UserManagement\components\AuthHelper;
use yii\base\Action;
use yii\db\Query;
use yii\helpers\ArrayHelper;
use Yii;

class Route extends AbstractItem
{
	const ITEM_TYPE = self::TYPE_ROUTE;

	public static function getUserRoutes(int $userId, bool $withSubRoutes = true): array
	{
		$permissions = array_keys(Permission::getUserPermissions($userId));

		if (!$permissions) {
			return [];
		}

		$auth_item       = Yii::$app->getModule('user-management')->auth_item_table;
		$auth_item_child = Yii::$app->getModule('user-management')->auth_item_child_table;

		$routes = (new Query())
			->select(['name'])
			->from($auth_item)
			->innerJoin(
				$auth_item_child,
				'(' . $auth_item_child . '.child = ' . $auth_item . '.name AND ' . $auth_item . '.type = :type)'
			)
			->params([':type' => self::TYPE_ROUTE])
			->where([$auth_item_child . '.parent' => $permissions])
			->column();

		return $withSubRoutes
			? static::withSubRoutes($routes, ArrayHelper::map(Route::find()->asArray()->all(), 'name', 'name'))
			: $routes;
	}

	public static function withSubRoutes(array $givenRoutes, array $allRoutes): array
	{
		$result = [];

		foreach ($allRoutes as $route) {
			foreach ($givenRoutes as $givenRoute) {
				if (static::isSubRoute($givenRoute, $route)) {
					$result[] = $route;
				}
			}
		}

		return $result;
	}

	public static function isSubRoute(string $route, string $candidate): bool
	{
		if ($route === $candidate) {
			return true;
		}

		if (str_ends_with($route, '/*')) {
			$prefix = rtrim($route, '*');

			if (str_starts_with($candidate, $prefix)) {
				return true;
			}
		}

		return false;
	}

	public static function refreshRoutes(bool $deleteUnusedRoutes = true): void
	{
		$allRoutes     = AuthHelper::getRoutes();
		$currentRoutes = ArrayHelper::map(Route::find()->asArray()->all(), 'name', 'name');

		foreach (array_diff(array_keys($allRoutes), array_keys($currentRoutes)) as $addItem) {
			Route::create($addItem);
		}

		$toRemove = false;

		if ($deleteUnusedRoutes) {
			$toRemove = array_diff(array_keys($currentRoutes), array_keys($allRoutes));

			if ($toRemove) {
				Route::deleteAll(['in', 'name', $toRemove]);
			}
		}

		if ($allRoutes || $toRemove) {
			Yii::$app->cache?->delete('__commonRoutes');
		}
	}

	public static function isRouteAllowed(string $route, array $allowedRoutes): bool
	{
		if (in_array($route, $allowedRoutes, true)) {
			return true;
		}

		foreach ($allowedRoutes as $allowedRoute) {
			if (str_ends_with($allowedRoute, '*')) {
				$routeArray        = explode('/', $route);
				$allowedRouteArray = explode('/', $allowedRoute);

				array_pop($routeArray);
				array_pop($allowedRouteArray);

				if (array_diff($routeArray, $allowedRouteArray) === []) {
					return true;
				}
			}
		}

		return false;
	}

	public static function isFreeAccess(string $route, ?Action $action = null): bool
	{
		if ($action) {
			$controller = $action->controller;

			if ($controller->hasProperty('freeAccess') && $controller->freeAccess === true) {
				return true;
			}

			if ($controller->hasProperty('freeAccessActions') && in_array($action->id, $controller->freeAccessActions, true)) {
				return true;
			}
		}

		$systemPages = [
			'/user-management/auth/logout',
			AuthHelper::unifyRoute(Yii::$app->errorHandler->errorAction),
			AuthHelper::unifyRoute(Yii::$app->user->loginUrl),
		];

		if (in_array($route, $systemPages, true)) {
			return true;
		}

		if ($route === '/user-management/auth/registration' && Yii::$app->getModule('user-management')->enableRegistration === true) {
			return true;
		}

		return static::isInCommonPermission($route);
	}

	protected static function isInCommonPermission(string $currentFullRoute): bool
	{
		$commonRoutes = Yii::$app->cache ? Yii::$app->cache->get('__commonRoutes') : false;

		if ($commonRoutes === false) {
			$commonRoutesDB = (new Query())
				->select('child')
				->from(Yii::$app->getModule('user-management')->auth_item_child_table)
				->where(['parent' => Yii::$app->getModule('user-management')->commonPermissionName])
				->column();

			$commonRoutes = Route::withSubRoutes(
				$commonRoutesDB,
				ArrayHelper::map(Route::find()->asArray()->all(), 'name', 'name')
			);

			Yii::$app->cache?->set('__commonRoutes', $commonRoutes, 3600);
		}

		return in_array($currentFullRoute, $commonRoutes, true);
	}
}
