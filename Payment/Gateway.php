<?php
namespace App\Payment;

use Exception;
use Seriti\Tools\Date;
use Seriti\Tools\Form;
use Seriti\Tools\Validate;
use Seriti\Tools\Secure;
use Seriti\Tools\DbInterface;
use Seriti\Tools\IconsClassesLinks;
use Seriti\Tools\MessageHelpers;
use Seriti\Tools\ContainerHelpers;

use Psr\Container\ContainerInterface;

use App\Payment\Helpers;

//NB: can be called without any user validation (user_id = 0)
class Gateway {
       
    use IconsClassesLinks;
    use MessageHelpers;
    use ContainerHelpers;
   
    protected $container;
    protected $container_allow = ['user','system'];

    protected $db;
    protected $system;
    protected $debug = false;
    protected $debug_log = false;

    protected $mode = '';
    protected $errors = array();
    protected $errors_found = false; 
    protected $messages = array();

    protected $locale = 'en-za';
    protected $country = 'ZAF';
    protected $currency = 'ZAR';
    protected $merchant_id = '';
    protected $return_url = '';
    protected $notify_url = '';

    protected $user_id;
    protected $provider;
    protected $table_prefix;
    protected $source;

    public function __construct(DbInterface $db, ContainerInterface $container) 
    {
        $this->db = $db;
        $this->container = $container;
        $this->system = $this->getContainer('system');

        if(defined('\Seriti\Tools\DEBUG')) $this->debug = \Seriti\Tools\DEBUG;

        $module = $this->container->config->get('module','payment');
        $this->table_prefix = $module['table_prefix'];
        if(isset($module['debug_log'])) $this->debug_log = $module['debug_log'];
 
        //will return 0 if user not logged in or accessed as an api
        $this->user_id = $this->getContainer('user')->getId();
    }

    public function setup($source,$provider_id)
    {
        //must be 'SHOP' 'AUCTION' capitalised module name.
        $this->source = $source;

        $this->provider = Helpers::getProvider($this->db,$this->table_prefix,'ID',$provider_id);
        if($this->provider == 0) {
            throw new Exception('PAYMENT_GATEWAY_ERROR: Provider ID not valid');
            exit;
        } else {
            $this->merchant_id = $this->provider['config']['merchant_id'];
            $this->return_url = $this->provider['config']['return_url'];
            if(isset($this->provider['config']['notify_url'])) $this->notify_url = $this->provider['config']['notify_url'];
            if(isset($this->provider['config']['locale'])) $this->locale = $this->provider['config']['locale'];
            if(isset($this->provider['config']['country'])) $this->country = $this->provider['config']['country'];
            if(isset($this->provider['config']['currency'])) $this->currency = $this->provider['config']['currency'];
        } 
    }

    //process gateway messages
    public function process($process_id,$form)
    {
        $html = '';
            
        if($process_id === 'CONFIRM') {
            $html .= $this->confirmPayment($form);
            //place messages/errors before confirmation html
            $html = $this->viewMessages().$html;
        }  

        //NB1: Notify process is called by gateway server and response is NOT for human consumption.
        //NB2: Notify process can be called BEFORE confirm process 
        //NB3: Output is plain text and not html
        if($process_id === 'NOTIFY') {  
            $html .= $this->notifyPayment($form);
        }    

        return $html; 
    }

