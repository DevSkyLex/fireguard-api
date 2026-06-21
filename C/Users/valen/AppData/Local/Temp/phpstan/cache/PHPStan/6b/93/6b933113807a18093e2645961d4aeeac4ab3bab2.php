<?php declare(strict_types = 1);

// odsl-/var/www/html/tests/Billing
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v1',
   'data' => 
  array (
    '/var/www/html/tests/Billing/Application/Service/BillingPriceCatalogTest.php' => 
    array (
      0 => '8bf407b574a15b0b66dea445e7fa0d3b93cc83ec',
      1 => 
      array (
        0 => 'tests\\billing\\application\\service\\billingpricecatalogtest',
      ),
      2 => 
      array (
        0 => 'tests\\billing\\application\\service\\itresolvesapriceidforaplanandinterval',
        1 => 'tests\\billing\\application\\service\\itreturnsnullforanunconfiguredplan',
        2 => 'tests\\billing\\application\\service\\itreverseresolvesapriceidtoplanandinterval',
        3 => 'tests\\billing\\application\\service\\itreturnsnullwhenreverseresolvinganunknownprice',
        4 => 'tests\\billing\\application\\service\\itreportspayableplans',
        5 => 'tests\\billing\\application\\service\\itexposesdisplaypricingforeverypayableplan',
        6 => 'tests\\billing\\application\\service\\itignoresblankpriceids',
        7 => 'tests\\billing\\application\\service\\prices',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Billing/Application/UseCase/HandleStripeWebhookHandlerTest.php' => 
    array (
      0 => 'd45729fc6b829ace6b983fad5fb40cb86033b39b',
      1 => 
      array (
        0 => 'tests\\billing\\application\\usecase\\handlestripewebhookhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\billing\\application\\usecase\\anactivesubscriptioneventassignsthepaidplan',
        1 => 'tests\\billing\\application\\usecase\\adeletedsubscriptioneventdowngradestofree',
        2 => 'tests\\billing\\application\\usecase\\anunrelatedeventchangesnothing',
        3 => 'tests\\billing\\application\\usecase\\prices',
        4 => 'tests\\billing\\application\\usecase\\uuidfactory',
        5 => 'tests\\billing\\application\\usecase\\transactionmanager',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Billing/Domain/SubscriptionTest.php' => 
    array (
      0 => 'b93873c31110b3bd494524daa941282f361e872f',
      1 => 
      array (
        0 => 'tests\\billing\\domain\\subscriptiontest',
      ),
      2 => 
      array (
        0 => 'tests\\billing\\domain\\itstartsincompletewithonlythecustomerlink',
        1 => 'tests\\billing\\domain\\itsynchronizesfromstripe',
        2 => 'tests\\billing\\domain\\itmarkscanceled',
        3 => 'tests\\billing\\domain\\itschedulesandresumescancellationwithouttouchingstatus',
        4 => 'tests\\billing\\domain\\statusgrantsaccessonlywhileusable',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Billing/Application/UseCase/CancelSubscriptionHandlerTest.php' => 
    array (
      0 => '4a471da2d8dceeea21c8a38544618e714532b19f',
      1 => 
      array (
        0 => 'tests\\billing\\application\\usecase\\cancelsubscriptionhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\billing\\application\\usecase\\itschedulescancellationonstripeandmirrorslocally',
        1 => 'tests\\billing\\application\\usecase\\itthrowswhennosubscriptionexists',
        2 => 'tests\\billing\\application\\usecase\\itthrowswhenthesubscriptionhasnostripeid',
        3 => 'tests\\billing\\application\\usecase\\activesubscription',
        4 => 'tests\\billing\\application\\usecase\\transactionmanager',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Billing/Application/UseCase/GetOrganizationInvoicesHandlerTest.php' => 
    array (
      0 => '16f8f5ca29c2fa755980068d71b7a556a05e9d57',
      1 => 
      array (
        0 => 'tests\\billing\\application\\usecase\\getorganizationinvoiceshandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\billing\\application\\usecase\\itreturnsnoinvoiceswhentheorganizationhasnocustomer',
        1 => 'tests\\billing\\application\\usecase\\itlistsinvoicesfortheorganizationcustomer',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Billing/Application/UseCase/ResumeSubscriptionHandlerTest.php' => 
    array (
      0 => '57efbee9a8937e0b81e3281424955878e280397c',
      1 => 
      array (
        0 => 'tests\\billing\\application\\usecase\\resumesubscriptionhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\billing\\application\\usecase\\itclearscancellationonstripeandmirrorslocally',
        1 => 'tests\\billing\\application\\usecase\\itthrowswhennosubscriptionexists',
        2 => 'tests\\billing\\application\\usecase\\cancelingsubscription',
        3 => 'tests\\billing\\application\\usecase\\transactionmanager',
      ),
      3 => 
      array (
      ),
    ),
  ),
));