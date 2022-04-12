<?php

use Phalcon\Mvc\Controller;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

class ApiController extends Controller
{
    /**
     * Function to Display Book List
     *
     * @return void
     */
    public function indexAction()
    {
        $user = Users :: findFirst($this->session->mydetails['id']);
        if ($user->access_token != null) {
            echo "token present";
            $acc = ['access_token' => $user->access_token];
            $this->session->tokens = $acc;
            $this->response->redirect('/api/me');
            // die;
        }
    }


    /**
     * Function to get grant code
     *
     * @return void
     */
    public function spotifyAction()
    {
        $query = ["client_id" => '8519bff9ffe547619edb31e97eb4ca2b', 'redirect_uri' => 'http://localhost:8080/api/spotifyToken', 'scope' => 'user-read-email playlist-modify-public playlist-read-private playlist-modify-private', 'response_type' => 'code', 'show_dialog' => 'true'];
        $q = http_build_query($query, '', '&');
        $url2 = "https://accounts.spotify.com/authorize?" . $q . "";
        echo $query2 = http_build_query($query);
        $this->response->redirect($url2);   
    }
    /**
     * Function to generate token
     *
     * @return void
     */
    public function spotifyTokenAction()
    {

        $code = $this->request->getQuery('code');

        $clientId = '8519bff9ffe547619edb31e97eb4ca2b';
        $clientSecret = '93442ca67c1d403097e440a109ae437d';
        $url = "https://accounts.spotify.com";

        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Authorization' => 'Basic ' . base64_encode($clientId . ":" . $clientSecret)
        ];

