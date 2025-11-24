<?php

use App\Http\Controllers\WhatsappController;

// Webhook (GET + POST)
Route::get('/webhook/whatsapp', [WhatsappController::class, 'webhook']);
Route::post('/webhook/whatsapp', [WhatsappController::class, 'webhook']);

// WhatsApp Inbox
Route::get('/whatsapp/inbox', [WhatsappController::class, 'inbox']);
