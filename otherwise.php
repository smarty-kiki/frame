<?php

function otherwise($assertion, $description = 'assertion is not true', $exception_class_name = 'Exception')
{
    if (! $assertion) {
        throw new $exception_class_name($description);
    }
}
