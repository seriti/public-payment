<?php
/*
NB: This is not stand alone code and is intended to be used within "seriti/slim3-skeleton" framework
The code snippet below is for use within an existing src/setup_app.php file within this framework
add the below code snippet to the end of existing "src/setup_app.php" file.
This tells the framework about module: name, sub-memnu route list and title, database table prefix.

NB: 'debug_log'=>true means that call and responses to payment service providers are logged to system log file for debugging purposes. 
*/

$container['config']->set('module','payment',['name'=>'Payment manager',
                                            'route_root'=>'admin/payment/',
                                            'route_list'=>['dashboard'=>'Dashboard','transaction'=>'Transactions',
                                                           'provider'=>'Payment providers','setup'=>'Setup','report'=>'Reports'],
                                            'labels'=>['payment'=>'Payment','type'=>'Type','order'=>'Order'],
                                            'location_base'=>'PMT',
                                            'debug_log'=>true,
                                            'table_prefix'=>'pmt_'
                                            ]);