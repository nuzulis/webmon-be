<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$config['auth_header'] = getenv('ECERT_AUTH_HEADER') ?: 'Basic default_if_empty';