    //NB: this produces error/message output for public users
    protected function confirmPayment($data)
    {
        $error = '';
        $error_tmp = '';
        $comment = '';
        $response = '';;
        $transaction_status = 'FAILURE';
        $update_transaction = false;

        if($this->provider['code'] === 'DPO_PAYGATE') {
            $key = $this->provider['config']['key'];
            
            $provider_ref = Secure::clean('basic',$data['PAY_REQUEST_ID']);
            $provider_status = Secure::clean('basic',$data['TRANSACTION_STATUS']);
            $checksum = Secure::clean('basic',$data['CHECKSUM']);

            $transaction = $this->getTransaction('PROVIDER',$provider_ref,$error);

            //NB: if NOTIFY_URL set this is called before RETURN_URL so transaction may have been confirmed already 
            if($error === '') {
                $check = [];
                $check[] = $this->merchant_id;
                $check[] = $provider_ref;
                $check[] = $provider_status;
                $check[] = $transaction['reference'];

                $checksum_test = md5(implode('',$check).$key);
                if($checksum_test !== $checksum) {
                    $error .= 'Payment gateway confirmation checksum invalid';
                    if($this->debug) $error .= 'data='.json_encode($data).' check='.json_encode($check).' md5='.$checksum_test;
                } else {
                    //convert provider status to internal status
                    $transaction_status = $this->getStatus($provider_status,$comment); 
                    $comment = 'DPO paygate: '.$comment.' ref['.$provider_ref.'] ';

                    if($transaction_status !== $transaction['status']) $update_transaction = true;    
                }
            }
        }

        if($error === '') {
            if($transaction_status === 'SUCCESS') {
                $msg = 'Your payment of <b>'.CURRENCY_SYMBOL.number_format($transaction['amount'],2).'</b> for <b>'.$transaction['reference'].'</b> has been CONFIRMED by '.$this->provider['name'];
                $this->addMessage($msg);    
            } else {
                $clean = false;
                $msg = 'Your payment of <b>'.CURRENCY_SYMBOL.number_format($transaction['amount'],2).'</b> for <b>'.$transaction['reference'].'</b> has NOT been processed by '.$this->provider['name'];
                $this->addError($msg,$clean);
                $this->addError($comment,$clean);    
            } 
            

            if($update_transaction) {
                $this->updateTransaction($transaction,$transaction_status,$comment,$data,$error_tmp);
                if($error_tmp !== '') {
                    $error .= 'WE could not update your order details, please contact us. ';
                    if($this->debug) $error .= $error_tmp;
                }    
            }
                
        }

        if($error !== '') $this->addError($error);
        
    }

    //NB: this produces plain text output to calling gateway.
    protected function notifyPayment($data)
    {
        $gateway_response = '';
        $error = '';
        $error_tmp = '';
        $comment = '';
        $transaction_status = '';
        $update_transaction = false;

        if($this->provider['code'] === 'DPO_PAYGATE') {
            $key = $this->provider['config']['key'];
            
            $provider_ref = Secure::clean('basic',$data['PAY_REQUEST_ID']);
            $provider_status = Secure::clean('basic',$data['TRANSACTION_STATUS']);
            $checksum = Secure::clean('basic',$data['CHECKSUM']);

            $transaction = $this->getTransaction('PROVIDER',$provider_ref,$error);

            //NB: NOTIFY_URL will be called 3 times every 30 minutes or until 'OK' response received
            if($error === '') {
                $update_transaction = true;
                
                $check = [];
                $check[] = $this->merchant_id; //must be same as $data['PAYGATE_ID']
                $check[] = $provider_ref; //$data['PAY_REQUEST_ID']
                $check[] = $transaction['reference']; //must be same as $data['REFERENCE']
                $check[] = $provider_status; //$data['TRANSACTION_STATUS'];
                $check[] = $data['RESULT_CODE'];
                if($data['AUTH_CODE'] !== 'null') $check[] = $data['AUTH_CODE'];
                $check[] = $data['CURRENCY'];
                $check[] = $data['AMOUNT'];
                $check[] = $data['RESULT_DESC'];
                $check[] = $data['TRANSACTION_ID'];
                $check[] = $data['RISK_INDICATOR'];
                $check[] = $data['PAY_METHOD'];
                $check[] = $data['PAY_METHOD_DETAIL'];
                if(isset($data['USER1'])) $check[] = $data['USER1'];
                if(isset($data['USER2'])) $check[] = $data['USER2'];
                if(isset($data['USER3'])) $check[] = $data['USER3'];
                if(isset($data['VAULT_ID'])) {
                    $check[] = $data['VAULT_ID'];
                    $check[] = $data['PAYVAULT_DATA_1'];
                    $check[] = $data['PAYVAULT_DATA_2'];
                }    
                
                $checksum_test = md5(implode('',$check).$key);
                if($checksum_test !== $checksum) {
                    $gateway_response = 'ERROR';
                    $transaction_status = 'ERROR';
                    $comment .= 'Gateway notification checksum invalid.';
                    if($this->debug) $comment .= 'data='.json_encode($data).' check='.json_encode($check).' md5='.$checksum_test;
                } else {
                    $gateway_response = 'OK';
                    //convert provider status to internal status
                    $transaction_status = $this->getStatus($provider_status,$comment); 
                    $comment = 'DPO paygate: '.$comment.' ref['.$provider_ref.'] ';
                }
            

            }
        }

        if($error === '') {
            if($update_transaction) {
                $this->updateTransaction($transaction,$transaction_status,$comment,$data,$error_tmp);
                if($error_tmp !== '') $error .= 'Notify payment: Could not update order details:'.$error_tmp;
            }
        } 

        if($error !== '') {
            //this will appear in application log if debug = false
            throw new Exception('PAYMENT_GATEWAY_ERROR: Gateway notify url error:'.$error);
            exit;
        }

        //NB: this is payment gateway specific 
        return $gateway_response;
    }

