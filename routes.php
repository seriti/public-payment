<?php  
/*
NB: This is not stand alone code and is intended to be used within "seriti/slim3-skeleton" framework
The code snippet below is for use within an existing src/routes.php file within this framework
copy the "/payment" groups into the existing "/admin" group within existing "src/routes.php" file 
*/

//*** BEGIN admin access ***
$app->group('/admin', function () {

    $this->group('/payment', function () {
        $this->any('/dashboard', \App\Payment\DashboardController::class);
        $this->any('/transaction', \App\Payment\TransactionController::class);
        //provider configuration sets the routes for notify and confirm urls
        $this->any('/provider', \App\Payment\ProviderController::class);
        $this->get('/setup_data', \App\Payment\SetupDataController::class);
        $this->any('/report', \App\Payment\ReportController::class);
    })->add(\App\Payment\Config::class);

})->add(\App\User\ConfigAdmin::class);
//*** END admin access ***


//Payment route for managing payment gateway notify urls(not for human consumption)
$app->group('/payment', function () {
    $this->post('/notify/{source}/{provider}', \App\Payment\GatewayNotifyController::class);
})->add(\App\Payment\ConfigPayment::class);

//for incorporatimg into public-shop or pubic-auction modules within public-website module 
//*** BEGIN public access ***
$app->group('/public', function () {
 
    //for processing confirm url from payment gateway service provider
    $this->post('/payment/confirm/{source}/{provider}', \App\Payment\GatewayConfirmController::class);

    
    
})->add(\App\Website\ConfigPublic::class);
//*** END public access ***

