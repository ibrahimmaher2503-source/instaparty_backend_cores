<?php

declare(strict_types=1);

return array_replace_recursive(
    require base_path('app/Modules/Geography/Resources/lang/en/geography.php'),
    require base_path('app/Modules/Geography/Resources/lang/ar/geography.php'),
);
