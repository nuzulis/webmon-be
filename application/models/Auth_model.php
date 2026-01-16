<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Auth_model extends CI_Model
{
    public function get_user_by_username(string $username)
    {
        return $this->db
            ->select('id, nama, username, nip, nik, email, upt_id')
            ->from('db_ums.users')
            ->where('username', $username)
            ->limit(1)
            ->get()
            ->row_array();
    }

    public function get_user_roles(int $user_id): array
    {
        return $this->db
            ->select('r.role_name, a.apps_id')
            ->from('db_ums.role_user ru')
            ->join('db_ums.roles r', 'r.id = ru.roles_id')
            ->join('db_ums.apps a', 'a.id = r.apps_id')
            ->where('ru.users_id', $user_id)
            ->where('ru.active', 1)
            ->get()
            ->result_array();
    }

    /**
     * OPTIONAL:
     * Kalau pakai password lokal (bukan SSO)
     */
    public function verify_password(array $user, string $password): bool
    {
        // contoh:
        // return password_verify($password, $user['password']);
        return true;
    }
}
