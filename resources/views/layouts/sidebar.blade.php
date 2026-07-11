@if(auth()->user()->Role === 'Administrator')
    @include('layouts.sidebar-admin')
@elseif(auth()->user()->Role === 'Lecturer')
    @include('layouts.sidebar-lecturer')
@else
    @include('layouts.sidebar-student')
@endif