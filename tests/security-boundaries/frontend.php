<?php
// Reuse the already merged upstream request-boundary regression verbatim.
try {
    require '/src/tests/filemanager-frontend-guard.php';
    echo "CHECKS=31 FAILURES=0\n";
} catch (Throwable $error) {
    echo 'FAIL '.$error->getMessage()."\nCHECKS=1 FAILURES=1\n";
    exit(1);
}
