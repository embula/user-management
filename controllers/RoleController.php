<?php

namespace webvimark\modules\UserManagement\controllers;

use webvimark\modules\UserManagement\components\AuthHelper;
use webvimark\modules\UserManagement\models\rbacDB\Permission;
use webvimark\modules\UserManagement\models\rbacDB\Role;
use webvimark\components\AdminDefaultController;
use webvimark\modules\UserManagement\UserManagementModule;
use Yii;
use yii\rbac\DbManager;
use yii\web\Response;

class RoleController extends AdminDefaultController
{
	public $modelClass       = 'webvimark\modules\UserManagement\models\rbacDB\Role';
	public $modelSearchClass = 'webvimark\modules\UserManagement\models\rbacDB\search\RoleSearch';

	public function actionView(string $id): string
	{
		$role       = $this->findModel($id);
		$authManager = Yii::$app->authManager instanceof DbManager ? Yii::$app->authManager : new DbManager();

		$allRoles = Role::find()
			->asArray()
			->andWhere('name != :current_name', [':current_name' => $id])
			->all();

		$permissions = Permission::find()
			->andWhere(
				Yii::$app->getModule('user-management')->auth_item_table . '.name != :commonPermissionName',
				[':commonPermissionName' => Yii::$app->getModule('user-management')->commonPermissionName]
			)
			->joinWith('group')
			->all();

		$permissionsByGroup = [];
		foreach ($permissions as $permission) {
			$permissionsByGroup[$permission->group?->name ?? ''][] = $permission;
		}

		$childRoles = $authManager->getChildren($role->name);

		$currentRoutesAndPermissions = AuthHelper::separateRoutesAndPermissions(
			$authManager->getPermissionsByRole($role->name)
		);

		$currentPermissions = $currentRoutesAndPermissions->permissions;

		return $this->renderIsAjax('view', compact('role', 'allRoles', 'childRoles', 'currentPermissions', 'permissionsByGroup'));
	}

	public function actionSetChildRoles(string $id): Response
	{
		$role           = $this->findModel($id);
		$newChildRoles  = Yii::$app->request->post('child_roles', []);
		$dbManager      = Yii::$app->authManager instanceof DbManager ? Yii::$app->authManager : new DbManager();

		$oldChildRoles = [];
		foreach ($dbManager->getChildren($role->name) as $child) {
			if ($child->type == Role::TYPE_ROLE) {
				$oldChildRoles[$child->name] = $child->name;
			}
		}

		Role::addChildren($role->name, array_diff($newChildRoles, $oldChildRoles));
		Role::removeChildren($role->name, array_diff($oldChildRoles, $newChildRoles));

		Yii::$app->session->setFlash('success', UserManagementModule::t('back', 'Saved'));

		return $this->redirect(['view', 'id' => $id]);
	}

	public function actionSetChildPermissions(string $id): Response
	{
		$role      = $this->findModel($id);
		$dbManager = Yii::$app->authManager instanceof DbManager ? Yii::$app->authManager : new DbManager();

		$newChildPermissions = Yii::$app->request->post('child_permissions', []);
		$oldChildPermissions = array_keys($dbManager->getPermissionsByRole($role->name));

		Role::addChildren($role->name, array_diff($newChildPermissions, $oldChildPermissions));
		Role::removeChildren($role->name, array_diff($oldChildPermissions, $newChildPermissions));

		Yii::$app->session->setFlash('success', UserManagementModule::t('back', 'Saved'));

		return $this->redirect(['view', 'id' => $id]);
	}

	public function actionCreate(): Response|string
	{
		$model = new Role();
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
