
```sh
sha256sum --check cuidado-integrity-inputs.sha256
```


```sh
docker compose exec backend php artisan test --filter=StrictJsonAndStaticAnalysisTest
docker compose exec backend php artisan test --filter='StrictInputValidationTest|CanonicalInputTest|ProfileMedicationIntegrityTest|ProcessStateIntegrityTest'
```


```sh
php tests/security/php-sast.php
```
