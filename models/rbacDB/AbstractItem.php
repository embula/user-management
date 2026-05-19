<?php

namespace webvimark\modules\UserManagement\models\rbacDB;

use webvimark\modules\UserManagement\components\AuthHelper;
use webvimark\modules\UserManagement\components\AbstractItemEvent;
use webvimark\modules\UserManagement\UserManagementModule;
use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\helpers\Inflector;
use yii\rbac\DbManager;

/**
 * @property integer $type
 * @property string  $name
 * @property string  $description
 * @property string  $group_code
 * @property string  $rule_name
 * @property string  $data
 * @property integer $created_at
 * @property integer $updated_at
 *
 * @property AuthItemGroup $group
 */
#[\AllowDynamicProperties]
abstract class AbstractItem extends ActiveRecord
{
	const EVENT_BEFORE_ADD_CHILDREN    = 'beforeAddChildren';
	const EVENT_BEFORE_REMOVE_CHILDREN = 'beforeRemoveChildren';

	const TYPE_ROLE       = 1;
	const TYPE_PERMISSION = 2;
	const TYPE_ROUTE      = 3;

	const ITEM_TYPE = 0;

	/**
	 * Helper for migrations: creates and saves a new item.
	 * If description is null it is titleized from the name.
	 */
	public static function create(
		string  $name,
		?string $description = null,
		?string $groupCode   = null,
		?string $ruleName    = null,
		?string $data        = null
	): static {
		$item = new static();

		$item->type        = static::ITEM_TYPE;
		$item->name        = $name;
		$item->description = ($description === null && static::ITEM_TYPE != static::TYPE_ROUTE)
			? Inflector::titleize($name)
			: $description;
		$item->rule_name   = $ruleName;
		$item->group_code  = $groupCode;
		$item->data        = $data;

		$item->save();

		return $item;
	}

	public static function addChildren(string $parentName, array|string $childrenNames, bool $throwException = false): void
	{
		$parent        = (object)['name' => $parentName];
		$childrenNames = (array)$childrenNames;
		$dbManager     = Yii::$app->authManager instanceof DbManager ? Yii::$app->authManager : new DbManager();

		static::beforeAddChildren($parentName, $childrenNames, $throwException);

		foreach ($childrenNames as $childName) {
			$child = (object)['name' => $childName];
			try {
				$dbManager->addChild($parent, $child);
			} catch (\Exception $e) {
				if ($throwException) {
					throw $e;
				}
			}
		}

		AuthHelper::invalidatePermissions();
	}

	public static function removeChildren(string $parentName, array|string $childrenNames): void
	{
		$childrenNames = (array)$childrenNames;

		static::beforeRemoveChildren($parentName, $childrenNames);

		foreach ($childrenNames as $childName) {
			Yii::$app->db->createCommand()
				->delete(
					Yii::$app->getModule('user-management')->auth_item_child_table,
					['parent' => $parentName, 'child' => $childName]
				)->execute();
		}

		AuthHelper::invalidatePermissions();
	}

	public static function deleteIfExists(mixed $condition): bool
	{
		$model = static::findOne($condition);

		if ($model) {
			$model->delete();
			return true;
		}

		return false;
	}

	public function behaviors(): array
	{
		return [
			TimestampBehavior::class,
		];
	}

	public static function tableName(): string
	{
		return Yii::$app->getModule('user-management')->auth_item_table;
	}

	public function rules(): array
	{
		return [
			[['name', 'rule_name', 'description', 'group_code'], 'trim'],

			['description', 'required', 'on' => 'webInput'],
			['description', 'string', 'max' => 255],

			['name', 'required'],
			['name', 'validateUniqueName'],
			[['name', 'rule_name', 'group_code'], 'string', 'max' => 64],

			[['rule_name', 'description', 'group_code', 'data'], 'default', 'value' => null],

			['type', 'integer'],
			['type', 'in', 'range' => [static::TYPE_ROLE, static::TYPE_PERMISSION, static::TYPE_ROUTE]],
		];
	}

	public function validateUniqueName(string $attribute): void
	{
		if ($this->isNewRecord || ($this->oldAttributes['name'] && $this->oldAttributes['name'] !== $this->name)) {
			if (Role::find()->where(['name' => $this->name])->exists()) {
				$this->addError('name', Yii::t('yii', '{attribute} "{value}" has already been taken.', [
					'attribute' => $this->getAttributeLabel($attribute),
					'value'     => $this->$attribute,
				]));
			}
		}
	}

	public static function find(): ActiveQuery
	{
		return parent::find()->andWhere([
			Yii::$app->getModule('user-management')->auth_item_table . '.type' => static::ITEM_TYPE,
		]);
	}

	public function attributeLabels(): array
	{
		return [
			'name'        => UserManagementModule::t('back', 'Code'),
			'description' => UserManagementModule::t('back', 'Description'),
			'rule_name'   => UserManagementModule::t('back', 'Rule'),
			'group_code'  => UserManagementModule::t('back', 'Group'),
			'data'        => UserManagementModule::t('back', 'Data'),
			'type'        => UserManagementModule::t('back', 'Type'),
			'created_at'  => UserManagementModule::t('back', 'Created'),
			'updated_at'  => UserManagementModule::t('back', 'Updated'),
		];
	}

	public function getGroup(): ActiveQuery
	{
		return $this->hasOne(AuthItemGroup::class, ['code' => 'group_code']);
	}

	public function beforeSave(bool $insert): bool
	{
		$this->type = static::ITEM_TYPE;

		return parent::beforeSave($insert);
	}

	public function afterDelete(): void
	{
		parent::afterDelete();

		AuthHelper::invalidatePermissions();
	}

	public static function beforeAddChildren(string $parentName, array $childrenNames, bool $throwException = false): void
	{
		$event = new AbstractItemEvent(compact('parentName', 'childrenNames', 'throwException'));
		$event->trigger(get_called_class(), self::EVENT_BEFORE_ADD_CHILDREN, $event);
	}

	public static function beforeRemoveChildren(string $parentName, array $childrenNames): void
	{
		$throwException = false;
		$event = new AbstractItemEvent(compact('parentName', 'childrenNames', 'throwException'));
		$event->trigger(get_called_class(), self::EVENT_BEFORE_REMOVE_CHILDREN, $event);
	}
}
