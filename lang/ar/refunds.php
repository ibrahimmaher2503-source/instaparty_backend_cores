<?php

declare(strict_types=1);

return array_replace_recursive(
    require base_path('app/Modules/Payments/Resources/lang/en/refunds.php'),
    require base_path('app/Modules/Payments/Resources/lang/ar/refunds.php'),
);
