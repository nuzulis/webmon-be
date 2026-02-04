<?php


class CI_Controller {
    /** @var CI_Config */
    public $config;
    /** @var CI_DB_query_builder */
    public $db;
    /** @var CI_Email */
    public $email;
    /** @var CI_Form_validation */
    public $form_validation;
    /** @var CI_Input */
    public $input;
    /** @var CI_Loader */
    public $load;
    /** @var CI_Output */
    public $output;
    /** @var CI_Session */
    public $session;
    /** @var CI_Uri */
    public $uri;
    
    /** @var M_Ptk_Core */
    public $m_ptk_core;
    /** @var M_Tindakan */
    public $m_tindakan;
    /** @var M_Pengguna_Jasa */
    public $m_pj;
    /** @var Kwitansi_model */
    public $Kwitansi_model;
    /** @var KwitansiBatal_model */
    public $KwitansiBatal_model;
    /** @var KwitansiBelumBayar_model */
    public $KwitansiBelumBayar_model;
    /** @var BillingBatal_model */
    public $BillingBatal_model;
}

class CI_Model {
    /** @var CI_Config */
    public $config;
    /** @var CI_DB_query_builder */
    public $db;
    /** @var CI_Loader */
    public $load;
}

class MY_Controller extends CI_Controller {}