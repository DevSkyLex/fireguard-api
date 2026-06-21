<?php declare(strict_types = 1);

// odsl-/var/www/html/tests
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v1',
   'data' => 
  array (
    '/var/www/html/tests/Architecture/Rule/ApplicationDependenciesTest.php' => 
    array (
      0 => '43b350c0d45e12b858ab5eeeda744147b49eb889',
      1 => 
      array (
        0 => 'app\\tests\\architecture\\rule\\applicationdependenciestest',
      ),
      2 => 
      array (
        0 => 'app\\tests\\architecture\\rule\\testapplicationdoesnotdependoninfrastructure',
        1 => 'app\\tests\\architecture\\rule\\testapplicationdependsonlyondomainandsharedcontracts',
        2 => 'app\\tests\\architecture\\rule\\testapplicationmodulesareisolated',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Architecture/Rule/BaseHexagonalArchitectureTest.php' => 
    array (
      0 => '0f2d2318d5fb56717f0f473af160004e01aceede',
      1 => 
      array (
        0 => 'app\\tests\\architecture\\rule\\basehexagonalarchitecturetest',
      ),
      2 => 
      array (
        0 => 'app\\tests\\architecture\\rule\\modules',
        1 => 'app\\tests\\architecture\\rule\\moduleshavinglayer',
        2 => 'app\\tests\\architecture\\rule\\selectorsforlayer',
        3 => 'app\\tests\\architecture\\rule\\selectorsforlayers',
        4 => 'app\\tests\\architecture\\rule\\selectorsformodulelayer',
        5 => 'app\\tests\\architecture\\rule\\modulenamespaces',
        6 => 'app\\tests\\architecture\\rule\\sharedmodule',
        7 => 'app\\tests\\architecture\\rule\\sharedlayerselectors',
        8 => 'app\\tests\\architecture\\rule\\sharedapplicationportselectors',
        9 => 'app\\tests\\architecture\\rule\\selectorsfornamespaces',
        10 => 'app\\tests\\architecture\\rule\\srcdir',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Architecture/Rule/DomainDependenciesTest.php' => 
    array (
      0 => 'd38cfb183a78b021cb58a64117317536a1287a9a',
      1 => 
      array (
        0 => 'app\\tests\\architecture\\rule\\domaindependenciestest',
      ),
      2 => 
      array (
        0 => 'app\\tests\\architecture\\rule\\testdomainhasnoouterdependencies',
        1 => 'app\\tests\\architecture\\rule\\testdomainonlydependsondomainandsharedglobally',
        2 => 'app\\tests\\architecture\\rule\\testdomaindependsonlyondomainandshared',
        3 => 'app\\tests\\architecture\\rule\\testdomainmodulesareisolated',
        4 => 'app\\tests\\architecture\\rule\\testshareddomainisframeworkagnostic',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Architecture/Rule/InfrastructureDependenciesTest.php' => 
    array (
      0 => '584b998665e655a149e61d5582ade09bcd8eb69b',
      1 => 
      array (
        0 => 'app\\tests\\architecture\\rule\\infrastructuredependenciestest',
      ),
      2 => 
      array (
        0 => 'app\\tests\\architecture\\rule\\testinfrastructurestaysinsidemodule',
        1 => 'app\\tests\\architecture\\rule\\testinfrastructuredependsonlyonmoduleandshared',
        2 => 'app\\tests\\architecture\\rule\\foreignmodulenamespaces',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Architecture/Rule/SharedContractsDependenciesTest.php' => 
    array (
      0 => 'c6118eef438fe65281a564d72445fe358bedfc69',
      1 => 
      array (
        0 => 'app\\tests\\architecture\\rule\\sharedcontractsdependenciestest',
      ),
      2 => 
      array (
        0 => 'app\\tests\\architecture\\rule\\testsharedportsdependonlyondomains',
        1 => 'app\\tests\\architecture\\rule\\testsharedportsareinfrastructureagnostic',
        2 => 'app\\tests\\architecture\\rule\\sharedapplicationsupportselectors',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Architecture/Support/ArchitectureLayer.php' => 
    array (
      0 => 'dc615395b9dfdacf32d87ae4fa5f391822fc0b9b',
      1 => 
      array (
        0 => 'app\\tests\\architecture\\support\\architecturelayer',
      ),
      2 => 
      array (
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Architecture/Support/ArchitectureNamespace.php' => 
    array (
      0 => '5367bc862fea20156d825f1fd808008ef1035b74',
      1 => 
      array (
        0 => 'app\\tests\\architecture\\support\\architecturenamespace',
      ),
      2 => 
      array (
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Architecture/Support/Module.php' => 
    array (
      0 => '59a756760808605de281462e8b957635eadeab71',
      1 => 
      array (
        0 => 'app\\tests\\architecture\\support\\module',
      ),
      2 => 
      array (
        0 => 'app\\tests\\architecture\\support\\__construct',
        1 => 'app\\tests\\architecture\\support\\layernamespace',
        2 => 'app\\tests\\architecture\\support\\layerpath',
        3 => 'app\\tests\\architecture\\support\\haslayer',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Architecture/Support/ModuleCollection.php' => 
    array (
      0 => '52582d5c78d8bbc9c675dbb4f83acd9c9bbd5a1c',
      1 => 
      array (
        0 => 'app\\tests\\architecture\\support\\modulecollection',
      ),
      2 => 
      array (
        0 => 'app\\tests\\architecture\\support\\all',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Architecture/Unit/AdapterNamingTest.php' => 
    array (
      0 => 'ccbbe5c8aad3d1892b97e2cc79e06deed41060b5',
      1 => 
      array (
        0 => 'app\\tests\\architecture\\unit\\adapternamingtest',
      ),
      2 => 
      array (
        0 => 'app\\tests\\architecture\\unit\\testalladaptersendwithadaptersuffix',
        1 => 'app\\tests\\architecture\\unit\\testadaptersareinsubdirectory',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Architecture/Unit/ApplicationNamingTest.php' => 
    array (
      0 => '80d3501b35c0627a3ce717effacfb4365583a488',
      1 => 
      array (
        0 => 'app\\tests\\architecture\\unit\\applicationnamingtest',
      ),
      2 => 
      array (
        0 => 'app\\tests\\architecture\\unit\\testcommandsendwithcommandsuffix',
        1 => 'app\\tests\\architecture\\unit\\testqueriesendwithquerysuffix',
        2 => 'app\\tests\\architecture\\unit\\testhandlersendwithhandlersuffix',
        3 => 'app\\tests\\architecture\\unit\\assertusecasenaming',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Architecture/Unit/ClassLocationTest.php' => 
    array (
      0 => '9bfcd7ef23c9db42d1e77bc338eb96fcfdce1043',
      1 => 
      array (
        0 => 'app\\tests\\architecture\\unit\\classlocationtest',
      ),
      2 => 
      array (
        0 => 'app\\tests\\architecture\\unit\\testadaptersareonlyininfrastructure',
        1 => 'app\\tests\\architecture\\unit\\testportsareonlyinportdirectory',
        2 => 'app\\tests\\architecture\\unit\\testrecordsareonlyinpersistencedirectory',
        3 => 'app\\tests\\architecture\\unit\\testrepositoriesareonlyinpersistencedirectory',
        4 => 'app\\tests\\architecture\\unit\\testmappersareonlyinpersistencedirectory',
        5 => 'app\\tests\\architecture\\unit\\assertsuffixonlyindirectory',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Architecture/Unit/ConsoleNamingTest.php' => 
    array (
      0 => '9602ae4838c9b28b2712a0941f4ce2706003c539',
      1 => 
      array (
        0 => 'app\\tests\\architecture\\unit\\consolenamingtest',
      ),
      2 => 
      array (
        0 => 'app\\tests\\architecture\\unit\\testconsolecommandsendwithcommandsuffix',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Architecture/Unit/DomainNamingTest.php' => 
    array (
      0 => '845d12065574462364c4219d42d9c4e15aa148a5',
      1 => 
      array (
        0 => 'app\\tests\\architecture\\unit\\domainnamingtest',
      ),
      2 => 
      array (
        0 => 'app\\tests\\architecture\\unit\\testeventsendwitheventsuffix',
        1 => 'app\\tests\\architecture\\unit\\testexceptionsendwithexceptionsuffix',
        2 => 'app\\tests\\architecture\\unit\\assertdomainnaming',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Architecture/Unit/InterventionAuthorizationEnforcementTest.php' => 
    array (
      0 => '5b467e097777c3cadb4563bbb90c29c290b4e9a6',
      1 => 
      array (
        0 => 'app\\tests\\architecture\\unit\\interventionauthorizationenforcementtest',
      ),
      2 => 
      array (
        0 => 'app\\tests\\architecture\\unit\\testuserfacinghandlersenforceauthorization',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Architecture/Unit/ModuleStructureTest.php' => 
    array (
      0 => '4b697701d29fe5c8fcca01aca649deaa07c5fcbf',
      1 => 
      array (
        0 => 'app\\tests\\architecture\\unit\\modulestructuretest',
      ),
      2 => 
      array (
        0 => 'app\\tests\\architecture\\unit\\testeachmodulecontainsrequiredlayers',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Architecture/Unit/PersistenceNamingTest.php' => 
    array (
      0 => '1fbad52553475cc9c6c855aef923c4b5e80cdbfe',
      1 => 
      array (
        0 => 'app\\tests\\architecture\\unit\\persistencenamingtest',
      ),
      2 => 
      array (
        0 => 'app\\tests\\architecture\\unit\\testrecordsendwithrecordsuffix',
        1 => 'app\\tests\\architecture\\unit\\testmappersendwithmappersuffix',
        2 => 'app\\tests\\architecture\\unit\\testrepositoriesendwithrepositorysuffix',
        3 => 'app\\tests\\architecture\\unit\\assertnamingconvention',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Architecture/Unit/PortNamingTest.php' => 
    array (
      0 => '0dcf9ebadc37011123f665c336c4ae765fa904c2',
      1 => 
      array (
        0 => 'app\\tests\\architecture\\unit\\portnamingtest',
      ),
      2 => 
      array (
        0 => 'app\\tests\\architecture\\unit\\testallportsendwithportsuffix',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Architecture/Unit/PresentationNamingTest.php' => 
    array (
      0 => '9639e403db573ab0b06598bd8516da0cbb768b69',
      1 => 
      array (
        0 => 'app\\tests\\architecture\\unit\\presentationnamingtest',
      ),
      2 => 
      array (
        0 => 'app\\tests\\architecture\\unit\\testprocessorsendwithprocessorsuffix',
        1 => 'app\\tests\\architecture\\unit\\testprovidersendwithprovidersuffix',
        2 => 'app\\tests\\architecture\\unit\\assertpresentationnaming',
      ),
      3 => 
      array (
      ),
    ),
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
    '/var/www/html/tests/bootstrap.php' => 
    array (
      0 => '64918840518fde0256a364d1e1490659ceaf0f81',
      1 => 
      array (
      ),
      2 => 
      array (
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/E2E/AuthLoginFlowTest.php' => 
    array (
      0 => 'e91ec8d98969c4badccd92781f077a9a4971d8d8',
      1 => 
      array (
        0 => 'app\\tests\\e2e\\authloginflowtest',
      ),
      2 => 
      array (
        0 => 'app\\tests\\e2e\\testloginwithvalidcredentials',
        1 => 'app\\tests\\e2e\\testloginwithinvalidpassword',
        2 => 'app\\tests\\e2e\\testloginwithnonexistentuser',
        3 => 'app\\tests\\e2e\\testloginwithinactiveuser',
        4 => 'app\\tests\\e2e\\testloginsetsrefreshtokencookie',
        5 => 'app\\tests\\e2e\\testrefreshtokenflow',
        6 => 'app\\tests\\e2e\\testrefreshwithoutcookiereturns401',
        7 => 'app\\tests\\e2e\\testrefreshwithinvalidtokenreturns401',
        8 => 'app\\tests\\e2e\\testlogoutclearsrefreshtokencookie',
        9 => 'app\\tests\\e2e\\testlogoutwithoutauthenticationsucceeds',
        10 => 'app\\tests\\e2e\\testcompleteauthenticationflow',
        11 => 'app\\tests\\e2e\\testloginwithemptyemail',
        12 => 'app\\tests\\e2e\\testloginwithemptypassword',
        13 => 'app\\tests\\e2e\\testloginresponsestructure',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/E2E/AuthorizationCodeFlowTest.php' => 
    array (
      0 => 'd010ab0303cbc0754bc74da4ae7c3795fdeae689',
      1 => 
      array (
        0 => 'app\\tests\\e2e\\authorizationcodeflowtest',
      ),
      2 => 
      array (
        0 => 'app\\tests\\e2e\\testauthorizationcodegrantrequirescode',
        1 => 'app\\tests\\e2e\\testauthorizationcodegrantrequiresredirecturi',
        2 => 'app\\tests\\e2e\\testauthorizationcodegrantrequirescodeverifier',
        3 => 'app\\tests\\e2e\\testauthorizationcodegrantwithinvalidcode',
        4 => 'app\\tests\\e2e\\testpkces256challengegeneration',
        5 => 'app\\tests\\e2e\\generatecodeverifier',
        6 => 'app\\tests\\e2e\\generatecodechallenge',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/E2E/ClientManagementFlowTest.php' => 
    array (
      0 => '5b9342882bdf23a8c97f29728a73bd851af42c5b',
      1 => 
      array (
        0 => 'app\\tests\\e2e\\clientmanagementflowtest',
      ),
      2 => 
      array (
        0 => 'app\\tests\\e2e\\testclientcredentialsonlyclient',
        1 => 'app\\tests\\e2e\\testdifferentscopes',
        2 => 'app\\tests\\e2e\\testexpiredtokenhandling',
        3 => 'app\\tests\\e2e\\testsqlinjectionprevention',
        4 => 'app\\tests\\e2e\\testregenerateclientsecret',
        5 => 'app\\tests\\e2e\\testregenerateclientsecretwithoutauth',
        6 => 'app\\tests\\e2e\\testactivateclient',
        7 => 'app\\tests\\e2e\\testactivateclientwithoutauth',
        8 => 'app\\tests\\e2e\\testdeactivateclient',
        9 => 'app\\tests\\e2e\\testdeactivateclientwithoutauth',
        10 => 'app\\tests\\e2e\\testactivatenonexistentclient',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/E2E/CurrentUserProfileFlowTest.php' => 
    array (
      0 => '1b78b2d8f3c083aa4dadb79b24a7bbcd22ad5da2',
      1 => 
      array (
        0 => 'app\\tests\\e2e\\currentuserprofileflowtest',
      ),
      2 => 
      array (
        0 => 'app\\tests\\e2e\\testcurrentusercanupdateprofileandavatar',
        1 => 'app\\tests\\e2e\\minimalpngbinary',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/E2E/EquipmentFlowTest.php' => 
    array (
      0 => '2d0e3e4efcb88c3304e2b6d39704231fa6293a21',
      1 => 
      array (
        0 => 'app\\tests\\e2e\\equipmentflowtest',
      ),
      2 => 
      array (
        0 => 'app\\tests\\e2e\\testcompleteequipmentmanagementflow',
        1 => 'app\\tests\\e2e\\testcreateequipmentreturns409onduplicateserialnumber',
        2 => 'app\\tests\\e2e\\testequipmentendpointsrequireauthentication',
        3 => 'app\\tests\\e2e\\loginandgetuseraccesstoken',
        4 => 'app\\tests\\e2e\\createorganization',
        5 => 'app\\tests\\e2e\\extractresourceid',
        6 => 'app\\tests\\e2e\\getcollectionmembers',
        7 => 'app\\tests\\e2e\\collectioncontainsid',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/E2E/InspectionFlowTest.php' => 
    array (
      0 => '05251484c34fbf0264f7d181229d086c90b79e02',
      1 => 
      array (
        0 => 'app\\tests\\e2e\\inspectionflowtest',
      ),
      2 => 
      array (
        0 => 'app\\tests\\e2e\\testcompleteinspectionlifecycleflow',
        1 => 'app\\tests\\e2e\\testclosedraftinspectionreturns409',
        2 => 'app\\tests\\e2e\\testdoublesubmitreturns409',
        3 => 'app\\tests\\e2e\\testaddnonconformitytoclosedinspectionreturns409',
        4 => 'app\\tests\\e2e\\testdoubleclosereturns409',
        5 => 'app\\tests\\e2e\\testdoublearchivechecklistreturns409',
        6 => 'app\\tests\\e2e\\testinspectionendpointsrequireauthentication',
        7 => 'app\\tests\\e2e\\loginandgetuseraccesstoken',
        8 => 'app\\tests\\e2e\\createorganization',
        9 => 'app\\tests\\e2e\\createequipment',
        10 => 'app\\tests\\e2e\\extractresourceid',
        11 => 'app\\tests\\e2e\\getcollectionmembers',
        12 => 'app\\tests\\e2e\\collectioncontainsid',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/E2E/InterventionFlowTest.php' => 
    array (
      0 => 'af7e561a00818c9a63bfc3a258bc9e64f1660656',
      1 => 
      array (
        0 => 'app\\tests\\e2e\\interventionflowtest',
      ),
      2 => 
      array (
        0 => 'app\\tests\\e2e\\testcreatedraftinterventionexposesdefaultsandcounts',
        1 => 'app\\tests\\e2e\\testplaninterventionthroughtheworkflow',
        2 => 'app\\tests\\e2e\\testplanwithoutschedulereturnsconflict',
        3 => 'app\\tests\\e2e\\testtransitionwithoutifmatchisrejected',
        4 => 'app\\tests\\e2e\\testinterventionendpointsrequireauthentication',
        5 => 'app\\tests\\e2e\\createdraftintervention',
        6 => 'app\\tests\\e2e\\patch',
        7 => 'app\\tests\\e2e\\getresource',
        8 => 'app\\tests\\e2e\\firstmemberiri',
        9 => 'app\\tests\\e2e\\createfacility',
        10 => 'app\\tests\\e2e\\loginandgetuseraccesstoken',
        11 => 'app\\tests\\e2e\\createorganization',
        12 => 'app\\tests\\e2e\\headers',
        13 => 'app\\tests\\e2e\\extractresourceid',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/E2E/MfaFlowTest.php' => 
    array (
      0 => 'ee71a6c7867010fce810f7a82b86ba0e54940046',
      1 => 
      array (
        0 => 'app\\tests\\e2e\\mfaflowtest',
      ),
      2 => 
      array (
        0 => 'app\\tests\\e2e\\teardown',
        1 => 'app\\tests\\e2e\\testmfaloginflow',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/E2E/OAuth2FlowTest.php' => 
    array (
      0 => 'ac02f20f85d4ef0e36431546c00aa8750b393a23',
      1 => 
      array (
        0 => 'app\\tests\\e2e\\oauth2flowtest',
      ),
      2 => 
      array (
        0 => 'app\\tests\\e2e\\testclientcredentialsflowcomplete',
        1 => 'app\\tests\\e2e\\testclientcredentialswithinvalidsecret',
        2 => 'app\\tests\\e2e\\testclientcredentialswithnonexistentclient',
        3 => 'app\\tests\\e2e\\testopenidconfiguration',
        4 => 'app\\tests\\e2e\\testjwksendpoint',
        5 => 'app\\tests\\e2e\\testprotectedresourcewithouttoken',
        6 => 'app\\tests\\e2e\\testprotectedresourcewithinvalidtoken',
        7 => 'app\\tests\\e2e\\testuserinfowithvalidtoken',
        8 => 'app\\tests\\e2e\\testintrospectionwithinvalidtoken',
        9 => 'app\\tests\\e2e\\testrevocationwithinvalidtoken',
        10 => 'app\\tests\\e2e\\testmultipletokensforsameclient',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/E2E/OAuth2WebTestCase.php' => 
    array (
      0 => 'abf9eba3105dfdd07c9a0e6e4c6fdb23fa137b2e',
      1 => 
      array (
        0 => 'app\\tests\\e2e\\oauth2webtestcase',
      ),
      2 => 
      array (
        0 => 'app\\tests\\e2e\\createclientwithfixtures',
        1 => 'app\\tests\\e2e\\loadtestfixtures',
        2 => 'app\\tests\\e2e\\getaccesstoken',
        3 => 'app\\tests\\e2e\\createuser',
        4 => 'app\\tests\\e2e\\createandactivateuser',
        5 => 'app\\tests\\e2e\\decodejsonresponse',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/E2E/OnboardingFlowTest.php' => 
    array (
      0 => '573994c26e9905c30878f4fcaa4ac0baa6e38194',
      1 => 
      array (
        0 => 'app\\tests\\e2e\\onboardingflowtest',
      ),
      2 => 
      array (
        0 => 'app\\tests\\e2e\\testonboardingendpointsrequireauthentication',
        1 => 'app\\tests\\e2e\\testgetflowreturnsinitialstate',
        2 => 'app\\tests\\e2e\\testcompleteorganizationonboardingflow',
        3 => 'app\\tests\\e2e\\testexecutewrongstepreturnsconflict',
        4 => 'app\\tests\\e2e\\testrollbackaftercreateorganization',
        5 => 'app\\tests\\e2e\\teststartwithresetreturnsconsistentstate',
        6 => 'app\\tests\\e2e\\loginandgetuseraccesstoken',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/E2E/OrganizationFlowTest.php' => 
    array (
      0 => '724cfbed114fddb69003d1acbf27aeabd4cfda8a',
      1 => 
      array (
        0 => 'app\\tests\\e2e\\organizationflowtest',
      ),
      2 => 
      array (
        0 => 'app\\tests\\e2e\\testorganizationdashboardendpointsexposeauthorizeddashboardcontracts',
        1 => 'app\\tests\\e2e\\testrestrictedmemberisdeniedprotectedorganizationdashboardendpoints',
        2 => 'app\\tests\\e2e\\testcompleteorganizationmanagementflow',
        3 => 'app\\tests\\e2e\\testorganizationdashboardendpointsaredeniedacrossorganizations',
        4 => 'app\\tests\\e2e\\testorganizationendpointsrequireauthentication',
        5 => 'app\\tests\\e2e\\loginandgetuseraccesstoken',
        6 => 'app\\tests\\e2e\\createorganization',
        7 => 'app\\tests\\e2e\\finduseridbyemail',
        8 => 'app\\tests\\e2e\\extractresourceid',
        9 => 'app\\tests\\e2e\\getcollectionmembers',
        10 => 'app\\tests\\e2e\\collectioncontainsid',
        11 => 'app\\tests\\e2e\\requestorganizationdashboard',
        12 => 'app\\tests\\e2e\\requestorganizationdashboardtrend',
        13 => 'app\\tests\\e2e\\assertorganizationdashboarddenied',
        14 => 'app\\tests\\e2e\\assertorganizationdashboardtrenddenied',
        15 => 'app\\tests\\e2e\\addorganizationmember',
        16 => 'app\\tests\\e2e\\listorganizationrolesbyname',
        17 => 'app\\tests\\e2e\\createorganizationrole',
        18 => 'app\\tests\\e2e\\removerolefrommember',
        19 => 'app\\tests\\e2e\\assignroletomember',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/E2E/OtpChallengeFlowTest.php' => 
    array (
      0 => '4421d6e90a3780a7d9668b91ba1462cdd3c1a7c2',
      1 => 
      array (
        0 => 'app\\tests\\e2e\\otpchallengeflowtest',
      ),
      2 => 
      array (
        0 => 'app\\tests\\e2e\\testcreatechallengewithvaliddata',
        1 => 'app\\tests\\e2e\\testcreatechallengewithinvalidchannel',
        2 => 'app\\tests\\e2e\\testcreatechallengewithoutauth',
        3 => 'app\\tests\\e2e\\testgetnonexistentchallengestatus',
        4 => 'app\\tests\\e2e\\testverifynonexistentchallenge',
        5 => 'app\\tests\\e2e\\testverifychallengewithoutcode',
        6 => 'app\\tests\\e2e\\testresendnonexistentchallenge',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/E2E/OtpConfigFlowTest.php' => 
    array (
      0 => 'f657ef95ee9b3f6875f2dcb015f49764254c5b2a',
      1 => 
      array (
        0 => 'app\\tests\\e2e\\otpconfigflowtest',
      ),
      2 => 
      array (
        0 => 'app\\tests\\e2e\\testlistpurposes',
        1 => 'app\\tests\\e2e\\testlistpurposeswithvalidtoken',
        2 => 'app\\tests\\e2e\\testlistchannels',
        3 => 'app\\tests\\e2e\\testlistchannelswithvalidtoken',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/E2E/PasswordResetFlowTest.php' => 
    array (
      0 => 'fbc5669db8ff55282a7684b002a0bc9112448e6c',
      1 => 
      array (
        0 => 'app\\tests\\e2e\\passwordresetflowtest',
      ),
      2 => 
      array (
        0 => 'app\\tests\\e2e\\testcompletepasswordresetflow',
        1 => 'app\\tests\\e2e\\testpasswordresetrequestwithnonexistentemail',
        2 => 'app\\tests\\e2e\\testpasswordresetrequestwithinvalidemail',
        3 => 'app\\tests\\e2e\\testpasswordresetconfirmwithinvalidtoken',
        4 => 'app\\tests\\e2e\\testpasswordresetconfirmwithweakpassword',
        5 => 'app\\tests\\e2e\\testpasswordresetrequestwithoutemail',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/E2E/RefreshTokenFlowTest.php' => 
    array (
      0 => '6420fe3a6ead76057025c5d17e9921e7fb0b43fa',
      1 => 
      array (
        0 => 'app\\tests\\e2e\\refreshtokenflowtest',
      ),
      2 => 
      array (
        0 => 'app\\tests\\e2e\\testrefreshtokengrantwithvalidtoken',
        1 => 'app\\tests\\e2e\\testrefreshtokengrantwithinvalidtoken',
        2 => 'app\\tests\\e2e\\testrefreshtokengrantwithouttoken',
        3 => 'app\\tests\\e2e\\testrefreshtokengrantwithexpiredtoken',
        4 => 'app\\tests\\e2e\\testauthrefreshendpointwithcookie',
        5 => 'app\\tests\\e2e\\testauthrefreshendpointwithoutcookie',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/E2E/RolePermissionManagementFlowTest.php' => 
    array (
      0 => 'e7a8135314157d5cffc4b9319ba58f44b91e9c27',
      1 => 
      array (
        0 => 'app\\tests\\e2e\\rolepermissionmanagementflowtest',
      ),
      2 => 
      array (
        0 => 'app\\tests\\e2e\\testlistpermissionswithvalidtoken',
        1 => 'app\\tests\\e2e\\testlistpermissionswithouttoken',
        2 => 'app\\tests\\e2e\\testcreatepermission',
        3 => 'app\\tests\\e2e\\testcreatepermissionwithinvalidname',
        4 => 'app\\tests\\e2e\\testgetpermissionbyid',
        5 => 'app\\tests\\e2e\\testlistroleswithvalidtoken',
        6 => 'app\\tests\\e2e\\testlistroleswithouttoken',
        7 => 'app\\tests\\e2e\\testcreaterole',
        8 => 'app\\tests\\e2e\\testcreaterolewithinvalidname',
        9 => 'app\\tests\\e2e\\testgetrolebyid',
        10 => 'app\\tests\\e2e\\testupdaterole',
        11 => 'app\\tests\\e2e\\testaddpermissiontorole',
        12 => 'app\\tests\\e2e\\testremovepermissionfromrole',
        13 => 'app\\tests\\e2e\\testdeleterolewithoutauth',
        14 => 'app\\tests\\e2e\\testdeletenonexistentrole',
        15 => 'app\\tests\\e2e\\createclientwithfixtures',
        16 => 'app\\tests\\e2e\\loadtestfixtures',
        17 => 'app\\tests\\e2e\\getaccesstoken',
        18 => 'app\\tests\\e2e\\decodejsonresponse',
        19 => 'app\\tests\\e2e\\getcollectionmembers',
        20 => 'app\\tests\\e2e\\getstringvalue',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/E2E/SeededFixturesFlowTest.php' => 
    array (
      0 => '1500569020341e790811f8a50f1c8eda28d72b3b',
      1 => 
      array (
        0 => 'app\\tests\\e2e\\seededfixturesflowtest',
      ),
      2 => 
      array (
        0 => 'app\\tests\\e2e\\testseededadmincanreaddashboardtrendandseededtotals',
        1 => 'app\\tests\\e2e\\testseededinspectorisdenieddashboardbutcanreadoperationalcollections',
        2 => 'app\\tests\\e2e\\requestdashboard',
        3 => 'app\\tests\\e2e\\loginandgetuseraccesstoken',
        4 => 'app\\tests\\e2e\\dashboardperiodquery',
        5 => 'app\\tests\\e2e\\asserttrendseries',
        6 => 'app\\tests\\e2e\\normalizewidget',
        7 => 'app\\tests\\e2e\\summaryvalue',
        8 => 'app\\tests\\e2e\\primarykey',
        9 => 'app\\tests\\e2e\\primaryvalue',
        10 => 'app\\tests\\e2e\\sumseries',
        11 => 'app\\tests\\e2e\\countnonzerobuckets',
        12 => 'app\\tests\\e2e\\normalizeseries',
        13 => 'app\\tests\\e2e\\getcollectionmembers',
        14 => 'app\\tests\\e2e\\collectioncontainsfieldvalue',
        15 => 'app\\tests\\e2e\\bucketvalue',
        16 => 'app\\tests\\e2e\\findmetricbyfield',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/E2E/SessionManagementFlowTest.php' => 
    array (
      0 => '08f4117a8eed20833d78ac2bd48a4db4078cfaab',
      1 => 
      array (
        0 => 'app\\tests\\e2e\\sessionmanagementflowtest',
      ),
      2 => 
      array (
        0 => 'app\\tests\\e2e\\testlistsessionswithvalidtoken',
        1 => 'app\\tests\\e2e\\testlistsessionswithouttoken',
        2 => 'app\\tests\\e2e\\testgetsessionbyid',
        3 => 'app\\tests\\e2e\\testgetnonexistentsession',
        4 => 'app\\tests\\e2e\\testrevokesessionwithoutauth',
        5 => 'app\\tests\\e2e\\testrevokenonexistentsession',
        6 => 'app\\tests\\e2e\\testrevokeallsessionswithvalidtoken',
        7 => 'app\\tests\\e2e\\testrevokeallsessionswithoutauth',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/E2E/TenantManagementFlowTest.php' => 
    array (
      0 => 'd66133b1cfe696642362714b7e1e2caa7cfc381d',
      1 => 
      array (
        0 => 'app\\tests\\e2e\\tenantmanagementflowtest',
      ),
      2 => 
      array (
        0 => 'app\\tests\\e2e\\testlisttenantswithvalidtoken',
        1 => 'app\\tests\\e2e\\testlisttenantswithouttoken',
        2 => 'app\\tests\\e2e\\testgetnonexistenttenant',
        3 => 'app\\tests\\e2e\\testgettenantwithoutauth',
        4 => 'app\\tests\\e2e\\testupdatetenantwithvalidtoken',
        5 => 'app\\tests\\e2e\\testactivatetenantwithvalidtoken',
        6 => 'app\\tests\\e2e\\testdeactivatetenantwithvalidtoken',
        7 => 'app\\tests\\e2e\\testdeletetenantwithvalidtoken',
        8 => 'app\\tests\\e2e\\testcreatetenantwithvaliddata',
        9 => 'app\\tests\\e2e\\testcreatetenantwithoutauth',
        10 => 'app\\tests\\e2e\\testcreatetenantwithinvaliddata',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/E2E/TokenValidationFlowTest.php' => 
    array (
      0 => 'e1404fd225432db22ab961adca9eb5266d1768e2',
      1 => 
      array (
        0 => 'app\\tests\\e2e\\tokenvalidationflowtest',
      ),
      2 => 
      array (
        0 => 'app\\tests\\e2e\\testtokenwithspecificscopes',
        1 => 'app\\tests\\e2e\\testtokenwithmultiplescopes',
        2 => 'app\\tests\\e2e\\testtokenwithoutscopeparameter',
        3 => 'app\\tests\\e2e\\testtokenwithinvalidscope',
        4 => 'app\\tests\\e2e\\testintrospectionreturnstokeninfo',
        5 => 'app\\tests\\e2e\\testintrospectionofrevokedtoken',
        6 => 'app\\tests\\e2e\\testtokenhasexpiration',
        7 => 'app\\tests\\e2e\\testintrospectionshowsexpiration',
        8 => 'app\\tests\\e2e\\testtokentypeisbearer',
        9 => 'app\\tests\\e2e\\testbearertokenauthenticationformat',
        10 => 'app\\tests\\e2e\\testunsupportedgranttyperejected',
        11 => 'app\\tests\\e2e\\testmissinggranttyperejected',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/E2E/TotpFlowTest.php' => 
    array (
      0 => '556d35504b84dc926766178db8d29e5650122bfc',
      1 => 
      array (
        0 => 'app\\tests\\e2e\\totpflowtest',
      ),
      2 => 
      array (
        0 => 'app\\tests\\e2e\\testsetuptotpwithvalidtoken',
        1 => 'app\\tests\\e2e\\testsetuptotpwithoutauth',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/E2E/TrustedDeviceFlowTest.php' => 
    array (
      0 => '441c8f039385194941186b7abc5214c17234562b',
      1 => 
      array (
        0 => 'app\\tests\\e2e\\trusteddeviceflowtest',
      ),
      2 => 
      array (
        0 => 'app\\tests\\e2e\\setup',
        1 => 'app\\tests\\e2e\\testlisttrusteddeviceswithvalidtoken',
        2 => 'app\\tests\\e2e\\testlisttrusteddeviceswithouttoken',
        3 => 'app\\tests\\e2e\\testrevokedevicewithoutauth',
        4 => 'app\\tests\\e2e\\testrevokenonexistentdevice',
        5 => 'app\\tests\\e2e\\testrevokealldeviceswithvalidtoken',
        6 => 'app\\tests\\e2e\\testrevokealldeviceswithoutauth',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/E2E/UserManagementFlowTest.php' => 
    array (
      0 => '8482b75899957bed75a2707a8d644b382d26387d',
      1 => 
      array (
        0 => 'app\\tests\\e2e\\usermanagementflowtest',
      ),
      2 => 
      array (
        0 => 'app\\tests\\e2e\\testlistuserswithvalidtoken',
        1 => 'app\\tests\\e2e\\testlistuserswithouttoken',
        2 => 'app\\tests\\e2e\\testusercreationrequiresauthorization',
        3 => 'app\\tests\\e2e\\testcreateuserwithinvaliddata',
        4 => 'app\\tests\\e2e\\testcreateuserwithinvalidemail',
        5 => 'app\\tests\\e2e\\testcreateuserwithweakpassword',
        6 => 'app\\tests\\e2e\\testgetuserbyid',
        7 => 'app\\tests\\e2e\\testgetnonexistentuser',
        8 => 'app\\tests\\e2e\\testupdateuserwithpatch',
        9 => 'app\\tests\\e2e\\testupdateuserwithpatchwithoutauth',
        10 => 'app\\tests\\e2e\\testreplaceuserwithput',
        11 => 'app\\tests\\e2e\\testreplaceuserwithputwithoutauth',
        12 => 'app\\tests\\e2e\\testupdatenonexistentuser',
        13 => 'app\\tests\\e2e\\testdeleteuser',
        14 => 'app\\tests\\e2e\\testdeleteuserwithoutauth',
        15 => 'app\\tests\\e2e\\testdeletenonexistentuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Functional/Api/AuditApiTest.php' => 
    array (
      0 => 'e1c693e3d9dcfe4708d5f1db2c9bebc456e3ef6d',
      1 => 
      array (
        0 => 'tests\\functional\\api\\auditapitest',
      ),
      2 => 
      array (
        0 => 'tests\\functional\\api\\testauditeventsendpointrequiresauthentication',
        1 => 'tests\\functional\\api\\testauditeventendpointexists',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Functional/Api/AuthApiTest.php' => 
    array (
      0 => '336136a83e7e4833d99a6e582dc5e40ec05e60ed',
      1 => 
      array (
        0 => 'tests\\functional\\api\\authapitest',
        1 => 'tests\\functional\\api\\testcommandbus',
        2 => 'tests\\functional\\api\\testquerybus',
      ),
      2 => 
      array (
        0 => 'tests\\functional\\api\\setup',
        1 => 'tests\\functional\\api\\teardown',
        2 => 'tests\\functional\\api\\testloginendpointrejectsget',
        3 => 'tests\\functional\\api\\testloginendpointreturnsunauthorizedwhencommandfails',
        4 => 'tests\\functional\\api\\testloginendpointreturnsmfaresponse',
        5 => 'tests\\functional\\api\\testloginendpointreturnstokensandsetscookie',
        6 => 'tests\\functional\\api\\testrefreshendpointreturnsunauthorizedwithoutcookie',
        7 => 'tests\\functional\\api\\testrefreshendpointreturnstokenswhencookieprovided',
        8 => 'tests\\functional\\api\\testlogoutendpointclearscookie',
        9 => 'tests\\functional\\api\\setcommandbus',
        10 => 'tests\\functional\\api\\setquerybus',
        11 => 'tests\\functional\\api\\assertokorcreated',
        12 => 'tests\\functional\\api\\__construct',
        13 => 'tests\\functional\\api\\dispatch',
        14 => 'tests\\functional\\api\\__construct',
        15 => 'tests\\functional\\api\\ask',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Functional/Api/ClientApiTest.php' => 
    array (
      0 => '1fddb5cc39b27850d1ba58fd0d754c0d924e8290',
      1 => 
      array (
        0 => 'tests\\functional\\api\\clientapitest',
      ),
      2 => 
      array (
        0 => 'tests\\functional\\api\\setup',
        1 => 'tests\\functional\\api\\testclientendpointsrequireauthentication',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Functional/Api/CurrentOrganizationMemberProfileApiTest.php' => 
    array (
      0 => '51c39a8d682b349b5f1bca5204e4a73d32b69436',
      1 => 
      array (
        0 => 'tests\\functional\\api\\currentorganizationmemberprofileapitest',
      ),
      2 => 
      array (
        0 => 'tests\\functional\\api\\testgetcurrentorganizationmemberprofilerequiresauthentication',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Functional/Api/CurrentUserProfileApiTest.php' => 
    array (
      0 => 'adb0fe7fc529f7677c933e7fa39e37b4c8b094b2',
      1 => 
      array (
        0 => 'tests\\functional\\api\\currentuserprofileapitest',
      ),
      2 => 
      array (
        0 => 'tests\\functional\\api\\testgetcurrentuserprofilerequiresauthentication',
        1 => 'tests\\functional\\api\\testupdatecurrentuserprofilerequiresauthentication',
        2 => 'tests\\functional\\api\\testuploadcurrentuseravatarrequiresauthentication',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Functional/Api/DiscoveryApiTest.php' => 
    array (
      0 => 'faa8ef7c42fac6a43322018e2574298a1d99cc22',
      1 => 
      array (
        0 => 'tests\\functional\\api\\discoveryapitest',
      ),
      2 => 
      array (
        0 => 'tests\\functional\\api\\setup',
        1 => 'tests\\functional\\api\\testopenidconfigurationendpointreturnsvalidresponse',
        2 => 'tests\\functional\\api\\testopenidconfigurationcontainsrequiredfields',
        3 => 'tests\\functional\\api\\testopenidconfigurationcontainsendsessionandpromptvalues',
        4 => 'tests\\functional\\api\\testjwksendpointreturnsvalidresponse',
        5 => 'tests\\functional\\api\\testjwksendpointcontainskeys',
        6 => 'tests\\functional\\api\\testjwkskeyshaverequiredfields',
        7 => 'tests\\functional\\api\\decodejsonresponse',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Functional/Api/EquipmentApiTest.php' => 
    array (
      0 => '862e52e59446ba053275f7b32963e73807dcbb5f',
      1 => 
      array (
        0 => 'tests\\functional\\api\\equipmentapitest',
      ),
      2 => 
      array (
        0 => 'tests\\functional\\api\\testlistequipmentstatusesrequiresauthentication',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Functional/Api/FacilityApiTest.php' => 
    array (
      0 => 'dff6a1273981e509ed006fb56a6d63b041e968ac',
      1 => 
      array (
        0 => 'tests\\functional\\api\\facilityapitest',
      ),
      2 => 
      array (
        0 => 'tests\\functional\\api\\testlistfacilitystatusesrequiresauthentication',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Functional/Api/InspectionApiTest.php' => 
    array (
      0 => '3829773bd93c71ba7f44aa94db5c2e4e029343e6',
      1 => 
      array (
        0 => 'tests\\functional\\api\\inspectionapitest',
      ),
      2 => 
      array (
        0 => 'tests\\functional\\api\\testlistinspectionresultsrequiresauthentication',
        1 => 'tests\\functional\\api\\testlistinspectionstatusesrequiresauthentication',
        2 => 'tests\\functional\\api\\testlistinspectortypesrequiresauthentication',
        3 => 'tests\\functional\\api\\testlistcheckliststatusesrequiresauthentication',
        4 => 'tests\\functional\\api\\testlistnonconformitystatusesrequiresauthentication',
        5 => 'tests\\functional\\api\\testcreateinspectionrequiresauthentication',
        6 => 'tests\\functional\\api\\testlistinspectionsrequiresauthentication',
        7 => 'tests\\functional\\api\\testgetinspectionrequiresauthentication',
        8 => 'tests\\functional\\api\\testsubmitinspectionrequiresauthentication',
        9 => 'tests\\functional\\api\\testcloseinspectionrequiresauthentication',
        10 => 'tests\\functional\\api\\testaddnonconformityrequiresauthentication',
        11 => 'tests\\functional\\api\\testlistnonconformitiesrequiresauthentication',
        12 => 'tests\\functional\\api\\testcreatechecklistrequiresauthentication',
        13 => 'tests\\functional\\api\\testlistchecklistsrequiresauthentication',
        14 => 'tests\\functional\\api\\testgetchecklistrequiresauthentication',
        15 => 'tests\\functional\\api\\testarchivechecklistrequiresauthentication',
        16 => 'tests\\functional\\api\\testeditinspectionrequiresauthentication',
        17 => 'tests\\functional\\api\\testcancelinspectionrequiresauthentication',
        18 => 'tests\\functional\\api\\testgetnonconformityrequiresauthentication',
        19 => 'tests\\functional\\api\\testupdatenonconformitystatusrequiresauthentication',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Functional/Api/OAuth2ApiTest.php' => 
    array (
      0 => 'a8066ef845401c5557bf8cdf020390d12b82272b',
      1 => 
      array (
        0 => 'tests\\functional\\api\\oauth2apitest',
      ),
      2 => 
      array (
        0 => 'tests\\functional\\api\\setup',
        1 => 'tests\\functional\\api\\testtokenendpointacceptspost',
        2 => 'tests\\functional\\api\\testtokenendpointrejectsget',
        3 => 'tests\\functional\\api\\testtokenendpointvalidatesclientcredentials',
        4 => 'tests\\functional\\api\\testintrospectionendpointexists',
        5 => 'tests\\functional\\api\\testrevocationendpointexists',
        6 => 'tests\\functional\\api\\testlogoutendpointreturnsjson',
        7 => 'tests\\functional\\api\\testlogoutendpointrejectsinvalididtokenhint',
        8 => 'tests\\functional\\api\\testlogoutendpointrequiresclientidwithredirect',
        9 => 'tests\\functional\\api\\testuserinfoendpointrequiresauthentication',
        10 => 'tests\\functional\\api\\testuserinfoendpointrejectsinvalidtoken',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Functional/Api/OrganizationApiTest.php' => 
    array (
      0 => '3584f35e153cb37384ed399b3f0c2d024bc6a8b6',
      1 => 
      array (
        0 => 'tests\\functional\\api\\organizationapitest',
      ),
      2 => 
      array (
        0 => 'tests\\functional\\api\\testlistorganizationstatusesrequiresauthentication',
        1 => 'tests\\functional\\api\\testlistorganizationinvitationstatusesrequiresauthentication',
        2 => 'tests\\functional\\api\\testgetorganizationdashboardrequiresauthentication',
        3 => 'tests\\functional\\api\\testgetorganizationdashboardtrendendpointsrequireauthentication',
        4 => 'tests\\functional\\api\\testlegacyorganizationstatisticsendpointsareremoved',
        5 => 'tests\\functional\\api\\testcreateorganizationrequiresauthentication',
        6 => 'tests\\functional\\api\\testlistorganizationsrequiresauthentication',
        7 => 'tests\\functional\\api\\testgetorganizationrequiresauthentication',
        8 => 'tests\\functional\\api\\testinvitememberrequiresauthentication',
        9 => 'tests\\functional\\api\\testlistmembersrequiresauthentication',
        10 => 'tests\\functional\\api\\testremovememberrequiresauthentication',
        11 => 'tests\\functional\\api\\testcreaterolerequiresauthentication',
        12 => 'tests\\functional\\api\\testlistrolesrequiresauthentication',
        13 => 'tests\\functional\\api\\testupdaterolerequiresauthentication',
        14 => 'tests\\functional\\api\\testdeleterolerequiresauthentication',
        15 => 'tests\\functional\\api\\testassignroletomemberrequiresauthentication',
        16 => 'tests\\functional\\api\\testremoverolefrommemberrequiresauthentication',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Functional/Api/SessionApiTest.php' => 
    array (
      0 => '26be9b097c53a0b9f0fe7d5829dcf6b95f15b986',
      1 => 
      array (
        0 => 'tests\\functional\\api\\sessionapitest',
      ),
      2 => 
      array (
        0 => 'tests\\functional\\api\\testsessionsendpointrequiresauthentication',
        1 => 'tests\\functional\\api\\testrevokeallsessionsendpointexists',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Functional/Api/TenantApiTest.php' => 
    array (
      0 => '8088c164b3facfd577d5ece436dd2657c26fff7c',
      1 => 
      array (
        0 => 'tests\\functional\\api\\tenantapitest',
      ),
      2 => 
      array (
        0 => 'tests\\functional\\api\\testtenantsendpointrequiresauthentication',
        1 => 'tests\\functional\\api\\testcreatetenantrequiresauthentication',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Functional/Api/UserApiTest.php' => 
    array (
      0 => '15214f0f8394bd1816ed2fb9ee90561371db7887',
      1 => 
      array (
        0 => 'tests\\functional\\api\\userapitest',
      ),
      2 => 
      array (
        0 => 'tests\\functional\\api\\setup',
        1 => 'tests\\functional\\api\\testcreateuserendpointexists',
        2 => 'tests\\functional\\api\\testuserendpointsrequireauthentication',
        3 => 'tests\\functional\\api\\testcreateuserwithoutauthreturnsunauthorized',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Functional/Api/UserStatusApiTest.php' => 
    array (
      0 => '2b33373a0db430524f62718b871b26d9e362c5e9',
      1 => 
      array (
        0 => 'tests\\functional\\api\\userstatusapitest',
      ),
      2 => 
      array (
        0 => 'tests\\functional\\api\\testlistuserstatusesrequiresauthentication',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Functional/Console/CreateUserCommandTest.php' => 
    array (
      0 => '20fa0ca6d25a77697ddac7be6ed43c5d15683505',
      1 => 
      array (
        0 => 'tests\\functional\\console\\createusercommandtest',
      ),
      2 => 
      array (
        0 => 'tests\\functional\\console\\setup',
        1 => 'tests\\functional\\console\\testcommandexists',
        2 => 'tests\\functional\\console\\testcommandhasalias',
        3 => 'tests\\functional\\console\\testcommandrequiresemail',
        4 => 'tests\\functional\\console\\testcommandhasusernameoption',
        5 => 'tests\\functional\\console\\testcommandhasfirstnameoption',
        6 => 'tests\\functional\\console\\testcommandhaslastnameoption',
        7 => 'tests\\functional\\console\\testcreateusersuccessfully',
        8 => 'tests\\functional\\console\\testhandlerdirectly',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Functional/Console/LoadSeedFixturesCommandTest.php' => 
    array (
      0 => 'de484b0c75c077a46026e94faa46617e1b1f592b',
      1 => 
      array (
        0 => 'tests\\functional\\console\\loadseedfixturescommandtest',
      ),
      2 => 
      array (
        0 => 'tests\\functional\\console\\testcommandreceivestaggedfixturesetsfromcontainer',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Helper/TestEventIdProvider.php' => 
    array (
      0 => 'a2b9abb95f3d8b8fbff9b10277bc6e81e6d4ae3f',
      1 => 
      array (
        0 => 'tests\\helper\\testeventidprovider',
      ),
      2 => 
      array (
        0 => 'tests\\helper\\nexteventid',
        1 => 'tests\\helper\\reset',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Integration/Auth/Infrastructure/RateLimiter/LoginRateLimiterAdapterTest.php' => 
    array (
      0 => '8690059619653d5a6abb700822d93d2e7293966b',
      1 => 
      array (
        0 => 'tests\\integration\\auth\\infrastructure\\ratelimiter\\loginratelimiteradaptertest',
      ),
      2 => 
      array (
        0 => 'tests\\integration\\auth\\infrastructure\\ratelimiter\\setup',
        1 => 'tests\\integration\\auth\\infrastructure\\ratelimiter\\teardown',
        2 => 'tests\\integration\\auth\\infrastructure\\ratelimiter\\testconsumereturnsacceptedonfirstattempt',
        3 => 'tests\\integration\\auth\\infrastructure\\ratelimiter\\testconsumedecreasesremainingtokens',
        4 => 'tests\\integration\\auth\\infrastructure\\ratelimiter\\testconsumereturnsrejectedwhenlimitexceeded',
        5 => 'tests\\integration\\auth\\infrastructure\\ratelimiter\\testconsumewithmultipletokens',
        6 => 'tests\\integration\\auth\\infrastructure\\ratelimiter\\testdifferentkeysareindependent',
        7 => 'tests\\integration\\auth\\infrastructure\\ratelimiter\\testresetrestorestokens',
        8 => 'tests\\integration\\auth\\infrastructure\\ratelimiter\\testresetafterlimitexceeded',
        9 => 'tests\\integration\\auth\\infrastructure\\ratelimiter\\getlimit',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Integration/Auth/OAuth2FlowTest.php' => 
    array (
      0 => '684bc34b6eed3e3139d2f828fdf333724c0bb9db',
      1 => 
      array (
        0 => 'tests\\integration\\auth\\oauth2flowtest',
      ),
      2 => 
      array (
        0 => 'tests\\integration\\auth\\setup',
        1 => 'tests\\integration\\auth\\testtokenendpointrequiresgranttype',
        2 => 'tests\\integration\\auth\\testtokenendpointrejectsinvalidgranttype',
        3 => 'tests\\integration\\auth\\testtokenendpointacceptsvalidgranttypes',
        4 => 'tests\\integration\\auth\\testintrospectionendpointrequirestoken',
        5 => 'tests\\integration\\auth\\testintrospectionendpointreturnsinactiveforinvalidtoken',
        6 => 'tests\\integration\\auth\\testrevocationendpointacceptstoken',
        7 => 'tests\\integration\\auth\\testrefreshtokengrantrequiresrefreshtoken',
        8 => 'tests\\integration\\auth\\testauthorizationcodegrantrequirescodeandredirecturi',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Integration/Authorization/Infrastructure/DataFixtures/AuthorizationFixturesIntegrationTest.php' => 
    array (
      0 => '22d271623d2700aee880b2df728bf3a3ab3aec42',
      1 => 
      array (
        0 => 'tests\\integration\\authorization\\infrastructure\\datafixtures\\authorizationfixturesintegrationtest',
      ),
      2 => 
      array (
        0 => 'tests\\integration\\authorization\\infrastructure\\datafixtures\\setup',
        1 => 'tests\\integration\\authorization\\infrastructure\\datafixtures\\teardown',
        2 => 'tests\\integration\\authorization\\infrastructure\\datafixtures\\testloadcreatesdefaultrolesandpermissions',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Integration/Authorization/Infrastructure/Persistence/Doctrine/Repository/RoleAssignmentRepositoryIntegrationTest.php' => 
    array (
      0 => '84ddb4d15f261dab6af76ea44c4fdc8924fc7721',
      1 => 
      array (
        0 => 'tests\\integration\\authorization\\infrastructure\\persistence\\doctrine\\repository\\roleassignmentrepositoryintegrationtest',
      ),
      2 => 
      array (
        0 => 'tests\\integration\\authorization\\infrastructure\\persistence\\doctrine\\repository\\setup',
        1 => 'tests\\integration\\authorization\\infrastructure\\persistence\\doctrine\\repository\\teardown',
        2 => 'tests\\integration\\authorization\\infrastructure\\persistence\\doctrine\\repository\\testfindbysubjectexcludesexpiredassignments',
        3 => 'tests\\integration\\authorization\\infrastructure\\persistence\\doctrine\\repository\\testfindrolesforsubjectreturnsroleswithpermissions',
        4 => 'tests\\integration\\authorization\\infrastructure\\persistence\\doctrine\\repository\\testfindbyrolereturnsassignments',
        5 => 'tests\\integration\\authorization\\infrastructure\\persistence\\doctrine\\repository\\testdeletebysubjectremovesassignments',
        6 => 'tests\\integration\\authorization\\infrastructure\\persistence\\doctrine\\repository\\testsaveanddeletebyid',
        7 => 'tests\\integration\\authorization\\infrastructure\\persistence\\doctrine\\repository\\createpermissionrecord',
        8 => 'tests\\integration\\authorization\\infrastructure\\persistence\\doctrine\\repository\\createrolerecord',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Integration/Authorization/Infrastructure/Persistence/Doctrine/Repository/RoleRepositoryIntegrationTest.php' => 
    array (
      0 => '9cc162c14faccf1d0681b7c8eb1d19a9970186cd',
      1 => 
      array (
        0 => 'tests\\integration\\authorization\\infrastructure\\persistence\\doctrine\\repository\\rolerepositoryintegrationtest',
      ),
      2 => 
      array (
        0 => 'tests\\integration\\authorization\\infrastructure\\persistence\\doctrine\\repository\\setup',
        1 => 'tests\\integration\\authorization\\infrastructure\\persistence\\doctrine\\repository\\teardown',
        2 => 'tests\\integration\\authorization\\infrastructure\\persistence\\doctrine\\repository\\testfindbyidandnamereturnnullwhenmissing',
        3 => 'tests\\integration\\authorization\\infrastructure\\persistence\\doctrine\\repository\\testsavefindallanddeleterole',
        4 => 'tests\\integration\\authorization\\infrastructure\\persistence\\doctrine\\repository\\createpermissionrecord',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Integration/Client/Infrastructure/Repository/ClientRepositoryIntegrationTest.php' => 
    array (
      0 => 'f2fc3861809c2330c585c21cf89df1a782147fac',
      1 => 
      array (
        0 => 'tests\\integration\\client\\infrastructure\\repository\\clientrepositoryintegrationtest',
      ),
      2 => 
      array (
        0 => 'tests\\integration\\client\\infrastructure\\repository\\setup',
        1 => 'tests\\integration\\client\\infrastructure\\repository\\teardown',
        2 => 'tests\\integration\\client\\infrastructure\\repository\\testsaveandfindbyid',
        3 => 'tests\\integration\\client\\infrastructure\\repository\\testfindbyidreturnsnullwhennotfound',
        4 => 'tests\\integration\\client\\infrastructure\\repository\\testsaveupdatesexistingclient',
        5 => 'tests\\integration\\client\\infrastructure\\repository\\testexistsbyname',
        6 => 'tests\\integration\\client\\infrastructure\\repository\\testdelete',
        7 => 'tests\\integration\\client\\infrastructure\\repository\\testfindallwithpagination',
        8 => 'tests\\integration\\client\\infrastructure\\repository\\testcount',
        9 => 'tests\\integration\\client\\infrastructure\\repository\\testclientwithmultipleredirecturis',
        10 => 'tests\\integration\\client\\infrastructure\\repository\\createtestclient',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Integration/Equipment/Infrastructure/DataFixtures/EquipmentFixturesIntegrationTest.php' => 
    array (
      0 => 'eccda613b99d09a8bc373f72b714d24fa312953e',
      1 => 
      array (
        0 => 'tests\\integration\\equipment\\infrastructure\\datafixtures\\equipmentfixturesintegrationtest',
      ),
      2 => 
      array (
        0 => 'tests\\integration\\equipment\\infrastructure\\datafixtures\\setup',
        1 => 'tests\\integration\\equipment\\infrastructure\\datafixtures\\teardown',
        2 => 'tests\\integration\\equipment\\infrastructure\\datafixtures\\testloadpersistsequipmenttagsandoperationalartifacts',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Integration/Facility/Infrastructure/DataFixtures/FacilityFixturesIntegrationTest.php' => 
    array (
      0 => '3642f8cd027cce5a03d2842941703419515f8ba2',
      1 => 
      array (
        0 => 'tests\\integration\\facility\\infrastructure\\datafixtures\\facilityfixturesintegrationtest',
      ),
      2 => 
      array (
        0 => 'tests\\integration\\facility\\infrastructure\\datafixtures\\setup',
        1 => 'tests\\integration\\facility\\infrastructure\\datafixtures\\teardown',
        2 => 'tests\\integration\\facility\\infrastructure\\datafixtures\\testloadpersistshierarchywithpublishedreferences',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Integration/Facility/Infrastructure/Persistence/Doctrine/Repository/FacilityRepositoryTest.php' => 
    array (
      0 => 'ff2a9e8e40d77fd884d5fbc01b896112ad4b62ed',
      1 => 
      array (
        0 => 'tests\\integration\\facility\\infrastructure\\persistence\\doctrine\\repository\\facilityrepositorytest',
      ),
      2 => 
      array (
        0 => 'tests\\integration\\facility\\infrastructure\\persistence\\doctrine\\repository\\setup',
        1 => 'tests\\integration\\facility\\infrastructure\\persistence\\doctrine\\repository\\teardown',
        2 => 'tests\\integration\\facility\\infrastructure\\persistence\\doctrine\\repository\\testfindbyorganizationidcanreturnonlyvisibleroots',
        3 => 'tests\\integration\\facility\\infrastructure\\persistence\\doctrine\\repository\\testfindchildrenpaginatesandcountsmatchingchildren',
        4 => 'tests\\integration\\facility\\infrastructure\\persistence\\doctrine\\repository\\testcountchildrenbyparentidsgroupsvisiblechildren',
        5 => 'tests\\integration\\facility\\infrastructure\\persistence\\doctrine\\repository\\createorganization',
        6 => 'tests\\integration\\facility\\infrastructure\\persistence\\doctrine\\repository\\createfacility',
        7 => 'tests\\integration\\facility\\infrastructure\\persistence\\doctrine\\repository\\removeorganization',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Integration/Inspection/Infrastructure/DataFixtures/InspectionFixturesIntegrationTest.php' => 
    array (
      0 => '476db3dd7c01bb5a977cb16cd9f6643cc861fe70',
      1 => 
      array (
        0 => 'tests\\integration\\inspection\\infrastructure\\datafixtures\\inspectionfixturesintegrationtest',
      ),
      2 => 
      array (
        0 => 'tests\\integration\\inspection\\infrastructure\\datafixtures\\setup',
        1 => 'tests\\integration\\inspection\\infrastructure\\datafixtures\\teardown',
        2 => 'tests\\integration\\inspection\\infrastructure\\datafixtures\\testloadpersistsinspectionchecklistandnonconformities',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Integration/Notification/Infrastructure/Persistence/Doctrine/Repository/NotificationRepositoryIntegrationTest.php' => 
    array (
      0 => 'b948185ae4d5e4a720748ca581d8727e00ae2dd5',
      1 => 
      array (
        0 => 'tests\\integration\\notification\\infrastructure\\persistence\\doctrine\\repository\\notificationrepositoryintegrationtest',
      ),
      2 => 
      array (
        0 => 'tests\\integration\\notification\\infrastructure\\persistence\\doctrine\\repository\\setup',
        1 => 'tests\\integration\\notification\\infrastructure\\persistence\\doctrine\\repository\\teardown',
        2 => 'tests\\integration\\notification\\infrastructure\\persistence\\doctrine\\repository\\testfindbyuseridmasksoldreadnotificationsforconfiguredcategories',
        3 => 'tests\\integration\\notification\\infrastructure\\persistence\\doctrine\\repository\\createnotification',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Integration/OAuth/Infrastructure/DataFixtures/ClientFixturesIntegrationTest.php' => 
    array (
      0 => 'fb5b6ab726b47115f82a3dc4c45e06a1b7328097',
      1 => 
      array (
        0 => 'tests\\integration\\oauth\\infrastructure\\datafixtures\\clientfixturesintegrationtest',
      ),
      2 => 
      array (
        0 => 'tests\\integration\\oauth\\infrastructure\\datafixtures\\setup',
        1 => 'tests\\integration\\oauth\\infrastructure\\datafixtures\\teardown',
        2 => 'tests\\integration\\oauth\\infrastructure\\datafixtures\\testloadpersistsclientsandreferences',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Integration/OAuth/Infrastructure/Persistence/Doctrine/Repository/ConsentRepositoryIntegrationTest.php' => 
    array (
      0 => '8b4d67d4f2f647dda1871caea9c783df5e39c70c',
      1 => 
      array (
        0 => 'tests\\integration\\oauth\\infrastructure\\persistence\\doctrine\\repository\\consentrepositoryintegrationtest',
      ),
      2 => 
      array (
        0 => 'tests\\integration\\oauth\\infrastructure\\persistence\\doctrine\\repository\\setup',
        1 => 'tests\\integration\\oauth\\infrastructure\\persistence\\doctrine\\repository\\teardown',
        2 => 'tests\\integration\\oauth\\infrastructure\\persistence\\doctrine\\repository\\testsaveandfindbyid',
        3 => 'tests\\integration\\oauth\\infrastructure\\persistence\\doctrine\\repository\\testfindbyuserandclientreturnsactiveconsent',
        4 => 'tests\\integration\\oauth\\infrastructure\\persistence\\doctrine\\repository\\testsaveupdatesscopes',
        5 => 'tests\\integration\\oauth\\infrastructure\\persistence\\doctrine\\repository\\testfindallbyuserreturnsallconsents',
        6 => 'tests\\integration\\oauth\\infrastructure\\persistence\\doctrine\\repository\\testrevokeallforusermarksconsentsrevoked',
        7 => 'tests\\integration\\oauth\\infrastructure\\persistence\\doctrine\\repository\\createconsent',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Integration/Onboarding/Infrastructure/DataFixtures/OnboardingFixturesIntegrationTest.php' => 
    array (
      0 => 'd228dbed9927f3057e54c3761cccb7f6ff71d4d6',
      1 => 
      array (
        0 => 'tests\\integration\\onboarding\\infrastructure\\datafixtures\\onboardingfixturesintegrationtest',
      ),
      2 => 
      array (
        0 => 'tests\\integration\\onboarding\\infrastructure\\datafixtures\\setup',
        1 => 'tests\\integration\\onboarding\\infrastructure\\datafixtures\\teardown',
        2 => 'tests\\integration\\onboarding\\infrastructure\\datafixtures\\testloadpersistscompletedadminonboardingsession',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Integration/Organization/Infrastructure/DataFixtures/OrganizationFixturesIntegrationTest.php' => 
    array (
      0 => '7fd150974403dd542f3b3f5a1c8cd60dd1e9323a',
      1 => 
      array (
        0 => 'tests\\integration\\organization\\infrastructure\\datafixtures\\organizationfixturesintegrationtest',
      ),
      2 => 
      array (
        0 => 'tests\\integration\\organization\\infrastructure\\datafixtures\\setup',
        1 => 'tests\\integration\\organization\\infrastructure\\datafixtures\\teardown',
        2 => 'tests\\integration\\organization\\infrastructure\\datafixtures\\testloadpersistsorganizationgraphandreferences',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Integration/Organization/Infrastructure/Persistence/Doctrine/Repository/OrganizationMemberRepositoryIntegrationTest.php' => 
    array (
      0 => '35003aac592d6a2d5966ee3eb271b2b97a7574c7',
      1 => 
      array (
        0 => 'tests\\integration\\organization\\infrastructure\\persistence\\doctrine\\repository\\organizationmemberrepositoryintegrationtest',
      ),
      2 => 
      array (
        0 => 'tests\\integration\\organization\\infrastructure\\persistence\\doctrine\\repository\\setup',
        1 => 'tests\\integration\\organization\\infrastructure\\persistence\\doctrine\\repository\\teardown',
        2 => 'tests\\integration\\organization\\infrastructure\\persistence\\doctrine\\repository\\testcountjoinedbetweencountsutcboundariesandscopesbyorganization',
        3 => 'tests\\integration\\organization\\infrastructure\\persistence\\doctrine\\repository\\createorganization',
        4 => 'tests\\integration\\organization\\infrastructure\\persistence\\doctrine\\repository\\createmember',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Integration/Otp/Infrastructure/Persistence/Doctrine/Repository/OtpRepositoryIntegrationTest.php' => 
    array (
      0 => '328cfec4e94827fbe04de0d500971b53ae9b833f',
      1 => 
      array (
        0 => 'tests\\integration\\otp\\infrastructure\\persistence\\doctrine\\repository\\otprepositoryintegrationtest',
      ),
      2 => 
      array (
        0 => 'tests\\integration\\otp\\infrastructure\\persistence\\doctrine\\repository\\setup',
        1 => 'tests\\integration\\otp\\infrastructure\\persistence\\doctrine\\repository\\teardown',
        2 => 'tests\\integration\\otp\\infrastructure\\persistence\\doctrine\\repository\\testsaveandfindbyidandchallengetoken',
        3 => 'tests\\integration\\otp\\infrastructure\\persistence\\doctrine\\repository\\testfindactivebyuserandpurposereturnsactiveotp',
        4 => 'tests\\integration\\otp\\infrastructure\\persistence\\doctrine\\repository\\testrevokeallforuserexpiresactiveotps',
        5 => 'tests\\integration\\otp\\infrastructure\\persistence\\doctrine\\repository\\testfindbychallengetokenreturnsnullwhenmissing',
        6 => 'tests\\integration\\otp\\infrastructure\\persistence\\doctrine\\repository\\createotp',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Integration/Session/Infrastructure/Persistence/Doctrine/Repository/SessionRepositoryIntegrationTest.php' => 
    array (
      0 => '6c0531f73cc23fadd849209a2879dc7257f9bae5',
      1 => 
      array (
        0 => 'tests\\integration\\session\\infrastructure\\persistence\\doctrine\\repository\\sessionrepositoryintegrationtest',
      ),
      2 => 
      array (
        0 => 'tests\\integration\\session\\infrastructure\\persistence\\doctrine\\repository\\setup',
        1 => 'tests\\integration\\session\\infrastructure\\persistence\\doctrine\\repository\\teardown',
        2 => 'tests\\integration\\session\\infrastructure\\persistence\\doctrine\\repository\\testsaveandfindbyid',
        3 => 'tests\\integration\\session\\infrastructure\\persistence\\doctrine\\repository\\testsaveupdatesexistingsession',
        4 => 'tests\\integration\\session\\infrastructure\\persistence\\doctrine\\repository\\testfindbyuseridandactivebyuserid',
        5 => 'tests\\integration\\session\\infrastructure\\persistence\\doctrine\\repository\\testfindbyaccesstokenidandrefreshtokenid',
        6 => 'tests\\integration\\session\\infrastructure\\persistence\\doctrine\\repository\\testrevokeallforuserrevokessessions',
        7 => 'tests\\integration\\session\\infrastructure\\persistence\\doctrine\\repository\\testdeleteremovessession',
        8 => 'tests\\integration\\session\\infrastructure\\persistence\\doctrine\\repository\\createsession',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Integration/Tenant/Infrastructure/Persistence/Doctrine/Repository/TenantRepositoryIntegrationTest.php' => 
    array (
      0 => '2dc9a485db38760818a6d5f331a42c324271e4b6',
      1 => 
      array (
        0 => 'tests\\integration\\tenant\\infrastructure\\persistence\\doctrine\\repository\\tenantrepositoryintegrationtest',
      ),
      2 => 
      array (
        0 => 'tests\\integration\\tenant\\infrastructure\\persistence\\doctrine\\repository\\setup',
        1 => 'tests\\integration\\tenant\\infrastructure\\persistence\\doctrine\\repository\\teardown',
        2 => 'tests\\integration\\tenant\\infrastructure\\persistence\\doctrine\\repository\\testsaveandfindbyid',
        3 => 'tests\\integration\\tenant\\infrastructure\\persistence\\doctrine\\repository\\testsaveupdatesexistingtenant',
        4 => 'tests\\integration\\tenant\\infrastructure\\persistence\\doctrine\\repository\\testfindallreturnsactivetenants',
        5 => 'tests\\integration\\tenant\\infrastructure\\persistence\\doctrine\\repository\\testdeleteremovestenant',
        6 => 'tests\\integration\\tenant\\infrastructure\\persistence\\doctrine\\repository\\createtenant',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Integration/TrustedDevice/Infrastructure/Persistence/Doctrine/Repository/TrustedDeviceRepositoryIntegrationTest.php' => 
    array (
      0 => '22389a47a01edfffd35f9d39731857099366d575',
      1 => 
      array (
        0 => 'tests\\integration\\trusteddevice\\infrastructure\\persistence\\doctrine\\repository\\trusteddevicerepositoryintegrationtest',
      ),
      2 => 
      array (
        0 => 'tests\\integration\\trusteddevice\\infrastructure\\persistence\\doctrine\\repository\\setup',
        1 => 'tests\\integration\\trusteddevice\\infrastructure\\persistence\\doctrine\\repository\\teardown',
        2 => 'tests\\integration\\trusteddevice\\infrastructure\\persistence\\doctrine\\repository\\testsaveandfindbyid',
        3 => 'tests\\integration\\trusteddevice\\infrastructure\\persistence\\doctrine\\repository\\testfindbyuseridandfingerprintreturnsdevice',
        4 => 'tests\\integration\\trusteddevice\\infrastructure\\persistence\\doctrine\\repository\\testfindbytokenignoresexpireddevices',
        5 => 'tests\\integration\\trusteddevice\\infrastructure\\persistence\\doctrine\\repository\\testfindallbyuseridreturnsonlyactivedevices',
        6 => 'tests\\integration\\trusteddevice\\infrastructure\\persistence\\doctrine\\repository\\testrevokeallforusermarksdevicesrevoked',
        7 => 'tests\\integration\\trusteddevice\\infrastructure\\persistence\\doctrine\\repository\\testdeleteremovesdevice',
        8 => 'tests\\integration\\trusteddevice\\infrastructure\\persistence\\doctrine\\repository\\createtrusteddevice',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Integration/User/Infrastructure/DataFixtures/UserFixturesIntegrationTest.php' => 
    array (
      0 => '3523cc58fd5a8b9deae7ca89815bd19e25f6112a',
      1 => 
      array (
        0 => 'tests\\integration\\user\\infrastructure\\datafixtures\\userfixturesintegrationtest',
      ),
      2 => 
      array (
        0 => 'tests\\integration\\user\\infrastructure\\datafixtures\\setup',
        1 => 'tests\\integration\\user\\infrastructure\\datafixtures\\teardown',
        2 => 'tests\\integration\\user\\infrastructure\\datafixtures\\testloadpersistsusersandreferences',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Integration/User/Infrastructure/Repository/UserRepositoryIntegrationTest.php' => 
    array (
      0 => '05701cfaee269a6f63b3edd7ee71cea3a519072e',
      1 => 
      array (
        0 => 'tests\\integration\\user\\infrastructure\\repository\\userrepositoryintegrationtest',
      ),
      2 => 
      array (
        0 => 'tests\\integration\\user\\infrastructure\\repository\\setup',
        1 => 'tests\\integration\\user\\infrastructure\\repository\\teardown',
        2 => 'tests\\integration\\user\\infrastructure\\repository\\testsaveandfindbyid',
        3 => 'tests\\integration\\user\\infrastructure\\repository\\testfindbyidreturnsnullwhennotfound',
        4 => 'tests\\integration\\user\\infrastructure\\repository\\testfindbyusername',
        5 => 'tests\\integration\\user\\infrastructure\\repository\\testfindbyusernamereturnsnullwhennotfound',
        6 => 'tests\\integration\\user\\infrastructure\\repository\\testfindbyemail',
        7 => 'tests\\integration\\user\\infrastructure\\repository\\testfindbyemailreturnsnullwhennotfound',
        8 => 'tests\\integration\\user\\infrastructure\\repository\\testexistsbyusername',
        9 => 'tests\\integration\\user\\infrastructure\\repository\\testexistsbyemail',
        10 => 'tests\\integration\\user\\infrastructure\\repository\\testsaveupdatesexistinguser',
        11 => 'tests\\integration\\user\\infrastructure\\repository\\testdelete',
        12 => 'tests\\integration\\user\\infrastructure\\repository\\testfindall',
        13 => 'tests\\integration\\user\\infrastructure\\repository\\testuserwithprofile',
        14 => 'tests\\integration\\user\\infrastructure\\repository\\createtestuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Support/Factory/UserTestFactory.php' => 
    array (
      0 => '49c757ecf6ba405bc07c894926887c4dc72f3769',
      1 => 
      array (
        0 => 'tests\\support\\factory\\usertestfactory',
      ),
      2 => 
      array (
        0 => 'tests\\support\\factory\\createpending',
        1 => 'tests\\support\\factory\\createactive',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/App/KernelTest.php' => 
    array (
      0 => '165f91fb97165ea2f72a892b3ed0b89438c58859',
      1 => 
      array (
        0 => 'tests\\unit\\app\\kerneltest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\app\\testgetcacheandlogdiruseoverrides',
        1 => 'tests\\unit\\app\\testgetcacheandlogdirusedefaults',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Audit/Application/Contract/AuditEventContractTest.php' => 
    array (
      0 => '9eb8fc90bf12568c1409df4b0c4f465b44974e42',
      1 => 
      array (
        0 => 'tests\\unit\\audit\\application\\contract\\auditeventcontracttest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\audit\\application\\contract\\testsearchcriteriastoresfilters',
        1 => 'tests\\unit\\audit\\application\\contract\\testauditeventviewstorespayload',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Audit/Application/UseCase/Command/RecordAuditEvent/RecordAuditEventHandlerTest.php' => 
    array (
      0 => '1f08b59e844180e9061d94d5a7c8b7b2e5a33e8f',
      1 => 
      array (
        0 => 'tests\\unit\\audit\\application\\usecase\\command\\recordauditevent\\recordauditeventhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\audit\\application\\usecase\\command\\recordauditevent\\testinvokepersistseventandreturnsresult',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Audit/Application/UseCase/Command/RecordAuditEvent/RecordAuditEventResultTest.php' => 
    array (
      0 => '0c5432a3f25eac0b4b0701bd6d99ea93542610c6',
      1 => 
      array (
        0 => 'tests\\unit\\audit\\application\\usecase\\command\\recordauditevent\\recordauditeventresulttest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\audit\\application\\usecase\\command\\recordauditevent\\testresultcontainseventid',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Audit/Application/UseCase/Query/AuditEventQueryTest.php' => 
    array (
      0 => 'f587a8a301db63da464b2f9fb2917e366a62a302',
      1 => 
      array (
        0 => 'tests\\unit\\audit\\application\\usecase\\query\\auditeventquerytest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\audit\\application\\usecase\\query\\testgetauditeventquerystoresid',
        1 => 'tests\\unit\\audit\\application\\usecase\\query\\testlistauditeventsquerystorescriteriaandpagination',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Audit/Application/UseCase/Query/GetAuditEvent/GetAuditEventHandlerTest.php' => 
    array (
      0 => '655f13f7ff219cc3fd57fce90fc6da13e306a34f',
      1 => 
      array (
        0 => 'tests\\unit\\audit\\application\\usecase\\query\\getauditevent\\getauditeventhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\audit\\application\\usecase\\query\\getauditevent\\testinvokereturnsauditeventview',
        1 => 'tests\\unit\\audit\\application\\usecase\\query\\getauditevent\\testinvokethrowswhennotfound',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Audit/Application/UseCase/Query/ListAuditEvents/ListAuditEventsHandlerTest.php' => 
    array (
      0 => '82f7f4cf8c8bb9c3d3b4c29ce587e2c4c3752104',
      1 => 
      array (
        0 => 'tests\\unit\\audit\\application\\usecase\\query\\listauditevents\\listauditeventshandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\audit\\application\\usecase\\query\\listauditevents\\testinvokereturnspaginatedresults',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Audit/Domain/Model/AuditEventTest.php' => 
    array (
      0 => '80f4dde838ebb3cbae0e2ecf807d132104db7ffe',
      1 => 
      array (
        0 => 'tests\\unit\\audit\\domain\\model\\auditeventtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\audit\\domain\\model\\testauditeventstoresproperties',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Audit/Infrastructure/EventSubscriber/AuditEventSubscriberTest.php' => 
    array (
      0 => '59f60bbd11fefd593ca64ca83aebbeb5c66de47a',
      1 => 
      array (
        0 => 'tests\\unit\\audit\\infrastructure\\eventsubscriber\\auditeventsubscribertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\audit\\infrastructure\\eventsubscriber\\testgetsubscribedevents',
        1 => 'tests\\unit\\audit\\infrastructure\\eventsubscriber\\testonuserloggedindispatchesauditcommand',
        2 => 'tests\\unit\\audit\\infrastructure\\eventsubscriber\\testdispatchauditeventlogswhendispatchfails',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Audit/Infrastructure/Service/AuditHashServiceTest.php' => 
    array (
      0 => 'f2d4ddc1caed1746967be29eb414978fc232bddd',
      1 => 
      array (
        0 => 'tests\\unit\\audit\\infrastructure\\service\\audithashservicetest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\audit\\infrastructure\\service\\testcomputeisdeterministicacrosskeyorder',
        1 => 'tests\\unit\\audit\\infrastructure\\service\\testcomputechangeswhenprevioushashdiffers',
        2 => 'tests\\unit\\audit\\infrastructure\\service\\testcomputepreserveslistorder',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Audit/Infrastructure/Service/AuditPiiSanitizerTest.php' => 
    array (
      0 => 'ac5762474cf609b142ceddd1f59b61372844c601',
      1 => 
      array (
        0 => 'tests\\unit\\audit\\infrastructure\\service\\auditpiisanitizertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\audit\\infrastructure\\service\\testhasheswhenpiiisexcluded',
        1 => 'tests\\unit\\audit\\infrastructure\\service\\testreturnspiiwhenenabledandusessaltedhash',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Audit/Presentation/Api/Provider/AuditEvent/GetAuditEventProviderTest.php' => 
    array (
      0 => '1b14353792d303fca107c61720506f3424e96e3a',
      1 => 
      array (
        0 => 'tests\\unit\\audit\\presentation\\api\\provider\\auditevent\\getauditeventprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\audit\\presentation\\api\\provider\\auditevent\\testprovidemapsauditeventview',
        1 => 'tests\\unit\\audit\\presentation\\api\\provider\\auditevent\\testprovideusesemptyidwhennonstring',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Audit/Presentation/Api/Provider/AuditEvent/ListAuditEventsProviderTest.php' => 
    array (
      0 => '5b2064544d0f4b7cbdb6a74d64c40df4fe41e195',
      1 => 
      array (
        0 => 'tests\\unit\\audit\\presentation\\api\\provider\\auditevent\\listauditeventsprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\audit\\presentation\\api\\provider\\auditevent\\testprovidereturnspaginatedoutputs',
        1 => 'tests\\unit\\audit\\presentation\\api\\provider\\auditevent\\testprovideparsesnonatomdates',
        2 => 'tests\\unit\\audit\\presentation\\api\\provider\\auditevent\\testprovidepassessearchandsortingtocriteria',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Audit/Presentation/Api/Resource/AuditEventResourceTest.php' => 
    array (
      0 => 'a3caa36a7a05b9dc8b9fe3aae2766478ae1f8215',
      1 => 
      array (
        0 => 'tests\\unit\\audit\\presentation\\api\\resource\\auditeventresourcetest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\audit\\presentation\\api\\resource\\testresourcecanbeinstantiated',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Application/Contract/User/UserAuthenticationResultTest.php' => 
    array (
      0 => '4ebefadb70395ad079e39ed05af75d5e2df3e8b8',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\application\\contract\\user\\userauthenticationresulttest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\application\\contract\\user\\testsuccessfactory',
        1 => 'tests\\unit\\auth\\application\\contract\\user\\testfailedfactory',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Application/Service/JwtTokenServiceTest.php' => 
    array (
      0 => 'eb08e60e1d56afa53ce006afba56f04fb8e8f7a9',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\application\\service\\jwttokenservicetest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\application\\service\\setup',
        1 => 'tests\\unit\\auth\\application\\service\\teardown',
        2 => 'tests\\unit\\auth\\application\\service\\testgeneratetokensreturnsvalidstructure',
        3 => 'tests\\unit\\auth\\application\\service\\testgeneratetokenswithemptyscopes',
        4 => 'tests\\unit\\auth\\application\\service\\testdecoderefreshtokenreturnsvalidpayload',
        5 => 'tests\\unit\\auth\\application\\service\\testdecoderefreshtokenreturnsnullforinvalidtoken',
        6 => 'tests\\unit\\auth\\application\\service\\testdecoderefreshtokenreturnsnullforemptytoken',
        7 => 'tests\\unit\\auth\\application\\service\\testgetaccesstokenttl',
        8 => 'tests\\unit\\auth\\application\\service\\testgetrefreshtokenttl',
        9 => 'tests\\unit\\auth\\application\\service\\testgeneratedtokensareunique',
        10 => 'tests\\unit\\auth\\application\\service\\testdecoderefreshtokenreturnsnullforexpiredtoken',
        11 => 'tests\\unit\\auth\\application\\service\\testdecoderefreshtokenreturnsnullformalformedjson',
        12 => 'tests\\unit\\auth\\application\\service\\testaccesstokencontainsexpectedclaims',
        13 => 'tests\\unit\\auth\\application\\service\\testrefreshtokenpayloadcontainsallrequiredfields',
        14 => 'tests\\unit\\auth\\application\\service\\testcustomttlvalues',
        15 => 'tests\\unit\\auth\\application\\service\\testaccesstokenexpirationmatchesttl',
        16 => 'tests\\unit\\auth\\application\\service\\testrefreshtokenexpirationmatchesttl',
        17 => 'tests\\unit\\auth\\application\\service\\testrefreshtokenusesshortttlwhenremembermefalse',
        18 => 'tests\\unit\\auth\\application\\service\\testissuerclaimmatchesconfiguration',
        19 => 'tests\\unit\\auth\\application\\service\\testsubjectclaimmatchesuserid',
        20 => 'tests\\unit\\auth\\application\\service\\testscopesarepreservedintoken',
        21 => 'tests\\unit\\auth\\application\\service\\testtokentypeisbearerinresponse',
        22 => 'tests\\unit\\auth\\application\\service\\testgeneratetokensincludesrolesandpermissionswhenauthorizationserviceprovided',
        23 => 'tests\\unit\\auth\\application\\service\\testaccesstokenincludesemailwhenenabled',
        24 => 'tests\\unit\\auth\\application\\service\\testgeneratepreauthtokenthrowsonemptyuserid',
        25 => 'tests\\unit\\auth\\application\\service\\testdecodepreauthtokenreturnsclaims',
        26 => 'tests\\unit\\auth\\application\\service\\testdecodepreauthtokenreturnsnullforemptytoken',
        27 => 'tests\\unit\\auth\\application\\service\\testdecodepreauthtokenreturnsnullforinvalidsignature',
        28 => 'tests\\unit\\auth\\application\\service\\testdecodepreauthtokenreturnsnullforexpiredtoken',
        29 => 'tests\\unit\\auth\\application\\service\\testdecodepreauthtokenreturnsnullforinvalidtoken',
        30 => 'tests\\unit\\auth\\application\\service\\testdecoderefreshtokenreturnsnullfornonarraypayload',
        31 => 'tests\\unit\\auth\\application\\service\\testdecoderefreshtokenreturnsnullformissingfields',
        32 => 'tests\\unit\\auth\\application\\service\\testdecoderefreshtokenreturnsnullforinvalidfieldtypes',
        33 => 'tests\\unit\\auth\\application\\service\\testdecoderefreshtokencoercesremembermestring',
        34 => 'tests\\unit\\auth\\application\\service\\testdecoderefreshtokendropsinvalidrememberme',
        35 => 'tests\\unit\\auth\\application\\service\\createpreauthtoken',
        36 => 'tests\\unit\\auth\\application\\service\\encryptpayload',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Application/UseCase/Command/Mfa/MfaChallenge/MfaChallengeHandlerTest.php' => 
    array (
      0 => '2b781e267023bfd0755c49d971b4c5637b4ca972',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\application\\usecase\\command\\mfa\\mfachallenge\\mfachallengehandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\application\\usecase\\command\\mfa\\mfachallenge\\testinvokereturnsgeneratedchallenge',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Application/UseCase/Command/Mfa/MfaVerify/MfaVerifyHandlerTest.php' => 
    array (
      0 => '0b4b06bef4f3f9c5eca24bccbf383b23f101654b',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\application\\usecase\\command\\mfa\\mfaverify\\mfaverifyhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\application\\usecase\\command\\mfa\\mfaverify\\testinvokethrowswhenpreauthinvalid',
        1 => 'tests\\unit\\auth\\application\\usecase\\command\\mfa\\mfaverify\\testinvokethrowswhenpayloadinvalid',
        2 => 'tests\\unit\\auth\\application\\usecase\\command\\mfa\\mfaverify\\testinvokereturnsfailurewhenverificationfails',
        3 => 'tests\\unit\\auth\\application\\usecase\\command\\mfa\\mfaverify\\testinvokegeneratestokenswhenverificationsucceeds',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Application/UseCase/Command/PasswordChange/ConfirmPasswordChangeHandlerTest.php' => 
    array (
      0 => '930413328d359fabd30aeca89c6a1addd0ac78ef',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\application\\usecase\\command\\passwordchange\\confirmpasswordchangehandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\application\\usecase\\command\\passwordchange\\testfailswhentokenisunknown',
        1 => 'tests\\unit\\auth\\application\\usecase\\command\\passwordchange\\testfailswhenotpbelongstoanotheruser',
        2 => 'tests\\unit\\auth\\application\\usecase\\command\\passwordchange\\testfailswhenotphaswrongpurpose',
        3 => 'tests\\unit\\auth\\application\\usecase\\command\\passwordchange\\testfailswhencodeisinvalid',
        4 => 'tests\\unit\\auth\\application\\usecase\\command\\passwordchange\\testchangespasswordandrevokessessionsonsuccess',
        5 => 'tests\\unit\\auth\\application\\usecase\\command\\passwordchange\\makehandler',
        6 => 'tests\\unit\\auth\\application\\usecase\\command\\passwordchange\\makecommand',
        7 => 'tests\\unit\\auth\\application\\usecase\\command\\passwordchange\\makeotp',
        8 => 'tests\\unit\\auth\\application\\usecase\\command\\passwordchange\\makeuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Application/UseCase/Command/PasswordChange/RequestPasswordChangeHandlerTest.php' => 
    array (
      0 => '5074e9c0bfea6a7120eb74c3fc37395800f97013',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\application\\usecase\\command\\passwordchange\\requestpasswordchangehandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\application\\usecase\\command\\passwordchange\\testfailswhenusernotfound',
        1 => 'tests\\unit\\auth\\application\\usecase\\command\\passwordchange\\testfailswhencurrentpasswordisincorrect',
        2 => 'tests\\unit\\auth\\application\\usecase\\command\\passwordchange\\testgeneratesotpchallengewhenpasswordiscorrect',
        3 => 'tests\\unit\\auth\\application\\usecase\\command\\passwordchange\\makeactiveuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Application/UseCase/Command/Session/Login/LoginHandlerTest.php' => 
    array (
      0 => '235a8e060407ffdd56b21c38437be04bb2164bdf',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\application\\usecase\\command\\session\\login\\loginhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\application\\usecase\\command\\session\\login\\testinvokereturnsfailedwhenratelimited',
        1 => 'tests\\unit\\auth\\application\\usecase\\command\\session\\login\\testinvokereturnsfailedwhencredentialsinvalid',
        2 => 'tests\\unit\\auth\\application\\usecase\\command\\session\\login\\testinvokereturnsmfachallengewhenenabled',
        3 => 'tests\\unit\\auth\\application\\usecase\\command\\session\\login\\testinvokegeneratestokensonsuccess',
        4 => 'tests\\unit\\auth\\application\\usecase\\command\\session\\login\\testinvokereturnsfailedwhenauthenticationthrows',
        5 => 'tests\\unit\\auth\\application\\usecase\\command\\session\\login\\testinvokesucceedswhensessiontrackingfails',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Application/UseCase/Command/Session/Login/LoginResultTest.php' => 
    array (
      0 => '2f201466bfc6f3bcfdd6590d36fd6f7be5bee8cb',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\application\\usecase\\command\\session\\login\\loginresulttest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\application\\usecase\\command\\session\\login\\testfailedfactory',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Application/UseCase/Command/Session/Logout/LogoutHandlerTest.php' => 
    array (
      0 => '73582ef29701e88c4e2070711d4e2eef0ac58da9',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\application\\usecase\\command\\session\\logout\\logouthandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\application\\usecase\\command\\session\\logout\\testinvokerevokesprovidedtokens',
        1 => 'tests\\unit\\auth\\application\\usecase\\command\\session\\logout\\testinvokeskipsmissingtokens',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Application/UseCase/Query/Session/RefreshToken/RefreshTokenHandlerTest.php' => 
    array (
      0 => 'a78fd693c4ee14b0659247cf5a9ab2f636e8d4a4',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\application\\usecase\\query\\session\\refreshtoken\\refreshtokenhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\application\\usecase\\query\\session\\refreshtoken\\testinvokerotatessessiontokensonsuccess',
        1 => 'tests\\unit\\auth\\application\\usecase\\query\\session\\refreshtoken\\testinvokeskipsrotationwhencurrentmissing',
        2 => 'tests\\unit\\auth\\application\\usecase\\query\\session\\refreshtoken\\testinvokeskipsrotationwhennewpayloadinvalid',
        3 => 'tests\\unit\\auth\\application\\usecase\\query\\session\\refreshtoken\\testinvokeskipsrotationwhennewtokenidsmissing',
        4 => 'tests\\unit\\auth\\application\\usecase\\query\\session\\refreshtoken\\testinvokeignoressessiontrackingfailures',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Application/UseCase/Query/Session/RefreshToken/RefreshTokenResultTest.php' => 
    array (
      0 => '78c2c8e11e27bbf4f33e235072c27225bf929d04',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\application\\usecase\\query\\session\\refreshtoken\\refreshtokenresulttest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\application\\usecase\\query\\session\\refreshtoken\\testfailedfactory',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Domain/Event/Session/LoginFailedEventTest.php' => 
    array (
      0 => '1ba67b756327a776de33b30221eda48993ba8ace',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\domain\\event\\session\\loginfailedeventtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\domain\\event\\session\\testcanbecreated',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Domain/Event/Session/UserLoggedInEventTest.php' => 
    array (
      0 => 'f95dfd62fc9311b9abfb0dfdb319a897fb027fd1',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\domain\\event\\session\\userloggedineventtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\domain\\event\\session\\testcanbecreated',
        1 => 'tests\\unit\\auth\\domain\\event\\session\\testcanbecreatedwithnullipaddress',
        2 => 'tests\\unit\\auth\\domain\\event\\session\\testoccurredatissetautomatically',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Domain/Event/Session/UserLoggedOutEventTest.php' => 
    array (
      0 => '56742432f8d96dec3dbcb985bb2cd6e2cb3f2389',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\domain\\event\\session\\userloggedouteventtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\domain\\event\\session\\testcanbecreated',
        1 => 'tests\\unit\\auth\\domain\\event\\session\\testcanbecreatedwithnulluserid',
        2 => 'tests\\unit\\auth\\domain\\event\\session\\testoccurredatissetautomatically',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Domain/Event/Token/TokenIssuedEventTest.php' => 
    array (
      0 => '8a793d8a8ca33b2d418dccd640427ac0a872b40b',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\domain\\event\\token\\tokenissuedeventtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\domain\\event\\token\\testcanbecreated',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Domain/Event/Token/TokenRevokedEventTest.php' => 
    array (
      0 => '7f6d874d5d8710ffc2cbb6612060863d448e5cd5',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\domain\\event\\token\\tokenrevokedeventtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\domain\\event\\token\\testcanbecreated',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Domain/Exception/Client/InvalidClientIdentifierExceptionTest.php' => 
    array (
      0 => '183a03cddd5ef4f6ef9d2dc97832c8f39d4fe333',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\domain\\exception\\client\\invalidclientidentifierexceptiontest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\domain\\exception\\client\\testemptycreatesmessage',
        1 => 'tests\\unit\\auth\\domain\\exception\\client\\testinvalidpatterncreatesmessage',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Domain/Exception/Session/AuthorizationExceptionTest.php' => 
    array (
      0 => '278cec1d298d9c231b98a5500d06da80d98c5581',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\domain\\exception\\session\\authorizationexceptiontest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\domain\\exception\\session\\testinvalidclientmessage',
        1 => 'tests\\unit\\auth\\domain\\exception\\session\\testinvalidgrantmessage',
        2 => 'tests\\unit\\auth\\domain\\exception\\session\\testinvalidscopemessage',
        3 => 'tests\\unit\\auth\\domain\\exception\\session\\testservererrormessage',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Domain/Exception/Session/ValidationExceptionTest.php' => 
    array (
      0 => 'b8fcaee17fe851d96aa8aac8c368ae085baf5d5d',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\domain\\exception\\session\\validationexceptiontest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\domain\\exception\\session\\testinvalidgranttypemessage',
        1 => 'tests\\unit\\auth\\domain\\exception\\session\\testmissingfieldmessage',
        2 => 'tests\\unit\\auth\\domain\\exception\\session\\testinvalidfieldmessage',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Domain/Model/Client/ClientTest.php' => 
    array (
      0 => '6a82d3156e331cf5cc10faed2025c83a8f45eb4e',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\domain\\model\\client\\clienttest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\domain\\model\\client\\testcancreateclient',
        1 => 'tests\\unit\\auth\\domain\\model\\client\\testvalidateredirecturi',
        2 => 'tests\\unit\\auth\\domain\\model\\client\\testsupportsgranttype',
        3 => 'tests\\unit\\auth\\domain\\model\\client\\testhasscope',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Domain/Model/Session/TokenSessionTest.php' => 
    array (
      0 => '47926dd17f2f03df6f7e99f6bcab3d37e78314c6',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\domain\\model\\session\\tokensessiontest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\domain\\model\\session\\testcancreateusersession',
        1 => 'tests\\unit\\auth\\domain\\model\\session\\testcreateusersessionrecordsevents',
        2 => 'tests\\unit\\auth\\domain\\model\\session\\testcancreateclientsession',
        3 => 'tests\\unit\\auth\\domain\\model\\session\\testcreateclientsessionrecordstokenissuedevent',
        4 => 'tests\\unit\\auth\\domain\\model\\session\\testcanrevokeusersession',
        5 => 'tests\\unit\\auth\\domain\\model\\session\\testcanrevokeclientsession',
        6 => 'tests\\unit\\auth\\domain\\model\\session\\testrevokeisidempotent',
        7 => 'tests\\unit\\auth\\domain\\model\\session\\testreleaseeventsclearsevents',
        8 => 'tests\\unit\\auth\\domain\\model\\session\\testcreatedatisset',
        9 => 'tests\\unit\\auth\\domain\\model\\session\\testaccesstokenexpiryisset',
        10 => 'tests\\unit\\auth\\domain\\model\\session\\testrefreshtokenexpiryissetforusersession',
        11 => 'tests\\unit\\auth\\domain\\model\\session\\testrefreshtokenexpiryisnullforclientsession',
        12 => 'tests\\unit\\auth\\domain\\model\\session\\testisvalidreflectsrevocationandexpiry',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Domain/ValueObject/Client/ClientIdentifierTest.php' => 
    array (
      0 => '506673cca78c9f0206e955801a7e59fb13bd4337',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\domain\\valueobject\\client\\clientidentifiertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\domain\\valueobject\\client\\testvalididentifierisaccepted',
        1 => 'tests\\unit\\auth\\domain\\valueobject\\client\\testemptyidentifierthrows',
        2 => 'tests\\unit\\auth\\domain\\valueobject\\client\\testinvalidpatternthrows',
        3 => 'tests\\unit\\auth\\domain\\valueobject\\client\\testequalscomparesvalue',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Domain/ValueObject/Scope/DefaultScopesTest.php' => 
    array (
      0 => '7cdbd57f3c8910d610a7d7c4b53b37a02d76a22f',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\domain\\valueobject\\scope\\defaultscopestest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\domain\\valueobject\\scope\\testdefaultscopesaredefined',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Domain/ValueObject/Scope/ScopeTest.php' => 
    array (
      0 => '0be2dd72a821e6ecc13d314f2e8b989663fe64ae',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\domain\\valueobject\\scope\\scopetest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\domain\\valueobject\\scope\\testvaluesreturnsallcases',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Domain/ValueObject/Security/DPoPProofTest.php' => 
    array (
      0 => '100a221afb2cdba108207c2b4c6c3ff42c447978',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\domain\\valueobject\\security\\dpopprooftest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\domain\\valueobject\\security\\testfromjwtcreatesproof',
        1 => 'tests\\unit\\auth\\domain\\valueobject\\security\\testfromjwtrejectsmissingclaims',
        2 => 'tests\\unit\\auth\\domain\\valueobject\\security\\testfromjwtrejectsinvalidtypes',
        3 => 'tests\\unit\\auth\\domain\\valueobject\\security\\testisvalidforchecksmethoduriandage',
        4 => 'tests\\unit\\auth\\domain\\valueobject\\security\\testisvalidforrejectsexpiredproof',
        5 => 'tests\\unit\\auth\\domain\\valueobject\\security\\testisvalidforhandlesunparseableuri',
        6 => 'tests\\unit\\auth\\domain\\valueobject\\security\\testisvalidforkeepsnondefaultport',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Domain/ValueObject/Security/GrantTypeTest.php' => 
    array (
      0 => 'aa77034f2f0e5ff92236aa4aa126fba08d12454c',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\domain\\valueobject\\security\\granttypetest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\domain\\valueobject\\security\\testvaluesreturnsallcases',
        1 => 'tests\\unit\\auth\\domain\\valueobject\\security\\testhelpermethods',
        2 => 'tests\\unit\\auth\\domain\\valueobject\\security\\granttypeprovider',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Domain/ValueObject/Token/TokenExpiryTest.php' => 
    array (
      0 => 'e4ec9edce2fb63ff3347cbdf8da21fde595e56a1',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\domain\\valueobject\\token\\tokenexpirytest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\domain\\valueobject\\token\\testfromttlrejectsnonpositive',
        1 => 'tests\\unit\\auth\\domain\\valueobject\\token\\testfromttlcreatesfutureexpiry',
        2 => 'tests\\unit\\auth\\domain\\valueobject\\token\\testfromsecondsaliasesfromttl',
        3 => 'tests\\unit\\auth\\domain\\valueobject\\token\\testfromtimestampcreatesexpiry',
        4 => 'tests\\unit\\auth\\domain\\valueobject\\token\\testextendreturnsnewexpiry',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Domain/ValueObject/Token/TokenIdentifierTest.php' => 
    array (
      0 => '4a4c5cf3d525d4082a13124433d1c8780ed889e7',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\domain\\valueobject\\token\\tokenidentifiertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\domain\\valueobject\\token\\testconstructorrejectsemptyvalue',
        1 => 'tests\\unit\\auth\\domain\\valueobject\\token\\testgeneratecreatesidentifier',
        2 => 'tests\\unit\\auth\\domain\\valueobject\\token\\testequalscomparesvalue',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Infrastructure/Adapter/Jwt/JwtParserAdapterTest.php' => 
    array (
      0 => '86cd076abb51b85e2d45d70f0d3633d68f1fb298',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\infrastructure\\adapter\\jwt\\jwtparseradaptertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\infrastructure\\adapter\\jwt\\testparsereturnsclaims',
        1 => 'tests\\unit\\auth\\infrastructure\\adapter\\jwt\\testparsereturnsnullforemptytoken',
        2 => 'tests\\unit\\auth\\infrastructure\\adapter\\jwt\\testparsereturnsnullforinvalidtoken',
        3 => 'tests\\unit\\auth\\infrastructure\\adapter\\jwt\\testparsereturnsnullfornonunencryptedtoken',
        4 => 'tests\\unit\\auth\\infrastructure\\adapter\\jwt\\testvalidatecheckstoken',
        5 => 'tests\\unit\\auth\\infrastructure\\adapter\\jwt\\testvalidatereturnsfalsewhenclaimsmissing',
        6 => 'tests\\unit\\auth\\infrastructure\\adapter\\jwt\\testvalidatereturnsfalsewhenparserthrows',
        7 => 'tests\\unit\\auth\\infrastructure\\adapter\\jwt\\testvalidatereturnsfalsefornonunencryptedtoken',
        8 => 'tests\\unit\\auth\\infrastructure\\adapter\\jwt\\testgettokenidanduserid',
        9 => 'tests\\unit\\auth\\infrastructure\\adapter\\jwt\\testgettokenidanduseridreturnnullonparsefailure',
        10 => 'tests\\unit\\auth\\infrastructure\\adapter\\jwt\\createtoken',
        11 => 'tests\\unit\\auth\\infrastructure\\adapter\\jwt\\replaceparser',
        12 => 'tests\\unit\\auth\\infrastructure\\adapter\\jwt\\createnonunencryptedparser',
        13 => 'tests\\unit\\auth\\infrastructure\\adapter\\jwt\\parse',
        14 => 'tests\\unit\\auth\\infrastructure\\adapter\\jwt\\headers',
        15 => 'tests\\unit\\auth\\infrastructure\\adapter\\jwt\\ispermittedfor',
        16 => 'tests\\unit\\auth\\infrastructure\\adapter\\jwt\\isidentifiedby',
        17 => 'tests\\unit\\auth\\infrastructure\\adapter\\jwt\\isrelatedto',
        18 => 'tests\\unit\\auth\\infrastructure\\adapter\\jwt\\hasbeenissuedby',
        19 => 'tests\\unit\\auth\\infrastructure\\adapter\\jwt\\hasbeenissuedbefore',
        20 => 'tests\\unit\\auth\\infrastructure\\adapter\\jwt\\isminimumtimebefore',
        21 => 'tests\\unit\\auth\\infrastructure\\adapter\\jwt\\isexpired',
        22 => 'tests\\unit\\auth\\infrastructure\\adapter\\jwt\\tostring',
        23 => 'tests\\unit\\auth\\infrastructure\\adapter\\jwt\\publickeypath',
        24 => 'tests\\unit\\auth\\infrastructure\\adapter\\jwt\\privatekeypath',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Infrastructure/Adapter/Mfa/OtpModuleChallengeGeneratorAdapterTest.php' => 
    array (
      0 => 'a399e6fdf7664dff32856522468c86137eba668b',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\infrastructure\\adapter\\mfa\\otpmodulechallengegeneratoradaptertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\infrastructure\\adapter\\mfa\\testgeneratemapschallengeinfo',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Infrastructure/Adapter/Mfa/OtpModuleChallengeVerifierAdapterTest.php' => 
    array (
      0 => '5f8f9e0585ec70085f71cff787490a83261095a6',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\infrastructure\\adapter\\mfa\\otpmodulechallengeverifieradaptertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\infrastructure\\adapter\\mfa\\testverifymapschallengeinfo',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Infrastructure/Adapter/Session/SessionTrackingAdapterTest.php' => 
    array (
      0 => '28e04446bcec1d1fca70ad758f2e69260b595d6f',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\infrastructure\\adapter\\session\\sessiontrackingadaptertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\infrastructure\\adapter\\session\\testrecordsessiondelegates',
        1 => 'tests\\unit\\auth\\infrastructure\\adapter\\session\\testrotatesessiontokensdelegates',
        2 => 'tests\\unit\\auth\\infrastructure\\adapter\\session\\testrevokesessionbytokendelegates',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Infrastructure/Adapter/User/UserAuthenticationAdapterTest.php' => 
    array (
      0 => '7562fe55a0039f9ca4ccbdd6d5f082a09a41fa43',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\infrastructure\\adapter\\user\\userauthenticationadaptertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\infrastructure\\adapter\\user\\testauthenticatereturnssuccess',
        1 => 'tests\\unit\\auth\\infrastructure\\adapter\\user\\testauthenticatereturnsfailedwhennotauthenticated',
        2 => 'tests\\unit\\auth\\infrastructure\\adapter\\user\\testauthenticatereturnsfailedonexception',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Infrastructure/Console/CleanupAuthDataCommandTest.php' => 
    array (
      0 => '15480c2b2892ac91b450159ee8685041cd38b471',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\infrastructure\\console\\cleanupauthdatacommandtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\infrastructure\\console\\testexecutefailswhenretentionnegative',
        1 => 'tests\\unit\\auth\\infrastructure\\console\\testexecutedryruncounts',
        2 => 'tests\\unit\\auth\\infrastructure\\console\\testexecutedeleteswhennotdryrun',
        3 => 'tests\\unit\\auth\\infrastructure\\console\\createentitymanagerwithquerybuilders',
        4 => 'tests\\unit\\auth\\infrastructure\\console\\createquerybuildermock',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Infrastructure/EventSubscriber/EventLogSubscriberTest.php' => 
    array (
      0 => 'c5ebfd5c2d0b14fe393c76e38cd576dca389b558',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\infrastructure\\eventsubscriber\\eventlogsubscribertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\infrastructure\\eventsubscriber\\testgetsubscribedevents',
        1 => 'tests\\unit\\auth\\infrastructure\\eventsubscriber\\testonuserloggedinlogsinfo',
        2 => 'tests\\unit\\auth\\infrastructure\\eventsubscriber\\testonloginfailedlogswarning',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Infrastructure/EventSubscriber/RefreshTokenCookieSubscriberTest.php' => 
    array (
      0 => 'f07cf89b54891046965d949fdf643a9968f1828c',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\infrastructure\\eventsubscriber\\refreshtokencookiesubscribertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\infrastructure\\eventsubscriber\\testonkernelresponseaddscookiewhenpresent',
        1 => 'tests\\unit\\auth\\infrastructure\\eventsubscriber\\testonkernelresponseskipswhennocookie',
        2 => 'tests\\unit\\auth\\infrastructure\\eventsubscriber\\testgetsubscribedeventsregisterskernelresponse',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Infrastructure/Security/Authenticator/OAuth2AuthenticatorTest.php' => 
    array (
      0 => '4aeb0aca44f334a90e624035cefd0c9f5cdb77db',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\infrastructure\\security\\authenticator\\oauth2authenticatortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\infrastructure\\security\\authenticator\\testsupportschecksauthorizationheader',
        1 => 'tests\\unit\\auth\\infrastructure\\security\\authenticator\\testauthenticaterejectsemptytoken',
        2 => 'tests\\unit\\auth\\infrastructure\\security\\authenticator\\testauthenticaterejectsnonunencryptedtoken',
        3 => 'tests\\unit\\auth\\infrastructure\\security\\authenticator\\parse',
        4 => 'tests\\unit\\auth\\infrastructure\\security\\authenticator\\headers',
        5 => 'tests\\unit\\auth\\infrastructure\\security\\authenticator\\ispermittedfor',
        6 => 'tests\\unit\\auth\\infrastructure\\security\\authenticator\\isidentifiedby',
        7 => 'tests\\unit\\auth\\infrastructure\\security\\authenticator\\isrelatedto',
        8 => 'tests\\unit\\auth\\infrastructure\\security\\authenticator\\hasbeenissuedby',
        9 => 'tests\\unit\\auth\\infrastructure\\security\\authenticator\\hasbeenissuedbefore',
        10 => 'tests\\unit\\auth\\infrastructure\\security\\authenticator\\isminimumtimebefore',
        11 => 'tests\\unit\\auth\\infrastructure\\security\\authenticator\\isexpired',
        12 => 'tests\\unit\\auth\\infrastructure\\security\\authenticator\\tostring',
        13 => 'tests\\unit\\auth\\infrastructure\\security\\authenticator\\testauthenticaterejectsmissingclaims',
        14 => 'tests\\unit\\auth\\infrastructure\\security\\authenticator\\testauthenticatewithaccesstokenstatususesscopes',
        15 => 'tests\\unit\\auth\\infrastructure\\security\\authenticator\\testauthenticaterejectsrevokedaccesstoken',
        16 => 'tests\\unit\\auth\\infrastructure\\security\\authenticator\\testauthenticaterejectsexpiredaccesstoken',
        17 => 'tests\\unit\\auth\\infrastructure\\security\\authenticator\\testauthenticatewithjwtvalidationfiltersscopes',
        18 => 'tests\\unit\\auth\\infrastructure\\security\\authenticator\\testauthenticateskipsaccesstokenlookupforinternalloginjwt',
        19 => 'tests\\unit\\auth\\infrastructure\\security\\authenticator\\testauthenticaterejectsinvalidinternalloginjwtwithoutdatabaselookup',
        20 => 'tests\\unit\\auth\\infrastructure\\security\\authenticator\\testauthenticaterejectsinvalidsignature',
        21 => 'tests\\unit\\auth\\infrastructure\\security\\authenticator\\testauthenticaterejectsexpiredjwt',
        22 => 'tests\\unit\\auth\\infrastructure\\security\\authenticator\\testauthenticaterejectsinvalidtokenstring',
        23 => 'tests\\unit\\auth\\infrastructure\\security\\authenticator\\testconstructorthrowswhenpublickeypathempty',
        24 => 'tests\\unit\\auth\\infrastructure\\security\\authenticator\\testonauthenticationsuccessreturnsnull',
        25 => 'tests\\unit\\auth\\infrastructure\\security\\authenticator\\testonauthenticationfailurereturnsjsonresponse',
        26 => 'tests\\unit\\auth\\infrastructure\\security\\authenticator\\createauthenticator',
        27 => 'tests\\unit\\auth\\infrastructure\\security\\authenticator\\createuserprovider',
        28 => 'tests\\unit\\auth\\infrastructure\\security\\authenticator\\createuserview',
        29 => 'tests\\unit\\auth\\infrastructure\\security\\authenticator\\buildrsatoken',
        30 => 'tests\\unit\\auth\\infrastructure\\security\\authenticator\\buildhmactoken',
        31 => 'tests\\unit\\auth\\infrastructure\\security\\authenticator\\getprojectdir',
        32 => 'tests\\unit\\auth\\infrastructure\\security\\authenticator\\getpublickeypath',
        33 => 'tests\\unit\\auth\\infrastructure\\security\\authenticator\\getprivatekeypath',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Infrastructure/Security/DPoP/DPoPValidatorTest.php' => 
    array (
      0 => '947a9b94b329f253fc008c0261950e3e83137d25',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\infrastructure\\security\\dpop\\dpopvalidatortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\infrastructure\\security\\dpop\\testgeneratenoncestoresandvalidates',
        1 => 'tests\\unit\\auth\\infrastructure\\security\\dpop\\testcalculatethumbprintfromheader',
        2 => 'tests\\unit\\auth\\infrastructure\\security\\dpop\\testvalidateproofreturnsproofandenforcesreplay',
        3 => 'tests\\unit\\auth\\infrastructure\\security\\dpop\\testvalidateproofreturnsnullwhenjwtmalformed',
        4 => 'tests\\unit\\auth\\infrastructure\\security\\dpop\\testvalidateproofreturnsnullwhenpayloadnotarray',
        5 => 'tests\\unit\\auth\\infrastructure\\security\\dpop\\testvalidateproofreturnsnullwhenproofclaimsinvalid',
        6 => 'tests\\unit\\auth\\infrastructure\\security\\dpop\\testvalidateproofreturnsnullforinvalidheader',
        7 => 'tests\\unit\\auth\\infrastructure\\security\\dpop\\testvalidateproofreturnsnullwhennoncemismatch',
        8 => 'tests\\unit\\auth\\infrastructure\\security\\dpop\\testvalidateproofreturnsnullwhenathmismatch',
        9 => 'tests\\unit\\auth\\infrastructure\\security\\dpop\\testvalidateproofreturnsnullwhenproofnotvalidformethod',
        10 => 'tests\\unit\\auth\\infrastructure\\security\\dpop\\testcalculatethumbprintreturnsnullforinvalidheader',
        11 => 'tests\\unit\\auth\\infrastructure\\security\\dpop\\testcalculatethumbprintreturnsnullforinvalidparts',
        12 => 'tests\\unit\\auth\\infrastructure\\security\\dpop\\testcalculatethumbprintreturnsnullwhenheadernotarray',
        13 => 'tests\\unit\\auth\\infrastructure\\security\\dpop\\testcalculatethumbprintreturnsnullwhenjwknotarray',
        14 => 'tests\\unit\\auth\\infrastructure\\security\\dpop\\testcalculatethumbprintsupportseckeys',
        15 => 'tests\\unit\\auth\\infrastructure\\security\\dpop\\invalidheaderprovider',
        16 => 'tests\\unit\\auth\\infrastructure\\security\\dpop\\creatersaheaderandkey',
        17 => 'tests\\unit\\auth\\infrastructure\\security\\dpop\\buildsignedjwt',
        18 => 'tests\\unit\\auth\\infrastructure\\security\\dpop\\buildjwtfromparts',
        19 => 'tests\\unit\\auth\\infrastructure\\security\\dpop\\buildsignedjwtfromparts',
        20 => 'tests\\unit\\auth\\infrastructure\\security\\dpop\\buildjwt',
        21 => 'tests\\unit\\auth\\infrastructure\\security\\dpop\\base64urlencode',
        22 => 'tests\\unit\\auth\\infrastructure\\security\\dpop\\calculatejwkthumbprint',
        23 => 'tests\\unit\\auth\\infrastructure\\security\\dpop\\calculateaccesstokenhash',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Infrastructure/Security/User/SecurityUserProviderTest.php' => 
    array (
      0 => '60c272757f1497cdc6645d4e8980d301c2624a5e',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\infrastructure\\security\\user\\securityuserprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\infrastructure\\security\\user\\testloaduserbyidreturnssecurityuser',
        1 => 'tests\\unit\\auth\\infrastructure\\security\\user\\testloaduserbyidentifierdelegatestoloaduserbyid',
        2 => 'tests\\unit\\auth\\infrastructure\\security\\user\\testloaduserbyidthrowswhenusermissing',
        3 => 'tests\\unit\\auth\\infrastructure\\security\\user\\testloaduserbyidthrowswhenqueryfails',
        4 => 'tests\\unit\\auth\\infrastructure\\security\\user\\testloaduserbyidmapsinactiveuserroles',
        5 => 'tests\\unit\\auth\\infrastructure\\security\\user\\testrefreshuserthrowsforunsupporteduser',
        6 => 'tests\\unit\\auth\\infrastructure\\security\\user\\testrefreshuserreloadssecurityuser',
        7 => 'tests\\unit\\auth\\infrastructure\\security\\user\\testsupportsclassreturnstrueforsecurityuser',
        8 => 'tests\\unit\\auth\\infrastructure\\security\\user\\testloaduserbyidnormalizesemptystringtenantidtonull',
        9 => 'tests\\unit\\auth\\infrastructure\\security\\user\\testloaduserbyidpropagatestenantid',
        10 => 'tests\\unit\\auth\\infrastructure\\security\\user\\testloaduserbyidusescachedbaseuserbutappliescurrenttokenscopes',
        11 => 'tests\\unit\\auth\\infrastructure\\security\\user\\createuserview',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Infrastructure/Security/User/SecurityUserTest.php' => 
    array (
      0 => '2a0f9c6640b34caca3b7b9145ab0a2485afb930b',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\infrastructure\\security\\user\\securityusertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\infrastructure\\security\\user\\testgetrolesalwaysincludesroleuser',
        1 => 'tests\\unit\\auth\\infrastructure\\security\\user\\testgetuseridentifierreturnsemail',
        2 => 'tests\\unit\\auth\\infrastructure\\security\\user\\testhasscopeiscaseinsensitive',
        3 => 'tests\\unit\\auth\\infrastructure\\security\\user\\testisactivereturnsflag',
        4 => 'tests\\unit\\auth\\infrastructure\\security\\user\\testgetpasswordreturnshash',
        5 => 'tests\\unit\\auth\\infrastructure\\security\\user\\testgettenantidreturnsnullwhennotset',
        6 => 'tests\\unit\\auth\\infrastructure\\security\\user\\testgettenantidreturnsvalue',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Infrastructure/Security/Voter/OAuth2ScopeVoterTest.php' => 
    array (
      0 => '62edcda55b4dacced0321b51f30c36c56c6d6d7d',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\infrastructure\\security\\voter\\oauth2scopevotertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\infrastructure\\security\\voter\\testvotegrantswhenscopepresent',
        1 => 'tests\\unit\\auth\\infrastructure\\security\\voter\\testvotedenieswhenscopemissing',
        2 => 'tests\\unit\\auth\\infrastructure\\security\\voter\\testvotedenieswhenusernotsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Infrastructure/Security/Voter/ResourceOwnerVoterTest.php' => 
    array (
      0 => '4c6120e355c136ba087a24f329341f5210cf1315',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\infrastructure\\security\\voter\\resourceownervotertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\infrastructure\\security\\voter\\testvotegrantswhenownermatches',
        1 => 'tests\\unit\\auth\\infrastructure\\security\\voter\\getuserid',
        2 => 'tests\\unit\\auth\\infrastructure\\security\\voter\\testvotedenieswhenownerdoesnotmatch',
        3 => 'tests\\unit\\auth\\infrastructure\\security\\voter\\getownerid',
        4 => 'tests\\unit\\auth\\infrastructure\\security\\voter\\testvotedenieswhenuserisnotsecurityuser',
        5 => 'tests\\unit\\auth\\infrastructure\\security\\voter\\getuserid',
        6 => 'tests\\unit\\auth\\infrastructure\\security\\voter\\testvoteusesvalueobjectownerid',
        7 => 'tests\\unit\\auth\\infrastructure\\security\\voter\\ownerid',
        8 => 'tests\\unit\\auth\\infrastructure\\security\\voter\\testvoteusesownerobjecttostring',
        9 => 'tests\\unit\\auth\\infrastructure\\security\\voter\\getowner',
        10 => 'tests\\unit\\auth\\infrastructure\\security\\voter\\__tostring',
        11 => 'tests\\unit\\auth\\infrastructure\\security\\voter\\testvotedenieswhenowneridcannotberesolved',
        12 => 'tests\\unit\\auth\\infrastructure\\security\\voter\\getownerid',
        13 => 'tests\\unit\\auth\\infrastructure\\security\\voter\\testvoteabstainswhensubjectnotobject',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Presentation/Api/Processor/Auth/LoginProcessorTest.php' => 
    array (
      0 => 'cf8599cdf4942b322261911de4dcbe2eb17c5aa5',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\presentation\\api\\processor\\auth\\loginprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\presentation\\api\\processor\\auth\\testprocessthrowsoninvalidinput',
        1 => 'tests\\unit\\auth\\presentation\\api\\processor\\auth\\testprocessthrowsunauthorizedwhenfailed',
        2 => 'tests\\unit\\auth\\presentation\\api\\processor\\auth\\testprocessthrowstoomanyrequestswhenratelimited',
        3 => 'tests\\unit\\auth\\presentation\\api\\processor\\auth\\testprocessreturnsmfaoutputwhenrequired',
        4 => 'tests\\unit\\auth\\presentation\\api\\processor\\auth\\testprocesssetsrefreshtokencookieonsuccess',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Presentation/Api/Processor/Auth/LogoutProcessorTest.php' => 
    array (
      0 => '17565cabea73a4d280cacb3615d6179238b82f7f',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\presentation\\api\\processor\\auth\\logoutprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\presentation\\api\\processor\\auth\\testprocessdispatcheslogoutandsetscookie',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Presentation/Api/Processor/Auth/MfaVerifyProcessorTest.php' => 
    array (
      0 => '6ea674b5023ef5346bf3761d4f2ec10308e78975',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\presentation\\api\\processor\\auth\\mfaverifyprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\presentation\\api\\processor\\auth\\testprocessthrowsunauthorizedonautherror',
        1 => 'tests\\unit\\auth\\presentation\\api\\processor\\auth\\testprocessthrowsbadrequestwhencodeinvalid',
        2 => 'tests\\unit\\auth\\presentation\\api\\processor\\auth\\testprocessthrowsbadrequestwhenverificationfails',
        3 => 'tests\\unit\\auth\\presentation\\api\\processor\\auth\\testprocessreturnsoutputandsetscookieonsuccess',
        4 => 'tests\\unit\\auth\\presentation\\api\\processor\\auth\\testprocessthrowstoomanyrequestswhenratelimited',
        5 => 'tests\\unit\\auth\\presentation\\api\\processor\\auth\\createratelimiterfactory',
        6 => 'tests\\unit\\auth\\presentation\\api\\processor\\auth\\createratelimitkey',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Presentation/Api/Processor/Auth/RefreshTokenProcessorTest.php' => 
    array (
      0 => '36b5c11dc78dd88306954479a2348c608a9c0a3f',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\presentation\\api\\processor\\auth\\refreshtokenprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\presentation\\api\\processor\\auth\\testprocessreturnsnullwhennorequest',
        1 => 'tests\\unit\\auth\\presentation\\api\\processor\\auth\\testprocessthrowswhennorefreshtoken',
        2 => 'tests\\unit\\auth\\presentation\\api\\processor\\auth\\testprocessthrowswhenrefreshfails',
        3 => 'tests\\unit\\auth\\presentation\\api\\processor\\auth\\testprocessreturnsoutputandsetscookieonsuccess',
        4 => 'tests\\unit\\auth\\presentation\\api\\processor\\auth\\testprocessthrowstoomanyrequestswhenratelimited',
        5 => 'tests\\unit\\auth\\presentation\\api\\processor\\auth\\createratelimiterfactory',
        6 => 'tests\\unit\\auth\\presentation\\api\\processor\\auth\\createratelimitkey',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Presentation/Api/Processor/PasswordReset/ConfirmPasswordResetProcessorTest.php' => 
    array (
      0 => 'bfef979854d19b8203c7682bb18635655257d792',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\presentation\\api\\processor\\passwordreset\\confirmpasswordresetprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\presentation\\api\\processor\\passwordreset\\testprocessreturnsoutputonsuccess',
        1 => 'tests\\unit\\auth\\presentation\\api\\processor\\passwordreset\\testprocessthrowsunauthorizedwheninvalidcode',
        2 => 'tests\\unit\\auth\\presentation\\api\\processor\\passwordreset\\testprocessthrowstoomanyrequestswhenratelimited',
        3 => 'tests\\unit\\auth\\presentation\\api\\processor\\passwordreset\\createratelimiterfactory',
        4 => 'tests\\unit\\auth\\presentation\\api\\processor\\passwordreset\\createratelimitkey',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Presentation/Api/Processor/PasswordReset/RequestPasswordResetProcessorTest.php' => 
    array (
      0 => 'e19649445f4e1759a48f3dcc17d3dd87550d1566',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\presentation\\api\\processor\\passwordreset\\requestpasswordresetprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\presentation\\api\\processor\\passwordreset\\testprocessreturnsoutputonsuccess',
        1 => 'tests\\unit\\auth\\presentation\\api\\processor\\passwordreset\\testprocessthrowstoomanyrequestswhenratelimited',
        2 => 'tests\\unit\\auth\\presentation\\api\\processor\\passwordreset\\createratelimiterfactory',
        3 => 'tests\\unit\\auth\\presentation\\api\\processor\\passwordreset\\createratelimitkey',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Presentation/Api/Resource/AuthResourceTest.php' => 
    array (
      0 => 'e31911df0e0a2b7c8c454c458b341e10fff2337e',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\presentation\\api\\resource\\authresourcetest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\presentation\\api\\resource\\testresourcecanbeinstantiated',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Presentation/Api/Service/RefreshTokenCookieServiceTest.php' => 
    array (
      0 => '767544244de3a503a102ac06e53d67b89d7491f4',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\presentation\\api\\service\\refreshtokencookieservicetest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\presentation\\api\\service\\testgetcookienameaddshostprefixinprod',
        1 => 'tests\\unit\\auth\\presentation\\api\\service\\testgetcookienameusesbaseinnonprod',
        2 => 'tests\\unit\\auth\\presentation\\api\\service\\testcreatecookiesetsattributes',
        3 => 'tests\\unit\\auth\\presentation\\api\\service\\testcreateclearcookieexpiresinpast',
        4 => 'tests\\unit\\auth\\presentation\\api\\service\\testgetrefreshtokenfromrequest',
        5 => 'tests\\unit\\auth\\presentation\\api\\service\\testcookiesecureoverridedisableshostprefix',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Auth/Presentation/Api/Validator/GrantType/GrantTypeValidatorTest.php' => 
    array (
      0 => '67bcbe8d2e2ae3c85eb00811b076b612d640aae4',
      1 => 
      array (
        0 => 'tests\\unit\\auth\\presentation\\api\\validator\\granttype\\granttypevalidatortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\auth\\presentation\\api\\validator\\granttype\\testvalidaterejectsunsupportedgranttype',
        1 => 'tests\\unit\\auth\\presentation\\api\\validator\\granttype\\testvalidaterefreshtokenrequirestoken',
        2 => 'tests\\unit\\auth\\presentation\\api\\validator\\granttype\\testvalidateauthorizationcoderequiresfields',
        3 => 'tests\\unit\\auth\\presentation\\api\\validator\\granttype\\testvalidateauthorizationcoderequiresredirecturi',
        4 => 'tests\\unit\\auth\\presentation\\api\\validator\\granttype\\testvalidateauthorizationcoderequirescodeverifier',
        5 => 'tests\\unit\\auth\\presentation\\api\\validator\\granttype\\testvalidateauthorizationcodesucceedswhenvalid',
        6 => 'tests\\unit\\auth\\presentation\\api\\validator\\granttype\\testvalidaterefreshtokensucceedswhentokenprovided',
        7 => 'tests\\unit\\auth\\presentation\\api\\validator\\granttype\\testvalidateclientcredentialssucceeds',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Application/Service/AuthorizationServiceTest.php' => 
    array (
      0 => 'cb8386bf8605363286912e69e859ad43410a11d8',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\application\\service\\authorizationservicetest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\application\\service\\testhaspermissionmatcheswildcard',
        1 => 'tests\\unit\\authorization\\application\\service\\testhaspermissionreturnsfalsewhenmissing',
        2 => 'tests\\unit\\authorization\\application\\service\\testrolechecksandnames',
        3 => 'tests\\unit\\authorization\\application\\service\\testgetuserrolenamesreturnscachedroles',
        4 => 'tests\\unit\\authorization\\application\\service\\testgetuserpermissionsdeduplicates',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Application/UseCase/Command/Permission/CreatePermission/CreatePermissionHandlerTest.php' => 
    array (
      0 => '9ce0e9b16c10e04be60d45adead83bb237b7158a',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\application\\usecase\\command\\permission\\createpermission\\createpermissionhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\application\\usecase\\command\\permission\\createpermission\\testinvokecreatespermission',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Application/UseCase/Command/Permission/DeletePermission/DeletePermissionHandlerTest.php' => 
    array (
      0 => '2129f4e1854a030792bd4b5d9c783be1dce30ad6',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\application\\usecase\\command\\permission\\deletepermission\\deletepermissionhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\application\\usecase\\command\\permission\\deletepermission\\testinvokethrowswhenpermissionmissing',
        1 => 'tests\\unit\\authorization\\application\\usecase\\command\\permission\\deletepermission\\testinvokedeletespermission',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Application/UseCase/Command/Permission/UpdatePermission/UpdatePermissionHandlerTest.php' => 
    array (
      0 => '41affedc5fddc212ec35c6cec499ae09e239bc11',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\application\\usecase\\command\\permission\\updatepermission\\updatepermissionhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\application\\usecase\\command\\permission\\updatepermission\\testinvokethrowswhenpermissionmissing',
        1 => 'tests\\unit\\authorization\\application\\usecase\\command\\permission\\updatepermission\\testinvokereturnspermissionresult',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Application/UseCase/Command/Role/AddPermissionToRole/AddPermissionToRoleHandlerTest.php' => 
    array (
      0 => '27c44ceb1e3c71f401440ddf6aa876cf95dadc4f',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\application\\usecase\\command\\role\\addpermissiontorole\\addpermissiontorolehandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\application\\usecase\\command\\role\\addpermissiontorole\\testinvokeaddspermission',
        1 => 'tests\\unit\\authorization\\application\\usecase\\command\\role\\addpermissiontorole\\testinvokethrowswhenrolemissing',
        2 => 'tests\\unit\\authorization\\application\\usecase\\command\\role\\addpermissiontorole\\testinvokethrowswhenpermissionmissing',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Application/UseCase/Command/Role/CreateRole/CreateRoleHandlerTest.php' => 
    array (
      0 => '3a5a64fb3a4f7ed648dcbc054c5b09f9fdd78a80',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\application\\usecase\\command\\role\\createrole\\createrolehandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\application\\usecase\\command\\role\\createrole\\testinvokecreatesroleandmapspermissions',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Application/UseCase/Command/Role/DeleteRole/DeleteRoleHandlerTest.php' => 
    array (
      0 => '7cb68eeff97924a1ff9eacdebacc37df5abb45ae',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\application\\usecase\\command\\role\\deleterole\\deleterolehandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\application\\usecase\\command\\role\\deleterole\\testinvokethrowswhenrolemissing',
        1 => 'tests\\unit\\authorization\\application\\usecase\\command\\role\\deleterole\\testinvokethrowswhenroleissystem',
        2 => 'tests\\unit\\authorization\\application\\usecase\\command\\role\\deleterole\\testinvokedeletesrole',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Application/UseCase/Command/Role/RemovePermissionFromRole/RemovePermissionFromRoleHandlerTest.php' => 
    array (
      0 => 'b0b97b1a76f4a2f7ad9d64e98f8601812a0fb8ce',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\application\\usecase\\command\\role\\removepermissionfromrole\\removepermissionfromrolehandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\application\\usecase\\command\\role\\removepermissionfromrole\\testinvokeremovespermission',
        1 => 'tests\\unit\\authorization\\application\\usecase\\command\\role\\removepermissionfromrole\\testinvokethrowswhenrolemissing',
        2 => 'tests\\unit\\authorization\\application\\usecase\\command\\role\\removepermissionfromrole\\testinvokethrowswhenpermissionmissing',
        3 => 'tests\\unit\\authorization\\application\\usecase\\command\\role\\removepermissionfromrole\\testinvokemapsremainingpermissions',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Application/UseCase/Command/Role/UpdateRole/UpdateRoleHandlerTest.php' => 
    array (
      0 => '6b44c0cad932f38a2d298a4b42be0803dff2f268',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\application\\usecase\\command\\role\\updaterole\\updaterolehandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\application\\usecase\\command\\role\\updaterole\\testinvokethrowswhenrolemissing',
        1 => 'tests\\unit\\authorization\\application\\usecase\\command\\role\\updaterole\\testinvokeupdatesroleandpermissions',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Application/UseCase/Query/Permission/GetPermission/GetPermissionHandlerTest.php' => 
    array (
      0 => 'eddc02c65ce11528a55a68b2e81438bc812dee86',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\application\\usecase\\query\\permission\\getpermission\\getpermissionhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\application\\usecase\\query\\permission\\getpermission\\testinvokethrowswhenpermissionmissing',
        1 => 'tests\\unit\\authorization\\application\\usecase\\query\\permission\\getpermission\\testinvokereturnsresult',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Application/UseCase/Query/Permission/ListPermissions/ListPermissionsHandlerTest.php' => 
    array (
      0 => '951601eeeb43e185beb150f910b40726a13c5757',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\application\\usecase\\query\\permission\\listpermissions\\listpermissionshandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\application\\usecase\\query\\permission\\listpermissions\\testinvokereturnsmappedpermissions',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Application/UseCase/Query/Role/GetRole/GetRoleHandlerTest.php' => 
    array (
      0 => 'c4f85a18291103ecacd93e65441ea199471a0c3d',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\application\\usecase\\query\\role\\getrole\\getrolehandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\application\\usecase\\query\\role\\getrole\\testinvokethrowswhenrolemissing',
        1 => 'tests\\unit\\authorization\\application\\usecase\\query\\role\\getrole\\testinvokereturnsroleresult',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Application/UseCase/Query/Role/ListRoles/ListRolesHandlerTest.php' => 
    array (
      0 => '8c7e886636ca67ae55d2a818a562752df5e602d6',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\application\\usecase\\query\\role\\listroles\\listroleshandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\application\\usecase\\query\\role\\listroles\\testinvokereturnsmappedroles',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Domain/Event/RoleAssignedEventTest.php' => 
    array (
      0 => '9b7572b84361bf3a42bce6d209747e273e5a206f',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\domain\\event\\roleassignedeventtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\domain\\event\\testpayloadandmetadata',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Domain/Event/RoleCreatedEventTest.php' => 
    array (
      0 => '9c2ea67e68a1d7d1a3683a2a604997743236d7e0',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\domain\\event\\rolecreatedeventtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\domain\\event\\testpayloadandmetadata',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Domain/Event/RoleRevokedEventTest.php' => 
    array (
      0 => 'd82640577a67fcc670371968c9dc0f0332954951',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\domain\\event\\rolerevokedeventtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\domain\\event\\testpayloadandmetadata',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Domain/Exception/PermissionNotFoundExceptionTest.php' => 
    array (
      0 => '213ab780ed058fcfb16c98217e42971fa5037ce8',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\domain\\exception\\permissionnotfoundexceptiontest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\domain\\exception\\testwithidcreatesmessage',
        1 => 'tests\\unit\\authorization\\domain\\exception\\testwithnamecreatesmessage',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Domain/Exception/RoleNotFoundExceptionTest.php' => 
    array (
      0 => '5db0708ee66e90da0d68e885eeb8fc26b0459c58',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\domain\\exception\\rolenotfoundexceptiontest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\domain\\exception\\testwithidcreatesmessage',
        1 => 'tests\\unit\\authorization\\domain\\exception\\testwithnamecreatesmessage',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Domain/Exception/SystemRoleDeletionExceptionTest.php' => 
    array (
      0 => '03561b101ad8bfe776266939af77f3fd1b4fb0ff',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\domain\\exception\\systemroledeletionexceptiontest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\domain\\exception\\testforrolecreatesmessage',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Domain/Model/PermissionTest.php' => 
    array (
      0 => 'd9d6cc8e108471a32eb05e3b91f22c8d965b1121',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\domain\\model\\permissiontest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\domain\\model\\testcreatepermissionwithvaliddata',
        1 => 'tests\\unit\\authorization\\domain\\model\\testcreatepermissionwithemptydescription',
        2 => 'tests\\unit\\authorization\\domain\\model\\testmatcheswithexactname',
        3 => 'tests\\unit\\authorization\\domain\\model\\testmatcheswithwildcard',
        4 => 'tests\\unit\\authorization\\domain\\model\\testmatcheswithsuperwildcard',
        5 => 'tests\\unit\\authorization\\domain\\model\\testdoesnotmatchdifferentpermission',
        6 => 'tests\\unit\\authorization\\domain\\model\\testwildcarddoesnotmatchdifferentresource',
        7 => 'tests\\unit\\authorization\\domain\\model\\testpermissionequalitybyid',
        8 => 'tests\\unit\\authorization\\domain\\model\\testpermissioninequalitywithdifferentids',
        9 => 'tests\\unit\\authorization\\domain\\model\\createpermission',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Domain/Model/RoleAssignment/RoleAssignmentTest.php' => 
    array (
      0 => 'a341a768a85fc2e51056e9b7390722f544430ae8',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\domain\\model\\roleassignment\\roleassignmenttest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\domain\\model\\roleassignment\\testassigntousercreatesassignment',
        1 => 'tests\\unit\\authorization\\domain\\model\\roleassignment\\testisexpiredreturnstruewhenpast',
        2 => 'tests\\unit\\authorization\\domain\\model\\roleassignment\\testreconstitutekeepsvaluesandequals',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Domain/Model/RoleTest.php' => 
    array (
      0 => '86f5052bebbcfbc8807e2b49065aa859c5359de7',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\domain\\model\\roletest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\domain\\model\\testcreaterolewithvaliddata',
        1 => 'tests\\unit\\authorization\\domain\\model\\testcreatesystemrole',
        2 => 'tests\\unit\\authorization\\domain\\model\\testaddpermissiontorole',
        3 => 'tests\\unit\\authorization\\domain\\model\\testaddduplicatepermissiondoesnotaddtwice',
        4 => 'tests\\unit\\authorization\\domain\\model\\testremovepermissionfromrole',
        5 => 'tests\\unit\\authorization\\domain\\model\\testhaspermissionwithexactmatch',
        6 => 'tests\\unit\\authorization\\domain\\model\\testhaspermissionwithwildcardmatch',
        7 => 'tests\\unit\\authorization\\domain\\model\\testhaspermissionreturnsfalseformissingpermission',
        8 => 'tests\\unit\\authorization\\domain\\model\\testupdaterolenameanddescription',
        9 => 'tests\\unit\\authorization\\domain\\model\\testreconstituterestorespermissions',
        10 => 'tests\\unit\\authorization\\domain\\model\\testremovepermissionupdatestimestampwhenmissing',
        11 => 'tests\\unit\\authorization\\domain\\model\\testroleequalitybyid',
        12 => 'tests\\unit\\authorization\\domain\\model\\testroleinequalitywithdifferentids',
        13 => 'tests\\unit\\authorization\\domain\\model\\createtestrole',
        14 => 'tests\\unit\\authorization\\domain\\model\\createtestpermission',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Domain/ValueObject/PermissionIdTest.php' => 
    array (
      0 => '2bcf9966442bffa74d2dbed615c80bf7e390733e',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\domain\\valueobject\\permissionidtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\domain\\valueobject\\testcreatewithvaliduuid',
        1 => 'tests\\unit\\authorization\\domain\\valueobject\\testcreatewithinvaliduuidthrowsexception',
        2 => 'tests\\unit\\authorization\\domain\\valueobject\\testpermissionidequality',
        3 => 'tests\\unit\\authorization\\domain\\valueobject\\testpermissionidinequality',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Domain/ValueObject/PermissionNameTest.php' => 
    array (
      0 => '3b78baf33f6b4822bee7ef22d218e4eaa501e547',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\domain\\valueobject\\permissionnametest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\domain\\valueobject\\testmatcheswildcardandexact',
        1 => 'tests\\unit\\authorization\\domain\\valueobject\\testresourceandaction',
        2 => 'tests\\unit\\authorization\\domain\\valueobject\\testsinglepartpermissionmatchesresource',
        3 => 'tests\\unit\\authorization\\domain\\valueobject\\testactionwildcardmatchesallresources',
        4 => 'tests\\unit\\authorization\\domain\\valueobject\\testemptyvaluethrowsexception',
        5 => 'tests\\unit\\authorization\\domain\\valueobject\\testtostringreturnsvalue',
        6 => 'tests\\unit\\authorization\\domain\\valueobject\\testequals',
        7 => 'tests\\unit\\authorization\\domain\\valueobject\\testinvalidvaluethrows',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Domain/ValueObject/RoleAssignmentIdTest.php' => 
    array (
      0 => '683216094ae048f783f2d71a150145072136313c',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\domain\\valueobject\\roleassignmentidtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\domain\\valueobject\\testcreatewithvaliduuid',
        1 => 'tests\\unit\\authorization\\domain\\valueobject\\testcreatewithinvaliduuidthrowsexception',
        2 => 'tests\\unit\\authorization\\domain\\valueobject\\testroleassignmentidequality',
        3 => 'tests\\unit\\authorization\\domain\\valueobject\\testroleassignmentidinequality',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Domain/ValueObject/RoleIdTest.php' => 
    array (
      0 => 'dea51af122f6567e2dbc25644b5c31730e8dde98',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\domain\\valueobject\\roleidtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\domain\\valueobject\\testcreatewithvaliduuidv4',
        1 => 'tests\\unit\\authorization\\domain\\valueobject\\testcreatewithvaliduuidv7',
        2 => 'tests\\unit\\authorization\\domain\\valueobject\\testcreatewithinvaliduuidthrowsexception',
        3 => 'tests\\unit\\authorization\\domain\\valueobject\\testcreatewithemptystringthrowsexception',
        4 => 'tests\\unit\\authorization\\domain\\valueobject\\testroleidequality',
        5 => 'tests\\unit\\authorization\\domain\\valueobject\\testroleidinequality',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Domain/ValueObject/RoleNameTest.php' => 
    array (
      0 => '98ab86eab7f775b4a7cbc62e01f8d13e1c6cc87f',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\domain\\valueobject\\rolenametest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\domain\\valueobject\\testcreatewithvalidlowercasename',
        1 => 'tests\\unit\\authorization\\domain\\valueobject\\testcreatewithunderscores',
        2 => 'tests\\unit\\authorization\\domain\\valueobject\\testcreatewithnumbers',
        3 => 'tests\\unit\\authorization\\domain\\valueobject\\testcreatewithuppercasethrowsexception',
        4 => 'tests\\unit\\authorization\\domain\\valueobject\\testcreatewithemptystringthrowsexception',
        5 => 'tests\\unit\\authorization\\domain\\valueobject\\testcreatewithspecialcharactersthrowsexception',
        6 => 'tests\\unit\\authorization\\domain\\valueobject\\testrolenameequality',
        7 => 'tests\\unit\\authorization\\domain\\valueobject\\testrolenameinequality',
        8 => 'tests\\unit\\authorization\\domain\\valueobject\\testrolenametostring',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Domain/ValueObject/SubjectTypeTest.php' => 
    array (
      0 => '9ebeea37b3c5420a1363c96043a3ab950b3df05b',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\domain\\valueobject\\subjecttypetest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\domain\\valueobject\\testlabelreturnsexpectedvalue',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Infrastructure/Catalog/RoleCatalogTest.php' => 
    array (
      0 => '4a9776614947857caced27d6efbe63e298123c46',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\infrastructure\\catalog\\rolecatalogtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\infrastructure\\catalog\\testsuperadminincludeswildcard',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Infrastructure/Console/AssignRoleCommandTest.php' => 
    array (
      0 => '0bd7bf3f4192bfa2a92e86a1e9b4e80c1a1ed902',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\infrastructure\\console\\assignrolecommandtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\infrastructure\\console\\testexecutefailswhenusernotfound',
        1 => 'tests\\unit\\authorization\\infrastructure\\console\\testexecutefailswhenrolenotfoundlistsavailableroles',
        2 => 'tests\\unit\\authorization\\infrastructure\\console\\testexecuteassignsroletouser',
        3 => 'tests\\unit\\authorization\\infrastructure\\console\\testexecutewarnswhenuseralreadyhasrole',
        4 => 'tests\\unit\\authorization\\infrastructure\\console\\testexecuteremovesrolefromuser',
        5 => 'tests\\unit\\authorization\\infrastructure\\console\\testexecutewarnswhenremovingmissingrole',
        6 => 'tests\\unit\\authorization\\infrastructure\\console\\testexecutefailswhenargumentsnotstrings',
        7 => 'tests\\unit\\authorization\\infrastructure\\console\\testexecutefailswhenexceptionthrown',
        8 => 'tests\\unit\\authorization\\infrastructure\\console\\createuser',
        9 => 'tests\\unit\\authorization\\infrastructure\\console\\createassignment',
        10 => 'tests\\unit\\authorization\\infrastructure\\console\\createrole',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Infrastructure/Console/SyncPermissionsCommandTest.php' => 
    array (
      0 => 'e7e4cb857ca28b2996c48b6c6bd5cd7c07874b53',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\infrastructure\\console\\syncpermissionscommandtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\infrastructure\\console\\testdryrunreportscountswithoutwriting',
        1 => 'tests\\unit\\authorization\\infrastructure\\console\\testupdaterolespersistschanges',
        2 => 'tests\\unit\\authorization\\infrastructure\\console\\testupdaterolesreportssystemrolecreationsindryrun',
        3 => 'tests\\unit\\authorization\\infrastructure\\console\\createrole',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Infrastructure/DataFixtures/AuthorizationFixturesTest.php' => 
    array (
      0 => 'c35efbb5d75228c73c16fe0bc359dfb6a65c6799',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\infrastructure\\datafixtures\\authorizationfixturestest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\infrastructure\\datafixtures\\testgetgroupsreturnsauthorizationgroup',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Infrastructure/Persistence/Doctrine/Mapper/PermissionMapperTest.php' => 
    array (
      0 => '68858bea66ff2e9595e20d775853cf8d3861a6e9',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\infrastructure\\persistence\\doctrine\\mapper\\permissionmappertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\infrastructure\\persistence\\doctrine\\mapper\\testtodomainmapsrecord',
        1 => 'tests\\unit\\authorization\\infrastructure\\persistence\\doctrine\\mapper\\testtorecordmapspermission',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Infrastructure/Persistence/Doctrine/Mapper/RoleAssignmentMapperTest.php' => 
    array (
      0 => '389147014b0c7ce2342551d47d241b315ecd5591',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\infrastructure\\persistence\\doctrine\\mapper\\roleassignmentmappertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\infrastructure\\persistence\\doctrine\\mapper\\testtodomainmapsrecord',
        1 => 'tests\\unit\\authorization\\infrastructure\\persistence\\doctrine\\mapper\\testtorecordmapsassignment',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Infrastructure/Persistence/Doctrine/Mapper/RoleMapperTest.php' => 
    array (
      0 => 'de9b6bf9cd5422c4e2c3598d55d5903f3ce0bb2a',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\infrastructure\\persistence\\doctrine\\mapper\\rolemappertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\infrastructure\\persistence\\doctrine\\mapper\\testtodomainmapsrecord',
        1 => 'tests\\unit\\authorization\\infrastructure\\persistence\\doctrine\\mapper\\testtorecordmapsrole',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Infrastructure/Persistence/Doctrine/Record/RoleRecordTest.php' => 
    array (
      0 => '2c0da06706e53ac68a814e993a4828cee260cea5',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\infrastructure\\persistence\\doctrine\\record\\rolerecordtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\infrastructure\\persistence\\doctrine\\record\\testconstructorinitializespermissionscollection',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Infrastructure/Persistence/Doctrine/Repository/PermissionRepositoryTest.php' => 
    array (
      0 => '2883309f20bd7cfb8a1b15566c74764a7c00fbfb',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\infrastructure\\persistence\\doctrine\\repository\\permissionrepositorytest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\infrastructure\\persistence\\doctrine\\repository\\testfindbyidreturnsnullwhenmissing',
        1 => 'tests\\unit\\authorization\\infrastructure\\persistence\\doctrine\\repository\\testfindbynamereturnspermission',
        2 => 'tests\\unit\\authorization\\infrastructure\\persistence\\doctrine\\repository\\testfindbynamereturnsnullwhenmissing',
        3 => 'tests\\unit\\authorization\\infrastructure\\persistence\\doctrine\\repository\\testfindallreturnspermissions',
        4 => 'tests\\unit\\authorization\\infrastructure\\persistence\\doctrine\\repository\\testsavepersistsrecord',
        5 => 'tests\\unit\\authorization\\infrastructure\\persistence\\doctrine\\repository\\testdeleteremovesrecord',
        6 => 'tests\\unit\\authorization\\infrastructure\\persistence\\doctrine\\repository\\createrecord',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Infrastructure/Persistence/Doctrine/Repository/RoleAssignmentRepositoryTest.php' => 
    array (
      0 => '25f6af2f232afc59f06ae69761b8804ee8258810',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\infrastructure\\persistence\\doctrine\\repository\\roleassignmentrepositorytest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\infrastructure\\persistence\\doctrine\\repository\\testfindbysubjectreturnsemptywhenqueryresultnotarray',
        1 => 'tests\\unit\\authorization\\infrastructure\\persistence\\doctrine\\repository\\testsaveinvalidatesuserauthorizationcache',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Infrastructure/Persistence/Doctrine/Repository/RoleRepositoryTest.php' => 
    array (
      0 => '9fb0af9ba50929a9097dec6b428e97ef5e8c1174',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\infrastructure\\persistence\\doctrine\\repository\\rolerepositorytest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\infrastructure\\persistence\\doctrine\\repository\\testfindbyidreturnsrole',
        1 => 'tests\\unit\\authorization\\infrastructure\\persistence\\doctrine\\repository\\testfindallfiltersbytenant',
        2 => 'tests\\unit\\authorization\\infrastructure\\persistence\\doctrine\\repository\\testsavepersistsroleandpermissions',
        3 => 'tests\\unit\\authorization\\infrastructure\\persistence\\doctrine\\repository\\testdeleteremovesrole',
        4 => 'tests\\unit\\authorization\\infrastructure\\persistence\\doctrine\\repository\\createrolerecord',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Infrastructure/Security/Voter/PermissionVoterTest.php' => 
    array (
      0 => '4c148a7c13f37ac4f302b8bc229a85cd94de55da',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\infrastructure\\security\\voter\\permissionvotertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\infrastructure\\security\\voter\\testvotegrantswhenpermissionmatches',
        1 => 'tests\\unit\\authorization\\infrastructure\\security\\voter\\testvoteabstainsforunsupportedattribute',
        2 => 'tests\\unit\\authorization\\infrastructure\\security\\voter\\testvotedenieswhenuserisnotsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Infrastructure/Security/Voter/RoleVoterTest.php' => 
    array (
      0 => '0a004072f8acb2ca6733d86e62151ac6e4f087c2',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\infrastructure\\security\\voter\\rolevotertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\infrastructure\\security\\voter\\testvotegrantswhenrolematches',
        1 => 'tests\\unit\\authorization\\infrastructure\\security\\voter\\testvoteabstainsforunsupportedattribute',
        2 => 'tests\\unit\\authorization\\infrastructure\\security\\voter\\testvotedenieswhenuserisnotsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Presentation/Api/Processor/AddPermissionToRoleProcessorTest.php' => 
    array (
      0 => '0ada73c82faddfcf21532a0f932b801b5f11cd66',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\presentation\\api\\processor\\addpermissiontoroleprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\presentation\\api\\processor\\setup',
        1 => 'tests\\unit\\authorization\\presentation\\api\\processor\\testaddpermissiontorolesuccessfully',
        2 => 'tests\\unit\\authorization\\presentation\\api\\processor\\testaddpermissiontononexistentrolethrowsexception',
        3 => 'tests\\unit\\authorization\\presentation\\api\\processor\\testaddnonexistentpermissionthrowsexception',
        4 => 'tests\\unit\\authorization\\presentation\\api\\processor\\testprocessthrowswhenroleidmissing',
        5 => 'tests\\unit\\authorization\\presentation\\api\\processor\\testprocessthrowswhenpermissionidmissing',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Presentation/Api/Processor/Permission/CreatePermissionProcessorTest.php' => 
    array (
      0 => '988632d0548edef0963e6853c4dbe70007c57260',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\presentation\\api\\processor\\permission\\createpermissionprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\presentation\\api\\processor\\permission\\setup',
        1 => 'tests\\unit\\authorization\\presentation\\api\\processor\\permission\\testprocesscreatespermission',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Presentation/Api/Processor/Permission/DeletePermissionProcessorTest.php' => 
    array (
      0 => '25215bf0b78baf68404b4921e4b1d3d301ecaf08',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\presentation\\api\\processor\\permission\\deletepermissionprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\presentation\\api\\processor\\permission\\setup',
        1 => 'tests\\unit\\authorization\\presentation\\api\\processor\\permission\\testprocessthrowswhenidmissing',
        2 => 'tests\\unit\\authorization\\presentation\\api\\processor\\permission\\testprocessdispatchescommand',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Presentation/Api/Processor/Permission/UpdatePermissionProcessorTest.php' => 
    array (
      0 => 'f055a500cc1b3840ffc850ad37b02b88ff548570',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\presentation\\api\\processor\\permission\\updatepermissionprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\presentation\\api\\processor\\permission\\setup',
        1 => 'tests\\unit\\authorization\\presentation\\api\\processor\\permission\\testprocessthrowswhenidmissing',
        2 => 'tests\\unit\\authorization\\presentation\\api\\processor\\permission\\testprocessupdatespermission',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Presentation/Api/Processor/RemovePermissionFromRoleProcessorTest.php' => 
    array (
      0 => 'a03572b5f8868c546688953821e4c31513d320a8',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\presentation\\api\\processor\\removepermissionfromroleprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\presentation\\api\\processor\\setup',
        1 => 'tests\\unit\\authorization\\presentation\\api\\processor\\testremovepermissionfromrolesuccessfully',
        2 => 'tests\\unit\\authorization\\presentation\\api\\processor\\testremovepermissionfromnonexistentrolethrowsexception',
        3 => 'tests\\unit\\authorization\\presentation\\api\\processor\\testremovenonexistentpermissionthrowsexception',
        4 => 'tests\\unit\\authorization\\presentation\\api\\processor\\testprocessthrowswhenroleidisinvalid',
        5 => 'tests\\unit\\authorization\\presentation\\api\\processor\\testprocessthrowswhenpermissionidisinvalid',
        6 => 'tests\\unit\\authorization\\presentation\\api\\processor\\testprocessmapspermissionoutputs',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Presentation/Api/Processor/Role/CreateRoleProcessorTest.php' => 
    array (
      0 => 'd678cd72ab5dd4617f6973583f20269134200cbc',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\presentation\\api\\processor\\role\\createroleprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\presentation\\api\\processor\\role\\setup',
        1 => 'tests\\unit\\authorization\\presentation\\api\\processor\\role\\testprocesscreatesrole',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Presentation/Api/Processor/Role/DeleteRoleProcessorTest.php' => 
    array (
      0 => '4247afbfb945bb93fa3d1f23ad72ca2a2de26886',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\presentation\\api\\processor\\role\\deleteroleprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\presentation\\api\\processor\\role\\setup',
        1 => 'tests\\unit\\authorization\\presentation\\api\\processor\\role\\testprocessthrowswhenidmissing',
        2 => 'tests\\unit\\authorization\\presentation\\api\\processor\\role\\testprocessdispatchescommand',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Presentation/Api/Processor/Role/UpdateRoleProcessorTest.php' => 
    array (
      0 => '1e6001c80379f78a0a311d5d14090fa36aee320e',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\presentation\\api\\processor\\role\\updateroleprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\presentation\\api\\processor\\role\\setup',
        1 => 'tests\\unit\\authorization\\presentation\\api\\processor\\role\\testprocessthrowswhenidmissing',
        2 => 'tests\\unit\\authorization\\presentation\\api\\processor\\role\\testprocessupdatesrole',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Presentation/Api/Provider/Permission/GetPermissionProviderTest.php' => 
    array (
      0 => '679ab44dd9f1114bb9359148e59952e78a871efd',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\presentation\\api\\provider\\permission\\getpermissionprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\presentation\\api\\provider\\permission\\testprovidereturnsnullwhenidmissing',
        1 => 'tests\\unit\\authorization\\presentation\\api\\provider\\permission\\testprovidereturnsnullwhenmissing',
        2 => 'tests\\unit\\authorization\\presentation\\api\\provider\\permission\\testprovidemapspermissionoutput',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Presentation/Api/Provider/Permission/ListPermissionsProviderTest.php' => 
    array (
      0 => 'a3176fba0a1bc5f37987fcd372c1ab543ffef2e4',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\presentation\\api\\provider\\permission\\listpermissionsprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\presentation\\api\\provider\\permission\\testprovidemapspermissions',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Presentation/Api/Provider/Role/GetRoleProviderTest.php' => 
    array (
      0 => '32ef3baf3f70fee524b11940467fc2b578e20c7d',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\presentation\\api\\provider\\role\\getroleprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\presentation\\api\\provider\\role\\testprovidereturnsnullwhenidmissing',
        1 => 'tests\\unit\\authorization\\presentation\\api\\provider\\role\\testprovidereturnsnullwhenrolemissing',
        2 => 'tests\\unit\\authorization\\presentation\\api\\provider\\role\\testprovidemapsroleoutput',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Presentation/Api/Provider/Role/ListRolesProviderTest.php' => 
    array (
      0 => 'f8f55fc58b181d4cdc9b643d08de80ed93037d1d',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\presentation\\api\\provider\\role\\listrolesprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\presentation\\api\\provider\\role\\testprovidemapsroles',
        1 => 'tests\\unit\\authorization\\presentation\\api\\provider\\role\\testprovidepassesissystemfiltertoquery',
        2 => 'tests\\unit\\authorization\\presentation\\api\\provider\\role\\testprovidewithnoissystemfilterpassesnull',
        3 => 'tests\\unit\\authorization\\presentation\\api\\provider\\role\\testprovidethrowsbadrequestwhenissystemfilterisinvalid',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Authorization/Presentation/Api/Resource/AuthorizationResourcesTest.php' => 
    array (
      0 => '987d8945384db338bed5464811f985b3f1d265b0',
      1 => 
      array (
        0 => 'tests\\unit\\authorization\\presentation\\api\\resource\\authorizationresourcestest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\authorization\\presentation\\api\\resource\\testresourcescanbeinstantiated',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Equipment/Application/UseCase/Command/Equipment/AddAttachment/AddAttachmentHandlerTest.php' => 
    array (
      0 => 'e1902825108d18458d70d28a362959c66f6180af',
      1 => 
      array (
        0 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\addattachment\\addattachmenthandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\addattachment\\testinvokestoresattachmentsuccessfully',
        1 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\addattachment\\testinvokethrowswhenequipmentnotfound',
        2 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\addattachment\\testinvokedoesnotsaverecordwhenstoragewritefails',
        3 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\addattachment\\testinvokedeletesfilewhendatabasesavefails',
        4 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\addattachment\\testinvokeappliesbasenametopreventpathtraversal',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Equipment/Application/UseCase/Command/Equipment/AddTagToEquipment/AddTagToEquipmentHandlerTest.php' => 
    array (
      0 => '2e0910524185f1fde286693106b01739e672dc26',
      1 => 
      array (
        0 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\addtagtoequipment\\addtagtoequipmenthandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\addtagtoequipment\\testinvokecreatesandlinksnewtag',
        1 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\addtagtoequipment\\testinvokelinksexistingtag',
        2 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\addtagtoequipment\\testinvokethrowswhenequipmentnotfound',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Equipment/Application/UseCase/Command/Equipment/AssignToFacility/AssignToFacilityHandlerTest.php' => 
    array (
      0 => 'cd5ae8c6e572d67e79d3c6300ec48a161210da91',
      1 => 
      array (
        0 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\assigntofacility\\assigntofacilityhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\assigntofacility\\testinvokeassignsequipmenttofacility',
        1 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\assigntofacility\\testinvokethrowswhenequipmentnotfound',
        2 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\assigntofacility\\testinvokethrowswhenequipmentbelongstoanotherorganization',
        3 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\assigntofacility\\testinvokethrowswhenfacilitynotfound',
        4 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\assigntofacility\\testinvokethrowswhenfacilityisarchived',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Equipment/Application/UseCase/Command/Equipment/CommissionEquipment/CommissionEquipmentHandlerTest.php' => 
    array (
      0 => '5854fe6451e8c2effa5f278aa36b3d1a7f99c29d',
      1 => 
      array (
        0 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\commissionequipment\\commissionequipmenthandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\commissionequipment\\testinvokecommissionsequipment',
        1 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\commissionequipment\\testinvokethrowswhenequipmentnotfound',
        2 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\commissionequipment\\testinvokethrowswhenequipmentbelongstoanotherorganization',
        3 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\commissionequipment\\testinvokethrowswhenequipmentisdecommissioned',
        4 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\commissionequipment\\testinvokethrowswhennofacilityassigned',
        5 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\commissionequipment\\testinvokeclosesmaintenancelogwhencommissioningfromundermaintenance',
        6 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\commissionequipment\\testinvokedoesnottouchmaintenancelogwhennotundermaintenance',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Equipment/Application/UseCase/Command/Equipment/CreateEquipment/CreateEquipmentHandlerTest.php' => 
    array (
      0 => 'c1fa6d934d3118051078f40af648142e41d3f354',
      1 => 
      array (
        0 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\createequipment\\createequipmenthandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\createequipment\\testinvokethrowsinvalidargumentoninvalidtype',
        1 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\createequipment\\testinvokethrowsserialnumberalreadyexistsonuniqueconstraintviolation',
        2 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\createequipment\\testinvokereturnsresultonsuccess',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Equipment/Application/UseCase/Command/Equipment/DecommissionEquipment/DecommissionEquipmentHandlerTest.php' => 
    array (
      0 => '47362d784d251ed536ab49d17ce18c76ed89129d',
      1 => 
      array (
        0 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\decommissionequipment\\decommissionequipmenthandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\decommissionequipment\\testinvokedecommissionsequipment',
        1 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\decommissionequipment\\testinvokethrowswhenequipmentnotfound',
        2 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\decommissionequipment\\testinvokethrowswhenequipmentbelongstoanotherorganization',
        3 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\decommissionequipment\\testinvokethrowswhenalreadydecommissioned',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Equipment/Application/UseCase/Command/Equipment/DeleteAttachment/DeleteAttachmentHandlerTest.php' => 
    array (
      0 => 'b6603f0104abaf3f3260d4213b83b2b02f86c70a',
      1 => 
      array (
        0 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\deleteattachment\\deleteattachmenthandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\deleteattachment\\testinvokedeletesattachment',
        1 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\deleteattachment\\testinvokethrowswhenequipmentnotfound',
        2 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\deleteattachment\\testinvokethrowswhenattachmentnotfound',
        3 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\deleteattachment\\testinvokethrowswhenattachmentbelongstoanotherequipment',
        4 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\deleteattachment\\buildattachment',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Equipment/Application/UseCase/Command/Equipment/PutUnderMaintenance/PutUnderMaintenanceHandlerTest.php' => 
    array (
      0 => '2dca1c989c474133de0fa9f39addea63fef2b64d',
      1 => 
      array (
        0 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\putundermaintenance\\putundermaintenancehandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\putundermaintenance\\testinvokeputsequipmentundermaintenance',
        1 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\putundermaintenance\\testinvokethrowswhenequipmentnotfound',
        2 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\putundermaintenance\\testinvokethrowswhenequipmentisdecommissioned',
        3 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\putundermaintenance\\testinvokethrowswhennofacilityassigned',
        4 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\putundermaintenance\\testinvokedoesnotcreatenewlogwhenalreadyundermaintenance',
        5 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\putundermaintenance\\testinvokereturnsresultwhennotificationdispatchfails',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Equipment/Application/UseCase/Command/Equipment/RemoveTagFromEquipment/RemoveTagFromEquipmentHandlerTest.php' => 
    array (
      0 => 'd942c2e3f79b803f3c7866ae60208352c5747dd9',
      1 => 
      array (
        0 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\removetagfromequipment\\removetagfromequipmenthandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\removetagfromequipment\\testinvokeremovestagfromequipment',
        1 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\removetagfromequipment\\testinvokethrowswhenequipmentnotfound',
        2 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\removetagfromequipment\\testinvokethrowswhentagnotlinkedtoequipment',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Equipment/Application/UseCase/Command/Equipment/UnassignFromFacility/UnassignFromFacilityHandlerTest.php' => 
    array (
      0 => '3be380fc3ef6ced48f8a7a6b6a87bbcf56f6f814',
      1 => 
      array (
        0 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\unassignfromfacility\\unassignfromfacilityhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\unassignfromfacility\\testinvokeunassignsequipmentfromfacility',
        1 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\unassignfromfacility\\testinvokethrowswhenequipmentnotfound',
        2 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\unassignfromfacility\\testinvokethrowswhenequipmentbelongstoanotherorganization',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Equipment/Application/UseCase/Command/Equipment/UpdateEquipment/UpdateEquipmentHandlerTest.php' => 
    array (
      0 => '0488fd9ace77d29e771814243fc7ac970002b621',
      1 => 
      array (
        0 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\updateequipment\\updateequipmenthandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\updateequipment\\testinvokethrowsnotfoundwhenequipmentmissing',
        1 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\updateequipment\\testinvokethrowsserialnumberalreadyexistsonuniqueconstraintviolation',
        2 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\updateequipment\\testinvokereturnsresultonsuccess',
        3 => 'tests\\unit\\equipment\\application\\usecase\\command\\equipment\\updateequipment\\testinvokethrowsinvalidargumentexceptiononinvalidtype',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Equipment/Application/UseCase/Query/Equipment/GetEquipment/GetEquipmentHandlerTest.php' => 
    array (
      0 => '438cbe98effd14a3ffa62984ac074312ac06f68e',
      1 => 
      array (
        0 => 'tests\\unit\\equipment\\application\\usecase\\query\\equipment\\getequipment\\getequipmenthandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\equipment\\application\\usecase\\query\\equipment\\getequipment\\testinvokethrowsinvalidargumentoninvalidequipmentid',
        1 => 'tests\\unit\\equipment\\application\\usecase\\query\\equipment\\getequipment\\testinvokethrowsequipmentnotfoundwhenrepositoryreturnsnull',
        2 => 'tests\\unit\\equipment\\application\\usecase\\query\\equipment\\getequipment\\testinvokethrowsequipmentnotfoundwhenorganizationmismatch',
        3 => 'tests\\unit\\equipment\\application\\usecase\\query\\equipment\\getequipment\\testinvokereturnsresultonsuccess',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Equipment/Application/UseCase/Query/Equipment/ListEquipmentAttachments/ListEquipmentAttachmentsHandlerTest.php' => 
    array (
      0 => '41a6321082595739ec8e8847859f405688ba7912',
      1 => 
      array (
        0 => 'tests\\unit\\equipment\\application\\usecase\\query\\equipment\\listequipmentattachments\\listequipmentattachmentshandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\equipment\\application\\usecase\\query\\equipment\\listequipmentattachments\\testinvokethrowsinvalidargumentoninvalidequipmentid',
        1 => 'tests\\unit\\equipment\\application\\usecase\\query\\equipment\\listequipmentattachments\\testinvokethrowsequipmentnotfoundwhenrepositoryreturnsnull',
        2 => 'tests\\unit\\equipment\\application\\usecase\\query\\equipment\\listequipmentattachments\\testinvokethrowsequipmentnotfoundwhenorganizationmismatch',
        3 => 'tests\\unit\\equipment\\application\\usecase\\query\\equipment\\listequipmentattachments\\testinvokereturnsemptyresultwhennoattachments',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Equipment/Application/UseCase/Query/Equipment/ListEquipments/ListEquipmentsHandlerTest.php' => 
    array (
      0 => '1573ff18aa57eda7a8ba41963489f60d0fb050ac',
      1 => 
      array (
        0 => 'tests\\unit\\equipment\\application\\usecase\\query\\equipment\\listequipments\\listequipmentshandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\equipment\\application\\usecase\\query\\equipment\\listequipments\\testinvokethrowsinvalidargumentoninvalidorganizationid',
        1 => 'tests\\unit\\equipment\\application\\usecase\\query\\equipment\\listequipments\\testinvokethrowsinvalidargumentoninvalidtype',
        2 => 'tests\\unit\\equipment\\application\\usecase\\query\\equipment\\listequipments\\testinvokereturnsemptypaginatedresultwhennoequipments',
        3 => 'tests\\unit\\equipment\\application\\usecase\\query\\equipment\\listequipments\\testinvokereturnspaginatedresultwithitems',
        4 => 'tests\\unit\\equipment\\application\\usecase\\query\\equipment\\listequipments\\testinvokepassespaginationtorepository',
        5 => 'tests\\unit\\equipment\\application\\usecase\\query\\equipment\\listequipments\\testinvokepassessearchandsortingtorepository',
        6 => 'tests\\unit\\equipment\\application\\usecase\\query\\equipment\\listequipments\\testinvokepassesbrandmodelandsubtypefilterstorepository',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Equipment/Application/UseCase/Query/Equipment/ListEquipmentTypes/ListEquipmentTypesHandlerTest.php' => 
    array (
      0 => '1e424a481f61a2af6f7871b7a9f2ef1e2367d9cb',
      1 => 
      array (
        0 => 'tests\\unit\\equipment\\application\\usecase\\query\\equipment\\listequipmenttypes\\listequipmenttypeshandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\equipment\\application\\usecase\\query\\equipment\\listequipmenttypes\\testinvokereturnsallequipmenttypes',
        1 => 'tests\\unit\\equipment\\application\\usecase\\query\\equipment\\listequipmenttypes\\testinvokereturnscorrectvaluesandlabels',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Equipment/Application/UseCase/Query/Equipment/ListMaintenanceLogs/ListMaintenanceLogsHandlerTest.php' => 
    array (
      0 => '656aa1adde55934c706e46549b93364e8cbed7bf',
      1 => 
      array (
        0 => 'tests\\unit\\equipment\\application\\usecase\\query\\equipment\\listmaintenancelogs\\listmaintenancelogshandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\equipment\\application\\usecase\\query\\equipment\\listmaintenancelogs\\testinvokethrowsinvalidargumentoninvalidequipmentid',
        1 => 'tests\\unit\\equipment\\application\\usecase\\query\\equipment\\listmaintenancelogs\\testinvokethrowsinvalidargumentoninvalidorganizationid',
        2 => 'tests\\unit\\equipment\\application\\usecase\\query\\equipment\\listmaintenancelogs\\testinvokethrowsequipmentnotfoundwhenrepositoryreturnsnull',
        3 => 'tests\\unit\\equipment\\application\\usecase\\query\\equipment\\listmaintenancelogs\\testinvokethrowsequipmentnotfoundwhenorganizationmismatch',
        4 => 'tests\\unit\\equipment\\application\\usecase\\query\\equipment\\listmaintenancelogs\\testinvokereturnsemptyresultwhennologs',
        5 => 'tests\\unit\\equipment\\application\\usecase\\query\\equipment\\listmaintenancelogs\\testinvokereturnslogsmappedtoarrays',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Equipment/Application/UseCase/Query/Tag/ListTags/ListTagsHandlerTest.php' => 
    array (
      0 => 'f10b0ca80dff97a1b4fe3661a9efca758ea675a8',
      1 => 
      array (
        0 => 'tests\\unit\\equipment\\application\\usecase\\query\\tag\\listtags\\listtagshandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\equipment\\application\\usecase\\query\\tag\\listtags\\testinvokethrowsinvalidargumentoninvalidorganizationid',
        1 => 'tests\\unit\\equipment\\application\\usecase\\query\\tag\\listtags\\testinvokereturnsemptyresultwhennotags',
        2 => 'tests\\unit\\equipment\\application\\usecase\\query\\tag\\listtags\\testinvokereturnstagsmappedtoarrays',
        3 => 'tests\\unit\\equipment\\application\\usecase\\query\\tag\\listtags\\testinvokeappliespaginationslice',
        4 => 'tests\\unit\\equipment\\application\\usecase\\query\\tag\\listtags\\testinvokeforwardssearchtorepository',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Equipment/Infrastructure/Adapter/Organization/EquipmentStatisticsAdapterTest.php' => 
    array (
      0 => 'f03dc477faa5f7b8ac3c752a4b1c4d299244b94d',
      1 => 
      array (
        0 => 'tests\\unit\\equipment\\infrastructure\\adapter\\organization\\equipmentstatisticsadaptertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\equipment\\infrastructure\\adapter\\organization\\testcountequipmentoverviewnormalizesmissingstatusestozero',
        1 => 'tests\\unit\\equipment\\infrastructure\\adapter\\organization\\testcountequipmentbystatusnormalizesmissingstatusestozero',
        2 => 'tests\\unit\\equipment\\infrastructure\\adapter\\organization\\testcountequipmentbytypenormalizesmissingtypestozero',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Equipment/Presentation/Api/Processor/Equipment/AddAttachmentProcessorTest.php' => 
    array (
      0 => '1dbebe357dbb5d3b6d85e1ba7b9887ba8fcee22e',
      1 => 
      array (
        0 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\addattachmentprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\testprocessthrowsaccessdeniedwhenpermissionmissing',
        1 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\testprocessthrowsbadrequestwhencontentisnotvalidbase64',
        2 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\testprocessmapswrappednotfoundtohttp404',
        3 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\testprocessreturnsattachmentoutputonsuccess',
        4 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Equipment/Presentation/Api/Processor/Equipment/AddTagToEquipmentProcessorTest.php' => 
    array (
      0 => '670e22311eaaf3177d688f6b73d6a797469da550',
      1 => 
      array (
        0 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\addtagtoequipmentprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\testprocessthrowsaccessdeniedwhenpermissionmissing',
        1 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\testprocessmapswrappednotfoundtohttp404',
        2 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\testprocessreturnstagoutputonsuccess',
        3 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Equipment/Presentation/Api/Processor/Equipment/AssignToFacilityProcessorTest.php' => 
    array (
      0 => '6de72c8945de4b689d26582ae2d202d6805fe877',
      1 => 
      array (
        0 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\assigntofacilityprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\testprocessmapswrappedequipmentnotfoundtohttp404',
        1 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\testprocessreturnsequipmentoutputonsuccess',
        2 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\testprocessthrowsaccessdeniedwhenpermissionmissing',
        3 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Equipment/Presentation/Api/Processor/Equipment/CanonicalEquipmentMutationProcessorTest.php' => 
    array (
      0 => '10f0944754aa6734915159f6bc059473e16de3a6',
      1 => 
      array (
        0 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\canonicalequipmentmutationprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\testdeletingpublishedequipmentdecommissionsit',
        1 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\testmergepatchexplicitnullclearsnullablefield',
        2 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\processor',
        3 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\entitymanager',
        4 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\record',
        5 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\request',
        6 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\user',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Equipment/Presentation/Api/Processor/Equipment/CommissionEquipmentProcessorTest.php' => 
    array (
      0 => '8953a53f271104e5906f7d44df0177d52765b2fe',
      1 => 
      array (
        0 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\commissionequipmentprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\testprocessthrowsaccessdeniedwhenpermissionmissing',
        1 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\testprocessmapswrappednotfoundtohttp404',
        2 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\testprocessmapswrappedalreadydecommissionedtohttp409',
        3 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\testprocessreturnscommissionedequipmentonsuccess',
        4 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Equipment/Presentation/Api/Processor/Equipment/CreateEquipmentProcessorTest.php' => 
    array (
      0 => '8a05322a708dc447fe77d682909905e89e6d0c64',
      1 => 
      array (
        0 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\createequipmentprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\testprocessmapswrappedserialnumberconflicttohttp409',
        1 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\testprocessreturnsequipmentoutputonsuccess',
        2 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Equipment/Presentation/Api/Processor/Equipment/DecommissionEquipmentProcessorTest.php' => 
    array (
      0 => '0ae02602c1df0527bdf1dbf7976b9882e5df87a6',
      1 => 
      array (
        0 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\decommissionequipmentprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\testprocessthrowsaccessdeniedwhenpermissionmissing',
        1 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\testprocessmapswrappednotfoundtohttp404',
        2 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\testprocessmapswrappedalreadydecommissionedtohttp409',
        3 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\testprocessreturnsdecommissionedequipmentonsuccess',
        4 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Equipment/Presentation/Api/Processor/Equipment/DeleteAttachmentProcessorTest.php' => 
    array (
      0 => '51cd12188638514ee75612408b4c730a3a8898b0',
      1 => 
      array (
        0 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\deleteattachmentprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\testprocessthrowsaccessdeniedwhenpermissionmissing',
        1 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\testprocessmapswrappedequipmentnotfoundtohttp404',
        2 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\testprocessmapswrappedattachmentnotfoundtohttp404',
        3 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\testprocessreturnsnullonsuccess',
        4 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Equipment/Presentation/Api/Processor/Equipment/PutUnderMaintenanceProcessorTest.php' => 
    array (
      0 => '13403fe07e16ee9ce666926fc83d47fe2b3da2fa',
      1 => 
      array (
        0 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\putundermaintenanceprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\testprocessthrowsaccessdeniedwhenpermissionmissing',
        1 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\testprocessmapswrappednotfoundtohttp404',
        2 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\testprocessmapswrappedalreadydecommissionedtohttp409',
        3 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\testprocessreturnsequipmentoutputonsuccess',
        4 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Equipment/Presentation/Api/Processor/Equipment/RemoveTagFromEquipmentProcessorTest.php' => 
    array (
      0 => 'fdbbe7682585dd795d82e87363e3c924554e6df7',
      1 => 
      array (
        0 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\removetagfromequipmentprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\testprocessthrowsaccessdeniedwhenpermissionmissing',
        1 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\testprocessmapswrappedequipmentnotfoundtohttp404',
        2 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\testprocessmapswrappedtagnotfoundtohttp404',
        3 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\testprocessreturnsnullonsuccess',
        4 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Equipment/Presentation/Api/Processor/Equipment/UnassignFromFacilityProcessorTest.php' => 
    array (
      0 => 'bbcfac57fdd42ac21e9965dec6bab83e120eeb89',
      1 => 
      array (
        0 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\unassignfromfacilityprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\testprocessthrowsaccessdeniedwhenpermissionmissing',
        1 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\testprocessmapswrappednotfoundtohttp404',
        2 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\testprocessreturnsequipmentoutputonsuccess',
        3 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Equipment/Presentation/Api/Processor/Equipment/UpdateEquipmentProcessorTest.php' => 
    array (
      0 => '4599c93c78e2e7cbfa8f55d783f253b187ac4f82',
      1 => 
      array (
        0 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\updateequipmentprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\testprocessthrowsaccessdeniedwhenpermissionmissing',
        1 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\testprocessmapswrappednotfoundtohttp404',
        2 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\testprocessmapswrappedserialnumberconflicttohttp409',
        3 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\testprocessreturnsequipmentoutputonsuccess',
        4 => 'tests\\unit\\equipment\\presentation\\api\\processor\\equipment\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Equipment/Presentation/Api/Processor/Media/MediaProcessorTest.php' => 
    array (
      0 => '902f4b369cd7c1df3bddef3126ee04bcefbb82d3',
      1 => 
      array (
        0 => 'tests\\unit\\equipment\\presentation\\api\\processor\\media\\mediaprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\equipment\\presentation\\api\\processor\\media\\testdeletingmediafrompublishedequipmentusesoperationalpermission',
        1 => 'tests\\unit\\equipment\\presentation\\api\\processor\\media\\testinterventioncontextauthorizesevidenceforpublishedequipment',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Equipment/Presentation/Api/Provider/Equipment/GetEquipmentProviderTest.php' => 
    array (
      0 => '760e38712b347b656dbf1969dbf4d2e671c259a7',
      1 => 
      array (
        0 => 'tests\\unit\\equipment\\presentation\\api\\provider\\equipment\\getequipmentprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\equipment\\presentation\\api\\provider\\equipment\\testprovidemapswrappednotfoundtohttp404',
        1 => 'tests\\unit\\equipment\\presentation\\api\\provider\\equipment\\testprovidemapsresulttooutput',
        2 => 'tests\\unit\\equipment\\presentation\\api\\provider\\equipment\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Equipment/Presentation/Api/Provider/Equipment/ListEquipmentAttachmentsProviderTest.php' => 
    array (
      0 => 'a0a47a91bfff93660027fcac3ff817cd84f818fb',
      1 => 
      array (
        0 => 'tests\\unit\\equipment\\presentation\\api\\provider\\equipment\\listequipmentattachmentsprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\equipment\\presentation\\api\\provider\\equipment\\testprovidethrowsaccessdeniedwhennotauthenticated',
        1 => 'tests\\unit\\equipment\\presentation\\api\\provider\\equipment\\testprovidethrowsaccessdeniedwhenpermissionmissing',
        2 => 'tests\\unit\\equipment\\presentation\\api\\provider\\equipment\\testprovidemapswrappedequipmentnotfoundtohttp404',
        3 => 'tests\\unit\\equipment\\presentation\\api\\provider\\equipment\\testprovidereturnsemptylistwhennoattachments',
        4 => 'tests\\unit\\equipment\\presentation\\api\\provider\\equipment\\testprovidemapsresulttoattachmentoutputlist',
        5 => 'tests\\unit\\equipment\\presentation\\api\\provider\\equipment\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Equipment/Presentation/Api/Provider/Equipment/ListEquipmentsProviderTest.php' => 
    array (
      0 => '97e79c3de9a6fbc54b2406c6c568a502b3eb5444',
      1 => 
      array (
        0 => 'tests\\unit\\equipment\\presentation\\api\\provider\\equipment\\listequipmentsprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\equipment\\presentation\\api\\provider\\equipment\\testprovidereturnsemptylistwhennoequipments',
        1 => 'tests\\unit\\equipment\\presentation\\api\\provider\\equipment\\testprovidereturnsequipmentoutputlist',
        2 => 'tests\\unit\\equipment\\presentation\\api\\provider\\equipment\\testprovidethrowswhennotauthenticated',
        3 => 'tests\\unit\\equipment\\presentation\\api\\provider\\equipment\\testprovidethrowswhenpermissiondenied',
        4 => 'tests\\unit\\equipment\\presentation\\api\\provider\\equipment\\testprovidethrowsbadrequestwhenorganizationidmissing',
        5 => 'tests\\unit\\equipment\\presentation\\api\\provider\\equipment\\testprovideexposestotalitemsinpaginator',
        6 => 'tests\\unit\\equipment\\presentation\\api\\provider\\equipment\\testprovidepassessearchandsortingtoquery',
        7 => 'tests\\unit\\equipment\\presentation\\api\\provider\\equipment\\testprovidepassesbrandmodelandsubtypefilterstoquery',
        8 => 'tests\\unit\\equipment\\presentation\\api\\provider\\equipment\\testprovideusesfacilityidfromurivariables',
        9 => 'tests\\unit\\equipment\\presentation\\api\\provider\\equipment\\createsecurityuser',
        10 => 'tests\\unit\\equipment\\presentation\\api\\provider\\equipment\\buildrequeststack',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Equipment/Presentation/Api/Provider/Equipment/ListEquipmentStatusesProviderTest.php' => 
    array (
      0 => '811dd148e69706c9cba32f9d527da63921159698',
      1 => 
      array (
        0 => 'tests\\unit\\equipment\\presentation\\api\\provider\\equipment\\listequipmentstatusesprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\equipment\\presentation\\api\\provider\\equipment\\testprovidethrowswhenorganizationidmissing',
        1 => 'tests\\unit\\equipment\\presentation\\api\\provider\\equipment\\testprovidethrowswhennotauthenticated',
        2 => 'tests\\unit\\equipment\\presentation\\api\\provider\\equipment\\testprovidethrowswhenpermissiondenied',
        3 => 'tests\\unit\\equipment\\presentation\\api\\provider\\equipment\\testprovidemapsequipmentstatuses',
        4 => 'tests\\unit\\equipment\\presentation\\api\\provider\\equipment\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Equipment/Presentation/Api/Provider/Equipment/ListEquipmentTypesProviderTest.php' => 
    array (
      0 => '1b973803143cfcf1cb4e2b1f45e14e7844baf291',
      1 => 
      array (
        0 => 'tests\\unit\\equipment\\presentation\\api\\provider\\equipment\\listequipmenttypesprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\equipment\\presentation\\api\\provider\\equipment\\testprovidethrowswhenorganizationidmissing',
        1 => 'tests\\unit\\equipment\\presentation\\api\\provider\\equipment\\testprovidethrowswhennotauthenticated',
        2 => 'tests\\unit\\equipment\\presentation\\api\\provider\\equipment\\testprovidethrowswhenpermissiondenied',
        3 => 'tests\\unit\\equipment\\presentation\\api\\provider\\equipment\\testprovidemapstypesresult',
        4 => 'tests\\unit\\equipment\\presentation\\api\\provider\\equipment\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Equipment/Presentation/Api/Provider/Equipment/ListMaintenanceLogsProviderTest.php' => 
    array (
      0 => 'c9e41071f6c84ef4acf5c7da152f132bcbd14c2a',
      1 => 
      array (
        0 => 'tests\\unit\\equipment\\presentation\\api\\provider\\equipment\\listmaintenancelogsprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\equipment\\presentation\\api\\provider\\equipment\\testprovidethrowsaccessdeniedwhennotauthenticated',
        1 => 'tests\\unit\\equipment\\presentation\\api\\provider\\equipment\\testprovidethrowsbadrequestwhenurivariablesmissing',
        2 => 'tests\\unit\\equipment\\presentation\\api\\provider\\equipment\\testprovidethrowsaccessdeniedwhenpermissionmissing',
        3 => 'tests\\unit\\equipment\\presentation\\api\\provider\\equipment\\testprovidemapswrappedequipmentnotfoundtohttp404',
        4 => 'tests\\unit\\equipment\\presentation\\api\\provider\\equipment\\testprovidereturnsemptypaginatorwhennologs',
        5 => 'tests\\unit\\equipment\\presentation\\api\\provider\\equipment\\testprovidemapsresulttomaintenancelogoutputpaginator',
        6 => 'tests\\unit\\equipment\\presentation\\api\\provider\\equipment\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Equipment/Presentation/Api/Provider/Tag/ListTagsProviderTest.php' => 
    array (
      0 => 'fa3c6d0a15bca957700fbdfde2b5747d92772bc8',
      1 => 
      array (
        0 => 'tests\\unit\\equipment\\presentation\\api\\provider\\tag\\listtagsprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\equipment\\presentation\\api\\provider\\tag\\testprovidethrowsaccessdeniedwhennotauthenticated',
        1 => 'tests\\unit\\equipment\\presentation\\api\\provider\\tag\\testprovidethrowsbadrequestwhenorganizationidmissing',
        2 => 'tests\\unit\\equipment\\presentation\\api\\provider\\tag\\testprovidethrowsaccessdeniedwhenpermissionmissing',
        3 => 'tests\\unit\\equipment\\presentation\\api\\provider\\tag\\testprovidemapswrappedinvalidargumenttobadrequest',
        4 => 'tests\\unit\\equipment\\presentation\\api\\provider\\tag\\testprovidereturnsemptypaginatorwhennotags',
        5 => 'tests\\unit\\equipment\\presentation\\api\\provider\\tag\\testprovidemapsresulttotagoutputpaginator',
        6 => 'tests\\unit\\equipment\\presentation\\api\\provider\\tag\\testprovideforwardssearchnullwhenparamabsent',
        7 => 'tests\\unit\\equipment\\presentation\\api\\provider\\tag\\createsecurityuser',
        8 => 'tests\\unit\\equipment\\presentation\\api\\provider\\tag\\buildrequeststack',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Facility/Application/UseCase/Command/Facility/ArchiveFacility/ArchiveFacilityHandlerTest.php' => 
    array (
      0 => '15dbdd25e232e82b5c9e60bc1a6abeb4bb8f64e3',
      1 => 
      array (
        0 => 'tests\\unit\\facility\\application\\usecase\\command\\facility\\archivefacility\\archivefacilityhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\facility\\application\\usecase\\command\\facility\\archivefacility\\testinvokemapsorganizationconstraintviolationtoinvalidargument',
        1 => 'tests\\unit\\facility\\application\\usecase\\command\\facility\\archivefacility\\getsqlstate',
        2 => 'tests\\unit\\facility\\application\\usecase\\command\\facility\\archivefacility\\testinvokethrowswhenfacilitynotfound',
        3 => 'tests\\unit\\facility\\application\\usecase\\command\\facility\\archivefacility\\testinvokethrowswhenfacilitybelongstoanotherorganization',
        4 => 'tests\\unit\\facility\\application\\usecase\\command\\facility\\archivefacility\\testinvokearchivesandreturnsresult',
        5 => 'tests\\unit\\facility\\application\\usecase\\command\\facility\\archivefacility\\testinvokereturnsresultwhennotificationdispatchfails',
        6 => 'tests\\unit\\facility\\application\\usecase\\command\\facility\\archivefacility\\testinvokedoesnotnotifywhenfacilityalreadyarchived',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Facility/Application/UseCase/Command/Facility/CreateFacility/CreateFacilityHandlerTest.php' => 
    array (
      0 => '6366b1f4bb481f781653eadbee905779bd599281',
      1 => 
      array (
        0 => 'tests\\unit\\facility\\application\\usecase\\command\\facility\\createfacility\\createfacilityhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\facility\\application\\usecase\\command\\facility\\createfacility\\testinvokethrowswhenparentfacilityidisblankstring',
        1 => 'tests\\unit\\facility\\application\\usecase\\command\\facility\\createfacility\\testinvokethrowsfacilitycodealreadyexistsonuniqueconstraintviolation',
        2 => 'tests\\unit\\facility\\application\\usecase\\command\\facility\\createfacility\\getsqlstate',
        3 => 'tests\\unit\\facility\\application\\usecase\\command\\facility\\createfacility\\testinvokethrowswhenparentfacilitynotfound',
        4 => 'tests\\unit\\facility\\application\\usecase\\command\\facility\\createfacility\\testinvokethrowswhenparentbelongstoanotherorganization',
        5 => 'tests\\unit\\facility\\application\\usecase\\command\\facility\\createfacility\\testinvokereturnsresult',
        6 => 'tests\\unit\\facility\\application\\usecase\\command\\facility\\createfacility\\testinvokethrowswhenparentfacilityisarchived',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Facility/Application/UseCase/Command/Facility/MoveFacility/MoveFacilityHandlerTest.php' => 
    array (
      0 => '60fbf962ffe5798fbd9833426ba58b20d1839b37',
      1 => 
      array (
        0 => 'tests\\unit\\facility\\application\\usecase\\command\\facility\\movefacility\\movefacilityhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\facility\\application\\usecase\\command\\facility\\movefacility\\testinvokethrowswhenparentfacilityidisblankstring',
        1 => 'tests\\unit\\facility\\application\\usecase\\command\\facility\\movefacility\\testinvokethrowswhenexistinghierarchycontainscycle',
        2 => 'tests\\unit\\facility\\application\\usecase\\command\\facility\\movefacility\\testinvokethrowswhenfacilitynotfound',
        3 => 'tests\\unit\\facility\\application\\usecase\\command\\facility\\movefacility\\testinvokethrowswhenfacilitybelongstoanotherorganization',
        4 => 'tests\\unit\\facility\\application\\usecase\\command\\facility\\movefacility\\testinvokethrowswhenselfasparent',
        5 => 'tests\\unit\\facility\\application\\usecase\\command\\facility\\movefacility\\testinvokethrowswhenparentbelongstoanotherorganization',
        6 => 'tests\\unit\\facility\\application\\usecase\\command\\facility\\movefacility\\testinvokemovessuccessfullywithnullparent',
        7 => 'tests\\unit\\facility\\application\\usecase\\command\\facility\\movefacility\\testinvokethrowswhenparentfacilityisarchived',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Facility/Application/UseCase/Command/Facility/UpdateFacility/UpdateFacilityHandlerTest.php' => 
    array (
      0 => '146a27a1f02d67a12ef373323cfd8618464a6b55',
      1 => 
      array (
        0 => 'tests\\unit\\facility\\application\\usecase\\command\\facility\\updatefacility\\updatefacilityhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\facility\\application\\usecase\\command\\facility\\updatefacility\\testinvokeappliesonlyprovidedfieldsforpartialupdate',
        1 => 'tests\\unit\\facility\\application\\usecase\\command\\facility\\updatefacility\\testinvokethrowswhentypeisexplicitlynull',
        2 => 'tests\\unit\\facility\\application\\usecase\\command\\facility\\updatefacility\\testinvokethrowswhentypeisinvalidenumvalue',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Facility/Application/UseCase/Query/Facility/GetFacility/GetFacilityHandlerTest.php' => 
    array (
      0 => '7ff3131a3920efee4b0a3b6053a6bb422369ffe5',
      1 => 
      array (
        0 => 'tests\\unit\\facility\\application\\usecase\\query\\facility\\getfacility\\getfacilityhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\facility\\application\\usecase\\query\\facility\\getfacility\\testinvokethrowswhenfacilitynotfound',
        1 => 'tests\\unit\\facility\\application\\usecase\\query\\facility\\getfacility\\testinvokethrowswhenfacilitybelongstoanotherorganization',
        2 => 'tests\\unit\\facility\\application\\usecase\\query\\facility\\getfacility\\testinvokereturnsresult',
        3 => 'tests\\unit\\facility\\application\\usecase\\query\\facility\\getfacility\\testinvokereturnsresultwithparentfacilityid',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Facility/Application/UseCase/Query/Facility/GetFacilityChildren/GetFacilityChildrenHandlerTest.php' => 
    array (
      0 => 'd1754a6385ef71701986c7d6d80d7ff872c2661a',
      1 => 
      array (
        0 => 'tests\\unit\\facility\\application\\usecase\\query\\facility\\getfacilitychildren\\getfacilitychildrenhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\facility\\application\\usecase\\query\\facility\\getfacilitychildren\\testinvokereturnspaginatedchildrenwithhaschildren',
        1 => 'tests\\unit\\facility\\application\\usecase\\query\\facility\\getfacilitychildren\\testinvokethrowswhenfacilityisoutsideorganization',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Facility/Application/UseCase/Query/Facility/ListFacilities/ListFacilitiesHandlerTest.php' => 
    array (
      0 => '00bda7dfeda6841b77d018fda242da41a444f250',
      1 => 
      array (
        0 => 'tests\\unit\\facility\\application\\usecase\\query\\facility\\listfacilities\\listfacilitieshandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\facility\\application\\usecase\\query\\facility\\listfacilities\\testinvokepassesfilterspaginationandsortingtorepository',
        1 => 'tests\\unit\\facility\\application\\usecase\\query\\facility\\listfacilities\\testinvokereturnsemptylistwhennofacilitiesexist',
        2 => 'tests\\unit\\facility\\application\\usecase\\query\\facility\\listfacilities\\testinvokethrowswhentypeisinvalid',
        3 => 'tests\\unit\\facility\\application\\usecase\\query\\facility\\listfacilities\\testinvokethrowswhenparentfacilityidisinvalid',
        4 => 'tests\\unit\\facility\\application\\usecase\\query\\facility\\listfacilities\\testinvokethrowswhenrootsonlyiscombinedwithparentfacilityid',
        5 => 'tests\\unit\\facility\\application\\usecase\\query\\facility\\listfacilities\\testinvokemapsresultfieldscorrectly',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Facility/Domain/Model/Facility/FacilityTest.php' => 
    array (
      0 => '22d529d51911ea92c0066b690eb420b592cdaea4',
      1 => 
      array (
        0 => 'tests\\unit\\facility\\domain\\model\\facility\\facilitytest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\facility\\domain\\model\\facility\\setup',
        1 => 'tests\\unit\\facility\\domain\\model\\facility\\testcreatesetsstatustoactive',
        2 => 'tests\\unit\\facility\\domain\\model\\facility\\testcreatesetsallfields',
        3 => 'tests\\unit\\facility\\domain\\model\\facility\\testreconstituterestoresarchivedstatus',
        4 => 'tests\\unit\\facility\\domain\\model\\facility\\testrenameupdatesname',
        5 => 'tests\\unit\\facility\\domain\\model\\facility\\testchangetypeupdatestype',
        6 => 'tests\\unit\\facility\\domain\\model\\facility\\testmovetoupdatesparentfacilityid',
        7 => 'tests\\unit\\facility\\domain\\model\\facility\\testchangecodenormalizesandupdates',
        8 => 'tests\\unit\\facility\\domain\\model\\facility\\testchangecodethrowswhenexceedsmaxlength',
        9 => 'tests\\unit\\facility\\domain\\model\\facility\\testchangeaddressnormalizesandupdates',
        10 => 'tests\\unit\\facility\\domain\\model\\facility\\testchangeaddressthrowswhenexceedsmaxlength',
        11 => 'tests\\unit\\facility\\domain\\model\\facility\\testchangemetadatafiltersnonstringkeys',
        12 => 'tests\\unit\\facility\\domain\\model\\facility\\testarchivesetsstatustoarchived',
        13 => 'tests\\unit\\facility\\domain\\model\\facility\\testarchiveisidempotent',
        14 => 'tests\\unit\\facility\\domain\\model\\facility\\testcreatewithnullcodeandaddresssetsnullvalues',
        15 => 'tests\\unit\\facility\\domain\\model\\facility\\testcreatecodenullonblankstring',
        16 => 'tests\\unit\\facility\\domain\\model\\facility\\testcreateaddressnullonblankstring',
        17 => 'tests\\unit\\facility\\domain\\model\\facility\\makeactivefacility',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Facility/Domain/ValueObject/FacilityNameTest.php' => 
    array (
      0 => '60048845f940c8157bd5a5098c168cc20af973e9',
      1 => 
      array (
        0 => 'tests\\unit\\facility\\domain\\valueobject\\facilitynametest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\facility\\domain\\valueobject\\testconstructorthrowsonemptystring',
        1 => 'tests\\unit\\facility\\domain\\valueobject\\testconstructorthrowsonwhitespaceonly',
        2 => 'tests\\unit\\facility\\domain\\valueobject\\testconstructorthrowsonsinglecharacteraftertrimming',
        3 => 'tests\\unit\\facility\\domain\\valueobject\\testconstructorthrowsonsinglecharacterwithwhitespace',
        4 => 'tests\\unit\\facility\\domain\\valueobject\\testconstructoracceptsminimumlength',
        5 => 'tests\\unit\\facility\\domain\\valueobject\\testconstructoracceptsmaximumlength',
        6 => 'tests\\unit\\facility\\domain\\valueobject\\testconstructorthrowswhenexceedsmaximumlength',
        7 => 'tests\\unit\\facility\\domain\\valueobject\\testconstructortrimswhitespace',
        8 => 'tests\\unit\\facility\\domain\\valueobject\\testtostringreturnsvalue',
        9 => 'tests\\unit\\facility\\domain\\valueobject\\testequalsreturnstrueforsamevalue',
        10 => 'tests\\unit\\facility\\domain\\valueobject\\testequalsreturnsfalsefordifferentvalue',
        11 => 'tests\\unit\\facility\\domain\\valueobject\\testequalsiscasesensitive',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Facility/Infrastructure/Adapter/Organization/FacilityStatisticsAdapterTest.php' => 
    array (
      0 => '11e1d0c1a35f6287192a6c9f231e5e56ce88e881',
      1 => 
      array (
        0 => 'tests\\unit\\facility\\infrastructure\\adapter\\organization\\facilitystatisticsadaptertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\facility\\infrastructure\\adapter\\organization\\testcountfacilityoverviewdelegatestorepository',
        1 => 'tests\\unit\\facility\\infrastructure\\adapter\\organization\\testcountfacilitiesbytypenormalizesmissingtypestozero',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Facility/Infrastructure/Persistence/Doctrine/Mapper/FacilityMapperTest.php' => 
    array (
      0 => '05a0286f84de1ce4dad4ff217ad9383366c2493d',
      1 => 
      array (
        0 => 'tests\\unit\\facility\\infrastructure\\persistence\\doctrine\\mapper\\facilitymappertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\facility\\infrastructure\\persistence\\doctrine\\mapper\\testtodomainmapsallfields',
        1 => 'tests\\unit\\facility\\infrastructure\\persistence\\doctrine\\mapper\\testtodomainhandlesnulloptionalfields',
        2 => 'tests\\unit\\facility\\infrastructure\\persistence\\doctrine\\mapper\\testtorecordmapsallfields',
        3 => 'tests\\unit\\facility\\infrastructure\\persistence\\doctrine\\mapper\\testtorecordhandlesnulloptionalfields',
        4 => 'tests\\unit\\facility\\infrastructure\\persistence\\doctrine\\mapper\\testtodomainandtorecordroundtrip',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Facility/Presentation/Api/Processor/Facility/ArchiveFacilityProcessorTest.php' => 
    array (
      0 => '63e6a0e437a38bea1b30f48dead98ca41365a3a1',
      1 => 
      array (
        0 => 'tests\\unit\\facility\\presentation\\api\\processor\\facility\\archivefacilityprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\facility\\presentation\\api\\processor\\facility\\testprocessmapswrappednotfoundtohttp404',
        1 => 'tests\\unit\\facility\\presentation\\api\\processor\\facility\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Facility/Presentation/Api/Processor/Facility/CanonicalFacilityMutationProcessorTest.php' => 
    array (
      0 => '29204fd14bc3210c28acf6e75b53da45943da38c',
      1 => 
      array (
        0 => 'tests\\unit\\facility\\presentation\\api\\processor\\facility\\canonicalfacilitymutationprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\facility\\presentation\\api\\processor\\facility\\testdeletingpublishedfacilityarchivesit',
        1 => 'tests\\unit\\facility\\presentation\\api\\processor\\facility\\processor',
        2 => 'tests\\unit\\facility\\presentation\\api\\processor\\facility\\entitymanager',
        3 => 'tests\\unit\\facility\\presentation\\api\\processor\\facility\\record',
        4 => 'tests\\unit\\facility\\presentation\\api\\processor\\facility\\request',
        5 => 'tests\\unit\\facility\\presentation\\api\\processor\\facility\\user',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Facility/Presentation/Api/Processor/Facility/CreateFacilityProcessorTest.php' => 
    array (
      0 => 'edb5cb5e0cdd5299651d827ca66bb3dcf0adb977',
      1 => 
      array (
        0 => 'tests\\unit\\facility\\presentation\\api\\processor\\facility\\createfacilityprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\facility\\presentation\\api\\processor\\facility\\testprocessmapswrappedcodeconflicttohttp409',
        1 => 'tests\\unit\\facility\\presentation\\api\\processor\\facility\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Facility/Presentation/Api/Processor/Facility/MoveFacilityProcessorTest.php' => 
    array (
      0 => 'a82b86c95095dcb96e95700b84aa71fc9325ec17',
      1 => 
      array (
        0 => 'tests\\unit\\facility\\presentation\\api\\processor\\facility\\movefacilityprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\facility\\presentation\\api\\processor\\facility\\testprocessthrowswhenparentfacilityidfieldismissing',
        1 => 'tests\\unit\\facility\\presentation\\api\\processor\\facility\\testprocessallowsexplicitnulltodetachfacility',
        2 => 'tests\\unit\\facility\\presentation\\api\\processor\\facility\\testprocessmapswrappedhierarchyexceptiontohttp400',
        3 => 'tests\\unit\\facility\\presentation\\api\\processor\\facility\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Facility/Presentation/Api/Processor/Facility/RestoreFacilityProcessorTest.php' => 
    array (
      0 => 'bef8a693f22918f119351b78d95dc0389d4720f9',
      1 => 
      array (
        0 => 'tests\\unit\\facility\\presentation\\api\\processor\\facility\\restorefacilityprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\facility\\presentation\\api\\processor\\facility\\testprocessmapsresulttooutput',
        1 => 'tests\\unit\\facility\\presentation\\api\\processor\\facility\\testprocessmapsnotfoundtohttp404',
        2 => 'tests\\unit\\facility\\presentation\\api\\processor\\facility\\testprocessmapsarchivedparenttobadrequest',
        3 => 'tests\\unit\\facility\\presentation\\api\\processor\\facility\\makeprocessor',
        4 => 'tests\\unit\\facility\\presentation\\api\\processor\\facility\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Facility/Presentation/Api/Processor/Facility/UpdateFacilityProcessorTest.php' => 
    array (
      0 => '6872467d0d8d88ed87e3d192ac50408148bd08a4',
      1 => 
      array (
        0 => 'tests\\unit\\facility\\presentation\\api\\processor\\facility\\updatefacilityprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\facility\\presentation\\api\\processor\\facility\\testprocessthrowswhennopatchfieldsareprovided',
        1 => 'tests\\unit\\facility\\presentation\\api\\processor\\facility\\testprocessdispatchespartialupdatecommandwithpresenceflags',
        2 => 'tests\\unit\\facility\\presentation\\api\\processor\\facility\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Facility/Presentation/Api/Provider/Facility/GetFacilityProviderTest.php' => 
    array (
      0 => 'fd882b753a057c8a088359976d1135abdd2e49a4',
      1 => 
      array (
        0 => 'tests\\unit\\facility\\presentation\\api\\provider\\facility\\getfacilityprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\facility\\presentation\\api\\provider\\facility\\testprovidemapswrappedfacilitynotfoundtohttp404',
        1 => 'tests\\unit\\facility\\presentation\\api\\provider\\facility\\testprovidemapsresulttooutput',
        2 => 'tests\\unit\\facility\\presentation\\api\\provider\\facility\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Facility/Presentation/Api/Provider/Facility/ListFacilitiesProviderTest.php' => 
    array (
      0 => '708cd0cd76dd5f88f810bf97b4297a037cf0a03b',
      1 => 
      array (
        0 => 'tests\\unit\\facility\\presentation\\api\\provider\\facility\\listfacilitiesprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\facility\\presentation\\api\\provider\\facility\\testprovideusesincludearchivedfalsebydefault',
        1 => 'tests\\unit\\facility\\presentation\\api\\provider\\facility\\testprovideusesincludearchivedtruewhenqueryparamistrue',
        2 => 'tests\\unit\\facility\\presentation\\api\\provider\\facility\\testprovidepassesfilterssearchandsortingtoquery',
        3 => 'tests\\unit\\facility\\presentation\\api\\provider\\facility\\testprovidethrowswhennotauthenticated',
        4 => 'tests\\unit\\facility\\presentation\\api\\provider\\facility\\testprovidethrowswhenpermissiondenied',
        5 => 'tests\\unit\\facility\\presentation\\api\\provider\\facility\\testprovidethrowsbadrequestwhenorganizationidmissing',
        6 => 'tests\\unit\\facility\\presentation\\api\\provider\\facility\\testprovideexposestotalitemsinpaginator',
        7 => 'tests\\unit\\facility\\presentation\\api\\provider\\facility\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Facility/Presentation/Api/Provider/Facility/ListFacilityChildrenProviderTest.php' => 
    array (
      0 => 'd4f96f33b58e78112d306ca6ea326b96cc009bf8',
      1 => 
      array (
        0 => 'tests\\unit\\facility\\presentation\\api\\provider\\facility\\listfacilitychildrenprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\facility\\presentation\\api\\provider\\facility\\testprovidemapsresults',
        1 => 'tests\\unit\\facility\\presentation\\api\\provider\\facility\\testprovidemapswrappednotfoundtohttp404',
        2 => 'tests\\unit\\facility\\presentation\\api\\provider\\facility\\makerequeststack',
        3 => 'tests\\unit\\facility\\presentation\\api\\provider\\facility\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Facility/Presentation/Api/Provider/Facility/ListFacilityDescendantsProviderTest.php' => 
    array (
      0 => '493f9a0341f13f0571d53fbe7f7f93d36e3e5db7',
      1 => 
      array (
        0 => 'tests\\unit\\facility\\presentation\\api\\provider\\facility\\listfacilitydescendantsprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\facility\\presentation\\api\\provider\\facility\\testprovidemapsresults',
        1 => 'tests\\unit\\facility\\presentation\\api\\provider\\facility\\makerequeststack',
        2 => 'tests\\unit\\facility\\presentation\\api\\provider\\facility\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Facility/Presentation/Api/Provider/Facility/ListFacilityStatusesProviderTest.php' => 
    array (
      0 => '3c198a62c828789e00e614f62a8500df43741744',
      1 => 
      array (
        0 => 'tests\\unit\\facility\\presentation\\api\\provider\\facility\\listfacilitystatusesprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\facility\\presentation\\api\\provider\\facility\\testprovidethrowswhenunauthenticated',
        1 => 'tests\\unit\\facility\\presentation\\api\\provider\\facility\\testprovidereturnsfacilitystatusoptions',
        2 => 'tests\\unit\\facility\\presentation\\api\\provider\\facility\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Facility/Presentation/Api/Provider/Facility/ListFacilityTypesProviderTest.php' => 
    array (
      0 => '4f0c22d650a0c1578fbe611dc4c93642e07d5664',
      1 => 
      array (
        0 => 'tests\\unit\\facility\\presentation\\api\\provider\\facility\\listfacilitytypesprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\facility\\presentation\\api\\provider\\facility\\testprovidethrowswhenunauthenticated',
        1 => 'tests\\unit\\facility\\presentation\\api\\provider\\facility\\testprovidereturnsfacilitytypeoptions',
        2 => 'tests\\unit\\facility\\presentation\\api\\provider\\facility\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Inspection/Application/UseCase/Command/Inspection/CancelInspection/CancelInspectionHandlerTest.php' => 
    array (
      0 => 'b1b712678cdbd592d04e4e0099c04d02a7f309e2',
      1 => 
      array (
        0 => 'tests\\unit\\inspection\\application\\usecase\\command\\inspection\\cancelinspection\\cancelinspectionhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\inspection\\application\\usecase\\command\\inspection\\cancelinspection\\testinvokeremovesinspectionandreturnsresult',
        1 => 'tests\\unit\\inspection\\application\\usecase\\command\\inspection\\cancelinspection\\testinvokethrowswheninspectionnotfound',
        2 => 'tests\\unit\\inspection\\application\\usecase\\command\\inspection\\cancelinspection\\testinvokethrowswhenorganizationmismatch',
        3 => 'tests\\unit\\inspection\\application\\usecase\\command\\inspection\\cancelinspection\\testinvokethrowswheninspectionisalreadyclosed',
        4 => 'tests\\unit\\inspection\\application\\usecase\\command\\inspection\\cancelinspection\\testinvokethrowswheninspectionissubmitted',
        5 => 'tests\\unit\\inspection\\application\\usecase\\command\\inspection\\cancelinspection\\makedraftinspection',
        6 => 'tests\\unit\\inspection\\application\\usecase\\command\\inspection\\cancelinspection\\makesubmittedinspection',
        7 => 'tests\\unit\\inspection\\application\\usecase\\command\\inspection\\cancelinspection\\makeclosedinspection',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Inspection/Application/UseCase/Command/Inspection/CloseInspection/CloseInspectionHandlerTest.php' => 
    array (
      0 => 'ee5fe8bfd7546dc546b87758500c3a78100c74ad',
      1 => 
      array (
        0 => 'tests\\unit\\inspection\\application\\usecase\\command\\inspection\\closeinspection\\closeinspectionhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\inspection\\application\\usecase\\command\\inspection\\closeinspection\\testinvokeclosessubmittedinspectionandreturnsresult',
        1 => 'tests\\unit\\inspection\\application\\usecase\\command\\inspection\\closeinspection\\testinvokethrowswheninspectionnotfound',
        2 => 'tests\\unit\\inspection\\application\\usecase\\command\\inspection\\closeinspection\\testinvokethrowswhenorganizationmismatch',
        3 => 'tests\\unit\\inspection\\application\\usecase\\command\\inspection\\closeinspection\\testinvokethrowswheninspectionisdraft',
        4 => 'tests\\unit\\inspection\\application\\usecase\\command\\inspection\\closeinspection\\makesubmittedinspection',
        5 => 'tests\\unit\\inspection\\application\\usecase\\command\\inspection\\closeinspection\\makedraftinspection',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Inspection/Application/UseCase/Command/Inspection/CreateInspection/CreateInspectionHandlerTest.php' => 
    array (
      0 => 'ebb21bce64152999a21ba27f32e8f56f69bfddba',
      1 => 
      array (
        0 => 'tests\\unit\\inspection\\application\\usecase\\command\\inspection\\createinspection\\createinspectionhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\inspection\\application\\usecase\\command\\inspection\\createinspection\\testinvokepersistsinspectionandreturnsresult',
        1 => 'tests\\unit\\inspection\\application\\usecase\\command\\inspection\\createinspection\\testinvokewithexternalinspector',
        2 => 'tests\\unit\\inspection\\application\\usecase\\command\\inspection\\createinspection\\testinvokethrowsoninvalidorganizationuuid',
        3 => 'tests\\unit\\inspection\\application\\usecase\\command\\inspection\\createinspection\\testinvokethrowswhenuserinspectorwithoutuserid',
        4 => 'tests\\unit\\inspection\\application\\usecase\\command\\inspection\\createinspection\\testinvokethrowswhenequipmentnotfound',
        5 => 'tests\\unit\\inspection\\application\\usecase\\command\\inspection\\createinspection\\testinvokenormalizesinvalidequipmentvalidationvalue',
        6 => 'tests\\unit\\inspection\\application\\usecase\\command\\inspection\\createinspection\\testinvokethrowswhenfacilitynotfound',
        7 => 'tests\\unit\\inspection\\application\\usecase\\command\\inspection\\createinspection\\testinvokethrowswhenfacilityisarchived',
        8 => 'tests\\unit\\inspection\\application\\usecase\\command\\inspection\\createinspection\\testinvokethrowswhenchecklistnotfound',
        9 => 'tests\\unit\\inspection\\application\\usecase\\command\\inspection\\createinspection\\testinvokethrowswhenchecklistisarchived',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Inspection/Application/UseCase/Command/Inspection/EditInspection/EditInspectionHandlerTest.php' => 
    array (
      0 => '5a7bc3ebd1dfb609e6028946ce3bfb3e940bf102',
      1 => 
      array (
        0 => 'tests\\unit\\inspection\\application\\usecase\\command\\inspection\\editinspection\\editinspectionhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\inspection\\application\\usecase\\command\\inspection\\editinspection\\testinvokeeditsinspectionsavesandreturnsresult',
        1 => 'tests\\unit\\inspection\\application\\usecase\\command\\inspection\\editinspection\\testinvokethrowswheninspectionnotfound',
        2 => 'tests\\unit\\inspection\\application\\usecase\\command\\inspection\\editinspection\\testinvokethrowswhenorganizationmismatch',
        3 => 'tests\\unit\\inspection\\application\\usecase\\command\\inspection\\editinspection\\testinvokethrowswhenequipmentidnullwhenrequired',
        4 => 'tests\\unit\\inspection\\application\\usecase\\command\\inspection\\editinspection\\testinvokevalidatesequipmentwhenequipmentidprovided',
        5 => 'tests\\unit\\inspection\\application\\usecase\\command\\inspection\\editinspection\\testinvokevalidatesfacilitywhenfacilityidprovided',
        6 => 'tests\\unit\\inspection\\application\\usecase\\command\\inspection\\editinspection\\testinvokevalidateschecklistwhenchecklistidprovided',
        7 => 'tests\\unit\\inspection\\application\\usecase\\command\\inspection\\editinspection\\makedraftinspection',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Inspection/Application/UseCase/Command/Inspection/SubmitInspection/SubmitInspectionHandlerTest.php' => 
    array (
      0 => '660d3af74180a02e1ee815d23e4eb1fa2dc2980f',
      1 => 
      array (
        0 => 'tests\\unit\\inspection\\application\\usecase\\command\\inspection\\submitinspection\\submitinspectionhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\inspection\\application\\usecase\\command\\inspection\\submitinspection\\testinvokesubmitsinspectionandreturnsresult',
        1 => 'tests\\unit\\inspection\\application\\usecase\\command\\inspection\\submitinspection\\testinvokethrowswheninspectionnotfound',
        2 => 'tests\\unit\\inspection\\application\\usecase\\command\\inspection\\submitinspection\\testinvokethrowswhenorganizationmismatch',
        3 => 'tests\\unit\\inspection\\application\\usecase\\command\\inspection\\submitinspection\\makedraftinspection',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Inspection/Application/UseCase/Command/NonConformity/AddNonConformity/AddNonConformityHandlerTest.php' => 
    array (
      0 => '5f405be9bf6548b4c75c1b5580dd0aa3b0897a2f',
      1 => 
      array (
        0 => 'tests\\unit\\inspection\\application\\usecase\\command\\nonconformity\\addnonconformity\\addnonconformityhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\inspection\\application\\usecase\\command\\nonconformity\\addnonconformity\\testinvokeaddsnonconformityandreturnsresult',
        1 => 'tests\\unit\\inspection\\application\\usecase\\command\\nonconformity\\addnonconformity\\testinvokethrowswheninspectionnotfound',
        2 => 'tests\\unit\\inspection\\application\\usecase\\command\\nonconformity\\addnonconformity\\testinvokethrowswheninspectionisclosed',
        3 => 'tests\\unit\\inspection\\application\\usecase\\command\\nonconformity\\addnonconformity\\makedraftinspection',
        4 => 'tests\\unit\\inspection\\application\\usecase\\command\\nonconformity\\addnonconformity\\makeclosedinspection',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Inspection/Application/UseCase/Command/NonConformity/UpdateNonConformityStatus/UpdateNonConformityStatusHandlerTest.php' => 
    array (
      0 => '64ea7579f18b84e3aff023ced2c3c02edef52349',
      1 => 
      array (
        0 => 'tests\\unit\\inspection\\application\\usecase\\command\\nonconformity\\updatenonconformitystatus\\updatenonconformitystatushandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\inspection\\application\\usecase\\command\\nonconformity\\updatenonconformitystatus\\testinvokeupdatesstatusandsavesnonconformity',
        1 => 'tests\\unit\\inspection\\application\\usecase\\command\\nonconformity\\updatenonconformitystatus\\testinvokethrowswheninspectionnotfound',
        2 => 'tests\\unit\\inspection\\application\\usecase\\command\\nonconformity\\updatenonconformitystatus\\testinvokethrowswhenorganizationmismatch',
        3 => 'tests\\unit\\inspection\\application\\usecase\\command\\nonconformity\\updatenonconformitystatus\\testinvokethrowswhennonconformitynotfound',
        4 => 'tests\\unit\\inspection\\application\\usecase\\command\\nonconformity\\updatenonconformitystatus\\testinvokethrowswhennonconformitybelongstodifferentinspection',
        5 => 'tests\\unit\\inspection\\application\\usecase\\command\\nonconformity\\updatenonconformitystatus\\testinvokethrowswhennonconformityalreadyresolved',
        6 => 'tests\\unit\\inspection\\application\\usecase\\command\\nonconformity\\updatenonconformitystatus\\testinvokethrowswhenstatusisinvalid',
        7 => 'tests\\unit\\inspection\\application\\usecase\\command\\nonconformity\\updatenonconformitystatus\\makedraftinspection',
        8 => 'tests\\unit\\inspection\\application\\usecase\\command\\nonconformity\\updatenonconformitystatus\\makeopennonconformity',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Inspection/Application/UseCase/Query/Inspection/ListInspections/ListInspectionsHandlerTest.php' => 
    array (
      0 => '9b705ede168cb737b27f453ab851135564c74af0',
      1 => 
      array (
        0 => 'tests\\unit\\inspection\\application\\usecase\\query\\inspection\\listinspections\\listinspectionshandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\inspection\\application\\usecase\\query\\inspection\\listinspections\\testinvokepassesfilterspaginationandsortingtorepository',
        1 => 'tests\\unit\\inspection\\application\\usecase\\query\\inspection\\listinspections\\testinvokereturnsemptypaginatedresultwhennoinspections',
        2 => 'tests\\unit\\inspection\\application\\usecase\\query\\inspection\\listinspections\\testinvokethrowswhenperformedatrangeisinvalid',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Inspection/Domain/Model/Checklist/ChecklistTest.php' => 
    array (
      0 => 'fde6914b8996f71736e5bbe67d821ab724a51dfa',
      1 => 
      array (
        0 => 'tests\\unit\\inspection\\domain\\model\\checklist\\checklisttest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\inspection\\domain\\model\\checklist\\testcreatereturnsactivestatus',
        1 => 'tests\\unit\\inspection\\domain\\model\\checklist\\testcreatestoresallproperties',
        2 => 'tests\\unit\\inspection\\domain\\model\\checklist\\testcreatethrowsonemptyname',
        3 => 'tests\\unit\\inspection\\domain\\model\\checklist\\testcreatethrowswhennametoolong',
        4 => 'tests\\unit\\inspection\\domain\\model\\checklist\\testcreatethrowsonemptyversion',
        5 => 'tests\\unit\\inspection\\domain\\model\\checklist\\testarchivetransitionstoarchivedstatus',
        6 => 'tests\\unit\\inspection\\domain\\model\\checklist\\testarchivethrowswhenalreadyarchived',
        7 => 'tests\\unit\\inspection\\domain\\model\\checklist\\makechecklist',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Inspection/Domain/Model/Inspection/InspectionTest.php' => 
    array (
      0 => '38b2b4b0a5ed1bf07e08157bba0529d514067e61',
      1 => 
      array (
        0 => 'tests\\unit\\inspection\\domain\\model\\inspection\\inspectiontest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\inspection\\domain\\model\\inspection\\testcreatereturnsdraftstatus',
        1 => 'tests\\unit\\inspection\\domain\\model\\inspection\\testcreatestoresallproperties',
        2 => 'tests\\unit\\inspection\\domain\\model\\inspection\\testcreatenormalizesemptynotestonull',
        3 => 'tests\\unit\\inspection\\domain\\model\\inspection\\testcreatethrowswhennotestoolong',
        4 => 'tests\\unit\\inspection\\domain\\model\\inspection\\testsubmittransitionsdrafttosubmitted',
        5 => 'tests\\unit\\inspection\\domain\\model\\inspection\\testsubmitthrowswhenalreadysubmitted',
        6 => 'tests\\unit\\inspection\\domain\\model\\inspection\\testsubmitthrowswhenalreadyclosed',
        7 => 'tests\\unit\\inspection\\domain\\model\\inspection\\testclosetransitionssubmittedtoclosed',
        8 => 'tests\\unit\\inspection\\domain\\model\\inspection\\testclosethrowswhenalreadyclosed',
        9 => 'tests\\unit\\inspection\\domain\\model\\inspection\\testclosethrowswhendraftnotyetsubmitted',
        10 => 'tests\\unit\\inspection\\domain\\model\\inspection\\testupdatedatchangesaftersubmit',
        11 => 'tests\\unit\\inspection\\domain\\model\\inspection\\makeinspection',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Inspection/Domain/Model/NonConformity/NonConformityTest.php' => 
    array (
      0 => '51d07022eb87a0492c166ea9be98aee256d3fe72',
      1 => 
      array (
        0 => 'tests\\unit\\inspection\\domain\\model\\nonconformity\\nonconformitytest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\inspection\\domain\\model\\nonconformity\\testcreatereturnsopenstatus',
        1 => 'tests\\unit\\inspection\\domain\\model\\nonconformity\\testcreatestoresallproperties',
        2 => 'tests\\unit\\inspection\\domain\\model\\nonconformity\\testcreatethrowsonemptydescription',
        3 => 'tests\\unit\\inspection\\domain\\model\\nonconformity\\testcreatethrowswhendescriptiontoolong',
        4 => 'tests\\unit\\inspection\\domain\\model\\nonconformity\\testupdatestatustransitionstoinprogress',
        5 => 'tests\\unit\\inspection\\domain\\model\\nonconformity\\testupdatestatussetsresolvedatwhendone',
        6 => 'tests\\unit\\inspection\\domain\\model\\nonconformity\\testupdatestatussetsresolvedatwhenwaived',
        7 => 'tests\\unit\\inspection\\domain\\model\\nonconformity\\testupdatestatusthrowswhenalreadyresolved',
        8 => 'tests\\unit\\inspection\\domain\\model\\nonconformity\\testupdatestatusthrowswhenwaivedandupdatedagain',
        9 => 'tests\\unit\\inspection\\domain\\model\\nonconformity\\makenonconformity',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Inspection/Infrastructure/Adapter/Organization/InspectionStatisticsAdapterTest.php' => 
    array (
      0 => '730ee6351aecaaa75c4f02fb7dc845352d742eb9',
      1 => 
      array (
        0 => 'tests\\unit\\inspection\\infrastructure\\adapter\\organization\\inspectionstatisticsadaptertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\inspection\\infrastructure\\adapter\\organization\\testcountinspectionoverviewnormalizesmissingvaluestozero',
        1 => 'tests\\unit\\inspection\\infrastructure\\adapter\\organization\\testcountinspectionperiodmetricsdelegatestorepository',
        2 => 'tests\\unit\\inspection\\infrastructure\\adapter\\organization\\testcountinspectionsbystatusnormalizesmissingstatusestozero',
        3 => 'tests\\unit\\inspection\\infrastructure\\adapter\\organization\\testcountinspectionsbyresultandinspectortypenormalizemissingvaluestozero',
        4 => 'tests\\unit\\inspection\\infrastructure\\adapter\\organization\\testcountinspectionsperformedbydaydelegatestimezonetorepository',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Inspection/Infrastructure/Adapter/Organization/NonConformityStatisticsAdapterTest.php' => 
    array (
      0 => '76f2b29d18f606605fbf924f2736a1ae836377a9',
      1 => 
      array (
        0 => 'tests\\unit\\inspection\\infrastructure\\adapter\\organization\\nonconformitystatisticsadaptertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\inspection\\infrastructure\\adapter\\organization\\testcountnonconformityoverviewnormalizesmissingstatusestozero',
        1 => 'tests\\unit\\inspection\\infrastructure\\adapter\\organization\\testcountnonconformityperiodmetricsdelegatestorepository',
        2 => 'tests\\unit\\inspection\\infrastructure\\adapter\\organization\\testcountnonconformitiesbystatusandseveritynormalizemissingvaluestozero',
        3 => 'tests\\unit\\inspection\\infrastructure\\adapter\\organization\\testcountactivenonconformitiesatdatedelegatestorepository',
        4 => 'tests\\unit\\inspection\\infrastructure\\adapter\\organization\\testcountnonconformitiescreatedbydaydelegatestimezonetorepository',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Inspection/Infrastructure/Persistence/Doctrine/Repository/InspectionRepositoryTest.php' => 
    array (
      0 => '9107f7a2863cb06d01638a569a34f7e105c245af',
      1 => 
      array (
        0 => 'tests\\unit\\inspection\\infrastructure\\persistence\\doctrine\\repository\\inspectionrepositorytest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\inspection\\infrastructure\\persistence\\doctrine\\repository\\testcountbyorganizationidnormalizesdatefilterstoconfiguredstoragetimezone',
        1 => 'tests\\unit\\inspection\\infrastructure\\persistence\\doctrine\\repository\\testfindbyidreinterpretsstoredtimestampsusingconfiguredstoragetimezone',
        2 => 'tests\\unit\\inspection\\infrastructure\\persistence\\doctrine\\repository\\testsavenormalizespersistedtimestampstoconfiguredstoragetimezone',
        3 => 'tests\\unit\\inspection\\infrastructure\\persistence\\doctrine\\repository\\testcountbyperformeddayfororganizationidreinterpretshydrateddatetimesusingconfiguredstoragetimezone',
        4 => 'tests\\unit\\inspection\\infrastructure\\persistence\\doctrine\\repository\\testcountbyperformeddayfororganizationidpreservesmicrosecondsonpostgresqlbounds',
        5 => 'tests\\unit\\inspection\\infrastructure\\persistence\\doctrine\\repository\\testcountbyperformeddayfororganizationidpostgresqlappliesoptionalfiltersinsql',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Inspection/Infrastructure/Persistence/Doctrine/Repository/NonConformityRepositoryTest.php' => 
    array (
      0 => 'c3a9504f16e6dda29e26fb6662167944bc4f93f0',
      1 => 
      array (
        0 => 'tests\\unit\\inspection\\infrastructure\\persistence\\doctrine\\repository\\nonconformityrepositorytest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\inspection\\infrastructure\\persistence\\doctrine\\repository\\testcountoverduebyorganizationidnormalizesdatefiltertoconfiguredstoragetimezone',
        1 => 'tests\\unit\\inspection\\infrastructure\\persistence\\doctrine\\repository\\testfindbyidreinterpretsstoredtimestampsusingconfiguredstoragetimezone',
        2 => 'tests\\unit\\inspection\\infrastructure\\persistence\\doctrine\\repository\\testsavenormalizespersistedtimestampstoconfiguredstoragetimezone',
        3 => 'tests\\unit\\inspection\\infrastructure\\persistence\\doctrine\\repository\\testcountbycreateddayfororganizationidreinterpretshydrateddatetimesusingconfiguredstoragetimezone',
        4 => 'tests\\unit\\inspection\\infrastructure\\persistence\\doctrine\\repository\\testcountbycreateddayfororganizationidpreservesmicrosecondsonpostgresqlbounds',
        5 => 'tests\\unit\\inspection\\infrastructure\\persistence\\doctrine\\repository\\testcountbycreateddayfororganizationidpostgresqlappliesoptionalfiltersinsql',
        6 => 'tests\\unit\\inspection\\infrastructure\\persistence\\doctrine\\repository\\testcountbyresolveddayfororganizationidpostgresqlappliesoptionalfiltersinsql',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Inspection/Presentation/Api/Processor/Checklist/ArchiveChecklistProcessorTest.php' => 
    array (
      0 => 'fe22dfdcd7dd347b256fd548ced35d4533a1df81',
      1 => 
      array (
        0 => 'tests\\unit\\inspection\\presentation\\api\\processor\\checklist\\archivechecklistprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\inspection\\presentation\\api\\processor\\checklist\\testprocessthrowswhennotauthenticated',
        1 => 'tests\\unit\\inspection\\presentation\\api\\processor\\checklist\\testprocessthrowswhenurivariablesmissing',
        2 => 'tests\\unit\\inspection\\presentation\\api\\processor\\checklist\\testprocessdispatchescommandandreturnsoutput',
        3 => 'tests\\unit\\inspection\\presentation\\api\\processor\\checklist\\testprocessthrowsnotfoundwhenchecklistmissing',
        4 => 'tests\\unit\\inspection\\presentation\\api\\processor\\checklist\\testprocessthrowsconflictwhenalreadyarchived',
        5 => 'tests\\unit\\inspection\\presentation\\api\\processor\\checklist\\testprocessunwrapsnotfoundfrommessengerexception',
        6 => 'tests\\unit\\inspection\\presentation\\api\\processor\\checklist\\makeauthorizedprocessor',
        7 => 'tests\\unit\\inspection\\presentation\\api\\processor\\checklist\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Inspection/Presentation/Api/Processor/Checklist/CreateChecklistProcessorTest.php' => 
    array (
      0 => 'c5b8875da78ed6f1720e304edaddf8ee871fa1af',
      1 => 
      array (
        0 => 'tests\\unit\\inspection\\presentation\\api\\processor\\checklist\\createchecklistprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\inspection\\presentation\\api\\processor\\checklist\\testprocessthrowswhennotauthenticated',
        1 => 'tests\\unit\\inspection\\presentation\\api\\processor\\checklist\\testprocessthrowswhenorganizationidmissing',
        2 => 'tests\\unit\\inspection\\presentation\\api\\processor\\checklist\\testprocessthrowswhenpermissiondenied',
        3 => 'tests\\unit\\inspection\\presentation\\api\\processor\\checklist\\testprocessdispatchescommandandreturnsoutput',
        4 => 'tests\\unit\\inspection\\presentation\\api\\processor\\checklist\\testprocessthrowsbadrequestoninvalidargument',
        5 => 'tests\\unit\\inspection\\presentation\\api\\processor\\checklist\\testprocessunwrapsbadrequestfrommessengerexception',
        6 => 'tests\\unit\\inspection\\presentation\\api\\processor\\checklist\\makeauthorizedprocessor',
        7 => 'tests\\unit\\inspection\\presentation\\api\\processor\\checklist\\makeinput',
        8 => 'tests\\unit\\inspection\\presentation\\api\\processor\\checklist\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Inspection/Presentation/Api/Processor/Inspection/CancelInspectionProcessorTest.php' => 
    array (
      0 => '932dbe14aa79d6b66e430acdddff69dbe88692f3',
      1 => 
      array (
        0 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\cancelinspectionprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\testprocessthrowswhennotauthenticated',
        1 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\testprocessdispatchescommandandreturnsnull',
        2 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\testprocessthrowsnotfoundwheninspectionmissing',
        3 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\testprocessthrowsconflictwheninspectionalreadysubmitted',
        4 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\testprocessunwrapsclosedfrommessengerexception',
        5 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\makeauthorizedprocessor',
        6 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Inspection/Presentation/Api/Processor/Inspection/CanonicalInspectionMutationProcessorTest.php' => 
    array (
      0 => 'de636c1cdceb1d159e7dce7d05ffa90c16409c41',
      1 => 
      array (
        0 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\canonicalinspectionmutationprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\testdeletingdraftinspectionremovesit',
        1 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\processor',
        2 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\entitymanager',
        3 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\record',
        4 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\request',
        5 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\user',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Inspection/Presentation/Api/Processor/Inspection/CloseInspectionProcessorTest.php' => 
    array (
      0 => '3756e5f8f136d3b0d386cc86005719ce121c8270',
      1 => 
      array (
        0 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\closeinspectionprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\testprocessthrowswhennotauthenticated',
        1 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\testprocessthrowswhenurivariablesmissing',
        2 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\testprocessthrowswhenpermissiondenied',
        3 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\testprocessdispatchescommandandreturnsoutput',
        4 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\testprocessthrowsnotfoundwheninspectionmissing',
        5 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\testprocessthrowsconflictwhenalreadyclosed',
        6 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\testprocessthrowsconflictwhennotyetsubmitted',
        7 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\testprocessunwrapsnotfoundfrommessengerexception',
        8 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\testprocessunwrapsconflictfrommessengerexception',
        9 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\makeauthorizedprocessor',
        10 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\createoutputmapper',
        11 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Inspection/Presentation/Api/Processor/Inspection/CreateInspectionProcessorTest.php' => 
    array (
      0 => 'b60f8d69f34f590519c0a943f1e690b12ff81727',
      1 => 
      array (
        0 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\createinspectionprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\testprocessthrowswhennotauthenticated',
        1 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\testprocessthrowswhenorganizationidmissing',
        2 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\testprocessthrowswhenpermissiondenied',
        3 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\testprocessdispatchescommandandreturnsoutput',
        4 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\testprocessthrowsbadrequestoninvalidargument',
        5 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\testprocessunwrapsinvalidargumentfrommessengerexception',
        6 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\createsecurityuser',
        7 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\createoutputmapper',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Inspection/Presentation/Api/Processor/Inspection/EditInspectionProcessorTest.php' => 
    array (
      0 => '02a6e069d4d9a9510261923202120a5adcc05e28',
      1 => 
      array (
        0 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\editinspectionprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\testprocessthrowswhenrequestpayloadhasnoeditablefields',
        1 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\testprocessdispatchescommandandreturnsoutput',
        2 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\testprocessthrowsconflictwheninspectionalreadysubmitted',
        3 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\testprocessthrowsnotfoundwheninspectionmissing',
        4 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\testprocessunwrapsclosedfrommessengerexception',
        5 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\makeprocessor',
        6 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\createoutputmapper',
        7 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\makerequeststack',
        8 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\makeinput',
        9 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\makesecurity',
        10 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\makegetinspectionresult',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Inspection/Presentation/Api/Processor/Inspection/SubmitInspectionProcessorTest.php' => 
    array (
      0 => 'de2775d1abf4a0220223c68c54316581acfae683',
      1 => 
      array (
        0 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\submitinspectionprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\testprocessthrowswhennotauthenticated',
        1 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\testprocessthrowswhenurivariablesmissing',
        2 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\testprocessthrowswhenpermissiondenied',
        3 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\testprocessdispatchescommandandreturnsoutput',
        4 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\testprocessthrowsnotfoundwheninspectionmissing',
        5 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\testprocessthrowsconflictwhenalreadysubmitted',
        6 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\testprocessthrowsconflictwhenalreadyclosed',
        7 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\testprocessunwrapsnotfoundfrommessengerexception',
        8 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\makeauthorizedprocessor',
        9 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\createoutputmapper',
        10 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\makegetinspectionresult',
        11 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspection\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Inspection/Presentation/Api/Processor/InspectionResponse/InspectionResponseProcessorTest.php' => 
    array (
      0 => '6791ad5fd73402c69ca55a39f00150fd64d14e23',
      1 => 
      array (
        0 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspectionresponse\\inspectionresponseprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\inspection\\presentation\\api\\processor\\inspectionresponse\\testexistingclientuuidputreturnspreconditionfailed',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Inspection/Presentation/Api/Processor/NonConformity/AddNonConformityProcessorTest.php' => 
    array (
      0 => '2327c816ccbdd87b42bc872efa227e071d04c9b1',
      1 => 
      array (
        0 => 'tests\\unit\\inspection\\presentation\\api\\processor\\nonconformity\\addnonconformityprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\inspection\\presentation\\api\\processor\\nonconformity\\testprocessthrowswhennotauthenticated',
        1 => 'tests\\unit\\inspection\\presentation\\api\\processor\\nonconformity\\testprocessthrowswhenurivariablesmissing',
        2 => 'tests\\unit\\inspection\\presentation\\api\\processor\\nonconformity\\testprocessdispatchescommandandreturnsoutput',
        3 => 'tests\\unit\\inspection\\presentation\\api\\processor\\nonconformity\\testprocessthrowsnotfoundwheninspectionmissing',
        4 => 'tests\\unit\\inspection\\presentation\\api\\processor\\nonconformity\\testprocessthrowsconflictwheninspectionclosed',
        5 => 'tests\\unit\\inspection\\presentation\\api\\processor\\nonconformity\\testprocessunwrapsnotfoundfrommessengerexception',
        6 => 'tests\\unit\\inspection\\presentation\\api\\processor\\nonconformity\\makeauthorizedprocessor',
        7 => 'tests\\unit\\inspection\\presentation\\api\\processor\\nonconformity\\makeinput',
        8 => 'tests\\unit\\inspection\\presentation\\api\\processor\\nonconformity\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Inspection/Presentation/Api/Processor/NonConformity/UpdateNonConformityStatusProcessorTest.php' => 
    array (
      0 => 'ad1340ab2c0be0b1c716b729de6dc3f30ad9c77a',
      1 => 
      array (
        0 => 'tests\\unit\\inspection\\presentation\\api\\processor\\nonconformity\\updatenonconformitystatusprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\inspection\\presentation\\api\\processor\\nonconformity\\testprocessthrowswhennotauthenticated',
        1 => 'tests\\unit\\inspection\\presentation\\api\\processor\\nonconformity\\testprocessthrowswhenurivariablesmissing',
        2 => 'tests\\unit\\inspection\\presentation\\api\\processor\\nonconformity\\testprocessdispatchescommandandreturnsoutput',
        3 => 'tests\\unit\\inspection\\presentation\\api\\processor\\nonconformity\\testprocessthrowsnotfoundwheninspectionmissing',
        4 => 'tests\\unit\\inspection\\presentation\\api\\processor\\nonconformity\\testprocessthrowsnotfoundwhennonconformitymissing',
        5 => 'tests\\unit\\inspection\\presentation\\api\\processor\\nonconformity\\testprocessthrowsconflictwhenalreadyresolved',
        6 => 'tests\\unit\\inspection\\presentation\\api\\processor\\nonconformity\\testprocessunwrapsnotfoundfrommessengerexception',
        7 => 'tests\\unit\\inspection\\presentation\\api\\processor\\nonconformity\\makeauthorizedprocessor',
        8 => 'tests\\unit\\inspection\\presentation\\api\\processor\\nonconformity\\makeinput',
        9 => 'tests\\unit\\inspection\\presentation\\api\\processor\\nonconformity\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Inspection/Presentation/Api/Provider/Checklist/ListChecklistsProviderTest.php' => 
    array (
      0 => 'fa6d79f05895fd11c9019d4a579e175ae73e2135',
      1 => 
      array (
        0 => 'tests\\unit\\inspection\\presentation\\api\\provider\\checklist\\listchecklistsprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\inspection\\presentation\\api\\provider\\checklist\\testprovidethrowswhennotauthenticated',
        1 => 'tests\\unit\\inspection\\presentation\\api\\provider\\checklist\\testprovidethrowswhenorganizationidmissing',
        2 => 'tests\\unit\\inspection\\presentation\\api\\provider\\checklist\\testprovidethrowswhenpermissiondenied',
        3 => 'tests\\unit\\inspection\\presentation\\api\\provider\\checklist\\testprovidereturnspaginatedchecklists',
        4 => 'tests\\unit\\inspection\\presentation\\api\\provider\\checklist\\testprovidepassesstatusfilterfromrequest',
        5 => 'tests\\unit\\inspection\\presentation\\api\\provider\\checklist\\testprovidethrowsbadrequestoninvalidargument',
        6 => 'tests\\unit\\inspection\\presentation\\api\\provider\\checklist\\testprovideunwrapsbadrequestfrommessengerexception',
        7 => 'tests\\unit\\inspection\\presentation\\api\\provider\\checklist\\testprovidepassessearchandsortingtoquery',
        8 => 'tests\\unit\\inspection\\presentation\\api\\provider\\checklist\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Inspection/Presentation/Api/Provider/Checklist/ListChecklistStatusesProviderTest.php' => 
    array (
      0 => 'a2d8528dfeb1dafe7365ff55cbac7b2261e67afc',
      1 => 
      array (
        0 => 'tests\\unit\\inspection\\presentation\\api\\provider\\checklist\\listcheckliststatusesprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\inspection\\presentation\\api\\provider\\checklist\\testprovidethrowswhennotauthenticated',
        1 => 'tests\\unit\\inspection\\presentation\\api\\provider\\checklist\\testprovidereturnssupportedcheckliststatuses',
        2 => 'tests\\unit\\inspection\\presentation\\api\\provider\\checklist\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Inspection/Presentation/Api/Provider/Inspection/GetInspectionProviderTest.php' => 
    array (
      0 => 'ca99f35d69ca30bd44428b0916fe36422ced9b62',
      1 => 
      array (
        0 => 'tests\\unit\\inspection\\presentation\\api\\provider\\inspection\\getinspectionprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\inspection\\presentation\\api\\provider\\inspection\\testprovidethrowswhennotauthenticated',
        1 => 'tests\\unit\\inspection\\presentation\\api\\provider\\inspection\\testprovidethrowswhenurivariablesmissing',
        2 => 'tests\\unit\\inspection\\presentation\\api\\provider\\inspection\\testprovidethrowswhenpermissiondenied',
        3 => 'tests\\unit\\inspection\\presentation\\api\\provider\\inspection\\testprovidereturnsinspectionoutput',
        4 => 'tests\\unit\\inspection\\presentation\\api\\provider\\inspection\\testprovidethrowsnotfoundwheninspectionmissing',
        5 => 'tests\\unit\\inspection\\presentation\\api\\provider\\inspection\\testprovideunwrapsnotfoundfrommessengerexception',
        6 => 'tests\\unit\\inspection\\presentation\\api\\provider\\inspection\\makeauthorizedprovider',
        7 => 'tests\\unit\\inspection\\presentation\\api\\provider\\inspection\\createoutputmapper',
        8 => 'tests\\unit\\inspection\\presentation\\api\\provider\\inspection\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Inspection/Presentation/Api/Provider/Inspection/ListInspectionResultsProviderTest.php' => 
    array (
      0 => '66a5abd3619b9b2a6d4c5d1ab25a9be6cb87f23b',
      1 => 
      array (
        0 => 'tests\\unit\\inspection\\presentation\\api\\provider\\inspection\\listinspectionresultsprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\inspection\\presentation\\api\\provider\\inspection\\testprovidethrowswhennotauthenticated',
        1 => 'tests\\unit\\inspection\\presentation\\api\\provider\\inspection\\testprovidereturnssupportedresults',
        2 => 'tests\\unit\\inspection\\presentation\\api\\provider\\inspection\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Inspection/Presentation/Api/Provider/Inspection/ListInspectionsProviderTest.php' => 
    array (
      0 => '0120dbed9458082a156099eaf1ccd598e39c5a39',
      1 => 
      array (
        0 => 'tests\\unit\\inspection\\presentation\\api\\provider\\inspection\\listinspectionsprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\inspection\\presentation\\api\\provider\\inspection\\testprovidethrowswhennotauthenticated',
        1 => 'tests\\unit\\inspection\\presentation\\api\\provider\\inspection\\testprovidethrowswhenorganizationidmissing',
        2 => 'tests\\unit\\inspection\\presentation\\api\\provider\\inspection\\testprovidethrowswhenpermissiondenied',
        3 => 'tests\\unit\\inspection\\presentation\\api\\provider\\inspection\\testprovidereturnspaginatedinspections',
        4 => 'tests\\unit\\inspection\\presentation\\api\\provider\\inspection\\testprovidepassesfilterparametersfromrequest',
        5 => 'tests\\unit\\inspection\\presentation\\api\\provider\\inspection\\testprovidepassesextendedfilterssearchandsorting',
        6 => 'tests\\unit\\inspection\\presentation\\api\\provider\\inspection\\testprovideusesfacilityidfromurivariables',
        7 => 'tests\\unit\\inspection\\presentation\\api\\provider\\inspection\\testprovidethrowsbadrequestoninvalidargument',
        8 => 'tests\\unit\\inspection\\presentation\\api\\provider\\inspection\\testprovideunwrapsbadrequestfrommessengerexception',
        9 => 'tests\\unit\\inspection\\presentation\\api\\provider\\inspection\\createsecurityuser',
        10 => 'tests\\unit\\inspection\\presentation\\api\\provider\\inspection\\createoutputmapper',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Inspection/Presentation/Api/Provider/Inspection/ListInspectionStatusesProviderTest.php' => 
    array (
      0 => 'a15322223fcfc49b2c09696d0bf889b11dadbcd4',
      1 => 
      array (
        0 => 'tests\\unit\\inspection\\presentation\\api\\provider\\inspection\\listinspectionstatusesprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\inspection\\presentation\\api\\provider\\inspection\\testprovidethrowswhennotauthenticated',
        1 => 'tests\\unit\\inspection\\presentation\\api\\provider\\inspection\\testprovidereturnssupportedstatuses',
        2 => 'tests\\unit\\inspection\\presentation\\api\\provider\\inspection\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Inspection/Presentation/Api/Provider/Inspection/ListInspectorTypesProviderTest.php' => 
    array (
      0 => '4199e999942bda4a38b3d3b88dbca5179eb3141e',
      1 => 
      array (
        0 => 'tests\\unit\\inspection\\presentation\\api\\provider\\inspection\\listinspectortypesprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\inspection\\presentation\\api\\provider\\inspection\\testprovidethrowswhennotauthenticated',
        1 => 'tests\\unit\\inspection\\presentation\\api\\provider\\inspection\\testprovidereturnssupportedinspectortypes',
        2 => 'tests\\unit\\inspection\\presentation\\api\\provider\\inspection\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Inspection/Presentation/Api/Provider/NonConformity/GetNonConformityProviderTest.php' => 
    array (
      0 => 'a0ca89cbfd9c9e6713ceefa97eaff225d95a72cb',
      1 => 
      array (
        0 => 'tests\\unit\\inspection\\presentation\\api\\provider\\nonconformity\\getnonconformityprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\inspection\\presentation\\api\\provider\\nonconformity\\testprovidethrowswhenurivariablesmissing',
        1 => 'tests\\unit\\inspection\\presentation\\api\\provider\\nonconformity\\testprovidereturnsnonconformityoutput',
        2 => 'tests\\unit\\inspection\\presentation\\api\\provider\\nonconformity\\testprovidethrowswhennotauthenticated',
        3 => 'tests\\unit\\inspection\\presentation\\api\\provider\\nonconformity\\testprovidethrowsnotfoundwhenmissing',
        4 => 'tests\\unit\\inspection\\presentation\\api\\provider\\nonconformity\\testprovideunwrapsinspectionnotfoundfrommessengerexception',
        5 => 'tests\\unit\\inspection\\presentation\\api\\provider\\nonconformity\\makeprovider',
        6 => 'tests\\unit\\inspection\\presentation\\api\\provider\\nonconformity\\makeresult',
        7 => 'tests\\unit\\inspection\\presentation\\api\\provider\\nonconformity\\makesecurity',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Inspection/Presentation/Api/Provider/NonConformity/ListNonConformitiesProviderTest.php' => 
    array (
      0 => '48fbe08c2a524ffc29ab5a25dd7e93003d66f991',
      1 => 
      array (
        0 => 'tests\\unit\\inspection\\presentation\\api\\provider\\nonconformity\\listnonconformitiesprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\inspection\\presentation\\api\\provider\\nonconformity\\testprovidethrowswhennotauthenticated',
        1 => 'tests\\unit\\inspection\\presentation\\api\\provider\\nonconformity\\testprovidethrowswhenurivariablesmissing',
        2 => 'tests\\unit\\inspection\\presentation\\api\\provider\\nonconformity\\testprovidethrowswhenpermissiondenied',
        3 => 'tests\\unit\\inspection\\presentation\\api\\provider\\nonconformity\\testprovidereturnspaginatednonconformities',
        4 => 'tests\\unit\\inspection\\presentation\\api\\provider\\nonconformity\\testprovidepassesfiltersfromrequest',
        5 => 'tests\\unit\\inspection\\presentation\\api\\provider\\nonconformity\\testprovidethrowsnotfoundwheninspectionmissing',
        6 => 'tests\\unit\\inspection\\presentation\\api\\provider\\nonconformity\\testprovideunwrapsnotfoundfrommessengerexception',
        7 => 'tests\\unit\\inspection\\presentation\\api\\provider\\nonconformity\\testprovidethrowsbadrequestoninvalidargument',
        8 => 'tests\\unit\\inspection\\presentation\\api\\provider\\nonconformity\\testprovidepassessearchandsortingtoquery',
        9 => 'tests\\unit\\inspection\\presentation\\api\\provider\\nonconformity\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Inspection/Presentation/Api/Provider/NonConformity/ListNonConformityStatusesProviderTest.php' => 
    array (
      0 => '606e2df52f7b7f9aaa43995635d289e95742448b',
      1 => 
      array (
        0 => 'tests\\unit\\inspection\\presentation\\api\\provider\\nonconformity\\listnonconformitystatusesprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\inspection\\presentation\\api\\provider\\nonconformity\\testprovidethrowswhennotauthenticated',
        1 => 'tests\\unit\\inspection\\presentation\\api\\provider\\nonconformity\\testprovidereturnssupportednonconformitystatuses',
        2 => 'tests\\unit\\inspection\\presentation\\api\\provider\\nonconformity\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Intervention/Application/Service/InterventionChangeApplicationTest.php' => 
    array (
      0 => '13491d6edb41eb3ac50598ae8ccdd2f2a9b63903',
      1 => 
      array (
        0 => 'tests\\unit\\intervention\\application\\service\\interventionchangeapplicationtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\intervention\\application\\service\\testdelegatespatchtoowningmodule',
        1 => 'tests\\unit\\intervention\\application\\service\\testrejectsunsupportedresource',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Intervention/Application/Service/InterventionIssueFinderTest.php' => 
    array (
      0 => '08a59c1c394bb42e867e278332196e010767c399',
      1 => 
      array (
        0 => 'tests\\unit\\intervention\\application\\service\\interventionissuefindertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\intervention\\application\\service\\emptyscoperules',
        1 => 'tests\\unit\\intervention\\application\\service\\itappliesinterventiontypevalidationpolicy',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Intervention/Application/Service/InterventionMemberPolicyTest.php' => 
    array (
      0 => 'be26434b0b6b3657f74464394f05625d32162409',
      1 => 
      array (
        0 => 'tests\\unit\\intervention\\application\\service\\interventionmemberpolicytest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\intervention\\application\\service\\itacceptstheactiveresponsiblemember',
        1 => 'tests\\unit\\intervention\\application\\service\\itrejectsaresponsiblememberfromanotherorganization',
        2 => 'tests\\unit\\intervention\\application\\service\\itrejectssubmissionbyanothermember',
        3 => 'tests\\unit\\intervention\\application\\service\\itallowstheassignedmembertoexecuteaworkitem',
        4 => 'tests\\unit\\intervention\\application\\service\\itrejectsexecutionofanothermembersassignedworkitem',
        5 => 'tests\\unit\\intervention\\application\\service\\itallowsainterventionparticipanttomutateinterventionresources',
        6 => 'tests\\unit\\intervention\\application\\service\\itrejectsanonparticipantmutatinginterventionresources',
        7 => 'tests\\unit\\intervention\\application\\service\\member',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Intervention/Application/Service/InterventionNotificationServiceTest.php' => 
    array (
      0 => '826b9df3326c560900e7fae69195a7d63ccc5ed3',
      1 => 
      array (
        0 => 'tests\\unit\\intervention\\application\\service\\interventionnotificationservicetest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\intervention\\application\\service\\itsendsaninappassignmentnotification',
        1 => 'tests\\unit\\intervention\\application\\service\\itdoesnotnotifywhentheassignmentcategoryisdisabled',
        2 => 'tests\\unit\\intervention\\application\\service\\itdoesnotnotifywheninappdeliveryisdisabled',
        3 => 'tests\\unit\\intervention\\application\\service\\itdoesnotfailtheinterventionwhennotificationdeliveryfails',
        4 => 'tests\\unit\\intervention\\application\\service\\member',
        5 => 'tests\\unit\\intervention\\application\\service\\policy',
        6 => 'tests\\unit\\intervention\\application\\service\\sent',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Intervention/Application/Service/InterventionResourceManagerTest.php' => 
    array (
      0 => '34ca883c5303e6435e73ffa60ee0251b81058998',
      1 => 
      array (
        0 => 'tests\\unit\\intervention\\application\\service\\interventionresourcemanagertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\intervention\\application\\service\\testrejectsanalreadysynchronizedofflinecreation',
        1 => 'tests\\unit\\intervention\\application\\service\\testrequiresplanningpermissionfordraftinterventionresources',
        2 => 'tests\\unit\\intervention\\application\\service\\testrequiresexecutionpermissionforplannedinterventionresources',
        3 => 'tests\\unit\\intervention\\application\\service\\testrejectsmutationafterinterventionsubmission',
        4 => 'tests\\unit\\intervention\\application\\service\\testchecksinterventionmembershipbeforeexecutionmutation',
        5 => 'tests\\unit\\intervention\\application\\service\\managerwithinterventionstatus',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Intervention/Application/Service/InterventionResourcePatchValidationTest.php' => 
    array (
      0 => '9fac44df0948f5c3c4a882b036dd135504bbe0da',
      1 => 
      array (
        0 => 'tests\\unit\\intervention\\application\\service\\interventionresourcepatchvalidationtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\intervention\\application\\service\\itrejectsunknownequipmentpatchfields',
        1 => 'tests\\unit\\intervention\\application\\service\\itrejectsunknownfacilitypatchfields',
        2 => 'tests\\unit\\intervention\\application\\service\\itrejectsunknowninspectionpatchfields',
        3 => 'tests\\unit\\intervention\\application\\service\\assertrejectsunknownfield',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Intervention/Application/UseCase/Command/Publication/ExecutePublication/ExecutePublicationHandlerTest.php' => 
    array (
      0 => '53da08c3a96680371cab8c0f9da7986ed339c214',
      1 => 
      array (
        0 => 'tests\\unit\\intervention\\application\\usecase\\command\\publication\\executepublication\\executepublicationhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\intervention\\application\\usecase\\command\\publication\\executepublication\\testpublishesreadyintervention',
        1 => 'tests\\unit\\intervention\\application\\usecase\\command\\publication\\executepublication\\testchangedinterventionmarkspublicationfailedwithoutpublishing',
        2 => 'tests\\unit\\intervention\\application\\usecase\\command\\publication\\executepublication\\publication',
        3 => 'tests\\unit\\intervention\\application\\usecase\\command\\publication\\executepublication\\context',
        4 => 'tests\\unit\\intervention\\application\\usecase\\command\\publication\\executepublication\\repository',
        5 => 'tests\\unit\\intervention\\application\\usecase\\command\\publication\\executepublication\\handler',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Intervention/Application/UseCase/Command/Publication/RequestPublication/RequestPublicationHandlerTest.php' => 
    array (
      0 => 'cb723a25710c41f7df368f0abf0714d640e98431',
      1 => 
      array (
        0 => 'tests\\unit\\intervention\\application\\usecase\\command\\publication\\requestpublication\\requestpublicationhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\intervention\\application\\usecase\\command\\publication\\requestpublication\\testcreatesandqueuespendingpublication',
        1 => 'tests\\unit\\intervention\\application\\usecase\\command\\publication\\requestpublication\\testidempotentpendingpublicationisqueuedagain',
        2 => 'tests\\unit\\intervention\\application\\usecase\\command\\publication\\requestpublication\\testblockerpreventspublicationcreation',
        3 => 'tests\\unit\\intervention\\application\\usecase\\command\\publication\\requestpublication\\testfailedpublicationisresetandqueuedforretry',
        4 => 'tests\\unit\\intervention\\application\\usecase\\command\\publication\\requestpublication\\command',
        5 => 'tests\\unit\\intervention\\application\\usecase\\command\\publication\\requestpublication\\context',
        6 => 'tests\\unit\\intervention\\application\\usecase\\command\\publication\\requestpublication\\publication',
        7 => 'tests\\unit\\intervention\\application\\usecase\\command\\publication\\requestpublication\\repository',
        8 => 'tests\\unit\\intervention\\application\\usecase\\command\\publication\\requestpublication\\queue',
        9 => 'tests\\unit\\intervention\\application\\usecase\\command\\publication\\requestpublication\\resources',
        10 => 'tests\\unit\\intervention\\application\\usecase\\command\\publication\\requestpublication\\handler',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Intervention/Application/UseCase/Workflow/InterventionWorkflowHandlersTest.php' => 
    array (
      0 => 'b4a540b4cc036d135f38cf3379b005a7e5964e58',
      1 => 
      array (
        0 => 'tests\\unit\\intervention\\application\\usecase\\workflow\\interventionworkflowhandlerstest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\intervention\\application\\usecase\\workflow\\itdelegatesareviewedchangemutationthroughtheworkflowport',
        1 => 'tests\\unit\\intervention\\application\\usecase\\workflow\\itrequiresplanpermissionwhencreatingaintervention',
        2 => 'tests\\unit\\intervention\\application\\usecase\\workflow\\itrejectsexecutionbyanonparticipantwithorganizationpermission',
        3 => 'tests\\unit\\intervention\\application\\usecase\\workflow\\itauthorizesandreturnsaworkflowview',
        4 => 'tests\\unit\\intervention\\application\\usecase\\workflow\\itauthorizesachildcollectionagainstitsinterventionorganization',
        5 => 'tests\\unit\\intervention\\application\\usecase\\workflow\\memberpolicy',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Intervention/Domain/Model/Intervention/InterventionTest.php' => 
    array (
      0 => '2ff6b2bfa593690186f81fa71205a80c3735a03f',
      1 => 
      array (
        0 => 'tests\\unit\\intervention\\domain\\model\\intervention\\interventiontest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\intervention\\domain\\model\\intervention\\itappliesonerepresentationpatchwithonerevisionincrement',
        1 => 'tests\\unit\\intervention\\domain\\model\\intervention\\itrequirespreparedscopebeforeplanning',
        2 => 'tests\\unit\\intervention\\domain\\model\\intervention\\itfreezesplanningfieldsafterplanning',
        3 => 'tests\\unit\\intervention\\domain\\model\\intervention\\itrequiresareviewnotewhenrequestingchanges',
        4 => 'tests\\unit\\intervention\\domain\\model\\intervention\\intervention',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Intervention/Domain/Service/InterventionChangePolicyTest.php' => 
    array (
      0 => '7816b6a9c197f07ce23fb0c5cdd56d5f52b55c3e',
      1 => 
      array (
        0 => 'tests\\unit\\intervention\\domain\\service\\interventionchangepolicytest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\intervention\\domain\\service\\setup',
        1 => 'tests\\unit\\intervention\\domain\\service\\itallowsfieldagentstocreateandeditchangesduringexecution',
        2 => 'tests\\unit\\intervention\\domain\\service\\itallowsreviewerstorejectbutnoteditsubmittedchanges',
        3 => 'tests\\unit\\intervention\\domain\\service\\itrejectsnewchangesaftersubmission',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Intervention/Domain/Service/InterventionTransitionPolicyTest.php' => 
    array (
      0 => '6b6152a3f029da120ab37be45cbb99ac175b1d77',
      1 => 
      array (
        0 => 'tests\\unit\\intervention\\domain\\service\\interventiontransitionpolicytest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\intervention\\domain\\service\\allowedtransitions',
        1 => 'tests\\unit\\intervention\\domain\\service\\itallowsworkflowtransitions',
        2 => 'tests\\unit\\intervention\\domain\\service\\itkeepspublishedinterventionsimmutable',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Intervention/Presentation/Api/Factory/InterventionWorkItemOutputFactoryTest.php' => 
    array (
      0 => '6ad779bd6ee059d8e58cee53ba0d5d1b7c66b42b',
      1 => 
      array (
        0 => 'tests\\unit\\intervention\\presentation\\api\\factory\\interventionworkitemoutputfactorytest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\intervention\\presentation\\api\\factory\\testresolvesassigneeidentityfrommemberanduser',
        1 => 'tests\\unit\\intervention\\presentation\\api\\factory\\testunassignedworkitemhasnoprofile',
        2 => 'tests\\unit\\intervention\\presentation\\api\\factory\\testunresolvablememberyieldsnullprofilewithoutfailing',
        3 => 'tests\\unit\\intervention\\presentation\\api\\factory\\testfallsbacktomemberuseridwhenuserunresolved',
        4 => 'tests\\unit\\intervention\\presentation\\api\\factory\\testresolvesmemberanduseronlyonceacrosssharedassignees',
        5 => 'tests\\unit\\intervention\\presentation\\api\\factory\\testresolvesfacilitytarget',
        6 => 'tests\\unit\\intervention\\presentation\\api\\factory\\testresolvesequipmenttargetwithcomposedlabel',
        7 => 'tests\\unit\\intervention\\presentation\\api\\factory\\testfreetexttargethasnosummary',
        8 => 'tests\\unit\\intervention\\presentation\\api\\factory\\testunresolvabletargetyieldsnullsummarywithoutfailing',
        9 => 'tests\\unit\\intervention\\presentation\\api\\factory\\view',
        10 => 'tests\\unit\\intervention\\presentation\\api\\factory\\querybus',
        11 => 'tests\\unit\\intervention\\presentation\\api\\factory\\memberresult',
        12 => 'tests\\unit\\intervention\\presentation\\api\\factory\\userresult',
        13 => 'tests\\unit\\intervention\\presentation\\api\\factory\\facilityresult',
        14 => 'tests\\unit\\intervention\\presentation\\api\\factory\\equipmentresult',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Intervention/Presentation/Api/Processor/InterventionProcessorTest.php' => 
    array (
      0 => '95c1662bf3f21fe0e98522eccdddf1f61b3c7396',
      1 => 
      array (
        0 => 'tests\\unit\\intervention\\presentation\\api\\processor\\interventionprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\intervention\\presentation\\api\\processor\\itpreservesexplicitnullsinthemergepatchcommand',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Notification/Application/UseCase/Command/Notification/MarkNotificationAsRead/MarkNotificationAsReadHandlerTest.php' => 
    array (
      0 => '58fa0b21c578ceaf5ab5c6b3d5f089092a07ac7c',
      1 => 
      array (
        0 => 'tests\\unit\\notification\\application\\usecase\\command\\notification\\marknotificationasread\\marknotificationasreadhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\notification\\application\\usecase\\command\\notification\\marknotificationasread\\testinvokemarksunreadnotificationasread',
        1 => 'tests\\unit\\notification\\application\\usecase\\command\\notification\\marknotificationasread\\testinvokedoesnotpersistwhenalreadyread',
        2 => 'tests\\unit\\notification\\application\\usecase\\command\\notification\\marknotificationasread\\testinvokethrowsnotfoundwhenidisinvalid',
        3 => 'tests\\unit\\notification\\application\\usecase\\command\\notification\\marknotificationasread\\testinvokethrowsnotfoundwhennotificationdoesnotexistforuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Notification/Application/UseCase/Command/Notification/SendNotification/SendNotificationHandlerTest.php' => 
    array (
      0 => '08764f2b386e0ba26be245d464f3e6d1993906e6',
      1 => 
      array (
        0 => 'tests\\unit\\notification\\application\\usecase\\command\\notification\\sendnotification\\sendnotificationhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\notification\\application\\usecase\\command\\notification\\sendnotification\\testinvokesendsemailnotificationwithephemeraldeliverypayload',
        1 => 'tests\\unit\\notification\\application\\usecase\\command\\notification\\sendnotification\\testinvokedoesnotfailwhenemailchannelthrowsexception',
        2 => 'tests\\unit\\notification\\application\\usecase\\command\\notification\\sendnotification\\testinvokethrowswhenmercurechannelhasnouserid',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Notification/Application/UseCase/Query/Notification/ListUserNotifications/ListUserNotificationsHandlerTest.php' => 
    array (
      0 => 'e911802cbe365c6d8e7847556bb391e1821fe93e',
      1 => 
      array (
        0 => 'tests\\unit\\notification\\application\\usecase\\query\\notification\\listusernotifications\\listusernotificationshandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\notification\\application\\usecase\\query\\notification\\listusernotifications\\testinvokeappliesreadvisibilitymaskforlowvaluecategories',
        1 => 'tests\\unit\\notification\\application\\usecase\\query\\notification\\listusernotifications\\testinvokeskipsreadvisibilitymaskwhenonlyunreadisrequested',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Notification/Infrastructure/Adapter/Channel/EmailNotificationChannelAdapterTest.php' => 
    array (
      0 => '38edf848de02ef04adf5bee76e266eb1e9bc22bd',
      1 => 
      array (
        0 => 'tests\\unit\\notification\\infrastructure\\adapter\\channel\\emailnotificationchanneladaptertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\notification\\infrastructure\\adapter\\channel\\testsendrendersdefaulttemplatewhennotemplateprovided',
        1 => 'tests\\unit\\notification\\infrastructure\\adapter\\channel\\testsendrenderscustomtemplatewithcontext',
        2 => 'tests\\unit\\notification\\infrastructure\\adapter\\channel\\testsendskipswhennotificationhasnorecipientemail',
        3 => 'tests\\unit\\notification\\infrastructure\\adapter\\channel\\createnotification',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Notification/Presentation/Api/Processor/Notification/MarkNotificationAsReadProcessorTest.php' => 
    array (
      0 => '75b3ac9b83ad908fee9d72eea0d438f086467081',
      1 => 
      array (
        0 => 'tests\\unit\\notification\\presentation\\api\\processor\\notification\\marknotificationasreadprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\notification\\presentation\\api\\processor\\notification\\testprocessthrowswhenunauthenticated',
        1 => 'tests\\unit\\notification\\presentation\\api\\processor\\notification\\testprocessthrowswhenidismissing',
        2 => 'tests\\unit\\notification\\presentation\\api\\processor\\notification\\testprocessdispatchescommandandmapsoutput',
        3 => 'tests\\unit\\notification\\presentation\\api\\processor\\notification\\testprocessmapsnestednotfoundexception',
        4 => 'tests\\unit\\notification\\presentation\\api\\processor\\notification\\testprocessrethrowsnestedinvalidvalueexception',
        5 => 'tests\\unit\\notification\\presentation\\api\\processor\\notification\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Notification/Presentation/Api/Provider/Notification/GetNotificationProviderTest.php' => 
    array (
      0 => '55d151cb2fedac89d8eed53cc5b6fc949e18a6af',
      1 => 
      array (
        0 => 'tests\\unit\\notification\\presentation\\api\\provider\\notification\\getnotificationprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\notification\\presentation\\api\\provider\\notification\\testprovidethrowswhenunauthenticated',
        1 => 'tests\\unit\\notification\\presentation\\api\\provider\\notification\\testprovidethrowswhenidismissing',
        2 => 'tests\\unit\\notification\\presentation\\api\\provider\\notification\\testprovidemapsresulttooutput',
        3 => 'tests\\unit\\notification\\presentation\\api\\provider\\notification\\testprovidemapsnestednotfoundtohttpnotfound',
        4 => 'tests\\unit\\notification\\presentation\\api\\provider\\notification\\testproviderethrowsnestedinvalidvalueexception',
        5 => 'tests\\unit\\notification\\presentation\\api\\provider\\notification\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Notification/Presentation/Api/Provider/Notification/ListNotificationsProviderTest.php' => 
    array (
      0 => 'fe7dee81e7e75e5f64365f682e59c0457b76e8b0',
      1 => 
      array (
        0 => 'tests\\unit\\notification\\presentation\\api\\provider\\notification\\listnotificationsprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\notification\\presentation\\api\\provider\\notification\\testprovidethrowswhenunauthenticated',
        1 => 'tests\\unit\\notification\\presentation\\api\\provider\\notification\\testprovidemapsnotificationsandappliesfilters',
        2 => 'tests\\unit\\notification\\presentation\\api\\provider\\notification\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Application/Service/OidcClaimsBuilderTest.php' => 
    array (
      0 => '84752589fd343b8777c2b898938796db76d8856f',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\application\\service\\oidcclaimsbuildertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\application\\service\\testbuilduserinfoclaimswithprofileandemailscopes',
        1 => 'tests\\unit\\oauth\\application\\service\\testbuildidtokenclaimsincludesauthtime',
        2 => 'tests\\unit\\oauth\\application\\service\\testbuildclaimsskipsoptionalscopes',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Application/UseCase/Command/Client/ActivateClient/ActivateClientHandlerTest.php' => 
    array (
      0 => '1acc3796c2f07d43ccaec928b56de399b3b7c363',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\application\\usecase\\command\\client\\activateclient\\activateclienthandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\application\\usecase\\command\\client\\activateclient\\testinvokeactivatesclient',
        1 => 'tests\\unit\\oauth\\application\\usecase\\command\\client\\activateclient\\testinvokethrowsexceptionwhenclientnotfound',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Application/UseCase/Command/Client/DeactivateClient/DeactivateClientHandlerTest.php' => 
    array (
      0 => '2e1babecad7db938af7e49394fb34aa88d5352bb',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\application\\usecase\\command\\client\\deactivateclient\\deactivateclienthandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\application\\usecase\\command\\client\\deactivateclient\\testinvokedeactivatesclient',
        1 => 'tests\\unit\\oauth\\application\\usecase\\command\\client\\deactivateclient\\testinvokethrowsexceptionwhenclientnotfound',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Application/UseCase/Command/Client/DeleteClient/DeleteClientHandlerTest.php' => 
    array (
      0 => '4592ccc27a652f49612b1b41696ac98660a1db12',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\application\\usecase\\command\\client\\deleteclient\\deleteclienthandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\application\\usecase\\command\\client\\deleteclient\\testinvokedeletesclient',
        1 => 'tests\\unit\\oauth\\application\\usecase\\command\\client\\deleteclient\\testinvokethrowsexceptionwhenclientnotfound',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Application/UseCase/Command/Client/RegenerateClientSecret/RegenerateClientSecretHandlerTest.php' => 
    array (
      0 => 'cd67f625fe0179f8c6c8f1f1e1cd5311811c4676',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\application\\usecase\\command\\client\\regenerateclientsecret\\regenerateclientsecrethandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\application\\usecase\\command\\client\\regenerateclientsecret\\testinvokeregeneratesclientsecret',
        1 => 'tests\\unit\\oauth\\application\\usecase\\command\\client\\regenerateclientsecret\\testinvokethrowsexceptionwhenclientnotfound',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Application/UseCase/Command/Client/RegisterClient/RegisterClientHandlerTest.php' => 
    array (
      0 => '14cd3cb19dabfd0f78b082c8cb15c1d4f820a84c',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\application\\usecase\\command\\client\\registerclient\\registerclienthandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\application\\usecase\\command\\client\\registerclient\\testinvokeregistersnewclient',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Application/UseCase/Command/Client/UpdateClientDetails/UpdateClientDetailsHandlerTest.php' => 
    array (
      0 => 'ed1f35940c61e4a84744283650c0da2a282f07ba',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\application\\usecase\\command\\client\\updateclientdetails\\updateclientdetailshandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\application\\usecase\\command\\client\\updateclientdetails\\testinvokeupdatesclientdetails',
        1 => 'tests\\unit\\oauth\\application\\usecase\\command\\client\\updateclientdetails\\testinvokethrowsexceptionwhenclientnotfound',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Application/UseCase/Command/Consent/GrantConsent/GrantConsentHandlerTest.php' => 
    array (
      0 => '48ab24225352bc499042ef5a8fc0bbe47c7cb6f1',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\application\\usecase\\command\\consent\\grantconsent\\grantconsenthandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\application\\usecase\\command\\consent\\grantconsent\\testinvokecreatesnewconsentwhennoneexists',
        1 => 'tests\\unit\\oauth\\application\\usecase\\command\\consent\\grantconsent\\testinvokeupdatesexistingconsent',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Application/UseCase/Command/Token/IssueToken/IssueTokenHandlerTest.php' => 
    array (
      0 => 'd5b91fcb358ada7f5f77f0ddfcd3f8d1d4aac111',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\application\\usecase\\command\\token\\issuetoken\\issuetokenhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\application\\usecase\\command\\token\\issuetoken\\setup',
        1 => 'tests\\unit\\oauth\\application\\usecase\\command\\token\\issuetoken\\testhandlesuccessfullyissuestoken',
        2 => 'tests\\unit\\oauth\\application\\usecase\\command\\token\\issuetoken\\testhandlethrowsexceptiononfailure',
        3 => 'tests\\unit\\oauth\\application\\usecase\\command\\token\\issuetoken\\testhandleissuesidtokenforauthorizationcode',
        4 => 'tests\\unit\\oauth\\application\\usecase\\command\\token\\issuetoken\\testhandleissuesidtokenforrefreshtoken',
        5 => 'tests\\unit\\oauth\\application\\usecase\\command\\token\\issuetoken\\testhandleskipsidtokenwithoutopenidscope',
        6 => 'tests\\unit\\oauth\\application\\usecase\\command\\token\\issuetoken\\testhandlefallsbacktoauthcodewhenencryptedmissing',
        7 => 'tests\\unit\\oauth\\application\\usecase\\command\\token\\issuetoken\\testhandlerefreshtokenwithoutaccesstokenskipsidtoken',
        8 => 'tests\\unit\\oauth\\application\\usecase\\command\\token\\issuetoken\\testhandlethrowswhenoidcusermissing',
        9 => 'tests\\unit\\oauth\\application\\usecase\\command\\token\\issuetoken\\expectnocalls',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Application/UseCase/Command/Token/RevokeToken/RevokeTokenHandlerTest.php' => 
    array (
      0 => 'ae9816b39db8b4682d16d51c0fbbee68c6d4395e',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\application\\usecase\\command\\token\\revoketoken\\revoketokenhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\application\\usecase\\command\\token\\revoketoken\\testinvokerevokesrefreshtokenwithhint',
        1 => 'tests\\unit\\oauth\\application\\usecase\\command\\token\\revoketoken\\testinvokerevokesaccesstokenwithhint',
        2 => 'tests\\unit\\oauth\\application\\usecase\\command\\token\\revoketoken\\testinvokefallsbackwhenhintfails',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Application/UseCase/Query/Client/GetClient/GetClientHandlerTest.php' => 
    array (
      0 => '592856e7a56cde6dc63a61f088260d9186fdb1e0',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\application\\usecase\\query\\client\\getclient\\getclienthandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\application\\usecase\\query\\client\\getclient\\testinvokereturnsclientresult',
        1 => 'tests\\unit\\oauth\\application\\usecase\\query\\client\\getclient\\testinvokethrowsexceptionwhenclientnotfound',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Application/UseCase/Query/Client/ListClients/ListClientsHandlerTest.php' => 
    array (
      0 => 'f68c8984f798de222361346b417688b8c6f05720',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\application\\usecase\\query\\client\\listclients\\listclientshandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\application\\usecase\\query\\client\\listclients\\testinvokereturnspaginatedresult',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Application/UseCase/Query/Client/ValidateClientCredentials/ValidateClientCredentialsHandlerTest.php' => 
    array (
      0 => 'ff9639b4bff21b2cc7e01ffd37d9ee22a456398b',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\application\\usecase\\query\\client\\validateclientcredentials\\validateclientcredentialshandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\application\\usecase\\query\\client\\validateclientcredentials\\testinvokereturnsvalidresultwhencredentialsarecorrect',
        1 => 'tests\\unit\\oauth\\application\\usecase\\query\\client\\validateclientcredentials\\testinvokereturnsinvalidresultwhenclientnotfound',
        2 => 'tests\\unit\\oauth\\application\\usecase\\query\\client\\validateclientcredentials\\testinvokereturnsinvalidresultwhensecretisinvalid',
        3 => 'tests\\unit\\oauth\\application\\usecase\\query\\client\\validateclientcredentials\\testinvokereturnsinvalidresultwhenclientidinvalid',
        4 => 'tests\\unit\\oauth\\application\\usecase\\query\\client\\validateclientcredentials\\testinvokereturnsinvalidresultwhenclientinactive',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Application/UseCase/Query/Consent/CheckConsent/CheckConsentHandlerTest.php' => 
    array (
      0 => '919be2d38c960d26dd1fedda51207404c6f5fd62',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\application\\usecase\\query\\consent\\checkconsent\\checkconsenthandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\application\\usecase\\query\\consent\\checkconsent\\testinvokereturnsfalsewhennoconsent',
        1 => 'tests\\unit\\oauth\\application\\usecase\\query\\consent\\checkconsent\\testinvokereturnstruewhenallscopesgranted',
        2 => 'tests\\unit\\oauth\\application\\usecase\\query\\consent\\checkconsent\\testinvokeidentifiesmissingscopes',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Application/UseCase/Query/Token/IntrospectToken/IntrospectTokenHandlerTest.php' => 
    array (
      0 => '476ce17aad9f5f55498c24303e5eba589e93bb92',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\application\\usecase\\query\\token\\introspecttoken\\introspecttokenhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\application\\usecase\\query\\token\\introspecttoken\\testintrospectaccesstokenreturnsinactivewhenvalidationfails',
        1 => 'tests\\unit\\oauth\\application\\usecase\\query\\token\\introspecttoken\\testintrospectaccesstokenreturnsinactivewhentokenmissing',
        2 => 'tests\\unit\\oauth\\application\\usecase\\query\\token\\introspecttoken\\testintrospectaccesstokenreturnsinactivewhenparsefails',
        3 => 'tests\\unit\\oauth\\application\\usecase\\query\\token\\introspecttoken\\testintrospectaccesstokenusescache',
        4 => 'tests\\unit\\oauth\\application\\usecase\\query\\token\\introspecttoken\\testintrospectaccesstokencachesresult',
        5 => 'tests\\unit\\oauth\\application\\usecase\\query\\token\\introspecttoken\\testintrospectrefreshtokenusesencryptedlookup',
        6 => 'tests\\unit\\oauth\\application\\usecase\\query\\token\\introspecttoken\\testintrospectrefreshtokenfallsbacktofind',
        7 => 'tests\\unit\\oauth\\application\\usecase\\query\\token\\introspecttoken\\testintrospectrefreshtokenreturnsinactivewhenrevoked',
        8 => 'tests\\unit\\oauth\\application\\usecase\\query\\token\\introspecttoken\\testinvokereturnsinactiveonexception',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Application/UseCase/Query/Token/RefreshToken/RefreshTokenHandlerTest.php' => 
    array (
      0 => 'b6f83964e60aa076f563214c6dce5389fa9862d2',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\application\\usecase\\query\\token\\refreshtoken\\refreshtokenhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\application\\usecase\\query\\token\\refreshtoken\\testreturnsfailurewhentokenmissing',
        1 => 'tests\\unit\\oauth\\application\\usecase\\query\\token\\refreshtoken\\testreturnsfailurewhentokeninvalid',
        2 => 'tests\\unit\\oauth\\application\\usecase\\query\\token\\refreshtoken\\testreturnsfailurewhenuserinactive',
        3 => 'tests\\unit\\oauth\\application\\usecase\\query\\token\\refreshtoken\\testreturnsfailurewhenusernotfound',
        4 => 'tests\\unit\\oauth\\application\\usecase\\query\\token\\refreshtoken\\testreturnsfailurewhenuseridmissingintoken',
        5 => 'tests\\unit\\oauth\\application\\usecase\\query\\token\\refreshtoken\\testreturnsfailurewhenuserlookupfails',
        6 => 'tests\\unit\\oauth\\application\\usecase\\query\\token\\refreshtoken\\testreturnssuccesswhenvalid',
        7 => 'tests\\unit\\oauth\\application\\usecase\\query\\token\\refreshtoken\\createuserview',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Application/UseCase/Query/Token/RefreshToken/RefreshTokenResultTest.php' => 
    array (
      0 => '987792d73ffd296654d5ed2e274ef4b82b43f41e',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\application\\usecase\\query\\token\\refreshtoken\\refreshtokenresulttest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\application\\usecase\\query\\token\\refreshtoken\\testfailedfactory',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Application/UseCase/Query/Token/ValidateToken/ValidateTokenHandlerTest.php' => 
    array (
      0 => '39f73eeedcb8b1c91e5ecc149dccfd92dbf6b54b',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\application\\usecase\\query\\token\\validatetoken\\validatetokenhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\application\\usecase\\query\\token\\validatetoken\\testinvokereturnsinvalidwhenvalidationfails',
        1 => 'tests\\unit\\oauth\\application\\usecase\\query\\token\\validatetoken\\testinvokereturnsinvalidwhenparsefails',
        2 => 'tests\\unit\\oauth\\application\\usecase\\query\\token\\validatetoken\\testinvokereturnsinvalidwhentokenidmissing',
        3 => 'tests\\unit\\oauth\\application\\usecase\\query\\token\\validatetoken\\testinvokereturnsinvalidwhentokennotfound',
        4 => 'tests\\unit\\oauth\\application\\usecase\\query\\token\\validatetoken\\testinvokereturnsinvalidwhentokenrevoked',
        5 => 'tests\\unit\\oauth\\application\\usecase\\query\\token\\validatetoken\\testinvokereturnsinvalidwhentokenexpired',
        6 => 'tests\\unit\\oauth\\application\\usecase\\query\\token\\validatetoken\\testinvokereturnsvalidwhentokenactive',
        7 => 'tests\\unit\\oauth\\application\\usecase\\query\\token\\validatetoken\\testinvokereturnsinvalidwhenexceptionthrown',
        8 => 'tests\\unit\\oauth\\application\\usecase\\query\\token\\validatetoken\\createaccesstoken',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Application/UseCase/Query/Token/ValidateToken/ValidateTokenResultTest.php' => 
    array (
      0 => '7b7ffa8c5de784f5caf11b5095fac4649d7cdc9f',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\application\\usecase\\query\\token\\validatetoken\\validatetokenresulttest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\application\\usecase\\query\\token\\validatetoken\\testinvalidfactory',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Domain/Event/Client/ClientActivatedEventTest.php' => 
    array (
      0 => '1b47c51fda5caf21695a5fc9f0be571b82591a30',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\event\\client\\clientactivatedeventtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\event\\client\\testeventiscreatedwithallproperties',
        1 => 'tests\\unit\\oauth\\domain\\event\\client\\testeventidisprovided',
        2 => 'tests\\unit\\oauth\\domain\\event\\client\\testaggregateidreturnsclientid',
        3 => 'tests\\unit\\oauth\\domain\\event\\client\\testaggregatetypereturnsclient',
        4 => 'tests\\unit\\oauth\\domain\\event\\client\\testpayloadcontainseventdata',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Domain/Event/Client/ClientDeactivatedEventTest.php' => 
    array (
      0 => '06342f857200a6008b4ab67c2c2db7ad01b38aaa',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\event\\client\\clientdeactivatedeventtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\event\\client\\testeventiscreatedwithallproperties',
        1 => 'tests\\unit\\oauth\\domain\\event\\client\\testeventidisprovided',
        2 => 'tests\\unit\\oauth\\domain\\event\\client\\testaggregateidreturnsclientid',
        3 => 'tests\\unit\\oauth\\domain\\event\\client\\testaggregatetypereturnsclient',
        4 => 'tests\\unit\\oauth\\domain\\event\\client\\testpayloadcontainseventdata',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Domain/Event/Client/ClientDeletedEventTest.php' => 
    array (
      0 => 'fd0145dd782fd4d09368c3eff817ffe6d899560b',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\event\\client\\clientdeletedeventtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\event\\client\\testeventiscreatedwithallproperties',
        1 => 'tests\\unit\\oauth\\domain\\event\\client\\testeventidisprovided',
        2 => 'tests\\unit\\oauth\\domain\\event\\client\\testaggregateidreturnsclientid',
        3 => 'tests\\unit\\oauth\\domain\\event\\client\\testaggregatetypereturnsclient',
        4 => 'tests\\unit\\oauth\\domain\\event\\client\\testpayloadcontainseventdata',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Domain/Event/Client/ClientRegisteredEventTest.php' => 
    array (
      0 => '50b50b7d288059abb2e590361a3674605c652503',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\event\\client\\clientregisteredeventtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\event\\client\\testeventiscreatedwithallproperties',
        1 => 'tests\\unit\\oauth\\domain\\event\\client\\testeventidisprovided',
        2 => 'tests\\unit\\oauth\\domain\\event\\client\\testaggregateidreturnsclientid',
        3 => 'tests\\unit\\oauth\\domain\\event\\client\\testaggregatetypereturnsclient',
        4 => 'tests\\unit\\oauth\\domain\\event\\client\\testpayloadcontainseventdata',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Domain/Event/Client/ClientSecretRegeneratedEventTest.php' => 
    array (
      0 => '6c496bd3254dc55ea67f7161d5a624d03bd86eeb',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\event\\client\\clientsecretregeneratedeventtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\event\\client\\testeventiscreatedwithallproperties',
        1 => 'tests\\unit\\oauth\\domain\\event\\client\\testeventidisprovided',
        2 => 'tests\\unit\\oauth\\domain\\event\\client\\testaggregateidreturnsclientid',
        3 => 'tests\\unit\\oauth\\domain\\event\\client\\testaggregatetypereturnsclient',
        4 => 'tests\\unit\\oauth\\domain\\event\\client\\testpayloadcontainseventdata',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Domain/Event/Client/ClientUpdatedEventTest.php' => 
    array (
      0 => 'e59fe7fba4bfa5a107c0a1fd6d15269c40cd653b',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\event\\client\\clientupdatedeventtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\event\\client\\testeventiscreatedwithallproperties',
        1 => 'tests\\unit\\oauth\\domain\\event\\client\\testeventidisprovided',
        2 => 'tests\\unit\\oauth\\domain\\event\\client\\testaggregateidreturnsclientid',
        3 => 'tests\\unit\\oauth\\domain\\event\\client\\testaggregatetypereturnsclient',
        4 => 'tests\\unit\\oauth\\domain\\event\\client\\testpayloadcontainseventdata',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Domain/Event/Consent/ConsentGrantedEventTest.php' => 
    array (
      0 => '823535f67c6407b68eec9ded36460d380fbee5eb',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\event\\consent\\consentgrantedeventtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\event\\consent\\testconstructsetsoccurredat',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Domain/Event/Token/TokenIssuedEventTest.php' => 
    array (
      0 => '90a4ee3a7258ead7e5d93729c857f2463336cf9c',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\event\\token\\tokenissuedeventtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\event\\token\\testcanbecreated',
        1 => 'tests\\unit\\oauth\\domain\\event\\token\\testcanbecreatedwithnulluserid',
        2 => 'tests\\unit\\oauth\\domain\\event\\token\\testoccurredatissetautomatically',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Domain/Event/Token/TokenIssueFailedEventTest.php' => 
    array (
      0 => '46d67be2de3b068283f57bc6acc09f02ae8abfa1',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\event\\token\\tokenissuefailedeventtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\event\\token\\testcanbecreated',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Domain/Event/Token/TokenRefreshedEventTest.php' => 
    array (
      0 => '6627df3c9e9423f298d5ad6033b82af8737a3ebd',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\event\\token\\tokenrefreshedeventtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\event\\token\\testcanbecreated',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Domain/Event/Token/TokenRefreshFailedEventTest.php' => 
    array (
      0 => '9288e8e82d489105970c3c8eacca346bbbdeb97a',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\event\\token\\tokenrefreshfailedeventtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\event\\token\\testcanbecreated',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Domain/Event/Token/TokenRevokedEventTest.php' => 
    array (
      0 => '48dcfba3fad94ea71cf117aa90c451cdf1333ff9',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\event\\token\\tokenrevokedeventtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\event\\token\\testcanbecreated',
        1 => 'tests\\unit\\oauth\\domain\\event\\token\\testcanbecreatedwithnullreason',
        2 => 'tests\\unit\\oauth\\domain\\event\\token\\testoccurredatissetautomatically',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Domain/Exception/Client/InvalidClientExceptionTest.php' => 
    array (
      0 => '1b8172957216107b3a0949ec2e2a3c314c6d500d',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\exception\\client\\invalidclientexceptiontest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\exception\\client\\testforidcreatesmessage',
        1 => 'tests\\unit\\oauth\\domain\\exception\\client\\testinactivecreatesmessage',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Domain/Exception/Client/InvalidOAuthClientIdentifierExceptionTest.php' => 
    array (
      0 => '226c29068328c010a8c964a3690f8d223916af26',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\exception\\client\\invalidoauthclientidentifierexceptiontest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\exception\\client\\testextendsinvalidvalueexception',
        1 => 'tests\\unit\\oauth\\domain\\exception\\client\\testinvalidpattern',
        2 => 'tests\\unit\\oauth\\domain\\exception\\client\\testempty',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Domain/Exception/Client/InvalidRedirectUriExceptionTest.php' => 
    array (
      0 => 'e0df86cc8bb6eae0ababb93ee2f263e452a18daf',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\exception\\client\\invalidredirecturiexceptiontest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\exception\\client\\testforuricreatesmessage',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Domain/Exception/Token/AuthorizationExceptionTest.php' => 
    array (
      0 => '4dae7cadafb51640f6573eb48faac8cd831f8708',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\exception\\token\\authorizationexceptiontest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\exception\\token\\testinvalidrequestsetserrortype',
        1 => 'tests\\unit\\oauth\\domain\\exception\\token\\testservererrorsetserrortype',
        2 => 'tests\\unit\\oauth\\domain\\exception\\token\\testtemporarilyunavailablesetserrortype',
        3 => 'tests\\unit\\oauth\\domain\\exception\\token\\testinvalidclientsetserrortype',
        4 => 'tests\\unit\\oauth\\domain\\exception\\token\\testinvalidgrantsetserrortype',
        5 => 'tests\\unit\\oauth\\domain\\exception\\token\\testunauthorizedclientsetserrortype',
        6 => 'tests\\unit\\oauth\\domain\\exception\\token\\testunsupportedgranttypesetserrortype',
        7 => 'tests\\unit\\oauth\\domain\\exception\\token\\testinvalidscopesetserrortype',
        8 => 'tests\\unit\\oauth\\domain\\exception\\token\\testaccessdeniedsetserrortypeandprevious',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Domain/Exception/Token/InvalidGrantTypeExceptionTest.php' => 
    array (
      0 => '0e2b40cfdcd36cb0594db3a0eba24c004971988e',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\exception\\token\\invalidgranttypeexceptiontest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\exception\\token\\testextendsinvalidvalueexception',
        1 => 'tests\\unit\\oauth\\domain\\exception\\token\\testnotallowed',
        2 => 'tests\\unit\\oauth\\domain\\exception\\token\\testempty',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Domain/Exception/Token/InvalidScopeExceptionTest.php' => 
    array (
      0 => '85ee3d021caedb79c21653c4ecd2a5989ccabe41',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\exception\\token\\invalidscopeexceptiontest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\exception\\token\\testextendsinvalidvalueexception',
        1 => 'tests\\unit\\oauth\\domain\\exception\\token\\testinvalidformat',
        2 => 'tests\\unit\\oauth\\domain\\exception\\token\\testempty',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Domain/Exception/Token/UnauthorizedGrantTypeExceptionTest.php' => 
    array (
      0 => '614142679d22052ec91bd459eb67329f60539619',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\exception\\token\\unauthorizedgranttypeexceptiontest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\exception\\token\\testforgranttypecreatesmessage',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Domain/Model/Client/ClientTest.php' => 
    array (
      0 => '8ac9e5a0efed1342f4253c06ec8016c5552ea82c',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\model\\client\\clienttest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\model\\client\\testregistercreatesnewclient',
        1 => 'tests\\unit\\oauth\\domain\\model\\client\\testregisterrecordsclientregisteredevent',
        2 => 'tests\\unit\\oauth\\domain\\model\\client\\testupdatedetailschangesclientproperties',
        3 => 'tests\\unit\\oauth\\domain\\model\\client\\testregeneratesecretchangessecret',
        4 => 'tests\\unit\\oauth\\domain\\model\\client\\testactivatesetsclientasactive',
        5 => 'tests\\unit\\oauth\\domain\\model\\client\\testactivatenoopswhenalreadyactive',
        6 => 'tests\\unit\\oauth\\domain\\model\\client\\testdeactivatesetsclientasinactive',
        7 => 'tests\\unit\\oauth\\domain\\model\\client\\testdeactivatenoopswhenalreadyinactive',
        8 => 'tests\\unit\\oauth\\domain\\model\\client\\testdeletemarksclientasdeleted',
        9 => 'tests\\unit\\oauth\\domain\\model\\client\\testdeletenoopswhenalreadydeleted',
        10 => 'tests\\unit\\oauth\\domain\\model\\client\\testvalidateredirecturireturnstrueforalloweduri',
        11 => 'tests\\unit\\oauth\\domain\\model\\client\\testvalidateredirecturireturnsfalsefordisalloweduri',
        12 => 'tests\\unit\\oauth\\domain\\model\\client\\testsupportsgranttypereturnstrueforsupportedtype',
        13 => 'tests\\unit\\oauth\\domain\\model\\client\\testhasscopereturnstrueforallowedscope',
        14 => 'tests\\unit\\oauth\\domain\\model\\client\\createtestclient',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Domain/Model/Client/OAuthClientTest.php' => 
    array (
      0 => '8a73d99007a5b4792950449bda67f7132ea230e5',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\model\\client\\oauthclienttest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\model\\client\\testvalidations',
        1 => 'tests\\unit\\oauth\\domain\\model\\client\\testpublicclientdefaults',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Domain/Model/Consent/ConsentTest.php' => 
    array (
      0 => '57b07a145dfa3eef7213ad00278bc1952cf281b8',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\model\\consent\\consenttest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\model\\consent\\testgrantcreatesnewconsent',
        1 => 'tests\\unit\\oauth\\domain\\model\\consent\\testrevokemarksconsentasrevoked',
        2 => 'tests\\unit\\oauth\\domain\\model\\consent\\testhasscopechecksindividualscope',
        3 => 'tests\\unit\\oauth\\domain\\model\\consent\\testcontainsallscopeschecksmultiplescopes',
        4 => 'tests\\unit\\oauth\\domain\\model\\consent\\testupdatescopesmodifiesgrantedscopes',
        5 => 'tests\\unit\\oauth\\domain\\model\\consent\\createtestconsent',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Domain/Model/Oidc/OidcUserTest.php' => 
    array (
      0 => '0aa766e18ede895082cda1a8e65edc1267f5d8a2',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\model\\oidc\\oidcusertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\model\\oidc\\testconstructorrejectsemptysubject',
        1 => 'tests\\unit\\oauth\\domain\\model\\oidc\\testgettersreturnvalues',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Domain/Model/Token/AccessTokenTest.php' => 
    array (
      0 => '7908472ae72479dd21d47d49d3bd582c105d206d',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\model\\token\\accesstokentest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\model\\token\\testcancreateaccesstoken',
        1 => 'tests\\unit\\oauth\\domain\\model\\token\\testcanrevokeaccesstoken',
        2 => 'tests\\unit\\oauth\\domain\\model\\token\\testisexpiredreturnstruewhenexpired',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Domain/Model/Token/AuthCodeTest.php' => 
    array (
      0 => 'f77d513f597f5ea6846f42d6c7fc4f4574a6db36',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\model\\token\\authcodetest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\model\\token\\testrevokeandaccessors',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Domain/Model/Token/RefreshTokenTest.php' => 
    array (
      0 => '17a8be2e46be1db2b184ead153b291f6dfe97b9e',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\model\\token\\refreshtokentest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\model\\token\\testrevokeandexpiration',
        1 => 'tests\\unit\\oauth\\domain\\model\\token\\testisexpiredwhenpast',
        2 => 'tests\\unit\\oauth\\domain\\model\\token\\testaccessorsreturnvalues',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Domain/Service/TokenValidationServiceTest.php' => 
    array (
      0 => 'f694376f7e32672e24a27929e67f664faf44190d',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\service\\tokenvalidationservicetest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\service\\setup',
        1 => 'tests\\unit\\oauth\\domain\\service\\testvalidateaccesstokenreturnsnotfoundwhennull',
        2 => 'tests\\unit\\oauth\\domain\\service\\testvalidateaccesstokenreturnsrevokedwhenrevoked',
        3 => 'tests\\unit\\oauth\\domain\\service\\testvalidateaccesstokenreturnsexpiredwhenexpired',
        4 => 'tests\\unit\\oauth\\domain\\service\\testvalidateaccesstokenreturnssuccesswhenvalid',
        5 => 'tests\\unit\\oauth\\domain\\service\\testvalidateaccesstokenchecksrequiredscopes',
        6 => 'tests\\unit\\oauth\\domain\\service\\testvalidaterefreshtokenreturnsnotfoundwhennull',
        7 => 'tests\\unit\\oauth\\domain\\service\\testvalidaterefreshtokenreturnsrevokedwhenrevoked',
        8 => 'tests\\unit\\oauth\\domain\\service\\testvalidaterefreshtokenreturnsexpiredwhenexpired',
        9 => 'tests\\unit\\oauth\\domain\\service\\testvalidaterefreshtokenreturnssuccesswhenvalid',
        10 => 'tests\\unit\\oauth\\domain\\service\\testcanrefreshreturnsfalsewhennull',
        11 => 'tests\\unit\\oauth\\domain\\service\\testcanrefreshreturnsfalsewhenrevoked',
        12 => 'tests\\unit\\oauth\\domain\\service\\testcanrefreshreturnsfalsewhenexpired',
        13 => 'tests\\unit\\oauth\\domain\\service\\testcanrefreshreturnstruewhenvalid',
        14 => 'tests\\unit\\oauth\\domain\\service\\createaccesstoken',
        15 => 'tests\\unit\\oauth\\domain\\service\\createrefreshtoken',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Domain/ValueObject/Client/ClientIdTest.php' => 
    array (
      0 => '0488d50f3e24016fe15488c9715ba91068c46bcd',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\valueobject\\client\\clientidtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\valueobject\\client\\testvalidclientidisaccepted',
        1 => 'tests\\unit\\oauth\\domain\\valueobject\\client\\testinvalidclientidthrowsexception',
        2 => 'tests\\unit\\oauth\\domain\\valueobject\\client\\testequalsreturnstrueforsamevalue',
        3 => 'tests\\unit\\oauth\\domain\\valueobject\\client\\testequalsreturnsfalsefordifferentvalue',
        4 => 'tests\\unit\\oauth\\domain\\valueobject\\client\\testtostringreturnsvalue',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Domain/ValueObject/Client/ClientNameTest.php' => 
    array (
      0 => '47fc07519c9101af5d75ad1c5828a9b90036b253',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\valueobject\\client\\clientnametest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\valueobject\\client\\testvalidclientnameisaccepted',
        1 => 'tests\\unit\\oauth\\domain\\valueobject\\client\\testtooshortclientnamethrowsexception',
        2 => 'tests\\unit\\oauth\\domain\\valueobject\\client\\testtoolongclientnamethrowsexception',
        3 => 'tests\\unit\\oauth\\domain\\valueobject\\client\\testequalsreturnstrueforsamevalue',
        4 => 'tests\\unit\\oauth\\domain\\valueobject\\client\\testequalsreturnsfalsefordifferentvalue',
        5 => 'tests\\unit\\oauth\\domain\\valueobject\\client\\testtostringreturnsvalue',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Domain/ValueObject/Client/ClientSecretTest.php' => 
    array (
      0 => '2914701b4e053dbbd64c0fe88ae463005301106e',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\valueobject\\client\\clientsecrettest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\valueobject\\client\\testvalidhashedsecretisaccepted',
        1 => 'tests\\unit\\oauth\\domain\\valueobject\\client\\testgeneraterandomplainreturnsvalidsecret',
        2 => 'tests\\unit\\oauth\\domain\\valueobject\\client\\testgeneraterandomplainreturnsdifferentvalues',
        3 => 'tests\\unit\\oauth\\domain\\valueobject\\client\\testgeneraterandomplainwithcustomlength',
        4 => 'tests\\unit\\oauth\\domain\\valueobject\\client\\testinvalidsecretthrowsexception',
        5 => 'tests\\unit\\oauth\\domain\\valueobject\\client\\testemptysecretthrowsexception',
        6 => 'tests\\unit\\oauth\\domain\\valueobject\\client\\testtostringreturnsvalue',
        7 => 'tests\\unit\\oauth\\domain\\valueobject\\client\\testequalsreturnstrueforsamevalue',
        8 => 'tests\\unit\\oauth\\domain\\valueobject\\client\\testequalsreturnsfalsefordifferentvalue',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Domain/ValueObject/Client/OAuthClientIdentifierTest.php' => 
    array (
      0 => 'f6a41c3766362d20c12d6f7e22abe2c617c6d87f',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\valueobject\\client\\oauthclientidentifiertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\valueobject\\client\\testcanbecreatedwithvalidvalue',
        1 => 'tests\\unit\\oauth\\domain\\valueobject\\client\\testcannotbecreatedwithemptyvalue',
        2 => 'tests\\unit\\oauth\\domain\\valueobject\\client\\testcannotbecreatedwithinvalidcharacters',
        3 => 'tests\\unit\\oauth\\domain\\valueobject\\client\\testcannotbecreatedwithtooshortvalue',
        4 => 'tests\\unit\\oauth\\domain\\valueobject\\client\\testcannotstartwithspecialcharacter',
        5 => 'tests\\unit\\oauth\\domain\\valueobject\\client\\testequality',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Domain/ValueObject/Client/RedirectUriTest.php' => 
    array (
      0 => 'a98ed39370b4d107c60342c927de3384c5cff51b',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\valueobject\\client\\redirecturitest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\valueobject\\client\\testcanbecreatedwithvalidvalue',
        1 => 'tests\\unit\\oauth\\domain\\valueobject\\client\\testcannotbecreatedwithinvalidvalue',
        2 => 'tests\\unit\\oauth\\domain\\valueobject\\client\\testcannotbecreatedwithunsupportedscheme',
        3 => 'tests\\unit\\oauth\\domain\\valueobject\\client\\testequality',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Domain/ValueObject/Scope/DefaultScopesTest.php' => 
    array (
      0 => 'b90d2c47538a2add3551d14baa1b7281866be0f8',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\valueobject\\scope\\defaultscopestest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\valueobject\\scope\\testdefaultscopesaredefined',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Domain/ValueObject/Scope/ScopesTest.php' => 
    array (
      0 => '9eed22cc7cf18d85832aad84ed24fceb6c03dfeb',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\valueobject\\scope\\scopestest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\valueobject\\scope\\testconstructorthrowsonempty',
        1 => 'tests\\unit\\oauth\\domain\\valueobject\\scope\\testconstructorremovesduplicates',
        2 => 'tests\\unit\\oauth\\domain\\valueobject\\scope\\testfromarrayremovesduplicatesandtostring',
        3 => 'tests\\unit\\oauth\\domain\\valueobject\\scope\\testfromstringparsesscopes',
        4 => 'tests\\unit\\oauth\\domain\\valueobject\\scope\\testfromarraythrowsoninvalidscope',
        5 => 'tests\\unit\\oauth\\domain\\valueobject\\scope\\testfromarraythrowsonempty',
        6 => 'tests\\unit\\oauth\\domain\\valueobject\\scope\\testfromstringthrowsonempty',
        7 => 'tests\\unit\\oauth\\domain\\valueobject\\scope\\testfromstringthrowsoninvalidscope',
        8 => 'tests\\unit\\oauth\\domain\\valueobject\\scope\\testcontainsreturnsfalsewhenmissing',
        9 => 'tests\\unit\\oauth\\domain\\valueobject\\scope\\testiteratorprovidesscopes',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Domain/ValueObject/Scope/ScopeTest.php' => 
    array (
      0 => 'f79ef113fb894f7d2cb03655ecde721762b46710',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\valueobject\\scope\\scopetest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\valueobject\\scope\\testvaluesreturnslist',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Domain/ValueObject/Security/DPoPProofTest.php' => 
    array (
      0 => '9a3df33ef9e217dfddde25d3d55a7dd22924475b',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\valueobject\\security\\dpopprooftest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\valueobject\\security\\testcancreatedpopproof',
        1 => 'tests\\unit\\oauth\\domain\\valueobject\\security\\testfromjwtwithvalidpayload',
        2 => 'tests\\unit\\oauth\\domain\\valueobject\\security\\testfromjwtwithmissingclaimsthrowsexception',
        3 => 'tests\\unit\\oauth\\domain\\valueobject\\security\\testisvalidforwithmatchingmethodanduri',
        4 => 'tests\\unit\\oauth\\domain\\valueobject\\security\\testisvalidforwithmismatchedmethod',
        5 => 'tests\\unit\\oauth\\domain\\valueobject\\security\\testisvalidforwithmismatcheduri',
        6 => 'tests\\unit\\oauth\\domain\\valueobject\\security\\testisvalidforwithexpiredproof',
        7 => 'tests\\unit\\oauth\\domain\\valueobject\\security\\testisvalidforwithcustommaxage',
        8 => 'tests\\unit\\oauth\\domain\\valueobject\\security\\testurinormalization',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Domain/ValueObject/Security/GrantTypesTest.php' => 
    array (
      0 => 'fca63c1e2cb9f1ce9998a72da475ffe80b16c0da',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\valueobject\\security\\granttypestest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\valueobject\\security\\testcanbecreatedandaccessed',
        1 => 'tests\\unit\\oauth\\domain\\valueobject\\security\\testiteration',
        2 => 'tests\\unit\\oauth\\domain\\valueobject\\security\\testfromarray',
        3 => 'tests\\unit\\oauth\\domain\\valueobject\\security\\testremovesduplicates',
        4 => 'tests\\unit\\oauth\\domain\\valueobject\\security\\testtoarray',
        5 => 'tests\\unit\\oauth\\domain\\valueobject\\security\\testconstructorthrowswhenempty',
        6 => 'tests\\unit\\oauth\\domain\\valueobject\\security\\testfromarraythrowswhenempty',
        7 => 'tests\\unit\\oauth\\domain\\valueobject\\security\\testfromarraythrowswheninvalidvalue',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Domain/ValueObject/Security/GrantTypeTest.php' => 
    array (
      0 => '856ed1038992d6df366b566547ebda55811d3749',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\valueobject\\security\\granttypetest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\valueobject\\security\\testenumcases',
        1 => 'tests\\unit\\oauth\\domain\\valueobject\\security\\testenumvalues',
        2 => 'tests\\unit\\oauth\\domain\\valueobject\\security\\testfromstring',
        3 => 'tests\\unit\\oauth\\domain\\valueobject\\security\\testfrominvalidstringthrowsexception',
        4 => 'tests\\unit\\oauth\\domain\\valueobject\\security\\testisauthorizationcode',
        5 => 'tests\\unit\\oauth\\domain\\valueobject\\security\\testisclientcredentials',
        6 => 'tests\\unit\\oauth\\domain\\valueobject\\security\\testisrefreshtoken',
        7 => 'tests\\unit\\oauth\\domain\\valueobject\\security\\testrequiresuserauthentication',
        8 => 'tests\\unit\\oauth\\domain\\valueobject\\security\\testlabel',
        9 => 'tests\\unit\\oauth\\domain\\valueobject\\security\\testequality',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Domain/ValueObject/Token/TokenClaimsTest.php' => 
    array (
      0 => '2d517a0706b86b0b5a4515e017b0b761865339c3',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\valueobject\\token\\tokenclaimstest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\valueobject\\token\\testcanbecreatedandaccessed',
        1 => 'tests\\unit\\oauth\\domain\\valueobject\\token\\testjsonserialization',
        2 => 'tests\\unit\\oauth\\domain\\valueobject\\token\\testemptyclaimsthrowexception',
        3 => 'tests\\unit\\oauth\\domain\\valueobject\\token\\testemptykeythrowsexception',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Domain/ValueObject/Token/TokenExpiryTest.php' => 
    array (
      0 => '7638b4f3e6ef2ff33cc83b75e193622661c360a3',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\valueobject\\token\\tokenexpirytest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\valueobject\\token\\testcancreatewithexpiresat',
        1 => 'tests\\unit\\oauth\\domain\\valueobject\\token\\testfromttlcreatescorrectexpiry',
        2 => 'tests\\unit\\oauth\\domain\\valueobject\\token\\testfromttlwithzerothrowsexception',
        3 => 'tests\\unit\\oauth\\domain\\valueobject\\token\\testfromttlwithnegativethrowsexception',
        4 => 'tests\\unit\\oauth\\domain\\valueobject\\token\\testfromtimestamp',
        5 => 'tests\\unit\\oauth\\domain\\valueobject\\token\\testfromsecondsdelegatestofromttl',
        6 => 'tests\\unit\\oauth\\domain\\valueobject\\token\\testisexpiredpropertyhookforvalidtoken',
        7 => 'tests\\unit\\oauth\\domain\\valueobject\\token\\testisexpiredpropertyhookforexpiredtoken',
        8 => 'tests\\unit\\oauth\\domain\\valueobject\\token\\testisexpiredmethoduseshook',
        9 => 'tests\\unit\\oauth\\domain\\valueobject\\token\\testremainingsecondspropertyhook',
        10 => 'tests\\unit\\oauth\\domain\\valueobject\\token\\testremainingsecondsforexpiredtoken',
        11 => 'tests\\unit\\oauth\\domain\\valueobject\\token\\testsecondsremainingmethoduseshook',
        12 => 'tests\\unit\\oauth\\domain\\valueobject\\token\\testexpiresinminutespropertyhook',
        13 => 'tests\\unit\\oauth\\domain\\valueobject\\token\\testwillexpirewithin',
        14 => 'tests\\unit\\oauth\\domain\\valueobject\\token\\testextend',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Domain/ValueObject/Token/TokenIdentifierTest.php' => 
    array (
      0 => '69074838bbcac4dd6f0692a3d7306a26b856b091',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\valueobject\\token\\tokenidentifiertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\domain\\valueobject\\token\\testgeneratecreatesidentifier',
        1 => 'tests\\unit\\oauth\\domain\\valueobject\\token\\testequalsmatchesvalue',
        2 => 'tests\\unit\\oauth\\domain\\valueobject\\token\\testemptyvaluethrowsexception',
        3 => 'tests\\unit\\oauth\\domain\\valueobject\\token\\testtostringreturnsvalue',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Infrastructure/Adapter/Auth/AccessTokenLookupAdapterTest.php' => 
    array (
      0 => '3f5a0046963b509859a62bea002baf0d2ff5bc0f',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\adapter\\auth\\accesstokenlookupadaptertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\adapter\\auth\\testfindreturnsnullwhenmissing',
        1 => 'tests\\unit\\oauth\\infrastructure\\adapter\\auth\\testfindreturnsaccesstokenstatus',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Infrastructure/Adapter/Auth/TokenRefreshAdapterTest.php' => 
    array (
      0 => '606e6838bf06cee3a9632ee1cf88fee3e5d3e46d',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\adapter\\auth\\tokenrefreshadaptertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\adapter\\auth\\testrefreshreturnssuccessresult',
        1 => 'tests\\unit\\oauth\\infrastructure\\adapter\\auth\\testrefreshreturnsfailedresultwhenqueryfails',
        2 => 'tests\\unit\\oauth\\infrastructure\\adapter\\auth\\testrefreshreturnsfailedresultwhennotsuccessful',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Infrastructure/Adapter/Cache/TokenCacheAdapterTest.php' => 
    array (
      0 => '0f687a99b52f5a307175666cf8c15eda6af56c5b',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\adapter\\cache\\tokencacheadaptertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\adapter\\cache\\testgetreturnsnullwhenmissing',
        1 => 'tests\\unit\\oauth\\infrastructure\\adapter\\cache\\testsetandget',
        2 => 'tests\\unit\\oauth\\infrastructure\\adapter\\cache\\testinvalidateremovesitem',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Infrastructure/Adapter/Jwt/JwtParserAdapterTest.php' => 
    array (
      0 => '8e15b26981fa3faa067a7b76114b5f703f20d4e0',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\adapter\\jwt\\jwtparseradaptertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\adapter\\jwt\\testparsereturnsclaims',
        1 => 'tests\\unit\\oauth\\infrastructure\\adapter\\jwt\\testvalidatecheckstoken',
        2 => 'tests\\unit\\oauth\\infrastructure\\adapter\\jwt\\testgettokenidanduserid',
        3 => 'tests\\unit\\oauth\\infrastructure\\adapter\\jwt\\testparsereturnsnullforemptytoken',
        4 => 'tests\\unit\\oauth\\infrastructure\\adapter\\jwt\\testparsereturnsnullforinvalidtoken',
        5 => 'tests\\unit\\oauth\\infrastructure\\adapter\\jwt\\testvalidatereturnsfalsewhenclaimsmissing',
        6 => 'tests\\unit\\oauth\\infrastructure\\adapter\\jwt\\testgettokenidanduseridreturnnullforinvalidtoken',
        7 => 'tests\\unit\\oauth\\infrastructure\\adapter\\jwt\\createtoken',
        8 => 'tests\\unit\\oauth\\infrastructure\\adapter\\jwt\\publickeypath',
        9 => 'tests\\unit\\oauth\\infrastructure\\adapter\\jwt\\privatekeypath',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Infrastructure/Adapter/Token/TokenRevocationAdapterTest.php' => 
    array (
      0 => '768ff52db8c376820c10ed9c5533b5612b2a45b4',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\adapter\\token\\tokenrevocationadaptertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\adapter\\token\\testrevokerefreshtokenreturnsfalseonemptytoken',
        1 => 'tests\\unit\\oauth\\infrastructure\\adapter\\token\\testrevokerefreshtokenrevokestoken',
        2 => 'tests\\unit\\oauth\\infrastructure\\adapter\\token\\testrevokerefreshtokenreturnsfalseforinvalidpayload',
        3 => 'tests\\unit\\oauth\\infrastructure\\adapter\\token\\testrevokerefreshtokenreturnsfalsewhentokennotfound',
        4 => 'tests\\unit\\oauth\\infrastructure\\adapter\\token\\testrevokerefreshtokenreturnsfalsewhentokenidnotstring',
        5 => 'tests\\unit\\oauth\\infrastructure\\adapter\\token\\testrevokerefreshtokenreturnsfalsewhendecryptfails',
        6 => 'tests\\unit\\oauth\\infrastructure\\adapter\\token\\testrevokeaccesstokenrevokestoken',
        7 => 'tests\\unit\\oauth\\infrastructure\\adapter\\token\\testrevokeaccesstokenreturnsfalsewhenmissingjti',
        8 => 'tests\\unit\\oauth\\infrastructure\\adapter\\token\\testrevokeaccesstokenreturnsfalsewhenjtinotstring',
        9 => 'tests\\unit\\oauth\\infrastructure\\adapter\\token\\testrevokeaccesstokenreturnsfalsewhentokennotfound',
        10 => 'tests\\unit\\oauth\\infrastructure\\adapter\\token\\testrevokeaccesstokenreturnsfalsewhentokenencrypted',
        11 => 'tests\\unit\\oauth\\infrastructure\\adapter\\token\\testrevokeallusertokenslogs',
        12 => 'tests\\unit\\oauth\\infrastructure\\adapter\\token\\createadapter',
        13 => 'tests\\unit\\oauth\\infrastructure\\adapter\\token\\createaccesstoken',
        14 => 'tests\\unit\\oauth\\infrastructure\\adapter\\token\\createrefreshtoken',
        15 => 'tests\\unit\\oauth\\infrastructure\\adapter\\token\\encryptpayload',
        16 => 'tests\\unit\\oauth\\infrastructure\\adapter\\token\\createjwt',
        17 => 'tests\\unit\\oauth\\infrastructure\\adapter\\token\\base64urlencode',
        18 => 'tests\\unit\\oauth\\infrastructure\\adapter\\token\\encryptionkey',
        19 => 'tests\\unit\\oauth\\infrastructure\\adapter\\token\\createjwe',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Infrastructure/Console/Command/Client/CreateClientCommandTest.php' => 
    array (
      0 => '11eb1b83b19af24e50eafe762333c911c9a2bdcc',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\console\\command\\client\\createclientcommandtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\console\\command\\client\\testexecutecreatesclientwithprovidedidandsecret',
        1 => 'tests\\unit\\oauth\\infrastructure\\console\\command\\client\\testexecutewarnsoninvalidcustomid',
        2 => 'tests\\unit\\oauth\\infrastructure\\console\\command\\client\\testexecutegeneratesidwhennotprovided',
        3 => 'tests\\unit\\oauth\\infrastructure\\console\\command\\client\\testexecutereturnsfailureonexception',
        4 => 'tests\\unit\\oauth\\infrastructure\\console\\command\\client\\createuuidfactory',
        5 => 'tests\\unit\\oauth\\infrastructure\\console\\command\\client\\__construct',
        6 => 'tests\\unit\\oauth\\infrastructure\\console\\command\\client\\generate',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Infrastructure/Console/Command/Client/DeleteClientCommandTest.php' => 
    array (
      0 => '04c32e2157e8decef1e1edfc0645b0f3771fdb61',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\console\\command\\client\\deleteclientcommandtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\console\\command\\client\\testexecutefailswhenclientnotfound',
        1 => 'tests\\unit\\oauth\\infrastructure\\console\\command\\client\\testexecutecancelswhennotforced',
        2 => 'tests\\unit\\oauth\\infrastructure\\console\\command\\client\\testexecutedeleteswhenforced',
        3 => 'tests\\unit\\oauth\\infrastructure\\console\\command\\client\\testexecutereturnsfailureonexception',
        4 => 'tests\\unit\\oauth\\infrastructure\\console\\command\\client\\createclient',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Infrastructure/Console/Command/Client/ListClientsCommandTest.php' => 
    array (
      0 => '7b5ca37f091e56daf26a571be867b1c35135069a',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\console\\command\\client\\listclientscommandtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\console\\command\\client\\testexecuteoutputsnoclientsmessage',
        1 => 'tests\\unit\\oauth\\infrastructure\\console\\command\\client\\testexecutelistsclients',
        2 => 'tests\\unit\\oauth\\infrastructure\\console\\command\\client\\createclient',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Infrastructure/Console/Command/Token/GenerateTokenCommandTest.php' => 
    array (
      0 => '4aa8abe5f4fe97e7f6fec5fca9d10b41acaff7e6',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\console\\command\\token\\generatetokencommandtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\console\\command\\token\\testexecutefailswhenclientmissing',
        1 => 'tests\\unit\\oauth\\infrastructure\\console\\command\\token\\testexecutegeneratestokensuccessfully',
        2 => 'tests\\unit\\oauth\\infrastructure\\console\\command\\token\\testexecutereturnsfailureonexception',
        3 => 'tests\\unit\\oauth\\infrastructure\\console\\command\\token\\createclient',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Infrastructure/DataFixtures/ClientFixturesTest.php' => 
    array (
      0 => '0ae42f4bba63af3c714d5d2f3d705e12a3f35f5b',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\datafixtures\\clientfixturestest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\datafixtures\\testgetgroupsreturnsoauthgroup',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Infrastructure/EventSubscriber/EventLogSubscriberTest.php' => 
    array (
      0 => 'b493cb4eebf1be2ea1551a9b111c337edda9a7c9',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\eventsubscriber\\eventlogsubscribertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\eventsubscriber\\testgetsubscribedevents',
        1 => 'tests\\unit\\oauth\\infrastructure\\eventsubscriber\\testontokenissuedlogsinfo',
        2 => 'tests\\unit\\oauth\\infrastructure\\eventsubscriber\\testontokenissuefailedlogswarning',
        3 => 'tests\\unit\\oauth\\infrastructure\\eventsubscriber\\testontokenrefreshedlogsinfo',
        4 => 'tests\\unit\\oauth\\infrastructure\\eventsubscriber\\testontokenrefreshfailedlogswarning',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Infrastructure/OAuth2/League/Adapter/ClientRepositoryPortAdapterTest.php' => 
    array (
      0 => '53133888de44e7089b503c3f6169aaae6d5f5278',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\adapter\\clientrepositoryportadaptertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\adapter\\testfindreturnsclientwhenactive',
        1 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\adapter\\testfindreturnsnullwheninactive',
        2 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\adapter\\testfindreturnsnullonexception',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Infrastructure/OAuth2/League/Adapter/ClientValidationAdapterTest.php' => 
    array (
      0 => 'd564e767756fb6e1bba05ec0db462b6e32b4da9f',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\adapter\\clientvalidationadaptertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\adapter\\testvalidatecredentialsreturnstruewhenvalid',
        1 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\adapter\\testvalidatecredentialsreturnsfalseonexception',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Infrastructure/OAuth2/League/Entity/ClientTest.php' => 
    array (
      0 => '39b026c6791aeb4f83da132c35763ec9b8f28460',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\entity\\clienttest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\entity\\testconstructorsetsproperties',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Infrastructure/OAuth2/League/Repository/AccessTokenRepositoryAdapterTest.php' => 
    array (
      0 => 'f73a21d0a39274b24d662900267608fe723a1297',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\repository\\accesstokenrepositoryadaptertest',
        1 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\repository\\testclient',
        2 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\repository\\testscope',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\repository\\testgetnewtokenbuildsaccesstokenentity',
        1 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\repository\\testpersistnewaccesstokensavesdomaintoken',
        2 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\repository\\testrevokeaccesstokenhandlesmissingtoken',
        3 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\repository\\testisaccesstokenrevokedusesrepository',
        4 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\repository\\__construct',
        5 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\repository\\getidentifier',
        6 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\repository\\getname',
        7 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\repository\\getredirecturi',
        8 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\repository\\isconfidential',
        9 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\repository\\__construct',
        10 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\repository\\getidentifier',
        11 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\repository\\jsonserialize',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Infrastructure/OAuth2/League/Repository/AuthCodeRepositoryAdapterTest.php' => 
    array (
      0 => '1459375644e659421a57c2763a0181ad5ba4a9e1',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\repository\\authcoderepositoryadaptertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\repository\\testgetnewauthcodereturnsentity',
        1 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\repository\\testpersistnewauthcodesavesdomainauthcode',
        2 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\repository\\testrevokeauthcodemarksrevoked',
        3 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\repository\\testisauthcoderevokedreturnstruewhenmissing',
        4 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\repository\\testisauthcoderevokedreturnsfalsewhenactive',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Infrastructure/OAuth2/League/Repository/ClientRepositoryAdapterTest.php' => 
    array (
      0 => 'f5b4c6f5426fe2b5ad3eb1dda2973d098ccacc6c',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\repository\\clientrepositoryadaptertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\repository\\testgetcliententityreturnsmappedclient',
        1 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\repository\\testgetcliententityreturnsnullonmissingclient',
        2 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\repository\\testgetcliententityreturnsnullonexception',
        3 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\repository\\testvalidateclientdelegatestovalidationport',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Infrastructure/OAuth2/League/Repository/RefreshTokenRepositoryAdapterTest.php' => 
    array (
      0 => '3993fc9a7f28c3c3552b4aaf150323c6016f1b09',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\repository\\refreshtokenrepositoryadaptertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\repository\\testgetnewrefreshtokenreturnsentity',
        1 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\repository\\testpersistnewrefreshtokensavesdomainrefreshtoken',
        2 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\repository\\testrevokerefreshtokenmarksrevoked',
        3 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\repository\\testisrefreshtokenrevokedreturnstruewhenmissing',
        4 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\repository\\testisrefreshtokenrevokedreturnsfalsewhenactive',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Infrastructure/OAuth2/League/Repository/ScopeRepositoryAdapterTest.php' => 
    array (
      0 => '5011ca587ebea9f2312a0b793bfce47de7cd8b62',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\repository\\scoperepositoryadaptertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\repository\\testgetscopeentitybyidentifierreturnsnullforinvalidvalues',
        1 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\repository\\testgetscopeentitybyidentifierreturnsscope',
        2 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\repository\\testfinalizescopesreturnsinput',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Infrastructure/OAuth2/League/Server/AuthorizationServerAdapterTest.php' => 
    array (
      0 => 'b5d9b7e6f0b42171d74ec8d94bac72452e61acd0',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\server\\authorizationserveradaptertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\server\\testissueaccesstokenreturnsresult',
        1 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\server\\testissueaccesstokenmapsoauthserverexception',
        2 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\server\\testissueaccesstokenmapsservererrorforauthorizationcodegrant',
        3 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\server\\testissueaccesstokenmapsservererrorforrefreshtokengrant',
        4 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\server\\testissueaccesstokenmapsthrowabletoinvalidgrantforrefreshtoken',
        5 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\server\\testissueaccesstokenmapsthrowabletoinvalidgrantforauthorizationcode',
        6 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\server\\testissueaccesstokenmapsthrowabletoservererrorforothergrant',
        7 => 'tests\\unit\\oauth\\infrastructure\\oauth2\\league\\server\\oautherrorprovider',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Infrastructure/Oidc/Adapter/IdTokenIssuerAdapterTest.php' => 
    array (
      0 => 'e8e2dc755235560a61cf2cb291ecdafc9dbd8070',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\oidc\\adapter\\idtokenissueradaptertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\oidc\\adapter\\testissueidtokenincludesclaimsandheaders',
        1 => 'tests\\unit\\oauth\\infrastructure\\oidc\\adapter\\testissuerfallbackusesdefaulturi',
        2 => 'tests\\unit\\oauth\\infrastructure\\oidc\\adapter\\testissuerfallbackuseslocalhostwhenmissing',
        3 => 'tests\\unit\\oauth\\infrastructure\\oidc\\adapter\\testusesaccesstokenttlwhenidtokenttlinvalid',
        4 => 'tests\\unit\\oauth\\infrastructure\\oidc\\adapter\\parsetoken',
        5 => 'tests\\unit\\oauth\\infrastructure\\oidc\\adapter\\getprojectdir',
        6 => 'tests\\unit\\oauth\\infrastructure\\oidc\\adapter\\getpublickeypath',
        7 => 'tests\\unit\\oauth\\infrastructure\\oidc\\adapter\\getprivatekeypath',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Infrastructure/Oidc/Adapter/OidcUserProviderAdapterTest.php' => 
    array (
      0 => 'e27297d463306a26f459860a09c6b9b0dd6b822d',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\oidc\\adapter\\oidcuserprovideradaptertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\oidc\\adapter\\testfindbyidentifierreturnsnullforemptyidentifier',
        1 => 'tests\\unit\\oauth\\infrastructure\\oidc\\adapter\\testfindbyidentifierreturnsnullonqueryfailure',
        2 => 'tests\\unit\\oauth\\infrastructure\\oidc\\adapter\\testfindbyidentifierreturnsnullwhenusermissing',
        3 => 'tests\\unit\\oauth\\infrastructure\\oidc\\adapter\\testfindbyidentifierreturnsoidcuser',
        4 => 'tests\\unit\\oauth\\infrastructure\\oidc\\adapter\\createuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Infrastructure/Persistence/Doctrine/Mapper/Client/ClientMapperTest.php' => 
    array (
      0 => '0a4bd69c8ea769e9b3d01d91831afd8b1ffa7aac',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\persistence\\doctrine\\mapper\\client\\clientmappertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\persistence\\doctrine\\mapper\\client\\testtodomainmapsrecordtoclient',
        1 => 'tests\\unit\\oauth\\infrastructure\\persistence\\doctrine\\mapper\\client\\testtodomainhandlessoftdeletedclient',
        2 => 'tests\\unit\\oauth\\infrastructure\\persistence\\doctrine\\mapper\\client\\testtorecordmapsclienttorecord',
        3 => 'tests\\unit\\oauth\\infrastructure\\persistence\\doctrine\\mapper\\client\\testroundtripmappingpreservesdata',
        4 => 'tests\\unit\\oauth\\infrastructure\\persistence\\doctrine\\mapper\\client\\testtooauthclientmapsrecord',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Infrastructure/Persistence/Doctrine/Mapper/ConsentMapperTest.php' => 
    array (
      0 => '3cfb521860e06ac12413a1065c364ca9c1a0ce2d',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\persistence\\doctrine\\mapper\\consentmappertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\persistence\\doctrine\\mapper\\testtodomainmapsrecord',
        1 => 'tests\\unit\\oauth\\infrastructure\\persistence\\doctrine\\mapper\\testtorecordmapsconsent',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Infrastructure/Persistence/Doctrine/Repository/AccessTokenRepositoryTest.php' => 
    array (
      0 => '06d6c80cb9fbd23b97ca17658690250803699177',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\persistence\\doctrine\\repository\\accesstokenrepositorytest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\persistence\\doctrine\\repository\\testsavepersistsaccesstoken',
        1 => 'tests\\unit\\oauth\\infrastructure\\persistence\\doctrine\\repository\\testfindreturnsaccesstoken',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Infrastructure/Persistence/Doctrine/Repository/AuthCodeRepositoryTest.php' => 
    array (
      0 => '574f432f8e55f574ec052d93a0b196cff41012d3',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\persistence\\doctrine\\repository\\authcoderepositorytest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\persistence\\doctrine\\repository\\testsavepersistsauthcode',
        1 => 'tests\\unit\\oauth\\infrastructure\\persistence\\doctrine\\repository\\testfindbyencryptedcodereturnsauthcode',
        2 => 'tests\\unit\\oauth\\infrastructure\\persistence\\doctrine\\repository\\testupdatenonceusesencryptedidentifier',
        3 => 'tests\\unit\\oauth\\infrastructure\\persistence\\doctrine\\repository\\testfindreturnsnullwhenmissing',
        4 => 'tests\\unit\\oauth\\infrastructure\\persistence\\doctrine\\repository\\testfindbyencryptedcodereturnsnullwhenempty',
        5 => 'tests\\unit\\oauth\\infrastructure\\persistence\\doctrine\\repository\\testfindbyencryptedcodereturnsnullforinvalidpayload',
        6 => 'tests\\unit\\oauth\\infrastructure\\persistence\\doctrine\\repository\\testfindbyencryptedcodereturnsnullwhenidentifiermissing',
        7 => 'tests\\unit\\oauth\\infrastructure\\persistence\\doctrine\\repository\\testfindbyencryptedcodereturnsnullwhendecryptfails',
        8 => 'tests\\unit\\oauth\\infrastructure\\persistence\\doctrine\\repository\\testupdatenoncereturnswhenrecordmissing',
        9 => 'tests\\unit\\oauth\\infrastructure\\persistence\\doctrine\\repository\\testupdatenoncereturnswhenencryptedidentifierinvalid',
        10 => 'tests\\unit\\oauth\\infrastructure\\persistence\\doctrine\\repository\\testupdatenoncereturnswhenidentifierempty',
        11 => 'tests\\unit\\oauth\\infrastructure\\persistence\\doctrine\\repository\\testupdatenoncereturnswhenencryptedpayloadmissingidentifier',
        12 => 'tests\\unit\\oauth\\infrastructure\\persistence\\doctrine\\repository\\testupdatenoncereturnswhenencryptedpayloadnotarray',
        13 => 'tests\\unit\\oauth\\infrastructure\\persistence\\doctrine\\repository\\createrecord',
        14 => 'tests\\unit\\oauth\\infrastructure\\persistence\\doctrine\\repository\\encryptpayload',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Infrastructure/Persistence/Doctrine/Repository/Client/ClientRepositoryTest.php' => 
    array (
      0 => 'e98fadd1b43e85f7e86690c985ea6979567848ec',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\persistence\\doctrine\\repository\\client\\clientrepositorytest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\persistence\\doctrine\\repository\\client\\testsavepersistsclient',
        1 => 'tests\\unit\\oauth\\infrastructure\\persistence\\doctrine\\repository\\client\\testfindbyidreturnsclient',
        2 => 'tests\\unit\\oauth\\infrastructure\\persistence\\doctrine\\repository\\client\\testfindbyidreturnsnullwhennotfound',
        3 => 'tests\\unit\\oauth\\infrastructure\\persistence\\doctrine\\repository\\client\\testdeleteremovesclient',
        4 => 'tests\\unit\\oauth\\infrastructure\\persistence\\doctrine\\repository\\client\\createtestclient',
        5 => 'tests\\unit\\oauth\\infrastructure\\persistence\\doctrine\\repository\\client\\createtestrecord',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Infrastructure/Persistence/Doctrine/Repository/ConsentRepositoryTest.php' => 
    array (
      0 => '2e587f34ce991f57abc9702728025952f2b2cd7b',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\persistence\\doctrine\\repository\\consentrepositorytest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\persistence\\doctrine\\repository\\testfindbyidreturnsnullwhenmissing',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Infrastructure/Persistence/Doctrine/Repository/RefreshTokenRepositoryTest.php' => 
    array (
      0 => '26c43468346c4242868ed6185fb5e108bbea3c26',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\persistence\\doctrine\\repository\\refreshtokenrepositorytest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\infrastructure\\persistence\\doctrine\\repository\\testsavepersistsrefreshtoken',
        1 => 'tests\\unit\\oauth\\infrastructure\\persistence\\doctrine\\repository\\testsaveupdatesexistingrefreshtoken',
        2 => 'tests\\unit\\oauth\\infrastructure\\persistence\\doctrine\\repository\\testfindreturnsnullwhenmissing',
        3 => 'tests\\unit\\oauth\\infrastructure\\persistence\\doctrine\\repository\\testfindbyencryptedtokenreturnsrefreshtoken',
        4 => 'tests\\unit\\oauth\\infrastructure\\persistence\\doctrine\\repository\\testfindbyencryptedtokenreturnsnullwhenempty',
        5 => 'tests\\unit\\oauth\\infrastructure\\persistence\\doctrine\\repository\\testfindbyencryptedtokenreturnsnullwhenpayloadinvalid',
        6 => 'tests\\unit\\oauth\\infrastructure\\persistence\\doctrine\\repository\\testfindbyencryptedtokenreturnsnullwhenidentifiermissing',
        7 => 'tests\\unit\\oauth\\infrastructure\\persistence\\doctrine\\repository\\testfindbyencryptedtokenreturnsnullwhendecryptfails',
        8 => 'tests\\unit\\oauth\\infrastructure\\persistence\\doctrine\\repository\\createrecord',
        9 => 'tests\\unit\\oauth\\infrastructure\\persistence\\doctrine\\repository\\encryptpayload',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Presentation/Api/Dto/Output/Consent/CheckConsentOutputTest.php' => 
    array (
      0 => 'c15c457b41d7eb7b5c4f47f73b3bc1d9091cb914',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\dto\\output\\consent\\checkconsentoutputtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\dto\\output\\consent\\testconstructorassignsproperties',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Presentation/Api/EventSubscriber/OAuthErrorSubscriberTest.php' => 
    array (
      0 => '48ce2bb6aaa1e512738e596ad10f1f7409274671',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\eventsubscriber\\oautherrorsubscribertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\eventsubscriber\\testonkernelexceptionignoresnonoauthoperation',
        1 => 'tests\\unit\\oauth\\presentation\\api\\eventsubscriber\\testonkernelexceptionmapstoomanyrequests',
        2 => 'tests\\unit\\oauth\\presentation\\api\\eventsubscriber\\testonkernelexceptionmapsviolations',
        3 => 'tests\\unit\\oauth\\presentation\\api\\eventsubscriber\\__construct',
        4 => 'tests\\unit\\oauth\\presentation\\api\\eventsubscriber\\getviolations',
        5 => 'tests\\unit\\oauth\\presentation\\api\\eventsubscriber\\testonkernelexceptionmapsauthorizationexceptionwitherroruribase',
        6 => 'tests\\unit\\oauth\\presentation\\api\\eventsubscriber\\testonkernelexceptionmapsoauthserverexception',
        7 => 'tests\\unit\\oauth\\presentation\\api\\eventsubscriber\\testonkernelexceptionmapshttpexceptionwithheaders',
        8 => 'tests\\unit\\oauth\\presentation\\api\\eventsubscriber\\testonkernelexceptionunwrapsmessengerexception',
        9 => 'tests\\unit\\oauth\\presentation\\api\\eventsubscriber\\testonkernelexceptionmapsdomainexceptions',
        10 => 'tests\\unit\\oauth\\presentation\\api\\eventsubscriber\\testonkernelexceptionmapsunknownexceptiontoservererror',
        11 => 'tests\\unit\\oauth\\presentation\\api\\eventsubscriber\\testgetsubscribedeventsregisterskernelexception',
        12 => 'tests\\unit\\oauth\\presentation\\api\\eventsubscriber\\testonkernelexceptionunwrapsmessengerexceptionpreviouschain',
        13 => 'tests\\unit\\oauth\\presentation\\api\\eventsubscriber\\testonkernelexceptionunwrapshandlerfailedexceptiondirect',
        14 => 'tests\\unit\\oauth\\presentation\\api\\eventsubscriber\\testonkernelexceptionmapshttpstatuserrors',
        15 => 'tests\\unit\\oauth\\presentation\\api\\eventsubscriber\\testonkernelexceptionusesdefaultdescriptions',
        16 => 'tests\\unit\\oauth\\presentation\\api\\eventsubscriber\\testonkernelexceptionignoresnoniterableviolations',
        17 => 'tests\\unit\\oauth\\presentation\\api\\eventsubscriber\\getviolations',
        18 => 'tests\\unit\\oauth\\presentation\\api\\eventsubscriber\\testonkernelexceptionignoresnonconstraintviolations',
        19 => 'tests\\unit\\oauth\\presentation\\api\\eventsubscriber\\getviolations',
        20 => 'tests\\unit\\oauth\\presentation\\api\\eventsubscriber\\domainexceptionprovider',
        21 => 'tests\\unit\\oauth\\presentation\\api\\eventsubscriber\\httpstatusprovider',
        22 => 'tests\\unit\\oauth\\presentation\\api\\eventsubscriber\\emptydescriptionprovider',
        23 => 'tests\\unit\\oauth\\presentation\\api\\eventsubscriber\\createevent',
        24 => 'tests\\unit\\oauth\\presentation\\api\\eventsubscriber\\decoderesponsebody',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Presentation/Api/EventSubscriber/OAuthResponseSubscriberTest.php' => 
    array (
      0 => 'ee0b478d1759aa0a0466b9869891c0e9a1bc3ac1',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\eventsubscriber\\oauthresponsesubscribertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\eventsubscriber\\testonkernelresponseaddscacheheadersfortokenoperations',
        1 => 'tests\\unit\\oauth\\presentation\\api\\eventsubscriber\\testonkernelresponseskipsnontokenoperations',
        2 => 'tests\\unit\\oauth\\presentation\\api\\eventsubscriber\\testgetsubscribedeventsregisterskernelresponse',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Presentation/Api/Processor/Authorization/AuthorizeProcessorTest.php' => 
    array (
      0 => '5991ef6cd6517df62b4ed93ca7041a6ab9a94be4',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\authorizeprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\testprocessreturnsinvalidrequestwhenpromptnonecombined',
        1 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\testprocessreturnsloginrequiredwhenmaxageexceeded',
        2 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\testprocessreturnsinvalidrequestwhenmaxagenotnumeric',
        3 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\testprocessreturnsconsentrequiredwhenpromptconsent',
        4 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\testprocessreturnsinvalidrequestwhenpromptunknown',
        5 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\testprovidethrowswhenrequestmissing',
        6 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\testprocessreturnsinvalidrequestwhencodechallengemissing',
        7 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\testprocessreturnsinvalidrequestwhencodechallengemethodinvalid',
        8 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\testprocessreturnsloginrequiredwhenusermissing',
        9 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\testprocessreturnsloginrequiredwhenselectaccountprompt',
        10 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\testprocessthrowswhenuseridempty',
        11 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\testprocessreturnserrorwhenauthorizationrequestthrowsoauthexception',
        12 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\testprocessthrowsbadrequestonunexpectedauthorizationexception',
        13 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\testprocessstoresnoncefromqueryresponse',
        14 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\testprocessreturnsloginrequiredwhenpromptlogin',
        15 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\testprocessallowsmissingcodechallengemethod',
        16 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\testprocessstoresnoncefromfragmentresponse',
        17 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\testprocessstoresnoncefromformpostresponse',
        18 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\testprocessusespostrequestandnormalizesparams',
        19 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\testprocessallowsnoncoderesponsetype',
        20 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\testprocessreturnsloginrequiredwhenmaxagezero',
        21 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\testprocessreturnsloginrequiredwhenauthtimemissing',
        22 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\testprocessreturnsloginrequiredwhenuseridemptywithmaxage',
        23 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\testprocessdoesnotstorenoncewhencodemissing',
        24 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\testprocessskipsinvalidlocation',
        25 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\testprocessskipsemptycodefromlocation',
        26 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\testprocessstoresnoncefromformpostvaluebeforename',
        27 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\testprocessstoresnoncefrombodyqueryparam',
        28 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\testprocessskipsunreadablebody',
        29 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\testprocessskipsbodyreadexception',
        30 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\testprovidethrowstoomanyrequestswhenratelimited',
        31 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\createauthorizationservermock',
        32 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\createauthorizationrequest',
        33 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\createsecurityuser',
        34 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\createsecuritymock',
        35 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\createratelimiterfactory',
        36 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\createratelimitkey',
        37 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\createstream',
        38 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\__construct',
        39 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\__tostring',
        40 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\close',
        41 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\detach',
        42 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\getsize',
        43 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\tell',
        44 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\eof',
        45 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\isseekable',
        46 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\seek',
        47 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\rewind',
        48 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\iswritable',
        49 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\write',
        50 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\isreadable',
        51 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\read',
        52 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\getcontents',
        53 => 'tests\\unit\\oauth\\presentation\\api\\processor\\authorization\\getmetadata',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Presentation/Api/Processor/Client/ActivateClientProcessorTest.php' => 
    array (
      0 => 'c29d6b68e93c130a0e571fc5fdcd5ca37c097a56',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\processor\\client\\activateclientprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\processor\\client\\testprocessthrowswhenidisnotstring',
        1 => 'tests\\unit\\oauth\\presentation\\api\\processor\\client\\testprocessdispatchesandreturnsoutput',
        2 => 'tests\\unit\\oauth\\presentation\\api\\processor\\client\\createclientresult',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Presentation/Api/Processor/Client/DeactivateClientProcessorTest.php' => 
    array (
      0 => 'ebd10e0ced56e1ecf15d46fbd7602f61ba54d604',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\processor\\client\\deactivateclientprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\processor\\client\\testprocessthrowswhenidisnotstring',
        1 => 'tests\\unit\\oauth\\presentation\\api\\processor\\client\\testprocessdispatchesandreturnsoutput',
        2 => 'tests\\unit\\oauth\\presentation\\api\\processor\\client\\createclientresult',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Presentation/Api/Processor/Client/DeleteClientProcessorTest.php' => 
    array (
      0 => 'b3802fe29e9e9c9fa9bb91142fa91b69ace407de',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\processor\\client\\deleteclientprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\processor\\client\\testprocessthrowswhenidisnotstring',
        1 => 'tests\\unit\\oauth\\presentation\\api\\processor\\client\\testprocessdispatchescommand',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Presentation/Api/Processor/Client/RegenerateSecretProcessorTest.php' => 
    array (
      0 => 'f056914f4af439916dd6f13adea68c7f691b7ac8',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\processor\\client\\regeneratesecretprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\processor\\client\\testprocessthrowswhenidisnotstring',
        1 => 'tests\\unit\\oauth\\presentation\\api\\processor\\client\\testprocessreturnsoutputwithsecret',
        2 => 'tests\\unit\\oauth\\presentation\\api\\processor\\client\\createclientresult',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Presentation/Api/Processor/Client/RegisterClientProcessorTest.php' => 
    array (
      0 => '2d789aa44c6279d2534e41408bbb97fc449582d1',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\processor\\client\\registerclientprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\processor\\client\\testprocessregistersclientandreturnsoutput',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Presentation/Api/Processor/Client/UpdateClientProcessorTest.php' => 
    array (
      0 => '850cb46d264675321d13ee1b66ff57ab01370ef4',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\processor\\client\\updateclientprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\processor\\client\\testprocessthrowswhenidisnotstring',
        1 => 'tests\\unit\\oauth\\presentation\\api\\processor\\client\\testprocessdispatchesandreturnsoutput',
        2 => 'tests\\unit\\oauth\\presentation\\api\\processor\\client\\createclientresult',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Presentation/Api/Processor/Consent/GrantConsentProcessorTest.php' => 
    array (
      0 => '8142007037e53915d55d1b434cbdd7351931bae5',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\processor\\consent\\grantconsentprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\processor\\consent\\testprocessthrowswhendatainvalid',
        1 => 'tests\\unit\\oauth\\presentation\\api\\processor\\consent\\testprocessthrowswhenusermissing',
        2 => 'tests\\unit\\oauth\\presentation\\api\\processor\\consent\\testprocessdispatchesconsentandstoresnonce',
        3 => 'tests\\unit\\oauth\\presentation\\api\\processor\\consent\\testprocessreturnserrorwhenauthorizationrequestfails',
        4 => 'tests\\unit\\oauth\\presentation\\api\\processor\\consent\\testprocessreturnsjsonerroronunexpectedexception',
        5 => 'tests\\unit\\oauth\\presentation\\api\\processor\\consent\\testprocessreturnsbadrequestwhenuseridempty',
        6 => 'tests\\unit\\oauth\\presentation\\api\\processor\\consent\\testprocessdoesnotdispatchwhennotapproved',
        7 => 'tests\\unit\\oauth\\presentation\\api\\processor\\consent\\testprocessstoresnoncefromformpostbody',
        8 => 'tests\\unit\\oauth\\presentation\\api\\processor\\consent\\testprocessreturnserrorwhencompletionfails',
        9 => 'tests\\unit\\oauth\\presentation\\api\\processor\\consent\\testprocessdispatchesemptyscopeswhenscopeblank',
        10 => 'tests\\unit\\oauth\\presentation\\api\\processor\\consent\\testprocessdoesnotstorenoncewhencodemissing',
        11 => 'tests\\unit\\oauth\\presentation\\api\\processor\\consent\\testprocessskipsinvalidlocationandemptycode',
        12 => 'tests\\unit\\oauth\\presentation\\api\\processor\\consent\\testprocessskipsemptycodefromlocation',
        13 => 'tests\\unit\\oauth\\presentation\\api\\processor\\consent\\testprocessstoresnoncefromformpostvaluebeforename',
        14 => 'tests\\unit\\oauth\\presentation\\api\\processor\\consent\\testprocessstoresnoncefrombodyqueryparam',
        15 => 'tests\\unit\\oauth\\presentation\\api\\processor\\consent\\testprocessdoesnotstorenoncewhenbodyunreadable',
        16 => 'tests\\unit\\oauth\\presentation\\api\\processor\\consent\\testprocessdoesnotstorenoncewhenbodyreadfails',
        17 => 'tests\\unit\\oauth\\presentation\\api\\processor\\consent\\testextractcodefromformpostbodyreturnsnullwhenempty',
        18 => 'tests\\unit\\oauth\\presentation\\api\\processor\\consent\\testextractcodefromformpostbodyreturnsnullwhencodemissing',
        19 => 'tests\\unit\\oauth\\presentation\\api\\processor\\consent\\testprocessthrowstoomanyrequestswhenratelimited',
        20 => 'tests\\unit\\oauth\\presentation\\api\\processor\\consent\\createsecurityuser',
        21 => 'tests\\unit\\oauth\\presentation\\api\\processor\\consent\\createinput',
        22 => 'tests\\unit\\oauth\\presentation\\api\\processor\\consent\\createratelimiterfactory',
        23 => 'tests\\unit\\oauth\\presentation\\api\\processor\\consent\\createratelimitkey',
        24 => 'tests\\unit\\oauth\\presentation\\api\\processor\\consent\\createstream',
        25 => 'tests\\unit\\oauth\\presentation\\api\\processor\\consent\\__construct',
        26 => 'tests\\unit\\oauth\\presentation\\api\\processor\\consent\\__tostring',
        27 => 'tests\\unit\\oauth\\presentation\\api\\processor\\consent\\close',
        28 => 'tests\\unit\\oauth\\presentation\\api\\processor\\consent\\detach',
        29 => 'tests\\unit\\oauth\\presentation\\api\\processor\\consent\\getsize',
        30 => 'tests\\unit\\oauth\\presentation\\api\\processor\\consent\\tell',
        31 => 'tests\\unit\\oauth\\presentation\\api\\processor\\consent\\eof',
        32 => 'tests\\unit\\oauth\\presentation\\api\\processor\\consent\\isseekable',
        33 => 'tests\\unit\\oauth\\presentation\\api\\processor\\consent\\seek',
        34 => 'tests\\unit\\oauth\\presentation\\api\\processor\\consent\\rewind',
        35 => 'tests\\unit\\oauth\\presentation\\api\\processor\\consent\\iswritable',
        36 => 'tests\\unit\\oauth\\presentation\\api\\processor\\consent\\write',
        37 => 'tests\\unit\\oauth\\presentation\\api\\processor\\consent\\isreadable',
        38 => 'tests\\unit\\oauth\\presentation\\api\\processor\\consent\\read',
        39 => 'tests\\unit\\oauth\\presentation\\api\\processor\\consent\\getcontents',
        40 => 'tests\\unit\\oauth\\presentation\\api\\processor\\consent\\getmetadata',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Presentation/Api/Processor/Session/EndSessionProcessorTest.php' => 
    array (
      0 => '6da5d6de0efedf6bfc01b426e183f7f3dffd31e5',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\processor\\session\\endsessionprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\processor\\session\\testprocessreturnsinvalidrequestwhenclientidmissing',
        1 => 'tests\\unit\\oauth\\presentation\\api\\processor\\session\\testprocessredirectswhenpostlogouturiallowed',
        2 => 'tests\\unit\\oauth\\presentation\\api\\processor\\session\\testprocessusesidtokenhinttoresolveclientid',
        3 => 'tests\\unit\\oauth\\presentation\\api\\processor\\session\\testprocessreturnsinvalidrequestwhenidtokenhintinvalid',
        4 => 'tests\\unit\\oauth\\presentation\\api\\processor\\session\\testprocessreturnsinvalidrequestwhenclientidmismatch',
        5 => 'tests\\unit\\oauth\\presentation\\api\\processor\\session\\testprocessreturnsinvalidrequestwhenpostlogouturinotallowed',
        6 => 'tests\\unit\\oauth\\presentation\\api\\processor\\session\\testprocessusesidtokenhintaudiencearray',
        7 => 'tests\\unit\\oauth\\presentation\\api\\processor\\session\\testprocessreturnsjsonwhennoredirecturi',
        8 => 'tests\\unit\\oauth\\presentation\\api\\processor\\session\\testprocessthrowswhenrequestmissing',
        9 => 'tests\\unit\\oauth\\presentation\\api\\processor\\session\\testprocessreturnsinvalidrequestwhenidtokenhintparsefails',
        10 => 'tests\\unit\\oauth\\presentation\\api\\processor\\session\\testprocessreturnsinvalidrequestwhenaudiencearrayempty',
        11 => 'tests\\unit\\oauth\\presentation\\api\\processor\\session\\testprocessreturnsinvalidrequestwhenquerybusthrows',
        12 => 'tests\\unit\\oauth\\presentation\\api\\processor\\session\\testprocessreturnsinvalidrequestwhenclientinactive',
        13 => 'tests\\unit\\oauth\\presentation\\api\\processor\\session\\testprocessredirectswithoutstatewhenstateblank',
        14 => 'tests\\unit\\oauth\\presentation\\api\\processor\\session\\testprocessignoresrevocationfailures',
        15 => 'tests\\unit\\oauth\\presentation\\api\\processor\\session\\createclientresult',
        16 => 'tests\\unit\\oauth\\presentation\\api\\processor\\session\\createcookieservice',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Presentation/Api/Processor/Token/IntrospectTokenProcessorTest.php' => 
    array (
      0 => '1c400eda15461df95e16d603daa730512673ac80',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\processor\\token\\introspecttokenprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\processor\\token\\testprocessthrowswhendatainvalid',
        1 => 'tests\\unit\\oauth\\presentation\\api\\processor\\token\\testprocessthrowswhentokenmissing',
        2 => 'tests\\unit\\oauth\\presentation\\api\\processor\\token\\testprocessreturnsoutput',
        3 => 'tests\\unit\\oauth\\presentation\\api\\processor\\token\\testprocessthrowswhenratelimited',
        4 => 'tests\\unit\\oauth\\presentation\\api\\processor\\token\\createratelimiterfactory',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Presentation/Api/Processor/Token/IssueTokenProcessorTest.php' => 
    array (
      0 => '536d945d79d860ce34739b3a65527df930932657',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\processor\\token\\issuetokenprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\processor\\token\\testprocessthrowswhendatainvalid',
        1 => 'tests\\unit\\oauth\\presentation\\api\\processor\\token\\testprocessthrowswhenrequestmissing',
        2 => 'tests\\unit\\oauth\\presentation\\api\\processor\\token\\testprocessreturnstokenoutput',
        3 => 'tests\\unit\\oauth\\presentation\\api\\processor\\token\\testprocessthrowswhenratelimited',
        4 => 'tests\\unit\\oauth\\presentation\\api\\processor\\token\\testprocessrethrowsauthorizationexceptionfrommessenger',
        5 => 'tests\\unit\\oauth\\presentation\\api\\processor\\token\\testprocessrethrowsauthorizationexceptiondirect',
        6 => 'tests\\unit\\oauth\\presentation\\api\\processor\\token\\testprocessrethrowsoauthserverexceptiondirect',
        7 => 'tests\\unit\\oauth\\presentation\\api\\processor\\token\\testprocessrethrowsmessengerexceptionpreviouschain',
        8 => 'tests\\unit\\oauth\\presentation\\api\\processor\\token\\testprocessrethrowsmessengerexceptionwhenunhandled',
        9 => 'tests\\unit\\oauth\\presentation\\api\\processor\\token\\testprocessrethrowsauthorizationexceptionfromthrowablechain',
        10 => 'tests\\unit\\oauth\\presentation\\api\\processor\\token\\testprocessrethrowsauthorizationexceptionfromdeepchain',
        11 => 'tests\\unit\\oauth\\presentation\\api\\processor\\token\\testprocesswrapsunhandledthrowableasservererror',
        12 => 'tests\\unit\\oauth\\presentation\\api\\processor\\token\\createratelimiterfactory',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Presentation/Api/Processor/Token/RevokeTokenProcessorTest.php' => 
    array (
      0 => '7a50324c780b8c62ea79d8a4d0004b1a1586da14',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\processor\\token\\revoketokenprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\processor\\token\\testprocessthrowswhendatainvalid',
        1 => 'tests\\unit\\oauth\\presentation\\api\\processor\\token\\testprocessthrowswhentokenmissing',
        2 => 'tests\\unit\\oauth\\presentation\\api\\processor\\token\\testprocessdispatchescommand',
        3 => 'tests\\unit\\oauth\\presentation\\api\\processor\\token\\testprocessthrowswhenratelimited',
        4 => 'tests\\unit\\oauth\\presentation\\api\\processor\\token\\createratelimiterfactory',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Presentation/Api/Provider/Client/GetClientProviderTest.php' => 
    array (
      0 => 'cf2e2452467fedb8c539098fdbc651382d930ef7',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\provider\\client\\getclientprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\provider\\client\\testprovidereturnsclientoutputwhenfound',
        1 => 'tests\\unit\\oauth\\presentation\\api\\provider\\client\\testprovidereturnsnullwhennotfound',
        2 => 'tests\\unit\\oauth\\presentation\\api\\provider\\client\\testprovidereturnsnullwhenidinvalid',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Presentation/Api/Provider/Client/ListClientsProviderTest.php' => 
    array (
      0 => '89bc79d0a3e834856545ccb00c283a3fb37a692f',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\provider\\client\\listclientsprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\provider\\client\\testprovidemapsclientsandpagination',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Presentation/Api/Provider/Consent/CheckConsentProviderTest.php' => 
    array (
      0 => '1e41ae3b43bde9e9094e0ca8ea0601b6bc8a310e',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\provider\\consent\\checkconsentprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\provider\\consent\\testprovidethrowswhenusermissing',
        1 => 'tests\\unit\\oauth\\presentation\\api\\provider\\consent\\testprovidethrowswhenclientidmissing',
        2 => 'tests\\unit\\oauth\\presentation\\api\\provider\\consent\\testprovidethrowswhenrequestmissing',
        3 => 'tests\\unit\\oauth\\presentation\\api\\provider\\consent\\testprovidereturnsoutput',
        4 => 'tests\\unit\\oauth\\presentation\\api\\provider\\consent\\testprovidehandlesemptyscope',
        5 => 'tests\\unit\\oauth\\presentation\\api\\provider\\consent\\testprovidethrowstoomanyrequestswhenratelimited',
        6 => 'tests\\unit\\oauth\\presentation\\api\\provider\\consent\\createsecurityuser',
        7 => 'tests\\unit\\oauth\\presentation\\api\\provider\\consent\\createratelimiterfactory',
        8 => 'tests\\unit\\oauth\\presentation\\api\\provider\\consent\\createratelimitkey',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Presentation/Api/Provider/Discovery/JwksProviderTest.php' => 
    array (
      0 => '2a1eb948661a3d1caaa13040fcb768beb7c74853',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\provider\\discovery\\jwksprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\provider\\discovery\\testprovidereturnsemptykeyswhenfilemissing',
        1 => 'tests\\unit\\oauth\\presentation\\api\\provider\\discovery\\testprovidereturnsemptykeyswhenfilenotfound',
        2 => 'tests\\unit\\oauth\\presentation\\api\\provider\\discovery\\testprovidereturnsemptykeyswhenkeyisnotrsa',
        3 => 'tests\\unit\\oauth\\presentation\\api\\provider\\discovery\\testprovidereturnsemptykeyswhenrsapartsarenotstrings',
        4 => 'tests\\unit\\oauth\\presentation\\api\\provider\\discovery\\testprovidereturnskeysforvalidpublickey',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Presentation/Api/Provider/Discovery/OpenIdConfigurationProviderTest.php' => 
    array (
      0 => '1cc3b08f506fa57608840a492a970bdee79c1b28',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\provider\\discovery\\openidconfigurationprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\provider\\discovery\\testprovideincludesendsessionandpromptvalues',
        1 => 'tests\\unit\\oauth\\presentation\\api\\provider\\discovery\\testprovideusesconfiguredlogoutpath',
        2 => 'tests\\unit\\oauth\\presentation\\api\\provider\\discovery\\testprovidefallsbacktodefaultendsessionpathwhenroutemissing',
        3 => 'tests\\unit\\oauth\\presentation\\api\\provider\\discovery\\testprovideusesconfiguredauthorizeurl',
        4 => 'tests\\unit\\oauth\\presentation\\api\\provider\\discovery\\testprovideusesconfiguredauthorizeroutename',
        5 => 'tests\\unit\\oauth\\presentation\\api\\provider\\discovery\\testprovideusesrelativefallbackwhenbaseurlempty',
        6 => 'tests\\unit\\oauth\\presentation\\api\\provider\\discovery\\testprovideusesconfiguredauthorizepath',
        7 => 'tests\\unit\\oauth\\presentation\\api\\provider\\discovery\\testprovidefallsbacktoconfiguredauthorizepathwhenroutemissing',
        8 => 'tests\\unit\\oauth\\presentation\\api\\provider\\discovery\\testprovideusesissuerwhenconfigured',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Presentation/Api/Provider/Discovery/UserInfoProviderTest.php' => 
    array (
      0 => 'c3d95141a7add12f89c9dee5b71ea753e8730586',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\provider\\discovery\\userinfoprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\provider\\discovery\\testprovidethrowswhenusermissing',
        1 => 'tests\\unit\\oauth\\presentation\\api\\provider\\discovery\\testprovidethrowswhenopenidscopemissing',
        2 => 'tests\\unit\\oauth\\presentation\\api\\provider\\discovery\\testprovidethrowswhenoidcusermissing',
        3 => 'tests\\unit\\oauth\\presentation\\api\\provider\\discovery\\testprovidereturnsuserinfo',
        4 => 'tests\\unit\\oauth\\presentation\\api\\provider\\discovery\\testprovidehandlesinvalidclaimsandfallbacks',
        5 => 'tests\\unit\\oauth\\presentation\\api\\provider\\discovery\\testprovidewrapsunexpectedexception',
        6 => 'tests\\unit\\oauth\\presentation\\api\\provider\\discovery\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Presentation/Api/Resource/OAuthResourcesTest.php' => 
    array (
      0 => '347d6ff557dde71c8ac6284017f003768a9456fa',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\resource\\oauthresourcestest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\resource\\testresourcescanbeinstantiated',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Presentation/Api/Validator/GrantTypeRequirements/GrantTypeRequirementsValidatorTest.php' => 
    array (
      0 => '6c081b10ce56940ea353b06201e4cc1d49a345e6',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\validator\\granttyperequirements\\granttyperequirementsvalidatortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\validator\\granttyperequirements\\testrefreshtokengrantpasseswhentokenpresent',
        1 => 'tests\\unit\\oauth\\presentation\\api\\validator\\granttyperequirements\\testrefreshtokengrantaddsviolationwhenmissing',
        2 => 'tests\\unit\\oauth\\presentation\\api\\validator\\granttyperequirements\\testauthorizationcodegrantaddsviolations',
        3 => 'tests\\unit\\oauth\\presentation\\api\\validator\\granttyperequirements\\testnullvaluepasses',
        4 => 'tests\\unit\\oauth\\presentation\\api\\validator\\granttyperequirements\\testthrowsoninvalidconstraint',
        5 => 'tests\\unit\\oauth\\presentation\\api\\validator\\granttyperequirements\\testthrowsoninvalidvaluetype',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Presentation/Api/Validator/ValidRedirectUri/ValidRedirectUriValidatorTest.php' => 
    array (
      0 => '3f96855b80c24e9cec291de5825b8e71af7decf9',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\validator\\validredirecturi\\validredirecturivalidatortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\validator\\validredirecturi\\testvalidhttpsuri',
        1 => 'tests\\unit\\oauth\\presentation\\api\\validator\\validredirecturi\\testvalidlocalhosthttpuri',
        2 => 'tests\\unit\\oauth\\presentation\\api\\validator\\validredirecturi\\testinvalidhttpuri',
        3 => 'tests\\unit\\oauth\\presentation\\api\\validator\\validredirecturi\\testuriwithfragmentfails',
        4 => 'tests\\unit\\oauth\\presentation\\api\\validator\\validredirecturi\\testnullvaluepasses',
        5 => 'tests\\unit\\oauth\\presentation\\api\\validator\\validredirecturi\\testinvalidconstraintthrows',
        6 => 'tests\\unit\\oauth\\presentation\\api\\validator\\validredirecturi\\testinvaliduriwithoutschemefails',
        7 => 'tests\\unit\\oauth\\presentation\\api\\validator\\validredirecturi\\testnonstringvaluesareignored',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Presentation/Api/Validator/ValidScopes/ValidScopesTest.php' => 
    array (
      0 => '71bd40f110a7f7f45db326965c96ace8e986b97e',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\validator\\validscopes\\validscopestest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\validator\\validscopes\\testdefaultsareconfigured',
        1 => 'tests\\unit\\oauth\\presentation\\api\\validator\\validscopes\\testoverridesallowedscopesandmessage',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/OAuth/Presentation/Api/Validator/ValidScopes/ValidScopesValidatorTest.php' => 
    array (
      0 => '608040da125aeedc59ea0839c28805fa85c69b33',
      1 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\validator\\validscopes\\validscopesvalidatortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\oauth\\presentation\\api\\validator\\validscopes\\testvalidscopespasses',
        1 => 'tests\\unit\\oauth\\presentation\\api\\validator\\validscopes\\testinvalidscopefails',
        2 => 'tests\\unit\\oauth\\presentation\\api\\validator\\validscopes\\testcustomallowedscopes',
        3 => 'tests\\unit\\oauth\\presentation\\api\\validator\\validscopes\\testnullvaluepasses',
        4 => 'tests\\unit\\oauth\\presentation\\api\\validator\\validscopes\\testscalarvalueiswrapped',
        5 => 'tests\\unit\\oauth\\presentation\\api\\validator\\validscopes\\testnonstringvaluesareignored',
        6 => 'tests\\unit\\oauth\\presentation\\api\\validator\\validscopes\\testunexpectedconstraintthrows',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Onboarding/Application/Service/OrganizationOnboardingFlowServiceTest.php' => 
    array (
      0 => '1bfd5aec5a4bbe946360ad00e506e4776d5afb02',
      1 => 
      array (
        0 => 'tests\\unit\\onboarding\\application\\service\\organizationonboardingflowservicetest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\onboarding\\application\\service\\testgetflowcreatessessionwhennoneexists',
        1 => 'tests\\unit\\onboarding\\application\\service\\teststartwithresetdeletesexistingsession',
        2 => 'tests\\unit\\onboarding\\application\\service\\testgetflowwithpreexistingorgisnotadoptedforfreshsession',
        3 => 'tests\\unit\\onboarding\\application\\service\\testexecutestepthrowsinvalidargumentforunknownstep',
        4 => 'tests\\unit\\onboarding\\application\\service\\testexecutestepthrowslogicexceptionwhenstepisnotnext',
        5 => 'tests\\unit\\onboarding\\application\\service\\testexecutestepcreateorganizationhappypath',
        6 => 'tests\\unit\\onboarding\\application\\service\\testexecutestepcreateorganizationthrowswhennoorgcreated',
        7 => 'tests\\unit\\onboarding\\application\\service\\testexecutestepcreateorganizationthrowswhenpreexistingorgispresent',
        8 => 'tests\\unit\\onboarding\\application\\service\\testrollbacklaststepthrowswhennosession',
        9 => 'tests\\unit\\onboarding\\application\\service\\testrollbacklaststepthrowswhennorollbackactions',
        10 => 'tests\\unit\\onboarding\\application\\service\\testexecutestepinvitemembershappypath',
        11 => 'tests\\unit\\onboarding\\application\\service\\testskipstepinvitemembershappypath',
        12 => 'tests\\unit\\onboarding\\application\\service\\testskipstepthrowsforrequiredcreateorganizationstep',
        13 => 'tests\\unit\\onboarding\\application\\service\\testfullflowcompletionfiresevent',
        14 => 'tests\\unit\\onboarding\\application\\service\\testgetflowdoesnotautocompletestepsfrommodulestate',
        15 => 'tests\\unit\\onboarding\\application\\service\\testgetflowdoesnotdispatchcompletioneventfromexternalmodulestatealone',
        16 => 'tests\\unit\\onboarding\\application\\service\\testpinnedorgresetswhendeletedexternally',
        17 => 'tests\\unit\\onboarding\\application\\service\\testexecuteautodetectedstepthrowswhenmodulestatenotpresent',
        18 => 'tests\\unit\\onboarding\\application\\service\\testskippedstepsareclearedwhenpinnedorgisdeleted',
        19 => 'tests\\unit\\onboarding\\application\\service\\buildservice',
        20 => 'tests\\unit\\onboarding\\application\\service\\buildorganizationresult',
        21 => 'tests\\unit\\onboarding\\application\\service\\configurequerybus',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Onboarding/Domain/Model/OrganizationOnboardingSession/OrganizationOnboardingSessionTest.php' => 
    array (
      0 => '9ff63b52e93907c10c32b64132d373dcd5e67a91',
      1 => 
      array (
        0 => 'tests\\unit\\onboarding\\domain\\model\\organizationonboardingsession\\organizationonboardingsessiontest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\onboarding\\domain\\model\\organizationonboardingsession\\teststartcreatessessionwithcorrectinitialstate',
        1 => 'tests\\unit\\onboarding\\domain\\model\\organizationonboardingsession\\testreconstitutefiltersinvalidcompletedandskippedsteps',
        2 => 'tests\\unit\\onboarding\\domain\\model\\organizationonboardingsession\\testreconstitutepreservesallfields',
        3 => 'tests\\unit\\onboarding\\domain\\model\\organizationonboardingsession\\testmarkstepcompletedaddstocompletedandhistory',
        4 => 'tests\\unit\\onboarding\\domain\\model\\organizationonboardingsession\\testmarkstepcompletedisidempotent',
        5 => 'tests\\unit\\onboarding\\domain\\model\\organizationonboardingsession\\testmarkstepcompletedignoresinvalidstep',
        6 => 'tests\\unit\\onboarding\\domain\\model\\organizationonboardingsession\\testmarksteppendingremovesfromcompletedsteps',
        7 => 'tests\\unit\\onboarding\\domain\\model\\organizationonboardingsession\\testmarksteppendingisnoopwhenstepnotcompleted',
        8 => 'tests\\unit\\onboarding\\domain\\model\\organizationonboardingsession\\testmarkstepskippedaddstoskippedandhistory',
        9 => 'tests\\unit\\onboarding\\domain\\model\\organizationonboardingsession\\testmarkstepskippedisidempotent',
        10 => 'tests\\unit\\onboarding\\domain\\model\\organizationonboardingsession\\testmarkstepskippedignoresinvalidstep',
        11 => 'tests\\unit\\onboarding\\domain\\model\\organizationonboardingsession\\testremoveskippedstepremovesfromskippedlist',
        12 => 'tests\\unit\\onboarding\\domain\\model\\organizationonboardingsession\\testremoveskippedstepisnoopwhennotskipped',
        13 => 'tests\\unit\\onboarding\\domain\\model\\organizationonboardingsession\\testrollbackstacklifobehavior',
        14 => 'tests\\unit\\onboarding\\domain\\model\\organizationonboardingsession\\testpoprollbackactiononemptystackreturnsnull',
        15 => 'tests\\unit\\onboarding\\domain\\model\\organizationonboardingsession\\testclearrollbackstackemptiesallactions',
        16 => 'tests\\unit\\onboarding\\domain\\model\\organizationonboardingsession\\testclearstephistoryemptiesrecordedentries',
        17 => 'tests\\unit\\onboarding\\domain\\model\\organizationonboardingsession\\testclearrollbackstackisnoopwhenalreadyempty',
        18 => 'tests\\unit\\onboarding\\domain\\model\\organizationonboardingsession\\testsettargetorganizationupdatesfields',
        19 => 'tests\\unit\\onboarding\\domain\\model\\organizationonboardingsession\\testsettargetorganizationdirtycheckguardskipstouchwhenunchanged',
        20 => 'tests\\unit\\onboarding\\domain\\model\\organizationonboardingsession\\testcleartargetorganizationnullsoutfields',
        21 => 'tests\\unit\\onboarding\\domain\\model\\organizationonboardingsession\\testcleartargetorganizationdirtycheckguardskipstouchwhenalreadynull',
        22 => 'tests\\unit\\onboarding\\domain\\model\\organizationonboardingsession\\testsetinprogresstransitionupdatesnextstep',
        23 => 'tests\\unit\\onboarding\\domain\\model\\organizationonboardingsession\\testsetinprogressdirtycheckguardskipstouchwhenunchanged',
        24 => 'tests\\unit\\onboarding\\domain\\model\\organizationonboardingsession\\testsetblockedtransitionupdatesstate',
        25 => 'tests\\unit\\onboarding\\domain\\model\\organizationonboardingsession\\testsetblockeddirtycheckguardskipstouchwhenunchanged',
        26 => 'tests\\unit\\onboarding\\domain\\model\\organizationonboardingsession\\testsetcompletedtransitionupdatesstate',
        27 => 'tests\\unit\\onboarding\\domain\\model\\organizationonboardingsession\\testsetcompleteddirtycheckguardskipstouchwhenalreadycompleted',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Onboarding/Domain/Model/OrganizationOnboardingSession/RollbackAction/RollbackActionFactoryTest.php' => 
    array (
      0 => 'bf1e8079aaae74a6e0ba631223029a7e44204025',
      1 => 
      array (
        0 => 'tests\\unit\\onboarding\\domain\\model\\organizationonboardingsession\\rollbackaction\\rollbackactionfactorytest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\onboarding\\domain\\model\\organizationonboardingsession\\rollbackaction\\testfromarraycreatesdeleteorganizationaction',
        1 => 'tests\\unit\\onboarding\\domain\\model\\organizationonboardingsession\\rollbackaction\\testfromarraythrowsforunknownactiontype',
        2 => 'tests\\unit\\onboarding\\domain\\model\\organizationonboardingsession\\rollbackaction\\testfromarraythrowswhenactiondiscriminatormissing',
        3 => 'tests\\unit\\onboarding\\domain\\model\\organizationonboardingsession\\rollbackaction\\testfromarraythrowswhenactiondiscriminatorisempty',
        4 => 'tests\\unit\\onboarding\\domain\\model\\organizationonboardingsession\\rollbackaction\\testdeleteorganizationrollbackactionroundtrip',
        5 => 'tests\\unit\\onboarding\\domain\\model\\organizationonboardingsession\\rollbackaction\\testfromarraythrowswhenorganizationidmissing',
        6 => 'tests\\unit\\onboarding\\domain\\model\\organizationonboardingsession\\rollbackaction\\testfromarraythrowswhenorganizationidisempty',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Onboarding/Domain/Model/OrganizationOnboardingSession/StepHistoryEntryTest.php' => 
    array (
      0 => '710e38271448689d6542ef332b3120474103689d',
      1 => 
      array (
        0 => 'tests\\unit\\onboarding\\domain\\model\\organizationonboardingsession\\stephistoryentrytest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\onboarding\\domain\\model\\organizationonboardingsession\\testtoarrayreturnsexpectedstructure',
        1 => 'tests\\unit\\onboarding\\domain\\model\\organizationonboardingsession\\testtoarraywithskippedtrue',
        2 => 'tests\\unit\\onboarding\\domain\\model\\organizationonboardingsession\\testfromarrayreconstitutesallfields',
        3 => 'tests\\unit\\onboarding\\domain\\model\\organizationonboardingsession\\testfromarraydefaultstofalseformissingskipped',
        4 => 'tests\\unit\\onboarding\\domain\\model\\organizationonboardingsession\\testfromarraysafelyhandlesmissingfields',
        5 => 'tests\\unit\\onboarding\\domain\\model\\organizationonboardingsession\\testroundtriptoarrayfromarray',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Onboarding/Infrastructure/EventSubscriber/OnboardingNotificationSubscriberTest.php' => 
    array (
      0 => 'dcc9d58d72705768e364743249955c08d3e7f2bb',
      1 => 
      array (
        0 => 'tests\\unit\\onboarding\\infrastructure\\eventsubscriber\\onboardingnotificationsubscribertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\onboarding\\infrastructure\\eventsubscriber\\testgetsubscribedeventsregisterscompletedeventhandler',
        1 => 'tests\\unit\\onboarding\\infrastructure\\eventsubscriber\\testnotificationsentwithemailandmercurewhenuseremailresolved',
        2 => 'tests\\unit\\onboarding\\infrastructure\\eventsubscriber\\testemailchannelisdroppedwhenorganizationdisablesemail',
        3 => 'tests\\unit\\onboarding\\infrastructure\\eventsubscriber\\testnonotificationwhenorganizationdisableseverychannel',
        4 => 'tests\\unit\\onboarding\\infrastructure\\eventsubscriber\\testnotificationsentwithmercureonlywhenuseremailisnull',
        5 => 'tests\\unit\\onboarding\\infrastructure\\eventsubscriber\\testnotificationsentwithmercureonlywhenuserqueryfails',
        6 => 'tests\\unit\\onboarding\\infrastructure\\eventsubscriber\\testnotificationfailureiscaughtandlogged',
        7 => 'tests\\unit\\onboarding\\infrastructure\\eventsubscriber\\testnotificationpayloadcontainssessionandorganizationid',
        8 => 'tests\\unit\\onboarding\\infrastructure\\eventsubscriber\\policyport',
        9 => 'tests\\unit\\onboarding\\infrastructure\\eventsubscriber\\makesentnotification',
        10 => 'tests\\unit\\onboarding\\infrastructure\\eventsubscriber\\buildevent',
        11 => 'tests\\unit\\onboarding\\infrastructure\\eventsubscriber\\builduserview',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Onboarding/Infrastructure/Persistence/Doctrine/Mapper/OrganizationOnboardingSessionMapperTest.php' => 
    array (
      0 => '8b0a04c32065fe04106f8aa04db3e3d1dd9ba207',
      1 => 
      array (
        0 => 'tests\\unit\\onboarding\\infrastructure\\persistence\\doctrine\\mapper\\organizationonboardingsessionmappertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\onboarding\\infrastructure\\persistence\\doctrine\\mapper\\testtorecordmapsallfields',
        1 => 'tests\\unit\\onboarding\\infrastructure\\persistence\\doctrine\\mapper\\testtodomainmapsallfields',
        2 => 'tests\\unit\\onboarding\\infrastructure\\persistence\\doctrine\\mapper\\testroundtripdomaintorecordtodomain',
        3 => 'tests\\unit\\onboarding\\infrastructure\\persistence\\doctrine\\mapper\\testtorecordhandlesemptycollections',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Onboarding/Presentation/Api/Mapper/Onboarding/OrganizationOnboardingOutputAssemblerTest.php' => 
    array (
      0 => '9540b80b88a9f0c8bd5dee3a295bc405566a2c3f',
      1 => 
      array (
        0 => 'tests\\unit\\onboarding\\presentation\\api\\mapper\\onboarding\\organizationonboardingoutputassemblertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\onboarding\\presentation\\api\\mapper\\onboarding\\testfromstatewithnoorganization',
        1 => 'tests\\unit\\onboarding\\presentation\\api\\mapper\\onboarding\\testfromstatewithorgexistsbutcreatenotyetconfirmed',
        2 => 'tests\\unit\\onboarding\\presentation\\api\\mapper\\onboarding\\testfromstatewithorganizationandinvitepending',
        3 => 'tests\\unit\\onboarding\\presentation\\api\\mapper\\onboarding\\testfromstatecompleted',
        4 => 'tests\\unit\\onboarding\\presentation\\api\\mapper\\onboarding\\testfromstatewithskippedinvitemembers',
        5 => 'tests\\unit\\onboarding\\presentation\\api\\mapper\\onboarding\\testcompletedatisnullforstepthatwasrolledback',
        6 => 'tests\\unit\\onboarding\\presentation\\api\\mapper\\onboarding\\testcompletedatisexposedwhenstepisconfirmed',
        7 => 'tests\\unit\\onboarding\\presentation\\api\\mapper\\onboarding\\buildstate',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Onboarding/Presentation/Api/Processor/Onboarding/ExecuteOrganizationOnboardingStepProcessorTest.php' => 
    array (
      0 => '5bff1292bfcddcbccd23b88f24b8f15da27213b1',
      1 => 
      array (
        0 => 'tests\\unit\\onboarding\\presentation\\api\\processor\\onboarding\\executeorganizationonboardingstepprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\onboarding\\presentation\\api\\processor\\onboarding\\testprocessthrowswhenunauthenticated',
        1 => 'tests\\unit\\onboarding\\presentation\\api\\processor\\onboarding\\testprocessthrowsbadrequestwhenstepkeymissing',
        2 => 'tests\\unit\\onboarding\\presentation\\api\\processor\\onboarding\\testprocessbuildspayloadandmapsresultonsuccess',
        3 => 'tests\\unit\\onboarding\\presentation\\api\\processor\\onboarding\\testprocessthrowsconflictonlogicexception',
        4 => 'tests\\unit\\onboarding\\presentation\\api\\processor\\onboarding\\testprocessthrowsbadrequestoninvalidargumentexception',
        5 => 'tests\\unit\\onboarding\\presentation\\api\\processor\\onboarding\\buildflowservice',
        6 => 'tests\\unit\\onboarding\\presentation\\api\\processor\\onboarding\\buildorganizationresult',
        7 => 'tests\\unit\\onboarding\\presentation\\api\\processor\\onboarding\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Onboarding/Presentation/Api/Processor/Onboarding/RollbackOrganizationOnboardingProcessorTest.php' => 
    array (
      0 => '00372a094684e11a446db3ab051254ac74a12233',
      1 => 
      array (
        0 => 'tests\\unit\\onboarding\\presentation\\api\\processor\\onboarding\\rollbackorganizationonboardingprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\onboarding\\presentation\\api\\processor\\onboarding\\testprocessthrowswhenunauthenticated',
        1 => 'tests\\unit\\onboarding\\presentation\\api\\processor\\onboarding\\testprocessdelegatesandmapsresultonsuccess',
        2 => 'tests\\unit\\onboarding\\presentation\\api\\processor\\onboarding\\testprocessthrowsconflictonlogicexception',
        3 => 'tests\\unit\\onboarding\\presentation\\api\\processor\\onboarding\\testprocessthrowsconflictonmessengerruntimeexceptionwrappinglogicexception',
        4 => 'tests\\unit\\onboarding\\presentation\\api\\processor\\onboarding\\buildflowservice',
        5 => 'tests\\unit\\onboarding\\presentation\\api\\processor\\onboarding\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Onboarding/Presentation/Api/Processor/Onboarding/SkipOrganizationOnboardingStepProcessorTest.php' => 
    array (
      0 => '8cb0b96552468f693cac5df80ad09be8017fc460',
      1 => 
      array (
        0 => 'tests\\unit\\onboarding\\presentation\\api\\processor\\onboarding\\skiporganizationonboardingstepprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\onboarding\\presentation\\api\\processor\\onboarding\\testprocessthrowswhenunauthenticated',
        1 => 'tests\\unit\\onboarding\\presentation\\api\\processor\\onboarding\\testprocessthrowsbadrequestwhenstepkeymissing',
        2 => 'tests\\unit\\onboarding\\presentation\\api\\processor\\onboarding\\testprocessthrowsbadrequestonnonskippablestep',
        3 => 'tests\\unit\\onboarding\\presentation\\api\\processor\\onboarding\\testprocessthrowsconflictonlogicexception',
        4 => 'tests\\unit\\onboarding\\presentation\\api\\processor\\onboarding\\testprocessthrowsconflictonmessengerruntimeexceptionwrappinglogicexception',
        5 => 'tests\\unit\\onboarding\\presentation\\api\\processor\\onboarding\\testprocessskipsstepsuccessfully',
        6 => 'tests\\unit\\onboarding\\presentation\\api\\processor\\onboarding\\buildflowservice',
        7 => 'tests\\unit\\onboarding\\presentation\\api\\processor\\onboarding\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Onboarding/Presentation/Api/Processor/Onboarding/StartOrganizationOnboardingProcessorTest.php' => 
    array (
      0 => '76112251c82ea8877f944c5d60b1ab5fd9bb3998',
      1 => 
      array (
        0 => 'tests\\unit\\onboarding\\presentation\\api\\processor\\onboarding\\startorganizationonboardingprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\onboarding\\presentation\\api\\processor\\onboarding\\testprocessthrowswhenunauthenticated',
        1 => 'tests\\unit\\onboarding\\presentation\\api\\processor\\onboarding\\testprocessstartsflowwithoutreset',
        2 => 'tests\\unit\\onboarding\\presentation\\api\\processor\\onboarding\\testprocessstartsflowwithreset',
        3 => 'tests\\unit\\onboarding\\presentation\\api\\processor\\onboarding\\buildflowservice',
        4 => 'tests\\unit\\onboarding\\presentation\\api\\processor\\onboarding\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Onboarding/Presentation/Api/Provider/Onboarding/OrganizationOnboardingProviderTest.php' => 
    array (
      0 => '1c473a09e948bf85872e1a8bf640cca5b2a907cb',
      1 => 
      array (
        0 => 'tests\\unit\\onboarding\\presentation\\api\\provider\\onboarding\\organizationonboardingprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\onboarding\\presentation\\api\\provider\\onboarding\\testprovidethrowswhenunauthenticated',
        1 => 'tests\\unit\\onboarding\\presentation\\api\\provider\\onboarding\\testprovidedelegatestoflowservice',
        2 => 'tests\\unit\\onboarding\\presentation\\api\\provider\\onboarding\\createsecurityuser',
        3 => 'tests\\unit\\onboarding\\presentation\\api\\provider\\onboarding\\createflowservicefornoorganization',
        4 => 'tests\\unit\\onboarding\\presentation\\api\\provider\\onboarding\\createunusedflowservice',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Organization/Application/Service/OrganizationAuthorizationServiceTest.php' => 
    array (
      0 => 'ca2be23475e0aa0e32ab1f722892eeb021a0090e',
      1 => 
      array (
        0 => 'tests\\unit\\organization\\application\\service\\organizationauthorizationservicetest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\organization\\application\\service\\testhaspermissionreturnstrueforexactmatch',
        1 => 'tests\\unit\\organization\\application\\service\\testhaspermissionreturnstrueforwildcardmatch',
        2 => 'tests\\unit\\organization\\application\\service\\testpermissionlookupiscachedperuserandorganization',
        3 => 'tests\\unit\\organization\\application\\service\\testgetuserpermissionsusessharedcachebeforerepository',
        4 => 'tests\\unit\\organization\\application\\service\\testgetuserpermissionsrefreshesstaleemptysharedcache',
        5 => 'tests\\unit\\organization\\application\\service\\testresetclearspermissioncache',
        6 => 'tests\\unit\\organization\\application\\service\\testhaspermissionreturnsfalsewhennopermissionmatches',
        7 => 'tests\\unit\\organization\\application\\service\\testhaspermissiondoesnotescalatereadtomanagewithinsameresource',
        8 => 'tests\\unit\\organization\\application\\service\\testgetuserpermissionsreturnsrepositoryvalues',
        9 => 'tests\\unit\\organization\\application\\service\\testassertgrantedpermissionsacceptswildcardpermissionsinsinglerepositorylookup',
        10 => 'tests\\unit\\organization\\application\\service\\testassertgrantedpermissionsthrowsonfirstmissingpermission',
        11 => 'tests\\unit\\organization\\application\\service\\testassertgrantedpermissionsthrowswhenlaterpermissionismissing',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Organization/Application/Service/OrganizationNotificationPolicyServiceTest.php' => 
    array (
      0 => '67b51838a245f1bd626de0d3dff6ddcacd59e58b',
      1 => 
      array (
        0 => 'tests\\unit\\organization\\application\\service\\organizationnotificationpolicyservicetest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\organization\\application\\service\\itreturnsthepersistednotificationpolicy',
        1 => 'tests\\unit\\organization\\application\\service\\itfallsbacktodefaultswhenorganizationisunknown',
        2 => 'tests\\unit\\organization\\application\\service\\itfallsbacktodefaultswhenidentifierismalformed',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Organization/Application/Service/OrganizationQuotaServiceTest.php' => 
    array (
      0 => '879c1dc34913e01ad550cbdf080383959c1c0895',
      1 => 
      array (
        0 => 'tests\\unit\\organization\\application\\service\\organizationquotaservicetest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\organization\\application\\service\\testgetlimitandusageperresource',
        1 => 'tests\\unit\\organization\\application\\service\\testassertcanaddpassesunderlimit',
        2 => 'tests\\unit\\organization\\application\\service\\testassertcanaddthrowsatlimit',
        3 => 'tests\\unit\\organization\\application\\service\\testassertcanaddpasseswhenunlimited',
        4 => 'tests\\unit\\organization\\application\\service\\testgetquotasummarycoverseveryresource',
        5 => 'tests\\unit\\organization\\application\\service\\testplanresolutioniscachedinmemory',
        6 => 'tests\\unit\\organization\\application\\service\\service',
        7 => 'tests\\unit\\organization\\application\\service\\organization',
        8 => 'tests\\unit\\organization\\application\\service\\plan',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Organization/Application/UseCase/Command/Organization/AcceptOrganizationInvitation/AcceptOrganizationInvitationHandlerTest.php' => 
    array (
      0 => 'bc6f59f38a3d85ce8d01dabf0228eac126adb6e5',
      1 => 
      array (
        0 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\acceptorganizationinvitation\\acceptorganizationinvitationhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\acceptorganizationinvitation\\testinvokeacceptsinvitationandsendsnotificationtoinviter',
        1 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\acceptorganizationinvitation\\testinvokealsonotifiesownerwhenownerdiffersfrominviter',
        2 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\acceptorganizationinvitation\\testinvokereturnsresultwheninviternotificationfailsbutownernotificationstillsends',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Organization/Application/UseCase/Command/Organization/AddOrganizationMember/AddOrganizationMemberHandlerTest.php' => 
    array (
      0 => 'f74aa4b05c2d98dfa5508345765e2fe350adef7c',
      1 => 
      array (
        0 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\addorganizationmember\\addorganizationmemberhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\addorganizationmember\\testinvokecreatesmemberandassignsdefaultrolewhennorolesprovided',
        1 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\addorganizationmember\\testinvokereactivatesexistinginactivememberbeforeassigningroles',
        2 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\addorganizationmember\\testinvokededuplicatesroleidsbeforelookupandassignment',
        3 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\addorganizationmember\\testinvokethrowswhenorganizationdoesnotexist',
        4 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\addorganizationmember\\testinvokethrowswhenonerequestedroleismissing',
        5 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\addorganizationmember\\testinvokereturnsresultwhennotificationdispatchfailsfornewmember',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Organization/Application/UseCase/Command/Organization/AssignOrganizationRoleToMember/AssignOrganizationRoleToMemberHandlerTest.php' => 
    array (
      0 => 'e6977dc43f26d25078de0b62d47096fe48f95e6f',
      1 => 
      array (
        0 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\assignorganizationroletomember\\assignorganizationroletomemberhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\assignorganizationroletomember\\testinvokeassignsrolewhenmemberandrolebelongtocommandorganization',
        1 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\assignorganizationroletomember\\testinvokethrowswhenmembernotfound',
        2 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\assignorganizationroletomember\\testinvokethrowswhenmemberdoesnotbelongtocommandorganization',
        3 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\assignorganizationroletomember\\testinvokethrowswhenroledoesnotbelongtocommandorganization',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Organization/Application/UseCase/Command/Organization/ChangeOrganizationPlan/ChangeOrganizationPlanHandlerTest.php' => 
    array (
      0 => '11ba33484c8eb5fb2c233b57fa612b1e24d6f97c',
      1 => 
      array (
        0 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\changeorganizationplan\\changeorganizationplanhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\changeorganizationplan\\testchangesplanandpersistsorganization',
        1 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\changeorganizationplan\\testthrowswhenplannotfound',
        2 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\changeorganizationplan\\testthrowswhenplanisinactive',
        3 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\changeorganizationplan\\organization',
        4 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\changeorganizationplan\\plan',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Organization/Application/UseCase/Command/Organization/CreateOrganization/CreateOrganizationHandlerTest.php' => 
    array (
      0 => 'a56737507cf999f7e59ef428b3f51cfcaed84b39',
      1 => 
      array (
        0 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\createorganization\\createorganizationhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\createorganization\\testinvokecreatesorganizationownermemberandsystemroles',
        1 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\createorganization\\testinvokethrowswhenowneruserdoesnotexist',
        2 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\createorganization\\testinvokethrowswhenslugalreadyexists',
        3 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\createorganization\\getsqlstate',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Organization/Application/UseCase/Command/Organization/CreateOrganizationRole/CreateOrganizationRoleHandlerTest.php' => 
    array (
      0 => '4e953664f5e9b4c11b65bffa578581d9c503455f',
      1 => 
      array (
        0 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\createorganizationrole\\createorganizationrolehandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\createorganizationrole\\testinvokecreatesroleanddeduplicatespermissions',
        1 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\createorganizationrole\\testinvokethrowswhenorganizationdoesnotexist',
        2 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\createorganizationrole\\testinvokethrowswhenrolenamealreadyexists',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Organization/Application/UseCase/Command/Organization/DeleteOrganizationRole/DeleteOrganizationRoleHandlerTest.php' => 
    array (
      0 => '12bae1745a70f6165068b3de1cc92fb7fe4cd535',
      1 => 
      array (
        0 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\deleteorganizationrole\\deleteorganizationrolehandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\deleteorganizationrole\\testinvokedeletesnonsystemrole',
        1 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\deleteorganizationrole\\testinvokethrowswhenorganizationnotfound',
        2 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\deleteorganizationrole\\testinvokethrowswhenrolenotfound',
        3 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\deleteorganizationrole\\testinvokethrowswhenroledoesnotbelongtoorganization',
        4 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\deleteorganizationrole\\testinvokethrowswhenattemptingtodeletesystemrole',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Organization/Application/UseCase/Command/Organization/InviteOrganizationMember/InviteOrganizationMemberHandlerTest.php' => 
    array (
      0 => '7e94d87b014026220a816562f1083ebca8f2e594',
      1 => 
      array (
        0 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\inviteorganizationmember\\inviteorganizationmemberhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\inviteorganizationmember\\testinvokecreatesinvitationandsendsnotification',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Organization/Application/UseCase/Command/Organization/RemoveOrganizationMember/RemoveOrganizationMemberHandlerTest.php' => 
    array (
      0 => 'd18cab614fb610d72db16dd49d6824680e1c273c',
      1 => 
      array (
        0 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\removeorganizationmember\\removeorganizationmemberhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\removeorganizationmember\\testinvokedeactivatesmemberwhenmemberbelongstoorganization',
        1 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\removeorganizationmember\\testinvokethrowswhenorganizationnotfound',
        2 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\removeorganizationmember\\testinvokethrowswhenmembernotfound',
        3 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\removeorganizationmember\\testinvokethrowswhenmemberdoesnotbelongtoorganization',
        4 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\removeorganizationmember\\testinvokereturnsresultwhennotificationdispatchfails',
        5 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\removeorganizationmember\\testinvokedoesnotnotifywhenmemberalreadyinactive',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Organization/Application/UseCase/Command/Organization/RemoveOrganizationRoleFromMember/RemoveOrganizationRoleFromMemberHandlerTest.php' => 
    array (
      0 => '1c77bd323c96191b9826417f51174d52cdd2b7af',
      1 => 
      array (
        0 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\removeorganizationrolefrommember\\removeorganizationrolefrommemberhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\removeorganizationrolefrommember\\testinvokeunassignsrolefrommemberwhenallbelongtoorganization',
        1 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\removeorganizationrolefrommember\\testinvokethrowswhenorganizationnotfound',
        2 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\removeorganizationrolefrommember\\testinvokethrowswhenmembernotfound',
        3 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\removeorganizationrolefrommember\\testinvokethrowswhenmemberdoesnotbelongtoorganization',
        4 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\removeorganizationrolefrommember\\testinvokethrowswhenrolenotfound',
        5 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\removeorganizationrolefrommember\\testinvokethrowswhenroledoesnotbelongtoorganization',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Organization/Application/UseCase/Command/Organization/RevokeOrganizationInvitation/RevokeOrganizationInvitationHandlerTest.php' => 
    array (
      0 => 'c875d3181deeda14a8121ddc2add6a63d6803b98',
      1 => 
      array (
        0 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\revokeorganizationinvitation\\revokeorganizationinvitationhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\revokeorganizationinvitation\\testinvokereturnsresultwhennotificationdispatchfails',
        1 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\revokeorganizationinvitation\\testinvokelogswhenemailchannelisreportedasundelivered',
        2 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\revokeorganizationinvitation\\testinvokethrowswheninvitationbelongstoanotherorganization',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Organization/Application/UseCase/Command/Organization/UpdateOrganizationSettings/UpdateOrganizationSettingsHandlerTest.php' => 
    array (
      0 => '05ffd7456b844aae614829bfac466d2d1ceaeb99',
      1 => 
      array (
        0 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\updateorganizationsettings\\updateorganizationsettingshandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\updateorganizationsettings\\testinvokeappliesprovidedfieldsandsaves',
        1 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\updateorganizationsettings\\testinvokeappliesnotificationandregionalsections',
        2 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\updateorganizationsettings\\testinvokethrowswhenorganizationnotfound',
        3 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\updateorganizationsettings\\testinvokethrowswhenslugalreadyexists',
        4 => 'tests\\unit\\organization\\application\\usecase\\command\\organization\\updateorganizationsettings\\getsqlstate',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Organization/Application/UseCase/Query/Organization/GetCurrentOrganizationMemberProfile/GetCurrentOrganizationMemberProfileHandlerTest.php' => 
    array (
      0 => '4228f1e8ad4c9d79ec0b1ea03bd346c7b8766bab',
      1 => 
      array (
        0 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getcurrentorganizationmemberprofile\\getcurrentorganizationmemberprofilehandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getcurrentorganizationmemberprofile\\testinvokereturnscurrentactivememberprofile',
        1 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getcurrentorganizationmemberprofile\\testinvokethrowswhenorganizationnotfound',
        2 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getcurrentorganizationmemberprofile\\testinvokethrowswhenmembershipisinactive',
        3 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getcurrentorganizationmemberprofile\\testinvokeignoresstaleemptycachedprofileandrecomputespermissions',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Organization/Application/UseCase/Query/Organization/GetOrganization/GetOrganizationHandlerTest.php' => 
    array (
      0 => 'f2d49535f24b479dcd623cbb9d1af1e969f8a41a',
      1 => 
      array (
        0 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getorganization\\getorganizationhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getorganization\\testinvokereturnsmappedorganizationresult',
        1 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getorganization\\testinvokethrowswhenorganizationnotfound',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Organization/Application/UseCase/Query/Organization/GetOrganizationDashboard/DashboardDateTimeParserTest.php' => 
    array (
      0 => 'ac8cb0434ebfea25d015ea158fcdbad074da8a7c',
      1 => 
      array (
        0 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getorganizationdashboard\\dashboarddatetimeparsertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getorganizationdashboard\\testparseacceptsiso8601datetimeswithexplicittimezoneoffset',
        1 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getorganizationdashboard\\testparserejectsdatetimeswithoutexplicittimezoneoffset',
        2 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getorganizationdashboard\\testparserejectsrelativedatestrings',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Organization/Application/UseCase/Query/Organization/GetOrganizationDashboard/GetOrganizationDashboardHandlerTest.php' => 
    array (
      0 => '0d8f99765c40f4bc852ee21875bf1c48af8052a1',
      1 => 
      array (
        0 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getorganizationdashboard\\getorganizationdashboardhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getorganizationdashboard\\testinvokereturnslightdashboardoverviewhealthalertsandcomparison',
        1 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getorganizationdashboard\\testinvokereturnsdashboardwithoutcomparisonwhendisabled',
        2 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getorganizationdashboard\\testinvokekeepscomparisonboundsinrequestedtimezone',
        3 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getorganizationdashboard\\testinvokenormalizesmembercomparisonboundstoutcstoragetimezone',
        4 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getorganizationdashboard\\testinvokeacceptssingleexplicitboundwithouttimezone',
        5 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getorganizationdashboard\\testinvokerejectssingleexplicitnonutcoffsetwithouttimezone',
        6 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getorganizationdashboard\\testinvokeappliesanalyticsfilterstodashboardmetrics',
        7 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getorganizationdashboard\\testinvokeresolvedstatusfilterdoesnotemitunresolvedalerts',
        8 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getorganizationdashboard\\testinvokethrowswhenorganizationnotfound',
        9 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getorganizationdashboard\\testinvokethrowswhendashboarddependencypermissionismissing',
        10 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getorganizationdashboard\\resolvednonconformitystatusprovider',
        11 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getorganizationdashboard\\createdashboardauthorizationmock',
        12 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getorganizationdashboard\\createorganizationrepository',
        13 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getorganizationdashboard\\createzeromemberrepository',
        14 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getorganizationdashboard\\createzerorolerepository',
        15 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getorganizationdashboard\\createzeroinvitationrepository',
        16 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getorganizationdashboard\\createzerofacilitystatistics',
        17 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getorganizationdashboard\\createzeroequipmentstatistics',
        18 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getorganizationdashboard\\createzeroinspectionstatistics',
        19 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getorganizationdashboard\\createzerononconformitystatistics',
        20 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getorganizationdashboard\\createhandler',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Organization/Application/UseCase/Query/Organization/GetOrganizationDashboardTrend/GetOrganizationDashboardTrendHandlerTest.php' => 
    array (
      0 => '7807383bf1ac01d73329ecf8b3519d30a1f6cfb6',
      1 => 
      array (
        0 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getorganizationdashboardtrend\\getorganizationdashboardtrendhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getorganizationdashboardtrend\\testinvokereturnsinspectiontrendwithcomparison',
        1 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getorganizationdashboardtrend\\testinvokereturnsequipmentcreatedtrendwithcomparisonandfilters',
        2 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getorganizationdashboardtrend\\testinvokereturnsfacilitiescreatedtrendwithoutcomparison',
        3 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getorganizationdashboardtrend\\testinvokereturnsopenednonconformitytrendwithoutcomparison',
        4 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getorganizationdashboardtrend\\testinvokethrowswhenmetricpermissionismissing',
        5 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getorganizationdashboardtrend\\testinvokethrowswhenorganizationnotfoundafterpermissioncheck',
        6 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getorganizationdashboardtrend\\testinvokedoesnotrequiredashboardreadfortrendmetrics',
        7 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getorganizationdashboardtrend\\testinvokethrowswhenmetricisunsupported',
        8 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getorganizationdashboardtrend\\testinvokeappliesanalyticsfilterstoinspectiontrendmetric',
        9 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getorganizationdashboardtrend\\createorganization',
        10 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getorganizationdashboardtrend\\createinspectionstatisticsmock',
        11 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getorganizationdashboardtrend\\createnonconformitystatisticsmock',
        12 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getorganizationdashboardtrend\\createequipmentstatisticsmock',
        13 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getorganizationdashboardtrend\\createfacilitystatisticsmock',
        14 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\getorganizationdashboardtrend\\createmetricauthorizationmock',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Organization/Application/UseCase/Query/Organization/ListOrganizationMembers/ListOrganizationMembersHandlerTest.php' => 
    array (
      0 => '5730a9b89c04b6fc479284b79f6f192ac319fc51',
      1 => 
      array (
        0 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\listorganizationmembers\\listorganizationmembershandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\listorganizationmembers\\testinvokereturnsmemberswithroleids',
        1 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\listorganizationmembers\\testinvokethrowswhenorganizationnotfound',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Organization/Application/UseCase/Query/Organization/ListOrganizationRoles/ListOrganizationRolesHandlerTest.php' => 
    array (
      0 => '235f666a5bc0a8cdd03398a965dae37ab106a7c6',
      1 => 
      array (
        0 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\listorganizationroles\\listorganizationroleshandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\listorganizationroles\\testinvokereturnsrolecollection',
        1 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\listorganizationroles\\testinvokethrowswhenorganizationnotfound',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Organization/Application/UseCase/Query/Organization/ListUserOrganizations/ListUserOrganizationsHandlerTest.php' => 
    array (
      0 => '99ea7a6ef610b79e02805bb940da69d3a5d50da4',
      1 => 
      array (
        0 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\listuserorganizations\\listuserorganizationshandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\listuserorganizations\\testinvokereturnsdistinctactivecompaniesforuser',
        1 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\listuserorganizations\\testinvokereturnsemptylistwhennoactivememberships',
        2 => 'tests\\unit\\organization\\application\\usecase\\query\\organization\\listuserorganizations\\testinvokethrowswhenstatusisinvalid',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Organization/Domain/Catalog/OrganizationSystemRoleCatalogTest.php' => 
    array (
      0 => 'a71b4d018a00950d2286548b4dc8dfac87e2f9ef',
      1 => 
      array (
        0 => 'tests\\unit\\organization\\domain\\catalog\\organizationsystemrolecatalogtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\organization\\domain\\catalog\\testpermissionsformemberincludesdashboardreadpermissions',
        1 => 'tests\\unit\\organization\\domain\\catalog\\testmergepermissionskeepscanonicalsystempermissions',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Organization/Domain/Model/Plan/PlanTest.php' => 
    array (
      0 => 'f6a40b2cfd7b25cf9ef26e5e85e1bb4d5c58142d',
      1 => 
      array (
        0 => 'tests\\unit\\organization\\domain\\model\\plan\\plantest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\organization\\domain\\model\\plan\\testcreateexposeslimitsanddefaults',
        1 => 'tests\\unit\\organization\\domain\\model\\plan\\testcreaterejectsunknownresource',
        2 => 'tests\\unit\\organization\\domain\\model\\plan\\testcreaterejectsnegativelimit',
        3 => 'tests\\unit\\organization\\domain\\model\\plan\\testcreaterejectstooshortname',
        4 => 'tests\\unit\\organization\\domain\\model\\plan\\testchangelimitsreplacescaps',
        5 => 'tests\\unit\\organization\\domain\\model\\plan\\testmutationsupdatestate',
        6 => 'tests\\unit\\organization\\domain\\model\\plan\\testreconstituterestoresstate',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Organization/Domain/ValueObject/OrganizationQuotaResourceTest.php' => 
    array (
      0 => '5b299c880eff6c22147ea0c7eb7f5e65fcf47fbc',
      1 => 
      array (
        0 => 'tests\\unit\\organization\\domain\\valueobject\\organizationquotaresourcetest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\organization\\domain\\valueobject\\testsummarizephrasesacappedallowance',
        1 => 'tests\\unit\\organization\\domain\\valueobject\\testsummarizephrasesanunlimitedallowance',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Organization/Domain/ValueObject/OrganizationSettingsTest.php' => 
    array (
      0 => 'e46acdb65ac6e1c95ef43fca3f63f0ff87eff51d',
      1 => 
      array (
        0 => 'tests\\unit\\organization\\domain\\valueobject\\organizationsettingstest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\organization\\domain\\valueobject\\testdefaultappliessensibledefaults',
        1 => 'tests\\unit\\organization\\domain\\valueobject\\testfromemptyarrayfallsbacktodefaults',
        2 => 'tests\\unit\\organization\\domain\\valueobject\\testtoarrayandfromarrayroundtrip',
        3 => 'tests\\unit\\organization\\domain\\valueobject\\testwithnotificationsreturnsnewimmutableinstance',
        4 => 'tests\\unit\\organization\\domain\\valueobject\\testwithregionalreturnsnewimmutableinstance',
        5 => 'tests\\unit\\organization\\domain\\valueobject\\testregionalrejectsunknowntimezone',
        6 => 'tests\\unit\\organization\\domain\\valueobject\\testregionalrejectsunsupportedlocale',
        7 => 'tests\\unit\\organization\\domain\\valueobject\\testregionalrejectsunsupportedmeasurementsystem',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Organization/Infrastructure/Persistence/Doctrine/Repository/OrganizationMemberRepositoryTest.php' => 
    array (
      0 => 'f5aaf8f1e740215b35f899edee76b3b635030483',
      1 => 
      array (
        0 => 'tests\\unit\\organization\\infrastructure\\persistence\\doctrine\\repository\\organizationmemberrepositorytest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\organization\\infrastructure\\persistence\\doctrine\\repository\\testgetpermissionnamesforuserinorganizationcompleteslegacysystemrolepermissions',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Organization/Infrastructure/Persistence/Doctrine/Repository/OrganizationRepositoryTest.php' => 
    array (
      0 => 'f65671952d461b50a4f2f65912940498a8aac648',
      1 => 
      array (
        0 => 'tests\\unit\\organization\\infrastructure\\persistence\\doctrine\\repository\\organizationrepositorytest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\organization\\infrastructure\\persistence\\doctrine\\repository\\testdeleteremovesorganization',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Organization/Infrastructure/Persistence/Doctrine/Repository/OrganizationRoleRepositoryTest.php' => 
    array (
      0 => 'bcea443e7d3b1f923a2e57761ba6eccae2a8309b',
      1 => 
      array (
        0 => 'tests\\unit\\organization\\infrastructure\\persistence\\doctrine\\repository\\organizationrolerepositorytest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\organization\\infrastructure\\persistence\\doctrine\\repository\\testfindbyorganizationidcompleteslegacysystemrolepermissions',
        1 => 'tests\\unit\\organization\\infrastructure\\persistence\\doctrine\\repository\\testsaveupdatesexistingrecorddescription',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Organization/Presentation/Api/Processor/Organization/AddOrganizationMemberProcessorTest.php' => 
    array (
      0 => '8d9c1ccf0dd19cbe842d630789dcbb81dc7869e1',
      1 => 
      array (
        0 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\addorganizationmemberprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\testprocessthrowswhenuserlackspermission',
        1 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\testprocessdispatchescommandandmapsoutput',
        2 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\testprocessthrowswhenorganizationidmissinginuri',
        3 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Organization/Presentation/Api/Processor/Organization/AssignOrganizationRoleToMemberProcessorTest.php' => 
    array (
      0 => 'e3a7896bcff6dcb21ca27c636cf3dca3809b1196',
      1 => 
      array (
        0 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\assignorganizationroletomemberprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\testprocessthrowswhenurivariablesmissing',
        1 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\testprocessthrowswhenpermissionismissing',
        2 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\testprocessdispatchescommandandmapsoutput',
        3 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Organization/Presentation/Api/Processor/Organization/CreateOrganizationProcessorTest.php' => 
    array (
      0 => '7939c74ca4f144a43ef9d78d60889c5baf9b4ff2',
      1 => 
      array (
        0 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\createorganizationprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\testprocessdispatchescreateorganizationcommandandmapsoutput',
        1 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\testprocessthrowsconflictwhenslugalreadyexists',
        2 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\testprocessthrowsconflictwhenslugalreadyexistsiswrappedinmessengerruntimeexception',
        3 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\testprocessthrowswhenunauthenticated',
        4 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Organization/Presentation/Api/Processor/Organization/CreateOrganizationRoleProcessorTest.php' => 
    array (
      0 => '80ca192c1b5f4a1fd2435f1dd628d0c31fa6eec3',
      1 => 
      array (
        0 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\createorganizationroleprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\testprocessthrowswhenpermissionismissing',
        1 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\testprocessdispatchescommandandmapsroleoutput',
        2 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\testprocessthrowswhenorganizationidmissing',
        3 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Organization/Presentation/Api/Processor/Organization/DeleteOrganizationProcessorTest.php' => 
    array (
      0 => 'cbf192f385e5c667769b3e36cbee058a60ef99ac',
      1 => 
      array (
        0 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\deleteorganizationprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\testprocessthrowswhenunauthenticated',
        1 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\testprocessthrowswhenidentifiermissing',
        2 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\testprocessthrowswhendeletepermissionmissing',
        3 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\testprocessdispatchescommandandreturnsnull',
        4 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\testprocessthrowsnotfoundwhenorganizationabsent',
        5 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Organization/Presentation/Api/Processor/Organization/DeleteOrganizationRoleProcessorTest.php' => 
    array (
      0 => 'ca52af1ad68475a302ac134d8ddbdf66a1032b54',
      1 => 
      array (
        0 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\deleteorganizationroleprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\testprocessthrowswhenunauthenticated',
        1 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\testprocessthrowswhenurivariablesmissing',
        2 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\testprocessthrowswhenpermissionismissing',
        3 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\testprocessdispatchescommandandreturnsnull',
        4 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\testprocessthrowsnotfoundwhenroleabsent',
        5 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\testprocessthrowsbadrequestwhensystemrole',
        6 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\testprocessthrowsnotfoundwhenorganizationabsent',
        7 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Organization/Presentation/Api/Processor/Organization/InviteOrganizationMemberProcessorTest.php' => 
    array (
      0 => '8a020f285b941bc98eeb30993079e40179904665',
      1 => 
      array (
        0 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\inviteorganizationmemberprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\testprocessthrowswhenuserlackspermission',
        1 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\testprocessdispatchescommandandmapsoutput',
        2 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Organization/Presentation/Api/Processor/Organization/RemoveOrganizationMemberProcessorTest.php' => 
    array (
      0 => '1ea6db5c8124282262f04a0fd5c3311b8b939130',
      1 => 
      array (
        0 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\removeorganizationmemberprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\testprocessthrowswhenunauthenticated',
        1 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\testprocessthrowswhenurivariablesmissing',
        2 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\testprocessthrowswhenpermissionismissing',
        3 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\testprocessdispatchescommandandreturnsnull',
        4 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\testprocessthrowsnotfoundwhenmemberabsent',
        5 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Organization/Presentation/Api/Processor/Organization/RemoveOrganizationRoleFromMemberProcessorTest.php' => 
    array (
      0 => '97790240a87ce4bb9747401a27539ba8c7386f4d',
      1 => 
      array (
        0 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\removeorganizationrolefrommemberprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\testprocessthrowswhenunauthenticated',
        1 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\testprocessthrowswhenurivariablesmissing',
        2 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\testprocessthrowswhenpermissionismissing',
        3 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\testprocessdispatchescommandandreturnsnull',
        4 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\testprocessthrowsnotfoundwhenroleabsent',
        5 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\testprocessthrowsnotfoundwhenmemberabsent',
        6 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\testprocessthrowsnotfoundwhenorganizationabsent',
        7 => 'tests\\unit\\organization\\presentation\\api\\processor\\organization\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Organization/Presentation/Api/Provider/Organization/GetCurrentOrganizationMemberProfileProviderTest.php' => 
    array (
      0 => 'f6d5cafbf89a137d11d1f007be8c3d53aa451174',
      1 => 
      array (
        0 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\getcurrentorganizationmemberprofileprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovidereturnsnullwhenorganizationidmissing',
        1 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovidethrowswhennotauthenticated',
        2 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovidemapscurrentorganizationmemberprofile',
        3 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovidemapsmissingmembershiptonotfound',
        4 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Organization/Presentation/Api/Provider/Organization/GetOrganizationDashboardProviderTest.php' => 
    array (
      0 => '29e66d259c26c36bd74c802f015a6a1a08b75e35',
      1 => 
      array (
        0 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\getorganizationdashboardprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovidethrowswhenunauthenticated',
        1 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovidereturnsnullwhenorganizationidismissing',
        2 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovidethrowswhendashboarddependencypermissionismissing',
        3 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovidemapslightdashboardpayloadandextractsfilters',
        4 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovidefallsbacktofirstprimarymetricandmapsdirectionvariants',
        5 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovideusesselectedstatusfiltersasoverviewprimarymetrics',
        6 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovidethrowswhendatetimefiltersareinvalid',
        7 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovideacceptstimezonefilterformixedoffsets',
        8 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovideextractsanalyticsfilters',
        9 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovidethrowswhenanalyticsenumfilterisinvalid',
        10 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovidethrowswhencomparefilterisinvalidboolean',
        11 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovideacceptsdocumentedstringcomparealiases',
        12 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovidemapswrappedpermissiondenialtohttp403',
        13 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovidemapswrappedorganizationnotfoundtohttp404',
        14 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\documentedstringcomparefilterprovider',
        15 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\createemptyresult',
        16 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\createsecurityuser',
        17 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\createdashboardauthorizationmock',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Organization/Presentation/Api/Provider/Organization/GetOrganizationDashboardTrendProviderTest.php' => 
    array (
      0 => '825aa850734c83b87ef131090c9bdb84faeac4c4',
      1 => 
      array (
        0 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\getorganizationdashboardtrendprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovidethrowswhenunauthenticated',
        1 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovidemapsinspectiontrendpayloadandextractsfilters',
        2 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovidemapsequipmentcreatedtrendpayloadandextractsfilters',
        3 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovidenormalizestrendcomparisonpayload',
        4 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovidethrowswhenmetricpermissionismissing',
        5 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovidedoesnotrequiredashboardreadfortrendmetrics',
        6 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovidemapswrappedtrendpermissiondenialtohttp403',
        7 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovidemapswrappedtrendorganizationnotfoundtohttp404',
        8 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovideextractsanalyticsfiltersfortrendmetrics',
        9 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovidethrowswhentrendreceivesunsupportedmetricscopedfilters',
        10 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovidemapswrappedtrendinvalidargumenttohttp400',
        11 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovidethrowswhentrendanalyticsenumfilterisinvalid',
        12 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovidethrowswhentrendcomparefilterisinvalidboolean',
        13 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovidethrowswhentrendcomparefilterisemptystring',
        14 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovidethrowswhentrendcomparefilterisnonscalar',
        15 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovideacceptsnativebooleancomparefilterfortrend',
        16 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovideacceptscomparealiasofffortrend',
        17 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovideacceptsdocumentedstringcomparealiasesfortrend',
        18 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\documentedstringcomparefilterprovider',
        19 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\metricscopedunsupportedfilterprovider',
        20 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\createsecurityuser',
        21 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\createmetricauthorizationmock',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Organization/Presentation/Api/Provider/Organization/GetOrganizationProviderTest.php' => 
    array (
      0 => '46eeaacff03c8c536ff46c0fc7ee020a25df501c',
      1 => 
      array (
        0 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\getorganizationprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovidereturnsnullwhenidismissing',
        1 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovidethrowswhenpermissionismissing',
        2 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovidemapsqueryresulttooutput',
        3 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Organization/Presentation/Api/Provider/Organization/ListOrganizationInvitationsProviderTest.php' => 
    array (
      0 => '3eeff02fb335b21609624cca533807c01e90666d',
      1 => 
      array (
        0 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\listorganizationinvitationsprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovidereturnsemptyarraywhenorganizationidmissing',
        1 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovidethrowswhenpermissionismissing',
        2 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovidemapsinvitationsresult',
        3 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovidethrowswhennotauthenticated',
        4 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Organization/Presentation/Api/Provider/Organization/ListOrganizationInvitationStatusesProviderTest.php' => 
    array (
      0 => 'b6b16754185341be39daac59f6a12a106934d9e7',
      1 => 
      array (
        0 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\listorganizationinvitationstatusesprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovidethrowswhenunauthenticated',
        1 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovidereturnsorganizationinvitationstatusoptions',
        2 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Organization/Presentation/Api/Provider/Organization/ListOrganizationMembersProviderTest.php' => 
    array (
      0 => 'f4c12bde5d93aa1dedbfa0c873f3b8e1d7afb92e',
      1 => 
      array (
        0 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\listorganizationmembersprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovidereturnsemptyarraywhenorganizationidmissing',
        1 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovidethrowswhenpermissionismissing',
        2 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovidemapsmembersresult',
        3 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovidethrowswhennotauthenticated',
        4 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovideexposestotalitemsinpaginator',
        5 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Organization/Presentation/Api/Provider/Organization/ListOrganizationRolesProviderTest.php' => 
    array (
      0 => '659bd66125297f964d5d823ec1a9af2e32ace4e3',
      1 => 
      array (
        0 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\listorganizationrolesprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovidereturnsemptyarraywhenorganizationidmissing',
        1 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovidethrowswhenpermissionismissing',
        2 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovidemapsrolesresult',
        3 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Organization/Presentation/Api/Provider/Organization/ListOrganizationStatusesProviderTest.php' => 
    array (
      0 => '4bf544907f07b2c90bd2aa224ffc911aa357e001',
      1 => 
      array (
        0 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\listorganizationstatusesprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovidethrowswhenunauthenticated',
        1 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovidereturnsorganizationstatusoptions',
        2 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Organization/Presentation/Api/Provider/Organization/ListUserOrganizationsProviderTest.php' => 
    array (
      0 => 'da8db6e41c9e54cc3c3810d0d5a1aba5ee355df0',
      1 => 
      array (
        0 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\listuserorganizationsprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovidethrowswhenunauthenticated',
        1 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovidemapsuserorganizations',
        2 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovideexposestotalitemsinpaginator',
        3 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\testprovidepassesstatussearchandsortingtoquery',
        4 => 'tests\\unit\\organization\\presentation\\api\\provider\\organization\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Otp/Application/Exception/OtpNotFoundExceptionTest.php' => 
    array (
      0 => 'd99eff144bd9c254844fac1a828c5ef1791fd15c',
      1 => 
      array (
        0 => 'tests\\unit\\otp\\application\\exception\\otpnotfoundexceptiontest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\otp\\application\\exception\\testforidentifiercreatesmessageandcontext',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Otp/Application/Exception/ResendNotAllowedExceptionTest.php' => 
    array (
      0 => 'fd20c20524487cb5e26eea4a9e34d75a8a70fc7f',
      1 => 
      array (
        0 => 'tests\\unit\\otp\\application\\exception\\resendnotallowedexceptiontest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\otp\\application\\exception\\testcontextandretryafter',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Otp/Application/Service/ChallengeResendPolicyTest.php' => 
    array (
      0 => '1d5db1415b781f1804032ed7aa1682dec836528d',
      1 => 
      array (
        0 => 'tests\\unit\\otp\\application\\service\\challengeresendpolicytest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\otp\\application\\service\\testcanresendinreturnsremainingseconds',
        1 => 'tests\\unit\\otp\\application\\service\\testcanresendindoesnotreturnnegative',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Otp/Application/Service/OtpChallengeServiceTest.php' => 
    array (
      0 => 'f60d6762bacd57e735f433a918eb7ee217df2bc7',
      1 => 
      array (
        0 => 'tests\\unit\\otp\\application\\service\\otpchallengeservicetest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\otp\\application\\service\\testgeneratereturnschallengeinfo',
        1 => 'tests\\unit\\otp\\application\\service\\testverifyreturnsfailurewhennotfound',
        2 => 'tests\\unit\\otp\\application\\service\\testverifyreturnssuccess',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Otp/Application/UseCase/Command/Challenge/GenerateOtp/GenerateOtpHandlerTest.php' => 
    array (
      0 => '1183e3645be701172ef09559561450d0dbca9967',
      1 => 
      array (
        0 => 'tests\\unit\\otp\\application\\usecase\\command\\challenge\\generateotp\\generateotphandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\otp\\application\\usecase\\command\\challenge\\generateotp\\testinvokegeneratesotpandsendsnotification',
        1 => 'tests\\unit\\otp\\application\\usecase\\command\\challenge\\generateotp\\testinvokedoesnotsendnotificationfortotp',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Otp/Application/UseCase/Command/Challenge/ResendChallenge/ResendChallengeHandlerTest.php' => 
    array (
      0 => '2a0136d40052c3a7af8cf2a091fa93565bd35c89',
      1 => 
      array (
        0 => 'tests\\unit\\otp\\application\\usecase\\command\\challenge\\resendchallenge\\resendchallengehandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\otp\\application\\usecase\\command\\challenge\\resendchallenge\\testinvokethrowswhenotpmissing',
        1 => 'tests\\unit\\otp\\application\\usecase\\command\\challenge\\resendchallenge\\testinvokethrowswhenresendnotallowed',
        2 => 'tests\\unit\\otp\\application\\usecase\\command\\challenge\\resendchallenge\\testinvokereturnsresendresult',
        3 => 'tests\\unit\\otp\\application\\usecase\\command\\challenge\\resendchallenge\\creategeneratehandler',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Otp/Application/UseCase/Command/Challenge/VerifyOtp/VerifyOtpHandlerTest.php' => 
    array (
      0 => '2cfe0294bd0008b41042c382ef1e5094d3881f5f',
      1 => 
      array (
        0 => 'tests\\unit\\otp\\application\\usecase\\command\\challenge\\verifyotp\\verifyotphandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\otp\\application\\usecase\\command\\challenge\\verifyotp\\testinvokeverifiescorrectcode',
        1 => 'tests\\unit\\otp\\application\\usecase\\command\\challenge\\verifyotp\\testinvokerejectsincorrectcode',
        2 => 'tests\\unit\\otp\\application\\usecase\\command\\challenge\\verifyotp\\testinvokethrowswhenotpnotfound',
        3 => 'tests\\unit\\otp\\application\\usecase\\command\\challenge\\verifyotp\\testinvokethrowswhennoidentifiersprovided',
        4 => 'tests\\unit\\otp\\application\\usecase\\command\\challenge\\verifyotp\\testinvokeuseschallengetoken',
        5 => 'tests\\unit\\otp\\application\\usecase\\command\\challenge\\verifyotp\\testinvokereturnsexpirederror',
        6 => 'tests\\unit\\otp\\application\\usecase\\command\\challenge\\verifyotp\\testinvokereturnsmaxattemptserror',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Otp/Application/UseCase/Command/Challenge/VerifyOtp/VerifyOtpResultTest.php' => 
    array (
      0 => '459cac095725dd44aa992dcc8741c06ad606d3a3',
      1 => 
      array (
        0 => 'tests\\unit\\otp\\application\\usecase\\command\\challenge\\verifyotp\\verifyotpresulttest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\otp\\application\\usecase\\command\\challenge\\verifyotp\\testsuccessfactory',
        1 => 'tests\\unit\\otp\\application\\usecase\\command\\challenge\\verifyotp\\testfailedfactory',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Otp/Application/UseCase/Command/Totp/SetupTotp/SetupTotpHandlerTest.php' => 
    array (
      0 => '09d070b95ff4a8c3bd8406e7f8926a4e6712216e',
      1 => 
      array (
        0 => 'tests\\unit\\otp\\application\\usecase\\command\\totp\\setuptotp\\setuptotphandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\otp\\application\\usecase\\command\\totp\\setuptotp\\testinvokegeneratestotpsecret',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Otp/Application/UseCase/Query/Challenge/GetChallengeStatus/GetChallengeStatusHandlerTest.php' => 
    array (
      0 => '717737128cbfa7d60769152311a583078e67f1a0',
      1 => 
      array (
        0 => 'tests\\unit\\otp\\application\\usecase\\query\\challenge\\getchallengestatus\\getchallengestatushandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\otp\\application\\usecase\\query\\challenge\\getchallengestatus\\testinvokethrowswhenotpmissing',
        1 => 'tests\\unit\\otp\\application\\usecase\\query\\challenge\\getchallengestatus\\testinvokereturnsstatusforpendingotp',
        2 => 'tests\\unit\\otp\\application\\usecase\\query\\challenge\\getchallengestatus\\testinvokereturnszeroresendforverifiedotp',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Otp/Application/UseCase/Query/Config/ListChannels/ListChannelsHandlerTest.php' => 
    array (
      0 => '871bab66eeb941581bd5e72cd4ea112a0db532f9',
      1 => 
      array (
        0 => 'tests\\unit\\otp\\application\\usecase\\query\\config\\listchannels\\listchannelshandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\otp\\application\\usecase\\query\\config\\listchannels\\testinvokereturnschannels',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Otp/Application/UseCase/Query/Config/ListPurposes/ListPurposesHandlerTest.php' => 
    array (
      0 => '39646b33d4792c2171e39a23c02095b1070d49ba',
      1 => 
      array (
        0 => 'tests\\unit\\otp\\application\\usecase\\query\\config\\listpurposes\\listpurposeshandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\otp\\application\\usecase\\query\\config\\listpurposes\\testinvokereturnspurposes',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Otp/Domain/Event/OtpFailedEventTest.php' => 
    array (
      0 => '3d3e3b66da59d1f29759a1a994dd521aeda48828',
      1 => 
      array (
        0 => 'tests\\unit\\otp\\domain\\event\\otpfailedeventtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\otp\\domain\\event\\testeventpayload',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Otp/Domain/Event/OtpGeneratedEventTest.php' => 
    array (
      0 => 'dbd9586131136e5e1acc5586522bb884493fe1b4',
      1 => 
      array (
        0 => 'tests\\unit\\otp\\domain\\event\\otpgeneratedeventtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\otp\\domain\\event\\testeventpayload',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Otp/Domain/Event/OtpVerifiedEventTest.php' => 
    array (
      0 => '23703480654d001bc902e564f34bba22049c802e',
      1 => 
      array (
        0 => 'tests\\unit\\otp\\domain\\event\\otpverifiedeventtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\otp\\domain\\event\\testeventpayload',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Otp/Domain/Exception/OtpExpiredExceptionTest.php' => 
    array (
      0 => '4446c59078dc11487483184d7ebc5e872a4972ce',
      1 => 
      array (
        0 => 'tests\\unit\\otp\\domain\\exception\\otpexpiredexceptiontest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\otp\\domain\\exception\\testcreateincludesid',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Otp/Domain/Exception/OtpMaxAttemptsExceptionTest.php' => 
    array (
      0 => 'e82a74b5f7341d59055037770c62b124d557d702',
      1 => 
      array (
        0 => 'tests\\unit\\otp\\domain\\exception\\otpmaxattemptsexceptiontest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\otp\\domain\\exception\\testcreateincludesid',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Otp/Domain/Exception/OtpNotFoundExceptionTest.php' => 
    array (
      0 => 'dbd1f3a6c8e8aa37628ed1cee5496f1750a0224b',
      1 => 
      array (
        0 => 'tests\\unit\\otp\\domain\\exception\\otpnotfoundexceptiontest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\otp\\domain\\exception\\testcreateincludesid',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Otp/Domain/Model/OtpTest.php' => 
    array (
      0 => 'c32f5bdbeb80e2e1417b67d8099b1159360bcda7',
      1 => 
      array (
        0 => 'tests\\unit\\otp\\domain\\model\\otptest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\otp\\domain\\model\\testgeneratecreatesotp',
        1 => 'tests\\unit\\otp\\domain\\model\\testverifywithcorrectcode',
        2 => 'tests\\unit\\otp\\domain\\model\\testverifywithincorrectcode',
        3 => 'tests\\unit\\otp\\domain\\model\\testverifyexceedsmaxattempts',
        4 => 'tests\\unit\\otp\\domain\\model\\testmaskedrecipientemail',
        5 => 'tests\\unit\\otp\\domain\\model\\testmaskedrecipientsms',
        6 => 'tests\\unit\\otp\\domain\\model\\testmaskedrecipienttotp',
        7 => 'tests\\unit\\otp\\domain\\model\\testcustomttl',
        8 => 'tests\\unit\\otp\\domain\\model\\testreleaseevents',
        9 => 'tests\\unit\\otp\\domain\\model\\testreconstitutepreservesstate',
        10 => 'tests\\unit\\otp\\domain\\model\\teststatusexpiredandcannotverify',
        11 => 'tests\\unit\\otp\\domain\\model\\testmaskedrecipientemailshortlocalpart',
        12 => 'tests\\unit\\otp\\domain\\model\\testmaskedrecipientemailinvalidformat',
        13 => 'tests\\unit\\otp\\domain\\model\\testmaskedrecipientphoneshortnumber',
        14 => 'tests\\unit\\otp\\domain\\model\\testverifythrowswhenexpired',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Otp/Domain/ValueObject/ChallengeTokenTest.php' => 
    array (
      0 => 'e16dc199f508cccc7e52860c1fb09193304e5808',
      1 => 
      array (
        0 => 'tests\\unit\\otp\\domain\\valueobject\\challengetokentest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\otp\\domain\\valueobject\\testgeneratecreatestoken',
        1 => 'tests\\unit\\otp\\domain\\valueobject\\testequalsusesconstanttimecompare',
        2 => 'tests\\unit\\otp\\domain\\valueobject\\testtostringreturnsvalue',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Otp/Domain/ValueObject/OtpChannelTest.php' => 
    array (
      0 => 'e24668516ebb56a5cd2c58fcb9285df28d83ff30',
      1 => 
      array (
        0 => 'tests\\unit\\otp\\domain\\valueobject\\otpchanneltest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\otp\\domain\\valueobject\\testlabelsanddeliveryflags',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Otp/Domain/ValueObject/OtpCodeTest.php' => 
    array (
      0 => 'a63c0cd665d2a26d29956b596d5a6a6c41ab591f',
      1 => 
      array (
        0 => 'tests\\unit\\otp\\domain\\valueobject\\otpcodetest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\otp\\domain\\valueobject\\testgeneratecreates6digitcode',
        1 => 'tests\\unit\\otp\\domain\\valueobject\\testverifywithcorrectcode',
        2 => 'tests\\unit\\otp\\domain\\valueobject\\testverifywithincorrectcode',
        3 => 'tests\\unit\\otp\\domain\\valueobject\\testfromhashcanverify',
        4 => 'tests\\unit\\otp\\domain\\valueobject\\testfromhashplainnotavailable',
        5 => 'tests\\unit\\otp\\domain\\valueobject\\testmaskedshowslasttwodigits',
        6 => 'tests\\unit\\otp\\domain\\valueobject\\testmaskedreturnsplaceholderwhenplainmissing',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Otp/Domain/ValueObject/OtpContextTest.php' => 
    array (
      0 => 'aead24f6cd81adb3ab87d4bb98a6a37be3a08255',
      1 => 
      array (
        0 => 'tests\\unit\\otp\\domain\\valueobject\\otpcontexttest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\otp\\domain\\valueobject\\testfromarrayfiltersnonstringkeys',
        1 => 'tests\\unit\\otp\\domain\\valueobject\\testtoarrayandisempty',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Otp/Domain/ValueObject/OtpIdTest.php' => 
    array (
      0 => '3e8127df0da2e991146745dcff44d8b490b62d89',
      1 => 
      array (
        0 => 'tests\\unit\\otp\\domain\\valueobject\\otpidtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\otp\\domain\\valueobject\\testcreatewithvaliduuid',
        1 => 'tests\\unit\\otp\\domain\\valueobject\\testcreatewithinvaliduuidthrowsexception',
        2 => 'tests\\unit\\otp\\domain\\valueobject\\testequality',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Otp/Domain/ValueObject/OtpMetadataTest.php' => 
    array (
      0 => '0377daaa7d78a1069e4f4046ceb77bf63734ab4c',
      1 => 
      array (
        0 => 'tests\\unit\\otp\\domain\\valueobject\\otpmetadatatest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\otp\\domain\\valueobject\\testfromarraysupportssnakecase',
        1 => 'tests\\unit\\otp\\domain\\valueobject\\testtoarrayandisempty',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Otp/Domain/ValueObject/OtpPurposeTest.php' => 
    array (
      0 => '15d3896875494956a9c9a7c001d769dce5657ef1',
      1 => 
      array (
        0 => 'tests\\unit\\otp\\domain\\valueobject\\otppurposetest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\otp\\domain\\valueobject\\testdefaultsandlabels',
        1 => 'tests\\unit\\otp\\domain\\valueobject\\purposeprovider',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Otp/Domain/ValueObject/TotpSecretTest.php' => 
    array (
      0 => '0af94d70c10965a203ea02b2908eb120a05332f6',
      1 => 
      array (
        0 => 'tests\\unit\\otp\\domain\\valueobject\\totpsecrettest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\otp\\domain\\valueobject\\testgeneratecreatesvalidsecret',
        1 => 'tests\\unit\\otp\\domain\\valueobject\\testgetprovisioninguriincludessecret',
        2 => 'tests\\unit\\otp\\domain\\valueobject\\testinvalidsecretthrowsexception',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Otp/Infrastructure/Adapter/Notifier/OtpNotifierAdapterTest.php' => 
    array (
      0 => 'd0f1369794c381f8479abc5806959b1485f414ac',
      1 => 
      array (
        0 => 'tests\\unit\\otp\\infrastructure\\adapter\\notifier\\otpnotifieradaptertest',
        1 => 'tests\\unit\\otp\\infrastructure\\adapter\\notifier\\fakemailer',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\otp\\infrastructure\\adapter\\notifier\\testsendemailusesmailer',
        1 => 'tests\\unit\\otp\\infrastructure\\adapter\\notifier\\testsendsmsusesnotifier',
        2 => 'tests\\unit\\otp\\infrastructure\\adapter\\notifier\\testsendtotpdoesnothing',
        3 => 'tests\\unit\\otp\\infrastructure\\adapter\\notifier\\testsendemailskipswhencodemissing',
        4 => 'tests\\unit\\otp\\infrastructure\\adapter\\notifier\\testsendemailusespurposespecificsubject',
        5 => 'tests\\unit\\otp\\infrastructure\\adapter\\notifier\\testsendemailrendershtmlfromtwigtemplate',
        6 => 'tests\\unit\\otp\\infrastructure\\adapter\\notifier\\createtwigenvironment',
        7 => 'tests\\unit\\otp\\infrastructure\\adapter\\notifier\\createotp',
        8 => 'tests\\unit\\otp\\infrastructure\\adapter\\notifier\\createotpwithoutplaincode',
        9 => 'tests\\unit\\otp\\infrastructure\\adapter\\notifier\\send',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Otp/Infrastructure/Adapter/Notifier/TotpAdapterTest.php' => 
    array (
      0 => '9b539039af555fef86f66e429ecaec5232e4de24',
      1 => 
      array (
        0 => 'tests\\unit\\otp\\infrastructure\\adapter\\notifier\\totpadaptertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\otp\\infrastructure\\adapter\\notifier\\testgeneratesecretreturnsvalidsecret',
        1 => 'tests\\unit\\otp\\infrastructure\\adapter\\notifier\\testgetprovisioningurireturnsvaliduri',
        2 => 'tests\\unit\\otp\\infrastructure\\adapter\\notifier\\testverifyrejectsinvalidformat',
        3 => 'tests\\unit\\otp\\infrastructure\\adapter\\notifier\\testverifyacceptsgeneratedcode',
        4 => 'tests\\unit\\otp\\infrastructure\\adapter\\notifier\\invokeprivate',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Otp/Infrastructure/Notification/OtpNotificationTest.php' => 
    array (
      0 => '376ea897905497f30a1afa941cbd6c60e5d97c43',
      1 => 
      array (
        0 => 'tests\\unit\\otp\\infrastructure\\notification\\otpnotificationtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\otp\\infrastructure\\notification\\testchannelsforemailandsmsandtotp',
        1 => 'tests\\unit\\otp\\infrastructure\\notification\\testassmsmessagereturnsmessageforsmschannel',
        2 => 'tests\\unit\\otp\\infrastructure\\notification\\testassmsmessagereturnsnullfornonsmschannel',
        3 => 'tests\\unit\\otp\\infrastructure\\notification\\testsubjectmatchespurpose',
        4 => 'tests\\unit\\otp\\infrastructure\\notification\\testassmsmessageusesmaskedcodewhenplainmissing',
        5 => 'tests\\unit\\otp\\infrastructure\\notification\\testgetotpreturnsoriginalotp',
        6 => 'tests\\unit\\otp\\infrastructure\\notification\\otpsubjectprovider',
        7 => 'tests\\unit\\otp\\infrastructure\\notification\\createotp',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Otp/Infrastructure/Persistence/Doctrine/Mapper/OtpMapperTest.php' => 
    array (
      0 => '7bb249aaac976e34e27b85584f4ab6e5cc9eacc2',
      1 => 
      array (
        0 => 'tests\\unit\\otp\\infrastructure\\persistence\\doctrine\\mapper\\otpmappertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\otp\\infrastructure\\persistence\\doctrine\\mapper\\testtorecordmapsotp',
        1 => 'tests\\unit\\otp\\infrastructure\\persistence\\doctrine\\mapper\\testtodomainmapsrecord',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Otp/Infrastructure/Persistence/Doctrine/Record/OtpRecordTest.php' => 
    array (
      0 => '37f4cdb38eda451920b39e8017d09670e51ff7bd',
      1 => 
      array (
        0 => 'tests\\unit\\otp\\infrastructure\\persistence\\doctrine\\record\\otprecordtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\otp\\infrastructure\\persistence\\doctrine\\record\\testsettersandgetters',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Otp/Infrastructure/Persistence/Doctrine/Repository/OtpRepositoryTest.php' => 
    array (
      0 => 'a73e25a0a03ecf4f5d0b24843061cd46684a19ee',
      1 => 
      array (
        0 => 'tests\\unit\\otp\\infrastructure\\persistence\\doctrine\\repository\\otprepositorytest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\otp\\infrastructure\\persistence\\doctrine\\repository\\testfindbyidreturnsnullwhenmissing',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Otp/Presentation/Api/Processor/Challenge/CreateChallengeProcessorTest.php' => 
    array (
      0 => 'e05bcd815be9e4c34845c61dee4123175f35265a',
      1 => 
      array (
        0 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\createchallengeprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\testprocessthrowswhennotauthenticated',
        1 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\testprocessthrowswhenrecipientmissing',
        2 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\getuseridentifier',
        3 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\getroles',
        4 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\erasecredentials',
        5 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\testprocesscreateschallenge',
        6 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\getuseridentifier',
        7 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\getroles',
        8 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\erasecredentials',
        9 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\getemail',
        10 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\testprocessusesphonewhensmsrecipientmissing',
        11 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\getuseridentifier',
        12 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\getroles',
        13 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\erasecredentials',
        14 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\getphone',
        15 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\testprocessthrowstoomanyrequestswhenratelimited',
        16 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\getuseridentifier',
        17 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\getroles',
        18 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\erasecredentials',
        19 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\createratelimiterfactory',
        20 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\createratelimitkey',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Otp/Presentation/Api/Processor/Challenge/ResendChallengeProcessorTest.php' => 
    array (
      0 => '1e63aad628b2b987b8dce1f7af3706874ee63a74',
      1 => 
      array (
        0 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\resendchallengeprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\testprocessthrowswhentokenmissing',
        1 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\testprocessthrowswhenusermissing',
        2 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\testprocessreturnschallengeoutput',
        3 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\getuseridentifier',
        4 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\getroles',
        5 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\erasecredentials',
        6 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\testprocessmapsotpnotfoundexception',
        7 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\getuseridentifier',
        8 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\getroles',
        9 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\erasecredentials',
        10 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\testprocessmapsresendnotallowedexception',
        11 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\getuseridentifier',
        12 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\getroles',
        13 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\erasecredentials',
        14 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\testprocessmapsotpnotfoundhandlerfailedexception',
        15 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\getuseridentifier',
        16 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\getroles',
        17 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\erasecredentials',
        18 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\testprocessmapsresendnotallowedmessengerexception',
        19 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\getuseridentifier',
        20 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\getroles',
        21 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\erasecredentials',
        22 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\testprocessmapsresendnotallowedhandlerfailedexception',
        23 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\getuseridentifier',
        24 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\getroles',
        25 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\erasecredentials',
        26 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\testprocessmapsotpnotfoundmessengerpreviouschain',
        27 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\getuseridentifier',
        28 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\getroles',
        29 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\erasecredentials',
        30 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\testprocessmapsresendnotallowedmessengerpreviouschain',
        31 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\getuseridentifier',
        32 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\getroles',
        33 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\erasecredentials',
        34 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\testprocessrethrowsunhandledexception',
        35 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\getuseridentifier',
        36 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\getroles',
        37 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\erasecredentials',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Otp/Presentation/Api/Processor/Challenge/VerifyOtpProcessorTest.php' => 
    array (
      0 => '7836dd57d95d2842403b3d81aee2e40d9453692b',
      1 => 
      array (
        0 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\verifyotpprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\testprocessthrowswhenidentifiersmissing',
        1 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\testprocessmapsresult',
        2 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\testprocessmapsotpnotfoundexception',
        3 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\testprocessmapshandlerfailedexception',
        4 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\testprocessmapsmessengerruntimeexception',
        5 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\testprocessrethrowsmessengerruntimeexceptionwhennotfoundmissing',
        6 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\testprocessthrowstoomanyrequestswhenratelimited',
        7 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\createratelimiterfactory',
        8 => 'tests\\unit\\otp\\presentation\\api\\processor\\challenge\\createratelimitkey',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Otp/Presentation/Api/Processor/Totp/SetupTotpProcessorTest.php' => 
    array (
      0 => '64816f87d84716ed6aa3d2a18c58af6da10e5e1d',
      1 => 
      array (
        0 => 'tests\\unit\\otp\\presentation\\api\\processor\\totp\\setuptotpprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\otp\\presentation\\api\\processor\\totp\\testprocessthrowswhennotauthenticated',
        1 => 'tests\\unit\\otp\\presentation\\api\\processor\\totp\\testprocessmapsresult',
        2 => 'tests\\unit\\otp\\presentation\\api\\processor\\totp\\getuseridentifier',
        3 => 'tests\\unit\\otp\\presentation\\api\\processor\\totp\\getroles',
        4 => 'tests\\unit\\otp\\presentation\\api\\processor\\totp\\erasecredentials',
        5 => 'tests\\unit\\otp\\presentation\\api\\processor\\totp\\getemail',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Otp/Presentation/Api/Provider/Challenge/GetChallengeStatusProviderTest.php' => 
    array (
      0 => 'bb3e673d47314835649e2be9f1abb11d6ac81319',
      1 => 
      array (
        0 => 'tests\\unit\\otp\\presentation\\api\\provider\\challenge\\getchallengestatusprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\otp\\presentation\\api\\provider\\challenge\\testprovidethrowswhentokenmissing',
        1 => 'tests\\unit\\otp\\presentation\\api\\provider\\challenge\\testprovidemapsresult',
        2 => 'tests\\unit\\otp\\presentation\\api\\provider\\challenge\\testprovidemapsotpnotfound',
        3 => 'tests\\unit\\otp\\presentation\\api\\provider\\challenge\\testprovidemapsotpnotfoundhandlerfailedexception',
        4 => 'tests\\unit\\otp\\presentation\\api\\provider\\challenge\\testprovidemapsotpnotfoundmessengerexception',
        5 => 'tests\\unit\\otp\\presentation\\api\\provider\\challenge\\testproviderethrowsunknownexception',
        6 => 'tests\\unit\\otp\\presentation\\api\\provider\\challenge\\testproviderethrowshandlerfailedwhenotpnotfoundmissing',
        7 => 'tests\\unit\\otp\\presentation\\api\\provider\\challenge\\testprovidemapsotpnotfoundfrommessengerprevious',
        8 => 'tests\\unit\\otp\\presentation\\api\\provider\\challenge\\testprovidemapsotpnotfoundfromdeepmessengerprevious',
        9 => 'tests\\unit\\otp\\presentation\\api\\provider\\challenge\\testprovidereturnszeroexpiresinwhenexpired',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Otp/Presentation/Api/Provider/Config/ListChannelsProviderTest.php' => 
    array (
      0 => 'c052031011f832f2dfe208a6263c0c4b1e67037f',
      1 => 
      array (
        0 => 'tests\\unit\\otp\\presentation\\api\\provider\\config\\listchannelsprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\otp\\presentation\\api\\provider\\config\\testprovidemapschannels',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Otp/Presentation/Api/Provider/Config/ListPurposesProviderTest.php' => 
    array (
      0 => '4cc9a2a81313a2a161bfa42782b19f91ce66c7b3',
      1 => 
      array (
        0 => 'tests\\unit\\otp\\presentation\\api\\provider\\config\\listpurposesprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\otp\\presentation\\api\\provider\\config\\testprovidemapspurposes',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Otp/Presentation/Api/Resource/OtpResourcesTest.php' => 
    array (
      0 => 'ad780c10d21d12483b0419ae618c9c19357bce32',
      1 => 
      array (
        0 => 'tests\\unit\\otp\\presentation\\api\\resource\\otpresourcestest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\otp\\presentation\\api\\resource\\testresourcescanbeinstantiated',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Session/Application/Service/SessionTrackingServiceTest.php' => 
    array (
      0 => '4b80c177ddccecbca694a75fecfe11f629604bd3',
      1 => 
      array (
        0 => 'tests\\unit\\session\\application\\service\\sessiontrackingservicetest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\session\\application\\service\\testrecordsessionskipsemptyuserid',
        1 => 'tests\\unit\\session\\application\\service\\testrecordsessiondispatchescreate',
        2 => 'tests\\unit\\session\\application\\service\\testrotatesessiontokensdispatchesupdate',
        3 => 'tests\\unit\\session\\application\\service\\testrotatesessiontokensskipswhenmissingids',
        4 => 'tests\\unit\\session\\application\\service\\testrevokesessionbytokenskipsemptytokens',
        5 => 'tests\\unit\\session\\application\\service\\testrevokesessionbytokendispatchescommand',
        6 => 'tests\\unit\\session\\application\\service\\createuuidfactory',
        7 => 'tests\\unit\\session\\application\\service\\__construct',
        8 => 'tests\\unit\\session\\application\\service\\generate',
        9 => 'tests\\unit\\session\\application\\service\\createsession',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Session/Application/UseCase/Command/Session/CreateSession/CreateSessionHandlerTest.php' => 
    array (
      0 => '1f2c7cb49a2b9136c65e59a79c68c2eb5fed3f2d',
      1 => 
      array (
        0 => 'tests\\unit\\session\\application\\usecase\\command\\session\\createsession\\createsessionhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\session\\application\\usecase\\command\\session\\createsession\\testinvokecreatesnewsession',
        1 => 'tests\\unit\\session\\application\\usecase\\command\\session\\createsession\\testinvokedefaultsipanduseragentandmergesmetadata',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Session/Application/UseCase/Command/Session/RevokeAllUserSessions/RevokeAllUserSessionsHandlerTest.php' => 
    array (
      0 => 'c03d2facde8c54b1ddff8b0e321ad00c3b22b9e1',
      1 => 
      array (
        0 => 'tests\\unit\\session\\application\\usecase\\command\\session\\revokeallusersessions\\revokeallusersessionshandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\session\\application\\usecase\\command\\session\\revokeallusersessions\\testinvokerevokesallsessions',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Session/Application/UseCase/Command/Session/RevokeSession/RevokeSessionHandlerTest.php' => 
    array (
      0 => '9e55ba55f0a6d8c14f5f8812f8b10c2c35ab02cd',
      1 => 
      array (
        0 => 'tests\\unit\\session\\application\\usecase\\command\\session\\revokesession\\revokesessionhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\session\\application\\usecase\\command\\session\\revokesession\\testinvokerevokesexistingsession',
        1 => 'tests\\unit\\session\\application\\usecase\\command\\session\\revokesession\\testinvokethrowsexceptionwhensessionnotfound',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Session/Application/UseCase/Command/Session/RevokeSessionByToken/RevokeSessionByTokenHandlerTest.php' => 
    array (
      0 => '68fb290411a5749f56b5520d441eb91ea7b672ef',
      1 => 
      array (
        0 => 'tests\\unit\\session\\application\\usecase\\command\\session\\revokesessionbytoken\\revokesessionbytokenhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\session\\application\\usecase\\command\\session\\revokesessionbytoken\\testinvokerevokesusingrefreshtoken',
        1 => 'tests\\unit\\session\\application\\usecase\\command\\session\\revokesessionbytoken\\testinvokefallsbacktoaccesstoken',
        2 => 'tests\\unit\\session\\application\\usecase\\command\\session\\revokesessionbytoken\\testinvokereturnsfalsewhenmissing',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Session/Application/UseCase/Command/Session/UpdateSessionTokens/UpdateSessionTokensHandlerTest.php' => 
    array (
      0 => '63fc45f228abdeef3781f5c93f18be7cd19af6e7',
      1 => 
      array (
        0 => 'tests\\unit\\session\\application\\usecase\\command\\session\\updatesessiontokens\\updatesessiontokenshandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\session\\application\\usecase\\command\\session\\updatesessiontokens\\testinvokeupdatestokensbyrefreshtoken',
        1 => 'tests\\unit\\session\\application\\usecase\\command\\session\\updatesessiontokens\\testinvokefallsbacktoaccesstoken',
        2 => 'tests\\unit\\session\\application\\usecase\\command\\session\\updatesessiontokens\\testinvokereturnsfalsewhenmissing',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Session/Application/UseCase/Query/Session/GetSession/GetSessionHandlerTest.php' => 
    array (
      0 => '9aa9e7bc68a39d2854ea54cc386fdae6bb4b646b',
      1 => 
      array (
        0 => 'tests\\unit\\session\\application\\usecase\\query\\session\\getsession\\getsessionhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\session\\application\\usecase\\query\\session\\getsession\\testinvokereturnssession',
        1 => 'tests\\unit\\session\\application\\usecase\\query\\session\\getsession\\testinvokethrowsexceptionwhennotfound',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Session/Application/UseCase/Query/Session/ListUserSessions/ListUserSessionsHandlerTest.php' => 
    array (
      0 => '1885bb4dfc2f3588a2a8088a6f1efd8877ca6673',
      1 => 
      array (
        0 => 'tests\\unit\\session\\application\\usecase\\query\\session\\listusersessions\\listusersessionshandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\session\\application\\usecase\\query\\session\\listusersessions\\testinvokereturnsallsessions',
        1 => 'tests\\unit\\session\\application\\usecase\\query\\session\\listusersessions\\testinvokereturnsactivesessions',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Session/Domain/Event/SessionCreatedEventTest.php' => 
    array (
      0 => 'e3bef6532fbfbbe74b1166ff32d3957784553661',
      1 => 
      array (
        0 => 'tests\\unit\\session\\domain\\event\\sessioncreatedeventtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\session\\domain\\event\\testpayload',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Session/Domain/Event/SessionRevokedEventTest.php' => 
    array (
      0 => '5962ed68bb28dd32d3c1c8793b183146d6344928',
      1 => 
      array (
        0 => 'tests\\unit\\session\\domain\\event\\sessionrevokedeventtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\session\\domain\\event\\testpayload',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Session/Domain/Exception/SessionAlreadyRevokedExceptionTest.php' => 
    array (
      0 => '1045d6cf38c601f02f5e45dd1496873425534ed8',
      1 => 
      array (
        0 => 'tests\\unit\\session\\domain\\exception\\sessionalreadyrevokedexceptiontest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\session\\domain\\exception\\testwithidcreatesmessage',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Session/Domain/Exception/SessionNotFoundExceptionTest.php' => 
    array (
      0 => '73886b4a7dc87eb6dc9f82d4e8c56fbf5c7dc46b',
      1 => 
      array (
        0 => 'tests\\unit\\session\\domain\\exception\\sessionnotfoundexceptiontest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\session\\domain\\exception\\testwithidcreatesmessage',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Session/Domain/Model/SessionTest.php' => 
    array (
      0 => '9f502ee9caf129b1abe7143ea41530331d7e73f1',
      1 => 
      array (
        0 => 'tests\\unit\\session\\domain\\model\\sessiontest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\session\\domain\\model\\testcancreatesession',
        1 => 'tests\\unit\\session\\domain\\model\\testcanupdatetokens',
        2 => 'tests\\unit\\session\\domain\\model\\testcantouchsession',
        3 => 'tests\\unit\\session\\domain\\model\\testcanrevokesession',
        4 => 'tests\\unit\\session\\domain\\model\\testrevokingalreadyrevokedsessionisnoop',
        5 => 'tests\\unit\\session\\domain\\model\\testmetadataisinitialized',
        6 => 'tests\\unit\\session\\domain\\model\\createsession',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Session/Domain/ValueObject/SessionIdTest.php' => 
    array (
      0 => '4e8f5e6294435cf3eb1207b66f121452344875fc',
      1 => 
      array (
        0 => 'tests\\unit\\session\\domain\\valueobject\\sessionidtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\session\\domain\\valueobject\\testcreatewithvaliduuid',
        1 => 'tests\\unit\\session\\domain\\valueobject\\testcreatewithinvaliduuidthrowsexception',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Session/Domain/ValueObject/SessionMetadataTest.php' => 
    array (
      0 => 'da623ec36b0bb74e28427cda0248d37eedcedd86',
      1 => 
      array (
        0 => 'tests\\unit\\session\\domain\\valueobject\\sessionmetadatatest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\session\\domain\\valueobject\\testdefaultvalues',
        1 => 'tests\\unit\\session\\domain\\valueobject\\testcustomvalues',
        2 => 'tests\\unit\\session\\domain\\valueobject\\testtoarray',
        3 => 'tests\\unit\\session\\domain\\valueobject\\testfromarray',
        4 => 'tests\\unit\\session\\domain\\valueobject\\testfromarraywithemptydata',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Session/Infrastructure/Persistence/Doctrine/Mapper/SessionMapperTest.php' => 
    array (
      0 => '067ea973de5b537c66a3b1f879ef3e7bd243d40d',
      1 => 
      array (
        0 => 'tests\\unit\\session\\infrastructure\\persistence\\doctrine\\mapper\\sessionmappertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\session\\infrastructure\\persistence\\doctrine\\mapper\\testtorecordmapssession',
        1 => 'tests\\unit\\session\\infrastructure\\persistence\\doctrine\\mapper\\testtodomainmapsrecord',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Session/Infrastructure/Persistence/Doctrine/Repository/SessionRepositoryTest.php' => 
    array (
      0 => 'aa83d461b5f595900e7842b17cdd1e0de09dfd6d',
      1 => 
      array (
        0 => 'tests\\unit\\session\\infrastructure\\persistence\\doctrine\\repository\\sessionrepositorytest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\session\\infrastructure\\persistence\\doctrine\\repository\\testfindbyrefreshtokenidreturnsnullwhenmissing',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Session/Presentation/Api/Processor/Session/RevokeAllSessionsProcessorTest.php' => 
    array (
      0 => 'a45a534b9daab7b7d53c84e6da0d7926a5771375',
      1 => 
      array (
        0 => 'tests\\unit\\session\\presentation\\api\\processor\\session\\revokeallsessionsprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\session\\presentation\\api\\processor\\session\\testprocessthrowswhenunauthenticated',
        1 => 'tests\\unit\\session\\presentation\\api\\processor\\session\\testprocessdispatchescommand',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Session/Presentation/Api/Processor/Session/RevokeSessionProcessorTest.php' => 
    array (
      0 => 'ec2678c8eb3d70163719f6fc1f3d0a76ba7eba20',
      1 => 
      array (
        0 => 'tests\\unit\\session\\presentation\\api\\processor\\session\\revokesessionprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\session\\presentation\\api\\processor\\session\\testprocessrevokessession',
        1 => 'tests\\unit\\session\\presentation\\api\\processor\\session\\testprocessthrowsnotfoundwhensessionmissing',
        2 => 'tests\\unit\\session\\presentation\\api\\processor\\session\\testprocessthrowsnotfoundwhennestedsessionmissing',
        3 => 'tests\\unit\\session\\presentation\\api\\processor\\session\\testprocessthrowsnotfoundwhenidmissing',
        4 => 'tests\\unit\\session\\presentation\\api\\processor\\session\\testprocessthrowswhenusermissing',
        5 => 'tests\\unit\\session\\presentation\\api\\processor\\session\\testprocessrethrowsunexpectedexception',
        6 => 'tests\\unit\\session\\presentation\\api\\processor\\session\\createsecuritymock',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Session/Presentation/Api/Provider/Session/GetSessionProviderTest.php' => 
    array (
      0 => 'ad607a1248a009575ae89e47bfc80394ddc37d94',
      1 => 
      array (
        0 => 'tests\\unit\\session\\presentation\\api\\provider\\session\\getsessionprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\session\\presentation\\api\\provider\\session\\testprovidethrowswhenunauthenticated',
        1 => 'tests\\unit\\session\\presentation\\api\\provider\\session\\testprovidethrowswhenidmissing',
        2 => 'tests\\unit\\session\\presentation\\api\\provider\\session\\testprovidemapsresult',
        3 => 'tests\\unit\\session\\presentation\\api\\provider\\session\\testprovidemapsnotfoundexception',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Session/Presentation/Api/Provider/Session/ListUserSessionsProviderTest.php' => 
    array (
      0 => '147a96da50c8a2eb350ede66b5dc9722ca185f10',
      1 => 
      array (
        0 => 'tests\\unit\\session\\presentation\\api\\provider\\session\\listusersessionsprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\session\\presentation\\api\\provider\\session\\testprovidereturnsemptyarraywhennotauthenticated',
        1 => 'tests\\unit\\session\\presentation\\api\\provider\\session\\testprovidereturnssessionsforauthenticateduser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Session/Presentation/Api/Resource/SessionResourceTest.php' => 
    array (
      0 => 'b6833ab8206c3e56aba3d6750e6a01b3c26f44dd',
      1 => 
      array (
        0 => 'tests\\unit\\session\\presentation\\api\\resource\\sessionresourcetest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\session\\presentation\\api\\resource\\testresourcecanbeinstantiated',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Application/Event/ApplicationEventTest.php' => 
    array (
      0 => 'f7b2740db785a9a2729fad65c8c6941fcec29f11',
      1 => 
      array (
        0 => 'tests\\shared\\application\\event\\applicationeventtest',
        1 => 'tests\\shared\\application\\event\\dummydomainevent',
      ),
      2 => 
      array (
        0 => 'tests\\shared\\application\\event\\testfromdomaincopiesallmetadata',
        1 => 'tests\\shared\\application\\event\\__construct',
        2 => 'tests\\shared\\application\\event\\eventid',
        3 => 'tests\\shared\\application\\event\\occurredat',
        4 => 'tests\\shared\\application\\event\\aggregateid',
        5 => 'tests\\shared\\application\\event\\aggregatetype',
        6 => 'tests\\shared\\application\\event\\payload',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Application/Exception/ApplicationExceptionTest.php' => 
    array (
      0 => 'c5530253675bd0d8d6298bd515f8deaa45346401',
      1 => 
      array (
        0 => 'tests\\shared\\application\\exception\\applicationexceptiontest',
      ),
      2 => 
      array (
        0 => 'tests\\shared\\application\\exception\\testcontextreturnsemptyarray',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Application/Factory/UuidFactoryTest.php' => 
    array (
      0 => 'f36caa3446d932c2423f8b679d4997e8d4b0118e',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\application\\factory\\uuidfactorytest',
        1 => 'tests\\unit\\shared\\application\\factory\\fakeuuidgenerator',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\application\\factory\\testcreatebuildsuuidvalueobject',
        1 => 'tests\\unit\\shared\\application\\factory\\testgeneraterawreturnsgeneratorvalue',
        2 => 'tests\\unit\\shared\\application\\factory\\createfactory',
        3 => 'tests\\unit\\shared\\application\\factory\\__construct',
        4 => 'tests\\unit\\shared\\application\\factory\\generate',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Application/UseCase/Query/Health/HealthCheckHandlerTest.php' => 
    array (
      0 => '7386b6029b05d806dd16976e6fc9107e86f39bed',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\application\\usecase\\query\\health\\healthcheckhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\application\\usecase\\query\\health\\testhealthywhenalldependenciesareup',
        1 => 'tests\\unit\\shared\\application\\usecase\\query\\health\\testdegradedwhencacheisdown',
        2 => 'tests\\unit\\shared\\application\\usecase\\query\\health\\testunhealthywhendatabaseisdown',
        3 => 'tests\\unit\\shared\\application\\usecase\\query\\health\\testunhealthywhenbotharedown',
        4 => 'tests\\unit\\shared\\application\\usecase\\query\\health\\testqueryincludedetailsproperty',
        5 => 'tests\\unit\\shared\\application\\usecase\\query\\health\\testquerydefaultincludedetailsisfalse',
        6 => 'tests\\unit\\shared\\application\\usecase\\query\\health\\testresultfactorymethods',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Domain/Event/AuditEventTest.php' => 
    array (
      0 => 'b8ef01adf255753122098f4df790304f81ca1b22',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\domain\\event\\auditeventtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\domain\\event\\testloginsuccessfactorybuildsevent',
        1 => 'tests\\unit\\shared\\domain\\event\\testloginfailedfactorybuildspayload',
        2 => 'tests\\unit\\shared\\domain\\event\\testtokenissuedfactorybuildsevent',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Domain/Exception/BusinessRuleViolationExceptionTest.php' => 
    array (
      0 => '58b577a776c0ec7d07ea0370a4f6b3b93cb79149',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\domain\\exception\\businessruleviolationexceptiontest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\domain\\exception\\testbecause',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Domain/Exception/DomainExceptionTest.php' => 
    array (
      0 => '9677e42796fb34c46390232aa0b41649c16e1527',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\domain\\exception\\domainexceptiontest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\domain\\exception\\testcodereturnsexpectedformat',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Domain/Exception/EntityNotFoundExceptionTest.php' => 
    array (
      0 => '290f62ef0dccc0d10b110c2753ef33698027418a',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\domain\\exception\\entitynotfoundexceptiontest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\domain\\exception\\testforid',
        1 => 'tests\\unit\\shared\\domain\\exception\\testforcriteria',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Domain/Exception/InvalidValueExceptionTest.php' => 
    array (
      0 => 'ad4df2a2ccef6cc380841c892373da6795fd1e13',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\domain\\exception\\invalidvalueexceptiontest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\domain\\exception\\testbecause',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Domain/Trait/RecordsDomainEventsTest.php' => 
    array (
      0 => '0d269041faec2fdf24df6392cc8b952406d01e7e',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\domain\\trait\\recordsdomaineventstest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\domain\\trait\\testrecordreleaseandclearevents',
        1 => 'tests\\unit\\shared\\domain\\trait\\record',
        2 => 'tests\\unit\\shared\\domain\\trait\\testaggregatehasandclearsrecordedevents',
        3 => 'tests\\unit\\shared\\domain\\trait\\createevent',
        4 => 'tests\\unit\\shared\\domain\\trait\\__construct',
        5 => 'tests\\unit\\shared\\domain\\trait\\eventid',
        6 => 'tests\\unit\\shared\\domain\\trait\\occurredat',
        7 => 'tests\\unit\\shared\\domain\\trait\\aggregateid',
        8 => 'tests\\unit\\shared\\domain\\trait\\aggregatetype',
        9 => 'tests\\unit\\shared\\domain\\trait\\payload',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Domain/ValueObject/DateRangeTest.php' => 
    array (
      0 => '8f897e2458449cec714cda4c7296e69f9bf41fcf',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\domain\\valueobject\\daterangetest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\domain\\valueobject\\testcanbecreatedwithvaliddates',
        1 => 'tests\\unit\\shared\\domain\\valueobject\\testcannotbecreatedwithstartafterend',
        2 => 'tests\\unit\\shared\\domain\\valueobject\\testcontains',
        3 => 'tests\\unit\\shared\\domain\\valueobject\\testoverlapsandduration',
        4 => 'tests\\unit\\shared\\domain\\valueobject\\testisactive',
        5 => 'tests\\unit\\shared\\domain\\valueobject\\testequality',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Domain/ValueObject/EmailTest.php' => 
    array (
      0 => '63c620c1bad77ba4c55745bee724a74c473dca4c',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\domain\\valueobject\\emailtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\domain\\valueobject\\testcanbecreatedwithvalidvalue',
        1 => 'tests\\unit\\shared\\domain\\valueobject\\testcannotbecreatedwithinvalidvalue',
        2 => 'tests\\unit\\shared\\domain\\valueobject\\testequality',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Domain/ValueObject/HashedSecretTest.php' => 
    array (
      0 => '1ecdda988699a4d27d9266a4bd609bda5e3c9f03',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\domain\\valueobject\\hashedsecrettest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\domain\\valueobject\\testcanbecreatedwithvalidvalue',
        1 => 'tests\\unit\\shared\\domain\\valueobject\\testequality',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Domain/ValueObject/IpAddressTest.php' => 
    array (
      0 => 'a3c00902bb0ac813d035d2e0953d652bb933f979',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\domain\\valueobject\\ipaddresstest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\domain\\valueobject\\testipv4loopbackandreserved',
        1 => 'tests\\unit\\shared\\domain\\valueobject\\testprivateipv4',
        2 => 'tests\\unit\\shared\\domain\\valueobject\\testipv6',
        3 => 'tests\\unit\\shared\\domain\\valueobject\\testequals',
        4 => 'tests\\unit\\shared\\domain\\valueobject\\testinvalidipthrows',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Domain/ValueObject/IpAddressTypeTest.php' => 
    array (
      0 => 'e4bd76e45f6bec47e9292020ba281143bc8128df',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\domain\\valueobject\\ipaddresstypetest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\domain\\valueobject\\testlabels',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Domain/ValueObject/LocaleTest.php' => 
    array (
      0 => '8a273792709b7d3fb7908a49e68bfe42b766a007',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\domain\\valueobject\\localetest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\domain\\valueobject\\testcanbecreatedwithvalidvalue',
        1 => 'tests\\unit\\shared\\domain\\valueobject\\testcannotbecreatedwithinvalidvalue',
        2 => 'tests\\unit\\shared\\domain\\valueobject\\testequality',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Domain/ValueObject/MaskedDestinationTest.php' => 
    array (
      0 => 'c81be6efde95b472cca2261e1e6928d054611c5f',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\domain\\valueobject\\maskeddestinationtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\domain\\valueobject\\testmaskemail',
        1 => 'tests\\unit\\shared\\domain\\valueobject\\emailprovider',
        2 => 'tests\\unit\\shared\\domain\\valueobject\\testmaskphone',
        3 => 'tests\\unit\\shared\\domain\\valueobject\\phoneprovider',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Domain/ValueObject/PhoneNumberTest.php' => 
    array (
      0 => 'ab134517e20eeddbbb0d4aafe118f5e16b2d0397',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\domain\\valueobject\\phonenumbertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\domain\\valueobject\\testcanbecreatedwithvalidvalue',
        1 => 'tests\\unit\\shared\\domain\\valueobject\\testcannotbecreatedwithinvalidvalue',
        2 => 'tests\\unit\\shared\\domain\\valueobject\\testgetcountrycode',
        3 => 'tests\\unit\\shared\\domain\\valueobject\\testgetnationalnumber',
        4 => 'tests\\unit\\shared\\domain\\valueobject\\testequality',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Domain/ValueObject/RateLimitResultTest.php' => 
    array (
      0 => '2e39d766cc550cfb22f082689a8de527f3b2a39c',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\domain\\valueobject\\ratelimitresulttest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\domain\\valueobject\\testcanbecreatedwithacceptedstatus',
        1 => 'tests\\unit\\shared\\domain\\valueobject\\testcanbecreatedwithrejectedstatus',
        2 => 'tests\\unit\\shared\\domain\\valueobject\\testacceptedfactorymethod',
        3 => 'tests\\unit\\shared\\domain\\valueobject\\testacceptedfactorymethodwithdefaulttokens',
        4 => 'tests\\unit\\shared\\domain\\valueobject\\testrejectedfactorymethod',
        5 => 'tests\\unit\\shared\\domain\\valueobject\\testdefaultvalues',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Domain/ValueObject/TenantIdTest.php' => 
    array (
      0 => 'a01a2b68b4dabea60499f5ead681e98b07170642',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\domain\\valueobject\\tenantidtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\domain\\valueobject\\testcanbecreatedwithvalidvalue',
        1 => 'tests\\unit\\shared\\domain\\valueobject\\testfromstring',
        2 => 'tests\\unit\\shared\\domain\\valueobject\\testcannotbecreatedwithemptyvalue',
        3 => 'tests\\unit\\shared\\domain\\valueobject\\testequality',
        4 => 'tests\\unit\\shared\\domain\\valueobject\\testtouuidreturnsuuid',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Domain/ValueObject/UserAgentTest.php' => 
    array (
      0 => '53f39b70d7047e5095f494dbd6f3155c65317d55',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\domain\\valueobject\\useragenttest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\domain\\valueobject\\testdetectsmobileandbrowserandos',
        1 => 'tests\\unit\\shared\\domain\\valueobject\\testdetectsbot',
        2 => 'tests\\unit\\shared\\domain\\valueobject\\testequals',
        3 => 'tests\\unit\\shared\\domain\\valueobject\\testemptyuseragentthrows',
        4 => 'tests\\unit\\shared\\domain\\valueobject\\testtoolonguseragentthrows',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Domain/ValueObject/UuidTest.php' => 
    array (
      0 => 'a318f9b525a572f052c131420f258f99140594a0',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\domain\\valueobject\\uuidtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\domain\\valueobject\\testcanbecreatedwithvalidvalue',
        1 => 'tests\\unit\\shared\\domain\\valueobject\\testcannotbecreatedwithinvalidvalue',
        2 => 'tests\\unit\\shared\\domain\\valueobject\\testequality',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Infrastructure/Adapter/Doctrine/HealthCheckAdapterTest.php' => 
    array (
      0 => 'bf07bb0142b834a0c85b67ce57ea8bc286f829d8',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\adapter\\doctrine\\healthcheckadaptertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\adapter\\doctrine\\testcheckdatabasereturnstrue',
        1 => 'tests\\unit\\shared\\infrastructure\\adapter\\doctrine\\testcheckdatabasereturnsfalseonexception',
        2 => 'tests\\unit\\shared\\infrastructure\\adapter\\doctrine\\testcheckdatabasereturnsfalsewhenmainconnectionfails',
        3 => 'tests\\unit\\shared\\infrastructure\\adapter\\doctrine\\testcheckcachereturnstrue',
        4 => 'tests\\unit\\shared\\infrastructure\\adapter\\doctrine\\testcheckcachereturnsfalseonexception',
        5 => 'tests\\unit\\shared\\infrastructure\\adapter\\doctrine\\testcheckcachereturnsfalsewhenitemnothit',
        6 => 'tests\\unit\\shared\\infrastructure\\adapter\\doctrine\\testcheckcachereturnsfalsewhenvaluemismatch',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Infrastructure/Console/LoadSeedFixturesCommandTest.php' => 
    array (
      0 => '1f2f1f6b4aa4f6c4eab844eee729ea2ae9f4bade',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\console\\loadseedfixturescommandtest',
        1 => 'tests\\unit\\shared\\infrastructure\\console\\authfixturestub',
        2 => 'tests\\unit\\shared\\infrastructure\\console\\userfixturestub',
        3 => 'tests\\unit\\shared\\infrastructure\\console\\mainfixturestub',
        4 => 'tests\\unit\\shared\\infrastructure\\console\\secondarymainfixturestub',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\console\\testexecuteloadsauthandmainfixtures',
        1 => 'tests\\unit\\shared\\infrastructure\\console\\testexecuteusespurgeandreloadmode',
        2 => 'tests\\unit\\shared\\infrastructure\\console\\testexecutefailswhenfixtureexecutionthrows',
        3 => 'tests\\unit\\shared\\infrastructure\\console\\testexecutefailsclosedoutsidedevandtest',
        4 => 'tests\\unit\\shared\\infrastructure\\console\\createentitymanagerwithconnection',
        5 => 'tests\\unit\\shared\\infrastructure\\console\\load',
        6 => 'tests\\unit\\shared\\infrastructure\\console\\load',
        7 => 'tests\\unit\\shared\\infrastructure\\console\\load',
        8 => 'tests\\unit\\shared\\infrastructure\\console\\load',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Infrastructure/EventDispatcher/SymfonyEventDispatcherAdapterTest.php' => 
    array (
      0 => '3baee49be472a94a37d6d807aa6b82c071669b04',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\eventdispatcher\\symfonyeventdispatcheradaptertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\eventdispatcher\\testdispatchuseseventname',
        1 => 'tests\\unit\\shared\\infrastructure\\eventdispatcher\\testdispatchalldispatcheseachevent',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Infrastructure/EventListener/AuditEventListenerTest.php' => 
    array (
      0 => '0d2a78078ed4c7845515a42598b8272120765484',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\eventlistener\\auditeventlistenertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\eventlistener\\testgetsubscribedevents',
        1 => 'tests\\unit\\shared\\infrastructure\\eventlistener\\testonmessagehandledlogsdomainevent',
        2 => 'tests\\unit\\shared\\infrastructure\\eventlistener\\testonmessagehandledignoresnondomainevent',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Infrastructure/EventSubscriber/SecurityHeadersSubscriberTest.php' => 
    array (
      0 => 'c6644039819939c0218c6dcae01180f90a679856',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\eventsubscriber\\securityheaderssubscribertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\eventsubscriber\\testgetsubscribedevents',
        1 => 'tests\\unit\\shared\\infrastructure\\eventsubscriber\\testsecurityheadersareaddedindevenvironment',
        2 => 'tests\\unit\\shared\\infrastructure\\eventsubscriber\\testhstsisaddedinproductionenvironment',
        3 => 'tests\\unit\\shared\\infrastructure\\eventsubscriber\\testcustomcspisapplied',
        4 => 'tests\\unit\\shared\\infrastructure\\eventsubscriber\\testheadersnotaddedwhendisabled',
        5 => 'tests\\unit\\shared\\infrastructure\\eventsubscriber\\testcachecontrolforauthenticatedrequests',
        6 => 'tests\\unit\\shared\\infrastructure\\eventsubscriber\\testnocachecontrolforunauthenticatedrequests',
        7 => 'tests\\unit\\shared\\infrastructure\\eventsubscriber\\testcustomhstsmaxage',
        8 => 'tests\\unit\\shared\\infrastructure\\eventsubscriber\\testhstsonlyinproduction',
        9 => 'tests\\unit\\shared\\infrastructure\\eventsubscriber\\environmentprovider',
        10 => 'tests\\unit\\shared\\infrastructure\\eventsubscriber\\testpermissionspolicycontainsallrestrictedfeatures',
        11 => 'tests\\unit\\shared\\infrastructure\\eventsubscriber\\createresponseevent',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Infrastructure/Exception/CacheOperationExceptionTest.php' => 
    array (
      0 => '747be91a33c00bd7354023348a2093e38b71ef7c',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\exception\\cacheoperationexceptiontest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\exception\\testreadfailed',
        1 => 'tests\\unit\\shared\\infrastructure\\exception\\testwritefailed',
        2 => 'tests\\unit\\shared\\infrastructure\\exception\\testdeletefailed',
        3 => 'tests\\unit\\shared\\infrastructure\\exception\\testclearfailed',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Infrastructure/Exception/FileStorageExceptionTest.php' => 
    array (
      0 => 'c32206073bd0b123f90bdafdd9c4d27778fa454f',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\exception\\filestorageexceptiontest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\exception\\testreadfailed',
        1 => 'tests\\unit\\shared\\infrastructure\\exception\\testwritefailed',
        2 => 'tests\\unit\\shared\\infrastructure\\exception\\testdeletefailed',
        3 => 'tests\\unit\\shared\\infrastructure\\exception\\testdirectorycreationfailed',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Infrastructure/Exception/InfrastructureExceptionTest.php' => 
    array (
      0 => 'd000556d86f35232c0db64fb177fc98244fac210',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\exception\\infrastructureexceptiontest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\exception\\testmetadatareturnsemptyarraybydefault',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Infrastructure/Exception/MailSendingExceptionTest.php' => 
    array (
      0 => 'b2e27c3524b7a79589ed36dd8a85e7745606b05c',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\exception\\mailsendingexceptiontest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\exception\\testdispatchfailed',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Infrastructure/Exception/MessengerRuntimeExceptionTest.php' => 
    array (
      0 => '54e89cb32c255d721beb1f169770937b343ec539',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\exception\\messengerruntimeexceptiontest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\exception\\testwrap',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Infrastructure/Exception/NoHandlerResultExceptionTest.php' => 
    array (
      0 => 'cea2597f1a759f52e46c71cde4fb33606d902871',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\exception\\nohandlerresultexceptiontest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\exception\\testformessage',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Infrastructure/Exception/TransactionExecutionExceptionTest.php' => 
    array (
      0 => 'dfc58fcb6d026d34e1eb8b6b45b8f0ee1e1d2ada',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\exception\\transactionexecutionexceptiontest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\exception\\testwrap',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Infrastructure/Exception/TranslationExceptionTest.php' => 
    array (
      0 => 'f894bfd1338653d3ca4438b989a01fa604cd56f9',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\exception\\translationexceptiontest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\exception\\testtranslatefailed',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Infrastructure/Exception/UuidGenerationExceptionTest.php' => 
    array (
      0 => 'fea31e1ad0117746eda61720b5e2475f88a11597',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\exception\\uuidgenerationexceptiontest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\exception\\testduetorandomfailure',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Infrastructure/Serializer/DomainExceptionNormalizerTest.php' => 
    array (
      0 => 'eabaa151888592c0d143e19e4ab87d5144a5f508',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\serializer\\domainexceptionnormalizertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\serializer\\testnormalizedomainexception',
        1 => 'tests\\unit\\shared\\infrastructure\\serializer\\testnormalizeentitynotfound',
        2 => 'tests\\unit\\shared\\infrastructure\\serializer\\testsupportsnormalization',
        3 => 'tests\\unit\\shared\\infrastructure\\serializer\\expectedtype',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Infrastructure/Service/UuidEventIdProviderTest.php' => 
    array (
      0 => '38fb209651d89744350ec8e1da6b02fb50b49ad8',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\service\\uuideventidprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\service\\testnexteventidreturnsuuidvalueobject',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Infrastructure/Symfony/Adapter/Inbound/MessengerCommandBusAdapterTest.php' => 
    array (
      0 => '00be1b89c55e9465b90a4dec986ad45e96a5ece0',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\inbound\\messengercommandbusadaptertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\inbound\\setup',
        1 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\inbound\\testdispatchsuccess',
        2 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\inbound\\testdispatchthrowsmessengerruntimeexception',
        3 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\inbound\\testdispatchthrowsnohandlerresultexceptionwhennostamp',
        4 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\inbound\\testdispatchreturnsvoidresultwhenhandlerreturnsnull',
        5 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\inbound\\testdispatchthrowsnohandlerresultexceptionwhenresultnotresultmessage',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Infrastructure/Symfony/Adapter/Inbound/MessengerEventListenerAdapterTest.php' => 
    array (
      0 => '4ea08c4c7cd42cee0895617a4c2f33a88978395e',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\inbound\\messengereventlisteneradaptertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\inbound\\setup',
        1 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\inbound\\testhandlesuccess',
        2 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\inbound\\testhandlereturnsnullwhennohandledstamp',
        3 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\inbound\\testhandlereturnsnullwhenresultisnull',
        4 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\inbound\\testhandlethrowsmessengerruntimeexception',
        5 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\inbound\\testhandlethrowsnohandlerresultexceptionwhenresultinvalid',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Infrastructure/Symfony/Adapter/Inbound/MessengerQueryBusAdapterTest.php' => 
    array (
      0 => 'dc08f37f7aa37f64f1d3418b9a46046ac875d1cf',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\inbound\\messengerquerybusadaptertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\inbound\\setup',
        1 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\inbound\\testasksuccess',
        2 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\inbound\\testaskthrowsmessengerruntimeexception',
        3 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\inbound\\testaskthrowsnohandlerresultexceptionwhennostamp',
        4 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\inbound\\testaskthrowsnohandlerresultexceptionwhenresultnotresultmessage',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Infrastructure/Symfony/Adapter/Outbound/CacheAdapterTest.php' => 
    array (
      0 => 'fe43ce44682f478fffbdd2dabb40ba42c377dc67',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\cacheadaptertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\setup',
        1 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testgethit',
        2 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testgetmiss',
        3 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testgetthrowsexception',
        4 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testsetsuccess',
        5 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testsetthrowsexceptionongetitem',
        6 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testsetthrowsexceptiononsave',
        7 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testsetthrowsexceptionwhensavereturnsfalse',
        8 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testsetwithoutttlskipsexpiration',
        9 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testdeletesuccess',
        10 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testdeletethrowsexception',
        11 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testdeletethrowswhendeletereturnsfalse',
        12 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testclearsuccess',
        13 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testclearthrowswhenclearreturnsfalse',
        14 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testclearthrowsoncacheexception',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Infrastructure/Symfony/Adapter/Outbound/DoctrineTransactionManagerAdapterTest.php' => 
    array (
      0 => 'b8f62fbd994d911c4a0376748b587a221f894c25',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\doctrinetransactionmanageradaptertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\setup',
        1 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testtransactionalsuccess',
        2 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testtransactionalrethrowsnondbalexceptionunchanged',
        3 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testtransactionalwrapsdoctrinedbalexception',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Infrastructure/Symfony/Adapter/Outbound/FileStorageAdapterTest.php' => 
    array (
      0 => '70d37b2d365e94d95c9f006f57d6a0ebe8740c18',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\filestorageadaptertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\setup',
        1 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\teardown',
        2 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testwriteandread',
        3 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testwritecreatesdirectories',
        4 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testreadthrowsexceptioniffilenotfound',
        5 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testreadthrowswhenreaderfails',
        6 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testdelete',
        7 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testdeletenonexistentfiledoesnotthrow',
        8 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testwritethrowswhendirectorycreationfails',
        9 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testwritethrowswhenfileputfails',
        10 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testdeletethrowswhenunlinkfails',
        11 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\recursiveremove',
        12 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\suppresswarnings',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Infrastructure/Symfony/Adapter/Outbound/HashingAdapterTest.php' => 
    array (
      0 => '0ca1629b95c6b39b608d44869d5da4b800a63353',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\hashingadaptertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\setup',
        1 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testhashreturnshashedsecret',
        2 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testverifyreturnstrueformatchingpassword',
        3 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testverifyreturnsfalsefornonmatchingpassword',
        4 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testhashandverifyworktogether',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Infrastructure/Symfony/Adapter/Outbound/LoggerAdapterTest.php' => 
    array (
      0 => 'df6033c1b5093bf7ec56619cd54909e2b9e9156b',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\loggeradaptertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\setup',
        1 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testlog',
        2 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testcritical',
        3 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testerror',
        4 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testwarning',
        5 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testnotice',
        6 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testinfo',
        7 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testdebug',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Infrastructure/Symfony/Adapter/Outbound/MailerAdapterTest.php' => 
    array (
      0 => '181d2a16e85a1cbc864ce3f31fb1c68e639fc104',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\maileradaptertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\setup',
        1 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testsendsuccess',
        2 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testsendwithccandbcc',
        3 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testsendthrowsexception',
        4 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testsendwithattachments',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Infrastructure/Symfony/Adapter/Outbound/MessengerEventBusAdapterTest.php' => 
    array (
      0 => '2fa95625ff951cc1cb50f1dce68da119a0284d2f',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\messengereventbusadaptertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\setup',
        1 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testpublishsuccess',
        2 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testpublishthrowsexception',
        3 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testpublishignoresmissinghandlers',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Infrastructure/Symfony/Adapter/Outbound/SystemClockAdapterTest.php' => 
    array (
      0 => '95b9dc46d03ab4324677d882c333ca7c71988114',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\systemclockadaptertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testnow',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Infrastructure/Symfony/Adapter/Outbound/TranslatorAdapterTest.php' => 
    array (
      0 => 'defbc9e4bd39038bf800b92bc8f195e8aae5c41a',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\translatoradaptertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\setup',
        1 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testtranslatesuccess',
        2 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testtranslatethrowsexception',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Infrastructure/Symfony/Adapter/Outbound/UuidGeneratorAdapterTest.php' => 
    array (
      0 => '74f3378137ed08ab445e2ba65dffdbb7c8029869',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\uuidgeneratoradaptertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testgeneratedefault',
        1 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testgeneratewithcustomgenerator',
        2 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testgeneratethrowsexception',
        3 => 'tests\\unit\\shared\\infrastructure\\symfony\\adapter\\outbound\\testgeneratethrowswhengeneratorreturnsinvalidtype',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Presentation/Api/Http/ClientResourceAlreadyExistsHttpExceptionTest.php' => 
    array (
      0 => '76ec5a96d23d988dd6febab1e77cc48c0b4fa20f',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\presentation\\api\\http\\clientresourcealreadyexistshttpexceptiontest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\presentation\\api\\http\\testexposesastableproblemcontract',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Presentation/Api/Http/CreationPreconditionGuardTest.php' => 
    array (
      0 => 'e9fc533c2507944bc0a836c22607d4397f74d63a',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\presentation\\api\\http\\creationpreconditionguardtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\presentation\\api\\http\\testacceptscreateonlyprecondition',
        1 => 'tests\\unit\\shared\\presentation\\api\\http\\testrejectsmissingprecondition',
        2 => 'tests\\unit\\shared\\presentation\\api\\http\\testrejectsdifferentprecondition',
        3 => 'tests\\unit\\shared\\presentation\\api\\http\\guard',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Presentation/Api/Http/MergePatchFieldsTest.php' => 
    array (
      0 => 'b978b6e25b75a3a44fb13de70a4c8fa46091e270',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\presentation\\api\\http\\mergepatchfieldstest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\presentation\\api\\http\\itpreservesexplicitnullfields',
        1 => 'tests\\unit\\shared\\presentation\\api\\http\\itignoresnonpatchrequests',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Presentation/Api/Http/RevisionGuardTest.php' => 
    array (
      0 => '87e12eba1775ad017dc15531b599deeec6b663ab',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\presentation\\api\\http\\revisionguardtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\presentation\\api\\http\\itacceptsthepersistedrevision',
        1 => 'tests\\unit\\shared\\presentation\\api\\http\\itexposestheexpectedrevisionforapplicationcommands',
        2 => 'tests\\unit\\shared\\presentation\\api\\http\\itrejectsawildcardrevision',
        3 => 'tests\\unit\\shared\\presentation\\api\\http\\itrejectsastalerevision',
        4 => 'tests\\unit\\shared\\presentation\\api\\http\\itrequiresaconditionalmutation',
        5 => 'tests\\unit\\shared\\presentation\\api\\http\\guard',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Presentation/Api/Provider/HealthProviderTest.php' => 
    array (
      0 => '42b30df2feea54b0a360d05e7b9a9b596f191582',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\presentation\\api\\provider\\healthprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\presentation\\api\\provider\\testprovidemapshealthresult',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Presentation/Api/Search/CollectionSearcherTest.php' => 
    array (
      0 => 'd44e58f32b94644680fd14d59430ea4a34706fad',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\presentation\\api\\search\\collectionsearchertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\presentation\\api\\search\\testsearchreturnsallitemswhensearchisnull',
        1 => 'tests\\unit\\shared\\presentation\\api\\search\\testsearchreturnsemptyarrayforemptyinput',
        2 => 'tests\\unit\\shared\\presentation\\api\\search\\testsearchreturnsallitemswhenfieldsareempty',
        3 => 'tests\\unit\\shared\\presentation\\api\\search\\testsearchfiltersbyexactmatch',
        4 => 'tests\\unit\\shared\\presentation\\api\\search\\testsearchiscaseinsensitive',
        5 => 'tests\\unit\\shared\\presentation\\api\\search\\testsearchmatchespartialstrings',
        6 => 'tests\\unit\\shared\\presentation\\api\\search\\testsearchmatchesacrossmultiplefields',
        7 => 'tests\\unit\\shared\\presentation\\api\\search\\testsearchignoresnonstringfields',
        8 => 'tests\\unit\\shared\\presentation\\api\\search\\testsearchreturnsreindexedlist',
        9 => 'tests\\unit\\shared\\presentation\\api\\search\\testsearchreturnsnomatcheswhentermnotfound',
        10 => 'tests\\unit\\shared\\presentation\\api\\search\\testsearchignoresnullfieldvalues',
        11 => 'tests\\unit\\shared\\presentation\\api\\search\\testsearchhandlesundefinedproperties',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Presentation/Api/Search/SearchExtractorTest.php' => 
    array (
      0 => 'afc0c9aea194e137e5f1ca2861be1344d12afc0f',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\presentation\\api\\search\\searchextractortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\presentation\\api\\search\\testfromcontextreturnsnullwhennofilters',
        1 => 'tests\\unit\\shared\\presentation\\api\\search\\testfromcontextreturnsnullwhennosearchfilter',
        2 => 'tests\\unit\\shared\\presentation\\api\\search\\testfromcontextreturnsnullwhensearchisempty',
        3 => 'tests\\unit\\shared\\presentation\\api\\search\\testfromcontextreturnsnullwhensearchiswhitespace',
        4 => 'tests\\unit\\shared\\presentation\\api\\search\\testfromcontextreturnsnullwhensearchisnotstring',
        5 => 'tests\\unit\\shared\\presentation\\api\\search\\testfromcontextreturnsnullwhenfiltersisnotarray',
        6 => 'tests\\unit\\shared\\presentation\\api\\search\\testfromcontextreturnstrimmedsearchterm',
        7 => 'tests\\unit\\shared\\presentation\\api\\search\\testfromcontextreturnssearchtermasis',
        8 => 'tests\\unit\\shared\\presentation\\api\\search\\testfromcontextpreservessearchtermcase',
        9 => 'tests\\unit\\shared\\presentation\\api\\search\\testfromcontextreturnsnullwhensearchisnull',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Presentation/Api/Sorting/CollectionSorterTest.php' => 
    array (
      0 => '132c78d0c51bc6a0ec780f3b820c2492aa4e7409',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\presentation\\api\\sorting\\collectionsortertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\presentation\\api\\sorting\\testsortreturnsemptyarrayforemptyinput',
        1 => 'tests\\unit\\shared\\presentation\\api\\sorting\\testsortbystringfieldascending',
        2 => 'tests\\unit\\shared\\presentation\\api\\sorting\\testsortbystringfielddescending',
        3 => 'tests\\unit\\shared\\presentation\\api\\sorting\\testsortbyintegerfield',
        4 => 'tests\\unit\\shared\\presentation\\api\\sorting\\testsortbybooleanfield',
        5 => 'tests\\unit\\shared\\presentation\\api\\sorting\\testsortpreservesorderforequalvalues',
        6 => 'tests\\unit\\shared\\presentation\\api\\sorting\\testsortsingleitem',
        7 => 'tests\\unit\\shared\\presentation\\api\\sorting\\testsortbydatestringfield',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Shared/Presentation/Api/Sorting/SortingExtractorTest.php' => 
    array (
      0 => 'f5e21e88f1b894a94255d1bac86bd7a339d5ad56',
      1 => 
      array (
        0 => 'tests\\unit\\shared\\presentation\\api\\sorting\\sortingextractortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\shared\\presentation\\api\\sorting\\testfromcontextreturnsdefaultwhennofilters',
        1 => 'tests\\unit\\shared\\presentation\\api\\sorting\\testfromcontextreturnsdefaultwhennoorderfilter',
        2 => 'tests\\unit\\shared\\presentation\\api\\sorting\\testfromcontextextractsvalidascorder',
        3 => 'tests\\unit\\shared\\presentation\\api\\sorting\\testfromcontextextractsvaliddescorder',
        4 => 'tests\\unit\\shared\\presentation\\api\\sorting\\testfromcontextignoresdisallowedfield',
        5 => 'tests\\unit\\shared\\presentation\\api\\sorting\\testfromcontextusesfirstvalidfield',
        6 => 'tests\\unit\\shared\\presentation\\api\\sorting\\testfromcontexthandlescaseinsensitivedirection',
        7 => 'tests\\unit\\shared\\presentation\\api\\sorting\\testfromcontextfallsbacktodefaultdirectiononinvalidvalue',
        8 => 'tests\\unit\\shared\\presentation\\api\\sorting\\testfromcontextusescustomdefaultdirection',
        9 => 'tests\\unit\\shared\\presentation\\api\\sorting\\testfromcontexthandlesnonarrayfilters',
        10 => 'tests\\unit\\shared\\presentation\\api\\sorting\\testfromcontexthandlesnonarrayorder',
        11 => 'tests\\unit\\shared\\presentation\\api\\sorting\\testfromcontexthandlesnonstringdirectionvalue',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Smoke/UseCaseMessageConstructionTest.php' => 
    array (
      0 => 'c18140c0a087c6c988c2abccd4ead26a6b2860da',
      1 => 
      array (
        0 => 'tests\\unit\\smoke\\usecasemessageconstructiontest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\smoke\\testconstruction',
        1 => 'tests\\unit\\smoke\\messageclassprovider',
        2 => 'tests\\unit\\smoke\\buildargs',
        3 => 'tests\\unit\\smoke\\buildvalue',
        4 => 'tests\\unit\\smoke\\buildvaluefornamedtype',
        5 => 'tests\\unit\\smoke\\buildarrayvalue',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Tenant/Application/UseCase/Command/Tenant/ActivateTenant/ActivateTenantHandlerTest.php' => 
    array (
      0 => 'f8729deb7063b74168e1517a93d819ce9c8ccae5',
      1 => 
      array (
        0 => 'tests\\unit\\tenant\\application\\usecase\\command\\tenant\\activatetenant\\activatetenanthandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\tenant\\application\\usecase\\command\\tenant\\activatetenant\\testinvokeactivatestenantanddispatchesevent',
        1 => 'tests\\unit\\tenant\\application\\usecase\\command\\tenant\\activatetenant\\testinvokedoesnotdispatchwhenalreadyactive',
        2 => 'tests\\unit\\tenant\\application\\usecase\\command\\tenant\\activatetenant\\testinvokethrowswhentenantnotfound',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Tenant/Application/UseCase/Command/Tenant/CreateTenant/CreateTenantHandlerTest.php' => 
    array (
      0 => '922d52f9f86f2aaafc8b4cd4afb1471cf0ba4a55',
      1 => 
      array (
        0 => 'tests\\unit\\tenant\\application\\usecase\\command\\tenant\\createtenant\\createtenanthandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\tenant\\application\\usecase\\command\\tenant\\createtenant\\testinvokecreatesnewtenant',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Tenant/Application/UseCase/Command/Tenant/DeactivateTenant/DeactivateTenantHandlerTest.php' => 
    array (
      0 => 'f08f1b4bbdb38ad9d42e7a1beeb2a1fadea0babe',
      1 => 
      array (
        0 => 'tests\\unit\\tenant\\application\\usecase\\command\\tenant\\deactivatetenant\\deactivatetenanthandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\tenant\\application\\usecase\\command\\tenant\\deactivatetenant\\testinvokedeactivatestenantanddispatchesevent',
        1 => 'tests\\unit\\tenant\\application\\usecase\\command\\tenant\\deactivatetenant\\testinvokedoesnotdispatchwhenalreadyinactive',
        2 => 'tests\\unit\\tenant\\application\\usecase\\command\\tenant\\deactivatetenant\\testinvokethrowswhentenantnotfound',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Tenant/Application/UseCase/Command/Tenant/DeleteTenant/DeleteTenantHandlerTest.php' => 
    array (
      0 => '727501b6083e3d7f265b221761081d397df42354',
      1 => 
      array (
        0 => 'tests\\unit\\tenant\\application\\usecase\\command\\tenant\\deletetenant\\deletetenanthandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\tenant\\application\\usecase\\command\\tenant\\deletetenant\\testinvokedeletestenant',
        1 => 'tests\\unit\\tenant\\application\\usecase\\command\\tenant\\deletetenant\\testinvokethrowswhentenantnotfound',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Tenant/Application/UseCase/Command/Tenant/UpdateTenant/UpdateTenantHandlerTest.php' => 
    array (
      0 => 'fcf257835800aee3f0bf29dfa959b31b2a11641e',
      1 => 
      array (
        0 => 'tests\\unit\\tenant\\application\\usecase\\command\\tenant\\updatetenant\\updatetenanthandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\tenant\\application\\usecase\\command\\tenant\\updatetenant\\testinvokeupdatestenantanddispatchesevent',
        1 => 'tests\\unit\\tenant\\application\\usecase\\command\\tenant\\updatetenant\\testinvokedoesnotdispatchwhensettingsnull',
        2 => 'tests\\unit\\tenant\\application\\usecase\\command\\tenant\\updatetenant\\testinvokethrowswhentenantnotfound',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Tenant/Application/UseCase/Query/Tenant/GetTenant/GetTenantHandlerTest.php' => 
    array (
      0 => 'ecda0cdc5c33252e4049b4b713e34c55fccf3d0e',
      1 => 
      array (
        0 => 'tests\\unit\\tenant\\application\\usecase\\query\\tenant\\gettenant\\gettenanthandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\tenant\\application\\usecase\\query\\tenant\\gettenant\\testinvokereturnstenant',
        1 => 'tests\\unit\\tenant\\application\\usecase\\query\\tenant\\gettenant\\testinvokethrowsexceptionwhennotfound',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Tenant/Application/UseCase/Query/Tenant/ListTenants/ListTenantsHandlerTest.php' => 
    array (
      0 => '98555d99b94197704268996098400741d098203d',
      1 => 
      array (
        0 => 'tests\\unit\\tenant\\application\\usecase\\query\\tenant\\listtenants\\listtenantshandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\tenant\\application\\usecase\\query\\tenant\\listtenants\\testinvokereturnsmappedtenants',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Tenant/Domain/Event/TenantActivatedEventTest.php' => 
    array (
      0 => '2d138ba48802567c66c64526ff3984b41be84580',
      1 => 
      array (
        0 => 'tests\\unit\\tenant\\domain\\event\\tenantactivatedeventtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\tenant\\domain\\event\\testpayload',
        1 => 'tests\\unit\\tenant\\domain\\event\\testaccessorsreturnexpectedvalues',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Tenant/Domain/Event/TenantCreatedEventTest.php' => 
    array (
      0 => '778a0cb79cd6bd017d6bdaf71cc23852b950406c',
      1 => 
      array (
        0 => 'tests\\unit\\tenant\\domain\\event\\tenantcreatedeventtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\tenant\\domain\\event\\testpayload',
        1 => 'tests\\unit\\tenant\\domain\\event\\testaccessorsreturnexpectedvalues',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Tenant/Domain/Event/TenantDeactivatedEventTest.php' => 
    array (
      0 => '79a74ee0c1c30faaba7094bdd0830e8405166b52',
      1 => 
      array (
        0 => 'tests\\unit\\tenant\\domain\\event\\tenantdeactivatedeventtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\tenant\\domain\\event\\testpayload',
        1 => 'tests\\unit\\tenant\\domain\\event\\testaccessorsreturnexpectedvalues',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Tenant/Domain/Event/TenantSettingsUpdatedEventTest.php' => 
    array (
      0 => '848afd7f8c739946f8bc1ae39e44c7c884f75b5c',
      1 => 
      array (
        0 => 'tests\\unit\\tenant\\domain\\event\\tenantsettingsupdatedeventtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\tenant\\domain\\event\\testpayload',
        1 => 'tests\\unit\\tenant\\domain\\event\\testaccessorsreturnexpectedvalues',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Tenant/Domain/Exception/TenantNotFoundExceptionTest.php' => 
    array (
      0 => 'e7200edaf987e0a9f6b1eb89ff1859693e04f579',
      1 => 
      array (
        0 => 'tests\\unit\\tenant\\domain\\exception\\tenantnotfoundexceptiontest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\tenant\\domain\\exception\\testwithidcreatesmessage',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Tenant/Domain/Model/TenantTest.php' => 
    array (
      0 => '885449e2bfbf1ea7b0ea6a0fc8684e584ff04e4d',
      1 => 
      array (
        0 => 'tests\\unit\\tenant\\domain\\model\\tenanttest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\tenant\\domain\\model\\testcancreatetenant',
        1 => 'tests\\unit\\tenant\\domain\\model\\testcanactivatetenant',
        2 => 'tests\\unit\\tenant\\domain\\model\\testcandeactivatetenant',
        3 => 'tests\\unit\\tenant\\domain\\model\\testcanrenametenant',
        4 => 'tests\\unit\\tenant\\domain\\model\\testreconstitutetenant',
        5 => 'tests\\unit\\tenant\\domain\\model\\testcanupdatesettings',
        6 => 'tests\\unit\\tenant\\domain\\model\\testactivatingalreadyactivetenantisnoop',
        7 => 'tests\\unit\\tenant\\domain\\model\\testdeactivatingalreadyinactivetenantisnoop',
        8 => 'tests\\unit\\tenant\\domain\\model\\createtenant',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Tenant/Domain/ValueObject/TenantIdTest.php' => 
    array (
      0 => '850cb6db7edaf6f43924b1edc275596ad6b1e352',
      1 => 
      array (
        0 => 'tests\\unit\\tenant\\domain\\valueobject\\tenantidtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\tenant\\domain\\valueobject\\testfromstringcreatesid',
        1 => 'tests\\unit\\tenant\\domain\\valueobject\\testinvaliduuidthrowsexception',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Tenant/Domain/ValueObject/TenantNameTest.php' => 
    array (
      0 => 'fccd3fc7be6de51489f79ae26e1003409e578bea',
      1 => 
      array (
        0 => 'tests\\unit\\tenant\\domain\\valueobject\\tenantnametest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\tenant\\domain\\valueobject\\testcanbecreatedwithvalidname',
        1 => 'tests\\unit\\tenant\\domain\\valueobject\\testcannotbecreatedwithtooshortname',
        2 => 'tests\\unit\\tenant\\domain\\valueobject\\testcannotbecreatedwithtoolongname',
        3 => 'tests\\unit\\tenant\\domain\\valueobject\\testminimumlengthnameisvalid',
        4 => 'tests\\unit\\tenant\\domain\\valueobject\\testmaximumlengthnameisvalid',
        5 => 'tests\\unit\\tenant\\domain\\valueobject\\testvalidnames',
        6 => 'tests\\unit\\tenant\\domain\\valueobject\\validnamesprovider',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Tenant/Domain/ValueObject/TenantSettingsTest.php' => 
    array (
      0 => '733535c5a4fe0a4135f1800fc1834e3802fa42dc',
      1 => 
      array (
        0 => 'tests\\unit\\tenant\\domain\\valueobject\\tenantsettingstest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\tenant\\domain\\valueobject\\testdefaultvalues',
        1 => 'tests\\unit\\tenant\\domain\\valueobject\\testcustomvalues',
        2 => 'tests\\unit\\tenant\\domain\\valueobject\\testwithaccesstokenttl',
        3 => 'tests\\unit\\tenant\\domain\\valueobject\\testwithrefreshtokenttl',
        4 => 'tests\\unit\\tenant\\domain\\valueobject\\testtoarray',
        5 => 'tests\\unit\\tenant\\domain\\valueobject\\testfromarray',
        6 => 'tests\\unit\\tenant\\domain\\valueobject\\testfromarraywithdefaults',
        7 => 'tests\\unit\\tenant\\domain\\valueobject\\testfromarraynormalizesscopesandissuer',
        8 => 'tests\\unit\\tenant\\domain\\valueobject\\testaccesstokenttloutofrangethrows',
        9 => 'tests\\unit\\tenant\\domain\\valueobject\\testrefreshtokenttloutofrangethrows',
        10 => 'tests\\unit\\tenant\\domain\\valueobject\\testpublicclientsrequirepkce',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Tenant/Infrastructure/EventSubscriber/TenantIsolationSubscriberTest.php' => 
    array (
      0 => 'ace4d88c8d36ab6ae3e15fbcf75eee0c23db5629',
      1 => 
      array (
        0 => 'tests\\unit\\tenant\\infrastructure\\eventsubscriber\\tenantisolationsubscribertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\tenant\\infrastructure\\eventsubscriber\\testdisablesfilterwhennotenantid',
        1 => 'tests\\unit\\tenant\\infrastructure\\eventsubscriber\\testenablesfilterwhentenantidprovided',
        2 => 'tests\\unit\\tenant\\infrastructure\\eventsubscriber\\testusesexistingfilterwhenalreadyenabled',
        3 => 'tests\\unit\\tenant\\infrastructure\\eventsubscriber\\testdisablesfilteronterminate',
        4 => 'tests\\unit\\tenant\\infrastructure\\eventsubscriber\\testresetdisablesfilter',
        5 => 'tests\\unit\\tenant\\infrastructure\\eventsubscriber\\createrequestevent',
        6 => 'tests\\unit\\tenant\\infrastructure\\eventsubscriber\\createterminateevent',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Tenant/Infrastructure/Persistence/Doctrine/Filter/TenantFilterTest.php' => 
    array (
      0 => '67d2653930a4cd910d9d1ca2637b4f45c027284c',
      1 => 
      array (
        0 => 'tests\\unit\\tenant\\infrastructure\\persistence\\doctrine\\filter\\tenantfiltertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\tenant\\infrastructure\\persistence\\doctrine\\filter\\testreturnsemptywhenentityhasnotenantid',
        1 => 'tests\\unit\\tenant\\infrastructure\\persistence\\doctrine\\filter\\testreturnsemptywhenparametermissing',
        2 => 'tests\\unit\\tenant\\infrastructure\\persistence\\doctrine\\filter\\testreturnsconstraintwhentenantidisset',
        3 => 'tests\\unit\\tenant\\infrastructure\\persistence\\doctrine\\filter\\testreturnsemptywhentenantidisemptystring',
        4 => 'tests\\unit\\tenant\\infrastructure\\persistence\\doctrine\\filter\\createfilter',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Tenant/Infrastructure/Persistence/Doctrine/Mapper/TenantMapperTest.php' => 
    array (
      0 => 'a96f22f917035c3341781f8b7601d1d94fcca595',
      1 => 
      array (
        0 => 'tests\\unit\\tenant\\infrastructure\\persistence\\doctrine\\mapper\\tenantmappertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\tenant\\infrastructure\\persistence\\doctrine\\mapper\\testtorecordmapsdomaintenant',
        1 => 'tests\\unit\\tenant\\infrastructure\\persistence\\doctrine\\mapper\\testtodomainmapsrecord',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Tenant/Infrastructure/Resolver/RequestTenantResolverTest.php' => 
    array (
      0 => '5bf8d6f09b6a2957c17bb952d8256f5c4ff68780',
      1 => 
      array (
        0 => 'tests\\unit\\tenant\\infrastructure\\resolver\\requesttenantresolvertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\tenant\\infrastructure\\resolver\\testresolvetenantidreturnsnullwhennorequest',
        1 => 'tests\\unit\\tenant\\infrastructure\\resolver\\testresolvetenantidfromheader',
        2 => 'tests\\unit\\tenant\\infrastructure\\resolver\\testresolvetenantidreturnsnullforinvaliduuid',
        3 => 'tests\\unit\\tenant\\infrastructure\\resolver\\testresolvetenantidreturnsnullforemptystring',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Tenant/Presentation/Api/Processor/Tenant/ActivateTenantProcessorTest.php' => 
    array (
      0 => '450f25664195deb49a5e3f0e02e258544499e69d',
      1 => 
      array (
        0 => 'tests\\unit\\tenant\\presentation\\api\\processor\\tenant\\activatetenantprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\tenant\\presentation\\api\\processor\\tenant\\testprocessthrowswhenidisnotstring',
        1 => 'tests\\unit\\tenant\\presentation\\api\\processor\\tenant\\testprocessdispatchesandreturnsoutput',
        2 => 'tests\\unit\\tenant\\presentation\\api\\processor\\tenant\\createtenantresult',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Tenant/Presentation/Api/Processor/Tenant/CreateTenantProcessorTest.php' => 
    array (
      0 => 'be14feb58996b855f1b8fafffff0a8ea02684f56',
      1 => 
      array (
        0 => 'tests\\unit\\tenant\\presentation\\api\\processor\\tenant\\createtenantprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\tenant\\presentation\\api\\processor\\tenant\\testprocesscreatestenantandreturnsoutput',
        1 => 'tests\\unit\\tenant\\presentation\\api\\processor\\tenant\\testprocessthrowswhenunauthenticated',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Tenant/Presentation/Api/Processor/Tenant/DeactivateTenantProcessorTest.php' => 
    array (
      0 => 'cc7f82d3e7d03279076b8ba4bd1b10510d017407',
      1 => 
      array (
        0 => 'tests\\unit\\tenant\\presentation\\api\\processor\\tenant\\deactivatetenantprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\tenant\\presentation\\api\\processor\\tenant\\testprocessthrowswhenidisnotstring',
        1 => 'tests\\unit\\tenant\\presentation\\api\\processor\\tenant\\testprocessdispatchesandreturnsoutput',
        2 => 'tests\\unit\\tenant\\presentation\\api\\processor\\tenant\\createtenantresult',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Tenant/Presentation/Api/Processor/Tenant/DeleteTenantProcessorTest.php' => 
    array (
      0 => 'c75dbe4d386c75f5aa886f95eb29fd6c485ddf82',
      1 => 
      array (
        0 => 'tests\\unit\\tenant\\presentation\\api\\processor\\tenant\\deletetenantprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\tenant\\presentation\\api\\processor\\tenant\\testprocessthrowswhenidisnotstring',
        1 => 'tests\\unit\\tenant\\presentation\\api\\processor\\tenant\\testprocessdispatchescommand',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Tenant/Presentation/Api/Processor/Tenant/UpdateTenantProcessorTest.php' => 
    array (
      0 => '39bebc4c0b5c2e2bba685e459b7c1df64b458926',
      1 => 
      array (
        0 => 'tests\\unit\\tenant\\presentation\\api\\processor\\tenant\\updatetenantprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\tenant\\presentation\\api\\processor\\tenant\\testprocessthrowswhenidisnotstring',
        1 => 'tests\\unit\\tenant\\presentation\\api\\processor\\tenant\\testprocessdispatchesandreturnsoutput',
        2 => 'tests\\unit\\tenant\\presentation\\api\\processor\\tenant\\createtenantresult',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Tenant/Presentation/Api/Provider/Tenant/GetTenantProviderTest.php' => 
    array (
      0 => '12366ec0704e84636ee1b6607805d72dfcbf2869',
      1 => 
      array (
        0 => 'tests\\unit\\tenant\\presentation\\api\\provider\\tenant\\gettenantprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\tenant\\presentation\\api\\provider\\tenant\\testprovidethrowswhenunauthenticated',
        1 => 'tests\\unit\\tenant\\presentation\\api\\provider\\tenant\\testprovidethrowswhenidmissing',
        2 => 'tests\\unit\\tenant\\presentation\\api\\provider\\tenant\\testprovidemapsresult',
        3 => 'tests\\unit\\tenant\\presentation\\api\\provider\\tenant\\testprovidemapsnotfound',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Tenant/Presentation/Api/Provider/Tenant/ListTenantsProviderTest.php' => 
    array (
      0 => '41ffd4a0a4160a59b92fdbfbaf7e1c5d5ce256a4',
      1 => 
      array (
        0 => 'tests\\unit\\tenant\\presentation\\api\\provider\\tenant\\listtenantsprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\tenant\\presentation\\api\\provider\\tenant\\testprovidethrowswhenunauthenticated',
        1 => 'tests\\unit\\tenant\\presentation\\api\\provider\\tenant\\testprovidemapstenants',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/Tenant/Presentation/Api/Resource/TenantResourceTest.php' => 
    array (
      0 => '45ec7ac6341a1bc74b684cf99e727e3cda63f06a',
      1 => 
      array (
        0 => 'tests\\unit\\tenant\\presentation\\api\\resource\\tenantresourcetest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\tenant\\presentation\\api\\resource\\testresourcecanbeinstantiated',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/TrustedDevice/Application/UseCase/Command/TrustedDevice/RevokeAllDevices/RevokeAllDevicesHandlerTest.php' => 
    array (
      0 => 'c80afc10d877570b6cd556f4db4eb5811f5d84b6',
      1 => 
      array (
        0 => 'tests\\unit\\trusteddevice\\application\\usecase\\command\\trusteddevice\\revokealldevices\\revokealldeviceshandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\trusteddevice\\application\\usecase\\command\\trusteddevice\\revokealldevices\\testinvokerevokesalldevices',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/TrustedDevice/Application/UseCase/Command/TrustedDevice/RevokeDevice/RevokeDeviceHandlerTest.php' => 
    array (
      0 => '92ae7e0949a51c55c54f752df162ea2c821bef04',
      1 => 
      array (
        0 => 'tests\\unit\\trusteddevice\\application\\usecase\\command\\trusteddevice\\revokedevice\\revokedevicehandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\trusteddevice\\application\\usecase\\command\\trusteddevice\\revokedevice\\testinvokerevokesdeviceforowner',
        1 => 'tests\\unit\\trusteddevice\\application\\usecase\\command\\trusteddevice\\revokedevice\\testinvokethrowswhendevicemissing',
        2 => 'tests\\unit\\trusteddevice\\application\\usecase\\command\\trusteddevice\\revokedevice\\testinvokethrowswhenusermismatch',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/TrustedDevice/Application/UseCase/Command/TrustedDevice/TrustDevice/TrustDeviceHandlerTest.php' => 
    array (
      0 => 'fcc9985961469bfa2596b308370fbd8a4454bfeb',
      1 => 
      array (
        0 => 'tests\\unit\\trusteddevice\\application\\usecase\\command\\trusteddevice\\trustdevice\\trustdevicehandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\trusteddevice\\application\\usecase\\command\\trusteddevice\\trustdevice\\testinvokecreatesnewdevicealways',
        1 => 'tests\\unit\\trusteddevice\\application\\usecase\\command\\trusteddevice\\trustdevice\\testinvokecreatesnewdevicewhenmissing',
        2 => 'tests\\unit\\trusteddevice\\application\\usecase\\command\\trusteddevice\\trustdevice\\testinvokecreatesnewdevicewhenexistinginvalid',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/TrustedDevice/Application/UseCase/Query/TrustedDevice/CheckDeviceTrusted/CheckDeviceTrustedHandlerTest.php' => 
    array (
      0 => 'e24480b368fb7c7f5af45845ebc23fa0cb34b134',
      1 => 
      array (
        0 => 'tests\\unit\\trusteddevice\\application\\usecase\\query\\trusteddevice\\checkdevicetrusted\\checkdevicetrustedhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\trusteddevice\\application\\usecase\\query\\trusteddevice\\checkdevicetrusted\\testinvokereturnsnottrustedwhennodevice',
        1 => 'tests\\unit\\trusteddevice\\application\\usecase\\query\\trusteddevice\\checkdevicetrusted\\testinvokereturnsnottrustedwhentokeninvalid',
        2 => 'tests\\unit\\trusteddevice\\application\\usecase\\query\\trusteddevice\\checkdevicetrusted\\testinvokereturnsnottrustedwhenusermismatch',
        3 => 'tests\\unit\\trusteddevice\\application\\usecase\\query\\trusteddevice\\checkdevicetrusted\\testinvokemarkstrustedwhenvalid',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/TrustedDevice/Application/UseCase/Query/TrustedDevice/CheckDeviceTrusted/CheckDeviceTrustedResultTest.php' => 
    array (
      0 => '02a8f7d91590f24e3e671b815653790902abf2aa',
      1 => 
      array (
        0 => 'tests\\unit\\trusteddevice\\application\\usecase\\query\\trusteddevice\\checkdevicetrusted\\checkdevicetrustedresulttest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\trusteddevice\\application\\usecase\\query\\trusteddevice\\checkdevicetrusted\\testtrustedfactory',
        1 => 'tests\\unit\\trusteddevice\\application\\usecase\\query\\trusteddevice\\checkdevicetrusted\\testnottrustedfactory',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/TrustedDevice/Application/UseCase/Query/TrustedDevice/ListTrustedDevices/ListTrustedDevicesHandlerTest.php' => 
    array (
      0 => 'c75e33ab72d44d3da73328da566d76d51fd4b099',
      1 => 
      array (
        0 => 'tests\\unit\\trusteddevice\\application\\usecase\\query\\trusteddevice\\listtrusteddevices\\listtrusteddeviceshandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\trusteddevice\\application\\usecase\\query\\trusteddevice\\listtrusteddevices\\testinvokereturnsonlyvaliddevices',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/TrustedDevice/Domain/Event/DeviceRevokedEventTest.php' => 
    array (
      0 => '2c781d685ee4d673fe80681f105ab8a580443f8f',
      1 => 
      array (
        0 => 'tests\\unit\\trusteddevice\\domain\\event\\devicerevokedeventtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\trusteddevice\\domain\\event\\testcanbecreated',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/TrustedDevice/Domain/Event/DeviceTrustedEventTest.php' => 
    array (
      0 => '8659524c20122a596a096759d85243712d7f61b0',
      1 => 
      array (
        0 => 'tests\\unit\\trusteddevice\\domain\\event\\devicetrustedeventtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\trusteddevice\\domain\\event\\testcanbecreated',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/TrustedDevice/Domain/Exception/TrustedDeviceNotFoundExceptionTest.php' => 
    array (
      0 => 'fafbd6bcd2ea120b179d23398fd2a7c992db7076',
      1 => 
      array (
        0 => 'tests\\unit\\trusteddevice\\domain\\exception\\trusteddevicenotfoundexceptiontest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\trusteddevice\\domain\\exception\\testcreate',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/TrustedDevice/Domain/Model/TrustedDeviceTest.php' => 
    array (
      0 => '388465d204ad0e2fd194e03d2a91a8a94e8579c7',
      1 => 
      array (
        0 => 'tests\\unit\\trusteddevice\\domain\\model\\trusteddevicetest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\trusteddevice\\domain\\model\\testtrustcreatesdeviceandevents',
        1 => 'tests\\unit\\trusteddevice\\domain\\model\\testverifyusestokenandrevocation',
        2 => 'tests\\unit\\trusteddevice\\domain\\model\\testrevokeisidempotent',
        3 => 'tests\\unit\\trusteddevice\\domain\\model\\testtouchupdateslastusedat',
        4 => 'tests\\unit\\trusteddevice\\domain\\model\\testreconstitutesupportsexpiredandrevokedstates',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/TrustedDevice/Domain/ValueObject/DeviceFingerprintTest.php' => 
    array (
      0 => 'beacaf0adc5be47e9af7ef27f9cd2b17f6da23e6',
      1 => 
      array (
        0 => 'tests\\unit\\trusteddevice\\domain\\valueobject\\devicefingerprinttest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\trusteddevice\\domain\\valueobject\\testcreategeneratesstablehashandmatches',
        1 => 'tests\\unit\\trusteddevice\\domain\\valueobject\\testfromhashkeepsprovidedvalues',
        2 => 'tests\\unit\\trusteddevice\\domain\\valueobject\\testgetdevicenamedetectsbrowserandos',
        3 => 'tests\\unit\\trusteddevice\\domain\\valueobject\\devicenameprovider',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/TrustedDevice/Domain/ValueObject/DeviceTokenTest.php' => 
    array (
      0 => '0cdbc0cf8a2011d056e06f95a76e8a98e450ab77',
      1 => 
      array (
        0 => 'tests\\unit\\trusteddevice\\domain\\valueobject\\devicetokentest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\trusteddevice\\domain\\valueobject\\testgeneratecreatestokenwithplainandhash',
        1 => 'tests\\unit\\trusteddevice\\domain\\valueobject\\testplainthrowswhennotavailable',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/TrustedDevice/Infrastructure/Persistence/Doctrine/Mapper/TrustedDeviceMapperTest.php' => 
    array (
      0 => 'c5c30f26bff0089bc9cb39dacf65964561cfa47b',
      1 => 
      array (
        0 => 'tests\\unit\\trusteddevice\\infrastructure\\persistence\\doctrine\\mapper\\trusteddevicemappertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\trusteddevice\\infrastructure\\persistence\\doctrine\\mapper\\testtorecordmapsdomainfields',
        1 => 'tests\\unit\\trusteddevice\\infrastructure\\persistence\\doctrine\\mapper\\testtodomainmapsrecordfields',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/TrustedDevice/Infrastructure/Persistence/Doctrine/Record/TrustedDeviceRecordTest.php' => 
    array (
      0 => '487ef683838345e1a3621340e5a53866111f9ef0',
      1 => 
      array (
        0 => 'tests\\unit\\trusteddevice\\infrastructure\\persistence\\doctrine\\record\\trusteddevicerecordtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\trusteddevice\\infrastructure\\persistence\\doctrine\\record\\testsettersandgetters',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/TrustedDevice/Presentation/Api/EventSubscriber/TrustedDeviceCookieListenerTest.php' => 
    array (
      0 => '39f824c8190e1fb6f607863e8b3950cfe72d576f',
      1 => 
      array (
        0 => 'tests\\unit\\trusteddevice\\presentation\\api\\eventsubscriber\\trusteddevicecookielistenertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\trusteddevice\\presentation\\api\\eventsubscriber\\testonkernelresponseaddscookiewhenpresent',
        1 => 'tests\\unit\\trusteddevice\\presentation\\api\\eventsubscriber\\testonkernelresponseskipswhennocookie',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/TrustedDevice/Presentation/Api/Processor/TrustedDevice/RevokeAllDevicesProcessorTest.php' => 
    array (
      0 => 'f43c06d0c094a2109691b2c71c1b428b6b8a92a2',
      1 => 
      array (
        0 => 'tests\\unit\\trusteddevice\\presentation\\api\\processor\\trusteddevice\\revokealldevicesprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\trusteddevice\\presentation\\api\\processor\\trusteddevice\\testprocessthrowsbadrequestwhenusermissing',
        1 => 'tests\\unit\\trusteddevice\\presentation\\api\\processor\\trusteddevice\\testprocessdispatchescommandwhenauthenticated',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/TrustedDevice/Presentation/Api/Processor/TrustedDevice/RevokeDeviceProcessorTest.php' => 
    array (
      0 => '220cc6058315e0f346581b38de77f16210de9df3',
      1 => 
      array (
        0 => 'tests\\unit\\trusteddevice\\presentation\\api\\processor\\trusteddevice\\revokedeviceprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\trusteddevice\\presentation\\api\\processor\\trusteddevice\\testprocessthrowsbadrequestwhenusermissing',
        1 => 'tests\\unit\\trusteddevice\\presentation\\api\\processor\\trusteddevice\\testprocessthrowsbadrequestwhenidmissing',
        2 => 'tests\\unit\\trusteddevice\\presentation\\api\\processor\\trusteddevice\\testprocessthrowsnotfoundwhendevicemissing',
        3 => 'tests\\unit\\trusteddevice\\presentation\\api\\processor\\trusteddevice\\testprocessunwrapsnotfoundfrompreviousexception',
        4 => 'tests\\unit\\trusteddevice\\presentation\\api\\processor\\trusteddevice\\testprocessdispatchescommandwhenvalid',
        5 => 'tests\\unit\\trusteddevice\\presentation\\api\\processor\\trusteddevice\\createusermock',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/TrustedDevice/Presentation/Api/Processor/TrustedDevice/TrustDeviceProcessorTest.php' => 
    array (
      0 => '38e3d40c567b7f7bd7a5c534bf6fef895821d27c',
      1 => 
      array (
        0 => 'tests\\unit\\trusteddevice\\presentation\\api\\processor\\trusteddevice\\trustdeviceprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\trusteddevice\\presentation\\api\\processor\\trusteddevice\\testprocesscreatescookieandoutput',
        1 => 'tests\\unit\\trusteddevice\\presentation\\api\\processor\\trusteddevice\\testprocessthrowsbadrequestwhenusermissing',
        2 => 'tests\\unit\\trusteddevice\\presentation\\api\\processor\\trusteddevice\\testprocessthrowsbadrequestwhenrequestmissing',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/TrustedDevice/Presentation/Api/Provider/TrustedDevice/ListTrustedDevicesProviderTest.php' => 
    array (
      0 => '598097aaef1ff4ef5002dd3ae21bb22b51d09859',
      1 => 
      array (
        0 => 'tests\\unit\\trusteddevice\\presentation\\api\\provider\\trusteddevice\\listtrusteddevicesprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\trusteddevice\\presentation\\api\\provider\\trusteddevice\\testprovidethrowswhennotauthenticated',
        1 => 'tests\\unit\\trusteddevice\\presentation\\api\\provider\\trusteddevice\\testprovidereturnsoutputsforuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/TrustedDevice/Presentation/Api/Resource/TrustedDeviceResourceTest.php' => 
    array (
      0 => '2910feafe9d93c1c145157994346a4588edb2360',
      1 => 
      array (
        0 => 'tests\\unit\\trusteddevice\\presentation\\api\\resource\\trusteddeviceresourcetest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\trusteddevice\\presentation\\api\\resource\\testresourcecanbeinstantiated',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/TrustedDevice/Presentation/Api/Service/TrustedDeviceCookieServiceTest.php' => 
    array (
      0 => '73dcb6cfea1b6845a4100ff099c71fe45ccb4498',
      1 => 
      array (
        0 => 'tests\\unit\\trusteddevice\\presentation\\api\\service\\trusteddevicecookieservicetest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\trusteddevice\\presentation\\api\\service\\testgetcookienameaddshostprefixinprod',
        1 => 'tests\\unit\\trusteddevice\\presentation\\api\\service\\testgetcookienameusesbaseinnonprod',
        2 => 'tests\\unit\\trusteddevice\\presentation\\api\\service\\testcreatecookiesetsattributes',
        3 => 'tests\\unit\\trusteddevice\\presentation\\api\\service\\testcreateclearcookieexpiresinpast',
        4 => 'tests\\unit\\trusteddevice\\presentation\\api\\service\\testgettokenfromrequest',
        5 => 'tests\\unit\\trusteddevice\\presentation\\api\\service\\testcookiesecureoverridedisableshostprefix',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/User/Application/EventHandler/UserCreatedEventHandlerTest.php' => 
    array (
      0 => '1a45ae128fab8bcd0ed2d5b16e09370080dbb477',
      1 => 
      array (
        0 => 'tests\\unit\\user\\application\\eventhandler\\usercreatedeventhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\user\\application\\eventhandler\\testinvokelogsusercreated',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/User/Application/EventHandler/UserEmailVerifiedEventHandlerTest.php' => 
    array (
      0 => '58730ec4c461f97b993b308ec9a2cdd81e361b6a',
      1 => 
      array (
        0 => 'tests\\unit\\user\\application\\eventhandler\\useremailverifiedeventhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\user\\application\\eventhandler\\testinvokesendsmercurenotificationandlogsuseremailverified',
        1 => 'tests\\unit\\user\\application\\eventhandler\\testinvokelogswarningwhennotificationdispatchfails',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/User/Application/UseCase/Command/User/CreateUser/CreateUserHandlerTest.php' => 
    array (
      0 => '4206863ff55718afacdfba21924eb4eeff0da077',
      1 => 
      array (
        0 => 'tests\\unit\\user\\application\\usecase\\command\\user\\createuser\\createuserhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\user\\application\\usecase\\command\\user\\createuser\\setup',
        1 => 'tests\\unit\\user\\application\\usecase\\command\\user\\createuser\\testcreatesanewuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/User/Application/UseCase/Command/User/DeleteUser/DeleteUserHandlerTest.php' => 
    array (
      0 => '4a969c3036f5410e8b78704d16cae49e1251c912',
      1 => 
      array (
        0 => 'tests\\unit\\user\\application\\usecase\\command\\user\\deleteuser\\deleteuserhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\user\\application\\usecase\\command\\user\\deleteuser\\testinvokethrowswhenmissing',
        1 => 'tests\\unit\\user\\application\\usecase\\command\\user\\deleteuser\\testinvokedeletesuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/User/Application/UseCase/Command/User/UpdateUser/UpdateUserHandlerTest.php' => 
    array (
      0 => 'ca71efd2efcefcb13f079801ec5fa5971ee37d3c',
      1 => 
      array (
        0 => 'tests\\unit\\user\\application\\usecase\\command\\user\\updateuser\\updateuserhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\user\\application\\usecase\\command\\user\\updateuser\\testinvokethrowswhenmissing',
        1 => 'tests\\unit\\user\\application\\usecase\\command\\user\\updateuser\\testinvokeupdatesprofile',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/User/Application/UseCase/Command/User/UserStatusCommandTest.php' => 
    array (
      0 => '191c29e6e1daf11cc4828f3345c6f662f3146856',
      1 => 
      array (
        0 => 'tests\\unit\\user\\application\\usecase\\command\\user\\userstatuscommandtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\user\\application\\usecase\\command\\user\\testcommandsexposeids',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/User/Application/UseCase/Command/User/UserStatusHandlerTest.php' => 
    array (
      0 => '830797f32cf54a1591cec17d857fce67152ef0d4',
      1 => 
      array (
        0 => 'tests\\unit\\user\\application\\usecase\\command\\user\\userstatushandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\user\\application\\usecase\\command\\user\\testactivateuserthrowswhenmissing',
        1 => 'tests\\unit\\user\\application\\usecase\\command\\user\\testactivateusersavesuser',
        2 => 'tests\\unit\\user\\application\\usecase\\command\\user\\testdeactivateuserthrowswhenmissing',
        3 => 'tests\\unit\\user\\application\\usecase\\command\\user\\testdeactivateusersavesuser',
        4 => 'tests\\unit\\user\\application\\usecase\\command\\user\\testverifyuseremailthrowswhenmissing',
        5 => 'tests\\unit\\user\\application\\usecase\\command\\user\\testverifyuseremailpublishesevents',
        6 => 'tests\\unit\\user\\application\\usecase\\command\\user\\createuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/User/Application/UseCase/Query/User/AuthenticateUser/AuthenticateUserHandlerTest.php' => 
    array (
      0 => 'b08c7d9d2e03f40a951a56d3973e73b3b1e616a2',
      1 => 
      array (
        0 => 'tests\\unit\\user\\application\\usecase\\query\\user\\authenticateuser\\authenticateuserhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\user\\application\\usecase\\query\\user\\authenticateuser\\setup',
        1 => 'tests\\unit\\user\\application\\usecase\\query\\user\\authenticateuser\\testauthenticatesvalidcredentials',
        2 => 'tests\\unit\\user\\application\\usecase\\query\\user\\authenticateuser\\testfailsifusernotfound',
        3 => 'tests\\unit\\user\\application\\usecase\\query\\user\\authenticateuser\\testfailsifpasswordinvalid',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/User/Application/UseCase/Query/User/GetCurrentUserProfile/GetCurrentUserProfileHandlerTest.php' => 
    array (
      0 => '6259234146f8b3a6f1bd261a71d5b21431de4738',
      1 => 
      array (
        0 => 'tests\\unit\\user\\application\\usecase\\query\\user\\getcurrentuserprofile\\getcurrentuserprofilehandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\user\\application\\usecase\\query\\user\\getcurrentuserprofile\\testinvokereturnscurrentuserprofile',
        1 => 'tests\\unit\\user\\application\\usecase\\query\\user\\getcurrentuserprofile\\testinvokethrowswhenauthenticatedusercannotberesolved',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/User/Application/UseCase/Query/User/GetUser/GetUserHandlerTest.php' => 
    array (
      0 => '8fc63f8dbbcbef89b19457d7b5ef81c142e7ad21',
      1 => 
      array (
        0 => 'tests\\unit\\user\\application\\usecase\\query\\user\\getuser\\getuserhandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\user\\application\\usecase\\query\\user\\getuser\\testinvokereturnsnullwhenmissing',
        1 => 'tests\\unit\\user\\application\\usecase\\query\\user\\getuser\\testinvokemapsuserview',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/User/Application/UseCase/Query/User/ListUsers/ListUsersHandlerTest.php' => 
    array (
      0 => 'b88c793f3ca8cc3b3c4f7385dfd2ba89e48dd8b4',
      1 => 
      array (
        0 => 'tests\\unit\\user\\application\\usecase\\query\\user\\listusers\\listusershandlertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\user\\application\\usecase\\query\\user\\listusers\\testinvokereturnspaginatedresult',
        1 => 'tests\\unit\\user\\application\\usecase\\query\\user\\listusers\\testinvokepassestenantidtorepository',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/User/Domain/Event/UserCreatedEventTest.php' => 
    array (
      0 => 'b2200c19d014d4ef5e147382c7c859c75a2d4120',
      1 => 
      array (
        0 => 'tests\\unit\\user\\domain\\event\\usercreatedeventtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\user\\domain\\event\\testpayload',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/User/Domain/Event/UserEmailVerifiedEventTest.php' => 
    array (
      0 => 'f9c696212b5a4cb90485afdeb59d988cdbd1aec3',
      1 => 
      array (
        0 => 'tests\\unit\\user\\domain\\event\\useremailverifiedeventtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\user\\domain\\event\\testpayload',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/User/Domain/Exception/InvalidPasswordExceptionTest.php' => 
    array (
      0 => 'a3242d3c8c116b412d0f27d3c2b165cc99697735',
      1 => 
      array (
        0 => 'tests\\unit\\user\\domain\\exception\\invalidpasswordexceptiontest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\user\\domain\\exception\\testtooweakcreatesmessage',
        1 => 'tests\\unit\\user\\domain\\exception\\testincorrectcreatesmessage',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/User/Domain/Exception/InvalidUserExceptionTest.php' => 
    array (
      0 => '23daff6401e27ab8bd60865a02c6fba0b728e9fd',
      1 => 
      array (
        0 => 'tests\\unit\\user\\domain\\exception\\invaliduserexceptiontest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\user\\domain\\exception\\testlockedaccountcreatesmessage',
        1 => 'tests\\unit\\user\\domain\\exception\\testemailnotverifiedcreatesmessage',
        2 => 'tests\\unit\\user\\domain\\exception\\testinactiveaccountcreatesmessage',
        3 => 'tests\\unit\\user\\domain\\exception\\testcannotloginincludesreason',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/User/Domain/Exception/UserAlreadyExistsExceptionTest.php' => 
    array (
      0 => '4a634a43b34e811acec6081ff2c32788e0b40495',
      1 => 
      array (
        0 => 'tests\\unit\\user\\domain\\exception\\useralreadyexistsexceptiontest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\user\\domain\\exception\\testwithusernamecreatesmessage',
        1 => 'tests\\unit\\user\\domain\\exception\\testwithemailcreatesmessage',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/User/Domain/Exception/UserNotFoundExceptionTest.php' => 
    array (
      0 => '5f49e19b254adb8dac9da900031ce1eb8c5079ad',
      1 => 
      array (
        0 => 'tests\\unit\\user\\domain\\exception\\usernotfoundexceptiontest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\user\\domain\\exception\\testwithidcreatesmessage',
        1 => 'tests\\unit\\user\\domain\\exception\\testwithusernamecreatesmessage',
        2 => 'tests\\unit\\user\\domain\\exception\\testwithemailcreatesmessage',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/User/Domain/Model/Permission/PermissionTest.php' => 
    array (
      0 => '3376fa2ca4676a938c73a3e421448cd994897ff9',
      1 => 
      array (
        0 => 'tests\\unit\\user\\domain\\model\\permission\\permissiontest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\user\\domain\\model\\permission\\testmatchesandequals',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/User/Domain/Model/UserTest.php' => 
    array (
      0 => '04e1c3e7f85f70dce8b5f69ce8f4c341fbf1ea9e',
      1 => 
      array (
        0 => 'tests\\unit\\user\\domain\\model\\usertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\user\\domain\\model\\testcanberegistered',
        1 => 'tests\\unit\\user\\domain\\model\\testcanauthenticate',
        2 => 'tests\\unit\\user\\domain\\model\\testverifyemailisidempotent',
        3 => 'tests\\unit\\user\\domain\\model\\testauthenticatethrowsoninvalidpasswordandlocksaccount',
        4 => 'tests\\unit\\user\\domain\\model\\testupdateprofileandchangepassword',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/User/Domain/ValueObject/HashedPasswordTest.php' => 
    array (
      0 => 'cb6d3ee2e3c27382b7774989513e8df595a422da',
      1 => 
      array (
        0 => 'tests\\unit\\user\\domain\\valueobject\\hashedpasswordtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\user\\domain\\valueobject\\testverifiescorrectly',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/User/Domain/ValueObject/PermissionIdTest.php' => 
    array (
      0 => '57aa0a1133f1e47a1c4d6c6c83e766cd4ec892c2',
      1 => 
      array (
        0 => 'tests\\unit\\user\\domain\\valueobject\\permissionidtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\user\\domain\\valueobject\\testcreatewithvaliduuid',
        1 => 'tests\\unit\\user\\domain\\valueobject\\testcreatewithinvaliduuidthrowsexception',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/User/Domain/ValueObject/PermissionNameTest.php' => 
    array (
      0 => 'a98520f2c4dc12ca58a54f466bcd83110674ef1a',
      1 => 
      array (
        0 => 'tests\\unit\\user\\domain\\valueobject\\permissionnametest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\user\\domain\\valueobject\\testmatcheswildcard',
        1 => 'tests\\unit\\user\\domain\\valueobject\\testmatchesexactandwildcardall',
        2 => 'tests\\unit\\user\\domain\\valueobject\\testmatchesreturnsfalsewhenresourcediffers',
        3 => 'tests\\unit\\user\\domain\\valueobject\\testresourceandactionaccessors',
        4 => 'tests\\unit\\user\\domain\\valueobject\\testequalsandtostring',
        5 => 'tests\\unit\\user\\domain\\valueobject\\testinvalidnamethrowsexception',
        6 => 'tests\\unit\\user\\domain\\valueobject\\testemptynamethrowsexception',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/User/Domain/ValueObject/RoleIdTest.php' => 
    array (
      0 => '0ca04e6c6a5a6e4a6a606d0adbaf2bd5a23ec23d',
      1 => 
      array (
        0 => 'tests\\unit\\user\\domain\\valueobject\\roleidtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\user\\domain\\valueobject\\testcreatewithvaliduuid',
        1 => 'tests\\unit\\user\\domain\\valueobject\\testcreatewithinvaliduuidthrowsexception',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/User/Domain/ValueObject/RoleNameTest.php' => 
    array (
      0 => '09edb3949880db74bd1e0c19145bedddcde4262e',
      1 => 
      array (
        0 => 'tests\\unit\\user\\domain\\valueobject\\rolenametest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\user\\domain\\valueobject\\testcreatewithvalidname',
        1 => 'tests\\unit\\user\\domain\\valueobject\\testinvalidnamethrowsexception',
        2 => 'tests\\unit\\user\\domain\\valueobject\\testemptynamethrowsexception',
        3 => 'tests\\unit\\user\\domain\\valueobject\\testtostringreturnsvalue',
        4 => 'tests\\unit\\user\\domain\\valueobject\\testequalscomparesvalue',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/User/Domain/ValueObject/UserIdTest.php' => 
    array (
      0 => '95f64b8a2311bf77007774912197a4ad2e5776c8',
      1 => 
      array (
        0 => 'tests\\unit\\user\\domain\\valueobject\\useridtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\user\\domain\\valueobject\\testcanbecreatedwithvaliduuid',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/User/Domain/ValueObject/UsernameTest.php' => 
    array (
      0 => '9454a8db34082c90283a322d179a668220e10728',
      1 => 
      array (
        0 => 'tests\\unit\\user\\domain\\valueobject\\usernametest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\user\\domain\\valueobject\\testvalidatesformat',
        1 => 'tests\\unit\\user\\domain\\valueobject\\testthrowsoninvalidformat',
        2 => 'tests\\unit\\user\\domain\\valueobject\\testthrowsonemptyvalue',
        3 => 'tests\\unit\\user\\domain\\valueobject\\testthrowsoninvalidcharacters',
        4 => 'tests\\unit\\user\\domain\\valueobject\\testequals',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/User/Domain/ValueObject/UserProfileTest.php' => 
    array (
      0 => 'f704f89ca08b13e1b4063d51cc5de893f08ad701',
      1 => 
      array (
        0 => 'tests\\unit\\user\\domain\\valueobject\\userprofiletest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\user\\domain\\valueobject\\testhandlesfullname',
        1 => 'tests\\unit\\user\\domain\\valueobject\\testchecksequality',
        2 => 'tests\\unit\\user\\domain\\valueobject\\testrejectsemptynames',
        3 => 'tests\\unit\\user\\domain\\valueobject\\testrejectstoolongnames',
        4 => 'tests\\unit\\user\\domain\\valueobject\\testrejectsinvalidavatarurl',
        5 => 'tests\\unit\\user\\domain\\valueobject\\testequalsconsidersavatarurl',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/User/Domain/ValueObject/UserStatusTest.php' => 
    array (
      0 => 'fed35ae24fa0ea0ec3d85f88cdbd6c3cc3b8aed0',
      1 => 
      array (
        0 => 'tests\\unit\\user\\domain\\valueobject\\userstatustest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\user\\domain\\valueobject\\testhascorrectmethods',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/User/Infrastructure/Adapter/User/UserDataPurgeAdapterTest.php' => 
    array (
      0 => 'd348a81b6f9a25273948d0b6bc0749f50739482a',
      1 => 
      array (
        0 => 'tests\\unit\\user\\infrastructure\\adapter\\user\\userdatapurgeadaptertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\user\\infrastructure\\adapter\\user\\testpurgeskipswhenuseridblank',
        1 => 'tests\\unit\\user\\infrastructure\\adapter\\user\\testpurgeexecutesdeletequeries',
        2 => 'tests\\unit\\user\\infrastructure\\adapter\\user\\createquerybuildermock',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/User/Infrastructure/Console/CreateUserConsoleCommandTest.php' => 
    array (
      0 => '445020fb50f1636b10a9284a6d13d47d382a2bbf',
      1 => 
      array (
        0 => 'tests\\unit\\user\\infrastructure\\console\\createuserconsolecommandtest',
        1 => 'tests\\unit\\user\\infrastructure\\console\\testquestionhelper',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\user\\infrastructure\\console\\testexecutedispatchescommandwithpasswordargument',
        1 => 'tests\\unit\\user\\infrastructure\\console\\testexecutepromptsforpasswordwhenmissing',
        2 => 'tests\\unit\\user\\infrastructure\\console\\testexecutereturnsfailureonpasswordmismatch',
        3 => 'tests\\unit\\user\\infrastructure\\console\\testexecutereturnsfailurewhenemailnotstring',
        4 => 'tests\\unit\\user\\infrastructure\\console\\testexecuterejectsinvalidpasswordthenacceptsvalid',
        5 => 'tests\\unit\\user\\infrastructure\\console\\testexecutereturnsfailurewhenpasswordpromptnotstring',
        6 => 'tests\\unit\\user\\infrastructure\\console\\testexecutereturnsfailurewhendispatchthrows',
        7 => 'tests\\unit\\user\\infrastructure\\console\\createcommand',
        8 => 'tests\\unit\\user\\infrastructure\\console\\__construct',
        9 => 'tests\\unit\\user\\infrastructure\\console\\ask',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/User/Infrastructure/Console/ListUsersCommandTest.php' => 
    array (
      0 => '955fc9adec0f287d677955aefd433c1793e657f3',
      1 => 
      array (
        0 => 'tests\\unit\\user\\infrastructure\\console\\listuserscommandtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\user\\infrastructure\\console\\testexecuteoutputsnousersmessage',
        1 => 'tests\\unit\\user\\infrastructure\\console\\testexecutelistsusers',
        2 => 'tests\\unit\\user\\infrastructure\\console\\createuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/User/Infrastructure/DataFixtures/UserFixturesTest.php' => 
    array (
      0 => 'd11dacb9cfdc390fe9a56af7c96084b6180179ef',
      1 => 
      array (
        0 => 'tests\\unit\\user\\infrastructure\\datafixtures\\userfixturestest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\user\\infrastructure\\datafixtures\\testgetgroupsreturnsusergroup',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/User/Infrastructure/Image/AvatarResizerTest.php' => 
    array (
      0 => 'a98225e0edfaf7d9d2293837cffbffd9fde02057',
      1 => 
      array (
        0 => 'tests\\unit\\user\\infrastructure\\image\\avatarresizertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\user\\infrastructure\\image\\testresizewritesallvariants',
        1 => 'tests\\unit\\user\\infrastructure\\image\\testdeleteremovesexistingvariants',
        2 => 'tests\\unit\\user\\infrastructure\\image\\testdeleteskipsmissingvariants',
        3 => 'tests\\unit\\user\\infrastructure\\image\\testdeleteskipspartiallymissingvariants',
        4 => 'tests\\unit\\user\\infrastructure\\image\\testsizesconstantcontainsfourentries',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/User/Infrastructure/Persistence/Doctrine/Mapper/UserMapperTest.php' => 
    array (
      0 => '223065ed13ea38e61899d4ad3fdd90c5aa09e768',
      1 => 
      array (
        0 => 'tests\\unit\\user\\infrastructure\\persistence\\doctrine\\mapper\\usermappertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\user\\infrastructure\\persistence\\doctrine\\mapper\\testtorecordmapsuser',
        1 => 'tests\\unit\\user\\infrastructure\\persistence\\doctrine\\mapper\\testupdaterecordupdatesfields',
        2 => 'tests\\unit\\user\\infrastructure\\persistence\\doctrine\\mapper\\testtodomainmapsrecord',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/User/Infrastructure/Persistence/Doctrine/Record/UserRecordTest.php' => 
    array (
      0 => '43ffb6d410d426a84067f88f930e1cc8dbf61a8b',
      1 => 
      array (
        0 => 'tests\\unit\\user\\infrastructure\\persistence\\doctrine\\record\\userrecordtest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\user\\infrastructure\\persistence\\doctrine\\record\\testconstructorinitializesrolescollection',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/User/Infrastructure/Persistence/Doctrine/Repository/UserRepositoryTest.php' => 
    array (
      0 => '292ce4fed96155538651234488fef711ebfc2ac7',
      1 => 
      array (
        0 => 'tests\\unit\\user\\infrastructure\\persistence\\doctrine\\repository\\userrepositorytest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\user\\infrastructure\\persistence\\doctrine\\repository\\testsavecreatesnewrecord',
        1 => 'tests\\unit\\user\\infrastructure\\persistence\\doctrine\\repository\\testsaveupdatesexistingrecord',
        2 => 'tests\\unit\\user\\infrastructure\\persistence\\doctrine\\repository\\testfindbyidreturnsnullwhenmissing',
        3 => 'tests\\unit\\user\\infrastructure\\persistence\\doctrine\\repository\\testfindbyemailusesmapper',
        4 => 'tests\\unit\\user\\infrastructure\\persistence\\doctrine\\repository\\testexistsbyusernamereturnstrue',
        5 => 'tests\\unit\\user\\infrastructure\\persistence\\doctrine\\repository\\testdeleteremovesreference',
        6 => 'tests\\unit\\user\\infrastructure\\persistence\\doctrine\\repository\\createuser',
        7 => 'tests\\unit\\user\\infrastructure\\persistence\\doctrine\\repository\\createuserrecord',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/User/Presentation/Api/Processor/User/CreateUserProcessorTest.php' => 
    array (
      0 => '0239fb9cd3aac3109596f4fa4b106ec6a10ee961',
      1 => 
      array (
        0 => 'tests\\unit\\user\\presentation\\api\\processor\\user\\createuserprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\user\\presentation\\api\\processor\\user\\setup',
        1 => 'tests\\unit\\user\\presentation\\api\\processor\\user\\testprocessescreationrequest',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/User/Presentation/Api/Processor/User/DeleteUserProcessorTest.php' => 
    array (
      0 => '81d8f2a031966a583b2af259ed45d1c1755127b4',
      1 => 
      array (
        0 => 'tests\\unit\\user\\presentation\\api\\processor\\user\\deleteuserprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\user\\presentation\\api\\processor\\user\\testprocessreturnswhenidmissing',
        1 => 'tests\\unit\\user\\presentation\\api\\processor\\user\\testprocessdispatchescommand',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/User/Presentation/Api/Processor/User/UpdateCurrentUserProfileProcessorTest.php' => 
    array (
      0 => '509ba9f52cebbf4dfe8c52209bf0cfc88a4cc2e0',
      1 => 
      array (
        0 => 'tests\\unit\\user\\presentation\\api\\processor\\user\\updatecurrentuserprofileprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\user\\presentation\\api\\processor\\user\\testprocessreturnsnullwhendatainvalid',
        1 => 'tests\\unit\\user\\presentation\\api\\processor\\user\\testprocessthrowswhennotauthenticated',
        2 => 'tests\\unit\\user\\presentation\\api\\processor\\user\\testprocessupdatesandmapscurrentuserprofile',
        3 => 'tests\\unit\\user\\presentation\\api\\processor\\user\\testprocessmapsmissingauthenticatedusertonotfound',
        4 => 'tests\\unit\\user\\presentation\\api\\processor\\user\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/User/Presentation/Api/Processor/User/UpdateUserProcessorTest.php' => 
    array (
      0 => '44c8d38c6fd056205bb6e88f06563ea4a8630247',
      1 => 
      array (
        0 => 'tests\\unit\\user\\presentation\\api\\processor\\user\\updateuserprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\user\\presentation\\api\\processor\\user\\testprocessreturnsnullwhendatainvalid',
        1 => 'tests\\unit\\user\\presentation\\api\\processor\\user\\testprocessreturnsnullwhenidmissing',
        2 => 'tests\\unit\\user\\presentation\\api\\processor\\user\\testprocessmapsoutput',
        3 => 'tests\\unit\\user\\presentation\\api\\processor\\user\\testprocessreturnsnullwhenusermissing',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/User/Presentation/Api/Processor/User/UploadCurrentUserAvatarProcessorTest.php' => 
    array (
      0 => 'fabf8b3f0b22f908f752ec87fc7c8412374e4037',
      1 => 
      array (
        0 => 'tests\\unit\\user\\presentation\\api\\processor\\user\\uploadcurrentuseravatarprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\user\\presentation\\api\\processor\\user\\testprocessthrowswhennotauthenticated',
        1 => 'tests\\unit\\user\\presentation\\api\\processor\\user\\testprocessdelegatesforauthenticateduser',
        2 => 'tests\\unit\\user\\presentation\\api\\processor\\user\\createuploaduseravatarprocessor',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/User/Presentation/Api/Processor/User/UploadUserAvatarProcessorTest.php' => 
    array (
      0 => 'b2232859c2805a3701f8ae694b97db676c882e5a',
      1 => 
      array (
        0 => 'tests\\unit\\user\\presentation\\api\\processor\\user\\uploaduseravatarprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\user\\presentation\\api\\processor\\user\\testprocessreturnsnullwhenidmissing',
        1 => 'tests\\unit\\user\\presentation\\api\\processor\\user\\testprocessreturnsnullwhennorequest',
        2 => 'tests\\unit\\user\\presentation\\api\\processor\\user\\testprocessthrowswhennofileattached',
        3 => 'tests\\unit\\user\\presentation\\api\\processor\\user\\testprocessthrowswhenmimetypeinvalid',
        4 => 'tests\\unit\\user\\presentation\\api\\processor\\user\\testprocessdispatchescommandandreturnsoutput',
        5 => 'tests\\unit\\user\\presentation\\api\\processor\\user\\testprocessreturnsnullwhenqueryreturnsnouser',
        6 => 'tests\\unit\\user\\presentation\\api\\processor\\user\\makeprocessor',
        7 => 'tests\\unit\\user\\presentation\\api\\processor\\user\\makeuserview',
        8 => 'tests\\unit\\user\\presentation\\api\\processor\\user\\createtempfile',
        9 => 'tests\\unit\\user\\presentation\\api\\processor\\user\\minimalpngbinary',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/User/Presentation/Api/Processor/User/UserStatusProcessorTest.php' => 
    array (
      0 => 'a988882d9c5155f6605b58b74a4f13a0c67cc3d4',
      1 => 
      array (
        0 => 'tests\\unit\\user\\presentation\\api\\processor\\user\\userstatusprocessortest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\user\\presentation\\api\\processor\\user\\testactivatereturnsnullwhenidmissing',
        1 => 'tests\\unit\\user\\presentation\\api\\processor\\user\\testactivatemapsoutput',
        2 => 'tests\\unit\\user\\presentation\\api\\processor\\user\\testactivatereturnsnullwhenusermissing',
        3 => 'tests\\unit\\user\\presentation\\api\\processor\\user\\testdeactivatereturnsnullwhenidmissing',
        4 => 'tests\\unit\\user\\presentation\\api\\processor\\user\\testdeactivatemapsoutput',
        5 => 'tests\\unit\\user\\presentation\\api\\processor\\user\\testdeactivatereturnsnullwhenusermissing',
        6 => 'tests\\unit\\user\\presentation\\api\\processor\\user\\testverifyemailreturnsnullwhenidmissing',
        7 => 'tests\\unit\\user\\presentation\\api\\processor\\user\\testverifyemailmapsoutput',
        8 => 'tests\\unit\\user\\presentation\\api\\processor\\user\\testverifyemailreturnsnullwhenusermissing',
        9 => 'tests\\unit\\user\\presentation\\api\\processor\\user\\createuserview',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/User/Presentation/Api/Provider/User/GetCurrentUserProfileProviderTest.php' => 
    array (
      0 => '249899c9c990a9c7d71b4dcf2120635d1d6163fa',
      1 => 
      array (
        0 => 'tests\\unit\\user\\presentation\\api\\provider\\user\\getcurrentuserprofileprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\user\\presentation\\api\\provider\\user\\testprovidemapscurrentuserprofile',
        1 => 'tests\\unit\\user\\presentation\\api\\provider\\user\\testprovidethrowswhennotauthenticated',
        2 => 'tests\\unit\\user\\presentation\\api\\provider\\user\\testprovidemapsmissingauthenticatedusertonotfound',
        3 => 'tests\\unit\\user\\presentation\\api\\provider\\user\\createsecurityuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/User/Presentation/Api/Provider/User/GetUserAvatarProviderTest.php' => 
    array (
      0 => 'b34259cac9665196c7dd2f325b82f08a0aa485c6',
      1 => 
      array (
        0 => 'tests\\unit\\user\\presentation\\api\\provider\\user\\getuseravatarprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\user\\presentation\\api\\provider\\user\\testprovidethrows404whenusernotfound',
        1 => 'tests\\unit\\user\\presentation\\api\\provider\\user\\testprovidethrows404whenavatarfilemissing',
        2 => 'tests\\unit\\user\\presentation\\api\\provider\\user\\testprovidereturnswebpresponsewithdefaultsize',
        3 => 'tests\\unit\\user\\presentation\\api\\provider\\user\\testsizeurivariableresolvestonearestvariant',
        4 => 'tests\\unit\\user\\presentation\\api\\provider\\user\\testsizequeryparamresolvestonearestvariant',
        5 => 'tests\\unit\\user\\presentation\\api\\provider\\user\\sizeresolutionprovider',
        6 => 'tests\\unit\\user\\presentation\\api\\provider\\user\\makeuser',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/User/Presentation/Api/Provider/User/ListUsersProviderTest.php' => 
    array (
      0 => '452fedf52f14a651a697f9d68f94ffad61070ac6',
      1 => 
      array (
        0 => 'tests\\unit\\user\\presentation\\api\\provider\\user\\listusersprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\user\\presentation\\api\\provider\\user\\testprovidemapsusers',
        1 => 'tests\\unit\\user\\presentation\\api\\provider\\user\\testprovidepassessearchandsortingtoquery',
        2 => 'tests\\unit\\user\\presentation\\api\\provider\\user\\testprovidepassestenantidfromcaller',
        3 => 'tests\\unit\\user\\presentation\\api\\provider\\user\\testprovidedeniesnulltenantidwithoutsuperadmin',
        4 => 'tests\\unit\\user\\presentation\\api\\provider\\user\\testprovidethrowswhenunauthenticated',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/User/Presentation/Api/Provider/User/ListUserStatusesProviderTest.php' => 
    array (
      0 => '0566894e7e006d0283d20e09dfdaeced8ae7daf0',
      1 => 
      array (
        0 => 'tests\\unit\\user\\presentation\\api\\provider\\user\\listuserstatusesprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\user\\presentation\\api\\provider\\user\\testprovidethrowswhenunauthenticated',
        1 => 'tests\\unit\\user\\presentation\\api\\provider\\user\\testprovidereturnssupporteduserstatuses',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/User/Presentation/Api/Provider/User/UserProviderTest.php' => 
    array (
      0 => 'e8907d74716f3b04720e9761710a6ab2a7848d41',
      1 => 
      array (
        0 => 'tests\\unit\\user\\presentation\\api\\provider\\user\\userprovidertest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\user\\presentation\\api\\provider\\user\\setup',
        1 => 'tests\\unit\\user\\presentation\\api\\provider\\user\\testprovidesuserresource',
        2 => 'tests\\unit\\user\\presentation\\api\\provider\\user\\testreturnsnullifusernotfound',
        3 => 'tests\\unit\\user\\presentation\\api\\provider\\user\\testreturnsnullifidmissing',
      ),
      3 => 
      array (
      ),
    ),
    '/var/www/html/tests/Unit/User/Presentation/Api/Resource/UserResourceTest.php' => 
    array (
      0 => 'a8df9f70c05e480d64a86e2b7d77ab81d870f303',
      1 => 
      array (
        0 => 'tests\\unit\\user\\presentation\\api\\resource\\userresourcetest',
      ),
      2 => 
      array (
        0 => 'tests\\unit\\user\\presentation\\api\\resource\\testresourcecanbeinstantiated',
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