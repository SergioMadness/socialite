<?php

namespace Overtrue\Socialite\Providers;

use Overtrue\Socialite\AccessToken;
use Overtrue\Socialite\AccessTokenInterface;
use Overtrue\Socialite\ProviderInterface;
use Overtrue\Socialite\User;

/**
 * Class OkProvider.
 *
 * @link https://new.vk.com/dev/main [VK API]
 */
class OkProvider extends AbstractProvider implements ProviderInterface
{
    /**
     * Get profile method name
     */
    const METHOD_GET_PROFILE = 'account.getProfileInfo';

    /**
     * The base VK URL.
     *
     * @var string
     */
    protected $apiUrl = 'https://api.ok.ru';

    /**
     * The user fields being requested.
     *
     * @var array
     */
    protected $fields = ['first_name', 'last_name', 'email', 'sex', 'verified',
        'photo_medium',
        'photo_big', 'mobile_phone'];

    /**
     * The scopes being requested.
     *
     * @var array
     */
    protected $scopes = ['email'];

    /**
     * Display the dialog in a popup view.
     *
     * @var bool
     */
    protected $popup = false;

    /**
     * Authorization url
     *
     * @var string
     */
    protected $authUrl = 'https://connect.ok.ru/oauth/authorize';

    /**
     * URL to get access token
     *
     * @var string
     */
    protected $accessTokenUrl = 'https://api.ok.ru/oauth/token.do';

    /**
     * {@inheritdoc}
     */
    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase($this->authUrl, $state);
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenUrl()
    {
        return $this->accessTokenUrl;
    }

    /**
     * Invoke API method
     *
     * @param string $methodName
     * @param string $token
     * @param array $params
     * @return array|boolean
     */
    protected function invokeMethod($methodName, $token, array $params = [])
    {
        $params['access_token'] = $token;
        $response = $this->getHttpClient()->get($this->apiUrl.'/'.$methodName,
            [
            'query' => $params,
            'headers' => [
                'Accept' => 'application/json',
            ],
            'verify' => false,
        ]);
        return json_decode($response->getBody(), true);
    }

    /**
     * Get the access token for the given code.
     *
     * @param string $code
     *
     * @return \Overtrue\Socialite\AccessToken
     */
    public function getAccessToken($code)
    {
        $response = $this->getHttpClient()->get($this->getTokenUrl(),
            [
            'query' => $this->getTokenFields($code),
            'verify' => false,
        ]);
        return $this->parseAccessToken($response->getBody());
    }

    /**
     * Get the POST fields for the token request.
     *
     * @param string $code
     *
     * @return array
     */
//    protected function getTokenFields($code)
//    {
//        $result               = parent::getTokenFields($code);
//        $result['grant_type'] = 'client_credentials';
//        return $result;
//    }

    /**
     * {@inheritdoc}
     */
    protected function parseAccessToken($body)
    {
        return new AccessToken(json_decode($body->getContents(), true));
    }

    /**
     * {@inheritdoc}
     */
    protected function getUserByToken(AccessTokenInterface $token)
    {
        return $this->invokeMethod(self::METHOD_GET_PROFILE, $token->getToken(),
                [
                'fields' => implode(',', $this->fields)
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function mapUserToObject(array $user)
    {
        $firstName = $this->arrayItem($user, 'first_name');
        $lastName  = $this->arrayItem($user, 'last_name');
        $nickName  = $this->arrayItem($user, 'screen_name');
        return new User([
            'id' => $this->arrayItem($user, 'id'),
            'nickname' => $nickName,
            'name' => $firstName.' '.$lastName,
            'email' => $this->arrayItem($user, 'mobile_phone'),
            'avatar' => $avatarUrl.'?type=normal',
            'avatar_original' => $avatarUrl.'?width=1920',
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function getCodeFields($state = null)
    {
        $fields = parent::getCodeFields($state);
        if ($this->popup) {
            $fields['display'] = 'popup';
        }
        return $fields;
    }

    /**
     * Set the user fields to request from Facebook.
     *
     * @param array $fields
     *
     * @return $this
     */
    public function fields(array $fields)
    {
        $this->fields = $fields;
        return $this;
    }

    /**
     * Set the dialog to be displayed as a popup.
     *
     * @return $this
     */
    public function asPopup()
    {
        $this->popup = true;
        return $this;
    }
}