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
        // Support JSON input (React)
        $input = json_decode($this->input->raw_input_stream, true);
        $username = trim($input['username'] ?? $this->input->post('username', TRUE));
        $password = $input['password'] ?? $this->input->post('password', TRUE);

        if (!$username || !$password) {
            return $this->jsonRes(400, ['success' => false, 'message' => 'Username/Password wajib diisi']);
        }

        // 1. Ambil User
        $user = $this->db_ums->where('username', $username)
                             ->where('deleted_at IS NULL', null, false)
                             ->get('users')->row();

        if (!$user || (int)$user->active !== 1) {
            return $this->jsonRes(401, ['success' => false, 'message' => 'Akun tidak aktif atau tidak ditemukan']);
        }

        // 2. Simulasi/Validasi UMS (Pastikan fungsi umsLogin Anda mengembalikan array success)
        $ums = $this->umsLogin($username, $password); 
        if (!$ums['success']) {
            $this->logActivity($user->id, $user->username, "LOGIN FAILED: UMS");
            return $this->jsonRes(401, ['success' => false, 'message' => 'Login gagal']);
        }

        // 3. Ambil Roles
        $roles = $this->db_ums->select('r.role_name, r.apps_id')
            ->from('role_user ru')
            ->join('roles r', 'r.id = ru.roles_id')
            ->where(['ru.users_id' => $user->id, 'ru.active' => 1])
            ->get()->result_array();

        if (empty($roles)) {
            return $this->jsonRes(403, ['success' => false, 'message' => 'User tidak punya role']);
        }

        // 4. Build Payload
        $payload = [
            'sub'   => $user->id,
            'uname' => $user->username,
            'nama'  => $user->nama,
            'upt'   => (string)($user->upt_id ?? '0'),
            'detil' => $roles,
            'iat'   => time(),
            'exp'   => time() + (int)$this->config->item('jwt_expire')
        ];

        $token = jwt_encode($payload, "");
        $this->logActivity($user->id, $user->username, "LOGIN SUCCESS: 008-Webmon");
        return $this->jsonRes(200, [
            'success' => true,
            'token'   => $token,
            'user'    => ['nama' => $user->nama, 'upt' => $user->upt_id]
        ]);
    }

    protected function jsonRes(int $status, array $data)
    {
        return $this->output
            ->set_status_header($status)
            ->set_content_type('application/json', 'utf-8')
            ->set_output(json_encode($data, JSON_UNESCAPED_UNICODE));
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
            CURLOPT_TIMEOUT        => 15,
        ]);

        $response = curl_exec($ch);
        if ($response === false) return ['success' => false, 'message' => 'UMS Connection Error'];
        curl_close($ch);

        $res = json_decode($response, true);
        if (isset($res['status']) && $res['status'] == '200') return ['success' => true];

        return ['success' => false, 'message' => $res['message'] ?? 'Login UMS gagal'];
    }

    private function jsonResponse($status, $data) {
        return $this->output->set_status_header($status)
            ->set_content_type('application/json')
            ->set_output(json_encode($data));
    }
}