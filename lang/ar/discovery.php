<?php

declare(strict_types=1);

return array_replace_recursive(
    require base_path('app/Modules/Discovery/Resources/lang/en/discovery.php'),
    require base_path('app/Modules/Discovery/Resources/lang/ar/discovery.php'),
);
