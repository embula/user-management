<?php

namespace webvimark\modules\UserManagement\models;

use webvimark\helpers\LittleBigHelper;
use webvimark\modules\UserManagement\UserManagementModule;
use Yii;
use yii\db\ActiveQuery;

/**
 * @property integer $id
 * @property string  $token
 * @property string  $ip
 * @property string  $language
 * @property string  $browser
 * @property string  $os
 * @property string  $user_agent
 * @property integer $user_id
 * @property integer $visit_time
 *
 * @property User $user
 */
class UserVisitLog extends \webvimark\components\BaseActiveRecord
{
	const SESSION_TOKEN = '__visitorToken';

	/**
	 * Records a new visit and stores a unique token in the session.
	 * Uses cbschuld/browser.php (replaces abandoned ikimea/browser).
	 */
	public static function newVisitor(int $userId): void
	{
		// cbschuld/browser.php exposes \Browser in the global namespace
		$browser = new \Browser();

		$model             = new self();
		$model->user_id    = $userId;
		$model->token      = uniqid('', true);
		$model->ip         = LittleBigHelper::getRealIp();
		$model->language   = isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])
			? substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2)
			: '';
		$model->browser    = $browser->getBrowser();
		$model->os         = $browser->getPlatform();
		$model->user_agent = $browser->getUserAgent();
		$model->visit_time = time();
		$model->save(false);

		Yii::$app->session->set(self::SESSION_TOKEN, $model->token);
	}

	/**
	 * Logs out and reloads if session token no longer matches the latest DB record.
	 */
	public static function checkToken(): void
	{
		if (Yii::$app->user->isGuest) {
			return;
		}

		$model = static::find()
			->andWhere(['user_id' => Yii::$app->user->id])
			->orderBy('id DESC')
			->asArray()
			->one();

		if (!$model || ($model['token'] !== Yii::$app->session->get(self::SESSION_TOKEN))) {
			Yii::$app->user->logout();
			echo '<script>location.reload();</script>';
			Yii::$app->end();
		}
	}

	public static function tableName(): string
	{
		return Yii::$app->getModule('user-management')->user_visit_log_table;
	}

	public function rules(): array
	{
		return [
			[['token', 'ip', 'language', 'visit_time'], 'required'],
			[['user_id', 'visit_time'], 'integer'],
			[['token', 'user_agent'], 'string', 'max' => 255],
			[['ip'], 'string', 'max' => 15],
			[['os'], 'string', 'max' => 20],
			[['browser'], 'string', 'max' => 30],
			[['language'], 'string', 'max' => 2],
		];
	}

	public function attributeLabels(): array
	{
		return [
			'id'         => UserManagementModule::t('back', 'ID'),
			'token'      => UserManagementModule::t('back', 'Token'),
			'ip'         => UserManagementModule::t('back', 'IP'),
			'language'   => UserManagementModule::t('back', 'Language'),
			'browser'    => UserManagementModule::t('back', 'Browser'),
			'os'         => UserManagementModule::t('back', 'OS'),
			'user_agent' => UserManagementModule::t('back', 'User agent'),
			'user_id'    => UserManagementModule::t('back', 'User'),
			'visit_time' => UserManagementModule::t('back', 'Visit Time'),
		];
	}

	public function getUser(): ActiveQuery
	{
		return $this->hasOne(User::class, ['id' => 'user_id']);
	}
}
