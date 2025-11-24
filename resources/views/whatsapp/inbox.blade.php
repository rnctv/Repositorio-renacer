@extends('layouts.app')

@section('content')
<div class="container mt-4">

    <h2 class="mb-4">📥 Mensajes Entrantes de WhatsApp</h2>

    <div class="card shadow-sm">
        <div class="card-body" style="max-height: 600px; overflow-y: auto;">

            @foreach($messages as $msg)
                <div class="p-3 mb-3 border rounded" style="background: #f5f5f5;">
                    <strong>{{ $msg->from_number }}</strong>
                    <small class="text-muted float-end">{{ $msg->received_at }}</small>

                    <p class="mt-2 mb-0">{{ $msg->message }}</p>
                </div>
            @endforeach

        </div>
    </div>

</div>
@endsection
