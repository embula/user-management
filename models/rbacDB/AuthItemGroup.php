<?php

namespace webvimark\modules\UserManagement\models\rbacDB;

use webvimark\modules\UserManagement\UserManagementModule;
use Yii;
use yii\behaviors\TimestampBehavior;

/**
 * @property string  $code
 * @property string  $name
 * @property integer $created_at
 * @property integer $updated_at
 */
class AuthItemGroup extends \yii\db\ActiveRecord
{
	public static function tableName(): string
	{
		return Yii::$app->getModule('user-management')->auth_item_group_table;
	}

	public function behaviors(): array
	{
		return [
			TimestampBehavior::class,
		];
	}

	public function rules(): array
	{
		return [
			[['code', 'name'], 'required'],
			['code', 'unique'],
			[['code'], 'string', 'max' => 64],
			[['code', 'name'], 'trim'],
			[['name'], 'string', 'max' => 255],
		];
	}

	public function attributeLabels(): array
	{
		return [
			'name'       => UserManagementModule::t('back', 'Name'),
			'code'       => UserManagementModule::t('back', 'Code'),
			'created_at' => UserManagementModule::t('back', 'Created'),
			'updated_at' => UserManagementModule::t('back', 'Updated'),
		];
	}
}