        $client = new Client(
            [

                'base_uri' => $url,
                'headers' => $headers
            ]
        );
        $query = ["grant_type" => 'authorization_code', 'code' => $code, 'redirect_uri' => 'http://localhost:8080/api/spotifyToken'];
        $response = $client->request('POST', '/api/token', ['form_params' => $query]);
        $response =  $response->getBody();
        $response = json_decode($response, true);
        $this->session->tokens = $response;
        $user = Users::findFirst($this->session->mydetails['id']);
        $user->access_token = $this->session->tokens['access_token'];
        $user->refresh_token = $this->session->tokens['refresh_token'];
        $user->update();
        $this->response->redirect('/api/me');
    }
    /**
     * Function to perform search action
     *
     * @return void
     */
    public function searchAction()
    {
        $postdata = $this->request->getPost();

        $this->view->data = $postdata;

        $url = "https://api.spotify.com/";
        $client = new Client(
            [

                'base_uri' => $url,

            ]
        );
        if (count($postdata) != 0) {
            $checked = $postdata['check'];
            $type = "";
            foreach ($checked as $check)
                $type .=  $check . ",";
            $type = substr($type, 0, strlen($type) - 1);
            $query = ['q' => $postdata['search'], 'type' => $type, 'access_token' => $this->session->tokens['access_token']];
            try {

                $response = $client->request('GET', '/v1/search', ['query' => $query]);
            } catch(ClientException $e)
            {
                $this->eventsManager->fire('spotify:refreshToken', $this);
                $response = $client->request('GET', '/v1/search', ['query' => $query]);
            }
            $response = $response->getBody();
            $response = json_decode($response, true);
            $this->view->response = $response;
            $disp = "";
            foreach ($response as $key => $val) {
                $disp .= "<h1 class='text-center text-warning'>" . $key . "</h1><div class='row '>";
                $style = "none";
                if($key == 'tracks')
                $style = "block";
                foreach ($val['items'] as $k => $v) {
                    $disp .= ' <div class="col-3 border border-3  text-wrap p-4 fst-italic text-success" style ="border-radius:1rem;margin:1rem;">
                                <img class="" src="' . $v['images'][0]['url'] . '" alt="Card image" width="100px !important" height="100px !important">
                                <div class="">
                                <h4 class="">Name : ' . $v['name'] . '</h4>
                                <p class="">Popularity : ' . $v['popularity'] . '</p>
                                <a href="/api/selectPlaylist?url=' . $v['uri'] . '" style="display:
                                '.$style.'
                                ">Add to playlist</a>
                                </div>
                            </div>';
                    $this->session->id = $v['id'];
                }
                $disp .= "</div>";
            }
            $this->view->display = $disp;
        }
    }
    /**
     * Function for playlist
     *
     * @return void
     */
    public function selectPlaylistAction()
    {
        $token = $this->session->tokens['access_token'];
        $client = new Client(
            [
                'base_uri' => 'https://api.spotify.com'
            ]
        );
        try {

            $response = $client->request(
                'GET',
                "/v1/me/playlists",
                [
                    'headers' => [
                        'Authorization' => "Bearer " . $token
                    ]
                ]
            );
        } catch(ClientException $e)
        {
            $this->eventsManager->fire('spotify:refreshToken', $this);
            $response = $client->request(
                'GET',
                "/v1/me/playlists",
                [
                    'headers' => [
                        'Authorization' => "Bearer " . $token
                    ]
                ]
            );
            
        }
        $response = json_decode($response->getBody(), true);
        $this->view->response = $response;
        //____________________________________________________get items of playlist_______________________________________
        $items = $response['items'];
        $query = ['access_token' => $this->session->tokens['access_token']];
        $p_disp = '';
        foreach ($items as $key => $val) {
            $response2 = $client->request('GET', '/v1/playlists/'.$val['id'].'/tracks', ['query'=>$query]);
            $response2 =  $response2->getBody();
            $response2 = json_decode($response2, true);
            $p_disp .= '<div class="row">
                                <h1 class="text-center">'.$val['name'].'</h1>
                                <a class="btn btn-danger col-3 align-self-end" href="/api/deletePlaylist/?pId='.$val['id'].'">Delete Playlist</a>
                            <table class="table table-success table-striped">
                            <thead>
                                <tr>
                                <th scope="col">Track Name</th>
                                <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody>';
            foreach ($response2['items'] as $k=>$v) {
                $p_disp .= '<tr>
                            <td>'.$v['track']['album']['name'].'</td>
                            <td><a class="btn btn-danger" href="/api/deleteItem/?uri='.$v['track']['uri'].'&pId='.$val['id'].'">Delete</a></td>
                            </tr>';
            }
            $p_disp .= '</tbody>
                        </table>
                        </div>';
        }
        $this->view->p_disp = $p_disp;
    }
    /**
     * Function to add items to playlist
     *
     * @return void
     */
    public function addToPlaylistAction()
    {
        $pId = $this->request->getQuery('pId');
        $uris = $this->request->getQuery('url');
        $url = "https://api.spotify.com/";
        $client = new Client(
            [
                'base_uri' => $url,
                'headers' => ['Authorization' => 'Bearer ' . $this->session->tokens['access_token']]
            ]
        );
        try {

            $response = $client->request('POST', "/v1/playlists/" . $pId . "/tracks?uris=" . $uris);
        } catch(ClientException $e)
        {
            $this->eventsManager->fire('spotify:refreshToken', $this);
            $response = $client->request('POST', "/v1/playlists/" . $pId . "/tracks?uris=" . $uris);
            
        }
        $this->response->redirect('/api/selectPlaylist');
    }
    /**
     * Function to add new playlist
     *
     * @return void
     */
    public function addNewPlaylistAction()
    {
        $postdata = $this->request->getPost();
        $url = "https://api.spotify.com/";
        $client = new Client(
            [
                'base_uri' => $url,
                'headers' => ['Authorization' => 'Bearer '.$this->session->tokens['access_token']]
            ]
        );
        $args = [
            'name' => $postdata['p_name'],
            'description' => "New Playlist Description",
            'public' => 'false'
        ];
        $id= $this->session->uid;
        try {

            $response = $client->request('POST', '/v1/users/'.$id.'/playlists', ['body' => json_encode($args)]);
        } catch(ClientException $e)
        {
            $this->eventsManager->fire('spotify:refreshToken', $this);
            $response = $client->request('POST', '/v1/users/'.$id.'/playlists', ['body' => json_encode($args)]);
            
        }
        $this->response->redirect('/api/selectPlaylist');
    }
    /**
     * Function to get user details
     *
     * @return void
     */
    public function meAction()
    {
        $url = "https://api.spotify.com/";
        $client = new Client(
            [
            'base_uri' => $url
            ]
        );
        $query = ['access_token' => $this->session->tokens['access_token']];
        try {

            $response = $client->request('GET', "/v1/me", ['query' => $query]);
        } catch(ClientException $e)
        {
            $this->eventsManager->fire('spotify:refreshToken', $this);
            $response = $client->request('GET', "/v1/me", ['query' => $query]);
            
        }
        $response = json_decode($response->getBody(), true);
        $this->session->uid = $response['id'];
        $this->session->userDetails = $response;
        $this->response->redirect('/user/dashboard');
    }

    /**
     * Function to delete item from playlist
     *
     * @return void
     */
    public function deleteItemAction()
    {
        $uri = $this->request->getQuery('uri');
        $pId = $this->request->getQuery('pId');
        $url = "https://api.spotify.com/";
        $client = new Client(
            [
                'base_uri'=>$url,
                'headers' => ['Authorization' => 'Bearer '.$this->session->tokens['access_token']]
            ]
        );

        $arg = ['tracks'=>[['uri'=>$uri]]];
        try {

            $response = $client->request('DELETE', '/v1/playlists/'.$pId.'/tracks', ['body'=>json_encode($arg)]);
        } catch(ClientException $e)
        {
            $this->eventsManager->fire('spotify:refreshToken', $this);
            $response = $client->request('DELETE', '/v1/playlists/'.$pId.'/tracks', ['body'=>json_encode($arg)]);
            
        }
        $this->response->redirect('/api/selectPlaylist');
    }
    /**
     * Function to delete complete playlist
     *
     * @return void
     */
    public function deletePlaylistAction()
    {
        $pId = $this->request->getQuery('pId');
        $url = "https://api.spotify.com/";
        $client = new Client(
            [
                'base_uri' => $url,
                'headers' => ['Authorization' => 'Bearer '.$this->session->tokens['access_token']]
            ]
        );
        try {

            $response = $client->request('DELETE', '/v1/playlists/'.$pId.'/followers');
        } catch(ClientException $e)
        {
            $this->eventsManager->fire('spotify:refreshToken', $this);
            $response = $client->request('DELETE', '/v1/playlists/'.$pId.'/followers');
            
        }
        $this->response->redirect('/api/selectPlaylist');
    }
}
