<?php

namespace App\Services\Login;

use Abraham\TwitterOAuth\TwitterOAuth;

use App\Repositories\UserRepository;

class TwitterService
{

    private $user_repository;

    public function __construct(UserRepository $user_repository)
    {
        $this->user_repository = $user_repository;
    }

    /**
     * twtter認証のためのredirect_urlを取得する
     *
     * @return array
     */
    public function getRedirectUrl()
    {

        $this->twitter_oauth = new TwitterOAuth(
            config('twitter.consumer_key'),
            config('twitter.consumer_secret')
        );

        # 認証用のrequest_token取得
        $token = $this->twitter_oauth->oauth('oauth/request_token', array(
            'oauth_callback' => config('twitter.callback_url')
        ));

        # 認証画面へ移動させる
        $url = $this->twitter_oauth->url('oauth/authenticate', array(
            'oauth_token' => $token['oauth_token']
        ));

        return [
            'url' => $url
        ];
    }

    /**
     * twtter認証およびユーザーの紐付けを行う
     *
     * @param string $oauth_token twitterのoauth_token
     * @param string $oauth_token twitterのoauth_verifier
     */
    public function twitterUserVerification(string $oauth_token, string $oauth_verifier){

        # request_tokenからaccess_tokenを取得
        $twitter_oauth = new TwitterOAuth(
            $oauth_token,
            $oauth_verifier
        );

        $token = $twitter_oauth->oauth('oauth/access_token', array(
            'oauth_verifier' => $oauth_verifier,
            'oauth_token' => $oauth_token,
        ));

        // twitter_user_name取得
        $twitter_user = new TwitterOAuth(
            config('twitter.consumer_key'),
            config('twitter.consumer_secret'),
            $token['oauth_token'],
            $token['oauth_token_secret']
        );

        $twitter_user_info = $twitter_user->get('account/verify_credentials');

        $twitter_id = $twitter_user_info->screen_name;

        $twitter_profile_image_url = $twitter_user_info->profile_image_url_https;

        $user_id = $this->user_repository->getOrCreateUserByTwitterID($twitter_id);

        $this->user_repository->update($user_id, [
            'profile_image_url' => $twitter_profile_image_url,
            'twitter_oauth_token' => $token['oauth_token'],
            'twitter_oauth_token_secret' => $token['oauth_token_secret'],
        ]);

        $token   = $this->user_repository->updateToken($user_id);

        //slackの既存のworkspace, channelに紐付け
        $slack_params = [
            'slack_workspace_id' => 1,
            //ここはチャンネルを作成して編集
            'channel_id' => 'CU2T0UT9N'
        ];

        $this->user_repository->createSlackInfo($user_id, $slack_params);

        return [
            'token' => $token
        ];
    }
}
