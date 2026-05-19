<?php

namespace webvimark\modules\UserManagement;

use Yii;
use yii\helpers\ArrayHelper;

class UserManagementModule extends \yii\base\Module
{
	const SESSION_LAST_ATTEMPT  = '_um_last_attempt';
	const SESSION_ATTEMPT_COUNT = '_um_attempt_count';

	public bool $useEmailAsLogin = false;

	public bool $emailConfirmationRequired = false;

	public array $mailerOptions = [];

	protected array $_defaultMailerOptions = [
		'from' => '',
		'registrationFormViewFile'     => '/mail/registrationEmailConfirmation',
		'passwordRecoveryFormViewFile' => '/mail/passwordRecoveryMail',
		'confirmEmailFormViewFile'     => '/mail/emailConfirmationMail',
	];

	public string $commonPermissionName = 'commonPermission';

	public string $registrationFormClass = 'webvimark\modules\UserManagement\models\forms\RegistrationForm';

	public int $confirmationTokenExpire = 3600;

	public bool $enableRegistration = false;

	public array $rolesAfterRegistration = [];

	public string $registrationRegexp = '/^(\w|\d)+$/';

	public string $registrationBlackRegexp = '/^(.)*admin(.)*$/i';

	public string $passwordRegexp = '/^(.*)+$/';

	public bool $userCanHaveMultipleRoles = true;

	public int $maxAttempts = 5;

	public int $attemptsTimeout = 60;

	public array $captchaOptions = [
		'class'     => 'yii\captcha\CaptchaAction',
		'minLength' => 3,
		'maxLength' => 4,
		'offset'    => 5,
	];

	public string $user_table             = '{{%user}}';
	public string $user_visit_log_table   = '{{%user_visit_log}}';
	public string $auth_item_table        = '{{%auth_item}}';
	public string $auth_item_child_table  = '{{%auth_item_child}}';
	public string $auth_item_group_table  = '{{%auth_item_group}}';
	public string $auth_assignment_table  = '{{%auth_assignment}}';
	public string $auth_rule_table        = '{{%auth_rule}}';

	public $controllerNamespace = 'webvimark\modules\UserManagement\controllers';

	public function init(): void
	{
		parent::init();

		$this->prepareMailerOptions();
	}

	public static function menuItems(): array
	{
		return [
			['label' => '<i class="fa fa-angle-double-right"></i> ' . self::t('back', 'Users'),            'url' => ['/user-management/user/index']],
			['label' => '<i class="fa fa-angle-double-right"></i> ' . self::t('back', 'Roles'),            'url' => ['/user-management/role/index']],
			['label' => '<i class="fa fa-angle-double-right"></i> ' . self::t('back', 'Permissions'),      'url' => ['/user-management/permission/index']],
			['label' => '<i class="fa fa-angle-double-right"></i> ' . self::t('back', 'Permission groups'),'url' => ['/user-management/auth-item-group/index']],
			['label' => '<i class="fa fa-angle-double-right"></i> ' . self::t('back', 'Visit log'),        'url' => ['/user-management/user-visit-log/index']],
		];
	}

	public static function t(string $category, string $message, array $params = [], ?string $language = null): string
	{
		if (!isset(Yii::$app->i18n->translations['modules/user-management/*'])) {
			Yii::$app->i18n->translations['modules/user-management/*'] = [
				'class'          => 'yii\i18n\PhpMessageSource',
				'sourceLanguage' => 'en',
				'basePath'       => '@vendor/webvimark/module-user-management/messages',
				'fileMap'        => [
					'modules/user-management/back'  => 'back.php',
					'modules/user-management/front' => 'front.php',
				],
			];
		}

		return Yii::t('modules/user-management/' . $category, $message, $params, $language);
	}

	public function checkAttempts(): bool
	{
		$lastAttempt = Yii::$app->session->get(static::SESSION_LAST_ATTEMPT);

		if ($lastAttempt) {
			$attemptsCount = Yii::$app->session->get(static::SESSION_ATTEMPT_COUNT, 0);

			Yii::$app->session->set(static::SESSION_ATTEMPT_COUNT, ++$attemptsCount);

			if (($lastAttempt + $this->attemptsTimeout) < time()) {
				Yii::$app->session->set(static::SESSION_LAST_ATTEMPT, time());
				Yii::$app->session->set(static::SESSION_ATTEMPT_COUNT, 1);

				return true;
			}

			if ($attemptsCount > $this->maxAttempts) {
				return false;
			}

			return true;
		}

		Yii::$app->session->set(static::SESSION_LAST_ATTEMPT, time());
		Yii::$app->session->set(static::SESSION_ATTEMPT_COUNT, 1);

		return true;
	}

	protected function prepareMailerOptions(): void
	{
		if (!isset($this->mailerOptions['from'])) {
			$this->mailerOptions['from'] = [Yii::$app->params['adminEmail'] => Yii::$app->name . ' robot'];
		}

		$this->mailerOptions = ArrayHelper::merge($this->_defaultMailerOptions, $this->mailerOptions);
	}
}
