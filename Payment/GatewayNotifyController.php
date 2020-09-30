<?php
namespace App\Payment;

use Psr\Container\ContainerInterface;

use Exception;
use Seriti\Tools\Secure;
use Seriti\Tools\Template;
use Seriti\Tools\BASE_TEMPLATE;
use Seriti\Tools\DEBUG;

use App\Payment\Helpers;
use App\Payment\Gateway;

//NB: This is called by gateway server with only a simple text response
class GatewayNotifyController
{
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }


    public function __invoke($request, $response, $args)
    {
        if(MODULE_PAYMENT['debug_log']) {
            /* you can use logged responses here to test independant of gateway
            NB: "AUTH_CODE":"null" IS IGNORED IN CHECKSUM

            $log = '{"PAYGATE_ID":"10011072130","PAY_REQUEST_ID":"BB449144-6021-9EF8-BFB1-C8133800925F","REFERENCE":"ORDER-8","TRANSACTION_STATUS":"1","RESULT_CODE":"990017","AUTH_CODE":"4MLXW6","CURRENCY":"ZAR","AMOUNT":"10100","RESULT_DESC":"Auth Done","TRANSACTION_ID":"210391033","RISK_INDICATOR":"AP","PAY_METHOD":"CC","PAY_METHOD_DETAIL":"Visa","CHECKSUM":"52392801d5493a85a45b19f982cd1d9a"}';
            
            $log = '{"PAYGATE_ID":"10011072130","PAY_REQUEST_ID":"3159D4ED-F146-1D1C-FEB6-616D6D5F55B9","REFERENCE":"ORDER-2","TRANSACTION_STATUS":"2","RESULT_CODE":"900003","AUTH_CODE":"null","CURRENCY":"ZAR","AMOUNT":"10200","RESULT_DESC":"Insufficient Funds","TRANSACTION_ID":"211550986","RISK_INDICATOR":"AP","PAY_METHOD":"CC","PAY_METHOD_DETAIL":"MasterCard","CHECKSUM":"68a6ccec7e9b03f7b9183e757198b02c"}';

            $_POST = json_decode($log,true);
            */
            
            $this->container->logger->addInfo('Payment notify:',$_POST);
        }

        $gateway = new Gateway($this->container->mysql,$this->container);
        
        //defined in configPayment
        $table_prefix = MODULE_PAYMENT['table_prefix'];

        switch($args['source']) {
            case 'shop': $source = 'SHOP'; break;
            default: $source = 'UNKNOWN';
        }

        if($source === 'UNKNOWN') {
            throw new Exception('PAYMENT_GATEWAY_ERROR: INVALID source module argument['.$args['source'].']');
            exit;
        }

        switch($args['provider']) {
            case 'dpo': $provider_code = 'DPO_PAYGATE'; break;
            default: $provider_code = 'UNKNOWN';
        }

        if($provider_code === 'UNKNOWN') {
            throw new Exception('PAYMENT_GATEWAY_ERROR: INVALID url argument['.$args['provider'].']');
            exit;
        }

        $provider = Helpers::getProvider($this->container->mysql,$table_prefix,'CODE',$provider_code);
        if($provider == 0) {
            throw new Exception('PAYMENT_GATEWAY_ERROR: Provider CODE['.$provider_code.'] not valid');
            exit;
        } 
        
        $gateway->setup($source,$provider['provider_id']);
       
        //should be "OK" or some error
        $text = $gateway->process('NOTIFY',$_POST);

        if(MODULE_PAYMENT['debug_log']) {
            $this->container->logger->addInfo('Payment notify response:'.$text);
        }


        //response for non human consumption
        return $response->write($text);
        
    }
}