    //get internal status based on provider status
    protected function getStatus($provider_code,&$comment) 
    {   
        $status = 'ERROR';
        $comment = '';

        if($this->provider['code'] === 'DPO_PAYGATE') {
            $arr = [];
            $arr[0] = 'Not done';
            $arr[1] = 'Approved';
            $arr[2] = 'Declined';
            $arr[3] = 'Cancelled';
            $arr[4] = 'User Cancelled';
            $arr[5] = 'Received by PayGate';
            $arr[6] = 'NOT SPECIFIED';
            $arr[7] = 'Settlement Voided';

            if($provider_code == 1) $status = 'SUCCESS';
            if($provider_code == 3 or $provider_code == 4) $status = 'CANCELLED';

            $comment = $arr[$provider_code];
        }


        return $status;
    }

    //translate gateway result code, not used atm as already provided in notify post data
    protected function getResult($result_code,&$comment) 
    {   
        $comment = '';
        $result = 'ERROR';

        if($this->provider['code'] === 'DPO_PAYGATE') {
            switch($result_code) {
                case '900001' : $comment = 'Call for Approval'; break;
                case '900002' : $comment = 'Card Expired'; break;
                case '900003' : $comment = 'Insufficient Funds'; break;
                case '900004' : $comment = 'Invalid Card Number'; break;
                case '900005' : $comment = 'Bank Interface Timeout'; break;
                case '900006' : $comment = 'Invalid Card'; break;
                case '900007' : $comment = 'Declined'; break;
                //case '900008' : $comment = ''; break;
                case '900009' : $comment = 'Lost Card'; break;
                case '900010' : $comment = 'Invalid Card Length'; break;
                case '900011' : $comment = 'Suspected Fraud'; break;
                case '900012' : $comment = 'Card Reported as Stolen'; break;
                case '900013' : $comment = 'Restricted Card'; break;
                case '900014' : $comment = 'Excessive Card Usage'; break;
                case '900015' : $comment = 'Card Blacklisted'; break;
                case '900019' : $comment = 'Invalid PayVault Scope'; break;
                case '900207' : $comment = 'Declined; authentication failed'; break;
                case '900209' : $comment = 'Transaction verification failed (phase 2)'; break;
                case '900210' : $comment = 'Authentication complete; transaction must be restarted. Most likely caused by a customer clicking the refresh button. '; break;
                case '990013' : $comment = 'Error processing a batch transaction'; break;
                case '990020' : $comment = 'Auth Declined'; break;
                case '990024' : $comment = 'Duplicate Transaction Detected. Please check before submitting'; break;
                case '990028' : $comment = 'Transaction cancelled'; break;
                case '900210' : $comment = '3D Secure Lookup Timeout'; break;
                case '991001' : $comment = 'Invalid expiry date'; break;
                case '991002' : $comment = 'Invalid Amount'; break;
                case '990017' : $comment = 'Auth Done'; $result = 'SUCCESS'; break;

            }
        }


        return $result;
    }

