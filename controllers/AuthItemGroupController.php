<?php

namespace webvimark\modules\UserManagement\controllers;

use webvimark\modules\UserManagement\models\rbacDB\AuthItemGroup;
use webvimark\modules\UserManagement\models\rbacDB\search\AuthItemGroupSearch;
use webvimark\components\AdminDefaultController;

class AuthItemGroupController extends AdminDefaultController
{
	public $modelClass       = 'webvimark\modules\UserManagement\models\rbacDB\AuthItemGroup';
	public $modelSearchClass = 'webvimark\modules\UserManagement\models\rbacDB\search\AuthItemGroupSearch';

	protected function getRedirectPage(string $action, $model = null): string|array
	{
		return match ($action) {
			'delete' => ['index'],
			'update', 'create' => ['view', 'id' => $model->code],
			default  => ['index'],
		};
	}
}
