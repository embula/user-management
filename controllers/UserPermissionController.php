<?php

namespace webvimark\modules\UserManagement\controllers;

use webvimark\components\BaseController;
use webvimark\modules\UserManagement\models\rbacDB\Permission;
use webvimark\modules\UserManagement\models\rbacDB\Role;
use webvimark\modules\UserManagement\models\User;
use webvimark\modules\UserManagement\UserManagementModule;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use Yii;

class UserPermissionController extends BaseController
{
	public function actionSet(int $id): string
	{
		$user = User::findOne($id);

		if (!$user) {
			throw new NotFoundHttpException('User not found');
		}

		$permissionsByGroup = [];
		$permissions = Permission::find()
			->andWhere([
				Yii::$app->getModule('user-management')->auth_item_table . '.name' => array_keys(Permission::getUserPermissions($user->id)),
			])
			->joinWith('group')
			->all();

		foreach ($permissions as $permission) {
			$permissionsByGroup[$permission->group?->name ?? ''][] = $permission;
		}

		return $this->renderIsAjax('set', compact('user', 'permissionsByGroup'));
	}

	public function actionSetRoles(int $id): Response
	{
		if (!Yii::$app->user->isSuperadmin && Yii::$app->user->id == $id) {
			Yii::$app->session->setFlash('error', UserManagementModule::t('back', 'You can not change own permissions'));
			return $this->redirect(['set', 'id' => $id]);
		}

		$oldAssignments = array_keys(Role::getUserRoles($id));

		// Restrict to roles the current user actually has (unless superadmin)
		$newAssignments = array_intersect(
			Role::getAvailableRoles(Yii::$app->user->isSuperadmin, true),
			(array)Yii::$app->request->post('roles', [])
		);

		foreach (array_diff($oldAssignments, $newAssignments) as $role) {
			User::revokeRole($id, $role);
		}

		foreach (array_diff($newAssignments, $oldAssignments) as $role) {
			User::assignRole($id, $role);
		}

		Yii::$app->session->setFlash('success', UserManagementModule::t('back', 'Saved'));

		return $this->redirect(['set', 'id' => $id]);
	}
}