    //NB: make sure return_url and notify_url are valid routes
    protected function initialiseDpoPaygate($reference,$amount,$email,&$error)
    {
        $error = '';
        $error_tmp = '';
        $key = '';

        //encryption key set in the Merchant Access Portal and specfied in provider config json
        if(defined('MERCHANT_KEY')) {
            $key = MERCHANT_KEY;    
        } else {
            $key = $this->provider['config']['key'];
        }

        if($key === '') {
            throw new Exception('PAYMENT_GATEWAY_ERROR: DPO Paygate merchant key not specified!');
            exit;
        }    
        
        //NB: expects amount in cents
        $data = array(
            'PAYGATE_ID'        => $this->merchant_id,
            'REFERENCE'         => $reference,
            'AMOUNT'            => $amount*100,
            'CURRENCY'          => $this->currency,
            'RETURN_URL'        => $this->return_url,
            'TRANSACTION_DATE'  => date('Y-m-d H:i:s'),
            'LOCALE'            => $this->locale,
            'COUNTRY'           => $this->country,
            'EMAIL'             => $email,
        );

        if($this->notify_url !== '') $data['NOTIFY_URL'] = $this->notify_url;

        if($this->debug_log) {
            $this->container->logger->addInfo('Payment initialise:',$data);
        }

        $checksum = md5(implode('',$data).$key);

        $data['CHECKSUM'] = $checksum;

        $field_string = http_build_query($data);

        //open connection
        $ch = curl_init();

        //set the url, number of POST vars, POST data
        curl_setopt($ch, CURLOPT_URL, 'https://secure.paygate.co.za/payweb3/initiate.trans');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_NOBODY, false);
        curl_setopt($ch, CURLOPT_REFERER, $_SERVER['HTTP_HOST']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $field_string);

        //execute post
        $result_str = curl_exec($ch);
        $error_tmp .= curl_error($ch);

        //close connection
        curl_close($ch);

        if($error_tmp !== '') {
            $error .= 'Could not call Payment gateway.';
            if($this->debug) $error .= ': '.$error_temp;
        } else {
            parse_str($result_str,$result_arr);

            $output = [];
            $checksum = $result_arr['CHECKSUM'];
            $output['PAYGATE_ID'] = $result_arr['PAYGATE_ID'];
            $output['PAY_REQUEST_ID'] = $result_arr['PAY_REQUEST_ID'];
            $output['REFERENCE'] = $result_arr['REFERENCE'];

            $checksum_test = md5(implode('',$output).$key);
            if($checksum_test !== $checksum) {
                $error .= 'Payment gateway initialise values checksum invalid';
                if($this->debug) $error .= 'gateway['.$checksum.'] calculated['.$checksum_test.'] '.$result_str;
            } else {
                $output['CHECKSUM'] = $checksum;
            }

            if($reference !== $output['REFERENCE']) {
                $error .= 'Transaction initialise reference mismatch.';
                if($this->debug) $error .= 'gateway['.$output['REFERENCE'].'] actual['.$reference.']';
            } 

            if($this->merchant_id !== $output['PAYGATE_ID']) {
                $error .= 'Transaction merchant id mismatch.';
                if($this->debug) $error .= 'gateway['.$output['PAYGATE_ID'].'] actual['.$this->merchant_id.']';
            }    
        }

