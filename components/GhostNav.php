<?php

namespace webvimark\modules\UserManagement\components;

use webvimark\modules\UserManagement\models\User;

// Use whichever Nav your app has: yii\bootstrap\Nav / yii\bootstrap4\Nav / yii\bootstrap5\Nav
use yii\bootstrap\Nav;

/**
 * Filters nav items so only routes the current user can access are visible.
 * Items without an explicit 'visible' key get visibility derived from User::canRoute().
 */
class GhostNav extends Nav
{
	public function init(): void
	{
		parent::init();

		$this->ensureVisibility($this->items);
	}

	protected function ensureVisibility(array &$items): bool
	{
		$allVisible = false;

		foreach ($items as &$item) {
			if (isset($item['url']) && !isset($item['visible']) && !in_array($item['url'], ['', '#'], true)) {
				$item['visible'] = User::canRoute($item['url']);
			}

			if (isset($item['items'])) {
				if (!$this->ensureVisibility($item['items']) && !isset($item['visible'])) {
					$item['visible'] = false;
				}
			}

			if (isset($item['label']) && (!isset($item['visible']) || $item['visible'] === true)) {
				$allVisible = true;
			}
		}

		return $allVisible;
	}
}
