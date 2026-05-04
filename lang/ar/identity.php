<?php

declare(strict_types=1);

return array_replace_recursive(
    require base_path('app/Modules/Identity/Resources/lang/en/identity.php'),
    require base_path('app/Modules/Identity/Resources/lang/ar/identity.php'),
);
