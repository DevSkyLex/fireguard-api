# Billing test discovery consolidation

The ten former `tests/Billing` files contained 38 tests outside every PHPUnit suite.
Comparison was against the same relative paths under `tests/Unit/Billing`.

| Former test | Preserved coverage |
| --- | --- |
| `Application/Service/BillingPriceCatalogTest.php` | All 7 original cases and assertions already present; canonical suite adds malformed catalog cases |
| `Application/UseCase/CancelSubscriptionHandlerTest.php` | Exact copy after namespace normalization |
| `Application/UseCase/GetOrganizationInvoicesHandlerTest.php` | Both original cases and assertions present; non-expectation repository mocks became stubs |
| `Application/UseCase/GetOrganizationPaymentMethodHandlerTest.php` | Exact copy after namespace normalization |
| `Application/UseCase/GetOrganizationSubscriptionHandlerTest.php` | Exact copy after namespace normalization |
| `Application/UseCase/HandleStripeWebhookHandlerTest.php` | Three original distinct scenarios moved to `HandleStripeWebhookBasicScenariosTest.php`; stronger reconciliation cases remain in the canonical handler test |
| `Application/UseCase/ResumeSubscriptionHandlerTest.php` | Exact copy after namespace normalization |
| `Domain/SubscriptionTest.php` | Exact copy after namespace normalization |
| `Presentation/Api/Provider/GetPaymentMethodProviderTest.php` | Four original cases and assertions present; obsolete provider-local HTTP503 expectation replaced by exact exception preservation and a functional gateway→query bus→central mapper HTTP503 test |
| `Presentation/Api/Provider/GetSubscriptionProviderTest.php` | All original cases and assertions present; canonical suite adds unauthenticated denial |

`python -B .github/scripts/check-test-discovery.py` fails for test classes outside
all three explicit PHPUnit configurations and repeated class names. PHPAt rules
in `tests/Architecture/Rule` use PHPStan's separately configured extension.
