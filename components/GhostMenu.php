<?php

namespace webvimark\modules\UserManagement\components;

use webvimark\modules\UserManagement\models\User;
use yii\widgets\Menu;

/**
 * Filters menu items so only routes the current user can access are visible.
 * Items without an explicit 'visible' key get visibility derived from User::canRoute().
 */
class GhostMenu extends Menu
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
			if (isset($item['url']) && !in_array($item['url'], ['', '#'], true) && !isset($item['visible'])) {
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
