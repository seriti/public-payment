<?php
namespace App\Payment;

use Psr\Container\ContainerInterface;

use Seriti\Tools\Template;

use App\Payment\Transaction;

class TransactionController
{
    protected $container;
    
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function __invoke($request, $response, $args)
    {
        $table_name = TABLE_PREFIX.'transaction'; 
        $table = new Transaction($this->container->mysql,$this->container,$table_name);

        $table->setup();
        $html = $table->processTable();
            
        $template['title'] = MODULE_LOGO.' All provider transactions';
        $template['html'] = $html;
        //$template['javascript'] = $dashboard->getJavascript();
        
        return $this->container->view->render($response,'admin.php',$template);
    }
}