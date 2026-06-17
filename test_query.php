<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$cpus = App\Models\Cpu::where('name', 'like', '%Ryzen 7%')->get(['id', 'name', 'price'])->toArray();
print_r($cpus);
