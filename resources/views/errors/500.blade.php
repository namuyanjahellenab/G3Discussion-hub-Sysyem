@include('errors.layout', [
    'code' => 500,
    'icon' => 'fa-bolt',
    'iconClass' => 'danger',
    'title' => 'Something broke on our end',
    'message' => "That's on us, not you. We've logged the error — try again in a moment.",
])
