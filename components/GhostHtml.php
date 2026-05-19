<?php

namespace webvimark\modules\UserManagement\components;

use webvimark\modules\UserManagement\models\User;
use yii\helpers\Html;

/**
 * Renders links only when the current user has route access.
 */
class GhostHtml extends Html
{
	public static function a($text, $url = null, $options = []): string
	{
		if (in_array($url, [null, '', '#'], true)) {
			return parent::a($text, $url, $options);
		}

		return User::canRoute($url) ? parent::a($text, $url, $options) : '';
	}
}
