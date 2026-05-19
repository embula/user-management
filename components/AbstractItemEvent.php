<?php

namespace webvimark\modules\UserManagement\components;

use yii\base\Event;

class AbstractItemEvent extends Event
{
	public ?string $parentName   = null;
	public array   $childrenNames = [];
	public bool    $throwException = false;
}
