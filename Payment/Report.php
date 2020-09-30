<?php
namespace App\Payment;

use Seriti\Tools\CURRENCY_ID;
use Seriti\Tools\Form;
use Seriti\Tools\Report AS ReportTool;

use App\Payment\Helpers;

class Report extends ReportTool
{
     

    //configure
    public function setup() 
    {
        //$this->report_header = 'WTF';
        $param = [];
        $this->report_select_title = 'Select report';
        $this->always_list_reports = true;

        $param = ['input'=>['select_provider','select_month_period','select_format']];
        $this->addReport('TRANSACTIONS','Monthly transaction attempts',$param); 
        //$this->addReport('TRANSACTIONS_CHART','Monthly transactions chart',$param);

        
        $this->addInput('select_provider','');
        $this->addInput('select_month_period',''); 
        //$this->addInput('select_status','');
        $this->addInput('select_format','');
    }

    protected function viewInput($id,$form = []) 
    {
        $html = '';
        
        if($id === 'select_provider') {
            $param = [];
            $param['class'] = 'form-control input-medium';
            $param['xtra'] = ['ALL'=>'All providers'];
            $sql = 'SELECT provider_id,name FROM '.TABLE_PREFIX.'provider WHERE status = "OK" ORDER BY sort'; 
            if(isset($form['provider_id'])) $provider_id = $form['provider_id']; else $provider_id = 'ALL';
            $html .= Form::sqlList($sql,$this->db,'provider_id',$provider_id,$param);
        }

        if($id === 'select_month_period') {
            $past_years = 10;
            $future_years = 0;

            $param = [];
            $param['class'] = 'form-control input-small input-inline';
            
            $html .= 'From:';
            if(isset($form['from_month'])) $from_month = $form['from_month']; else $from_month = 1;
            if(isset($form['from_year'])) $from_year = $form['from_year']; else $from_year = date('Y');
            $html .= Form::monthsList($from_month,'from_month',$param);
            $html .= Form::yearsList($from_year,$past_years,$future_years,'from_year',$param);
            $html .= '&nbsp;&nbsp;To:';
            if(isset($form['to_month'])) $to_month = $form['to_month']; else $to_month = date('m');
            if(isset($form['to_year'])) $to_year = $form['to_year']; else $to_year = date('Y');
            $html .= Form::monthsList($to_month,'to_month',$param);
            $html .= Form::yearsList($to_year,$past_years,$future_years,'to_year',$param);
        }

        if($id === 'select_status') {
            $param = [];
            $param['class'] = 'form-control input-medium';
            $status_arr = ['ALL'=>'All transactions','NEW'=>'NEW unfinished transactions','ERROR'=>'ERROR transactions','SUCCESS'=>'SUCCESSful transactions','CANCELLED'=>'CANCELLED transactions'];
            if(isset($form['status'])) $status = $form['status']; else $status = 'ALL';
            $html .= Form::arrayList($status_arr,'status',$status,true,$param);
        }

        if($id === 'select_format') {
            if(isset($form['format'])) $format = $form['format']; else $format = 'HTML';
            $html.= Form::radiobutton('format','PDF',$format).'&nbsp;<img src="/images/pdf_icon.gif">&nbsp;PDF document<br/>';
            $html.= Form::radiobutton('format','CSV',$format).'&nbsp;<img src="/images/excel_icon.gif">&nbsp;CSV/Excel document<br/>';
            $html.= Form::radiobutton('format','HTML',$format).'&nbsp;Show on page<br/>';
        }

        return $html;       
    }

    protected function processReport($id,$form = []) 
    {
        $html = '';
        $error = '';
        $options = [];
        $options['format'] = $form['format'];

        if($form['provider_id'] === 'ALL') {
            $html .= '<h2>(ALL payment providers, values expressed in currency - '.CURRENCY_ID.')</h2>';
        } else {
            $provider = Helpers::getProvider($this->db,TABLE_PREFIX,$form['provider_id']);
            $html .= '<h2>('.$provider['name'].', values expressed in currency - '.CURRENCY_ID.')</h2>';
        }    
        
        if($id === 'TRANSACTIONS') {
            $html .= Helpers::transactionReport($this->db,TABLE_PREFIX,$form['provider_id'],$form['from_month'],$form['from_year'],$form['to_month'],$form['to_year'],$options,$error);
            if($error !== '') $this->addError($error);
        }

        if($id === 'TRANSACTIONS_CHART') {
            //$html .= Helpers::getPortfolioChart($this->db,'performance',$form['portfolio_id'],$form['currency_id'],$form['from_month'],$form['from_year'],$form['to_month'],$form['to_year'],$options,$error);
            $error = 'Not coded yet';
            if($error !== '') $this->addError($error);
        }
                

        return $html;
    }

}