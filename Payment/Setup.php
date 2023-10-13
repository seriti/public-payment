<?php
namespace App\Payment;

use Seriti\Tools\SetupModule;

use Seriti\Tools\BASE_UPLOAD;
use Seriti\Tools\UPLOAD_DOCS;

class Setup extends SetupModule
{
    public function setup() {
        //upload_dir is NOT publically accessible
        $upload_dir = BASE_UPLOAD.UPLOAD_DOCS;
        $this->setUpload($upload_dir,'PRIVATE');

        $param = [];
        $param['info'] = 'Specify payment success html';
        $param['rows'] = 5;
        $param['value'] = '';
        $this->addDefault('HTML','PAYMENT_SUCCESS_HTML','Success HTML',$param);

        $param = [];
        $param['info'] = 'Specify payment failure html';
        $param['rows'] = 5;
        $param['value'] = '';
        $this->addDefault('HTML','PAYMENT_FAILURE_HTML','Failure HTML',$param);

        $param = [];
        $param['info'] = 'Payment Email footer text / contact details';
        $param['rows'] = 5;
        $param['value'] = '';
        $this->addDefault('TEXTAREA','PAYMENT_EMAIL_FOOTER','Email footer',$param);
    }    
}
