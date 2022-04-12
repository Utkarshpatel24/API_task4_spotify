<?php

use Phalcon\Di\Injectable;
use GuzzleHttp\Client;

class TokenListener extends Injectable
{
    /**
     * Function to refresh token
     *
     * @return void
     */
    public function refreshToken()
    {
        // print_r($this->session);
        $user = Users :: findFirst($this->session->mydetails['id']);
        $r_token  = $user->refresh_token;
        $client_id = '8519bff9ffe547619edb31e97eb4ca2b';
        $client_secret = '93442ca67c1d403097e440a109ae437d';
        $url = "https://accounts.spotify.com/";
        $client = new Client(
            [
                'base_uri' => $url,
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Authorization' => 'Basic '. base64_encode($client_id.":".$client_secret)
                    ]
            ]
        );
        $arg = ['grant_type'=>'refresh_token', 'refresh_token'=>$r_token];
        $response = $client->request('POST', "/api/token", ['form_params'=>$arg]);
        $response = $response->getBody();
        $response = json_decode($response, true);
        $user->access_token = $response['access_token'];
        $user->update();
        $token = ['access_token'=>$response['access_token']];
        $this->session->tokens = $token;
        // $this->response->redirect('/user/dashboard');
        // return;

    }

}