<?php

declare(strict_types=1);

return array_replace_recursive(
    require base_path('app/Modules/Communication/Resources/lang/en/communication.php'),
    require base_path('app/Modules/Communication/Resources/lang/ar/communication.php'),
);
