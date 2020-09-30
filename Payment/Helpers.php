<?php
namespace App\Payment;

use Exception;
use Seriti\Tools\Calc;
use Seriti\Tools\Csv;
use Seriti\Tools\Html;
use Seriti\Tools\Pdf;
use Seriti\Tools\Doc;
use Seriti\Tools\Date;
use Seriti\Tools\STORAGE;
use Seriti\Tools\SITE_TITLE;
use Seriti\Tools\BASE_UPLOAD;
use Seriti\Tools\UPLOAD_DOCS;
use Seriti\Tools\UPLOAD_TEMP;
use Seriti\Tools\SITE_NAME;
use Seriti\Tools\AJAX_ROUTE;


class Helpers {
       
    
    public static function getProvider($db,$table_prefix,$id_type,$id) 
    {
        $table_provider = $table_prefix.'provider';

        $sql = 'SELECT provider_id,type_id,name,code,config,status '.
               'FROM '.$table_provider.' WHERE ';
        if($id_type === 'CODE') $sql .=  'code ';
        if($id_type === 'ID') $sql .=  'provider_id ';
        $sql .= '= "'.$db->escapeSql($id).'" ';
        $provider = $db->readSqlRecord($sql);
        if($provider != 0 and $provider['type_id'] !== 'EFT_TOKEN' ) {
            //convert config JSON into associative array
            $provider['config'] = json_decode($provider['config'],true);
        }
                
        return $provider;
    }  
    
    //Not currently used. just the basics use /App/Shop/Helpers to get all order details
    public static function getShopOrder($db,$table_prefix,$order_id,&$error) 
    {
        $error = '';

        $table_order = $table_prefix.'order';
        $sql = 'SELECT * FROM '.$table_order.' WHERE order_id = "'.$db->escapeSql($order_id).'" ';
        $order = $db->readSqlRecord($sql);
        if($order == 0) {
            $error .= 'Shop order id['.$order_id.'] does not exist.';
        } else {
            if($order['status'] === 'HIDE') $error .= 'Shop order id['.$order_id.'] no longer valid.';
        }    

        return $order;
    }

    //Not currently used
    public static function saveEftTokenTransact($db,$table_prefix,$provider_id,$source,$source_id,$reference,$amount,$email)
    {
        $error = '';
        $error_tmp = '';
        $data = [];
        
        $table_transact = $table_prefix.'transaction';

        $data['provider_id'] = $this->provider['provider_id'];
        $data['provider_ref'] = 'NA';
        $data['source'] = $source; 
        $data['source_id'] = $source_id; 
        $data['reference'] = $reference;
        $data['amount'] = $amount;
        $data['currency'] = CURRENCY_ID;
        $data['email'] = $email;
        $data['date'] = date('Y-m-d H:i:s');
        $data['status'] = 'NEW';

        $this->db->insertRecord($table_transact,$data,$error_tmp);
        if($error_tmp !== '') {
            $error .= 'Could not save EFT transaction';
            if($this->debug) $error .= ': '.$error_tmp;
        }
        
        if($error === '') return true; else return false;    
    }

    public static function transactionReport($db,$table_prefix,$provider_id,$from_month,$from_year,$to_month,$to_year,$options = [],&$error)
    {
        $error = '';

        $table_provider = $table_prefix.'provider';
        $table_transact = $table_prefix.'transaction';

        $pdf_dir=BASE_UPLOAD.UPLOAD_TEMP;
        //$img_dir=BASE_UPLOAD.UPLOAD_IMAGES;

        $data = [];
        $r = 0;
        $data[0][$r] = "Provider";
        $data[1][$r] = "Status";
        $data[2][$r] = "Transaction count";
        $data[3][$r] = "Total value";
        
        //dates from first day of start month to last day of end month
        $date_from = date('Y-m-d',mktime(0,0,0,$from_month,1,$from_year));
        $date_to = date('Y-m-d',mktime(0,0,0,$to_month+1,0,$to_year));

        $sql = 'SELECT T.provider_id, P.name, T.status, COUNT(*) AS count, SUM(T.amount) AS total '.
               'FROM '.$table_transact.' AS T JOIN '.$table_provider.' AS P ON(T.provider_id = P.provider_id) '.
               'WHERE T.date >= "'. $date_from.'" AND T.date <= "'. $date_to.'" '.
               'GROUP BY T.Provider_id,T.status ';

        $transactions = $db->readSqlArray($sql,false);
        if($transactions == 0) {
            $error .= 'No transactions found with over period.';
            return false;
        } 

        foreach($transactions as $transact) {
            $r++;
            $data[0][$r] = $transact['name'];
            
            $str = '';
            if($transact['status'] !== 'SUCCESS') $str = ' (not completed)';
            $data[1][$r] = $transact['status'].$str;
            
            $data[2][$r] = $transact['count'];;
            $data[3][$r] = CURRENCY_SYMBOL.$transact['total'];
        }

        $base_doc_name = 'payment_transaction_report';
        $page_title = 'All payment transactions from '.$date_from.' to '.$date_to;

        //pdf and csv parameters
        $row_h = 8;
        $col_width=array(30,30,30,30);
        $col_type=array('','','DBL0','CASH2');

        if($options['format'] === 'PDF') {
            $pdf_name=$base_doc_name.date('Y-m-d').'.pdf';
            $doc_name=$pdf_name;

            //$logo=array($img_dir.'logo_new.jpg',5,140,60,22); 
            
            $pdf=new Pdf('Portrait','mm','A4');
            $pdf->AliasNbPages();
              
            $pdf->setupLayout(['db'=>$db]);
            //change setup system setting if there is one
            $pdf->page_title=$page_title;
            //$pdf->bg_image=$logo; 
            $pdf->SetLineWidth(0.1);
            
            //$pdf->footer_text='footer';
    
            //NB footer must be set before this
            $pdf->AddPage();
            $pdf->changeFont('TEXT');
            $pdf_options=array();
            $pdf_options['font_size']=6;

            $pdf->arrayDrawTable($data,$row_h,$col_width,$col_type,'C',$pdf_options);

            //$pdf->mysqlDrawTable($result,$row_h,$col_width,$col_type,'L',$options);
                        
            //$file_path=$pdf_dir.$pdf_name;
            //$pdf->Output($file_path,'F');  
    
            //finally create pdf file to browser
            $pdf->Output($pdf_name,'D');    
            exit;
            
        }
        if($options['format']==='CSV') {
            
            $csv_data = '';
            $doc_name = $base_doc_name.'_on_'.date('Y-m-d').'.csv';
            $csv_data = Csv::arrayDumpCsv($data); 
            
            Doc::outputDoc($csv_data,$doc_name,'DOWNLOAD','csv');
            exit;
            
        }
        
        if($options['format']==='HTML') {
            $html = '<h1>'.$page_title.'</h1>';  
            $html_options = [];
            $html_options['col_type'] = $col_type; 
            $html.=Html::arrayDumpHtml2($data,$html_options); 
          
            $html.='<br/>';
                  
            return $html;
        }     


    }
    
}
?>
