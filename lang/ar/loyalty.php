<?php

declare(strict_types=1);

return array_replace_recursive(
    require base_path('app/Modules/Loyalty/Resources/lang/en/loyalty.php'),
    require base_path('app/Modules/Loyalty/Resources/lang/ar/loyalty.php'),
);
