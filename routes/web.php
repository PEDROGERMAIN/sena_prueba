<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/prueba-debug', function () {
    $mascota = "Luna";
    $edad = 3;
    $peso = 12.5;

    $resultado = $edad >= 2 ? "Adulta" : "Cachorra";

    return "Mascota: $mascota - Estado: $resultado";
});