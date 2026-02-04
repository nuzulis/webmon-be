<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$config['auth_header'] = getenv('PRIOR_AUTH_HEADER') ?: 'Basic default_if_empty';