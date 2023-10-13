<?php
namespace App\Payment;

use Psr\Container\ContainerInterface;

use Exception;
use Seriti\Tools\Secure;
use Seriti\Tools\Template;
use Seriti\Tools\BASE_TEMPLATE;

use App\Payment\Helpers;
use App\Payment\Gateway;

//NB: This must be accessed within public website routes
class GatewayConfirmController
{
    protected $container;
    
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }


    public function __invoke($request, $response, $args)
    {
        if(MODULE_PAYMENT['debug_log']) {
            $this->container->logger->addInfo('Payment confirm:',$_POST);
        }

        $gateway = new Gateway($this->container->mysql,$this->container);
        
        //NB: 'MODULE_PAYMENT' defined in Website/ConfigPublic and Payment/ConfigPayment
        $table_prefix = MODULE_PAYMENT['table_prefix'];

        switch($args['source']) {
            case 'shop': $source = 'SHOP'; break;
            case 'auction': $source = 'AUCTION'; break;
            default: $source = 'UNKNOWN';
        }

        if($source === 'UNKNOWN') {
            throw new Exception('PAYMENT_GATEWAY_ERROR: INVALID source module argument['.$args['source'].']');
            exit;
        }

        switch($args['provider']) {
            case 'dpo': $provider_code = 'DPO_PAYGATE'; break;
            case 'payfast': $provider_code = 'DPO_PAYFAST'; break;
            default: $provider_code = 'UNKNOWN';
        }

        if($provider_code === 'UNKNOWN') {
            throw new Exception('PAYMENT_GATEWAY_ERROR: INVALID url argument['.$args['provider'].']');
            exit;
        }

        $provider = Helpers::getProvider($this->container->mysql,$table_prefix,'CODE',$provider_code);
        if($provider == 0) {
            throw new Exception('PAYMENT_GATEWAY_ERROR: Provider CODE not valid');
            exit;
        } 
        
        $gateway->setup($source,$provider['provider_id']);
       
        $html = $gateway->process('CONFIRM',$_POST);
        
        $template['html'] = $html;
        $template['title'] = $provider['name'].': Payment confirmation';
        //$template['javascript'] = $wizard->getJavascript();

        return $this->container->view->render($response,'public.php',$template);
    }
}