<?php 
namespace App\Payment;

use Seriti\Tools\Table;

class Transaction extends Table 
{
    //configure
    public function setup($param = []) 
    {
        $param = ['row_name'=>'Transaction','col_label'=>'reference'];
        parent::setup($param);

        $this->modifyAccess(['edit'=>true,'add'=>false]);         
                
        $this->addTableCol(array('id'=>'transaction_id','type'=>'INTEGER','title'=>'Transaction ID','key'=>true,'key_auto'=>true));
        $this->addTableCol(array('id'=>'provider_id','type'=>'INTEGER','title'=>'Provider ID','join'=>'name FROM '.TABLE_PREFIX.'provider WHERE provider_id'));
        $this->addTableCol(array('id'=>'provider_ref','type'=>'STRING','title'=>'Provider Reference'));
        $this->addTableCol(array('id'=>'source','type'=>'STRING','title'=>'Source'));
        $this->addTableCol(array('id'=>'source_id','type'=>'INTETGER','title'=>'Source ID'));
        $this->addTableCol(array('id'=>'reference','type'=>'STRING','title'=>'Source Reference'));
        $this->addTableCol(array('id'=>'date','type'=>'DATETIME','title'=>'Date created'));
        $this->addTableCol(array('id'=>'amount','type'=>'DECIMAL','title'=>'Amount'));
        $this->addTableCol(array('id'=>'currency','type'=>'STRING','title'=>'Currency'));
        $this->addTableCol(array('id'=>'email','type'=>'EMAIL','title'=>'Email'));
        $this->addTableCol(array('id'=>'comment','type'=>'TEXT','title'=>'Comment'));
        //$this->addTableCol(array('id'=>'response','type'=>'TEXT','title'=>'Response','edit'=>false));
        $this->addTableCol(array('id'=>'status','type'=>'STRING','title'=>'Status'));

        $this->addSortOrder('T.transaction_id DESC','Most recent first','DEFAULT');

        $this->addAction(array('type'=>'edit','text'=>'edit','icon_text'=>'edit'));
        $this->addAction(array('type'=>'delete','text'=>'delete','icon_text'=>'delete','pos'=>'R'));

        $this->addSearch(array('provider_id','provider_ref','source','source_id','reference','date','amount','comment','status'),array('rows'=>3));

        $this->addSelect('provider_id','SELECT provider_id,name FROM '.TABLE_PREFIX.'provider WHERE status = "OK" ORDER BY sort');

        $status = ['NEW','ERROR','SUCCESS','CANCELLED'];
        $this->addSelect('status',['list'=>$status,'list_assoc'=>false]);

        $source = ['SHOP','AUCTION','SUBSCRIBE','RESERVE'];
        $this->addSelect('source',['list'=>$source,'list_assoc'=>false]);
    }    
}

?>