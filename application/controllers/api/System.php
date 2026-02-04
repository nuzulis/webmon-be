<?php 
class System extends CI_Controller
{
    public function info()
    {
        echo json_encode([
            'app' => getenv('APP_NAME'),
            'env' => getenv('APP_ENV'),
            'db'  => getenv('APP_ENV') === 'prod' ? 'SERVER PUSAT' : 'LOCAL',
            'time'=> date('Y-m-d H:i:s')
        ]);
    }
}
