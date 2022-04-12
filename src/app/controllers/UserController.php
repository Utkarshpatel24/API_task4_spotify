<?php

use Phalcon\Mvc\Controller;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class UserController extends Controller
{
    public function indexAction()
    {
    }
    /**
     * Function for signup 
     *
     * @return void
     */
    public function signupAction()
    {
        $this->view->message= '';
        $postdata=$this->request->getPost();
        if (count($postdata) != 0) {
            
            if ($postdata['password'] == $postdata['password1']) {
                $user=new Users();

                $user->name =$postdata['name'];
                $user->username =$postdata['username'];
                $user->email = $postdata['email'];
                $user->password = $postdata['password'];
                $result = $user->save();
                if($result)
                 $this->view->message = "Successfully registered!! Now wait for approval";
                else
                 {
                     $this->view->message = "Not registered successfully!! Please try again";
                     $this->signupLog->alert("Please Enter Valid details to Sign-up");
                }

            } else {
                $this->view->message = "Password Miss Matched";
                $this->signupLog->alert("Password Miss Matched");
            }
        }

    }
    /**
     * Function for login
     *
     * @return void
     */
    public function loginAction()
    {
        $postdata = $this->request->getPost();
                
        if (count($postdata) != 0 ) {
            $user = Users::find(
                [
                    'conditions' => 'email = ?1 AND password = ?2 ',
                    'bind'       => [
                        1 => $postdata['email'],
                        2 => $postdata['password'],
                        // 3 => 'approved'
                    ]
                ]
            );
            if (count($user) != 0) {


                 $this->session->mydetails = $this->getArray($user[0]);
               
                
                $this->response->redirect('/api/index');
            } else {
                echo "<h1>Invalid user name or password</h1>";
            }
            
        }
    }
    /**
     * Function to print details on dashboard page
     *
     * @return void
     */
    public function dashboardAction()
    {
       
        $url = "https://api.spotify.com/";
        $token = $this->session->tokens['access_token'];
        $client = new Client(
            [
                'base_uri' => $url,
                'headers' => [
                    'Authorization' => "Bearer " . $token
                ]

            ]
        ); 
        $query = ['seed_artists'=>'7dGJo4pcD2V6oG8kP0tJRR', 'seed_genres'=>'hip hop,rap', 'seed_tracks'=>'77IURH5NC56Jn09QHi76is'];
        try {

            $response = $client->request('GET', "/v1/recommendations", ['query'=>$query]);
        } catch(ClientException $e)
        {
            $this->eventsManager->fire('spotify:refreshToken', $this);
            $response = $client->request('GET', "/v1/recommendations", ['query'=>$query]);
        }
        $response = $response->getBody();
        $response = json_decode($response, true);
        // echo "<pre>";
        // print_r($response['tracks']);
        // die;
        $disp = "";
        foreach ($response['tracks'] as $key=>$val) {
            $disp .='<div class="col-2 text-wrap border p-3 text-center text-dark shadow-lg fw-bolder" style="border-radius:0.5rem; margin:1rem;">
            <img class="" src="' . $val['album']['images'][0]['url'] . '" alt="Card image" width="100px !important" height="100px !important">
            <p>Name : '.$val['album']['name'].'</p>
            </div>';
        }
        $this->view->r_disp = $disp;
    }
    /**
     * Function for sign out
     *
     * @return void
     */
    public function signoutAction()
    {
        $this->session->destroy();
        $this->response->redirect('/user/login');
    }

     /**
     * Function to get array of object
     *
     * @param [type] $user
     * @return void
     */
    public function getArray($user)
    {
        return array(
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'password' => $user->password,

        );
    }
}
