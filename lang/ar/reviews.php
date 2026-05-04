<?php

declare(strict_types=1);

return array_replace_recursive(
    require base_path('app/Modules/Reviews/Resources/lang/en/reviews.php'),
    require base_path('app/Modules/Reviews/Resources/lang/ar/reviews.php'),
);
