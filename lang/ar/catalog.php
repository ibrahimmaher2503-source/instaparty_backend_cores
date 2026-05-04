<?php

declare(strict_types=1);

return array_replace_recursive(
    require base_path('app/Modules/Catalog/Resources/lang/en/catalog.php'),
    require base_path('app/Modules/Catalog/Resources/lang/ar/catalog.php'),
);
