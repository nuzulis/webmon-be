<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * @property CI_Input  $input
 * @property CI_Output $output
 * @property CI_Config $config
 * @property CI_DB_query_builder $db_ums
 * @property CI_User_agent $agent
 */

class Auth extends MY_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->load->helper(['jwt', 'security']);
        $this->load->config('jwt');
        $this->db_ums = $this->load->database('ums', TRUE);
        date_default_timezone_set('Asia/Jakarta');
    }

   public function login()
{
    $input = json_decode($this->input->raw_input_stream, true);
    $username = trim($input['username'] ?? $this->input->post('username', TRUE));
    $password = $input['password'] ?? $this->input->post('password', TRUE);

    $ums = $this->umsLogin($username, $password); 
    
    if (!$ums['success']) {
        return $this->jsonRes(401, ['success' => false, 'message' => $ums['message']]);
    }

    $userData = $ums['data']; 
    $roles = $userData['detil'] ?? [];

    $payload = [
        'sub'   => $userData['uid'],
        'uname' => $userData['uname'],
        'nama'  => $userData['nama'], 
        'upt'   => (string)$userData['upt'],
        'detil' => $roles, 
        'iat'   => time(),
        'exp'   => time() + (int)($this->config->item('jwt_expire') ?: 86400)
    ];

    $key = $this->config->item('jwt_key') ?: "SECRET_123";
    $token = jwt_encode($payload, $key);

    return $this->jsonRes(200, [
        'success' => true,
        'token'   => $token,
        'user'    => [
            'nama' => $payload['nama'],
            'upt'  => $payload['upt']
        ]
    ]);
}



    private function umsLogin($username, $password)
{
    $url = 'https://api.karantinaindonesia.go.id/ums/login';
    $ch = curl_init($url);
    
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode(['username' => $username, 'password' => $password]),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
        CURLOPT_ENCODING       => '',
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 20,
    ]);

    $response = curl_exec($ch);
    $errno = curl_errno($ch);
    $error_msg = curl_error($ch);
    curl_close($ch);

    if ($errno) {
        return [
            'success' => false, 
            'message' => "Koneksi Pusat Gagal: " . $error_msg
        ];
    }

    $res = json_decode($response, true);
    
   if (isset($res['status']) && ($res['status'] == 200 || $res['status'] == "200") && !empty($res['data'])) {
        return [
            'success' => true, 
            'data'    => $res['data']
        ];
    }

    return [
        'success' => false, 
        'message' => isset($res['message']) ? $res['message'] : 'Username/Password salah'
    ];
}

        protected function jsonRes(int $status, array $data)
    {
        return $this->output
            ->set_status_header($status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($data, JSON_UNESCAPED_UNICODE));
    }
    

}