<?php
namespace App\Payment;

use Psr\Container\ContainerInterface;
use App\Payment\Setup;

class SetupController
{
    protected $container;
    
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function __invoke($request, $response, $args)
    {
        $module = $this->container->config->get('module','payment');  
        $setup = new Setup($this->container->mysql,$this->container,$module);

        $setup->setup();
        $html = $setup->processSetup();
        
        $template['html'] = $html;
        $template['title'] = MODULE_LOGO.'All Payment settings';
        
        return $this->container->view->render($response,'admin.php',$template);
    }
}