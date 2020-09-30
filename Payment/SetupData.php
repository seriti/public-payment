<?php
namespace App\Payment;

use Seriti\Tools\SetupModuleData;

class SetupData extends SetupModuledata
{

    public function setupSql()
    {
        $this->tables = ['provider','transaction'];

        $this->addCreateSql('provider',
                            'CREATE TABLE `TABLE_NAME` (
                              `provider_id` INT NOT NULL AUTO_INCREMENT,
                              `type_id` VARCHAR(64) NOT NULL,
                              `name` VARCHAR(250) NOT NULL,
                              `code` VARCHAR(64) NOT NULL,
                              `config` TEXT NOT NULL,
                              `sort` INT NOT NULL,
                              `status` VARCHAR(64) NOT NULL,
                              PRIMARY KEY (`provider_id`),
                              UNIQUE KEY `idx_provider1` (`code`)
                            ) ENGINE = MyISAM DEFAULT CHARSET=utf8');


        $this->addCreateSql('transaction',
                            'CREATE TABLE `TABLE_NAME` (
                              `transaction_id` INT NOT NULL AUTO_INCREMENT,
                              `provider_id` INT NOT NULL,
                              `provider_ref` VARCHAR(250) NOT NULL,
                              `source` VARCHAR(64) NOT NULL,
                              `source_id` INT NOT NULL,
                              `reference` VARCHAR(64) NOT NULL,
                              `date` DATETIME NOT NULL,
                              `amount` DECIMAL(12,2) NOT NULL,
                              `currency` VARCHAR(4) NOT NULL,
                              `email` VARCHAR(250) NOT NULL,
                              `comment` TEXT NOT NULL,
                              `response` TEXT NOT NULL,
                              `status` VARCHAR(64) NOT NULL,
                              PRIMARY KEY (`transaction_id`),
                              UNIQUE KEY `idx_transaction1` (`provider_id`,`provider_ref`)
                            ) ENGINE = MyISAM DEFAULT CHARSET=utf8');

        //initialisation
        $this->addInitialSql('INSERT INTO `TABLE_PREFIXprovider` (type_id,name,code,config,sort,status) '.
                             'VALUES("EFT_TOKEN","Manual payment with token reference","BANK_XXX","Your bank account details","10","OK"),("GATEWAY_FORM","DPO paygate","DPO_PAYGATE","{\"merchant_id\":\"10011072130\",\"key\":\"secret\",\"currency\":\"ZAR\",\"return_url\":\"https://yourdomain.com/public/payment/confirm/shop/dpo\",\"notify_url\":\"https://yourdomain.com/payment/notify/shop/dpo\"}","20","OK")',
                             'Created default EFT Token and DPO Paygate Payweb 3 testing options');

          
        //updates use time stamp in ['YYYY-MM-DD HH:MM'] format, must be unique and sequential
        //$this->addUpdateSql('YYYY-MM-DD HH:MM','Update TABLE_PREFIX--- SET --- "X"');
    }
}


  
?>
