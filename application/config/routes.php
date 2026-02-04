<?php
defined('BASEPATH') OR exit('No direct script access allowed');
$route['api/monitoring'] = 'api/monitoring/index';
$route['api/monitoring/count'] = 'api/monitoring/count';
$route['api/monitoring/(:any)'] = 'api/monitoring/$1';
$route['api/carikuitansi'] = 'api/carikuitansi/index';

/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
| This file lets you re-map URI requests to specific controller functions.
|
| Typically there is a one-to-one relationship between a URL string
| and its corresponding controller class/method. The segments in a
| URL normally follow this pattern:
|
|	example.com/class/method/id/
|
| In some instances, however, you may want to remap this relationship
| so that a different class/function is called than the one
| corresponding to the URL.
|
| Please see the user guide for complete details:
|
|	https://codeigniter.com/userguide3/general/routing.html
|
| -------------------------------------------------------------------------
| RESERVED ROUTES
| -------------------------------------------------------------------------
|
| There are three reserved routes:
|
|	$route['default_controller'] = 'welcome';
|
| This route indicates which controller class should be loaded if the
| URI contains no data. In the above example, the "welcome" class
| would be loaded.
|
|	$route['404_override'] = 'errors/page_missing';
|
| This route will tell the Router which controller/method to use if those
| provided in the URL cannot be matched to a valid route.
|
|	$route['translate_uri_dashes'] = FALSE;
|
| This is not exactly a route, but allows you to automatically route
| controller and method names that contain dashes. '-' isn't a valid
| class or method name character, so it requires translation.
| When you set this option to TRUE, it will replace ALL dashes in the
| controller and method URI segments.
|
| Examples:	my-controller/index	-> my_controller/index
|		my-controller/my-method	-> my_controller/my_method
*/
$route['api/caridokumen/search'] = 'api/caridokumen/search';
$route['api/caridokumen']        = 'api/caridokumen';
$route['default_controller'] = 'welcome';
$route['404_override'] = '';
$route['translate_uri_dashes'] = FALSE;
$route['api/auth/login'] = 'api/auth/login';
$route['api/dashboard/freq-3p']         = 'api/dashboard/freq_3p';
$route['api/dashboard/freq-permohonan'] = 'api/dashboard/freq_permohonan';

$route['api/dashboard/sla-ekspor'] = 'api/dashboard/sla_ekspor';
$route['api/dashboard/sla-impor']  = 'api/dashboard/sla_impor';
$route['api/dashboard/test-db'] = 'api/dashboard/test_db';
$route['api/dashboard/top-komoditi'] = 'api/dashboard/top_komoditi';
$route['api/carikuitansi']        = 'api/carikuitansi';
$route['api/elab/detail/(:any)'] = 'ElabDetail/detail/$1';
$route['api/pemeriksaanlapangan'] = 'api/PeriksaLapangan/index';
$route['api/pemeriksaanlapangan/export_excel'] = 'api/PeriksaLapangan/export_excel';
$route['pemeriksaan-lapangan/detail/(:any)'] = 'PeriksaLapangan/detail/$1';

$route['api/system/info'] = 'api/system/info';


