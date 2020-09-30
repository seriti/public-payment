<?php
namespace App\Payment;

use Psr\Container\ContainerInterface;
use App\Payment\Provider;

class ProviderController
{
    protected $container;
    
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function __invoke($request, $response, $args)
    {
        $table_name = TABLE_PREFIX.'provider'; 
        $table = new Provider($this->container->mysql,$this->container,$table_name);

        $table->setup();
        $html = $table->processTable();
        
        $template['html'] = $html;
        $template['title'] = MODULE_LOGO.' Configure payment providers';
        
        return $this->container->view->render($response,'admin.php',$template);
    }
}