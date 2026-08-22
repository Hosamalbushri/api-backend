<?php

use Illuminate\Support\Facades\Route;

if (file_exists(__DIR__.'/../packages/NexaBiz/Identity/src/Routes/api.php')) {
    require __DIR__.'/../packages/NexaBiz/Identity/src/Routes/api.php';
}

if (file_exists(__DIR__.'/../packages/NexaBiz/Initialization/src/Routes/api.php')) {
    require __DIR__.'/../packages/NexaBiz/Initialization/src/Routes/api.php';
}

if (file_exists(__DIR__.'/../packages/NexaBiz/Synchronization/src/Routes/api.php')) {
    require __DIR__.'/../packages/NexaBiz/Synchronization/src/Routes/api.php';
}
