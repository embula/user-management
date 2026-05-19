<?php

namespace webvimark\modules\UserManagement\components;

use webvimark\modules\UserManagement\models\rbacDB\AbstractItem;
use webvimark\modules\UserManagement\models\rbacDB\Permission;
use webvimark\modules\UserManagement\models\rbacDB\Role;
use webvimark\modules\UserManagement\models\rbacDB\Route;
use Yii;
use yii\base\InvalidArgumentException;
use yii\helpers\Inflector;
use yii\helpers\Url;
use yii\rbac\DbManager;

class AuthHelper
{
	const SESSION_PREFIX_LAST_UPDATE  = '__auth_last_update';
	const SESSION_PREFIX_ROLES        = '__userRoles';
	const SESSION_PREFIX_PERMISSIONS  = '__userPermissions';
	const SESSION_PREFIX_ROUTES       = '__userRoutes';

	/**
	 * Example layout handler — wire up via 'on beforeAction' in config.
	 */
	public static function layoutHandler(\yii\base\ActionEvent $event): void
	{
		if ($event->action->uniqueId === 'user-management/auth/login') {
			$event->action->controller->layout = 'loginLayout.php';
		} elseif ($event->action->controller->id === 'auth') {
			if (in_array($event->action->id, ['change-own-password', 'confirm-email'])) {
				$event->action->controller->layout = '//back.php';
			} else {
				$event->action->controller->layout = '//main.php';
			}
		} else {
			$event->action->controller->layout = '//back.php';
		}
	}

	/**
	 * Gather all user permissions and roles and store them in the session.
	 */
	public static function updatePermissions(UserIdentity $identity): void
	{
		$session = Yii::$app->session;

		$session->remove(self::SESSION_PREFIX_ROLES);
		$session->remove(self::SESSION_PREFIX_PERMISSIONS);
		$session->remove(self::SESSION_PREFIX_ROUTES);

		$session->set(self::SESSION_PREFIX_LAST_UPDATE, filemtime(self::getPermissionsLastModFile()));

		$session->set(self::SESSION_PREFIX_ROLES, array_keys(Role::getUserRoles($identity->id)));
		$session->set(self::SESSION_PREFIX_PERMISSIONS, array_keys(Permission::getUserPermissions($identity->id)));
		$session->set(self::SESSION_PREFIX_ROUTES, Route::getUserRoutes($identity->id));
	}

	/**
	 * Refresh session permissions if the backing file has been touched since last load.
	 */
	public static function ensurePermissionsUpToDate(): void
	{
		if (!Yii::$app->user->isGuest) {
			if (Yii::$app->session->get(self::SESSION_PREFIX_LAST_UPDATE) != filemtime(self::getPermissionsLastModFile())) {
				static::updatePermissions(Yii::$app->user->identity);
			}
		}
	}

	public static function getPermissionsLastModFile(): string
	{
		$file = Yii::$app->runtimePath . '/__permissions_last_mod.txt';

		if (!is_file($file)) {
			file_put_contents($file, '');
			chmod($file, 0777);
		}

		return $file;
	}

	public static function invalidatePermissions(): void
	{
		touch(static::getPermissionsLastModFile());
	}

	/**
	 * Return route without baseUrl, always starting with a slash.
	 *
	 * @param string|array $route
	 */
	public static function unifyRoute(string|array $route): string
	{
		// Relative array route like ['create'] — convert to absolute URL string first
		if (is_array($route) && !str_contains($route[0], '/')) {
			$route = Url::toRoute($route);
		}

		if (Yii::$app->getUrlManager()->showScriptName === true) {
			$baseUrl = Yii::$app->getRequest()->scriptUrl;
		} else {
			$baseUrl = Yii::$app->getRequest()->baseUrl;
		}

		if (!is_array($route)) {
			$route = explode('?', $route);
		}

		$routeAsString = $route[0];

		if ($baseUrl && str_starts_with($routeAsString, $baseUrl)) {
			$routeAsString = substr($routeAsString, strlen($baseUrl));
		}

		$languagePrefix = '/' . Yii::$app->language . '/';

		if (str_starts_with($routeAsString, $languagePrefix)) {
			$routeAsString = substr($routeAsString, strlen($languagePrefix));
		}

		return '/' . ltrim($routeAsString, '/');
	}

