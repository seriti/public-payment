<?php 
namespace App\Payment;

use Psr\Container\ContainerInterface;
use Seriti\Tools\BASE_URL;
use Seriti\Tools\SITE_NAME;
use Seriti\Tools\CURRENCY_ID;

class Config
{
    
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

    }

    /**
     * Example middleware invokable class
     *
     * @param  \Psr\Http\Message\ServerRequestInterface $request  PSR7 request
     * @param  \Psr\Http\Message\ResponseInterface      $response PSR7 response
     * @param  callable                                 $next     Next middleware
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function __invoke($request, $response, $next)
    {
        
        $module = $this->container->config->get('module','payment');
        //$ledger = $this->container->config->get('module','ledger');
        $menu = $this->container->menu;
        //$cache = $this->container->cache;
        //$db = $this->container->mysql;

        //$user_specific = true;
        //$cache->setCache('Payments',$user_specific);
        //$cache->eraseAll();
        
        define('TABLE_PREFIX',$module['table_prefix']);
        define('MODULE_LOGO','<span class="glyphicon glyphicon-piggy-bank" aria-hidden="true"></span> ');
        define('MODULE_PAGE',URL_CLEAN_LAST);

        define('PAYMENT_LOCATION_BASE',$module['location_base']);
        define('PAYMENT_TYPE',['EFT_TOKEN'=>'Manual Payment with reference code',
                               'GATEWAY_FORM'=>'Payment gateway form']);

        $submenu_html = $menu->buildNav($module['route_list'],MODULE_PAGE);
        $this->container->view->addAttribute('sub_menu',$submenu_html);
       
        $response = $next($request, $response);
        
        return $response;
    }
}