<?php declare(strict_types = 1);

// odsl-/var/www/html/src/Billing
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v1',
   'data' => 
  array (
    '/var/www/html/src/Billing/Application/Port/Outbound/OrganizationAccessPort.php' => 
    array (
      0 => '377c72b09629591ebd1ca212e750aa1619cde2a2',
      1 => 
      array (
        0 => 'billing\\application\\port\\outbound\\organizationaccessport',
      ),
      2 => 
      array (
        0 => 'billing\\application\\port\\outbound\\haspermission',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Application/Port/Outbound/OrganizationPlanAssignmentPort.php' => 
    array (
      0 => 'ab5360ebddd8d36c1c807585c90d503e1032880d',
      1 => 
      array (
        0 => 'billing\\application\\port\\outbound\\organizationplanassignmentport',
      ),
      2 => 
      array (
        0 => 'billing\\application\\port\\outbound\\assignplanbykey',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Application/Port/Outbound/Stripe/StripeEvent.php' => 
    array (
      0 => '0537a40c16c541d1768c0ea7c9afda31a639234c',
      1 => 
      array (
        0 => 'billing\\application\\port\\outbound\\stripe\\stripeevent',
      ),
      2 => 
      array (
        0 => 'billing\\application\\port\\outbound\\stripe\\__construct',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Application/Port/Outbound/Stripe/StripeInvoice.php' => 
    array (
      0 => '2406b1b88a0c83c267ca85424c4660745e18d5f8',
      1 => 
      array (
        0 => 'billing\\application\\port\\outbound\\stripe\\stripeinvoice',
      ),
      2 => 
      array (
        0 => 'billing\\application\\port\\outbound\\stripe\\__construct',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Application/Port/Outbound/StripeGatewayPort.php' => 
    array (
      0 => '0589b065f7e9fa3659f60deceddd2b07895084d1',
      1 => 
      array (
        0 => 'billing\\application\\port\\outbound\\stripegatewayport',
      ),
      2 => 
      array (
        0 => 'billing\\application\\port\\outbound\\ensurecustomer',
        1 => 'billing\\application\\port\\outbound\\createcheckoutsession',
        2 => 'billing\\application\\port\\outbound\\createbillingportalsession',
        3 => 'billing\\application\\port\\outbound\\parseevent',
        4 => 'billing\\application\\port\\outbound\\listinvoices',
        5 => 'billing\\application\\port\\outbound\\schedulecancellation',
        6 => 'billing\\application\\port\\outbound\\resumecancellation',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Application/Port/Outbound/SubscriptionRepositoryPort.php' => 
    array (
      0 => 'b1af14841613b507961fdb362dfb39199d5c2cd2',
      1 => 
      array (
        0 => 'billing\\application\\port\\outbound\\subscriptionrepositoryport',
      ),
      2 => 
      array (
        0 => 'billing\\application\\port\\outbound\\save',
        1 => 'billing\\application\\port\\outbound\\findbyorganizationid',
        2 => 'billing\\application\\port\\outbound\\findbystripecustomerid',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Application/Service/BillingPriceCatalog.php' => 
    array (
      0 => '24330d8213e8d3008bde7613008236163abbe7f8',
      1 => 
      array (
        0 => 'billing\\application\\service\\billingpricecatalog',
      ),
      2 => 
      array (
        0 => 'billing\\application\\service\\__construct',
        1 => 'billing\\application\\service\\priceidfor',
        2 => 'billing\\application\\service\\resolve',
        3 => 'billing\\application\\service\\ispayable',
        4 => 'billing\\application\\service\\pricing',
        5 => 'billing\\application\\service\\currency',
        6 => 'billing\\application\\service\\amountfor',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Application/Service/PlanPricing.php' => 
    array (
      0 => 'bcc4e98f9ea9a3d19b55aa88af4260baacd62526',
      1 => 
      array (
        0 => 'billing\\application\\service\\planpricing',
      ),
      2 => 
      array (
        0 => 'billing\\application\\service\\__construct',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Application/UseCase/Command/CancelSubscription/CancelSubscriptionCommand.php' => 
    array (
      0 => '1f102d41766075c6be9699b03df886b081c6f564',
      1 => 
      array (
        0 => 'billing\\application\\usecase\\command\\cancelsubscription\\cancelsubscriptioncommand',
      ),
      2 => 
      array (
        0 => 'billing\\application\\usecase\\command\\cancelsubscription\\__construct',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Application/UseCase/Command/CancelSubscription/CancelSubscriptionHandler.php' => 
    array (
      0 => 'b5fce63d6a1a3e100a417bd8e35ca4e0fade1735',
      1 => 
      array (
        0 => 'billing\\application\\usecase\\command\\cancelsubscription\\cancelsubscriptionhandler',
      ),
      2 => 
      array (
        0 => 'billing\\application\\usecase\\command\\cancelsubscription\\__construct',
        1 => 'billing\\application\\usecase\\command\\cancelsubscription\\__invoke',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Application/UseCase/Command/HandleStripeWebhook/HandleStripeWebhookCommand.php' => 
    array (
      0 => 'd54f8613e1baf26e3cda2b6116a490db97794dfa',
      1 => 
      array (
        0 => 'billing\\application\\usecase\\command\\handlestripewebhook\\handlestripewebhookcommand',
      ),
      2 => 
      array (
        0 => 'billing\\application\\usecase\\command\\handlestripewebhook\\__construct',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Application/UseCase/Command/HandleStripeWebhook/HandleStripeWebhookHandler.php' => 
    array (
      0 => 'e71558fdc44fc7f44128fdd698f102bce6ee8a82',
      1 => 
      array (
        0 => 'billing\\application\\usecase\\command\\handlestripewebhook\\handlestripewebhookhandler',
      ),
      2 => 
      array (
        0 => 'billing\\application\\usecase\\command\\handlestripewebhook\\__construct',
        1 => 'billing\\application\\usecase\\command\\handlestripewebhook\\__invoke',
        2 => 'billing\\application\\usecase\\command\\handlestripewebhook\\applyupsert',
        3 => 'billing\\application\\usecase\\command\\handlestripewebhook\\applycancellation',
        4 => 'billing\\application\\usecase\\command\\handlestripewebhook\\resolveorganizationid',
        5 => 'billing\\application\\usecase\\command\\handlestripewebhook\\startsubscription',
        6 => 'billing\\application\\usecase\\command\\handlestripewebhook\\todatetime',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Application/UseCase/Command/ResumeSubscription/ResumeSubscriptionCommand.php' => 
    array (
      0 => 'b1e0ddcd2293a834b34e73f29bde75f5610ab17e',
      1 => 
      array (
        0 => 'billing\\application\\usecase\\command\\resumesubscription\\resumesubscriptioncommand',
      ),
      2 => 
      array (
        0 => 'billing\\application\\usecase\\command\\resumesubscription\\__construct',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Application/UseCase/Command/ResumeSubscription/ResumeSubscriptionHandler.php' => 
    array (
      0 => '672d24b82e5fb95fa067861d632393c6bdb9bddd',
      1 => 
      array (
        0 => 'billing\\application\\usecase\\command\\resumesubscription\\resumesubscriptionhandler',
      ),
      2 => 
      array (
        0 => 'billing\\application\\usecase\\command\\resumesubscription\\__construct',
        1 => 'billing\\application\\usecase\\command\\resumesubscription\\__invoke',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Application/UseCase/Command/StartCheckout/StartCheckoutCommand.php' => 
    array (
      0 => '1eaac2efbaa83ae2180138cb4b693e04cfa1ac0a',
      1 => 
      array (
        0 => 'billing\\application\\usecase\\command\\startcheckout\\startcheckoutcommand',
      ),
      2 => 
      array (
        0 => 'billing\\application\\usecase\\command\\startcheckout\\__construct',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Application/UseCase/Command/StartCheckout/StartCheckoutHandler.php' => 
    array (
      0 => '0fc6dc264dc3fafa44c734c43783142b8b6b49b0',
      1 => 
      array (
        0 => 'billing\\application\\usecase\\command\\startcheckout\\startcheckouthandler',
      ),
      2 => 
      array (
        0 => 'billing\\application\\usecase\\command\\startcheckout\\__construct',
        1 => 'billing\\application\\usecase\\command\\startcheckout\\__invoke',
        2 => 'billing\\application\\usecase\\command\\startcheckout\\returnurl',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Application/UseCase/Command/StartCheckout/StartCheckoutResult.php' => 
    array (
      0 => '618615f273100136f60a0c63a20dbb08f23ecc6f',
      1 => 
      array (
        0 => 'billing\\application\\usecase\\command\\startcheckout\\startcheckoutresult',
      ),
      2 => 
      array (
        0 => 'billing\\application\\usecase\\command\\startcheckout\\__construct',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Application/UseCase/Command/StartPortal/StartPortalCommand.php' => 
    array (
      0 => '93b1ac3d832aeae4156da9aae1febebd95c90fd9',
      1 => 
      array (
        0 => 'billing\\application\\usecase\\command\\startportal\\startportalcommand',
      ),
      2 => 
      array (
        0 => 'billing\\application\\usecase\\command\\startportal\\__construct',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Application/UseCase/Command/StartPortal/StartPortalHandler.php' => 
    array (
      0 => '47bb4d7ba03e5f708604d322efce49ee45f3d393',
      1 => 
      array (
        0 => 'billing\\application\\usecase\\command\\startportal\\startportalhandler',
      ),
      2 => 
      array (
        0 => 'billing\\application\\usecase\\command\\startportal\\__construct',
        1 => 'billing\\application\\usecase\\command\\startportal\\__invoke',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Application/UseCase/Command/StartPortal/StartPortalResult.php' => 
    array (
      0 => 'af800fb85b6326cf76662ad9dbc9a5281183355a',
      1 => 
      array (
        0 => 'billing\\application\\usecase\\command\\startportal\\startportalresult',
      ),
      2 => 
      array (
        0 => 'billing\\application\\usecase\\command\\startportal\\__construct',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Application/UseCase/Query/GetOrganizationInvoices/GetOrganizationInvoicesHandler.php' => 
    array (
      0 => '13acdd5f9c25349f6f67521c591daf78532f6fa8',
      1 => 
      array (
        0 => 'billing\\application\\usecase\\query\\getorganizationinvoices\\getorganizationinvoiceshandler',
      ),
      2 => 
      array (
        0 => 'billing\\application\\usecase\\query\\getorganizationinvoices\\__construct',
        1 => 'billing\\application\\usecase\\query\\getorganizationinvoices\\__invoke',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Application/UseCase/Query/GetOrganizationInvoices/GetOrganizationInvoicesQuery.php' => 
    array (
      0 => 'fb1896f0c1e7c45eae8053ace3a1b79a7bd16732',
      1 => 
      array (
        0 => 'billing\\application\\usecase\\query\\getorganizationinvoices\\getorganizationinvoicesquery',
      ),
      2 => 
      array (
        0 => 'billing\\application\\usecase\\query\\getorganizationinvoices\\__construct',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Application/UseCase/Query/GetOrganizationInvoices/GetOrganizationInvoicesResult.php' => 
    array (
      0 => 'dcff62fedb9f3b1fdd042511cde4f57e603dbe98',
      1 => 
      array (
        0 => 'billing\\application\\usecase\\query\\getorganizationinvoices\\getorganizationinvoicesresult',
      ),
      2 => 
      array (
        0 => 'billing\\application\\usecase\\query\\getorganizationinvoices\\__construct',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Application/UseCase/Query/GetOrganizationSubscription/GetOrganizationSubscriptionHandler.php' => 
    array (
      0 => 'a595170f566ad1667275cbe373fe2db471b3b5cf',
      1 => 
      array (
        0 => 'billing\\application\\usecase\\query\\getorganizationsubscription\\getorganizationsubscriptionhandler',
      ),
      2 => 
      array (
        0 => 'billing\\application\\usecase\\query\\getorganizationsubscription\\__construct',
        1 => 'billing\\application\\usecase\\query\\getorganizationsubscription\\__invoke',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Application/UseCase/Query/GetOrganizationSubscription/GetOrganizationSubscriptionQuery.php' => 
    array (
      0 => 'b95d623299f8521e43433ff7eea4f1e007e1228c',
      1 => 
      array (
        0 => 'billing\\application\\usecase\\query\\getorganizationsubscription\\getorganizationsubscriptionquery',
      ),
      2 => 
      array (
        0 => 'billing\\application\\usecase\\query\\getorganizationsubscription\\__construct',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Application/UseCase/Query/GetOrganizationSubscription/GetOrganizationSubscriptionResult.php' => 
    array (
      0 => '1737705dddea1eddfd56f2badd3f101da7b71d32',
      1 => 
      array (
        0 => 'billing\\application\\usecase\\query\\getorganizationsubscription\\getorganizationsubscriptionresult',
      ),
      2 => 
      array (
        0 => 'billing\\application\\usecase\\query\\getorganizationsubscription\\__construct',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Domain/Exception/BillingCustomerNotFoundException.php' => 
    array (
      0 => '09f613a664d57937464a4226158e7e3c2cdfbb01',
      1 => 
      array (
        0 => 'billing\\domain\\exception\\billingcustomernotfoundexception',
      ),
      2 => 
      array (
        0 => 'billing\\domain\\exception\\fororganization',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Domain/Exception/InvalidWebhookSignatureException.php' => 
    array (
      0 => '74b575ed9e82f1bdca59239dc37327e668652c0c',
      1 => 
      array (
        0 => 'billing\\domain\\exception\\invalidwebhooksignatureexception',
      ),
      2 => 
      array (
        0 => 'billing\\domain\\exception\\create',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Domain/Exception/NoActiveSubscriptionException.php' => 
    array (
      0 => '83b1b68713e930c92f49eedda8e75a852d6c3800',
      1 => 
      array (
        0 => 'billing\\domain\\exception\\noactivesubscriptionexception',
      ),
      2 => 
      array (
        0 => 'billing\\domain\\exception\\fororganization',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Domain/Model/Subscription/Subscription.php' => 
    array (
      0 => '47c050c28dc035de831b905cb29dec8fe8745644',
      1 => 
      array (
        0 => 'billing\\domain\\model\\subscription\\subscription',
      ),
      2 => 
      array (
        0 => 'billing\\domain\\model\\subscription\\__construct',
        1 => 'billing\\domain\\model\\subscription\\start',
        2 => 'billing\\domain\\model\\subscription\\reconstitute',
        3 => 'billing\\domain\\model\\subscription\\syncfromstripe',
        4 => 'billing\\domain\\model\\subscription\\schedulecancellation',
        5 => 'billing\\domain\\model\\subscription\\resumecancellation',
        6 => 'billing\\domain\\model\\subscription\\markcanceled',
        7 => 'billing\\domain\\model\\subscription\\id',
        8 => 'billing\\domain\\model\\subscription\\organizationid',
        9 => 'billing\\domain\\model\\subscription\\stripecustomerid',
        10 => 'billing\\domain\\model\\subscription\\stripesubscriptionid',
        11 => 'billing\\domain\\model\\subscription\\status',
        12 => 'billing\\domain\\model\\subscription\\plankey',
        13 => 'billing\\domain\\model\\subscription\\interval',
        14 => 'billing\\domain\\model\\subscription\\currentperiodend',
        15 => 'billing\\domain\\model\\subscription\\cancelatperiodend',
        16 => 'billing\\domain\\model\\subscription\\createdat',
        17 => 'billing\\domain\\model\\subscription\\updatedat',
        18 => 'billing\\domain\\model\\subscription\\touch',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Domain/ValueObject/BillingInterval.php' => 
    array (
      0 => '4576fb142dd893f179ac7dbbf339e6d76981c4e9',
      1 => 
      array (
        0 => 'billing\\domain\\valueobject\\billinginterval',
      ),
      2 => 
      array (
        0 => 'billing\\domain\\valueobject\\fromstring',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Domain/ValueObject/SubscriptionId.php' => 
    array (
      0 => '9a18ca2c64223845d9928353ae5b53b2713e92cd',
      1 => 
      array (
        0 => 'billing\\domain\\valueobject\\subscriptionid',
      ),
      2 => 
      array (
        0 => 'billing\\domain\\valueobject\\fromstring',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Domain/ValueObject/SubscriptionStatus.php' => 
    array (
      0 => 'c28de1c641574317b42978613260b01150b2ec71',
      1 => 
      array (
        0 => 'billing\\domain\\valueobject\\subscriptionstatus',
      ),
      2 => 
      array (
        0 => 'billing\\domain\\valueobject\\fromstripe',
        1 => 'billing\\domain\\valueobject\\grantsaccess',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Infrastructure/Adapter/Stripe/StripeGatewayAdapter.php' => 
    array (
      0 => '4cf814683314c2197740832dc37c4bf1a2581e83',
      1 => 
      array (
        0 => 'billing\\infrastructure\\adapter\\stripe\\stripegatewayadapter',
      ),
      2 => 
      array (
        0 => 'billing\\infrastructure\\adapter\\stripe\\__construct',
        1 => 'billing\\infrastructure\\adapter\\stripe\\ensurecustomer',
        2 => 'billing\\infrastructure\\adapter\\stripe\\createcheckoutsession',
        3 => 'billing\\infrastructure\\adapter\\stripe\\createbillingportalsession',
        4 => 'billing\\infrastructure\\adapter\\stripe\\parseevent',
        5 => 'billing\\infrastructure\\adapter\\stripe\\listinvoices',
        6 => 'billing\\infrastructure\\adapter\\stripe\\schedulecancellation',
        7 => 'billing\\infrastructure\\adapter\\stripe\\resumecancellation',
        8 => 'billing\\infrastructure\\adapter\\stripe\\periodend',
        9 => 'billing\\infrastructure\\adapter\\stripe\\dig',
        10 => 'billing\\infrastructure\\adapter\\stripe\\stringornull',
        11 => 'billing\\infrastructure\\adapter\\stripe\\intorzero',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Infrastructure/Persistence/Doctrine/Mapper/SubscriptionMapper.php' => 
    array (
      0 => '53cdec94ce54bad40e875da76817f5419e72aa84',
      1 => 
      array (
        0 => 'billing\\infrastructure\\persistence\\doctrine\\mapper\\subscriptionmapper',
      ),
      2 => 
      array (
        0 => 'billing\\infrastructure\\persistence\\doctrine\\mapper\\todomain',
        1 => 'billing\\infrastructure\\persistence\\doctrine\\mapper\\torecord',
        2 => 'billing\\infrastructure\\persistence\\doctrine\\mapper\\applyto',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Infrastructure/Persistence/Doctrine/Record/SubscriptionRecord.php' => 
    array (
      0 => 'ecf4b726bcacd02db8b76b917ad8b123c36e15d4',
      1 => 
      array (
        0 => 'billing\\infrastructure\\persistence\\doctrine\\record\\subscriptionrecord',
      ),
      2 => 
      array (
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Infrastructure/Persistence/Doctrine/Repository/SubscriptionRepository.php' => 
    array (
      0 => '90aca3c354f376491523a30a1f67a99ae25bb7f0',
      1 => 
      array (
        0 => 'billing\\infrastructure\\persistence\\doctrine\\repository\\subscriptionrepository',
      ),
      2 => 
      array (
        0 => 'billing\\infrastructure\\persistence\\doctrine\\repository\\__construct',
        1 => 'billing\\infrastructure\\persistence\\doctrine\\repository\\save',
        2 => 'billing\\infrastructure\\persistence\\doctrine\\repository\\findbyorganizationid',
        3 => 'billing\\infrastructure\\persistence\\doctrine\\repository\\findbystripecustomerid',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Presentation/Api/Dto/Input/StartCheckoutInput.php' => 
    array (
      0 => '0671791d3ba99167cb5d1c45fce7968c4986044e',
      1 => 
      array (
        0 => 'billing\\presentation\\api\\dto\\input\\startcheckoutinput',
      ),
      2 => 
      array (
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Presentation/Api/Dto/Output/CheckoutSessionOutput.php' => 
    array (
      0 => '09e0b648d69999a2aabfea19606fa9af14612323',
      1 => 
      array (
        0 => 'billing\\presentation\\api\\dto\\output\\checkoutsessionoutput',
      ),
      2 => 
      array (
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Presentation/Api/Dto/Output/InvoiceOutput.php' => 
    array (
      0 => 'b49d310308b0dd75e192ab48d0edea0f073532de',
      1 => 
      array (
        0 => 'billing\\presentation\\api\\dto\\output\\invoiceoutput',
      ),
      2 => 
      array (
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Presentation/Api/Dto/Output/PlanPricingOutput.php' => 
    array (
      0 => 'c8ab451eaacd13bc09a32d270f830c2eff6ff8f3',
      1 => 
      array (
        0 => 'billing\\presentation\\api\\dto\\output\\planpricingoutput',
      ),
      2 => 
      array (
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Presentation/Api/Dto/Output/PortalSessionOutput.php' => 
    array (
      0 => 'b6fe3839e142af4c28b37249c111c62a50d5136b',
      1 => 
      array (
        0 => 'billing\\presentation\\api\\dto\\output\\portalsessionoutput',
      ),
      2 => 
      array (
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Presentation/Api/Dto/Output/SubscriptionOutput.php' => 
    array (
      0 => '0f9f4525270449324f10fc018eb7aad753231914',
      1 => 
      array (
        0 => 'billing\\presentation\\api\\dto\\output\\subscriptionoutput',
      ),
      2 => 
      array (
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Presentation/Api/Operation/BillingOperations.php' => 
    array (
      0 => '3c0dc654cc6f9ede693705cfa8e31bfacf9b2d36',
      1 => 
      array (
        0 => 'billing\\presentation\\api\\operation\\billingoperations',
      ),
      2 => 
      array (
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Presentation/Api/Processor/CancelSubscriptionProcessor.php' => 
    array (
      0 => '8d2c34557f8f3143f44c7ec5a9a21cfe7f0937a4',
      1 => 
      array (
        0 => 'billing\\presentation\\api\\processor\\cancelsubscriptionprocessor',
      ),
      2 => 
      array (
        0 => 'billing\\presentation\\api\\processor\\__construct',
        1 => 'billing\\presentation\\api\\processor\\process',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Presentation/Api/Processor/ResumeSubscriptionProcessor.php' => 
    array (
      0 => '0b60179e5c10fce60e9f366f6f0289a44b3b1a4c',
      1 => 
      array (
        0 => 'billing\\presentation\\api\\processor\\resumesubscriptionprocessor',
      ),
      2 => 
      array (
        0 => 'billing\\presentation\\api\\processor\\__construct',
        1 => 'billing\\presentation\\api\\processor\\process',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Presentation/Api/Processor/StartCheckoutProcessor.php' => 
    array (
      0 => '52bce8069e9bc476336c1243cd4e8c9a003ea7a1',
      1 => 
      array (
        0 => 'billing\\presentation\\api\\processor\\startcheckoutprocessor',
      ),
      2 => 
      array (
        0 => 'billing\\presentation\\api\\processor\\__construct',
        1 => 'billing\\presentation\\api\\processor\\process',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Presentation/Api/Processor/StartPortalProcessor.php' => 
    array (
      0 => '2379dcb7c1f4358ee81729ae46ff748fad91d2fd',
      1 => 
      array (
        0 => 'billing\\presentation\\api\\processor\\startportalprocessor',
      ),
      2 => 
      array (
        0 => 'billing\\presentation\\api\\processor\\__construct',
        1 => 'billing\\presentation\\api\\processor\\process',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Presentation/Api/Processor/Support/ResolvesMessengerFailure.php' => 
    array (
      0 => '7261365f7980208c426eafbbf3f5e6da661faa39',
      1 => 
      array (
        0 => 'billing\\presentation\\api\\processor\\support\\resolvesmessengerfailure',
      ),
      2 => 
      array (
        0 => 'billing\\presentation\\api\\processor\\support\\firstfailureof',
        1 => 'billing\\presentation\\api\\processor\\support\\expandfailure',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Presentation/Api/Provider/GetInvoicesProvider.php' => 
    array (
      0 => '73cb64c0ec850cec1e91600318a183c3d0a5bd6c',
      1 => 
      array (
        0 => 'billing\\presentation\\api\\provider\\getinvoicesprovider',
      ),
      2 => 
      array (
        0 => 'billing\\presentation\\api\\provider\\__construct',
        1 => 'billing\\presentation\\api\\provider\\provide',
        2 => 'billing\\presentation\\api\\provider\\tooutput',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Presentation/Api/Provider/GetPricingProvider.php' => 
    array (
      0 => '824a30b6f0fe2007d158653608217cfba515d41e',
      1 => 
      array (
        0 => 'billing\\presentation\\api\\provider\\getpricingprovider',
      ),
      2 => 
      array (
        0 => 'billing\\presentation\\api\\provider\\__construct',
        1 => 'billing\\presentation\\api\\provider\\provide',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Presentation/Api/Provider/GetSubscriptionProvider.php' => 
    array (
      0 => 'fc3855524d152bfc966e8696521cb253d0f40596',
      1 => 
      array (
        0 => 'billing\\presentation\\api\\provider\\getsubscriptionprovider',
      ),
      2 => 
      array (
        0 => 'billing\\presentation\\api\\provider\\__construct',
        1 => 'billing\\presentation\\api\\provider\\provide',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Presentation/Api/Resource/BillingInvoicesResource.php' => 
    array (
      0 => 'e29e087e3030bb77ebafe2e23dd8d2d00356d98d',
      1 => 
      array (
        0 => 'billing\\presentation\\api\\resource\\billinginvoicesresource',
      ),
      2 => 
      array (
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Presentation/Api/Resource/BillingPricingResource.php' => 
    array (
      0 => '26af07dbc30caeacbd28834eec1025c23b95bfed',
      1 => 
      array (
        0 => 'billing\\presentation\\api\\resource\\billingpricingresource',
      ),
      2 => 
      array (
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Presentation/Api/Resource/OrganizationBillingResource.php' => 
    array (
      0 => 'b91d430285df96b06073cf1342ec3b6d9a1a6d6b',
      1 => 
      array (
        0 => 'billing\\presentation\\api\\resource\\organizationbillingresource',
      ),
      2 => 
      array (
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Presentation/Api/Serialization/BillingSerializationGroup.php' => 
    array (
      0 => '27f5ad2ab68dca1695cba91049e61dc6e9837897',
      1 => 
      array (
        0 => 'billing\\presentation\\api\\serialization\\billingserializationgroup',
      ),
      2 => 
      array (
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/src/Billing/Presentation/Controller/StripeWebhookController.php' => 
    array (
      0 => '41f23205443f9394ae012918ba4527cbe1f0fd3c',
      1 => 
      array (
        0 => 'billing\\presentation\\controller\\stripewebhookcontroller',
      ),
      2 => 
      array (
        0 => 'billing\\presentation\\controller\\__construct',
        1 => 'billing\\presentation\\controller\\__invoke',
      ),
      3 => 
      array (
      ),
    ),
  ),
));