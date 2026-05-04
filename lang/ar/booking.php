<?php

declare(strict_types=1);

return array_replace_recursive(
    require base_path('app/Modules/Booking/Resources/lang/en/booking.php'),
    require base_path('app/Modules/Booking/Resources/lang/ar/booking.php'),
);
