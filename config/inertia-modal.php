<?php

return [
    /*
     * The shared props that should be excluded from the modal response (when
     * not forcing refresh). This is useful for data that is used in the
     * backdrop only and could be stale when the modal is open.
     */
    'exclude_shared_props' => [
        // 'auth',
    ],
];
