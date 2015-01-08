<?php
/**
 * @link http://buildwithcraft.com/
 * @copyright Copyright (c) 2013 Pixel & Tonic, Inc.
 * @license http://buildwithcraft.com/license
 */

namespace craft\app\web;

use craft\app\Craft;
use craft\app\dates\DateInterval;
use craft\app\enums\LogLevel;
use craft\app\helpers\DateTimeHelper;
use craft\app\models\User as UserModel;
use yii\web\Cookie;
use yii\web\IdentityInterface;

/**
 * The User service provides APIs for managing the user authentication status.
 *
 * An instance of the User service is globally accessible in Craft via [[Application::userSession `Craft::$app->getUser()`]].
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class User extends \yii\web\User
{
	// Properties
	// =========================================================================

	/**
	 * @var array The configuration of the username cookie.
	 * @see Cookie
	 */
	public $usernameCookie;

	// Public Methods
	// =========================================================================

	/**
	 * @inheritDoc \yii\base\object::__construct()
	 *
	 * @param array $config
	 */
	public function __construct($config = [])
	{
		// Should the session be enabled on this request?
		$config['enableSession'] = $this->_shouldEnableSession();

		// Set the configurable properties
		$configService = Craft::$app->config;
		$config['loginUrl']    = (array) $configService->getLoginPath();
		$config['authTimeout'] = $configService->getUserSessionDuration(false);

		// Set the state-based property names
		$appId = Craft::$app->config->get('appId');
		$stateKeyPrefix = md5('Craft.'.get_class($this).($appId ? '.'.$appId : ''));
		$config['identityCookie']           = ['name' => $stateKeyPrefix.'_identity', 'httpOnly' => true];
		$config['usernameCookie']           = ['name' => $stateKeyPrefix.'_username', 'httpOnly' => true];
		$config['idParam']                  = $stateKeyPrefix.'__id';
		$config['authTimeoutParam']         = $stateKeyPrefix.'__expire';
		$config['absoluteAuthTimeoutParam'] = $stateKeyPrefix.'__absoluteExpire';
		$config['returnUrlParam']           = $stateKeyPrefix.'__returnUrl';

		parent::__construct($config);
	}

	// Authentication
	// -------------------------------------------------------------------------

	/**
	 * Sends a username cookie.
	 *
	 * This method is used after a user is logged in. It saves the logged-in user's username in a cookie,
	 * so that login forms can remember the initial Username value on login forms.
	 *
	 * @param UserModel $user
	 * @see afterLogin()
	 */
	public function sendUsernameCookie(UserModel $user)
	{
		$rememberUsernameDuration = Craft::$app->config->get('rememberUsernameDuration');

		if ($rememberUsernameDuration)
		{
			$cookie = new Cookie($this->usernameCookie);
			$cookie->value = $user->username;
			$cookie->expire = time() + DateTimeHelper::timeFormatToSeconds($rememberUsernameDuration);
			Craft::$app->getResponse()->getCookies()->add($cookie);
		}
		else
		{
			Craft::$app->getResponse()->getCookies()->remove(new Cookie($this->usernameCookie));
		}
	}

	/**
	 * @inheritDoc \yii\web\User::getReturnUrl()
	 *
	 * @param string|array $defaultUrl
	 * @return string
	 * @see loginRequired()
	 */
	public function getReturnUrl($defaultUrl = null)
	{
		$url = parent::getReturnUrl($defaultUrl);

		// Strip out any tags that may have gotten in there by accident
		// i.e. if there was a {siteUrl} tag in the Site URL setting, but no matching environment variable,
		// so they ended up on something like http://example.com/%7BsiteUrl%7D/some/path
		$url = str_replace(['{', '}'], '', $url);

		return $url;
	}

	/**
	 * Removes the stored return URL, if there is one.
	 *
	 * @return null
	 * @see getReturnUrl()
	 */
	public function removeReturnUrl()
	{
		Craft::$app->getSession()->remove($this->returnUrlParam);
	}

	/**
	 * Returns the username of the account that the browser was last logged in as.
	 *
	 * @return string|null
	 */
	public function getRememberedUsername()
	{
		return Craft::$app->getRequest()->getCookies()->getValue($this->usernameCookie['name']);
	}

	/**
	 * Returns how many seconds are left in the current user session.
	 *
	 * @return int The seconds left in the session, or -1 if their session will expire when their HTTP session ends.
	 */
	public function getRemainingSessionTime()
	{
		// Are they logged in?
		if (!$this->getIsGuest())
		{
			if ($this->authTimeout === null)
			{
				// The session duration must have been empty (expire when the HTTP session ends)
				return -1;
			}

			$expire = Craft::$app->getSession()->get($this->authTimeoutParam);
			$time = time();

			if ($expire !== null && $expire > $time)
			{
				return $expire - $time;
			}
		}

		return 0;
	}

	// Authorization
	// -------------------------------------------------------------------------

	/**
	 * Returns whether the current user is an admin.
	 *
	 * @return bool Whether the current user is an admin.
	 */
	public function getIsAdmin()
	{
		$user = $this->getIdentity();
		return ($user && $user->admin);
	}

	/**
	 * Returns whether the current user has a given permission.
	 *
	 * @param string $permissionName The name of the permission.
	 *
	 * @return bool Whether the current user has the permission.
	 */
	public function checkPermission($permissionName)
	{
		$user = $this->getIdentity();
		return ($user && $user->can($permissionName));
	}

	// Protected Methods
	// =========================================================================

	/**
	 * @inheritDoc \yii\web\User::beforeLogin()
	 *
	 * @param IdentityInterface $identity
	 * @param boolean $cookieBased
	 * @param integer $duration
	 * @return boolean
	 */
	protected function beforeLogin($identity, $cookieBased, $duration)
	{
		// Only allow the login if the request meets our user agent and IP requirements
		if ($this->_validateUserAgentAndIp())
		{
			return parent::beforeLogin($identity, $cookieBased, $duration);
		}

		return false;
	}

	/**
	 * @inheritDoc \yii\web\User::afterLogin()
	 *
	 * @param IdentityInterface $identity
	 * @param boolean $cookieBased
	 * @param integer $duration
	 */
	protected function afterLogin($identity, $cookieBased, $duration)
	{
		// Save the username cookie
		$this->sendUsernameCookie($identity);

		// Delete any stale session rows
		$this->_deleteStaleSessions();

		parent::afterLogin($identity, $cookieBased, $duration);
	}

	/**
	 * @inheritDoc \yii\web\User::renewAuthStatus()
	 */
	protected function renewAuthStatus()
	{
		// Only renew if the request meets our user agent and IP requirements
		if ($this->_validateUserAgentAndIp())
		{
			parent::renewAuthStatus();
		}
	}

	/**
     * @inheritDoc \yii\web\User::renewIdentityCookie()
     */
    protected function renewIdentityCookie()
    {
    	// Prevent the session row from getting stale
    	$this->_updateSessionToken();

    	parent::renewIdentityCookie();
    }

	/**
	 * @inheritDoc \yii\web\User::afterLogout()
	 *
	 * @param IdentityInterface $identity
	 */
	protected function afterLogout($identity)
	{
		// Delete the session row
		$value = Craft::$app->getRequest()->getCookies()->getValue($this->identityCookie['name']);

		if ($value !== null)
		{
			$data = json_decode($value, true);

			if (count($data) === 4 && isset($data[0], $data[1], $data[2], $data[3]))
			{
				$authKey = $data[1];

				Craft::$app->db->createCommand()->delete('sessions', ['and', 'userId=:userId', 'uid=:uid'], [
					'userId' => $user->id,
					'token' => $authKey
				]);
			}
		}

		parent::afterLogout($identity);
	}

	// Private Methods
	// =========================================================================

	/**
	 * Determines whether the session should be enabled for this request.
	 *
	 * @return bool
	 */
	private function _shouldEnableSession()
	{
		// See if these are the exact conditions we support for disabling the session on the current request
		return !(
			Craft::$app->request->getIsGet() &&
			Craft::$app->request->isCpRequest() &&
			Craft::$app->request->getParam('dontEnableSession')
		);
	}

	/**
	 * Validates that the request has a user agent and IP associated with it,
	 * if the 'requireUserAgentAndIpForSession' config setting is enabled.
	 *
	 * @return boolean
	 */
	private function _validateUserAgentAndIp()
	{
		if (Craft::$app->config->get('requireUserAgentAndIpForSession'))
		{
			$request = Craft::$app->getRequest();

			if ($request->getUserAgent() === null || $request->getUserIP() === null)
			{
				Craft::log('Request didn’t meet the user agent and IP requirement for maintaining a user session.', LogLevel::Warning);
				return false;
			}
		}

		return true;
	}

	/**
	 * Updates the dateUpdated column on the session's row, so it doesn't get stale.
	 *
	 * @see _deleteStaleSessions()
	 */
	private function _updateSessionToken()
	{
		// Extract the current session token's UID from the identity cookie
		$cookieValue = Craft::$app->getRequest()->getCookies()->getValue($this->identityCookie['name']);

		if ($cookieValue !== null)
		{
			$identityData = json_decode($cookieValue, true);

			if (count($identityData) === 3 && isset($identityData[0], $identityData[1], $identityData[2]);
			{
				$authData = UserModel::getAuthData($identityData[1]);

				if ($authData)
				{
					$tokenUid = $authData[1];

					// Now update the associated session row's dateUpdated column
					Craft::$app->db->createCommand()->update('sessions',
						[],
						['and', 'userId=:userId', 'uid=:uid'],
						[':userId' => $this->getId(), ':uid' => $uid]
					);
				}
			}
		}
	}

	/**
	 * Deletes any session rows that have gone stale.
	 */
	private function _deleteStaleSessions()
	{
		$interval = new DateInterval('P3M');
		$expire = DateTimeHelper::currentUTCDateTime();
		$pastTimeStamp = $expire->sub($interval)->getTimestamp();
		$pastTime = DateTimeHelper::formatTimeForDb($pastTimeStamp);
		Craft::$app->db->createCommand()->delete('sessions', 'dateUpdated < :pastTime', ['pastTime' => $pastTime]);
	}
}
