<?php

declare(strict_types=1);

return array_replace_recursive(
    require base_path('app/Modules/Settlement/Resources/lang/en/settlement.php'),
    require base_path('app/Modules/Settlement/Resources/lang/ar/settlement.php'),
);
