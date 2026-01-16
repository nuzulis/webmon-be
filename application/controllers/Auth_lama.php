<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use Firebase\JWT\JWT;

/**
 * @property Auth_model $Auth_model
 */
class Auth extends MY_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model('Auth_model');
        $this->load->config('jwt');
    }

    public function login()
{
    $username = trim($this->input->post('username'));
    $password = trim($this->input->post('password'));

    if (!$username || !$password) {
        return $this->respond(false, 'Username dan password wajib diisi');
    }

    $user = $this->Auth_model->get_user_by_username($username);
    if (!$user) {
        return $this->respond(false, 'User tidak ditemukan');
    }

    if (!$this->Auth_model->verify_password($user, $password)) {
        return $this->respond(false, 'Password salah');
    }

    $roles = $this->Auth_model->get_user_roles((int)$user['id']);
    if (empty($roles)) {
        return $this->respond(false, 'User tidak memiliki role aktif');
    }
$jwtExpire = $this->config->item('jwt_expire');

$this->output
    ->set_content_type('application/json')
    ->set_output(json_encode([
        'debug' => [
            'jwt_expire' => $jwtExpire,
            'jwt_key_exists' => (bool) $this->config->item('jwt_key'),
            'config_loaded' => 'jwt'
        ]
    ]))
    ->_display();

exit;

    // 🔥 CEK EXPIRE DI SINI
    $jwtExpire = (int) $this->config->item('jwt_expire');
    log_message('info', 'JWT expire: ' . $jwtExpire);

if ($jwtExpire <= 0) {
    $jwtExpire = 3600;
    }

    $payload = [
        'id'        => $user['id'],
        'uname'     => $user['username'],
        'nama'      => $user['nama'],
        'nip'       => $user['nip'],
        'nik'       => $user['nik'],
        'email'     => $user['email'],
        'idpegawai' => $user['pegawai_id'],
        'upt'       => $user['upt_id'],
        'detil'     => $roles,
        'iat'       => time(),
        'exp'       => time() + $jwtExpire
    ];

    $token = JWT::encode(
        $payload,
        $this->config->item('jwt_key'),
        $this->config->item('jwt_algorithm')
    );

    return $this->respond(true, 'Login berhasil', [
        'token' => $token,
        'user'  => $payload
    ]);
}


    protected function respond(bool $success, string $message, array $data = [])
    {
        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode([
                'success' => $success,
                'message' => $message,
                'data'    => $data
            ]))
            ->_display();
        exit;
    }
}