	/**
	 * Get child routes, permissions, or roles filtered by type.
	 */
	public static function getChildrenByType(string $itemName, int $childType): array
	{
		$dbManager = Yii::$app->authManager instanceof DbManager ? Yii::$app->authManager : new DbManager();

		$children = $dbManager->getChildren($itemName);

		$result = [];

		foreach ($children as $id => $item) {
			if ($item->type == $childType) {
				$result[$id] = $item;
			}
		}

		return $result;
	}

	/**
	 * Split a flat permissions array into routes vs. permissions.
	 */
	public static function separateRoutesAndPermissions(array $allPermissions): object
	{
		$routes      = [];
		$permissions = [];

		foreach ($allPermissions as $id => $item) {
			if ($item->type == AbstractItem::TYPE_ROUTE) {
				$routes[$id] = $item;
			} else {
				$permissions[$id] = $item;
			}
		}

		return (object)compact('routes', 'permissions');
	}

	public static function getAllModules(): array
	{
		$result = [];

		foreach (\Yii::$app->getModules() as $moduleId => $uselessStuff) {
			$result[$moduleId] = \Yii::$app->getModule($moduleId);
		}

		return $result;
	}

	// ================= Credits to mdm/admin module =================

	public static function getRoutes(): array
	{
		$result = [];
		self::getRouteRecursive(Yii::$app, $result);

		return array_reverse(array_combine($result, $result));
	}

	private static function getRouteRecursive(\yii\base\Module $module, array &$result): void
	{
		foreach ($module->getModules() as $id => $child) {
			if (($child = $module->getModule($id)) !== null) {
				self::getRouteRecursive($child, $result);
			}
		}

		foreach ($module->controllerMap as $id => $value) {
			$controller = Yii::createObject($value, [$id, $module]);
			self::getActionRoutes($controller, $result);
			$result[] = '/' . $controller->uniqueId . '/*';
		}

		$namespace = trim($module->controllerNamespace, '\\') . '\\';
		self::getControllerRoutes($module, $namespace, '', $result);

		$result[] = ($module->uniqueId ? '/' . $module->uniqueId : '') . '/*';
	}

	private static function getActionRoutes(\yii\base\Controller $controller, array &$result): void
	{
		$prefix = '/' . $controller->uniqueId . '/';

		foreach ($controller->actions() as $id => $value) {
			$result[] = $prefix . $id;
		}

		$class = new \ReflectionClass($controller);

		foreach ($class->getMethods() as $method) {
			$name = $method->getName();
			if ($method->isPublic() && !$method->isStatic() && str_starts_with($name, 'action') && $name !== 'actions') {
				$result[] = $prefix . Inflector::camel2id(substr($name, 6));
			}
		}
	}

	private static function getControllerRoutes(\yii\base\Module $module, string $namespace, string $prefix, array &$result): void
	{
		try {
			$path = Yii::getAlias('@' . str_replace('\\', '/', $namespace));
		} catch (InvalidArgumentException $e) {
			$path = $module->getBasePath() . '/controllers';
		}

		if (!is_dir($path)) {
			return;
		}

		foreach (scandir($path) as $file) {
			if (str_starts_with($file, '.')) {
				continue;
			}

			if (is_dir($path . '/' . $file)) {
				self::getControllerRoutes($module, $namespace . $file . '\\', $prefix . $file . '/', $result);
			} elseif (str_ends_with($file, 'Controller.php')) {
				$id        = Inflector::camel2id(substr(basename($file), 0, -14), '-', true);
				$className = $namespace . Inflector::id2camel($id) . 'Controller';

				if (!str_contains($className, '-') && class_exists($className) && is_subclass_of($className, 'yii\base\Controller')) {
					$controller = new $className($prefix . $id, $module);
					self::getActionRoutes($controller, $result);
					$result[] = '/' . $controller->uniqueId . '/*';
				}
			}
		}
	}
}
