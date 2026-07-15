@include('errors.layout', [
    'code' => 404,
    'icon' => 'fa-magnifying-glass',
    'title' => 'Page not found',
    'message' => "The page you're looking for doesn't exist, or may have been moved.",
])
