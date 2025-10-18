<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models', function ($user) {
    return !blank($user);
});
