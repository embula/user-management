<?php

namespace webvimark\modules\UserManagement\controllers;

use webvimark\components\AdminDefaultController;

class UserVisitLogController extends AdminDefaultController
{
	public $modelClass       = 'webvimark\modules\UserManagement\models\UserVisitLog';
	public $modelSearchClass = 'webvimark\modules\UserManagement\models\search\UserVisitLogSearch';

	public array $enableOnlyActions = ['index', 'view', 'grid-page-size'];
}