        if($error === '') return $output; else return false; 
    }    

    protected function getTransaction($id_type,$id,&$error)
    {  
        $error = '';

        $table_transact = $this->table_prefix.'transaction';
        $sql = 'SELECT * FROM '.$table_transact.' '.
               'WHERE provider_id = "'.$this->db->escapeSql($this->provider['provider_id']).'" AND ';

        if($id_type === 'ID') $sql .= 'transaction_id = "'.$this->db->escapeSql($id).'" ';
        if($id_type === 'PROVIDER') $sql .= 'provider_ref = "'.$this->db->escapeSql($id).'" ';
        if($id_type === 'BUYER_REF') $sql .= 'reference = "'.$this->db->escapeSql($id).'" ';
                     
        $transaction = $this->db->readSqlRecord($sql);
        if($transaction == 0) {
            $error .= 'Could not identify payment gateway transaction.';
            if($this->debug) $error .= 'Provider ID['.$this->provider['provider_id'].'] & '.$id_type.' ref['.$id.']';
        }

        return $transaction;
    }    

    public function saveTransaction($provider_ref,$reference,$reference_id,$amount,$email,&$error)
    {   
        $error = '';
        $error_tmp = '';
        $data = [];
        
        $table_transact = $this->table_prefix.'transaction';

        $data['provider_id'] = $this->provider['provider_id'];
        $data['provider_ref'] = $provider_ref;
        $data['source'] = $this->source; 
        $data['source_id'] = $reference_id; 
        $data['reference'] = $reference;
        $data['amount'] = $amount;
        $data['currency'] = $this->currency;
        $data['email'] = $email;
        $data['date'] = date('Y-m-d H:i:s');
        $data['status'] = 'NEW';

        $this->db->insertRecord($table_transact,$data,$error_tmp);
        if($error_tmp !== '') {
            $error .= 'Could not save provider transaction';
            if($this->debug) $error .= ': '.$error_tmp;
        }
        
        if($error === '') return true; else return false;    
    }

    //Update after payment provider has confirmed or notified a transaction
    protected function updateTransaction($transaction,$status,$comment,$response = [],&$error)
    {
        $error = '';
        $error_tmp = '';
        $table_transact = $this->table_prefix.'transaction';
        $update = [];
        $update_module = false;

        if($status !== $transaction['status']) {
            $update['status'] = $status;
            
            if($status === 'SUCCESS') {
                $update_module = true;
                $transaction['status'] = $status;
            }    
        } 

        if($comment != '') {
            if($comment !== $transaction['comment']) $update['comment'] = $transaction['comment'].$comment;
        }    

        if(count($response)) {
            $response_str = json_encode($response);
            //NB: assume that lengthier response data is authorative. Never sure of confirm/notify sequence ! 
            if(strlen($response_str) > strlen($transaction['response'])) $update['response'] = $response_str;
        }  
        
        if(count($update)) {
            $where = ['transaction_id'=>$transaction['transaction_id']];
            $this->db->updateRecord($table_transact,$update,$where,$error_tmp);
            if($error_tmp !== '') $error .= 'Could not update transaction details: '.$error_tmp;
        }
        
        //now update related module record
        if($error === '' and $update_module) {
            $this->updateTransactionModule($transaction,$comment,$error);
        }
    }

    //update related module with transaction details
    protected function updateTransactionModule($transaction,$comment,&$error)
    {
        $error = '';
        $error_tmp = '';

        if($transaction['source']  === 'SHOP') {
            $module = $this->container->config->get('module','shop');

            $order_id = $transaction['source_id'];
            $amount = $transaction['amount'];
            $comment = '';
            \App\Shop\Helpers::paymentGatewayOrderUpdate($this->db,$module['table_prefix'],$order_id,$amount,$comment,$error_tmp);
            if($error_tmp !== '') {
                $error .= 'Could not update shop order with paymemyt details';
            }
        }

        if($error === '') return true; else return false;
    }

    public function getGatewayForm($reference,$reference_id,$amount,$email,&$error)
    {
        $error = '';
        $html = '';

        if($this->provider['type_id'] !== 'GATEWAY_FORM') $error .= 'Payment provider is not configured as a payment gateway that uses a webform.';

        if($error !== '') return false; 

        //PAYGATE_ID=10011072130&PAY_REQUEST_ID=23B785AE-C96C-32AF-4879-D2C9363DB6E8&REFERENCE=pgtest_123456789&CHECKSUM=b41a77f83a275a849f23e30b4666e837
        if($this->provider['code'] === 'DPO_PAYGATE') {
            $initialise = $this->initialiseDpoPaygate($reference,$amount,$email,$error);
            if($error === '') {
                $this->saveTransaction($initialise['PAY_REQUEST_ID'],$reference,$reference_id,$amount,$email,$error);
            }
            
            if($error === '' ) {
                $checksum =

                $button_text = 'Proceed to DPO PAYGATE payment gateway';
                $html .= '<form action="https://secure.paygate.co.za/payweb3/process.trans" method="POST" >
                             <input type="hidden" name="PAY_REQUEST_ID" value="'.$initialise['PAY_REQUEST_ID'].'">
                             <input type="hidden" name="CHECKSUM" value="'.$initialise['CHECKSUM'].'">
                             <input type="submit" name="Submit" value="'.$button_text.'" class="btn btn-primary">
                         </form>';
            }

        }  

        if($error === '') return $html; else return false;
    }
 
}
?>
