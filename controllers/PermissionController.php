<?php

namespace webvimark\modules\UserManagement\controllers;

use webvimark\modules\UserManagement\components\AuthHelper;
use webvimark\modules\UserManagement\models\rbacDB\AbstractItem;
use webvimark\modules\UserManagement\models\rbacDB\Permission;
use webvimark\modules\UserManagement\models\rbacDB\Route;
use webvimark\components\AdminDefaultController;
use webvimark\modules\UserManagement\UserManagementModule;
use Yii;
use yii\web\Response;

class PermissionController extends AdminDefaultController
{
	public $modelClass       = 'webvimark\modules\UserManagement\models\rbacDB\Permission';
	public $modelSearchClass = 'webvimark\modules\UserManagement\models\rbacDB\search\PermissionSearch';

	public function actionView(string $id): string
	{
		$item   = $this->findModel($id);
		$routes = Route::find()->asArray()->all();

		$permissions = Permission::find()
			->andWhere(['not in', Yii::$app->getModule('user-management')->auth_item_table . '.name', [
				Yii::$app->getModule('user-management')->commonPermissionName,
				$id,
			]])
			->joinWith('group')
			->all();

		$permissionsByGroup = [];
		foreach ($permissions as $permission) {
			$permissionsByGroup[$permission->group?->name ?? ''][] = $permission;
		}

		$childRoutes      = AuthHelper::getChildrenByType($item->name, AbstractItem::TYPE_ROUTE);
		$childPermissions = AuthHelper::getChildrenByType($item->name, AbstractItem::TYPE_PERMISSION);

		return $this->renderIsAjax('view', compact('item', 'childPermissions', 'routes', 'permissionsByGroup', 'childRoutes'));
	}

	public function actionSetChildPermissions(string $id): Response|string
	{
		$item = $this->findModel($id);

		$newChildPermissions = Yii::$app->request->post('child_permissions', []);
		$oldChildPermissions = array_keys(AuthHelper::getChildrenByType($item->name, AbstractItem::TYPE_PERMISSION));

		Permission::addChildren($item->name, array_diff($newChildPermissions, $oldChildPermissions));
		Permission::removeChildren($item->name, array_diff($oldChildPermissions, $newChildPermissions));

		Yii::$app->session->setFlash('success', UserManagementModule::t('back', 'Saved'));

		return $this->redirect(['view', 'id' => $id]);
	}

	public function actionSetChildRoutes(string $id): Response
	{
		$item = $this->findModel($id);

		$newRoutes = Yii::$app->request->post('child_routes', []);
		$oldRoutes = array_keys(AuthHelper::getChildrenByType($item->name, AbstractItem::TYPE_ROUTE));

		$toAdd    = array_diff($newRoutes, $oldRoutes);
		$toRemove = array_diff($oldRoutes, $newRoutes);

		Permission::addChildren($id, $toAdd);
		Permission::removeChildren($id, $toRemove);

		if (($toAdd || $toRemove) && $id === Yii::$app->getModule('user-management')->commonPermissionName) {
			Yii::$app->cache->delete('__commonRoutes');
		}

		AuthHelper::invalidatePermissions();

		Yii::$app->session->setFlash('success', UserManagementModule::t('back', 'Saved'));

		return $this->redirect(['view', 'id' => $id]);
	}

	public function actionRefreshRoutes(string $id, $deleteUnused = null): Response
	{
		Route::refreshRoutes($deleteUnused !== null);

		return $this->redirect(['view', 'id' => $id]);
	}

	public function actionCreate(): Response|string
	{
		$model = new Permission();
		$model->scenario = 'webInput';

		if ($model->load(Yii::$app->request->post()) && $model->save()) {
			return $this->redirect(['view', 'id' => $model->name]);
		}

		return $this->renderIsAjax('create', compact('model'));
	}

	public function actionUpdate(string $id): Response|string
	{
		$model = $this->findModel($id);
		$model->scenario = 'webInput';

		if ($model->load(Yii::$app->request->post()) && $model->save()) {
			return $this->redirect(['view', 'id' => $model->name]);
		}

		return $this->renderIsAjax('update', compact('model'));
	}
}
