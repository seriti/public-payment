<?php 
namespace App\Payment;

use Seriti\Tools\Table;

class Provider extends Table 
{
    
    //configure
    public function setup($param = []) 
    {
        $param = ['row_name'=>'Payment provider','col_label'=>'name'];
        parent::setup($param);
                
        $this->addTableCol(array('id'=>'provider_id','type'=>'INTEGER','title'=>'Provider ID','key'=>true,'key_auto'=>true,'list'=>false));
        $this->addTableCol(array('id'=>'name','type'=>'STRING','title'=>'Name'));
        $this->addTableCol(array('id'=>'code','type'=>'STRING','title'=>'Code','hint'=>'unique code to identify provider from another module'));
        $this->addTableCol(array('id'=>'type_id','type'=>'STRING','title'=>'Type'));
        $this->addTableCol(array('id'=>'config','type'=>'TEXT','title'=>'Configuration','hint'=>'Provider configuration in JSON. Do not modify unless you know what you are doing.'));
        //$this->addTableCol(array('id'=>'sort','type'=>'INTEGER','title'=>'Sort Order','hint'=>'Provider display order in dropdowns'));
        $this->addTableCol(array('id'=>'status','type'=>'STRING','title'=>'Status'));

        $this->addSortOrder('T.sort','Sort order','DEFAULT');

        $this->addAction(array('type'=>'edit','text'=>'edit','icon_text'=>'edit'));
        $this->addAction(array('type'=>'delete','text'=>'delete','icon_text'=>'delete','pos'=>'R'));

        $this->addSelect('type_id',['list'=>PAYMENT_TYPE,'list_assoc'=>true]);

        $status = ['OK','HIDE'];
        $this->addSelect('status',['list'=>$status,'list_assoc'=>false]);
   }
}
?>
