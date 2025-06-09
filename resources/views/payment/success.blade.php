<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Success</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container vh-100 d-flex justify-content-center align-items-center">
    <div class="card shadow-sm border-0 p-4">
        <div class="text-center">
            <div class="text-success display-1 mb-3">✓</div>
            <h2 class="mb-3">Payment Successful</h2>
            <p class="text-muted mb-4">
                Thank you! Your reservation has been confirmed.
            </p>
            @if(request()->has('booking'))
                <p><strong>Booking Reference:</strong> {{ request('booking') }}</p>
            @endif
            <a href="{{ url('/') }}" class="btn btn-success mt-3">Return to Home</a>
        </div>
    </div>
</div>

</body>
</html>
